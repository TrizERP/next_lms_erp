<?php

namespace App\Services\Eso;

use App\Models\PAL\ConceptVideo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Is there an approved video that teaches this concept?"
 *
 * This is the read side, and it is deliberately tiny: one indexed query
 * against `pal_concept_video`. It never scores anything, never touches
 * `content_master`, and never opens a socket.
 *
 * That split is the point. Sourcing a video means ranking a chapter's library
 * and possibly calling a search API, and none of that can help the student
 * currently on screen — an approved-content-only gate means anything
 * discovered now is unreviewed and therefore unservable to them anyway. So
 * discovery lives in pal:harvest-concept-videos, and the student's request
 * path pays for a single primary-key read.
 *
 * Unlike EsoLearningContentResolver this is NOT gated on semantic_intelligence.
 * Measured on the live estate, gating video behind the extraction would cut it
 * from 14 chapters to 4 — and coverage is the entire point of the feature.
 */
class EsoConceptVideoResolver
{
    /**
     * The best approved video for this concept, or null.
     *
     * @param  int  $rankOffset  0 = best match. A second failed check passes 1
     *          to get the next-best rather than replaying the video that just
     *          failed to land (CONTENT LAW C7), which works without storing
     *          any new learner state because the ordering is deterministic.
     * @return array{
     *     variant:int, format:string, format_label:string, h5p_type:?string,
     *     title:?string, body:?string, media_url:string, source:string,
     *     provider:?string, attribution:?string, match_score:?float,
     *     thumbnail_url:?string, video_id:int
     * }|null
     */
    public function forConcept(int $conceptId, ?int $subInstituteId, int $rankOffset = 0): ?array
    {
        if (! config('pal_content.video.enabled', true)) {
            return null;
        }

        if ($conceptId <= 0 || ! Schema::hasTable('pal_concept_video')) {
            return null;
        }

        try {
            $rows = ConceptVideo::query()
                ->servable()
                ->forTenant($subInstituteId)
                ->where('concept_id', $conceptId)
                // A tenant's own curation outranks the shared pool.
                ->orderByDesc('sub_institute_id')
                ->orderByDesc('match_score')
                ->orderBy('rank')
                ->orderBy('id')
                ->limit(max(1, $rankOffset + 1))
                ->get();
        } catch (\Throwable) {
            // A richer format is never worth failing a learning step over —
            // the same principle EsoLearningContentResolver applies.
            return null;
        }

        // Deliberately no wrap-around: when the student has exhausted the
        // videos we have, the caller falls through to the rest of the variant
        // ladder rather than re-serving one that already failed.
        $row = $rows->get($rankOffset);

        return $row === null ? null : $this->shape($row);
    }

    /** Every approved video for a concept — used by the review screens. */
    public function allForConcept(int $conceptId, ?int $subInstituteId): array
    {
        if (! Schema::hasTable('pal_concept_video')) {
            return [];
        }

        return ConceptVideo::query()
            ->servable()
            ->forTenant($subInstituteId)
            ->where('concept_id', $conceptId)
            ->orderByDesc('sub_institute_id')
            ->orderByDesc('match_score')
            ->get()
            ->map(fn (ConceptVideo $row) => $this->shape($row))
            ->all();
    }

    /**
     * Which of the given concepts have at least one servable video.
     *
     * A single query for the whole chapter rather than one per concept —
     * the dashboard and plan screens need to badge every concept in a
     * chapter, and N+1 lookups would dominate their render time.
     *
     * @param  array<int, int>  $conceptIds
     * @return array<int, bool> keyed by concept_id
     */
    public function hasVideoForConcepts(array $conceptIds, ?int $subInstituteId): array
    {
        if (empty($conceptIds) || ! Schema::hasTable('pal_concept_video')) {
            return [];
        }

        $conceptIds = array_values(array_unique(array_filter(array_map('intval', $conceptIds))));
        if ($conceptIds === []) {
            return [];
        }

        $rows = ConceptVideo::query()
            ->servable()
            ->forTenant($subInstituteId)
            ->whereIn('concept_id', $conceptIds)
            ->pluck('concept_id')
            ->unique()
            ->all();

        $result = array_fill_keys($conceptIds, false);
        foreach ($rows as $conceptId) {
            $result[(int) $conceptId] = true;
        }

        return $result;
    }

    /**
     * Shaped to match EsoLearningContentResolver::shape() key-for-key, so the
     * payload, the renderer and the frontend parser all need no special case.
     */
    protected function shape(ConceptVideo $row): array
    {
        return [
            'variant' => 2,
            'format' => 'video',

            // NOT the blueprint's "Video + interactive pauses" label. We serve
            // a plain player, and EsoPalRenderer::reteachInstruction() feeds
            // this string straight to Pal — the blueprint label would have Pal
            // promise the student interactive pauses that do not exist.
            'format_label' => 'Video',

            'h5p_type' => null,
            'title' => $this->trimmedOrNull($row->title),
            'body' => $this->supportingText($row->title, $row->description),
            'media_url' => (string) $row->media_url,
            'source' => $row->source === 'institute' ? 'institute_video' : 'curated_video',
            'provider' => $this->trimmedOrNull($row->provider),
            'attribution' => $this->trimmedOrNull($row->attribution),
            'match_score' => $row->match_score === null ? null : (float) $row->match_score,
            'thumbnail_url' => $this->trimmedOrNull($row->thumbnail_url),
            'video_id' => (int) $row->id,
        ];
    }

    protected function trimmedOrNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * The description, unless it is just the title again.
     *
     * YouTube descriptions almost always open by restating the title, which
     * on this panel renders as the same sentence twice directly above and
     * below the player. Nothing is lost by dropping it — the title is already
     * shown, and the video itself is the content.
     */
    protected function supportingText(?string $title, ?string $description): ?string
    {
        $body = $this->trimmedOrNull($description);
        if ($body === null) {
            return null;
        }

        $normalisedTitle = $this->normalise((string) $title);
        if ($normalisedTitle === '') {
            return $body;
        }

        $normalisedBody = $this->normalise($body);

        if ($normalisedBody === $normalisedTitle || str_starts_with($normalisedBody, $normalisedTitle)) {
            // What remains after the restated title is usually channel
            // boilerplate ("Future Toppers' Alert!"), not teaching.
            return null;
        }

        return $body;
    }

    protected function normalise(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', mb_strtolower(strip_tags($value))));
    }

    /**
     * The concept row the harvester and the repository both need. Kept here so
     * the "which columns actually exist" question is answered in one place.
     */
    public function concept(int $conceptId): ?object
    {
        return DB::table('lms_concept')
            ->where('id', $conceptId)
            ->first(['id', 'name', 'chapter_id', 'topic_id', 'subject_id', 'standard_id', 'sub_institute_id', 'syear']);
    }
}
