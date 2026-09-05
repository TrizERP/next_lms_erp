<?php

namespace App\Services\Eso;

use App\Services\PAL\Integration\PedagogySuggestedContentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * "What is worth exploring now that this concept is mastered?"
 *
 * Deliberately builds NO content of its own. PedagogySuggestedContentService
 * already produces an enrichment bucket from `content_master` — and already
 * scores enrichment UP once a learner's mastery average clears 70, so it was
 * literally designed for this moment. ESO simply never called it: a grep of
 * app/Services/Eso/ for `enrichment` returned nothing before this class, so a
 * mastered student hit a terminal "practice stops here" card while the
 * enrichment pipeline sat unused one service away.
 *
 * ---------------------------------------------------------------------------
 * WHY THIS IS NOT MASTERY EVIDENCE
 * ---------------------------------------------------------------------------
 * No existing D1-D5 rule treats exploratory content as evidence of anything,
 * and inventing one here would be a policy change smuggled in as a feature.
 * So enrichment is strictly display-only: this class writes no state, records
 * no response, and the student may skip it entirely with no consequence to
 * their mastery, their retention schedule, or their evidence trail.
 *
 * ---------------------------------------------------------------------------
 * WHY FAILURES ARE SWALLOWED
 * ---------------------------------------------------------------------------
 * Same rule the gamification and evidence hand-offs already follow: a bonus
 * surface must never be able to break the mastery verdict itself. A student who
 * has just earned a concept sees their achievement even if the content service
 * is misconfigured — they simply see no enrichment.
 */
class EsoEnrichmentResolver
{
    /** More than a handful stops being an invitation and starts being homework. */
    protected const MAX_ITEMS = 3;

    public function __construct(protected PedagogySuggestedContentService $suggestions)
    {
    }

    /**
     * Suggested content for a concept the learner is still working on.
     *
     * Same pipeline as forConcept(), different bucket: PedagogySuggestedContentService
     * already splits its output into remediation / practice / enrichment and
     * already scores each against the learner's mastery, so the bucket is
     * chosen from where the learner actually is rather than recomputed here.
     *
     * Returns [] when nothing is authored — which is the honest answer, not a
     * failure. Callers render an explicit "nothing authored yet" state; they do
     * not substitute content from elsewhere.
     *
     * @return array<int, array{title:string, description:?string, url:?string, content_type:?string, category:string}>
     */
    public function suggestionsFor(int $studentId, int $conceptId, int $subInstituteId, string $bucket): array
    {
        $allowed = ['remediation_content', 'practice_content', 'enrichment_content'];
        if (! in_array($bucket, $allowed, true)) {
            return [];
        }

        return $this->fromPipeline($studentId, $conceptId, $subInstituteId, $bucket);
    }

    /**
     * Enrichment items for a concept the student has just mastered.
     *
     * Returns [] whenever nothing is authored for the chapter, the content
     * service is unavailable, or the concept cannot be resolved — all normal
     * states, none of them errors.
     *
     * @return array<int, array{title:string, description:?string, url:?string, content_type:?string, category:string}>
     */
    public function forConcept(int $studentId, int $conceptId, int $subInstituteId): array
    {
        return $this->fromPipeline($studentId, $conceptId, $subInstituteId, 'enrichment_content');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fromPipeline(int $studentId, int $conceptId, int $subInstituteId, string $bucket): array
    {
        try {
            $concept = DB::table('lms_concept')
                ->where('id', $conceptId)
                ->first(['id', 'chapter_id', 'subject_id', 'standard_id', 'syear']);

            if ($concept === null) {
                return [];
            }

            $result = $this->suggestions->getSuggestions([
                'learner_id' => $studentId,
                'chapter_id' => (int) $concept->chapter_id,
                'subject_id' => (int) $concept->subject_id,
                'standard_id' => (int) $concept->standard_id,
                'sub_institute_id' => $subInstituteId,
                'syear' => $concept->syear,
                // The service scores enrichment up for a high-mastery learner.
                // Reaching D4 IS that state, stated explicitly rather than
                // left to whatever the service infers from other signals.
                'student_level' => 'advanced',
            ]);

            $items = $result['content_data'][$bucket] ?? [];

            if (! is_array($items)) {
                return [];
            }

            return collect($items)
                ->take(self::MAX_ITEMS)
                ->map(fn ($item) => $this->shape((array) $item, str_replace('_content', '', $bucket)))
                ->filter(fn (array $item) => $item['title'] !== '')
                ->values()
                ->all();
        } catch (Throwable $e) {
            Log::warning('ESO enrichment lookup failed; the mastery verdict is unaffected', [
                'student_id' => $studentId,
                'concept_id' => $conceptId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Narrow the content service's row to what the student-facing card needs.
     * Nothing is invented: a missing field stays null rather than being filled
     * with a plausible default.
     *
     * @param  array<string, mixed>  $item
     * @return array{title:string, description:?string, url:?string, content_type:?string, category:string}
     */
    protected function shape(array $item, string $category = 'enrichment'): array
    {
        $value = static function (array $keys) use ($item): ?string {
            foreach ($keys as $key) {
                $candidate = trim((string) ($item[$key] ?? ''));
                if ($candidate !== '') {
                    return $candidate;
                }
            }

            return null;
        };

        return [
            'title' => (string) ($value(['title', 'content_title', 'name']) ?? ''),
            'description' => $value(['description', 'summary', 'content_description']),
            'url' => $value(['url', 'content_url', 'file_url', 'link']),
            'content_type' => $value(['content_type', 'type', 'format']),
            'category' => $category,
        ];
    }
}
