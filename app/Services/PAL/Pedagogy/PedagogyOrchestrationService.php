<?php

namespace App\Services\PAL\Pedagogy;

use App\Services\PAL\Pedagogy\PedagogySelectorEngine;
use App\Services\PAL\Pedagogy\CognitiveLoadEngine;
use App\Services\PAL\Pedagogy\EmotionalSafetyEngine;
use App\Services\PAL\Pedagogy\MetacognitionEngine;
use App\Services\PAL\Pedagogy\PedagogyFatigueEngine;
use App\Services\PAL\Content\ContentIntelligenceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pedagogy Orchestration Service
 * Dynamic orchestration engine that selects best learning strategy
 */
class PedagogyOrchestrationService
{
    protected PedagogySelectorEngine $selector;
    protected CognitiveLoadEngine $cognitive;
    protected EmotionalSafetyEngine $emotional;
    protected MetacognitionEngine $metacognition;
    protected PedagogyFatigueEngine $fatigue;
    protected ContentIntelligenceService $content;

    public function __construct(
        PedagogySelectorEngine $selector,
        CognitiveLoadEngine $cognitive,
        EmotionalSafetyEngine $emotional,
        MetacognitionEngine $metacognition,
        PedagogyFatigueEngine $fatigue,
        ContentIntelligenceService $content
    ) {
        $this->selector = $selector;
        $this->cognitive = $cognitive;
        $this->emotional = $emotional;
        $this->metacognition = $metacognition;
        $this->fatigue = $fatigue;
        $this->content = $content;
    }

    /**
     * Get recommended pedagogy for learner
     * @param int $learnerId
     * @param int $conceptId
     * @param array $context
     * @return array
     */
    public function getRecommendation(int $learnerId, int $conceptId, array $context = []): array
    {
        $selected = $this->selector->select($learnerId, $conceptId, $context);
        
        // Check cognitive load
        $cognitiveLoad = $this->cognitive->assess($learnerId);
        
        // Apply cognitive load adjustments
        if ($cognitiveLoad['status'] === 'overloaded') {
            $selected = $this->cognitive->adjustPedagogy($selected, $cognitiveLoad);
        }
        
        // Apply emotional safety rules
        $selected = $this->emotional->apply($selected);
        
        // Check for pedagogy fatigue
        $fatigueStatus = $this->fatigue->check($learnerId);
        if ($fatigueStatus['is_fatigued']) {
            $selected = $this->fatigue->rotate($selected);

            // Rotation replaces the pedagogy the Tier 1 rule chose, so the rule
            // no longer describes this decision. Dropping the attribution keeps
            // the audit column honest: the log is meant to answer "how did this
            // rule perform?", and crediting a rule with a pedagogy that was
            // overridden after it fired would poison exactly that analysis.
            unset($selected['tier_1_rule']);
        }

        // Phase 8: a strategy name alone is not a recommendation the student can
        // act on. Hand the chosen pedagogy to the real content pipeline
        // (content_master via the sidecar, not the near-empty legacy pal_contents
        // table) so the caller gets an actual content id/url in the same
        // response, with the pipeline's own deterministic exhausted/fallback
        // handling rather than a fake placeholder.
        $contentRecommendation = $this->content->selectOptimalContent(
            $learnerId,
            $conceptId,
            $context['sub_institute_id'] ?? null,
            [
                'content_type' => $context['content_type'] ?? 'concept',
                'learning_style' => $selected['type'] ?? null,
                'failed_format' => $context['failed_format'] ?? null,
                'chapter_id' => $context['chapter_id'] ?? null,
            ]
        );

        $selectionReason = $this->selector->getReason($learnerId, $selected['type']);

        $this->logRecommendation($learnerId, $conceptId, $context, $selected, $selectionReason, $contentRecommendation);

        return [
            'learner_id' => $learnerId,
            'concept_id' => $conceptId,
            'recommended_pedagogy' => $selected,
            'alternative_pedagogies' => $this->selector->getAlternatives($conceptId),
            'cognitive_load_status' => $cognitiveLoad['status'],
            'cognitive_load_level' => $cognitiveLoad['load_level'] ?? 0,
            'cognitive_load_display' => $this->formatCognitiveLoadDisplay($cognitiveLoad),
            'fatigue_status' => $fatigueStatus,
            'selection_reason' => $selectionReason,
            'sequence' => $this->getPedagogySequence($selected),
            'content_recommendation' => $contentRecommendation,
        ];
    }

