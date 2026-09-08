<?php

namespace App\Services\lms\Content;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Surfaces H5P items into the chapter content list as a FORMAT, not a peer category.
 *
 * Delivers tracker "Content & LMS Architecture" row 2 and Decision #35:
 * "H5P Content is removed as a 4th top-level button alongside Classroom Resource /
 *  Teacher Resource / Question Bank, and instead becomes a content-type filter value
 *  inside Classroom Resource and Teacher Resource."
 *
 * WHY AN ADAPTER RATHER THAN A BACKFILL OR A UNION
 *
 * The sheet describes this as folding H5P into "the existing content-type filter",
 * which reads like a UI tweak. It is not. Measured on live 2026-09-07:
 *   - content_master has ZERO H5P rows. Its file_type values are pdf 15,460 /
 *     link 13,683 / mp4 1,921 / pptx 183 / jpg 99 / docx 9 - no h5p anywhere.
 *   - The real H5P estate is 3 separate tables holding 11 items in total
 *     (h5p_scenarios 11, h5p_interactive_video 0, h5p_flashcard 0).
 * So there is nothing in the content list to filter FOR until H5P is joined in.
 *
 * Three options were considered:
 *
 *   (a) INSERT the 11 items into content_master - rejected. It violates additive-only
 *       on a table 56 tenants and the legacy Blade UI read (CONTENT LAW C2), and an
 *       H5P item has no file: its render target is a route, not a file_type any
 *       existing viewer could open.
 *
 *   (b) Backfill pal_content_metadata.format = 'h5p' - rejected as insufficient. That
 *       table is uniquely keyed (content_master_id, sub_institute_id), so it
 *       structurally cannot describe an item that has no content_master row. It stays
 *       the right mechanism for H5P authored AS content_master rows later; it just
 *       cannot cover the estate that exists today.
 *
 *   (c) This adapter - chosen. It reads the h5p_* tables and maps them into the same
 *       asset shape the content list already uses, with format='h5p'. Nothing is
 *       copied, so there is no sync problem and no second source of truth.
 *
 * There is precedent for exactly this in the method that calls it: Flash Cards are
 * already merged in from lms_flashcard, and Mindmap / Virtual Lab are already
 * synthesised as empty buckets (ApiLmsCourseController::getChapterContentCategories).
 * H5P follows a pattern that already ships.
 *
 * IDS ARE NAMESPACED. An H5P item is emitted as "h5p:scenario:7", never a bare 7,
 * so it can never collide with a content_master.id or be mistaken for one by a
 * downstream write path.
 *
 * AUDIENCE IS 'both'. None of the h5p_* tables records who an item is for, and the
 * Classroom-vs-Teacher split is itself only a string match on the frontend. Rather
 * than invent a classification, H5P items surface on BOTH surfaces - which is also
 * what the tracker asks for ("a filter value inside Classroom Resource AND Teacher
 * Resource"), and is honest about what the data supports.
 */
class H5PContentAdapter
{
    /**
     * The three H5P estates, and how each maps into a content asset.
     *
     * `route` is the Next.js editor the item deep-links to. Those routes already
     * exist and hold the CRUD, which is what makes demoting the top-level button
     * safe: nothing becomes unreachable, it just stops being a 4th destination.
     */
    private const SOURCES = [
        'scenario' => [
            'table' => 'h5p_scenarios',
            'title' => 'title',
            'route' => '/h5p/scenario_based',
            'label' => 'H5P scenario',
            'h5p_type' => 'image_hotspots',
        ],
        'interactive_video' => [
            'table' => 'h5p_interactive_video',
            'title' => 'title',
            'route' => '/h5p/h5p_interactive_video',
            'label' => 'H5P interactive video',
            'h5p_type' => 'interactive_video',
        ],
        'flashcard' => [
            // No title column on this table - the question stands in for one.
            'table' => 'h5p_flashcard',
            'title' => 'question',
            'route' => '/h5p/h5p_flashacard', // route spelling is the legacy one; kept verbatim
            'label' => 'H5P flashcard',
            'h5p_type' => 'flashcards',
        ],
    ];

    /**
     * The category bucket H5P items appear under in the content list.
     *
     * Matches the frontend filter tab value exactly. This is the "format tag" the
     * tracker asks for: it sits on the same axis as Presentations / Videos, not on
     * the audience axis that Classroom vs Teacher occupies.
     */
    public const CATEGORY = 'H5P Interactive';

    /**
     * H5P assets for one chapter, in the same shape as a content_master row.
     *
     * Scoped additively - the platform layer plus this tenant - so H5P follows the
     * same "platform PLUS your own, never a fork" rule as everything else.
     *
     * @return list<array<string,mixed>>
     */
    public function forChapter(int|string $chapterId, int|string|null $subInstituteId): array
    {
        if (! config('lms_content.h5p.surface_in_content_list', true)) {
            return [];
        }

        $tenants = array_values(array_unique(array_merge(
            array_map('intval', (array) config('lms_content.platform_sub_institute_ids', [1])),
            $subInstituteId !== null && $subInstituteId !== '' ? [(int) $subInstituteId] : []
        )));

        $assets = [];

        foreach (self::SOURCES as $kind => $spec) {
            // The estate is uneven: two of these three tables are empty today and one
            // may not exist on every deployment. Missing is normal, not an error.
            if (! Schema::hasTable($spec['table'])) {
                continue;
            }

            $query = DB::table($spec['table'])
                ->where('chapter_id', $chapterId)
                ->whereIn('sub_institute_id', $tenants);

            if (Schema::hasColumn($spec['table'], 'deleted_at')) {
                $query->whereNull('deleted_at');
            }

            foreach ($query->get() as $row) {
                $assets[] = $this->toAsset($kind, $spec, (array) $row);
            }
        }

        return $assets;
    }

    /**
     * Map one h5p_* row into the content-asset shape the chapter list already uses.
     *
     * Keys mirror content_master so the frontend needs no special case: it renders an
     * H5P item with the same card component, and only `deep_link` differs in how the
     * card is opened.
     *
     * @param  array<string,mixed>  $row
     * @return array<string,mixed>
     */
    private function toAsset(string $kind, array $spec, array $row): array
    {
        $id = (int) ($row['id'] ?? 0);
        $title = trim((string) ($row[$spec['title']] ?? ''));

        return [
            // Namespaced so it can never be confused with a content_master id.
            'id'               => sprintf('h5p:%s:%d', $kind, $id),
            'h5p_source_id'    => $id,
            'h5p_kind'         => $kind,
            'h5p_type'         => $spec['h5p_type'],

            'title'            => $title !== '' ? $title : $spec['label'] . ' #' . $id,
            'description'      => $row['description'] ?? null,

            // The format axis - this is the whole point of row 2.
            'format'           => 'h5p',
            'file_type'        => 'h5p',
            'content_category' => self::CATEGORY,
            'source'           => 'H5P',

            // Where clicking it goes. The existing /h5p/* editors keep the CRUD.
            'deep_link'        => $spec['route'] . '/' . $id,
            'url'              => $spec['route'] . '/' . $id,

            'chapter_id'       => $row['chapter_id'] ?? null,
            'subject_id'       => $row['subject_id'] ?? null,
            'standard_id'      => $row['standard_id'] ?? null,
            'sub_institute_id' => $row['sub_institute_id'] ?? null,
            'concept_id'       => null,
            'concept_name'     => null,
            'topic_id'         => null,
            'created_by'       => $row['created_by'] ?? null,
            'created_at'       => $row['created_at'] ?? null,

            // No h5p_* table records an audience, and inventing one would repeat the
            // string-match mistake the Classroom/Teacher split already makes.
            'audience'         => 'both',
        ];
    }
}
