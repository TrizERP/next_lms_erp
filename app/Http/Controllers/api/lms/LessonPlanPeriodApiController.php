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

class LessonPlanPeriodApiController extends Controller
{
    private const WEEKDAY_LETTER = ['M', 'T', 'W', 'H', 'F', 'S', 'U'];

    /**
     * Create a scheduled lesson period. Finds (or creates) the parent
     * lms_intelligence_lesson_plans row for this sub_institute/syear/standard/
     * subject/division, then inserts the period under it.
     *
     * POST /api/intelligence/lesson-plan-periods
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sub_institute_id'     => 'required|integer',
            'syear'                => 'required|integer',
            'standard_id'          => 'required|integer',
            'division_id'          => 'required|integer',
            'subject_id'           => 'required|integer',
            'teacher_id'           => 'required|integer',
            'scheduled_date'       => 'required|date',
            'period_id'            => 'required|integer',
            'period_slot'          => 'required|string|max:10',
            'chapter_id'           => 'nullable|integer',
            'chapter_name'         => 'nullable|string|max:255',
            // Optional so the callers that predate term-scoped plans keep working.
            // When sent it both scopes the plan lookup and lands on a newly created
            // plan, so the calendar - which reads with the session's term_id - finds
            // what was just written instead of silently missing it.
            'term_id'              => 'nullable|integer',
            // 'chapter_id'           => 'nullable|integer',
            // 'chapter_name'         => 'nullable|string|max:255',
            'primary_concept_id'   => 'nullable|integer',
            'primary_concept_name' => 'nullable|string|max:255',
            'pedagogy_method'      => 'nullable|string|max:100',
            'learning_objectives'  => 'nullable|string',
            'period_type'          => 'nullable|in:teaching,assessment,revision,activity,lab,project,buffer',
            'teacher_notes'        => 'nullable|string',
            'planned_duration_min' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        // sub_institute_id 1 only has curriculum/lesson-plan data seeded for
        // syear 2026 - force it for that institute so the session's normal
        // "active academic year" (which may resolve to a different year)
        // does not create a lesson under the wrong, dataless year.
        if ((int) $data['sub_institute_id'] === 1) {
            $data['syear'] = 2026;
        }

        try {
            $plan = DB::table('lms_intelligence_lesson_plans')
                ->where('sub_institute_id', $data['sub_institute_id'])
                ->where('syear', $data['syear'])
                ->where('standard_id', $data['standard_id'])
                ->where('subject_id', $data['subject_id'])
                ->where('division_id', $data['division_id'])
                // Scope to the term when the caller sends one, so the lesson
                // lands on the same plan the calendar reads back.
                ->when(!empty($data['term_id']), fn ($q) => $q->where('term_id', $data['term_id']))
                ->first();

            if ($plan) {
                $planId = $plan->id;
                $termStartDate = $plan->term_start_date;
            } else {
                $subjectName = DB::table('subject')->where('id', $data['subject_id'])->value('subject_name') ?? 'Subject';
                $termStartDate = Carbon::create((int) $data['syear'], 1, 1)->toDateString();
                $termEndDate = Carbon::create((int) $data['syear'], 12, 31)->toDateString();

                $planId = DB::table('lms_intelligence_lesson_plans')->insertGetId([
                    'sub_institute_id'    => $data['sub_institute_id'],
                    'syear'               => $data['syear'],
                    'term_id'             => $data['term_id'] ?? 1,
                    'standard_id'         => $data['standard_id'],
                    'subject_id'          => $data['subject_id'],
                    'division_id'         => $data['division_id'],
                    'plan_title'          => $subjectName . ' - ' . $data['syear'],
                    'term_start_date'     => $termStartDate,
                    'term_end_date'       => $termEndDate,
                    'total_teaching_days' => 0,
                    'total_periods'       => 0,
                    'periods_per_week'    => 0,
                    'period_duration_min' => $data['planned_duration_min'] ?? 40,
                    'total_teaching_min'  => 0,
                    'total_required_min'  => 0,
                    'buffer_percent'      => 0,
                    'holidays_count'      => 0,
                    'exam_days_count'     => 0,
                    'generation_status'   => 'completed',
                    'generation_progress' => 100,
                    'generated_at'        => now(),
                    'generated_by'        => 'manual-entry',
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);
            }

            $chapterName = $data['chapter_name'] ?? null;
            if (!empty($data['chapter_id'])) {
                $chapterName = DB::table('chapter_master')->where('id', $data['chapter_id'])->value('chapter_name') ?? $chapterName;
            }

            $conceptId = !empty($data['primary_concept_id']) ? (int) $data['primary_concept_id'] : null;
            $conceptName = $data['primary_concept_name'] ?? null;

            // learning_objectives is a json column. The dialog collects one free-text
            // block, so it is stored as a single-element list to keep the column's
            // shape the same as the generated plans write it.
            $objectives = null;
            if (!empty($data['learning_objectives'])) {
                $objectives = json_encode([trim($data['learning_objectives'])]);
            }

            $scheduledDate = Carbon::parse($data['scheduled_date']);
            $weekDay = self::WEEKDAY_LETTER[$scheduledDate->dayOfWeekIso - 1];
            $weekNumber = intdiv(Carbon::parse($termStartDate)->diffInDays($scheduledDate), 7) + 1;

            $periodId = DB::table('lms_lesson_plan_periods')->insertGetId([
                'lms_intelligence_lesson_plans_id' => $planId,
                // Denormalised from the plan so institute-scoped reads can filter
                // this row directly instead of joining back up to the plan.
                'sub_institute_id'     => $data['sub_institute_id'],
                'scheduled_date'       => $scheduledDate->toDateString(),
                'week_day'             => $weekDay,
                'week_number'          => max(1, $weekNumber),
                'period_id'            => $data['period_id'],
                'period_slot'          => $data['period_slot'],
                'teacher_id'           => $data['teacher_id'],
                'chapter_id'           => $data['chapter_id'] ?? null,
                'chapter_name'         => $chapterName,
                'primary_concept_id'   => $conceptId,
                'primary_concept_name' => $conceptName,
                'period_type'          => $data['period_type'] ?? 'teaching',
                'pedagogy_method'      => $data['pedagogy_method'] ?? null,
                'learning_objectives'  => $objectives,
                'planned_duration_min' => $data['planned_duration_min'] ?? 40,
                'status'               => 'not_started',
                'teacher_notes'        => $data['teacher_notes'] ?? null,
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);

            // The calendar reads a lesson's coverage from this table, so a lesson
            // created for a concept needs the link row here and not just the
            // denormalised name on the period.
            if ($conceptId && $conceptName) {
                DB::table('lms_lesson_plan_concepts')->insert([
                    'lms_lesson_plan_periods_id' => $periodId,
                    'sub_institute_id'           => $data['sub_institute_id'],
                    'concept_id'                 => $conceptId,
                    'concept_name'               => $conceptName,
                    'is_primary'                 => 1,
                    'coverage_percent'           => 100,
                    'created_at'                 => now(),
                ]);
            }

            $period = DB::table('lms_lesson_plan_periods')->where('id', $periodId)->first();

            return response()->json([
                'status'  => true,
                'message' => 'Lesson scheduled',
                'data'    => $period,
            ], 201);
        } catch (Throwable $e) {
            Log::error('LessonPlanPeriod store failed: ' . $e->getMessage(), ['data' => $data]);

            return response()->json(['status' => false, 'message' => 'Something went wrong while scheduling the lesson', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update a scheduled lesson period's date/period/teacher/chapter/status/notes.
     *
     * POST /api/intelligence/lesson-plan-periods/{id}/update
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sub_institute_id'     => 'required|integer',
            'scheduled_date'       => 'nullable|date',
            'period_id'            => 'nullable|integer',
            'period_slot'          => 'nullable|string|max:10',
            'teacher_id'           => 'nullable|integer',
            'chapter_id'           => 'nullable|integer',
            'chapter_name'         => 'nullable|string|max:255',
            'status'               => 'nullable|in:not_started,in_progress,completed,skipped,rescheduled',
            'teacher_notes'        => 'nullable|string',
            'planned_duration_min' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        try {
            $period = DB::table('lms_lesson_plan_periods as pp')
                ->join('lms_intelligence_lesson_plans as lp', 'lp.id', '=', 'pp.lms_intelligence_lesson_plans_id')
                ->where('pp.id', $id)
                ->where('lp.sub_institute_id', $data['sub_institute_id'])
                ->select('pp.*', 'lp.term_start_date')
                ->first();

            if (!$period) {
                return response()->json(['status' => false, 'message' => 'Lesson not found', 'data' => []], 404);
            }

            $update = ['updated_at' => now()];

            if (!empty($data['scheduled_date'])) {
                $scheduledDate = Carbon::parse($data['scheduled_date']);
                $update['scheduled_date'] = $scheduledDate->toDateString();
                $update['week_day'] = self::WEEKDAY_LETTER[$scheduledDate->dayOfWeekIso - 1];
                $update['week_number'] = max(1, intdiv(Carbon::parse($period->term_start_date)->diffInDays($scheduledDate), 7) + 1);
            }
            if (!empty($data['period_id'])) $update['period_id'] = $data['period_id'];
            if (!empty($data['period_slot'])) $update['period_slot'] = $data['period_slot'];
            if (!empty($data['teacher_id'])) $update['teacher_id'] = $data['teacher_id'];
            if (array_key_exists('chapter_id', $data) && $data['chapter_id']) {
                $update['chapter_id'] = $data['chapter_id'];
                $update['chapter_name'] = DB::table('chapter_master')->where('id', $data['chapter_id'])->value('chapter_name')
                    ?? ($data['chapter_name'] ?? $period->chapter_name);
            } elseif (array_key_exists('chapter_name', $data) && $data['chapter_name']) {
                $update['chapter_name'] = $data['chapter_name'];
            }
            if (array_key_exists('teacher_notes', $data)) $update['teacher_notes'] = $data['teacher_notes'];
            if (!empty($data['planned_duration_min'])) $update['planned_duration_min'] = $data['planned_duration_min'];

            if (!empty($data['status'])) {
                $update['status'] = $data['status'];
                if ($data['status'] === 'completed') {
                    $update['completion_percent'] = 100;
                    $update['completed_at'] = now();
                } elseif ($data['status'] === 'in_progress') {
                    $update['completion_percent'] = 50;
                    $update['completed_at'] = null;
                } else {
                    $update['completion_percent'] = null;
                    $update['completed_at'] = null;
                }
            }

            DB::table('lms_lesson_plan_periods')->where('id', $id)->update($update);
            $updated = DB::table('lms_lesson_plan_periods')->where('id', $id)->first();

            return response()->json([
                'status'  => true,
                'message' => 'Lesson updated',
                'data'    => $updated,
            ], 200);
        } catch (Throwable $e) {
            Log::error('LessonPlanPeriod update failed: ' . $e->getMessage(), ['id' => $id, 'data' => $data]);

            return response()->json(['status' => false, 'message' => 'Something went wrong while updating the lesson', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete a scheduled lesson period (and its concept links).
     *
     * POST /api/intelligence/lesson-plan-periods/{id}/delete
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $subInstituteId = (int) $request->input('sub_institute_id');

        try {
            $period = DB::table('lms_lesson_plan_periods as pp')
                ->join('lms_intelligence_lesson_plans as lp', 'lp.id', '=', 'pp.lms_intelligence_lesson_plans_id')
                ->where('pp.id', $id)
                ->where('lp.sub_institute_id', $subInstituteId)
                ->select('pp.id')
                ->first();

            if (!$period) {
                return response()->json(['status' => false, 'message' => 'Lesson not found', 'data' => []], 404);
            }

            DB::table('lms_lesson_plan_concepts')->where('lms_lesson_plan_periods_id', $id)->delete();
            DB::table('lms_lesson_plan_periods')->where('id', $id)->delete();

            return response()->json([
                'status'  => true,
                'message' => 'Lesson deleted',
                'data'    => [],
            ], 200);
        } catch (Throwable $e) {
            Log::error('LessonPlanPeriod destroy failed: ' . $e->getMessage(), ['id' => $id]);

            return response()->json(['status' => false, 'message' => 'Something went wrong while deleting the lesson', 'error' => $e->getMessage()], 500);
        }
    }
}
