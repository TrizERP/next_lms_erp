<?php

namespace App\Http\Controllers\api\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Controllers\api\Attendance\Concerns\ResolvesAttendanceContext;
use App\Models\HRMS\hrmsDepartmentModel;
use App\Models\user\tbluserModel;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Lookup endpoints for the Attendance Reports screen.
 *
 * The legacy equivalents are HrmsController::hrmsAttendanceReportIndex and
 * HrmsController::getEmployeeLists on the `hrms-attendance-report` /
 * `get-employees-list` web routes, which stay as they are for the Blade
 * screens. This API variant scopes the department dropdown to the caller's
 * sub_institute and treats an absent / "all" / 0 department as "every active
 * employee of the institute" instead of returning an empty list.
 */
class AttendanceReportApiController extends Controller
{
    use ResolvesAttendanceContext;

    /**
     * GET /api/attendance/report-filters
     *
     * Department dropdown plus the default date range for the report screen.
     */
    public function filters(Request $request)
    {
        $context = $this->attendanceContext($request);

        if (!is_array($context)) {
            return $context;
        }

        $subInstituteId = $context['sub_institute_id'];

        // Scope the dropdown to the caller's institute, otherwise it offers
        // departments that have no employees in this sub_institute.
        $departments = hrmsDepartmentModel::where('status', true)
            ->where('sub_institute_id', $subInstituteId)
            ->orderBy('department')
            ->pluck('department', 'id');

        return response()->json([
            'status' => 1,
            'message' => 'Success to Find Data',
            'employee_id' => $request->get('employee_id'),
            'department_id' => $request->get('department_id'),
            'from_date_formatted' => Carbon::now()->format('Y-m-d'),
            'to_date_formatted' => Carbon::now()->format('Y-m-d'),
            'departments' => $departments,
        ]);
    }

    /**
     * GET /api/attendance/employees
     *
     * Active employees of the institute, optionally narrowed to a department.
     */
    public function employees(Request $request)
    {
        $context = $this->attendanceContext($request);

        if (!is_array($context)) {
            return $context;
        }

        $subInstituteId = $context['sub_institute_id'];
        $departmentId = $this->activeFilter($request->input('department_id'));
        $employeeId = $request->get('employee_id');

        $employees = tbluserModel::where('sub_institute_id', $subInstituteId)
            ->where('status', 1)
            ->when($departmentId, function ($query) use ($departmentId) {
                $query->where('department_id', $departmentId);
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'employee_no', 'first_name', 'middle_name', 'last_name', 'department_id'])
            ->toArray();

        return response()->json([
            'status' => 1,
            'message' => 'Success to Find Data',
            'employees' => $employees,
            'department_id' => $request->input('department_id'),
            'employee_id' => $employeeId,
        ]);
    }
}
