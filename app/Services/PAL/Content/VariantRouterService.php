<?php

namespace App\Services\PAL\Content;

use App\Models\PAL\ContentMetadata;
use App\Models\PAL\LearnerContentExposure;
use Illuminate\Support\Facades\DB;

/**
 * The variant router — spec §2.1, CONTENT LAW C7.
 *
 * "Variants re-route, they never re-teach." After a learner fails on variant 1
 * the system must serve a DIFFERENT content id in a DIFFERENT format, not the
 * same explanation again. Re-showing the same text more loudly is what separates
 * a smart quiz engine from an adaptive one.
 *
 * Three hard rules, all enforced here rather than by callers:
 *   1. never return a content id already in the learner's shown-set for the concept
 *   2. never return the same format as the attempt that just failed
 *   3. when the ladder is exhausted, raise a teacher alert — do NOT loop back to
 *      variant 1, which is exactly the re-teach this law exists to prevent
 *
 * H5P NOTE (plan §1.2.3): h5p_type is a preference, never a requirement. With 11
 * scenarios and 0 interactive videos live, gating on H5P would serve nothing, so
 * routing falls back to the content_master file/url delivery that already works.
 */
class VariantRouterService
{
    /**
     * Pick the next content variant for a learner on a concept.
     *
     * @param  array  $context  optional keys:
     *                          failed_format         string  the format that just failed
     *                          learning_style        string  matched against format
     *                          mother_tongue         string  ISO 639-1
     *                          content_type          string  concept|practice|corrective|assessment
     *                          bloom_level           string
     *                          exclude_content_ids   int[]
     */
    public function nextVariant(int $learnerId, int $conceptId, ?int $subInstituteId = null, array $context = []): array
    {
        $contentType = $context['content_type'] ?? 'concept';

        // concept_ref_id is unpopulated on essentially the whole live estate, so
        // the router accepts a chapter fallback via context['chapter_id'].
        $chapterId = isset($context['chapter_id']) ? (int) $context['chapter_id'] : null;

        $shown = $this->shownSet($learnerId, $conceptId, $contentType, $chapterId);
        $shownFormats = $this->shownFormats($learnerId, $conceptId, $contentType, $chapterId);

        $excluded = array_values(array_unique(array_merge($shown, array_map('intval', $context['exclude_content_ids'] ?? []))));

        $query = ContentMetadata::query()
            ->forCurriculum($conceptId ?: null, $chapterId)
            ->where('content_type', $contentType)
            ->servable()                       // C4 — approved only
            ->forTenant($subInstituteId);

        if ($excluded !== []) {
            $query->whereNotIn('content_master_id', $excluded);   // C7 rule 1
        }

        // C7 rule 2 — a different modality, not the same one again.
        $failedFormat = $context['failed_format'] ?? null;
        if ($failedFormat !== null) {
            $query->where(function ($q) use ($failedFormat) {
                $q->whereNull('format')->orWhere('format', '!=', $failedFormat);
            });
        }
        if ($shownFormats !== []) {
            $query->where(function ($q) use ($shownFormats) {
                $q->whereNull('format')->orWhereNotIn('format', $shownFormats);
            });
        }

        if (! empty($context['bloom_level'])) {
            $query->where('bloom_level_served', $context['bloom_level']);
        }

        $candidates = $query->get();

        // Nothing left in a different format. Widen once to "not yet shown", still
        // never re-serving a content id — then, if that is empty too, escalate.
        $relaxed = false;
        if ($candidates->isEmpty()) {
            $relaxed = true;
            $wide = ContentMetadata::query()
                ->forCurriculum($conceptId ?: null, $chapterId)
                ->where('content_type', $contentType)
                ->servable()
                ->forTenant($subInstituteId);

            if ($excluded !== []) {
                $wide->whereNotIn('content_master_id', $excluded);
            }
            $candidates = $wide->get();
        }

        if ($candidates->isEmpty()) {
            // C7 rule 3 — exhausted. Escalate instead of looping.
            return [
                'content' => null,
                'exhausted' => true,
                'teacher_alert' => true,
                'reason' => 'all_variants_exhausted',
                'variants_shown' => count($shown),
                'formats_shown' => $shownFormats,
                'concept_id' => $conceptId,
                'message' => 'Every approved variant for this concept has been served to this learner. '
                    . 'Escalating to a teacher rather than re-teaching an already-seen variant (C7).',
            ];
        }

        $ranked = $this->rank($candidates, $context, $shownFormats);
        $chosen = $ranked->first();

        return [
            'content' => $this->hydrate($chosen),
            'alternatives' => $ranked->slice(1, 3)->map(fn ($c) => $this->hydrate($c))->values()->all(),
            'exhausted' => false,
            'teacher_alert' => false,
            'relaxed_format_rule' => $relaxed,
            'reason' => $failedFormat !== null ? 'variant_reroute' : 'first_delivery',
            'variants_shown' => count($shown),
            'formats_shown' => $shownFormats,
            'concept_id' => $conceptId,
        ];
    }

