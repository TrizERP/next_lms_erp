<?php

namespace App\Services\PAL\Content;

use App\Models\PAL\Content as ContentModel;
use App\Models\PAL\ContentMetadata;
use App\Models\PAL\ContentRecommendation;
use Illuminate\Support\Facades\DB;

/**
 * Content Intelligence Service
 * Handles content routing and remediation
 *
 * Two content estates live behind this service:
 *
 *   pal_contents        — the V4 engine's own table. 1 row live. Kept working so
 *                         nothing that already calls this class breaks.
 *   content_master      — the real LMS estate, 31,362 rows, annotated by the
 *                         pal_content_metadata sidecar (CONTENT LAW C1).
 *
 * New callers should prefer the sidecar-backed methods (getConceptContent,
 * getPracticeContent, getMisconceptionCorrective) — those honour the 4-type
 * model, the variant re-route law (C7) and the approved-only filter (C4).
 */
class ContentIntelligenceService
{
    public function __construct(
        protected ?VariantRouterService $router = null,
        protected ?MisconceptionLibraryService $misconceptions = null,
        protected ?BloomLadderService $ladder = null
    ) {
        $this->router = $router ?? new VariantRouterService();
        $this->misconceptions = $misconceptions ?? new MisconceptionLibraryService($this->router);
        $this->ladder = $ladder ?? new BloomLadderService();
    }

    // ══════════════════════════════════════════════════════════════════════
    // V4 Content Intelligence Layer — sidecar-backed (spec §1, §2, §3, §4)
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Type 1 — Concept Learning content, variant-aware (spec §2).
     *
     * Delegates to the variant router, so C7 holds: a re-route always yields a
     * different content id in a different format, and an exhausted ladder
     * escalates to a teacher instead of re-teaching.
     */
    public function getConceptContent(int $learnerId, int $conceptId, ?int $subInstituteId = null, array $context = []): array
    {
        $context['content_type'] = 'concept';

        return $this->router->nextVariant($learnerId, $conceptId, $subInstituteId, $context);
    }

    /**
     * Type 2 — Practice content at a rung of the Bloom ladder (spec §3).
     */
    public function getPracticeContent(
        int $learnerId,
        int $conceptId,
        int $practiceLevel,
        ?int $subInstituteId = null,
        int $limit = 10
    ): array {
        return [
            'concept_id' => $conceptId,
            'practice_level' => $practiceLevel,
            'bloom_level' => PalVocabulary::bloomForPracticeLevel($practiceLevel),
            'items' => $this->ladder->itemsForLevel($conceptId, $practiceLevel, $subInstituteId, $learnerId, $limit),
        ];
    }

    /**
     * Type 3 — the corrective for a detected misconception (spec §4).
     */
    public function getMisconceptionCorrective(
        int $learnerId,
        int $misconceptionId,
        ?int $subInstituteId = null,
        ?string $motherTongue = null
    ): ?array {
        return $this->misconceptions->selectCorrective($misconceptionId, $learnerId, $subInstituteId, $motherTongue);
    }

    /**
     * Spec §6.3 "select optimal content for a learner in a session", against the
     * real content estate rather than the near-empty pal_contents table.
     *
     * CONTENT LAW C4 — only approved rows are eligible, always.
     */
    public function selectOptimalContent(int $learnerId, int $conceptId, ?int $subInstituteId, array $context = []): array
    {
        $result = $this->router->nextVariant($learnerId, $conceptId, $subInstituteId, $context);

        if ($result['content'] === null) {
            // Fall back to the legacy pal_contents path so an untagged concept
            // still returns something rather than a hard failure.
            $legacy = $this->getRecommendation($learnerId, $conceptId, $context);
            $result['legacy_fallback'] = $legacy;
        }

        return $result;
    }

    /**
     * Spec §7.3 — content that is used a lot and teaches little.
     */
    public function underperformingContent(?int $subInstituteId = null, int $limit = 50): array
    {
        $rows = ContentMetadata::underperforming()
            ->forTenant($subInstituteId)
            ->orderBy('avg_mastery_post')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $titles = DB::table('content_master')
            ->whereIn('id', $rows->pluck('content_master_id'))
            ->pluck('title', 'id');

        return $rows->map(fn ($r) => [
            'content_master_id' => (int) $r->content_master_id,
            'title' => $titles[$r->content_master_id] ?? null,
            'concept_id' => $r->concept_ref_id,
            'avg_mastery_post' => $r->avg_mastery_post,
            'usage_count' => (int) $r->usage_count,
            'confusion_flag_count' => (int) $r->confusion_flag_count,
            'flag' => 'REVIEW',
        ])->all();
    }

    /**
     * Spec §7.3 — concepts where EVERY variant is failing. These need new content
     * or a teacher-led redesign, not another tweak.
     */
    public function conceptsWithAllVariantsFailing(?int $subInstituteId = null): array
    {
        $threshold = config('pal_content.monitoring.all_variants_failing_below');

        $rows = ContentMetadata::query()
            ->where('content_type', 'concept')
            ->whereNotNull('avg_mastery_post')
            ->forTenant($subInstituteId)
            ->selectRaw('concept_ref_id, COUNT(*) AS variants, MAX(avg_mastery_post) AS best')
            ->groupBy('concept_ref_id')
            ->havingRaw('MAX(avg_mastery_post) < ?', [$threshold])
            ->get();

        return $rows->map(fn ($r) => [
            'concept_id' => (int) $r->concept_ref_id,
            'variant_count' => (int) $r->variants,
            'best_mastery_post' => (float) $r->best,
            'threshold' => $threshold,
        ])->all();
    }

