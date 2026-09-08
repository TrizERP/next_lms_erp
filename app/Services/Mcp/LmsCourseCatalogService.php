<?php

namespace App\Services\Mcp;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The published LMS courses — which subjects carry content, and the chapters inside them.
 *
 * Distinct from `academics.subjects`, which resolves a subject *name* to the id other
 * tools need. This answers a different question: what is actually teachable, for whom,
 * and how much of it there is. A subject can exist on the timetable and carry no content
 * at all, which is why `allow_content` rather than the subject list is the source here.
 *
 * The institute-scoping is the part that is easy to get wrong. Content is published
 * either by the institute or centrally, so both the subject map and the chapters are read
 * across the institute *and* institute 1 — the shared library — exactly as
 * ApiLmsCourseController does. Scoping to the institute alone returns an empty catalogue
 * for every school that uses the central content, which is most of them.
 */
class LmsCourseCatalogService
{
    /** The shared content library every institute may read from. */
    private const SHARED_LIBRARY_INSTITUTE_ID = 1;

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function catalogue(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('sub_std_map')) {
            return ['count' => 0, 'courses' => [], 'note' => 'The LMS course map is not installed on this estate.'];
        }

        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);
        $scope = $this->scope($context);

        $query = DB::table('sub_std_map as m')
            ->join('standard as std', function ($join) {
                $join->on('std.id', '=', 'm.standard_id')
                    ->on('std.sub_institute_id', '=', 'm.sub_institute_id');
            })
            ->whereIn('m.sub_institute_id', $scope)
            ->where('m.allow_content', 'Yes');

        // Names as well as ids: a planner fills this from the user's sentence, and an
        // id-only tool leaves it nothing to send. See ClassTeacherService::resolve().
        $standardId = (int) ($filters['standard_id'] ?? 0);
        $standardName = trim((string) ($filters['standard_name'] ?? ''));

        if ($standardId <= 0 && $standardName !== '') {
            $resolved = DB::table('standard')
                ->whereIn('sub_institute_id', $scope)
                ->where('name', $standardName)
                ->value('id');

            if ($resolved === null) {
                return [
                    'count' => 0,
                    'courses' => [],
                    'unresolved_filters' => ['standard "' . $standardName . '"'],
                    'note' => 'This institute has no standard named "' . $standardName . '".',
                ];
            }

            $standardId = (int) $resolved;
        }

        if ($standardId > 0) {
            $query->where('m.standard_id', $standardId);
        }

        if (! empty($filters['subject_id'])) {
            $query->where('m.subject_id', (int) $filters['subject_id']);
        }

        $category = trim((string) ($filters['category'] ?? ''));

        if ($category !== '') {
            $query->where('m.subject_category', $category);
        }

        $search = trim((string) ($filters['query'] ?? ''));

        if ($search !== '') {
            $query->where('m.display_name', 'like', '%' . $search . '%');
        }

        $rows = $query
            ->selectRaw(
                "m.subject_id, m.standard_id, m.display_name AS subject_name,
                 std.name AS standard_name,
                 IFNULL(m.subject_category, 'My Course') AS category,
                 m.sort_order"
            )
            // One row per subject per class. The map holds a row per category as well,
            // so without this a subject appears once for every category it sits in.
            ->groupBy('m.subject_id', 'm.standard_id', 'm.subject_category', 'm.display_name', 'std.name', 'm.sort_order')
            ->orderBy('m.sort_order')
            ->orderBy('m.display_name')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return [
                'count' => 0,
                'courses' => [],
                'note' => 'No published courses match that filter. A subject only appears here once '
                    . 'its content has been allowed, so a subject on the timetable may still be absent.',
            ];
        }

        $chapters = $this->chaptersFor($rows, $scope, (bool) ($filters['include_chapters'] ?? true));

        $courses = $rows->map(static function ($row) use ($chapters) {
            $key = $row->subject_id . ':' . $row->standard_id;
            $own = $chapters[$key] ?? [];

            return [
                'subject_id' => (int) $row->subject_id,
                'subject_name' => (string) $row->subject_name,
                'standard_id' => (int) $row->standard_id,
                'standard_name' => (string) $row->standard_name,
                'category' => (string) $row->category,
                'chapter_count' => count($own),
                'chapters' => $own,
            ];
        })->all();

        return [
            'count' => count($courses),
            'limit' => $limit,
            'courses' => $courses,
        ];
    }

    /**
     * The institutes whose published content this caller may read.
     *
     * @return array<int, int>
     */
    private function scope(McpRequestContext $context): array
    {
        return array_values(array_unique([
            $context->selectedInstituteId,
            self::SHARED_LIBRARY_INSTITUTE_ID,
        ]));
    }

    /**
     * Chapters for the listed courses, keyed `subject_id:standard_id`.
     *
     * Fetched in one query rather than per course: a catalogue of fifty subjects would
     * otherwise be fifty round trips to an estate this application reaches over the
     * open internet.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     * @param  array<int, int>  $scope
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function chaptersFor($rows, array $scope, bool $include): array
    {
        if (! $include || $rows->isEmpty() || ! Schema::hasTable('chapter_master')) {
            return [];
        }

        $subjectIds = $rows->pluck('subject_id')->unique()->values()->all();
        $standardIds = $rows->pluck('standard_id')->unique()->values()->all();

        $chapters = DB::table('chapter_master')
            ->whereIn('sub_institute_id', $scope)
            ->whereIn('subject_id', $subjectIds)
            ->whereIn('standard_id', $standardIds)
            // 1 is visible; blank is not. Stored as a string in places, so compared loosely.
            ->where('show_hide', 1)
            ->orderBy('sort_order')
            ->orderBy('chapter_name')
            ->get(['id', 'chapter_name', 'chapter_desc', 'subject_id', 'standard_id', 'syear']);

        $map = [];

        foreach ($chapters as $chapter) {
            $key = $chapter->subject_id . ':' . $chapter->standard_id;

            $map[$key][] = [
                'chapter_id' => (int) $chapter->id,
                'chapter_name' => (string) $chapter->chapter_name,
                'description' => $chapter->chapter_desc !== null ? (string) $chapter->chapter_desc : null,
                'academic_year' => $chapter->syear !== null ? (int) $chapter->syear : null,
            ];
        }

        return $map;
    }
}
