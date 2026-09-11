<?php

namespace App\Services\PAL\Pedagogy;

use App\Models\PAL\LearnerPreference;
use App\Models\PAL\PedagogyEffectiveness;
use App\Services\Eso\EsoPolicyService;
use App\Services\PAL\Framework\FrameworkCatalogService;

/**
 * Pedagogy Selector Engine
 * Five-tier pedagogy selection based on learner state and concept requirements
 *
 * TIER 1 IS NOW RULE-DRIVEN. It resolves through the authored rows in
 * `pal_pedagogy_engine_rules` (see PedagogyRuleBands) rather than through
 * thresholds hardcoded here. That closes a real divergence: the authored Tier 1
 * bands on `bkt_mastery` in five bands (<0.40 / 0.40-0.69 / 0.70-0.84 /
 * 0.85-0.92 / >=0.93), while this class historically banded
 * avg(Competency.mastery_score) in three (<50 / 50-75 / >75) — a different
 * metric, a different band count and different boundaries, with the authored
 * set never consulted by anything a student could reach.
 *
 * Tiers 2-5 are still the hardcoded approximations below and are the next ones
 * to migrate. They are left in place deliberately: moving one tier at a time
 * keeps each change reviewable against the rules it implements.
 */
class PedagogySelectorEngine
{
    public function __construct(
        private readonly FrameworkCatalogService $catalog,
        private readonly PedagogyRuleBands $bands,
        private ?EsoPolicyService $eso = null
    ) {
    }

    /**
     * EsoPolicyService is resolved on first use, never injected eagerly.
     *
     * Constructor-injecting it closes a dependency cycle:
     *
     *   PedagogySelectorEngine -> EsoPolicyService -> EsoEnrichmentResolver
     *     -> PedagogySuggestedContentService -> PedagogyOrchestrationService
     *     -> PedagogySelectorEngine
     *
     * which the container follows until it exhausts memory. Deferring to call
     * time breaks it: by the time select() runs, this singleton is already
     * built and cached, so the chain terminates. PedagogySuggestedContentService
     * takes its own PedagogyOrchestrationService optionally for the same
     * reason — this is that established pattern, not a new workaround.
     *
     * The parameter stays constructor-settable so a test can inject a double
     * without touching the container.
     */
    protected function eso(): EsoPolicyService
    {
        return $this->eso ??= app(EsoPolicyService::class);
    }

    /**
     * Select best pedagogy using 5-tier process
     * @param int $learnerId
     * @param int $conceptId
     * @param array $context
     * @return array
     */
    public function select(int $learnerId, int $conceptId, array $context = []): array
    {
        // Tier 4 decorates whatever the tiers below decide. It is applied here,
        // around every return path, rather than inside each one — the authored
        // section is explicit that Tier 4 "does not change what is taught, it
        // changes the format it arrives in", so it must never be one of the
        // branches competing to pick a pedagogy.
        return $this->applyLearningStyleFormat(
            $this->selectPedagogy($learnerId, $conceptId, $context),
            $learnerId
        );
    }

    /**
     * Tier 4: reorder the candidate's H5P formats to match the learner's
     * authored learning-style preference.
     *
     * Deliberately narrow. It reorders formats the chosen pedagogy already
     * allows and never introduces one it does not — a preference for Image
     * Hotspot cannot make Image Hotspot valid for a pedagogy whose catalog
     * entry excludes it. `type` is never touched.
     *
     * Returns the candidate unchanged when the learner has no recorded style,
     * the rules are unseeded, or the style has no authored rule.
     */
    protected function applyLearningStyleFormat(array $candidate, int $learnerId): array
    {
        if (! $this->tierEnabled('tier-4')) {
            return $candidate;
        }

        $rule = $this->bands->learningStyleRule($this->learningStyleFor($learnerId));
        if ($rule === null) {
            return $candidate;
        }

        $allowed = $candidate['h5p_types'] ?? [];

        if (is_array($allowed) && $allowed !== []) {
            $preferred = array_values(array_intersect($rule['h5p_priority'], $allowed));
            $rest = array_values(array_diff($allowed, $preferred));

            $candidate['h5p_types'] = array_merge($preferred, $rest);
        }

        $candidate['tier_4_style'] = [
            'rule_key' => $rule['rule_key'],
            'learning_style' => $rule['learning_style'],
            'h5p_priority' => $rule['h5p_priority'],
            'avoid' => $rule['avoid'],
            'source' => 'pal_pedagogy_engine_rules',
        ];

        return $candidate;
    }