    // ══════════════════════════════════════════════════════════════════════
    // Legacy pal_contents path — unchanged behaviour for existing callers
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Get recommended content for learner
     * @param int $learnerId
     * @param int $conceptId
     * @param array $context
     * @return array
     */
    public function getRecommendation(int $learnerId, int $conceptId, array $context = []): array
    {
        $contents = ContentModel::where('concept_id', $conceptId)
            ->where('status', 'active')
            ->get();

        if ($contents->isEmpty()) {
            return $this->getAIRemediatedContent($learnerId, $conceptId, $context);
        }

        // Filter by difficulty matching learner level
        $learnerLevel = $this->getLearnerLevel($learnerId);
        $filtered = $contents->filter(fn($c) => $this->matchesDifficulty($c, $learnerLevel));

        // Sort by multiple factors
        $scored = $this->scoreContent($filtered, $learnerId, $context);

        return [
            'content' => $scored->first(),
            'alternatives' => $scored->slice(1, 5)->values(),
            'count' => $scored->count(),
        ];
    }

    /**
     * Get content variants for H5P
     * @param int $conceptId
     * @param string $h5pType
     * @return array
     */
    public function getH5PVariants(int $conceptId, string $h5pType): array
    {
        return ContentModel::where('concept_id', $conceptId)
            ->where('h5p_type', $h5pType)
            ->where('status', 'active')
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'title' => $c->title,
                'difficulty' => $c->difficulty_level,
                'bloom_level' => $c->bloom_level,
            ])
            ->toArray();
    }

    /**
     * Get misconception content
     *
     * Previously queried `pal_contents.misconception_tags`, a column that does
     * not exist on that table — so this threw on every call. It now reads the
     * real misconception library (pal_misconception_corrective), which is what
     * the spec's CORRECTS_WITH edge actually maps to.
     *
     * @param int $misconceptionId  pal_misconception_library.id
     * @return array
     */
    public function getMisconceptionContent(int $misconceptionId, ?int $subInstituteId = null): array
    {
        $contents = \App\Models\PAL\MisconceptionCorrective::where('misconception_id', $misconceptionId)
            ->servable()
            ->forTenant($subInstituteId)
            ->orderBy('priority_level')
            ->get();

        return [
            'contents' => $contents->map(fn ($c) => [
                'id' => (int) $c->id,
                'type' => 'corrective',
                'title' => $c->title,
                'format' => $c->format,
                'h5p_type' => $c->h5p_type,
                'language' => $c->language,
                'content_master_id' => $c->content_master_id ? (int) $c->content_master_id : null,
                'estimated_duration_minutes' => $c->estimated_duration_minutes,
            ]),
            'count' => $contents->count(),
        ];
    }

    /**
     * Track content engagement
     * @param int $learnerId
     * @param int $contentId
     * @param string $eventType
     * @return void
     */
    public function trackEngagement(int $learnerId, int $contentId, string $eventType): void
    {
        ContentRecommendation::create([
            'learner_id' => $learnerId,
            'content_id' => $contentId,
            'event_type' => $eventType,
            'engagement_score' => $this->calculateEngagementScore($eventType),
        ]);
    }

    protected function getLearnerLevel(int $learnerId): int
    {
        $avg = \App\Models\PAL\Competency::where('learner_id', $learnerId)
            ->avg('mastery_score') ?? 50;

        return match(true) {
            $avg >= 80 => 3,
            $avg >= 60 => 2,
            $avg >= 40 => 1,
            default => 0,
        };
    }

    protected function matchesDifficulty($content, int $learnerLevel): bool
    {
        // Allow 1 level variance for optimal challenge
        return abs($content->difficulty_level - $learnerLevel) <= 1;
    }

    protected function scoreContent($contents, int $learnerId, array $context): \Illuminate\Database\Eloquent\Collection
    {
        return $contents->map(function ($content) use ($learnerId) {
            $score = 50;

            // Boost from previous engagement
            $history = ContentRecommendation::where('learner_id', $learnerId)
                ->where('content_id', $content->id)
                ->avg('engagement_score');

            if ($history) {
                $score += ($history - 50) * 0.4;
            }

            // Boost from pedagogy match
            if (in_array($context['pedagogy'] ?? '', $content->pedagogy_tags ?? [])) {
                $score += 15;
            }

            // Penalize recently consumed
            $recent = ContentRecommendation::where('learner_id', $learnerId)
                ->where('content_id', $content->id)
                ->where('created_at', '>=', now()->subDays(1))
                ->exists();

            if ($recent) {
                $score -= 20;
            }

            $content->recommendation_score = round($score);
            return $content;
        })->sortByDesc('recommendation_score');
    }

    protected function getAIRemediatedContent(int $learnerId, int $conceptId, array $context): array
    {
        // Use AI to generate content on-the-fly
        // This would call the AI service
        return [
            'ai_generated' => true,
            'concept_id' => $conceptId,
            'fallback_message' => 'No content available - requesting AI generation',
        ];
    }

    protected function calculateEngagementScore(string $eventType): int
    {
        return match($eventType) {
            'completed' => 100,
            'partial' => 50,
            'skipped' => 10,
            'started' => 30,
            default => 20,
        };
    }
}