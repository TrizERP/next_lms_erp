<?php

namespace App\Services\Mcp;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What a course actually contains: its chapters, and how much of each has a lesson plan.
 *
 * Built on the published catalogue (`LmsCourseCatalogService`) for WHICH course, then reads
 * `chapter_master` for its chapters and `lms_lesson_plan` for the plan written against each
 * one. Nothing is generated: a chapter with no lesson-plan rows is reported as having none,
 * because inventing a plan for it is exactly the answer a teacher cannot check.
 *
 * Lesson plans are the institute's own and are year-scoped, so they are read for the caller's
 * institute only — never from the shared library — and the year they were read for is stated.
 */
class LmsCourseSummaryService
{
    /** Longest learning-objective excerpt carried per chapter. */
    private const OBJECTIVE_LENGTH = 160;

    public function __construct(private readonly LmsCourseCatalogService $catalogue)
    {
    }

    /**
     * @param  array<string, mixed>  $args  `standard_id`, `standard_name`, `subject_id`, `question`, `syear`
     * @return array<string, mixed>
     */
    public function summary(McpRequestContext $context, array $args): array
    {
        $courses = $this->catalogue->catalogue($context, [
            'standard_id' => $args['standard_id'] ?? null,
            'standard_name' => $args['standard_name'] ?? null,
            'subject_id' => $args['subject_id'] ?? null,
            'include_chapters' => false,
            'limit' => 200,
        ]);

        if (isset($courses['unresolved_filters'])) {
            return $courses + ['headline' => 'I could not find that class, so there is no course to summarise.'];
        }

        $rows = (array) ($courses['courses'] ?? []);

        // A subject named in the sentence narrows the list when nothing else did.
        if (empty($args['subject_id']) && isset($args['question']) && count($rows) > 1) {
            $named = array_values(array_filter(
                $rows,
                fn (array $row) => $this->mentions((string) $args['question'], (string) $row['subject_name'])
            ));

            if ($named !== []) {
                $rows = $named;
            }
        }

        if ($rows === []) {
            return [
                'count' => 0,
                'courses' => [],
                'headline' => 'No published course matches, so there are no chapters or lesson plans to summarise.',
            ];
        }

        $syear = isset($args['syear']) && $args['syear'] !== '' ? (int) $args['syear'] : $context->academicYear;

        return count($rows) === 1
            ? $this->one($context, $rows[0], $syear)
            : $this->many($context, $rows, $syear);
    }

    /**
     * One course: every chapter with the lesson plan written for it.
     *
     * @param  array<string, mixed>  $course
     * @return array<string, mixed>
     */
    private function one(McpRequestContext $context, array $course, ?int $syear): array
    {
        $chapters = $this->chapters($context, [$course]);
        $plans = $this->plans($context, [$course], $syear);

        $planned = 0;
        $periods = 0;
        $rows = [];

        foreach ($chapters as $chapter) {
            $plan = $plans[$chapter['chapter_id']] ?? null;
            $planned += $plan !== null ? 1 : 0;
            $periods += (int) ($plan['periods'] ?? 0);

            $rows[] = array_filter([
                'chapter_name' => $chapter['chapter_name'],
                'chapter_id' => $chapter['chapter_id'],
                'topics_planned' => $plan['topics'] ?? 0,
                'periods_planned' => $plan['periods'] ?? 0,
                'lesson_plan' => $plan === null ? 'none written' : 'written',
                'learning_objective' => $plan['objective'] ?? null,
            ], static fn ($value) => $value !== null);
        }

        $label = $course['subject_name'] . ' (' . $course['standard_name'] . ')';

        return [
            'count' => count($rows),
            'chapters' => $rows,
            'headline' => $rows === []
                ? sprintf('%s has no chapters on record.', $label)
                : sprintf(
                    '%s has %d chapter%s; %d %s a lesson plan (%d period%s planned)%s.',
                    $label,
                    count($rows),
                    count($rows) === 1 ? '' : 's',
                    $planned,
                    $planned === 1 ? 'has' : 'have',
                    $periods,
                    $periods === 1 ? '' : 's',
                    $syear !== null ? ' for ' . $syear : ''
                ),
        ];
    }