    /**
     * Whether an authored tier is switched on.
     *
     * Defaults to FALSE for an unlisted tier, not true: a tier that has not
     * been explicitly enabled has not been reviewed for rollout, and silently
     * running an unreviewed tier in front of students is the failure this
     * switch exists to prevent.
     */
    protected function tierEnabled(string $tier): bool
    {
        return (bool) config("pal_content.pedagogy.tiers.{$tier}", false);
    }

    /** The learner's recorded modality preference, or null when none is set. */
    protected function learningStyleFor(int $learnerId): ?string
    {
        return LearnerPreference::where('learner_id', $learnerId)
            ->where('pref_key', 'learning_style')
            ->value('pref_value');
    }

    /**
     * Tiers 1-3 and 5: choose the pedagogy itself.
     *
     * This is select()'s original body, unchanged apart from the name. Split
     * out so Tier 4's format layer can wrap every one of its return paths
     * without being interleaved into them.
     */
    protected function selectPedagogy(int $learnerId, int $conceptId, array $context = []): array
    {
        if (!empty($context['teacher_override'])) {
            return $this->buildCandidate(
                $this->catalog->normalizePedagogy((string) $context['teacher_override']),
                100,
                'Teacher override'
            );
        }

        // Tier 1 - Hard Constraints
        $constraints = $this->checkConstraints($learnerId, $context);
        if ($constraints['blocked']) {
            return $constraints['fallback'];
        }

        // Tier 2 - Learner State
        $learnerState = $this->getLearnerState($learnerId);

        // Tier 3 - Concept Requirements
        $conceptReqs = $this->getConceptRequirements($conceptId);

        // Tier 4 - Historical Effectiveness
        $historical = $this->getHistoricalEffectiveness($learnerId);

        // Tier 5 - Novelty & Fatigue
        $novelty = $this->checkNovelty($learnerId);

        if (($context['type'] ?? null) === 'spaced_review' || !empty($context['spaced_review'])) {
            return $this->buildCandidate('flashcard', 98, 'Spaced review due');
        }

        if ($this->tierEnabled('tier-2') && ($learnerState['declining_engagement'] ?? false) === true) {
            return $this->buildCandidate('game_based', 92, 'Engagement is declining');
        }

        if (($context['new_concept'] ?? false) === true) {
            return $this->buildCandidate(
                $conceptReqs['recommended_pedagogy'] ?? 'inquiry_based',
                88,
                'New concept default'
            );
        }

        // Tier 1 — the authored mastery rules.
        //
        // Placed after the three special-case shortcuts above rather than
        // literally first, even though the authored section says Tier 1 "runs
        // first". Those shortcuts are this class's existing stand-ins for
        // Tier 5 (spaced review due) and Tier 2 (engagement declining), and
        // the authored rules have both of those tiers overriding Tier 1
        // anyway. `new_concept` cannot conflict: a concept with no evidence
        // yields a null estimate, which resolves to no band at all.
        $tier1 = $this->resolveTier1Rule($learnerId, $conceptId, $context);
        if ($tier1 !== null) {
            return $tier1;
        }

        // Combine all factors
        return $this->combineSelections($learnerState, $conceptReqs, $historical, $novelty);
    }