    /**
     * Spec Phase 18: log every recommendation with enough context to later
     * evaluate whether it worked. Guarded by Schema::hasTable() because the
     * pal_recommendation_log migration (2026_08_19_120000) has not been run
     * against every environment yet -- degrades to a no-op rather than
     * breaking recommendation calls until it is. mastery_after/outcome are
     * deliberately left null here; they belong to a separate call once the
     * student has actually acted on the recommendation, not to the moment of
     * recommending.
     */
    protected function logRecommendation(
        int $learnerId,
        int $conceptId,
        array $context,
        array $selectedPedagogy,
        string $selectionReason,
        array $contentRecommendation
    ): void {
        if (! Schema::hasTable('pal_recommendation_log')) {
            return;
        }

        // The rule-key columns arrive in a later migration than the table, so an
        // environment that has one and not the other must still be able to log.
        // Without this check the insert 500s on EVERY recommendation there —
        // turning a missing audit column into an outage, which is the opposite
        // of what the surrounding hasTable() guard is for.
        $hasRuleKeys = Schema::hasColumn('pal_recommendation_log', 'tier_1_rule_key');

        $masteryBefore = \App\Models\PAL\Competency::where('learner_id', $learnerId)
            ->when($context['subject_id'] ?? null, fn ($q, $subjectId) => $q->where('subject_id', $subjectId))
            ->avg('mastery_score');

        $content = $contentRecommendation['content'] ?? null;

        $ruleKeys = $hasRuleKeys ? [
            // Which authored rule actually decided this, as a queryable value
            // rather than a phrase inside selection_reason. NULL means the
            // hardcoded path resolved it and no authored rule fired — a real
            // distinction, and the one that says how much of the engine is
            // genuinely live.
            'tier_1_rule_key' => $selectedPedagogy['tier_1_rule']['rule_key'] ?? null,
            'tier_4_rule_key' => $selectedPedagogy['tier_4_style']['rule_key'] ?? null,
        ] : [];

        DB::table('pal_recommendation_log')->insert($ruleKeys + [
            'learner_id' => $learnerId,
            'concept_id' => $conceptId,
            'subject_id' => $context['subject_id'] ?? null,
            'sub_institute_id' => $context['sub_institute_id'] ?? null,
            'mastery_before' => $masteryBefore,
            'had_data_before' => $masteryBefore !== null,
            'pedagogy_type' => $selectedPedagogy['type'] ?? null,
            'selection_reason' => $selectionReason,
            'content_master_id' => $content['content_master_id'] ?? $content['id'] ?? null,
            'content_format' => $content['format'] ?? null,
            'content_exhausted' => (bool) ($contentRecommendation['exhausted'] ?? false),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Get content variants for a pedagogy
     * @param int $conceptId
     * @param string $pedagogyType
     * @return array
     */
    public function getContentVariants(int $conceptId, string $pedagogyType): array
    {
        return \App\Models\PAL\Content::where('concept_id', $conceptId)
            ->whereJsonContains('pedagogy_tags', $pedagogyType)
            ->where('status', 'active')
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'type' => $c->content_type,
                'format' => $c->format,
                'difficulty' => $c->difficulty_level,
                'bloom_level' => $c->bloom_level,
            ])
            ->toArray();
    }

    /**
     * Track pedagogy effectiveness
     * @param int $learnerId
     * @param string $pedagogyType
     * @param string $outcome
     * @return void
     */
    public function trackEffectiveness(int $learnerId, string $pedagogyType, string $outcome, array $context = []): array
    {
        $record = $this->selector->recordOutcome($learnerId, $pedagogyType, $outcome, $context);

        return [
            'id' => $record->id,
            'learner_id' => $learnerId,
            'pedagogy_type' => $pedagogyType,
            'outcome' => $outcome,
        ];
    }

    /**
     * Get metacognitive prompts
     * @param int $learnerId
     * @param string $context
     * @return array
     */
    public function getMetacognitivePrompts(int $learnerId, string $context = 'general'): array
    {
        return $this->metacognition->getPrompts($learnerId, $context);
    }

    /**
     * Record reflection from learner
     * @param int $learnerId
     * @param array $reflection
     * @return \App\Models\PAL\Reflection
     */
    public function recordReflection(int $learnerId, array $reflection): \App\Models\PAL\Reflection
    {
        return $this->metacognition->record($learnerId, $reflection);
    }

    protected function getPedagogySequence(array $primary): array
    {
        return [
            $primary,
            ['type' => 'fallback', 'weight' => 0.2],
        ];
    }

    /**
     * Format cognitive load for display in the UI.
     */
    protected function formatCognitiveLoadDisplay(array $cognitiveLoad): string
    {
        $status = $cognitiveLoad['status'] ?? 'unknown';
        $loadLevel = (int) ($cognitiveLoad['load_level'] ?? 0);

        return match ($status) {
            'critical' => 'Critical (' . $loadLevel . '/10)',
            'overloaded' => 'High (' . $loadLevel . '/10)',
            'elevated' => 'Elevated (' . $loadLevel . '/10)',
            'normal' => 'Normal (' . $loadLevel . '/10)',
            default => 'Not enough data (' . $loadLevel . '/10)',
        };
    }
}