    /**
     * Every approved variant for a concept, with a shown/unshown flag. Feeds the
     * authoring console's "does this concept have 3+ variants?" check (spec §2.1).
     */
    public function variantsForConcept(
        int $conceptId,
        ?int $subInstituteId = null,
        ?int $learnerId = null,
        string $contentType = 'concept',
        ?int $chapterId = null
    ): array {
        $rows = ContentMetadata::query()
            ->forCurriculum($conceptId ?: null, $chapterId)
            ->where('content_type', $contentType)
            ->forTenant($subInstituteId)
            ->orderBy('variant_number')
            ->get();

        $shown = $learnerId ? $this->shownSet($learnerId, $conceptId, $contentType, $chapterId) : [];

        $variants = $rows->map(function ($r) use ($shown) {
            $h = $this->hydrate($r);
            $h['shown_to_learner'] = in_array((int) $r->content_master_id, $shown, true);
            $h['quality_status'] = $r->quality_status;
            $h['servable'] = PalVocabulary::isServable($r->quality_status);

            return $h;
        })->all();

        $approved = array_filter($variants, fn ($v) => $v['servable']);
        $distinctFormats = count(array_unique(array_filter(array_column($approved, 'format'))));

        return [
            'concept_id' => $conceptId,
            'content_type' => $contentType,
            'variants' => $variants,
            'total' => count($variants),
            'approved' => count($approved),
            'distinct_formats' => $distinctFormats,
            // Spec §2.1: every Concept Learning node must have at least 3 format
            // variants, otherwise the re-route ladder has nowhere to go.
            'meets_minimum_variants' => $distinctFormats >= 3,
        ];
    }

    /**
     * Record a serve. Callers must invoke this, otherwise the shown-set never
     * grows and C7 silently degrades into re-teaching.
     */
    public function recordServe(
        int $learnerId,
        int $conceptId,
        int $subInstituteId,
        array $content,
        string $reason = 'first_delivery',
        ?int $sessionId = null
    ): void {
        LearnerContentExposure::create([
            'learner_id' => $learnerId,
            'sub_institute_id' => $subInstituteId,
            'concept_ref_id' => $conceptId ?: null,
            'chapter_ref_id' => $content['chapter_ref_id'] ?? null,
            'content_type' => $content['content_type'] ?? 'concept',
            'content_master_id' => $content['content_master_id'] ?? null,
            'content_id_ref' => $content['content_id_ref'] ?? null,
            'format' => $content['format'] ?? null,
            'variant_number' => $content['variant_number'] ?? null,
            'reason' => $reason,
            'session_id' => $sessionId,
            'served_at' => now(),
        ]);

        if (! empty($content['content_master_id'])) {
            ContentMetadata::where('content_master_id', $content['content_master_id'])
                ->where('sub_institute_id', $subInstituteId)
                ->increment('usage_count');
        }
    }

    /** Content ids already served to this learner for this concept. */
    public function shownSet(int $learnerId, int $conceptId, ?string $contentType = null, ?int $chapterId = null): array
    {
        $q = LearnerContentExposure::where('learner_id', $learnerId)
            ->when($conceptId > 0,
                fn ($x) => $x->where('concept_ref_id', $conceptId),
                fn ($x) => $x->where('chapter_ref_id', $chapterId))
            ->whereNotNull('content_master_id');

        if ($contentType !== null) {
            $q->where('content_type', $contentType);
        }

        return $q->pluck('content_master_id')->map(fn ($v) => (int) $v)->unique()->values()->all();
    }