    /**
     * Tier 1 of the authored Pedagogy Engine: map this learner's BKT mastery
     * on this concept to the band an author wrote, and serve that band's
     * pedagogy.
     *
     * Returns null — falling through to the previous hardcoded path — in every
     * case where the rules cannot answer:
     *
     *   - the rules table is absent or unseeded
     *   - no tenant in context, so BKT cannot be scoped
     *   - the learner has no evidence on the concept yet (diagnose, don't band)
     *   - the estimate falls outside every authored range
     *   - the matched band names no resolvable pedagogy
     *
     * The fallback is what makes this safe to ship: an environment that has not
     * run PalPedagogyEngineSeeder behaves exactly as it did before.
     */
    protected function resolveTier1Rule(int $learnerId, int $conceptId, array $context): ?array
    {
        // Kill switch. Off returns this tier to the hardcoded path below, which
        // is still present and still correct — so disabling a tier degrades
        // rather than breaking, and does not affect any other tier.
        if (! $this->tierEnabled('tier-1')) {
            return null;
        }

        if (! $this->bands->available()) {
            return null;
        }

        $subInstituteId = $context['sub_institute_id'] ?? null;
        if ($subInstituteId === null || (int) $subInstituteId <= 0) {
            return null;
        }

        // BKT comes from EsoPolicyService, which owns it. Recomputing it here
        // would recreate the exact split this change exists to close.
        $bkt = $this->eso()->conceptMasteryEstimate($learnerId, $conceptId, (int) $subInstituteId);

        $band = $this->bands->resolveMasteryBand($bkt);
        if ($band === null) {
            return null;
        }

        $pedagogy = $band['pedagogy_tags'][0] ?? null;
        if ($pedagogy === null) {
            return null;
        }

        $candidate = $this->buildCandidate(
            $pedagogy,
            95,
            sprintf(
                'Pedagogy Engine Tier 1 (%s): bkt_mastery %.2f matches authored rule %s',
                $band['label'] ?? $band['rule_key'],
                $bkt,
                $band['rule_key']
            )
        );

        // The rule's own directives travel with the decision. Without these a
        // caller gets a pedagogy name and loses everything else the author
        // specified — the content type to serve, the Bloom ceiling not to
        // exceed, how much scaffolding to show.
        $candidate['tier_1_rule'] = [
            'rule_key' => $band['rule_key'],
            'band' => $band['label'],
            'bkt_mastery' => $bkt,
            'content_type' => $band['content_type'],
            'bloom_ceiling' => $band['bloom_ceiling'],
            'difficulty' => $band['difficulty'],
            'scaffolding' => $band['scaffolding'],
            'source' => 'pal_pedagogy_engine_rules',
        ];

        return $candidate;
    }

    /**
     * Get alternative pedagogies
     * @param int $conceptId
     * @return array
     */
    public function getAlternatives(int $conceptId): array
    {
        $concept = \App\Models\PAL\Concept::find($conceptId);
        
        if (!$concept) {
            return $this->getDefaultAlternatives();
        }

        $tags = $concept->pedagogy_tags ?? ['inquiry_based'];
        $alternatives = [];

        foreach ($tags as $tag) {
            $normalized = $this->catalog->normalizePedagogy((string) $tag);
            if ($normalized !== 'primary') {
                $alternatives[] = $this->buildCandidate($normalized, 60, 'Available for concept');
            }
        }

        return array_merge($alternatives, $this->getDefaultAlternatives());
    }

    /**
     * Get reason for selection
     * @param int $learnerId
     * @param string $pedagogyType
     * @return string
     */
    public function getReason(int $learnerId, string $pedagogyType): string
    {
        $learnerState = $this->getLearnerState($learnerId);
        $pedagogyType = $this->catalog->normalizePedagogy($pedagogyType);
        
        return match ($pedagogyType) {
            'inquiry_based' => $learnerState['high_mastery'] ?? false
                ? 'Ready for exploration'
                : 'Strong default for first exposure',
            'experiential' => 'Connects learning to real experience',
            'art_integrated' => $learnerState['visual_learner'] ?? false
                ? 'Matches creative and visual learning preference'
                : 'Supports conceptual expression',
            'game_based' => 'Used as a motivational intervention',
            'activity_based' => 'Builds understanding through concrete practice',
            'project_based' => 'Supports deeper application and collaboration',
            'flashcard' => 'Targets review and fluency',
            'flipped_classroom' => 'Supports pre-learning and guided application',
            'scenario_based' => 'Supports contextual decision-making',
            'spiritual_science' => 'Builds reflection, wellbeing, and awareness',
            'competency_based' => 'Requires demonstrated performance',
            'concept_sports' => 'Transfers learning through sports contexts',
            default => 'Standard delivery',
        };
    }

    /**
     * Record outcome for tracking
     * @param int $learnerId
     * @param string $pedagogyType
     * @param string $outcome
     * @return void
     */
    public function recordOutcome(int $learnerId, string $pedagogyType, string $outcome, array $context = []): PedagogyEffectiveness
    {
        $pedagogyType = $this->catalog->normalizePedagogy($pedagogyType);

        return PedagogyEffectiveness::create([
            'learner_id' => $learnerId,
            'pedagogy_type' => $pedagogyType,
            'outcome' => $outcome,
            'effectiveness_score' => $context['effectiveness_score'] ?? ($outcome === 'success' ? 100 : ($outcome === 'partial' ? 50 : 0)),
            'concept_id' => $context['concept_id'] ?? null,
            'session_id' => $context['session_id'] ?? null,
            'content_id' => $context['content_id'] ?? null,
            'context_data' => $context['context_data'] ?? $context,
        ]);
    }

