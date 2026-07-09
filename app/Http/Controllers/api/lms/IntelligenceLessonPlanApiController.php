<?php

namespace App\Http\Controllers\api\lms;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class IntelligenceLessonPlanApiController extends Controller
{
    /**
     * Fetch Lesson Plan data with the
     * Lesson Plan -> Period -> Concepts hierarchy.
     *
     * POST /api/intelligence/lesson-plans
     */
    public function index(Request $request): JsonResponse
    {
        // 1. Validate incoming filter parameters.
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|integer',
            'syear'            => 'required',
            'term_id'          => 'required|integer',
            'standard_id'      => 'required|integer',
            'subject_id'       => 'required|integer',
            'division_id'      => 'required|integer',
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
            // 2. Fetch matching lesson plans (parent level).
            $lessonPlans = DB::table('lms_intelligence_lesson_plans as lp')
                ->where('lp.sub_institute_id', $filters['sub_institute_id'])
                ->where('lp.syear', $filters['syear'])
                ->where('lp.term_id', $filters['term_id'])
                ->where('lp.standard_id', $filters['standard_id'])
                ->where('lp.subject_id', $filters['subject_id'])
                ->where('lp.division_id', $filters['division_id'])
                ->orderBy('lp.id')
                ->get();

            if ($lessonPlans->isEmpty()) {
                return response()->json([
                    'status'  => false,
                    'message' => 'No Lesson Plan Data Found',
                    'data'    => [],
                ], 404);
            }

            $lessonPlanIds = $lessonPlans->pluck('id')->all();

            // 3. Fetch all periods for these lesson plans in a single query (avoids N+1).
            //    INNER JOIN ensures only periods that belong to a matched lesson plan are returned.
            $periods = DB::table('lms_lesson_plan_periods as pp')
                ->join(
                    'lms_intelligence_lesson_plans as lp',
                    'lp.id',
                    '=',
                    'pp.lms_intelligence_lesson_plans_id'
                )
                ->whereIn('pp.lms_intelligence_lesson_plans_id', $lessonPlanIds)
                ->orderBy('pp.scheduled_date')
                ->orderBy('pp.id')
                ->select('pp.*')
                ->get()
                ->groupBy('lms_intelligence_lesson_plans_id');

            $periodIds = $periods->flatten()->pluck('id')->all();

            // 4. Fetch all concepts for these periods in a single query (avoids N+1).
            $concepts = collect();
            if (!empty($periodIds)) {
                $concepts = DB::table('lms_lesson_plan_concepts as pc')
                    ->join(
                        'lms_lesson_plan_periods as pp',
                        'pp.id',
                        '=',
                        'pc.lms_lesson_plan_periods_id'
                    )
                    ->whereIn('pc.lms_lesson_plan_periods_id', $periodIds)
                    ->orderBy('pc.id')
                    ->select('pc.*')
                    ->get()
                    ->groupBy('lms_lesson_plan_periods_id');
            }

            // 5. Build the Lesson Plan -> Period -> Concepts hierarchy.
            $data = $lessonPlans->map(function ($plan) use ($periods, $concepts) {
                $planPeriods = ($periods->get($plan->id) ?? collect())
                    ->map(function ($period) use ($concepts) {
                        $period->concepts = ($concepts->get($period->id) ?? collect())->values();
                        return $period;
                    })
                    ->values();

                return [
                    'lesson_plan' => $plan,
                    'periods'     => $planPeriods,
                ];
            })->values();

            return response()->json([
                'status'  => true,
                'message' => 'Lesson Plan Data Found',
                'data'    => $data,
            ], 200);
        } catch (Throwable $e) {
            Log::error('IntelligenceLessonPlan fetch failed: ' . $e->getMessage(), [
                'filters' => $filters,
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong while fetching Lesson Plan data',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
