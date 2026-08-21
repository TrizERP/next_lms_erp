<?php

namespace App\Http\Controllers\api\Leave;

use App\Http\Controllers\Controller;
use App\Http\Controllers\api\Leave\Concerns\ResolvesLeaveContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Leave distribution for the Leave Management dashboard.
 *
 * The legacy endpoint is Api\HRITDashboard\LeaveDistribution::leaveDistribution
 * (GET /api/leave-distribution), which is left exactly as it is. This variant
 * joins hrms_leave_types on its primary key (the legacy join compared the
 * 'LTY0xx' code column against the integer FK, so leave_type_name was always
 * null) and ignores soft deleted leave rows.
 */
class LeaveDistributionApiController extends Controller
{
    use ResolvesLeaveContext;

    /**
     * GET /api/leave/distribution
     */
    public function index(Request $request)
    {
        $context = $this->leaveContext($request);

        if (!is_array($context)) {
            return $context;
        }

        $subInstituteId = $context['sub_institute_id'];
        $departmentId = $this->activeFilter($request->input('department_id'));

        $query = DB::table('tbluser as u')
            ->leftJoin(DB::raw("(SELECT department_id, MIN(department) AS department
                                FROM s_user_jobrole
                                GROUP BY department_id) AS j"),
                fn ($join) => $join->on('j.department_id', '=', 'u.department_id'))
            ->join(DB::raw("(SELECT * FROM hrms_emp_leaves
                            WHERE deleted_at IS NULL
                              AND id IN (
                                SELECT MAX(id)
                                FROM hrms_emp_leaves
                                WHERE deleted_at IS NULL
                                GROUP BY user_id
                            )) AS l"),
                fn ($join) => $join->on('l.user_id', '=', 'u.id'))
            // lt.leave_type_id is the 'LTY0xx' code while l.leave_type_id is the
            // integer FK, so joining on the code can never match.
            ->leftJoin('hrms_leave_types as lt', 'lt.id', '=', 'l.leave_type_id')
            ->where('u.sub_institute_id', $subInstituteId);

        if ($departmentId) {
            $query->where('u.department_id', $departmentId);
        }

        $results = $query->select(
                'lt.leave_type AS leave_type_name',
                DB::raw("CASE
                            WHEN l.day_type = 1 THEN 'Full Day Leave'
                            WHEN l.day_type = 0.5 THEN 'Half Day Leave'
                            ELSE 'Unknown'
                        END AS leave_day_type"),
                DB::raw('COUNT(*) AS leave_count')
            )
            ->groupBy('lt.leave_type', 'leave_day_type')
            ->get();

        return response()->json([
            'status' => 1,
            'message' => 'Leave distribution fetched successfully',
            'data' => $results,
        ]);
    }
}
