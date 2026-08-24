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

class MonthlyPlanApiController extends Controller
{
    /**
     * Calendar view of scheduled periods for a given month, grouped by day,
     * for a standard/division (optionally scoped to one subject).
     *
     * GET|POST /api/intelligence/monthly-plan
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|integer',
            'syear'            => 'required',
            'standard_id'      => 'required|integer',
            'division_id'      => 'nullable|integer',
            'subject_id'       => 'nullable|integer',
            'year'             => 'required|integer',
            'month'            => 'required|integer|min:1|max:12',
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
            $monthStart = Carbon::createFromDate($filters['year'], $filters['month'], 1)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();

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
                ->whereBetween('pp.scheduled_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->orderBy('pp.scheduled_date')
                ->orderBy('pp.period_slot')
                ->select(
                    'pp.*',
                    'lp.subject_id',
                    'sub.subject_name',
                    DB::raw('concat(tu.first_name," ",tu.middle_name," ",tu.last_name) as teacher_name')
                )
                ->get();

            $days = $periods
                ->groupBy(fn ($p) => Carbon::parse($p->scheduled_date)->toDateString())
                ->map(function ($dayPeriods, $date) {
                    return [
                        'date'    => $date,
                        'weekday' => Carbon::parse($date)->format('l'),
                        'periods' => $dayPeriods->map(function ($period) {
                            return [
                                'period_id'            => $period->id,
                                'subject_id'           => $period->subject_id,
                                'subject_name'         => $period->subject_name,
                                'topic'                => $period->primary_concept_name ?: $period->chapter_name,
                                'period_slot'          => $period->period_slot,
                                'teacher_name'         => trim(preg_replace('/\s+/', ' ', $period->teacher_name ?? '')),
                                'status'               => $period->status,
                                'planned_duration_min' => $period->planned_duration_min,
                            ];
                        })->values(),
                    ];
                })
                ->values();

            return response()->json([
                'status'  => true,
                'message' => 'Monthly Plan Data Found',
                'data'    => [
                    'month'         => $monthStart->format('F Y'),
                    'month_start'   => $monthStart->toDateString(),
                    'month_end'     => $monthEnd->toDateString(),
                    'total_lessons' => $periods->count(),
                    'days'          => $days,
                ],
            ], 200);
        } catch (Throwable $e) {
            Log::error('MonthlyPlan fetch failed: ' . $e->getMessage(), [
                'filters' => $filters,
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong while fetching Monthly Plan data',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
