<?php

namespace App\Http\Controllers\api\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Controllers\api\Attendance\Concerns\ResolvesAttendanceContext;
use App\Models\HrmsDepartment;
use App\Models\user\tbluserModel;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $departments = HrmsDepartment::where('status', true)
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

    /**
     * GET /api/attendance/day-detail
     *
     * Every active employee (in scope) for a single date, with computed
     * status - present / late / absent / early-going / leave - and punch
     * times. Unlike HrmsController::earlyGoingHrmsAttendanceReport (which
     * only returns employees who left before their scheduled out time) this
     * is a LEFT JOIN over the full employee roster, so absent and ordinary
     * on-time employees are included too. Modelled on
     * AttendanceTrackingApiController::myAttendance's per-day status
     * resolution, but for many employees on one date instead of one
     * employee across a calendar range.
     */
    public function dayDetail(Request $request)
    {
        $context = $this->attendanceContext($request);

        if (!is_array($context)) {
            return $context;
        }

        $subInstituteId = $context['sub_institute_id'];
        // Some sub_institutes have more than one hrms_departments row sharing
        // the same display name (e.g. duplicate data entry), so the frontend
        // groups by name but resolves department_id as a comma-separated
        // list of every id that shares that name - a single-id where()
        // would silently drop employees who belong to the "other" id, which
        // is exactly why the roster came up short of the summary's total.
        $departmentIds = $this->activeFilter($request->input('department_id'));
        $departmentIds = $departmentIds ? array_filter(array_map('trim', explode(',', $departmentIds))) : [];
        $employeeId = $this->activeFilter($request->input('employee_id'));
        $date = $request->filled('date') ? Carbon::parse($request->input('date'))->format('Y-m-d') : Carbon::now()->format('Y-m-d');
        $dayName = strtolower(Carbon::parse($date)->format('l'));

        $employees = tbluserModel::query()
            ->join('hrms_departments as hd', 'hd.id', '=', 'tbluser.department_id')
            ->leftJoin('hrms_attendances as ha', function ($join) use ($date, $subInstituteId) {
                $join->on('ha.user_id', '=', 'tbluser.id')
                    ->where('ha.day', $date)
                    ->where('ha.sub_institute_id', $subInstituteId)
                    ->where('ha.status', 1);
            })
            ->where('tbluser.sub_institute_id', $subInstituteId)
            ->where('tbluser.status', 1)
            ->when(!empty($departmentIds), function ($query) use ($departmentIds) {
                $query->whereIn('tbluser.department_id', $departmentIds);
            })
            ->when($employeeId, function ($query) use ($employeeId) {
                $query->where('tbluser.id', $employeeId);
            })
            ->orderBy('tbluser.first_name')
            ->orderBy('tbluser.last_name')
            ->get([
                'tbluser.id as user_id',
                'tbluser.employee_no',
                'tbluser.first_name',
                'tbluser.middle_name',
                'tbluser.last_name',
                'hd.department',
                'hd.id as department_id',
                'tbluser.' . $dayName . '_in_date as expected_in',
                'tbluser.' . $dayName . '_out_date as expected_out',
                'ha.punchin_time',
                'ha.punchout_time',
                'ha.timestamp_diff',
            ]);

        $userIds = $employees->pluck('user_id')->all();

        $onLeave = empty($userIds) ? collect() : DB::table('hrms_emp_leaves')
            ->where('sub_institute_id', $subInstituteId)
            ->where('status', 'approved')
            ->whereNull('deleted_at')
            ->whereIn('user_id', $userIds)
            ->where('from_date', '<=', $date)
            ->where('to_date', '>=', $date)
            ->pluck('user_id')
            ->flip();

        $result = $employees->map(function ($row) use ($onLeave, $date) {
            $status = 'present';

            if ($onLeave->has($row->user_id)) {
                $status = 'leave';
            } elseif (empty($row->punchin_time)) {
                $status = 'absent';
            } elseif (!empty($row->expected_in) && Carbon::parse($row->punchin_time)->gt(Carbon::parse($date . ' ' . Carbon::parse($row->expected_in)->format('H:i:s')))) {
                $status = 'late';
            } elseif (!empty($row->punchout_time) && !empty($row->expected_out) && Carbon::parse($row->punchout_time)->lt(Carbon::parse($date . ' ' . Carbon::parse($row->expected_out)->format('H:i:s')))) {
                $status = 'early-going';
            }

            // Built in PHP (not SQL CONCAT_WS/COALESCE) so a name part stored
            // as an empty string - not NULL, which COALESCE would have
            // caught - can't produce a whitespace-only "full name" that
            // renders blank on the frontend.
            $nameParts = array_filter(
                [$row->first_name, $row->middle_name, $row->last_name],
                fn ($part) => $part !== null && trim($part) !== ''
            );
            $fullName = empty($nameParts) ? null : implode(' ', array_map('trim', $nameParts));

            return [
                'user_id' => $row->user_id,
                'employee_no' => $row->employee_no,
                'full_name' => $fullName,
                'department' => $row->department,
                'department_id' => $row->department_id,
                'punchin_time' => $row->punchin_time,
                'punchout_time' => $row->punchout_time,
                'timestamp_diff' => $row->timestamp_diff,
                'expected_out' => $row->expected_out,
                'status' => $status,
            ];
        })->values();

        return response()->json([
            'status' => 1,
            'message' => 'Success to Find Data',
            'date' => $date,
            'department_id' => implode(',', $departmentIds),
            'employee_id' => $employeeId,
            'employees' => $result,
        ]);
    }

    /**
     * GET /api/attendance/latest-activity-date
     *
     * The most recent date (in scope) with a real attendance punch. The
     * report screens' single-day views (Daily Details, department "View")
     * default to the applied date range's own end date, which - even after
     * the initial "jump to the latest activity date on load" default - can
     * still land on a day with nothing recorded whenever the user's own
     * date range extends past the last real punch (e.g. "this month",
     * where only the first half has data): the aggregate summary for that
     * range looks full of activity, but the single-day view is blank with
     * no obvious reason why. Optional from_date/to_date bound the search to
     * the caller's own applied range, so the frontend can resolve "the
     * latest date WITHIN what I've selected that actually has data" rather
     * than only the institute-wide latest.
     */
    public function latestActivityDate(Request $request)
    {
        $context = $this->attendanceContext($request);

        if (!is_array($context)) {
            return $context;
        }

        $subInstituteId = $context['sub_institute_id'];
        $departmentId = $this->activeFilter($request->input('department_id'));
        $fromDate = $this->activeFilter($request->input('from_date'));
        $toDate = $this->activeFilter($request->input('to_date'));

        $latestDate = DB::table('hrms_attendances as ha')
            ->join('tbluser as tu', 'tu.id', '=', 'ha.user_id')
            ->where('ha.sub_institute_id', $subInstituteId)
            ->where('ha.status', 1)
            ->whereNotNull('ha.punchin_time')
            ->when($departmentId, function ($query) use ($departmentId) {
                $query->where('tu.department_id', $departmentId);
            })
            ->when($fromDate, function ($query) use ($fromDate) {
                $query->where('ha.day', '>=', $fromDate);
            })
            ->when($toDate, function ($query) use ($toDate) {
                $query->where('ha.day', '<=', $toDate);
            })
            ->max('ha.day');

        return response()->json([
            'status' => 1,
            'message' => 'Success to Find Data',
            'date' => $latestDate,
        ]);
    }
}