    /** Formats already tried, so the router can insist on a new modality. */
    public function shownFormats(int $learnerId, int $conceptId, ?string $contentType = null, ?int $chapterId = null): array
    {
        $q = LearnerContentExposure::where('learner_id', $learnerId)
            ->when($conceptId > 0,
                fn ($x) => $x->where('concept_ref_id', $conceptId),
                fn ($x) => $x->where('chapter_ref_id', $chapterId))
            ->whereNotNull('format');

        if ($contentType !== null) {
            $q->where('content_type', $contentType);
        }

        return $q->pluck('format')->unique()->values()->all();
    }

    // ── Internals ────────────────────────────────────────────────────────────

    /**
     * Rank candidates. Ordering mirrors the spec §6.3 selection query: prefer a
     * learning-style match, then mother tongue, then observed post-mastery.
     */
    protected function rank($candidates, array $context, array $shownFormats)
    {
        $style = $context['learning_style'] ?? null;
        $tongue = $context['mother_tongue'] ?? null;
        $ladder = config('pal_content.variant_ladder', [1, 2, 3, 4]);

        return $candidates->map(function ($c) use ($style, $tongue, $shownFormats, $ladder) {
            $score = 50.0;

            // A format the learner has not seen is the whole point of re-routing.
            if ($c->format && ! in_array($c->format, $shownFormats, true)) {
                $score += 25;
            }

            // Walk the variant ladder in order rather than jumping to variant 4.
            $pos = array_search((int) $c->variant_number, $ladder, true);
            if ($pos !== false) {
                $score += (count($ladder) - $pos) * 2;
            }

            if ($style && $c->format && str_contains($c->format, $style)) {
                $score += 15;
            }

            if ($tongue) {
                if ($c->language === $tongue) {
                    $score += 12;
                } elseif (in_array($tongue, $c->language_variants_available ?? [], true)) {
                    $score += 6;
                }
            }

            if ($c->avg_mastery_post !== null) {
                $score += ((float) $c->avg_mastery_post - 0.5) * 20;
            }

            // Content the field has flagged as confusing should sink, not surface.
            $score -= min(10, (int) $c->confusion_flag_count);

            $c->routing_score = round($score, 2);

            return $c;
        })->sortByDesc('routing_score')->values();
    }

    /** Merge sidecar metadata with the live content_master row that carries the file/url. */
    protected function hydrate($meta): array
    {
        $source = DB::table('content_master')->where('id', $meta->content_master_id)->first();

        return [
            'content_master_id' => (int) $meta->content_master_id,
            'content_id_ref' => $meta->content_id_ref,
            'concept_ref_id' => $meta->concept_ref_id,
            'chapter_ref_id' => $meta->chapter_ref_id,
            'title' => $source->title ?? null,
            'description' => $source->description ?? null,
            'content_type' => $meta->content_type,
            'variant_number' => $meta->variant_number ? (int) $meta->variant_number : null,
            'format' => $meta->format,
            'bloom_level_served' => $meta->bloom_level_served,
            'practice_level' => $meta->practice_level,
            'difficulty' => $meta->difficulty_1_to_5,
            'language' => $meta->language,
            'language_variants_available' => $meta->language_variants_available ?? [],
            'cultural_context' => $meta->cultural_context,
            'estimated_duration_minutes' => $meta->estimated_duration_minutes,
            // Preference only — never a delivery gate (plan §1.2.3).
            'h5p_type' => $meta->h5p_type,
            'h5p_content_id' => $meta->h5p_content_id,
            // The delivery fallback that actually exists today.
            'file_type' => $source->file_type ?? null,
            'url' => $source->url ?? null,
            'filename' => $source->filename ?? null,
            'file_folder' => $source->file_folder ?? null,
            'accessibility' => [
                'has_alt_text' => (bool) $meta->has_alt_text,
                'has_transcript' => (bool) $meta->has_transcript,
                'wcag_level' => $meta->wcag_level,
                'screen_reader_compatible' => (bool) $meta->screen_reader_compatible,
            ],
            'routing_score' => $meta->routing_score ?? null,
        ];
    }
}