    protected function checkConstraints(int $learnerId, array $context): array
    {
        // Check device capability
        if (($context['device_type'] ?? 'desktop') === 'mobile') {
            return ['blocked' => false, 'fallback' => null]; 
        }

        // Check language availability
        $pref = LearnerPreference::where('learner_id', $learnerId)
            ->where('pref_key', 'language')
            ->first();

        if ($pref && !in_array($pref->pref_value, ['en', $context['available_languages'] ?? ['en']])) {
            return [
                'blocked' => true,
                'fallback' => $this->buildCandidate('inquiry_based', 40, 'Language constraint')
            ];
        }

        // Check accessibility
        $access = LearnerPreference::where('learner_id', $learnerId)
            ->where('pref_key', 'accessibility')
            ->first();

        if ($access && $access->pref_value === 'screen_reader') {
            return [
                'blocked' => true,
                'fallback' => $this->buildCandidate('experiential', 40, 'Accessibility requirement')
            ];
        }

        return ['blocked' => false, 'fallback' => null];
    }

    protected function getLearnerState(int $learnerId): array
    {
        // NOT_ASSESSED is not low mastery (ADR-001 §5).
        //
        // This used to coalesce to 0, which made every band below fire for a
        // learner who had simply never been assessed: `low_mastery` and
        // `low_confidence` both true, so remedial pedagogy was selected for
        // someone who might already know the material. Absence of evidence is
        // the signal to DIAGNOSE, not to remediate.
        $measured = \App\Models\PAL\Competency::where('learner_id', $learnerId)
            ->avg('mastery_score');
        $notAssessed = $measured === null;
        $competency = (float) ($measured ?? 0);

        $preference = LearnerPreference::where('learner_id', $learnerId)
            ->where('pref_key', 'learning_style')
            ->first();

        $recentFails = \App\Models\PAL\AssessmentResult::where('learner_id', $learnerId)
            ->where('is_correct', false)
            ->where('created_at', '>=', now()->subDays(3))
            ->count();

        $decliningEngagement = false;
        if ($this->tierEnabled('tier-2')) {
            $recentSessions = \App\Models\PAL\LearningSession::where('learner_id', $learnerId)
                ->where('created_at', '>=', now()->subDays(14))
                ->orderByDesc('created_at')
                ->limit(6)
                ->pluck('exam_accuracy')
                ->toArray();
            $recentAvg = collect(array_slice($recentSessions, 0, 3))->avg() ?? 0;
            $olderAvg = collect(array_slice($recentSessions, 3, 3))->avg() ?? 0;
            $decliningEngagement = $olderAvg > 0 && $recentAvg < $olderAvg;
        }

        return [
            // Null, not 0 — a consumer must not be able to read "no evidence"
            // as a measured score.
            'mastery_score' => $notAssessed ? null : $competency,
            'not_assessed' => $notAssessed,
            'needs_diagnosis' => $notAssessed,
            // Every mastery band is false when there is nothing to band.
            'high_mastery' => ! $notAssessed && $competency > 75,
            'medium_mastery' => ! $notAssessed && $competency >= 50 && $competency <= 75,
            'low_mastery' => ! $notAssessed && $competency < 50,
            'low_confidence' => ! $notAssessed && $competency < 40,
            'low_self_efficacy' => $recentFails > 5,
            'has_gaps' => \App\Models\PAL\Competency::where('learner_id', $learnerId)
                ->where('mastery_score', '<', 50)->exists(),
            'visual_learner' => $preference?->pref_value === 'visual',
            'auditory_learner' => $preference?->pref_value === 'auditory',
            'kinesthetic_learner' => $preference?->pref_value === 'kinesthetic',
            'declining_engagement' => $decliningEngagement,
        ];
    }

    protected function getConceptRequirements(int $conceptId): array
    {
        $concept = \App\Models\PAL\Concept::find($conceptId);
        
        if (!$concept) {
            return ['default' => 'inquiry_based', 'recommended_pedagogy' => 'inquiry_based', 'available_pedagogies' => ['inquiry_based']];
        }

        return [
            'abstractness' => $concept->abstractness_level ?? 1,
            'visual_dependency' => $concept->requires_visual ?? false,
            'manipulation_need' => $concept->requires_manipulation ?? false,
            'simulation_required' => $concept->requires_simulation ?? false,
            'recommended_pedagogy' => $this->catalog->normalizePedagogy($concept->recommended_pedagogy ?? 'inquiry_based'),
            'available_pedagogies' => array_values(array_unique(array_map(
                fn ($tag) => $this->catalog->normalizePedagogy((string) $tag),
                (array) ($concept->pedagogy_tags ?? ['inquiry_based'])
            ))),
            'bloom_level' => $concept->bloom_level ?? 1,
        ];
    }

