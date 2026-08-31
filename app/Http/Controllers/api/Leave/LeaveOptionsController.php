<?php

namespace App\Http\Controllers\api\Leave;

use App\Http\Controllers\api\Leave\Concerns\ResolvesLeaveContext;
use App\Http\Controllers\Controller;
use App\Services\Leave\LeaveAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaveOptionsController extends Controller
{
    use ResolvesLeaveContext;

    public function __construct(private LeaveAnalyticsService $analytics)
    {
    }

    /**
     * GET /api/leave/options
     *
     * Every dropdown the Leave screens need, in a single round trip:
     * departments, employees, leave types and the status vocabulary.
     */
    public function index(Request $request)
    {
        $context = $this->leaveContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $departmentId = $this->activeFilter($request->input('department_id'));

        $departments = DB::table('hrms_departments')
            ->select('id', 'department')
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->orderBy('department')
            ->get()
            ->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->department]);

        $employees = DB::table('tbluser as u')
            ->leftJoin('hrms_departments as hd', 'hd.id', '=', 'u.department_id')
            ->selectRaw("
                u.id,
                u.employee_no,
                u.department_id,
                COALESCE(hd.department, '') AS department,
                CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS employee_name
            ")
            ->where('u.sub_institute_id', $context['sub_institute_id'])
            ->where('u.status', 1)
            ->when($departmentId, fn ($q) => $q->where('u.department_id', $departmentId))
            ->orderBy('u.first_name')
            ->get()
            ->map(fn ($row) => [
                'value'         => (string) $row->id,
                'label'         => trim(preg_replace('/\s+/', ' ', (string) $row->employee_name)),
                'employee_no'   => $row->employee_no,
                'department_id' => $row->department_id ? (string) $row->department_id : null,
                'department'    => $row->department,
            ]);

        $leaveTypes = $this->analytics->leaveTypes($context['sub_institute_id'])
            ->map(fn ($row) => [
                'value' => (string) $row->id,
                'label' => $row->leave_type,
                'code'  => $row->leave_type_id,
            ]);

        return response()->json([
            'status'  => 1,
            'message' => 'Leave options fetched successfully',
            'year'    => $context['year'],
            'data'    => [
                'departments' => $departments,
                'employees'   => $employees,
                'leave_types' => $leaveTypes,
                'statuses'    => [
                    ['value' => 'pending',      'label' => 'Pending'],
                    ['value' => 'approved',     'label' => 'Approved'],
                    ['value' => 'rejected',     'label' => 'Rejected'],
                    ['value' => 'sent_back',    'label' => 'Sent Back'],
                    ['value' => 'cancelled',    'label' => 'Cancelled'],
                    ['value' => 'approved_lwp', 'label' => 'Approved LWP'],
                ],
            ],
        ]);
    }

    /**
     * GET /api/leave/balances
     * Balance snapshot for one employee (defaults to the caller).
     */
    public function balances(Request $request)
    {
        $context = $this->leaveContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $employeeId = (int) ($this->activeFilter($request->input('employee_id')) ?: $context['user_id']);

        if (!$employeeId) {
            return response()->json(['status' => 0, 'message' => 'employee_id is required'], 400);
        }

        $balances = $this->analytics->balancesForEmployee(
            $context['sub_institute_id'],
            $context['year'],
            $employeeId
        );

        return response()->json([
            'status'      => 1,
            'message'     => 'Leave balances fetched successfully',
            'year'        => $context['year'],
            'employee_id' => $employeeId,
            'data'        => $balances,
        ]);
    }
}
