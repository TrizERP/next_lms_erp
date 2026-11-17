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
    public const SHARED_LIBRARY_INSTITUTE_ID = 1;

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
        $tenantId = (int) $context->selectedInstituteId;

        // Tenant-first course scoping: if tenant has its own published courses, scope strictly to tenant
        $tenantHasCourses = DB::table('sub_std_map')
            ->where('sub_institute_id', $tenantId)
            ->where('allow_content', 'Yes')
            ->exists();

        $courseScope = $tenantHasCourses ? [$tenantId] : array_values(array_unique([$tenantId, self::SHARED_LIBRARY_INSTITUTE_ID]));

        $query = DB::table('sub_std_map as m')
            ->join('standard as std', function ($join) {
                $join->on('std.id', '=', 'm.standard_id')
                    ->on('std.sub_institute_id', '=', 'm.sub_institute_id');
            })
            ->whereIn('m.sub_institute_id', $courseScope)
            ->where('m.allow_content', 'Yes');

        // Names as well as ids: a planner fills this from the user's sentence, and an
        // id-only tool leaves it nothing to send. See ClassTeacherService::resolve().
        $standardId = (int) ($filters['standard_id'] ?? 0);
        $standardName = trim((string) ($filters['standard_name'] ?? ''));

        if ($standardId <= 0 && $standardName !== '') {
            $standardId = $this->resolveStandardId($context, $standardName);

            if ($standardId <= 0) {
                return [
                    'count' => 0,
                    'courses' => [],
                    'unresolved_filters' => ['standard "' . $standardName . '"'],
                    'note' => 'This institute has no standard named "' . $standardName . '".',
                ];
            }
        }

        if ($standardId > 0) {
            $query->where('m.standard_id', $standardId);
        }

        if (! empty($filters['subject_id'])) {
            $query->where('m.subject_id', (int) $filters['subject_id']);
        }

        $subjectName = trim((string) ($filters['subject_name'] ?? ''));
        if ($subjectName !== '') {
            $exactExists = (clone $query)->where(function ($q) use ($subjectName) {
                $q->where('m.display_name', $subjectName)
                  ->orWhere('m.display_name', strtoupper($subjectName));
            })->exists();

            if ($exactExists) {
                $query->where(function ($q) use ($subjectName) {
                    $q->where('m.display_name', $subjectName)
                      ->orWhere('m.display_name', strtoupper($subjectName));
                });
            } else {
                $query->where('m.display_name', 'like', '%' . $subjectName . '%');
            }
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

        $allScope = $this->scope($context);
        $chapters = $this->chaptersFor($rows, $allScope, (bool) ($filters['include_chapters'] ?? true));

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

        $response = [
            'count' => count($courses),
            'limit' => $limit,
            'courses' => $courses,
        ];

        if (count($courses) === 1) {
            $response['subject_name'] = $courses[0]['subject_name'];
            $response['standard_name'] = $courses[0]['standard_name'];
            $response['chapter_count'] = $courses[0]['chapter_count'];
            $response['chapters'] = $courses[0]['chapters'];
        }

        if (! empty($filters['query_type'])) {
            $response['query_type'] = $filters['query_type'];
        }

        $validator = app(\App\Services\Lms\CourseCatalogueHierarchyValidator::class);

        if (!empty($filters['validate_hierarchy'])) {
            $force = !empty($filters['force']);
            $response['hierarchy_validation'] = $validator->validate($context->selectedInstituteId, $force);
        } else {
            $latest = $validator->getLatestRun($context->selectedInstituteId);
            if ($latest) {
                $response['latest_hierarchy_validation'] = [
                    'status' => $latest['status'],
                    'last_run_time' => $latest['last_run_time'],
                    'next_scheduled_run' => $latest['next_scheduled_run'],
                    'counts' => $latest['counts'],
                    'summary' => $latest['summary'],
                ];
            }
        }

        return $response;
    }

    /**
     * Retrieve topics for a given chapter.
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function topics(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('topic_master')) {
            return ['count' => 0, 'topics' => [], 'note' => 'The topic master table is not available.'];
        }

        $chapterId = (int) ($filters['chapter_id'] ?? 0);

        if ($chapterId <= 0) {
            $chapterId = $this->resolveChapterId($context, $filters);
        }

        if ($chapterId <= 0) {
            return [
                'count' => 0,
                'topics' => [],
                'note' => 'Could not identify the specific chapter for topic retrieval. Please specify the chapter name or number.',
            ];
        }

        $resolvedChapter = DB::table('chapter_master')->where('id', $chapterId)->first();
        $resolvedChapterName = $resolvedChapter?->chapter_name ?? "Chapter {$chapterId}";

        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);

        $query = DB::table('topic_master')
            ->where('chapter_id', $chapterId)
            ->where(function ($q) {
                $q->where('topic_show_hide', 1)
                  ->orWhere('topic_show_hide', '1')
                  ->orWhereNull('topic_show_hide');
            })
            ->orderBy('topic_sort_order')
            ->orderBy('name')
            ->limit($limit);

        $search = trim((string) ($filters['query'] ?? ''));
        if ($search !== '') {
            $query->where('name', 'like', '%' . $search . '%');
        }

        $rows = $query->get(['id', 'chapter_id', 'name', 'description', 'estimated_minutes', 'topic_sort_order']);

        $topics = $rows->map(static fn ($r) => [
            'topic_id' => (int) $r->id,
            'topic_name' => (string) $r->name,
            'description' => $r->description !== null ? (string) $r->description : null,
            'estimated_minutes' => $r->estimated_minutes !== null ? (int) $r->estimated_minutes : null,
            'sort_order' => (int) $r->topic_sort_order,
        ])->all();

        $response = [
            'count' => count($topics),
            'chapter_id' => $chapterId,
            'chapter_name' => $resolvedChapterName,
            'topics' => $topics,
        ];

        if (! empty($filters['query_type'])) {
            $response['query_type'] = $filters['query_type'];
        }

        return $response;
    }

    /**
     * Resolve a standard name or number into a standard_id, prioritizing the tenant's own institute.
     */
    public function resolveStandardId(McpRequestContext $context, string $standardName): int
    {
        $tenantId = (int) $context->selectedInstituteId;

        // Normalize Indic numerals (Gujarati, Hindi) to ASCII digits
        $clean = strtr(trim($standardName), [
            '૦' => '0', '૧' => '1', '૨' => '2', '૩' => '3', '૪' => '4', '૫' => '5', '૬' => '6', '૭' => '7', '૮' => '8', '૯' => '9',
            '०' => '0', '१' => '1', '२' => '2', '३' => '3', '४' => '4', '५' => '5', '६' => '6', '७' => '7', '८' => '8', '९' => '9',
        ]);

        // 1. Check tenant institute first
        $resolved = DB::table('standard')
            ->where('sub_institute_id', $tenantId)
            ->where('name', $clean)
            ->value('id');

        if ($resolved !== null) {
            return (int) $resolved;
        }

        $digits = preg_replace('/[^\d]/', '', $clean);
        if ($digits !== '') {
            $resolved = DB::table('standard')
                ->where('sub_institute_id', $tenantId)
                ->where('name', 'like', '%-' . $digits)
                ->orderBy('id')
                ->value('id');

            if ($resolved !== null) {
                return (int) $resolved;
            }

            $resolved = DB::table('standard')
                ->where('sub_institute_id', $tenantId)
                ->where(function ($q) use ($digits) {
                    $q->where('name', 'like', 'Standard ' . $digits)
                      ->orWhere('name', 'like', 'Class ' . $digits)
                      ->orWhere('name', 'like', 'Std ' . $digits)
                      ->orWhere('name', 'like', '%' . $digits);
                })
                ->orderBy('id')
                ->value('id');

            if ($resolved !== null) {
                return (int) $resolved;
            }
        }

        // 2. Fall back to shared library Institute 1
        if ($tenantId !== self::SHARED_LIBRARY_INSTITUTE_ID) {
            $resolved = DB::table('standard')
                ->where('sub_institute_id', self::SHARED_LIBRARY_INSTITUTE_ID)
                ->where('name', $clean)
                ->value('id');

            if ($resolved !== null) {
                return (int) $resolved;
            }

            if ($digits !== '') {
                $resolved = DB::table('standard')
                    ->where('sub_institute_id', self::SHARED_LIBRARY_INSTITUTE_ID)
                    ->where(function ($q) use ($digits) {
                        $q->where('name', $digits)
                          ->orWhere('name', 'like', '%-' . $digits);
                    })
                    ->orderBy('id')
                    ->value('id');

                if ($resolved !== null) {
                    return (int) $resolved;
                }
            }
        }

        return 0;
    }

    /**
     * Resolve a chapter ID from filters or context.
     *
     * @param array<string, mixed> $filters
     */
    public function resolveChapterId(McpRequestContext $context, array $filters): int
    {
        $chapterId = (int) ($filters['chapter_id'] ?? 0);
        if ($chapterId > 0 && DB::table('chapter_master')->where('id', $chapterId)->exists()) {
            return $chapterId;
        }

        $scope = $this->scope($context);
        $chapterName = trim((string) ($filters['chapter_name'] ?? ''));

        if ($chapterName !== '') {
            $found = DB::table('chapter_master')
                ->whereIn('sub_institute_id', $scope)
                ->where(function ($q) use ($chapterName) {
                    $q->where('chapter_name', $chapterName)
                      ->orWhere('chapter_name', 'like', '%' . $chapterName . '%');
                })
                ->where('show_hide', 1)
                ->orderBy('sort_order')
                ->value('id');

            if ($found !== null) {
                return (int) $found;
            }
        }

        // Chapter number within a course/subject
        $chapterNumber = (int) ($filters['chapter_number'] ?? 0);
        $subjectName = trim((string) ($filters['subject_name'] ?? ''));
        $standardName = trim((string) ($filters['standard_name'] ?? ''));

        if ($chapterNumber > 0 || $subjectName !== '') {
            $catFilters = [];
            if ($standardName !== '') {
                $catFilters['standard_name'] = $standardName;
            }
            if ($subjectName !== '') {
                $catFilters['subject_name'] = $subjectName;
            }
            $cat = $this->catalogue($context, $catFilters);
            $courses = $cat['courses'] ?? [];

            foreach ($courses as $c) {
                $chList = $c['chapters'] ?? [];
                if (! empty($chList)) {
                    $idx = max(0, $chapterNumber - 1);
                    if (isset($chList[$idx])) {
                        return (int) $chList[$idx]['chapter_id'];
                    }
                    if ($chapterNumber <= 0 && isset($chList[0])) {
                        return (int) $chList[0]['chapter_id'];
                    }
                }
            }
        }

        return 0;
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

        $directChapters = DB::table('chapter_master')
            ->whereIn('sub_institute_id', $scope)
            ->whereIn('subject_id', $subjectIds)
            ->whereIn('standard_id', $standardIds)
            ->where('show_hide', 1)
            ->orderBy('sort_order')
            ->orderBy('chapter_name')
            ->get(['id', 'chapter_name', 'chapter_desc', 'subject_id', 'standard_id', 'syear']);

        $map = [];

        foreach ($directChapters as $chapter) {
            $key = $chapter->subject_id . ':' . $chapter->standard_id;

            $map[$key][] = [
                'chapter_id' => (int) $chapter->id,
                'chapter_name' => (string) $chapter->chapter_name,
                'description' => $chapter->chapter_desc !== null ? (string) $chapter->chapter_desc : null,
                'academic_year' => $chapter->syear !== null ? (int) $chapter->syear : null,
            ];
        }

        // For courses with 0 direct chapters, fall back to shared library (Institute 1)
        foreach ($rows as $row) {
            $key = $row->subject_id . ':' . $row->standard_id;
            if (! empty($map[$key])) {
                continue;
            }

            $shared = $this->resolveSharedChapters(
                (string) $row->standard_name,
                (string) $row->subject_name
            );

            if (! empty($shared)) {
                $map[$key] = $shared;
            }
        }

        return $map;
    }

    /**
     * Resolve chapters from the shared content library (Institute 1) by standard name and subject name.
     *
     * @return array<int, array<string, mixed>>
     */
    private function resolveSharedChapters(string $standardName, string $subjectName): array
    {
        // 1. Clean standard name: e.g. "CBSE-6" -> "6", "Class 6" -> "6"
        $digits = preg_replace('/[^\d]/', '', $standardName);
        $cleanStd = $digits !== '' ? $digits : trim($standardName);

        $inst1Std = DB::table('standard')
            ->where('sub_institute_id', self::SHARED_LIBRARY_INSTITUTE_ID)
            ->where(function ($q) use ($cleanStd) {
                $q->where('name', $cleanStd)
                  ->orWhere('name', 'like', '%-' . $cleanStd);
            })
            ->first();

        if (! $inst1Std) {
            return [];
        }

        $cleanSub = trim($subjectName);

        // Find candidate courses in Inst 1 sub_std_map
        $inst1Courses = DB::table('sub_std_map as m')
            ->join('subject as sub', 'sub.id', '=', 'm.subject_id')
            ->where('m.sub_institute_id', self::SHARED_LIBRARY_INSTITUTE_ID)
            ->where('m.standard_id', $inst1Std->id)
            ->select('m.subject_id', 'm.display_name', 'sub.subject_name')
            ->get();

        // Exact match first
        $matched = $inst1Courses->first(function ($c) use ($cleanSub) {
            return strcasecmp(trim($c->display_name), $cleanSub) === 0
                || strcasecmp(trim($c->subject_name), $cleanSub) === 0;
        });

        // Prefix match fallback (e.g. Hindi -> Hindi Malhar)
        if (! $matched) {
            $matched = $inst1Courses->first(function ($c) use ($cleanSub) {
                return stripos(trim($c->display_name), $cleanSub) === 0
                    || stripos(trim($c->subject_name), $cleanSub) === 0;
            });
        }

        if (! $matched) {
            return [];
        }

        $chapters = DB::table('chapter_master')
            ->where('sub_institute_id', self::SHARED_LIBRARY_INSTITUTE_ID)
            ->where('standard_id', $inst1Std->id)
            ->where('subject_id', $matched->subject_id)
            ->where('show_hide', 1)
            ->orderBy('sort_order')
            ->orderBy('chapter_name')
            ->get(['id', 'chapter_name', 'chapter_desc', 'subject_id', 'standard_id', 'syear']);

        if ($chapters->isEmpty()) {
            $chapters = DB::table('chapter_master')
                ->where('sub_institute_id', self::SHARED_LIBRARY_INSTITUTE_ID)
                ->where('standard_id', $inst1Std->id)
                ->where('subject_id', $matched->subject_id)
                ->orderBy('sort_order')
                ->orderBy('chapter_name')
                ->get(['id', 'chapter_name', 'chapter_desc', 'subject_id', 'standard_id', 'syear']);
        }

        return $chapters->map(static fn ($ch) => [
            'chapter_id' => (int) $ch->id,
            'chapter_name' => (string) $ch->chapter_name,
            'description' => $ch->chapter_desc !== null ? (string) $ch->chapter_desc : null,
            'academic_year' => $ch->syear !== null ? (int) $ch->syear : null,
        ])->all();
    }
}
