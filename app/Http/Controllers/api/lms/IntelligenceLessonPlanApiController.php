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

        // sub_institute_id 1 only has lesson-plan data seeded for syear 2026 -
        // force it for that institute so the session's normal "active academic
        // year" (which may resolve to a different year) does not silently
        // return "no data" for this institute alone.
        //
        // This endpoint feeds the master calendar and the concept-wise view, and
        // was the only one of the five lesson-plan controllers missing this: the
        // create endpoint forces 2026 on write, so a lesson saved successfully
        // was then invisible to a calendar reading the session's real year.
        if ((int) $filters['sub_institute_id'] === 1) {
            $filters['syear'] = 2026;
        }

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
                    'message' => $this->describeMissingPlan($filters),
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
                ->join('tbluser as tu', 'tu.id', '=', 'pp.teacher_id')
                ->whereIn('pp.lms_intelligence_lesson_plans_id', $lessonPlanIds)
                ->orderBy('pp.scheduled_date')
                ->orderBy('pp.id')
                ->select('pp.*', DB::raw('concat(tu.first_name," ",tu.middle_name," ",tu.last_name) as teacher_name'))
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

                        // Decode the generated lesson content so the client gets
                        // objects rather than JSON strings it would have to parse
                        // itself. Both columns are written by the micro planner
                        // and are null until a lesson has been generated.
                        foreach (['plan_json', 'learning_objectives'] as $column) {
                            if (is_string($period->{$column} ?? null) && $period->{$column} !== '') {
                                $decoded = json_decode($period->{$column}, true);
                                if (json_last_error() === JSON_ERROR_NONE) {
                                    $period->{$column} = $decoded;
                                }
                            }
                        }

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

    /**
     * Explain an empty calendar in terms of what the institute actually has.
     *
     * A plan is keyed by institute + year + term + standard + subject + division;
     * a bare "No Lesson Plan Data Found" leaves the user guessing which of the six
     * is wrong, so widen the search one axis at a time and name the first thing
     * that would work.
     */
    private function describeMissingPlan(array $filters): string
    {
        $scoped = DB::table('lms_intelligence_lesson_plans')
            ->where('sub_institute_id', $filters['sub_institute_id'])
            ->where('standard_id', $filters['standard_id'])
            ->where('subject_id', $filters['subject_id']);

        // Same class and subject, different term or division within this year?
        $sameYear = (clone $scoped)->where('syear', $filters['syear'])->get(['term_id', 'division_id']);

        if ($sameYear->isNotEmpty()) {
            $terms = $sameYear->pluck('term_id')->unique()->sort()->values();
            $divisions = $sameYear->pluck('division_id')->unique()->sort()->values();

            if (!$terms->contains($filters['term_id'])) {
                return 'No lesson plan for the selected term. This subject has plans for term '
                    . $terms->implode(', ') . ' in ' . $filters['syear'] . '.';
            }

            return 'No lesson plan for the selected division. This subject has plans for division '
                . $divisions->implode(', ') . ' in ' . $filters['syear'] . '.';
        }

        // Any year at all?
        $years = (clone $scoped)->distinct()->orderByDesc('syear')->pluck('syear');

        if ($years->isNotEmpty()) {
            return 'No lesson plan for ' . $filters['syear'] . '. This subject has plans for '
                . $years->implode(', ') . '.';
        }

        return 'No lesson plan has been created for this class and subject yet. '
            . 'Use Create lesson plan to add one, or Auto-generate to build the whole term.';
    }

}