    protected function getHistoricalEffectiveness(int $learnerId): array
    {
        $history = PedagogyEffectiveness::where('learner_id', $learnerId)
            ->where('created_at', '>=', now()->subDays(14))
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return $history->groupBy('pedagogy_type')
            ->mapWithKeys(fn($group, $type) => [$this->catalog->normalizePedagogy((string) $type) => $group->avg('effectiveness_score')])
            ->toArray();
    }

    protected function checkNovelty(int $learnerId): array
    {
        $recent = PedagogyEffectiveness::where('learner_id', $learnerId)
            ->where('created_at', '>=', now()->subDays(3))
            ->pluck('pedagogy_type')
            ->map(fn ($type) => $this->catalog->normalizePedagogy((string) $type))
            ->toArray();

        $counts = array_count_values($recent);
        $overused = array_filter($counts, fn($c) => $c >= 3);

        return [
            'recent_pedagogies' => $recent,
            'overused_pedagogies' => array_keys($overused),
            'should_rotate' => count($overused) > 0,
        ];
    }

    protected function combineSelections(array $learnerState, array $conceptReqs, array $historical, array $novelty): array
    {
        $candidates = $this->generateCandidates($learnerState, $conceptReqs);

        // Score each candidate
        foreach ($candidates as &$candidate) {
            $score = 50; // Base score

            // Boost from historical effectiveness
            $type = $candidate['type'];
            if (isset($historical[$type])) {
                $score += ($historical[$type] - 50) * 0.4;
            }

            // Penalize for overuse
            if (in_array($type, $novelty['overused_pedagogies'] ?? [])) {
                $score -= 20;
            }

            // Boost from concept recommendation
            if ($type === ($conceptReqs['recommended_pedagogy'])) {
                $score += 15;
            }

            $candidate['score'] = round($score);
        }

        // Sort by score
        usort($candidates, fn($a, $b) => $b['score'] <=> $a['score']);

        return $candidates[0] ?? $this->buildCandidate('inquiry_based', 50, 'Default fallback');
    }

    protected function generateCandidates(array $learnerState, array $conceptReqs): array
    {
        $candidates = [];

        foreach (($conceptReqs['available_pedagogies'] ?? ['inquiry_based']) as $tag) {
            $candidates[] = ['type' => $this->catalog->normalizePedagogy($tag), 'base_score' => 30];
        }

        if (($conceptReqs['bloom_level'] ?? 1) >= 2) {
            $candidates[] = ['type' => 'inquiry_based', 'base_score' => 25];
        }

        $candidates[] = ['type' => 'activity_based', 'base_score' => 25];

        if ($conceptReqs['visual_dependency'] ?? false) {
            $candidates[] = ['type' => 'art_integrated', 'base_score' => 30];
        }

        if ($learnerState['low_confidence'] ?? false) {
            $candidates[] = ['type' => 'scenario_based', 'base_score' => 25];
            $candidates[] = ['type' => 'game_based', 'base_score' => 20];
        }

        if ($conceptReqs['simulation_required'] ?? false) {
            $candidates[] = ['type' => 'scenario_based', 'base_score' => 25];
        }

        if (!empty($conceptReqs['manipulation_need'])) {
            $candidates[] = ['type' => 'experiential', 'base_score' => 20];
            $candidates[] = ['type' => 'concept_sports', 'base_score' => 18];
        }

        return collect($candidates)
            ->unique('type')
            ->values()
            ->all();
    }

    protected function getDefaultAlternatives(): array
    {
        return [
            $this->buildCandidate('inquiry_based', 50, 'Default fallback'),
            $this->buildCandidate('activity_based', 45, 'Active reinforcement'),
        ];
    }

    protected function buildCandidate(string $type, int|float $score, string $reason): array
    {
        $normalized = $this->catalog->normalizePedagogy($type);

        return [
            'type' => $normalized,
            'score' => round((float) $score),
            'reason' => $reason,
            'h5p_types' => $this->catalog->allowedH5pForPedagogy($normalized),
            'coverage' => $this->catalog->pedagogyCoverage($normalized),
        ];
    }
}
