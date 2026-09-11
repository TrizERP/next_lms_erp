<?php

namespace App\Http\Controllers\api\lms;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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

            // Chapters that exist but hang off no curriculum unit. Two shapes of
            // orphan land here: a chapter under a subject that does have a
            // curriculum but was never assigned to one of its units, and every
            // chapter of an institute that has no lms_curriculum row at all.
            // Both are real curriculum content, so they are surfaced rather than
            // silently dropped for want of a parent.
            $unmappedChapters = $this->unmappedChapters($filters);

            // 404 only when there is genuinely nothing to show. An institute
            // whose chapters have been extracted but never organised into a
            // curriculum still has something to render.
            if ($curricula->isEmpty() && $unmappedChapters->isEmpty()) {
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
                    ->orderBy('id')
                    ->get([
                        'id', 'chapter_id', 'name', 'description',
                        'estimated_minutes', 'topic_sort_order', 'topic_show_hide',
                    ])
                    ->groupBy('chapter_id');

                // Kept for estates that do attach outcomes to a chapter. On this
                // estate every row carries chapter_id 0 and is reached by
                // curriculum_id instead (see $outcomesByCurriculum below), so
                // this legitimately comes back empty here.
                $outcomesByChapter = DB::table('lms_learning_outcomes')
                    ->whereIn('chapter_id', $chapterIds)
                    ->whereNotNull('parent_id')
                    ->whereNotNull('code')
                    ->where('code', '<>', '')
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
                    ->get(['chapter_id', 'learning_objective', 'total_concepts'])
                    ->keyBy('chapter_id');
            }

            // 4b. Goals and competencies. These hang off the curriculum, not the
            //     chapter: the NCF states a curricular goal (CG-n) once for the
            //     whole subject and its competencies (C-n.m) beneath it, and the
            //     rows carry curriculum_id with chapter_id left at 0. Guarded by
            //     hasColumn because the table was originally created chapter-only
            //     and estates that never took the curriculum_id column must not
            //     fail the whole request.
            $outcomesByCurriculum = collect();
            if (!empty($curriculumIds) && Schema::hasColumn('lms_learning_outcomes', 'curriculum_id')) {
                $outcomesByCurriculum = DB::table('lms_learning_outcomes')
                    ->whereIn('curriculum_id', $curriculumIds)
                    ->orderBy('code')
                    ->get(['id', 'curriculum_id', 'parent_id', 'code', 'type', 'description'])
                    ->groupBy('curriculum_id');
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

            $subjects = $curricula->map(function ($curriculum) use ($units, $chapters, $topicsByChapter, $outcomesByChapter, $outcomesByCurriculum, $conceptCountByChapter, $semanticByChapter, $chapterStatus) {
                $curriculumUnits = ($units->get($curriculum->id) ?? collect())
                    ->map(function ($unit) use ($chapters, $topicsByChapter, $outcomesByChapter, $conceptCountByChapter, $semanticByChapter, $chapterStatus) {
                        $unitChapters = ($chapters->get($unit->id) ?? collect())
                            ->map(function ($chapter) use ($topicsByChapter, $outcomesByChapter, $conceptCountByChapter, $semanticByChapter, $chapterStatus) {
                                $execution = $chapterStatus($chapter->id);
                                $semantic = $semanticByChapter->get($chapter->id);
                                $chapterTopics = ($topicsByChapter->get($chapter->id) ?? collect())->values();

                                return [
                                    'chapter_id'         => $chapter->id,
                                    'chapter_name'       => $chapter->chapter_name,
                                    'chapter_desc'       => $this->blankToNull($chapter->chapter_desc ?? null),
                                    'sort_order'         => $chapter->sort_order,
                                    'availability'       => $chapter->availability,
                                    'show_hide'          => $chapter->show_hide,
                                    // Feeds the lazy intelligence drawer: the
                                    // existing /api/semantic-intelligence/{id}/result
                                    // is keyed by extraction, not by chapter.
                                    'extraction_id'      => $chapter->extraction_id,
                                    'topics'             => $chapterTopics,
                                    'topic_count'        => $chapterTopics->count(),
                                    'learning_outcomes'  => ($outcomesByChapter->get($chapter->id) ?? collect())->values(),
                                    'concept_count'      => (int) ($conceptCountByChapter->get($chapter->id) ?? 0),
                                    // The key_concepts blob itself stays out of this
                                    // payload (~231KB across the roll-up); only its
                                    // size travels, and the list comes from chapter().
                                    'key_concept_count'  => count($this->decodeJsonArray($chapter->key_concepts ?? null)),
                                    'learning_objective' => $semantic->learning_objective ?? null,
                                    'total_concepts'     => isset($semantic->total_concepts) ? (int) $semantic->total_concepts : null,
                                    // blooms_level is deliberately absent: it reads
                                    // like a scalar but holds a per-concept JSON
                                    // blob, and emitting it here added 285KB to a
                                    // single standard's roll-up. It ships from
                                    // chapter() instead, under `semantic`.
                                    'has_intelligence'   => $semantic !== null,
                                    'status'             => $execution['status'],
                                    'total_periods'      => $execution['total_periods'],
                                    'completed_periods'  => $execution['completed_periods'],
                                    'start_date'         => $execution['start_date'],
                                    'end_date'           => $execution['end_date'],
                                ];
                            })
                            ->values();

                        // What the syllabus says this unit contains, which is not
                        // the same as what has been extracted. The two lists are
                        // returned side by side rather than reconciled: the names
                        // diverge ("Circles" vs "I'm Up and Down, and Round and
                        // Round"), so any name matching here would invent links
                        // that do not exist. chapter_master.unit_id is the only
                        // authoritative join.
                        $declaredChapters = $this->decodeJsonArray($unit->unit_chapters ?? null);

                        return [
                            'unit_id'                 => $unit->id,
                            'unit_number'             => $unit->unit_number,
                            'unit_name'               => $unit->name,
                            'total_marks'             => $unit->total_marks,
                            'planned_periods'         => $unit->planned_periods,
                            'extraction_id'           => $unit->extraction_id,
                            'declared_chapters'       => $declaredChapters,
                            'declared_chapter_count'  => count($declaredChapters),
                            'extracted_chapter_count' => $unitChapters->count(),
                            'chapters'                => $unitChapters,
                        ];
                    })
                    ->values();

                $allChapters = $curriculumUnits->flatMap(fn ($unit) => $unit['chapters']);
                $totalChapters = $allChapters->count();
                $doneChapters = $allChapters->where('status', 'Done')->count();

                return [
                    'subject_id'      => $curriculum->subject_id,
                    'subject_name'    => $curriculum->subject_name,
                    'standard_id'     => $curriculum->standard_id,
                    'standard_name'   => $curriculum->standard_name,
                    'curriculum_id'   => $curriculum->id,
                    'curriculum_name' => $curriculum->curriculum_name,
                    'board'           => $curriculum->board,
                    'framework'       => $curriculum->framework,
                    'grade_id'        => $curriculum->grade_id,
                    'syear'           => $curriculum->syear,
                    'status'          => $curriculum->status,
                    'extraction_id'   => $curriculum->extraction_id,
                    'total_marks'     => $curriculum->total_marks,
                    'internal_marks'  => $curriculum->internal_marks,
                    // The seven authoring fields. Every one of them is an empty
                    // string on this estate, so they are normalised to null: the
                    // UI has to be able to tell "nobody has written this yet"
                    // from "written, and deliberately blank".
                    'details'         => [
                        'curriculum_alignment' => $this->blankToNull($curriculum->curriculum_alignment ?? null),
                        'holistic_curriculum'  => $this->blankToNull($curriculum->holistic_curriculum ?? null),
                        'model_integration'    => $this->blankToNull($curriculum->model_integration ?? null),
                        'objective'            => $this->blankToNull($curriculum->objective ?? null),
                        'chapter'              => $this->blankToNull($curriculum->chapter ?? null),
                        'outcome'              => $this->blankToNull($curriculum->outcome ?? null),
                        'assessment_tool'      => $this->blankToNull($curriculum->assessment_tool ?? null),
                    ],
                    'outcomes'        => $this->outcomeTree($outcomesByCurriculum->get($curriculum->id) ?? collect()),
                    'coverage'        => [
                        'declared_chapters'         => $curriculumUnits->sum('declared_chapter_count'),
                        'extracted_chapters'        => $totalChapters,
                        'chapters_with_intelligence' => $allChapters->where('has_intelligence', true)->count(),
                    ],
                    'progress'        => $totalChapters > 0 ? (int) round(($doneChapters / $totalChapters) * 100) : 0,
                    'units'           => $curriculumUnits,
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
                    'stats'             => $stats,
                    'subjects'          => $monthGrid,
                    'curriculum'        => $subjects,
                    'upcoming_lessons'  => $upcomingLessons,
                    'subject_progress'  => $subjectProgress,
                    'unmapped_chapters' => $unmappedChapters,
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

    /**
     * Everything about one chapter that is too heavy to travel in the roll-up.
     *
     * The list endpoint above carries counts only: inlining the concept
     * descriptions and the key_concepts blobs for every chapter of every
     * standard costs roughly half a megabyte, nearly all of it for chapters
     * the user never opens. This serves the same data one chapter at a time,
     * when a chapter is actually expanded.
     *
     * The per-concept AI intelligence is deliberately NOT here — that already
     * has a home at GET /api/semantic-intelligence/{extraction_id}/result, and
     * `extraction_id` (returned both here and in the roll-up) is the key to it.
     *
     * GET|POST /api/intelligence/curriculum-planning/chapter
     */
    public function chapter(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|integer',
            'chapter_id'       => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $filters = $validator->validated();

        try {
            // Scoped by institute as well as id. This route sits outside the
            // session middleware like its sibling, so without the ownership
            // check a bare chapter id would read across tenants.
            $chapter = DB::table('chapter_master')
                ->where('id', $filters['chapter_id'])
                ->where('sub_institute_id', $filters['sub_institute_id'])
                ->first();

            if ($chapter === null) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Chapter not found',
                    'data'    => null,
                ], 404);
            }

            $topics = DB::table('topic_master')
                ->where('chapter_id', $chapter->id)
                ->orderBy('topic_sort_order')
                ->orderBy('id')
                ->get([
                    'id as topic_id', 'name', 'description',
                    'estimated_minutes', 'topic_sort_order', 'topic_show_hide',
                ]);

            $concepts = DB::table('lms_concept')
                ->where('chapter_id', $chapter->id)
                ->orderBy('id')
                ->get([
                    'id as concept_id', 'topic_id', 'name', 'description',
                    'mastery_threshold', 'learning_pattern', 'estimated_mastery_minutes',
                ]);

            $semantic = DB::table('semantic_intelligence')
                ->where('chapter_id', $chapter->id)
                ->first(['id', 'extraction_id', 'learning_objective', 'total_concepts', 'blooms_level']);

            // Provenance: which document this chapter was extracted from, so a
            // teacher can see whether the source is the NCERT book or something
            // the school supplied itself.
            $source = $chapter->extraction_id
                ? DB::table('document_extractions')
                    ->where('id', $chapter->extraction_id)
                    ->first(['id', 'document_type', 'document_tittle', 'chapter_number', 'board', 'page_count', 'pdf_url'])
                : null;

            $learningOutcomes = DB::table('lms_learning_outcomes')
                ->where('chapter_id', $chapter->id)
                ->whereNotNull('parent_id')
                ->whereNotNull('code')
                ->where('code', '<>', '')
                ->orderBy('code')
                ->get(['id', 'chapter_id', 'code', 'type', 'description']);

            return response()->json([
                'status'  => true,
                'message' => 'Chapter detail found',
                'data'    => [
                    'chapter_id'        => (int) $chapter->id,
                    'chapter_name'      => $chapter->chapter_name,
                    'chapter_desc'      => $this->blankToNull($chapter->chapter_desc ?? null),
                    'sort_order'        => $chapter->sort_order,
                    'availability'      => $chapter->availability,
                    'show_hide'         => $chapter->show_hide,
                    'unit_id'           => $chapter->unit_id,
                    'extraction_id'     => $chapter->extraction_id,
                    'topics'            => $topics,
                    'concepts'          => $concepts,
                    'learning_outcomes' => $learningOutcomes,
                    'key_concepts'      => $this->decodeJsonArray($chapter->key_concepts ?? null),
                    'semantic'          => $semantic,
                    'source'            => $source,
                ],
            ], 200);
        } catch (Throwable $e) {
            Log::error('CurriculumPlanning chapter fetch failed: ' . $e->getMessage(), [
                'filters' => $filters,
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong while fetching chapter detail',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Chapters this institute holds that no curriculum unit claims.
     *
     * Grouped by standard + subject rather than folded into the curriculum
     * tree, because that is exactly what they are not part of. Two populations
     * end up here and both are legitimate: a chapter whose subject does have a
     * curriculum but which was never assigned to a unit, and every chapter of
     * an institute that has no lms_curriculum row at all.
     */
    private function unmappedChapters(array $filters)
    {
        $rows = DB::table('chapter_master as cm')
            ->leftJoin('subject as sub', 'sub.id', '=', 'cm.subject_id')
            ->leftJoin('standard as std', 'std.id', '=', 'cm.standard_id')
            ->where('cm.sub_institute_id', $filters['sub_institute_id'])
            ->where('cm.syear', $filters['syear'])
            ->whereNull('cm.unit_id')
            ->when(!empty($filters['standard_id']), fn ($q) => $q->where('cm.standard_id', $filters['standard_id']))
            ->orderBy('std.sort_order')
            ->orderBy('sub.subject_name')
            ->orderBy('cm.sort_order')
            ->select('cm.*', 'sub.subject_name', 'std.name as standard_name')
            ->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        $chapterIds = $rows->pluck('id')->all();

        $conceptCounts = DB::table('lms_concept')
            ->whereIn('chapter_id', $chapterIds)
            ->selectRaw('chapter_id, count(*) as total')
            ->groupBy('chapter_id')
            ->pluck('total', 'chapter_id');

        $topicCounts = DB::table('topic_master')
            ->whereIn('chapter_id', $chapterIds)
            ->selectRaw('chapter_id, count(*) as total')
            ->groupBy('chapter_id')
            ->pluck('total', 'chapter_id');

        $semanticByChapter = DB::table('semantic_intelligence')
            ->whereIn('chapter_id', $chapterIds)
            ->get(['chapter_id', 'learning_objective', 'total_concepts'])
            ->keyBy('chapter_id');

        return $rows
            // Keyed on both ids: the same subject taught in two standards must
            // not be merged, exactly as in the curriculum roll-up above.
            ->groupBy(fn ($row) => $row->standard_id . ':' . $row->subject_id)
            ->map(function ($group) use ($conceptCounts, $topicCounts, $semanticByChapter) {
                $first = $group->first();

                $chapters = $group->map(function ($row) use ($conceptCounts, $topicCounts, $semanticByChapter) {
                    $semantic = $semanticByChapter->get($row->id);

                    return [
                        'chapter_id'         => (int) $row->id,
                        'chapter_name'       => $row->chapter_name,
                        'chapter_desc'       => $this->blankToNull($row->chapter_desc ?? null),
                        'sort_order'         => $row->sort_order,
                        'extraction_id'      => $row->extraction_id,
                        'topic_count'        => (int) ($topicCounts->get($row->id) ?? 0),
                        'concept_count'      => (int) ($conceptCounts->get($row->id) ?? 0),
                        'key_concept_count'  => count($this->decodeJsonArray($row->key_concepts ?? null)),
                        'learning_objective' => $semantic->learning_objective ?? null,
                        'total_concepts'     => isset($semantic->total_concepts) ? (int) $semantic->total_concepts : null,
                        'has_intelligence'   => $semantic !== null,
                    ];
                })->values();

                return [
                    'standard_id'                => $first->standard_id,
                    'standard_name'              => $first->standard_name,
                    'subject_id'                 => $first->subject_id,
                    'subject_name'               => $first->subject_name,
                    'chapter_count'              => $chapters->count(),
                    'concept_count'              => $chapters->sum('concept_count'),
                    'chapters_with_intelligence' => $chapters->where('has_intelligence', true)->count(),
                    'chapters'                   => $chapters,
                ];
            })
            ->values();
    }

    /**
     * Nest the flat outcome rows into goals with their competencies beneath.
     *
     * Parenthood is read from `parent_id`, never from `type`: the enum stores
     * an empty string for every goal row on this estate, so `type = 'goal'`
     * matches nothing and would silently flatten the tree.
     */
    private function outcomeTree($rows)
    {
        $rows = collect($rows);

        $children = $rows->filter(fn ($row) => !empty($row->parent_id))->groupBy('parent_id');

        $goals = $rows
            ->filter(fn ($row) => empty($row->parent_id))
            ->map(fn ($goal) => [
                'id'           => (int) $goal->id,
                'code'         => $goal->code,
                'type'         => $this->blankToNull($goal->type ?? null) ?? 'goal',
                'description'  => $goal->description,
                'competencies' => ($children->get($goal->id) ?? collect())
                    ->map(fn ($child) => [
                        'id'          => (int) $child->id,
                        'code'        => $child->code,
                        'type'        => $this->blankToNull($child->type ?? null) ?? 'competency',
                        'description' => $child->description,
                    ])
                    ->values(),
            ])
            ->values();

        // A competency whose goal is missing would otherwise vanish. Keep it
        // visible under a null-coded parent rather than lose curriculum data.
        $orphans = $children
            ->reject(fn ($group, $parentId) => $rows->contains(fn ($row) => (int) $row->id === (int) $parentId))
            ->flatten(1);

        if ($orphans->isNotEmpty()) {
            $goals->push([
                'id'           => null,
                'code'         => null,
                'type'         => 'goal',
                'description'  => 'Competencies with no parent goal recorded',
                'competencies' => $orphans->map(fn ($child) => [
                    'id'          => (int) $child->id,
                    'code'        => $child->code,
                    'type'        => $this->blankToNull($child->type ?? null) ?? 'competency',
                    'description' => $child->description,
                ])->values(),
            ]);
        }

        return $goals;
    }

    /**
     * Decode a JSON array column, tolerating null, '' and malformed content.
     * Always an array, so callers can count() it without guarding.
     */
    private function decodeJsonArray($value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Empty and whitespace-only strings become null.
     *
     * Every authoring column on lms_curriculum holds '' rather than NULL, and
     * the difference between "never written" and "written blank" is the whole
     * point of showing these fields, so the UI is given one unambiguous value.
     */
    private function blankToNull($value)
    {
        if ($value === null) {
            return null;
        }

        return trim((string) $value) === '' ? null : $value;
    }
}
