<?php

namespace App\Http\Controllers\api\lms;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class CurriculumPlanningApiController extends Controller
{
    /**
     * Yearly syllabus overview across subjects: summary stats, a subject x
     * month topic grid, upcoming lessons and per-subject chapter progress.
     *
     * `standard_id` is OPTIONAL - when omitted, this returns a combined
     * summary across every standard the institute has curriculum data for
     * (each subject entry still carries its own standard_id/standard_name so
     * the same subject taught in different standards is never merged).
     *
     * The syllabus STRUCTURE (subjects -> units -> chapters -> topics /
     * learning outcomes) comes from the curriculum-authoring tables:
     *   lms_curriculum -> lms_units -> chapter_master -> topic_master /
     *   lms_learning_outcomes / lms_concept / semantic_intelligence
     * (chained by curriculum_id -> unit_id -> chapter_id).
     *
     * The EXECUTION state (when a chapter is actually scheduled/taught, and
     * whether it is done) has no equivalent in those structural tables, so
     * it is overlaid from lms_intelligence_lesson_plans / lms_lesson_plan_periods
     * (matched back to a chapter via periods.chapter_id) - a chapter with no
     * matching periods simply has no schedule yet ("Upcoming").
     *
     * GET|POST /api/intelligence/curriculum-planning
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|integer',
            'syear'            => 'required',
            'standard_id'      => 'nullable|integer',
            'division_id'      => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $filters = $validator->validated();

        // sub_institute_id 1 only has curriculum-authoring data seeded for
        // syear 2026 - force it for that institute so the session's normal
        // "active academic year" (which may resolve to a different year)
        // does not silently return "no data" for this institute alone.
        if ((int) $filters['sub_institute_id'] === 1) {
            $filters['syear'] = 2026;
        }

        try {
            // 1. Curriculum (one row per subject, per standard, for this year).
            //    standard_id is optional - omitting it combines every standard.
            $curricula = DB::table('lms_curriculum as cur')
                ->join('subject as sub', 'sub.id', '=', 'cur.subject_id')
                ->leftJoin('standard as std', 'std.id', '=', 'cur.standard_id')
                ->where('cur.sub_institute_id', $filters['sub_institute_id'])
                ->where('cur.syear', $filters['syear'])
                ->when(!empty($filters['standard_id']), fn ($q) => $q->where('cur.standard_id', $filters['standard_id']))
                ->orderBy('std.sort_order')
                ->orderBy('sub.subject_name')
                ->select('cur.*', 'sub.subject_name', 'std.name as standard_name')
                ->get();

            if ($curricula->isEmpty()) {
                return response()->json([
                    'status'  => false,
                    'message' => 'No Curriculum Plan Data Found',
                    'data'    => [],
                ], 404);
            }

            $curriculumIds = $curricula->pluck('id')->all();

            // 2. Units per curriculum.
            $units = DB::table('lms_units')
                ->whereIn('curriculum_id', $curriculumIds)
                ->orderBy('unit_number')
                ->get()
                ->groupBy('curriculum_id');

            $unitIds = $units->flatten()->pluck('id')->all();

            // 3. Chapters per unit - these are the "topics" shown in the planner grid.
            $chapters = collect();
            if (!empty($unitIds)) {
                $chapters = DB::table('chapter_master')
                    ->whereIn('unit_id', $unitIds)
                    ->orderBy('sort_order')
                    ->get()
                    ->groupBy('unit_id');
            }

            $chapterIds = $chapters->flatten()->pluck('id')->all();

            // 4. Chapter enrichment: topics, learning outcomes, concept count, semantic intelligence.
            $topicsByChapter = collect();
            $outcomesByChapter = collect();
            $conceptCountByChapter = collect();
            $semanticByChapter = collect();

            if (!empty($chapterIds)) {
                $topicsByChapter = DB::table('topic_master')
                    ->whereIn('chapter_id', $chapterIds)
                    ->orderBy('topic_sort_order')
                    ->get(['id', 'chapter_id', 'name', 'description'])
                    ->groupBy('chapter_id');

                $outcomesByChapter = DB::table('lms_learning_outcomes')
                    ->whereIn('chapter_id', $chapterIds)
                    ->orderBy('code')
                    ->get(['id', 'chapter_id', 'code', 'type', 'description'])
                    ->groupBy('chapter_id');

                $conceptCountByChapter = DB::table('lms_concept')
                    ->whereIn('chapter_id', $chapterIds)
                    ->selectRaw('chapter_id, count(*) as total')
                    ->groupBy('chapter_id')
                    ->pluck('total', 'chapter_id');

                $semanticByChapter = DB::table('semantic_intelligence')
                    ->whereIn('chapter_id', $chapterIds)
                    ->get(['chapter_id', 'learning_objective', 'total_concepts', 'blooms_level'])
                    ->keyBy('chapter_id');
            }

            // 5. Execution overlay - periods actually scheduled for this
            //    institute/year (all standards, unless one was requested),
            //    matched back to a chapter via chapter_id.
            $planIds = DB::table('lms_intelligence_lesson_plans as lp')
                ->where('lp.sub_institute_id', $filters['sub_institute_id'])
                ->where('lp.syear', $filters['syear'])
                ->when(!empty($filters['standard_id']), fn ($q) => $q->where('lp.standard_id', $filters['standard_id']))
                ->when(!empty($filters['division_id']), fn ($q) => $q->where('lp.division_id', $filters['division_id']))
                ->pluck('lp.id');

            $allPeriods = collect();
            $periodsByChapter = collect();
            if ($planIds->isNotEmpty()) {
                $allPeriods = DB::table('lms_lesson_plan_periods as pp')
                    ->join('tbluser as tu', 'tu.id', '=', 'pp.teacher_id')
                    ->whereIn('pp.lms_intelligence_lesson_plans_id', $planIds)
                    ->orderBy('pp.scheduled_date')
                    ->select('pp.*', DB::raw('concat(tu.first_name," ",tu.middle_name," ",tu.last_name) as teacher_name'))
                    ->get();

                $periodsByChapter = $allPeriods->filter(fn ($p) => !empty($p->chapter_id))->groupBy('chapter_id');
            }

            $today = Carbon::today();
            $weeksRemaining = $allPeriods
                ->filter(fn ($p) => Carbon::parse($p->scheduled_date)->greaterThanOrEqualTo($today))
                ->pluck('week_number')
                ->unique()
                ->count();

            // 6. Build subject -> unit -> chapter hierarchy with execution status per chapter.
            $chapterStatus = function ($chapterId) use ($periodsByChapter) {
                $chapterPeriods = $periodsByChapter->get($chapterId) ?? collect();
                $total = $chapterPeriods->count();
                $completed = $chapterPeriods->where('status', 'completed')->count();
                $inProgress = $chapterPeriods->where('status', 'in_progress')->count();

                if ($total === 0) {
                    $status = 'Upcoming';
                } elseif ($completed === $total) {
                    $status = 'Done';
                } elseif ($completed > 0 || $inProgress > 0) {
                    $status = 'In progress';
                } else {
                    $status = 'Upcoming';
                }

                return [
                    'status'             => $status,
                    'total_periods'      => $total,
                    'completed_periods'  => $completed,
                    'start_date'         => $chapterPeriods->min('scheduled_date'),
                    'end_date'           => $chapterPeriods->max('scheduled_date'),
                ];
            };

            $subjects = $curricula->map(function ($curriculum) use ($units, $chapters, $topicsByChapter, $outcomesByChapter, $conceptCountByChapter, $semanticByChapter, $chapterStatus) {
                $curriculumUnits = ($units->get($curriculum->id) ?? collect())
                    ->map(function ($unit) use ($chapters, $topicsByChapter, $outcomesByChapter, $conceptCountByChapter, $semanticByChapter, $chapterStatus) {
                        $unitChapters = ($chapters->get($unit->id) ?? collect())
                            ->map(function ($chapter) use ($topicsByChapter, $outcomesByChapter, $conceptCountByChapter, $semanticByChapter, $chapterStatus) {
                                $execution = $chapterStatus($chapter->id);
                                $semantic = $semanticByChapter->get($chapter->id);

                                return [
                                    'chapter_id'         => $chapter->id,
                                    'chapter_name'       => $chapter->chapter_name,
                                    'sort_order'         => $chapter->sort_order,
                                    'topics'             => ($topicsByChapter->get($chapter->id) ?? collect())->values(),
                                    'learning_outcomes'  => ($outcomesByChapter->get($chapter->id) ?? collect())->values(),
                                    'concept_count'      => (int) ($conceptCountByChapter->get($chapter->id) ?? 0),
                                    'learning_objective' => $semantic->learning_objective ?? null,
                                    'status'             => $execution['status'],
                                    'total_periods'      => $execution['total_periods'],
                                    'completed_periods'  => $execution['completed_periods'],
                                    'start_date'         => $execution['start_date'],
                                    'end_date'           => $execution['end_date'],
                                ];
                            })
                            ->values();

                        return [
                            'unit_id'         => $unit->id,
                            'unit_number'     => $unit->unit_number,
                            'unit_name'       => $unit->name,
                            'planned_periods' => $unit->planned_periods,
                            'chapters'        => $unitChapters,
                        ];
                    })
                    ->values();

                $allChapters = $curriculumUnits->flatMap(fn ($unit) => $unit['chapters']);
                $totalChapters = $allChapters->count();
                $doneChapters = $allChapters->where('status', 'Done')->count();

                return [
                    'subject_id'    => $curriculum->subject_id,
                    'subject_name'  => $curriculum->subject_name,
                    'standard_id'   => $curriculum->standard_id,
                    'standard_name' => $curriculum->standard_name,
                    'curriculum_id' => $curriculum->id,
                    'board'         => $curriculum->board,
                    'progress'      => $totalChapters > 0 ? (int) round(($doneChapters / $totalChapters) * 100) : 0,
                    'units'         => $curriculumUnits,
                ];
            })->values();

            // 7. Summary stats across all subjects.
            $allChaptersFlat = $subjects->flatMap(fn ($subject) => collect($subject['units'])->flatMap(fn ($unit) => $unit['chapters']));
            $totalTopics = $allChaptersFlat->count();
            $completedChapters = $allChaptersFlat->where('status', 'Done')->count();
            $inProgressChapters = $allChaptersFlat->where('status', 'In progress')->count();

            $stats = [
                'total_topics'       => $totalTopics,
                'completed'          => $completedChapters,
                'in_progress'        => $inProgressChapters,
                'weeks_remaining'    => $weeksRemaining,
                'completion_percent' => $totalTopics > 0 ? (int) round(($completedChapters / $totalTopics) * 100) : 0,
            ];

            // 8. Subject x month topic grid, still driven by scheduled periods
            //    (the only place calendar dates exist), labelled with the
            //    curriculum's chapter_name (already denormalised onto the period row).
            $periodsByPlan = $allPeriods->groupBy('lms_intelligence_lesson_plans_id');
            $plansBySubject = DB::table('lms_intelligence_lesson_plans as lp')
                ->whereIn('lp.id', $planIds)
                ->get(['id', 'standard_id', 'subject_id']);

            $monthGrid = $subjects->map(function ($subject) use ($plansBySubject, $periodsByPlan) {
                // Match on (standard_id, subject_id) - the same subject taught
                // in a different standard must not share its schedule here.
                $subjectPlanIds = $plansBySubject
                    ->where('standard_id', $subject['standard_id'])
                    ->where('subject_id', $subject['subject_id'])
                    ->pluck('id');
                $subjectPeriods = $subjectPlanIds->flatMap(fn ($planId) => $periodsByPlan->get($planId) ?? collect());

                $months = $subjectPeriods
                    ->groupBy(fn ($p) => Carbon::parse($p->scheduled_date)->format('Y-m'))
                    ->map(function ($monthPeriods, $monthKey) {
                        $completed = $monthPeriods->where('status', 'completed')->count();

                        return [
                            'month'              => Carbon::createFromFormat('Y-m', $monthKey)->format('M'),
                            'month_key'          => $monthKey,
                            'topics'             => $monthPeriods->pluck('chapter_name')->filter()->unique()->values(),
                            'completion_percent' => $monthPeriods->count() > 0
                                ? (int) round(($completed / $monthPeriods->count()) * 100)
                                : 0,
                        ];
                    })
                    ->values();

                return [
                    'subject_id'    => $subject['subject_id'],
                    'subject_name'  => $subject['subject_name'],
                    'standard_id'   => $subject['standard_id'],
                    'standard_name' => $subject['standard_name'],
                    'progress'      => $subject['progress'],
                    'months'        => $months,
                ];
            })->values();

            // 9. Upcoming lessons across all subjects (execution-only, unchanged source).
            $subjectNameByPlanId = DB::table('lms_intelligence_lesson_plans as lp')
                ->join('subject as sub', 'sub.id', '=', 'lp.subject_id')
                ->whereIn('lp.id', $planIds)
                ->pluck('sub.subject_name', 'lp.id');
            $subjectIdByPlanId = $plansBySubject->pluck('subject_id', 'id');
            $standardIdByPlanId = $plansBySubject->pluck('standard_id', 'id');
            $standardNameByPlanId = DB::table('lms_intelligence_lesson_plans as lp')
                ->join('standard as std', 'std.id', '=', 'lp.standard_id')
                ->whereIn('lp.id', $planIds)
                ->pluck('std.name', 'lp.id');

            $upcomingLessons = $allPeriods
                ->whereIn('status', ['not_started', 'in_progress'])
                ->sortBy('scheduled_date')
                ->take(10)
                ->map(function ($period) use ($subjectIdByPlanId, $subjectNameByPlanId, $standardIdByPlanId, $standardNameByPlanId) {
                    return [
                        'period_id'      => $period->id,
                        'subject_id'     => $subjectIdByPlanId->get($period->lms_intelligence_lesson_plans_id),
                        'subject_name'   => $subjectNameByPlanId->get($period->lms_intelligence_lesson_plans_id),
                        'standard_id'    => $standardIdByPlanId->get($period->lms_intelligence_lesson_plans_id),
                        'standard_name'  => $standardNameByPlanId->get($period->lms_intelligence_lesson_plans_id),
                        'topic'          => $period->chapter_name ?: $period->primary_concept_name,
                        'scheduled_date' => $period->scheduled_date,
                        'period_slot'    => $period->period_slot,
                        'status'         => $period->status,
                        'teacher_name'   => trim(preg_replace('/\s+/', ' ', $period->teacher_name ?? '')),
                    ];
                })
                ->values();

            // 10. Per-subject chapter progress detail (structural chapter list, execution status overlaid).
            $subjectProgress = $subjects->map(function ($subject) {
                $allChapters = collect($subject['units'])->flatMap(fn ($unit) => $unit['chapters']);

                $topics = $allChapters->map(fn ($chapter) => [
                    'title'      => $chapter['chapter_name'],
                    'start_date' => $chapter['start_date'],
                    'end_date'   => $chapter['end_date'],
                    'status'     => $chapter['status'],
                ])->values();

                return [
                    'subject_id'    => $subject['subject_id'],
                    'subject_name'  => $subject['subject_name'],
                    'standard_id'   => $subject['standard_id'],
                    'standard_name' => $subject['standard_name'],
                    'progress'      => $subject['progress'],
                    'topics'        => $topics,
                ];
            })->values();

            return response()->json([
                'status'  => true,
                'message' => 'Curriculum Plan Data Found',
                'data'    => [
                    'stats'            => $stats,
                    'subjects'         => $monthGrid,
                    'curriculum'       => $subjects,
                    'upcoming_lessons' => $upcomingLessons,
                    'subject_progress' => $subjectProgress,
                ],
            ], 200);
        } catch (Throwable $e) {
            Log::error('CurriculumPlanning fetch failed: ' . $e->getMessage(), [
                'filters' => $filters,
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong while fetching Curriculum Plan data',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