    /**
     * Several courses: one line each, so a class-wide question is answered without a wall of chapters.
     *
     * @param  array<int, array<string, mixed>>  $courses
     * @return array<string, mixed>
     */
    private function many(McpRequestContext $context, array $courses, ?int $syear): array
    {
        $chapters = $this->chapters($context, $courses);
        $plans = $this->plans($context, $courses, $syear);

        $perCourse = [];

        foreach ($chapters as $chapter) {
            $key = $chapter['subject_id'] . ':' . $chapter['standard_id'];
            $perCourse[$key]['chapters'] = ($perCourse[$key]['chapters'] ?? 0) + 1;

            if (isset($plans[$chapter['chapter_id']])) {
                $perCourse[$key]['planned'] = ($perCourse[$key]['planned'] ?? 0) + 1;
                $perCourse[$key]['periods'] = ($perCourse[$key]['periods'] ?? 0) + $plans[$chapter['chapter_id']]['periods'];
            }
        }

        $rows = array_map(static function (array $course) use ($perCourse) {
            $figures = $perCourse[$course['subject_id'] . ':' . $course['standard_id']] ?? [];

            return [
                'subject_name' => $course['subject_name'],
                'standard_name' => $course['standard_name'],
                'subject_id' => $course['subject_id'],
                'standard_id' => $course['standard_id'],
                'chapters' => (int) ($figures['chapters'] ?? 0),
                'chapters_with_lesson_plan' => (int) ($figures['planned'] ?? 0),
                'periods_planned' => (int) ($figures['periods'] ?? 0),
            ];
        }, $courses);

        $totalChapters = array_sum(array_column($rows, 'chapters'));
        $totalPlanned = array_sum(array_column($rows, 'chapters_with_lesson_plan'));

        return [
            'count' => count($rows),
            'courses' => $rows,
            'headline' => sprintf(
                '%d course%s, %d chapter%s, %d with a lesson plan%s. Name a subject to see its chapters.',
                count($rows),
                count($rows) === 1 ? '' : 's',
                $totalChapters,
                $totalChapters === 1 ? '' : 's',
                $totalPlanned,
                $syear !== null ? ' for ' . $syear : ''
            ),
        ];
    }

    /**
     * Visible chapters for the courses, shared library included — the same rule the catalogue uses.
     *
     * @param  array<int, array<string, mixed>>  $courses
     * @return array<int, array<string, mixed>>
     */
    private function chapters(McpRequestContext $context, array $courses): array
    {
        if (! Schema::hasTable('chapter_master') || $courses === []) {
            return [];
        }

        return DB::table('chapter_master')
            ->whereIn('sub_institute_id', array_unique([$context->selectedInstituteId, 1]))
            ->whereIn('subject_id', array_column($courses, 'subject_id'))
            ->whereIn('standard_id', array_column($courses, 'standard_id'))
            ->where('show_hide', 1)
            ->orderBy('sort_order')
            ->orderBy('chapter_name')
            ->get(['id', 'chapter_name', 'subject_id', 'standard_id'])
            ->map(static fn ($row) => [
                'chapter_id' => (int) $row->id,
                'chapter_name' => (string) $row->chapter_name,
                'subject_id' => (int) $row->subject_id,
                'standard_id' => (int) $row->standard_id,
            ])
            ->all();
    }

    /**
     * Lesson-plan rows for the courses, rolled up per chapter, this institute and year only.
     *
     * @param  array<int, array<string, mixed>>  $courses
     * @return array<int, array{topics:int, periods:int, objective:?string}>
     */
    private function plans(McpRequestContext $context, array $courses, ?int $syear): array
    {
        if (! Schema::hasTable('lms_lesson_plan') || $courses === []) {
            return [];
        }

        $rows = DB::table('lms_lesson_plan')
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->when($syear !== null, fn ($q) => $q->where('syear', $syear))
            ->whereIn('subject_id', array_column($courses, 'subject_id'))
            ->whereIn('standard_id', array_column($courses, 'standard_id'))
            ->orderBy('id')
            ->get(['chapter_id', 'topic_id', 'numberofperiod', 'learningobjective']);

        $plans = [];

        foreach ($rows as $row) {
            $id = (int) $row->chapter_id;
            $plans[$id]['topics'] = ($plans[$id]['topics'] ?? 0) + 1;
            $plans[$id]['periods'] = ($plans[$id]['periods'] ?? 0) + (int) $row->numberofperiod;

            $objective = trim(strip_tags((string) $row->learningobjective));

            if (($plans[$id]['objective'] ?? null) === null && $objective !== '') {
                $plans[$id]['objective'] = mb_strimwidth($objective, 0, self::OBJECTIVE_LENGTH, '…');
            }
        }

        foreach ($plans as $id => $plan) {
            $plans[$id]['objective'] = $plan['objective'] ?? null;
        }

        return $plans;
    }

    private function mentions(string $question, string $subject): bool
    {
        $subject = trim($subject);

        return $subject !== '' && stripos($question, $subject) !== false;
    }
}
