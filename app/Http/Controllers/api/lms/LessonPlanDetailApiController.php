<?php

namespace App\Http\Controllers\api\lms;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class LessonPlanDetailApiController extends Controller
{
    /**
     * Periods (+ concepts) scheduled within a date range for a standard/division,
     * for the single-lesson detail page (lesson list + selected lesson detail).
     *
     * GET|POST /api/intelligence/lesson-plan-detail
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|integer',
            'syear'            => 'required',
            'standard_id'      => 'required|integer',
            'division_id'      => 'nullable|integer',
            'subject_id'       => 'nullable|integer',
            'date_from'        => 'required|date',
            'date_to'          => 'required|date|after_or_equal:date_from',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $filters = $validator->validated();

        // sub_institute_id 1 only has lesson-plan data seeded for syear 2026 -
        // force it for that institute so the session's normal "active academic
        // year" (which may resolve to a different year) does not silently
        // return "no data" for this institute alone.
        if ((int) $filters['sub_institute_id'] === 1) {
            $filters['syear'] = 2026;
        }

        try {
            $planIds = DB::table('lms_intelligence_lesson_plans as lp')
                ->where('lp.sub_institute_id', $filters['sub_institute_id'])
                ->where('lp.syear', $filters['syear'])
                ->where('lp.standard_id', $filters['standard_id'])
                ->when(!empty($filters['division_id']), fn ($q) => $q->where('lp.division_id', $filters['division_id']))
                ->when(!empty($filters['subject_id']), fn ($q) => $q->where('lp.subject_id', $filters['subject_id']))
                ->pluck('lp.id');

            if ($planIds->isEmpty()) {
                return response()->json([
                    'status'  => false,
                    'message' => 'No Lesson Plan Data Found',
                    'data'    => [],
                ], 404);
            }

            $periods = DB::table('lms_lesson_plan_periods as pp')
                ->join('lms_intelligence_lesson_plans as lp', 'lp.id', '=', 'pp.lms_intelligence_lesson_plans_id')
                ->join('subject as sub', 'sub.id', '=', 'lp.subject_id')
                ->join('tbluser as tu', 'tu.id', '=', 'pp.teacher_id')
                ->whereIn('pp.lms_intelligence_lesson_plans_id', $planIds)
                ->whereBetween('pp.scheduled_date', [$filters['date_from'], $filters['date_to']])
                ->orderBy('pp.scheduled_date')
                ->orderBy('pp.period_slot')
                ->select(
                    'pp.*',
                    'lp.subject_id',
                    'sub.subject_name',
                    DB::raw('concat(tu.first_name," ",tu.middle_name," ",tu.last_name) as teacher_name')
                )
                ->get();

            if ($periods->isEmpty()) {
                return response()->json([
                    'status'  => false,
                    'message' => 'No Lesson Plan Data Found',
                    'data'    => [],
                ], 404);
            }

            $periodIds = $periods->pluck('id')->all();

            $concepts = DB::table('lms_lesson_plan_concepts as pc')
                ->whereIn('pc.lms_lesson_plan_periods_id', $periodIds)
                ->orderBy('pc.id')
                ->get()
                ->groupBy('lms_lesson_plan_periods_id');

            $data = $periods->map(function ($period) use ($concepts) {
                return [
                    'period_id'            => $period->id,
                    'subject_id'           => $period->subject_id,
                    'subject_name'         => $period->subject_name,
                    'scheduled_date'       => $period->scheduled_date,
                    'period_slot'          => $period->period_slot,
                    'teacher_name'         => trim(preg_replace('/\s+/', ' ', $period->teacher_name ?? '')),
                    'chapter_name'         => $period->chapter_name,
                    'primary_concept_name' => $period->primary_concept_name,
                    'period_type'          => $period->period_type,
                    'plan_json'            => $period->plan_json ? json_decode($period->plan_json, true) : null,
                    'learning_objectives'  => $period->learning_objectives ? json_decode($period->learning_objectives, true) : [],
                    'planned_duration_min' => $period->planned_duration_min,
                    'status'               => $period->status,
                    'completion_percent'   => $period->completion_percent,
                    'teacher_notes'        => $period->teacher_notes,
                    'concepts'             => ($concepts->get($period->id) ?? collect())->values(),
                ];
            })->values();

            return response()->json([
                'status'  => true,
                'message' => 'Lesson Plan Data Found',
                'data'    => $data,
            ], 200);
        } catch (Throwable $e) {
            Log::error('LessonPlanDetail fetch failed: ' . $e->getMessage(), [
                'filters' => $filters,
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong while fetching Lesson Plan detail data',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
