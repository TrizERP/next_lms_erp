<?php

namespace App\Http\Controllers\api\Leave;

use App\Http\Controllers\api\Leave\Concerns\ResolvesLeaveContext;
use App\Http\Controllers\Controller;
use App\Services\Leave\LeaveAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaveReportApiController extends Controller
{
    use ResolvesLeaveContext;

    /**
     * days = (DATEDIFF(to, from) + 1) * day_type - the SQL equivalent of
     * App\Helpers\countDays() with no skip-day rule.
     */
    private const DAYS_EXPR = "((DATEDIFF(COALESCE(hel.to_date, hel.from_date), hel.from_date) + 1) * COALESCE(CAST(hel.day_type AS DECIMAL(4,2)), 1))";

    public function __construct(private LeaveAnalyticsService $analytics)
    {
    }

    /**
     * GET /api/leave/reports/summary
     * Feeds the Leave Summary report preview: one row per leave type.
     */
    public function summary(Request $request)
    {
        $context = $this->leaveContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $rows = $this->filtered($context, $request)
            ->selectRaw("
                COALESCE(hlt.leave_type, 'Unassigned') AS leave_type,
                COALESCE(hlt.id, 0) AS leave_type_id,
                COALESCE(hlt.leave_type_id, '') AS leave_type_code,
                COUNT(*) AS total,
                SUM(CASE WHEN hel.status IN ('approved','approved_lwp') THEN 1 ELSE 0 END) AS approved,
                SUM(CASE WHEN hel.status = 'pending' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN hel.status = 'rejected' THEN 1 ELSE 0 END) AS rejected,
                SUM(CASE WHEN hel.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled,
                SUM(CASE WHEN hel.status = 'sent_back' THEN 1 ELSE 0 END) AS sent_back,
                ROUND(SUM(CASE WHEN hel.status IN ('approved','pending','approved_lwp') THEN " . self::DAYS_EXPR . " ELSE 0 END), 2) AS days
            ")
            ->groupBy('hlt.id', 'hlt.leave_type', 'hlt.leave_type_id')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'leave_type_id'   => (int) $row->leave_type_id,
                'leave_type'      => $row->leave_type,
                'leave_type_code' => $row->leave_type_code,
                'total'           => (int) $row->total,
                'approved'        => (int) $row->approved,
                'pending'         => (int) $row->pending,
                'rejected'        => (int) $row->rejected,
                'cancelled'       => (int) $row->cancelled,
                'sent_back'       => (int) $row->sent_back,
                'days'            => (float) $row->days,
            ]);

        $departmentBreakdown = $this->filtered($context, $request)
            ->selectRaw("COALESCE(hd.department, 'Unassigned') AS department, COUNT(*) AS total")
            ->groupBy('hd.id', 'hd.department')
            ->orderByDesc('total')
            ->get();

        $grandTotal = (int) $departmentBreakdown->sum('total');

        return response()->json([
            'status'  => 1,
            'message' => 'Leave summary report fetched successfully',
            'year'    => $context['year'],
            'data'    => [
                'rows'    => $rows,
                'totals'  => [
                    'total'     => (int) $rows->sum('total'),
                    'approved'  => (int) $rows->sum('approved'),
                    'pending'   => (int) $rows->sum('pending'),
                    'rejected'  => (int) $rows->sum('rejected'),
                    'cancelled' => (int) $rows->sum('cancelled'),
                    'sent_back' => (int) $rows->sum('sent_back'),
                    'days'      => round((float) $rows->sum('days'), 2),
                ],
                'department_breakdown' => $departmentBreakdown->map(fn ($row) => [
                    'department' => $row->department,
                    'total'      => (int) $row->total,
                    'percentage' => $grandTotal > 0 ? round(((int) $row->total / $grandTotal) * 100, 2) : 0,
                ]),
            ],
        ]);
    }

    /**
     * GET /api/leave/reports/register
     * Row-level register / employee leave history export.
     */
    public function register(Request $request)
    {
        $context = $this->leaveContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $limit = min(max((int) $request->input('limit', 500), 1), 5000);

        $rows = $this->filtered($context, $request)
            ->selectRaw("
                hel.id,
                hel.user_id,
                hel.from_date,
                hel.to_date,
                hel.day_type,
                hel.status,
                hel.comment,
                hel.approved_by,
                hel.created_at,
                u.employee_no,
                CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS employee_name,
                COALESCE(hd.department, 'Unassigned') AS department,
                COALESCE(hlt.leave_type, 'Unassigned') AS leave_type,
                ROUND(" . self::DAYS_EXPR . ", 2) AS days
            ")
            ->orderBy('hel.from_date', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'id'             => (int) $row->id,
                'employee_id'    => (int) $row->user_id,
                'employee_no'    => $row->employee_no,
                'employee_name'  => trim(preg_replace('/\s+/', ' ', (string) $row->employee_name)),
                'department'     => $row->department,
                'leave_type'     => $row->leave_type,
                'from_date'      => $row->from_date,
                'to_date'        => $row->to_date,
                'days'           => (float) $row->days,
                'duration'       => $this->analytics->durationLabel((float) $row->days),
                'status'         => $row->status,
                'reason'         => $row->comment,
                'approver'       => $row->approved_by,
                'submitted_date' => $row->created_at,
            ]);

        return response()->json([
            'status'  => 1,
            'message' => 'Leave register fetched successfully',
            'year'    => $context['year'],
            'count'   => $rows->count(),
            'data'    => $rows,
        ]);
    }

    /**
     * GET /api/leave/reports/balance
     * Entitlement / used / remaining per employee per leave type.
     */
    public function balance(Request $request)
    {
        $context = $this->leaveContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $departmentId = $this->activeFilter($request->input('department_id'));
        $employeeId   = $this->activeFilter($request->input('employee_id'));

        $entitlement = $this->analytics->entitlementByType(
            $context['sub_institute_id'],
            $context['year'],
            $departmentId ? (int) $departmentId : null,
            $employeeId ? (int) $employeeId : null
        );

        $consumed = $this->analytics->consumedByType(
            $context['sub_institute_id'],
            $context['year'],
            $departmentId ? (int) $departmentId : null,
            $employeeId ? (int) $employeeId : null
        );

        $userIds = collect($entitlement)->merge($consumed)
            ->flatMap(fn ($byUser) => array_keys($byUser))
            ->unique()
            ->values();

        if ($userIds->isEmpty()) {
            return response()->json([
                'status'  => 1,
                'message' => 'Leave balance report fetched successfully',
                'year'    => $context['year'],
                'data'    => ['leave_types' => [], 'rows' => []],
            ]);
        }

        $employees = DB::table('tbluser as u')
            ->leftJoin('hrms_departments as hd', 'hd.id', '=', 'u.department_id')
            ->selectRaw("
                u.id, u.employee_no,
                CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS employee_name,
                COALESCE(hd.department, 'Unassigned') AS department
            ")
            ->whereIn('u.id', $userIds)
            ->orderBy('u.first_name')
            ->get();

        $leaveTypeNames = collect(array_keys($entitlement))
            ->merge(array_keys($consumed))
            ->unique()
            ->sort()
            ->values();

        $rows = $employees->map(function ($employee) use ($leaveTypeNames, $entitlement, $consumed) {
            $balances = [];

            foreach ($leaveTypeNames as $name) {
                $total = (float) ($entitlement[$name][$employee->id] ?? 0);
                $used  = (float) ($consumed[$name][$employee->id] ?? 0);

                $balances[$name] = [
                    'total'     => round($total, 2),
                    'used'      => round($used, 2),
                    'remaining' => $total > 0 ? round($total - $used, 2) : 0.0,
                ];
            }

            return [
                'employee_id'   => (int) $employee->id,
                'employee_no'   => $employee->employee_no,
                'employee_name' => trim(preg_replace('/\s+/', ' ', (string) $employee->employee_name)),
                'department'    => $employee->department,
                'balances'      => $balances,
            ];
        });

        return response()->json([
            'status'  => 1,
            'message' => 'Leave balance report fetched successfully',
            'year'    => $context['year'],
            'data'    => [
                'leave_types' => $leaveTypeNames,
                'rows'        => $rows,
            ],
        ]);
    }

    /** Shared filter set for every report. */
    private function filtered(array $context, Request $request)
    {
        $query = $this->analytics->requestsQuery($context['sub_institute_id'], $context['year']);

        $departments = $this->filterList($request->input('department_id'));
        $employees   = $this->filterList($request->input('employee_id'));
        $leaveTypes  = $this->filterList($request->input('leave_type_id'));
        $statuses    = $this->filterList($request->input('status'));
        $fromDate    = $this->activeFilter($request->input('from_date'));
        $toDate      = $this->activeFilter($request->input('to_date'));
        $activeOnly  = $request->input('employee_status', 'active');

        if (!empty($departments)) {
            $query->whereIn('u.department_id', $departments);
        }

        if (!empty($employees)) {
            $query->whereIn('hel.user_id', $employees);
        }

        if (!empty($leaveTypes)) {
            $query->whereIn('hel.leave_type_id', $leaveTypes);
        }

        if (!empty($statuses)) {
            $query->whereIn('hel.status', $statuses);
        }

        if ($fromDate) {
            $query->where('hel.to_date', '>=', $fromDate);
        }

        if ($toDate) {
            $query->where('hel.from_date', '<=', $toDate);
        }

        if ($activeOnly === 'active') {
            $query->where('u.status', 1);
        } elseif ($activeOnly === 'inactive') {
            $query->where('u.status', '!=', 1);
        }

        return $query;
    }
}
