<?php

namespace App\Services\Curriculum;

use Illuminate\Support\Facades\DB;

/**
 * "Is this chapter actually being taught?" - the one execution signal this
 * estate has, read fresh for a given set of chapters.
 *
 * `lms_lesson_plan_periods` (via its parent `lms_intelligence_lesson_plans`)
 * is the table CurriculumPlanningApiController already overlays onto the
 * curriculum tree for the same purpose. This class exists so a second
 * consumer (OutcomeAnalyticsService) does not re-derive the Done/In
 * progress/Upcoming rule from scratch - this estate already carries two
 * other lesson-delivery tables (`lessonplan`/`lessonplan_execution`,
 * `lms_lesson_plan`) that are NOT what either of these read, so a third,
 * subtly different reading of "is it delivered" is a real risk, not a
 * theoretical one.
 */
class ChapterDeliveryStatus
{
    /**
     * @param  array<int,int>  $chapterIds
     * @return array<int, array{status:string, total_periods:int, completed_periods:int, start_date:?string, end_date:?string}>
     *         Keyed by chapter_id. Every requested id is present, even with
     *         zero periods (status "Upcoming"), so callers never need an
     *         isset() guard.
     */
    public function forChapters(
        int $subInstituteId,
        $syear,
        ?int $standardId,
        ?int $divisionId,
        ?int $termId,
        array $chapterIds
    ): array {
        $empty = [
            'status' => 'Upcoming',
            'total_periods' => 0,
            'completed_periods' => 0,
            'start_date' => null,
            'end_date' => null,
        ];

        if (empty($chapterIds)) {
            return [];
        }

        $planIds = DB::table('lms_intelligence_lesson_plans as lp')
            ->where('lp.sub_institute_id', $subInstituteId)
            ->where('lp.syear', $syear)
            ->when($standardId, fn ($q) => $q->where('lp.standard_id', $standardId))
            ->when($divisionId, fn ($q) => $q->where('lp.division_id', $divisionId))
            ->when($termId, fn ($q) => $q->where('lp.term_id', $termId))
            ->pluck('lp.id');

        $periodsByChapter = collect();
        if ($planIds->isNotEmpty()) {
            $periodsByChapter = DB::table('lms_lesson_plan_periods')
                ->whereIn('lms_intelligence_lesson_plans_id', $planIds)
                ->whereIn('chapter_id', $chapterIds)
                ->get(['chapter_id', 'status', 'scheduled_date'])
                ->groupBy('chapter_id');
        }

        $out = [];
        foreach ($chapterIds as $chapterId) {
            $rows = $periodsByChapter->get($chapterId) ?? collect();
            $total = $rows->count();

            if ($total === 0) {
                $out[$chapterId] = $empty;
                continue;
            }

            $completed = $rows->where('status', 'completed')->count();
            $inProgress = $rows->where('status', 'in_progress')->count();

            if ($completed === $total) {
                $status = 'Done';
            } elseif ($completed > 0 || $inProgress > 0) {
                $status = 'In progress';
            } else {
                $status = 'Upcoming';
            }

            $out[$chapterId] = [
                'status' => $status,
                'total_periods' => $total,
                'completed_periods' => $completed,
                'start_date' => $rows->min('scheduled_date'),
                'end_date' => $rows->max('scheduled_date'),
            ];
        }

        return $out;
    }
}
