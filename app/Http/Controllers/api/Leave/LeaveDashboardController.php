<?php

namespace App\Http\Controllers\api\Leave;

use App\Http\Controllers\api\Leave\Concerns\ResolvesLeaveContext;
use App\Http\Controllers\Controller;
use App\Services\Leave\LeaveAnalyticsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaveDashboardController extends Controller
{
    use ResolvesLeaveContext;

    public function __construct(private LeaveAnalyticsService $analytics)
    {
    }

    /**
     * GET /api/leave/dashboard
     * KPI cards + recent activity for the Leave Dashboard screen.
     */
    public function index(Request $request)
    {
        $context = $this->leaveContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $departmentId = $this->activeFilter($request->input('department_id'));
        $today = Carbon::today()->toDateString();

        $counts = $this->analytics
            ->requestsQuery($context['sub_institute_id'], $context['year'])
            ->when($departmentId, fn ($q) => $q->where('u.department_id', $departmentId))
            ->selectRaw("
                COUNT(*) AS total_requests,
                SUM(CASE WHEN hel.status = 'pending' THEN 1 ELSE 0 END) AS pending_requests,
                SUM(CASE WHEN hel.status IN ('approved','approved_lwp') THEN 1 ELSE 0 END) AS approved_requests,
                SUM(CASE WHEN hel.status = 'rejected' THEN 1 ELSE 0 END) AS rejected_requests,
                SUM(CASE WHEN hel.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_requests
            ")
            ->first();

        $onLeaveToday = $this->analytics
            ->requestsQuery($context['sub_institute_id'], $context['year'])
            ->when($departmentId, fn ($q) => $q->where('u.department_id', $departmentId))
            ->whereIn('hel.status', ['approved', 'approved_lwp'])
            ->where('hel.from_date', '<=', $today)
            ->where('hel.to_date', '>=', $today)
            ->distinct()
            ->count('hel.user_id');

        $availableBalance = 0.0;
        if ($context['user_id']) {
            $balances = $this->analytics->balancesForEmployee(
                $context['sub_institute_id'],
                $context['year'],
                $context['user_id']
            );
            $availableBalance = $balances['overall']['remaining'];
        }

        return response()->json([
            'status'  => 1,
            'message' => 'Leave dashboard fetched successfully',
            'year'    => $context['year'],
            'data'    => [
                'total_requests'    => (int) ($counts->total_requests ?? 0),
                'pending_requests'  => (int) ($counts->pending_requests ?? 0),
                'approved_requests' => (int) ($counts->approved_requests ?? 0),
                'rejected_requests' => (int) ($counts->rejected_requests ?? 0),
                'cancelled_requests'=> (int) ($counts->cancelled_requests ?? 0),
                'on_leave_today'    => (int) $onLeaveToday,
                'available_balance' => $availableBalance,
                'recent_activity'   => $this->recentActivity($context, $departmentId),
            ],
        ]);
    }

    /**
     * GET /api/leave/trend
     * Requests / approved / rejected for each month of the April-March leave year.
     */
    public function trend(Request $request)
    {
        $context = $this->leaveContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $departmentId = $this->activeFilter($request->input('department_id'));

        $rows = $this->analytics
            ->requestsQuery($context['sub_institute_id'], $context['year'])
            ->when($departmentId, fn ($q) => $q->where('u.department_id', $departmentId))
            ->selectRaw("
                DATE_FORMAT(hel.from_date, '%Y-%m') AS period,
                COUNT(*) AS requests,
                SUM(CASE WHEN hel.status IN ('approved','approved_lwp') THEN 1 ELSE 0 END) AS approved,
                SUM(CASE WHEN hel.status = 'rejected' THEN 1 ELSE 0 END) AS rejected
            ")
            ->groupBy('period')
            ->get()
            ->keyBy('period');

        // Always emit all 12 months of the leave year so the chart never has gaps.
        $data = [];
        $cursor = Carbon::createFromFormat('Y-m-d', $context['from']);

        for ($i = 0; $i < 12; $i++) {
            $period = $cursor->format('Y-m');
            $row = $rows->get($period);

            $data[] = [
                'period'   => $period,
                'month'    => $cursor->format('M'),
                'requests' => (int) ($row->requests ?? 0),
                'approved' => (int) ($row->approved ?? 0),
                'rejected' => (int) ($row->rejected ?? 0),
            ];

            $cursor->addMonth();
        }

        return response()->json([
            'status'  => 1,
            'message' => 'Leave trend fetched successfully',
            'year'    => $context['year'],
            'data'    => $data,
        ]);
    }

    /**
     * GET /api/leave/department-summary
     */
    public function departmentSummary(Request $request)
    {
        $context = $this->leaveContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $rows = $this->analytics
            ->requestsQuery($context['sub_institute_id'], $context['year'])
            ->selectRaw("
                COALESCE(hd.department, 'Unassigned') AS department,
                COALESCE(hd.id, 0) AS department_id,
                COUNT(*) AS requests,
                SUM(CASE WHEN hel.status IN ('approved','approved_lwp') THEN 1 ELSE 0 END) AS approved,
                SUM(CASE WHEN hel.status = 'rejected' THEN 1 ELSE 0 END) AS rejected,
                SUM(CASE WHEN hel.status = 'pending' THEN 1 ELSE 0 END) AS pending
            ")
            ->groupBy('hd.id', 'hd.department')
            ->orderByDesc('requests')
            ->get()
            ->map(fn ($row) => [
                'department_id' => (int) $row->department_id,
                'department'    => $row->department,
                'requests'      => (int) $row->requests,
                'approved'      => (int) $row->approved,
                'rejected'      => (int) $row->rejected,
                'pending'       => (int) $row->pending,
            ]);

        return response()->json([
            'status'  => 1,
            'message' => 'Department summary fetched successfully',
            'year'    => $context['year'],
            'data'    => $rows,
        ]);
    }

    /**
     * GET /api/leave/type-distribution
     * Replaces /api/leave-distribution, whose join compared hrms_leave_types.leave_type_id
     * (an "LTY0xx" string) against hrms_emp_leaves.leave_type_id (an integer FK) and so
     * never matched a row.
     */
    public function typeDistribution(Request $request)
    {
        $context = $this->leaveContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $departmentId = $this->activeFilter($request->input('department_id'));

        $rows = $this->analytics
            ->requestsQuery($context['sub_institute_id'], $context['year'])
            ->when($departmentId, fn ($q) => $q->where('u.department_id', $departmentId))
            ->selectRaw("
                COALESCE(hlt.leave_type, 'Unassigned') AS leave_type,
                COALESCE(hlt.id, 0) AS leave_type_id,
                COUNT(*) AS leave_count,
                SUM(CASE WHEN hel.day_type = '0.5' THEN 1 ELSE 0 END) AS half_day_count,
                SUM(CASE WHEN hel.day_type <> '0.5' OR hel.day_type IS NULL THEN 1 ELSE 0 END) AS full_day_count
            ")
            ->groupBy('hlt.id', 'hlt.leave_type')
            ->orderByDesc('leave_count')
            ->get()
            ->map(fn ($row) => [
                'leave_type_id'  => (int) $row->leave_type_id,
                'leave_type'     => $row->leave_type,
                'leave_count'    => (int) $row->leave_count,
                'full_day_count' => (int) $row->full_day_count,
                'half_day_count' => (int) $row->half_day_count,
            ]);

        return response()->json([
            'status'  => 1,
            'message' => 'Leave distribution fetched successfully',
            'year'    => $context['year'],
            'data'    => $rows,
        ]);
    }

    /**
     * GET /api/leave/holidays/upcoming
     */
    public function upcomingHolidays(Request $request)
    {
        $context = $this->leaveContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $limit = min(max((int) $request->input('limit', 5), 1), 50);

        $holidays = DB::table('hrms_holidays')
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->whereNull('deleted_at')
            ->whereDate('to_date', '>=', Carbon::today()->toDateString())
            ->orderBy('from_date')
            ->limit($limit)
            ->get()
            ->map(fn ($holiday) => [
                'id'           => (int) $holiday->id,
                'name'         => $holiday->holiday_name,
                'from_date'    => $holiday->from_date,
                'to_date'      => $holiday->to_date,
                'day'          => $holiday->from_date ? Carbon::parse($holiday->from_date)->format('l') : null,
                'day_type'     => $holiday->day_type,
                'department'   => $holiday->department,
            ]);

        return response()->json([
            'status'  => 1,
            'message' => 'Upcoming holidays fetched successfully',
            'data'    => $holidays,
        ]);
    }

    /**
     * Activity feed derived from the leave rows themselves - a request is an
     * "application" until it is decided, at which point the decision timestamp wins.
     */
    private function recentActivity(array $context, ?string $departmentId): array
    {
        $rows = $this->analytics
            ->requestsQuery($context['sub_institute_id'], $context['year'])
            ->when($departmentId, fn ($q) => $q->where('u.department_id', $departmentId))
            ->selectRaw("
                hel.id,
                hel.status,
                hel.from_date,
                hel.to_date,
                hel.comment,
                hel.approved_by,
                hel.created_at,
                hel.updated_at,
                hel.hod_comment_date,
                hel.hr_remark_date,
                COALESCE(hlt.leave_type, 'Leave') AS leave_type,
                CONCAT_WS(' ', u.first_name, u.last_name) AS employee_name,
                GREATEST(COALESCE(hel.updated_at, hel.created_at), COALESCE(hel.created_at, hel.updated_at)) AS activity_at
            ")
            ->orderByDesc('activity_at')
            ->orderByDesc('hel.id')
            ->limit(10)
            ->get();

        return $rows->map(function ($row) {
            $type = match ($row->status) {
                'approved', 'approved_lwp' => 'approval',
                'rejected'                 => 'rejection',
                'cancelled'                => 'cancellation',
                default                    => 'application',
            };

            $title = match ($type) {
                'approval'     => trim($row->employee_name) . "'s " . $row->leave_type . ' approved',
                'rejection'    => trim($row->employee_name) . "'s " . $row->leave_type . ' rejected',
                'cancellation' => trim($row->employee_name) . "'s " . $row->leave_type . ' cancelled',
                default        => trim($row->employee_name) . ' applied for ' . $row->leave_type,
            };

            return [
                'id'          => (int) $row->id,
                'type'        => $type,
                'title'       => $title,
                'description' => $row->comment ?: ($row->approved_by ? 'Actioned by ' . $row->approved_by : 'Awaiting review'),
                'timestamp'   => $row->activity_at,
                'from_date'   => $row->from_date,
                'to_date'     => $row->to_date,
            ];
        })->all();
    }
}
