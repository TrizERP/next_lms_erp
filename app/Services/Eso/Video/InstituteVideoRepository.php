<?php

namespace App\Services\Eso\Video;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The institute's own video library, read out of `content_master`.
 *
 * Three things about that table drive every decision here, all measured on the
 * live estate rather than assumed:
 *
 *  1. THE URL IS IN `filename`, NOT `url`. `url` is empty on effectively every
 *     row; `filename` holds a full https link to DigitalOcean Spaces. Reading
 *     `url` first — the obvious order — returns nothing.
 *  2. `file_type` is 'link' even for mp4s, so it cannot be used to find video.
 *     Category plus a filename extension test is the working filter.
 *  3. `concept_id` is 0/NULL across all 31k rows despite having a foreign key,
 *     so `chapter_id` is the only join that returns anything.
 *
 * The tenancy and visibility guards are deliberately copied from
 * PedagogySuggestedContentService::getCandidateContent(), which already serves
 * content from this table to students — a second, subtly different guard set
 * over the same estate would be a latent leak.
 */
class InstituteVideoRepository
{
    /**
     * Video rows for the chapter that owns this concept, already scored and
     * gated, best first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function candidatesForConcept(object $concept, ?int $subInstituteId): array
    {
        $rows = $this->rawCandidates($concept, $subInstituteId);
        if ($rows === []) {
            return [];
        }

        $scorer = app(ConceptVideoRelevanceScorer::class);

        $ranked = $scorer->rank((string) $concept->name, $rows, [
            'min_score' => (float) config('pal_content.video.min_match_score', 0.55),
            'small_pool_score' => (float) config('pal_content.video.small_pool_score', 0.65),
            'small_pool_size' => (int) config('pal_content.video.small_pool_size', 3),
        ]);

        // Resolve the playable URL only for rows that survived scoring — the
        // validation below rejects rows, and rejecting after ranking keeps the
        // IDF pool honest about what the chapter actually contains.
        $usable = [];
        foreach ($ranked as $row) {
            $url = $this->resolveUrl($row);
            if ($url === null) {
                continue;
            }

            $row['media_url'] = $url;
            $usable[] = $row;
        }

        return $usable;
    }

    /**
     * The chapter's video rows, before scoring.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function rawCandidates(object $concept, ?int $subInstituteId): array
    {
        if (! Schema::hasTable('content_master')) {
            return [];
        }

        $chapterId = (int) ($concept->chapter_id ?? 0);
        if ($chapterId <= 0) {
            return [];
        }

        $query = DB::table('content_master as cm')
            ->select('cm.*')
            ->where('cm.chapter_id', $chapterId);

        // file_type is unusable ('link' even for mp4s), so match the authored
        // category OR an actual video extension on the stored URL.
        $categories = (array) config('pal_content.video.categories', ['Recorded Videos', 'Videos']);
        $extensions = (array) config('pal_content.video.extensions', ['mp4', 'webm', 'ogg']);
        $extensionPattern = '\\.(' . implode('|', array_map('preg_quote', $extensions)) . ')([?#]|$)';

        $query->where(function ($q) use ($categories, $extensionPattern) {
            $q->whereIn('cm.content_category', $categories)
                ->orWhere('cm.filename', 'REGEXP', $extensionPattern)
                ->orWhere('cm.url', 'REGEXP', $extensionPattern);
        });

        // ── Guards copied from PedagogySuggestedContentService ──────────────
        if (Schema::hasColumn('content_master', 'sub_institute_id') && (int) $subInstituteId > 0) {
            $query->where(function ($q) use ($subInstituteId) {
                $q->whereNull('cm.sub_institute_id')
                    ->orWhere('cm.sub_institute_id', $subInstituteId)
                    ->orWhere('cm.sub_institute_id', 1);
            });
        }

        if (Schema::hasColumn('content_master', 'show_hide')) {
            $query->where(function ($q) {
                $q->whereNull('cm.show_hide')->orWhere('cm.show_hide', 1);
            });
        }

        if (Schema::hasColumn('content_master', 'user_profile_name')) {
            $query->where(function ($q) {
                $q->whereNull('cm.user_profile_name')
                    ->orWhereRaw("LOWER(cm.user_profile_name) != 'student'");
            });
        }

        if (config('pal_content.video.chapter_level_only', true)
            && Schema::hasColumn('content_master', 'topic_id')) {
            $query->where(function ($q) {
                $q->whereNull('cm.topic_id')->orWhere('cm.topic_id', 0)->orWhere('cm.topic_id', '0');
            });
        }

        // Curriculum scope, but deliberately NOT syear.
        //
        // PedagogySuggestedContentService filters on syear because it is
        // assembling this year's suggestions. Applying that here removes
        // everything: chapter 1014's concepts carry syear 2026 while its
        // videos were uploaded in 2021, so an equality test matches nothing at
        // all. And it should not match on year anyway — an explanation of how
        // metals react does not expire at the end of an academic session, and
        // a library this thin cannot afford to discard five years of it.
        foreach (['standard_id', 'subject_id'] as $column) {
            $value = $concept->{$column} ?? null;
            if ($value !== null && (int) $value > 0 && Schema::hasColumn('content_master', $column)) {
                $query->where('cm.' . $column, $value);
            }
        }

        return $query
            ->orderByRaw('COALESCE(cm.sort_order, 999999) ASC')
            ->orderByDesc('cm.id')
            ->limit((int) config('pal_content.video.candidate_limit', 40))
            ->get()
            ->map(static fn ($row) => (array) $row)
            ->all();
    }

    /**
     * The playable URL for a row, or null when there isn't a trustworthy one.
     *
     * `filename` is read FIRST because that is where this estate actually
     * stores the link. We deliberately do NOT fall back to concatenating
     * file_folder + filename the way the legacy blade player does: for these
     * rows the folder is null and the reconstruction produces a 404, and a
     * dead player is worse for a struggling student than no player.
     */
    public function resolveUrl(array $row): ?string
    {
        foreach (['filename', 'url'] as $field) {
            $value = trim((string) ($row[$field] ?? ''));
            if ($value === '' || ! preg_match('#^https?://#i', $value)) {
                continue;
            }

            if (filter_var($value, FILTER_VALIDATE_URL) === false) {
                continue;
            }

            // An https page silently blocks an http <video src>, so an
            // insecure URL would render as a dead player, not an error.
            if (config('pal_content.video.require_https', true)
                && ! str_starts_with(mb_strtolower($value), 'https://')) {
                continue;
            }

            return $value;
        }

        return null;
    }
}
