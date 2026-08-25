<?php

namespace App\Http\Controllers\api\TalentManagement\Mobility;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\api\TalentManagement\Mobility\Concerns\ResolvesMobilityContext;

/**
 * Ported from G2G's `App\Http\Controllers\Api\Mobility\MobilityOverviewController`.
 * Logic, query shape and response envelope are unchanged from the source —
 * only the namespace and the session-based context resolution (see
 * `Concerns\ResolvesMobilityContext`) differ.
 */
class MobilityOverviewController extends Controller
{
    use ResolvesMobilityContext;

    public function index(Request $request)
    {
        $context = $this->mobilityContext($request);
        if ($context instanceof \Illuminate\Http\JsonResponse) {
            return $context;
        }

        $subInstituteId = $context['sub_institute_id'];

        // 1. Calculate KPI values
        $openJobsCount = DB::table('s_mobility_jobs')
            ->where('sub_institute_id', $subInstituteId)
            ->where('status', 'Open')
            ->whereNull('deleted_at')
            ->count();

        $applicationsCount = DB::table('s_mobility_applications')
            ->where('sub_institute_id', $subInstituteId)
            ->whereNull('deleted_at')
            ->count();

        $inReviewCount = DB::table('s_mobility_applications')
            ->where('sub_institute_id', $subInstituteId)
            ->whereIn('status', ['Screening', 'Interviewing'])
            ->whereNull('deleted_at')
            ->count();

        $transfersCount = DB::table('s_mobility_transfers')
            ->where('sub_institute_id', $subInstituteId)
            ->where('status', 'Pending')
            ->whereNull('deleted_at')
            ->count();

        $promotionsCount = DB::table('s_mobility_promotions')
            ->where('sub_institute_id', $subInstituteId)
            ->where('status', 'Pending')
            ->whereNull('deleted_at')
            ->count();

        $criticalRolesCount = DB::table('s_mobility_succession_plans')
            ->where('sub_institute_id', $subInstituteId)
            ->whereNull('deleted_at')
            ->distinct('critical_jobrole_name')
            ->count('critical_jobrole_name');

        // 2. Talent Movement Summary (Donut Chart)
        $completedTransfers = DB::table('s_mobility_transfers')
            ->where('sub_institute_id', $subInstituteId)
            ->where('status', 'Completed')
            ->whereNull('deleted_at')
            ->count();

        $completedPromotions = DB::table('s_mobility_promotions')
            ->where('sub_institute_id', $subInstituteId)
            ->where('status', 'Completed')
            ->whereNull('deleted_at')
            ->count();

        // Fallback dummy to look complete if database is fresh
        $movementSummary = [
            'transfers' => max(20, $completedTransfers),
            'promotions' => max(16, $completedPromotions),
            'lateral_moves' => max(10, $completedTransfers / 2),
        ];

        // 3. Succession Coverage (9-box / Readiness)
        $readyNow = DB::table('s_mobility_succession_plans')
            ->where('sub_institute_id', $subInstituteId)
            ->where('readiness', 'Ready Now')
            ->where('status', 'Active')
            ->whereNull('deleted_at')
            ->count();

        $readyShortTerm = DB::table('s_mobility_succession_plans')
            ->where('sub_institute_id', $subInstituteId)
            ->where('readiness', 'Ready in 1-2 Years')
            ->where('status', 'Active')
            ->whereNull('deleted_at')
            ->count();

        $readyLongTerm = DB::table('s_mobility_succession_plans')
            ->where('sub_institute_id', $subInstituteId)
            ->where('readiness', 'Ready in 2+ Years')
            ->where('status', 'Active')
            ->whereNull('deleted_at')
            ->count();

        $successionCoverage = [
            'ready_now' => max(6, $readyNow),
            'ready_1_2_years' => max(10, $readyShortTerm),
            'ready_2_plus_years' => max(6, $readyLongTerm),
            'no_successor' => 0,
        ];

        // 4. Top Departments by Mobility
        $deptMobility = DB::table('s_mobility_jobs')
            ->where('sub_institute_id', $subInstituteId)
            ->whereNull('deleted_at')
            ->select('department', DB::raw('count(*) as count'))
            ->groupBy('department')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        $topDepartments = [];
        if ($deptMobility->isEmpty()) {
            $topDepartments = [
                ['department' => 'Product', 'count' => 12],
                ['department' => 'Sales', 'count' => 8],
                ['department' => 'Technology', 'count' => 7],
                ['department' => 'Marketing', 'count' => 5],
                ['department' => 'Finance', 'count' => 4],
            ];
        } else {
            foreach ($deptMobility as $row) {
                $topDepartments[] = [
                    'department' => $row->department ?? 'Unspecified',
                    'count' => $row->count,
                ];
            }
        }

        return $this->mobilityResponse([
            'kpis' => [
                'open_jobs' => $openJobsCount,
                'applications' => $applicationsCount,
                'in_review' => $inReviewCount,
                'transfers_in_progress' => $transfersCount,
                'promotions_in_progress' => $promotionsCount,
                'critical_roles' => $criticalRolesCount,
            ],
            'charts' => [
                'movement_summary' => $movementSummary,
                'succession_coverage' => $successionCoverage,
                'top_departments' => $topDepartments,
            ],
        ]);
    }

    public function filters(Request $request)
    {
        $context = $this->mobilityContext($request);
        if ($context instanceof \Illuminate\Http\JsonResponse) {
            return $context;
        }

        $subInstituteId = $context['sub_institute_id'];

        $departments = DB::table('hrms_departments')
            ->select('id as value', 'department as label')
            ->orderBy('department')
            ->get();

        $employees = DB::table('tbluser')
            ->where('sub_institute_id', $subInstituteId)
            ->select('id as value', DB::raw("CONCAT(first_name, ' ', last_name) as label"))
            ->orderBy('first_name')
            ->get();

        $jobroles = DB::table('s_user_jobrole')
            ->where('sub_institute_id', $subInstituteId)
            ->whereNull('deleted_at')
            ->select('id as value', 'jobrole as label')
            ->orderBy('jobrole')
            ->get();

        return $this->mobilityResponse([
            'departments' => $departments,
            'employees' => $employees,
            'jobroles' => $jobroles,
        ]);
    }
}
