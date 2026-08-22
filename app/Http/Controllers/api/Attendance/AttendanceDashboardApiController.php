<?php

namespace App\Http\Controllers\api\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Controllers\api\Attendance\Concerns\ResolvesAttendanceContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Attendance analytics for the HRIT dashboard / attendance tracking header.
 *
 * Legacy equivalents: Api\HRITDashboard\AttendanceApiController::weeklySummary
 * (GET /api/attendance-weekly) and ::KPI (GET /api/KPI-HRITDashboard), which
 * stay untouched and still serve every existing consumer. This controller
 * keeps the same response contract and adds the optional employee_id filter so
 * a single selected employee makes the denominator 1 and the series read as
 * that person's own percentages.
 */
class AttendanceDashboardApiController extends Controller
{
    use ResolvesAttendanceContext;

    /**
     * GET /api/attendance/weekly-summary
     */
    public function weeklySummary(Request $request)
    {
        $context = $this->attendanceContext($request);

        if (!is_array($context)) {
            return $context;
        }

        $subInstituteId = $context['sub_institute_id'];
        $departmentId = $this->activeFilter($request->input('department_id'));
        $employeeId = $this->activeFilter($request->input('employee_id'));

        // DATE RANGE LOGIC
        if ($request->filled('from_date') && $request->filled('to_date')) {
            $start = Carbon::parse($request->input('from_date'));
            $end   = Carbon::parse($request->input('to_date'));
        } else {
            // Default: previous week, Monday to Sunday.
            $start = Carbon::now()->subWeek()->startOfWeek();
            $end   = Carbon::now()->subWeek()->endOfWeek();
        }

        $attendanceQuery = DB::table('hrms_attendances')
            ->join('tbluser', 'hrms_attendances.user_id', '=', 'tbluser.id')
            ->where('hrms_attendances.sub_institute_id', $subInstituteId)
            ->whereBetween('hrms_attendances.day', [
                $start->format('Y-m-d'),
                $end->format('Y-m-d'),
            ]);

        if ($departmentId) {
            $attendanceQuery->where('tbluser.department_id', $departmentId);
        }

        if ($employeeId) {
            $attendanceQuery->where('hrms_attendances.user_id', $employeeId);
        }

        $attendance = $attendanceQuery
            ->select('hrms_attendances.*', 'tbluser.first_name', 'tbluser.monday_in_date', 'tbluser.department_id')
            ->orderBy('hrms_attendances.day', 'ASC')
            ->get();

        // One label per calendar day in the requested [start, end] range
        // (inclusive), as an ISO date so callers can parse and reformat it
        // however they need (e.g. weekday name for a week view, day-of-month
        // for a month view). Previously this was a fixed 6-entry
        // ['Mon',...,'Sat'] array with no Sunday, so any range longer than 6
        // days silently lost days past the 6th, any shorter range (e.g. a
        // single day) still walked 6 days, and the labels never reflected
        // the real weekday of the dates being summarised.
        $labels = [];
        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            $labels[] = $cursor->format('Y-m-d');
        }

        $present = [];
        $absent  = [];
        $late    = [];
        $dailyPunchData = [];

        $userQuery = DB::table('tbluser')
            ->where('sub_institute_id', $subInstituteId)
            ->where('status', 1)
            ->whereNull('terminated_date');

        if ($departmentId) {
            $userQuery->where('department_id', $departmentId);
        }

        // A single selected employee makes the denominator 1, so the
        // present/absent/late series read as that person's own percentages.
        if ($employeeId) {
            $userQuery->where('id', $employeeId);
        }

        $totalUsers = $userQuery->count();

        foreach ($labels as $dayDate) {
            $dayRecords = $attendance->where('day', $dayDate);

            // Present is when both entries exist.
            $presentCount = $dayRecords
                ->whereNotNull('punchin_time')
                ->whereNotNull('punchout_time')
                ->count();

            $lateCount = $dayRecords->filter(function ($rec) {
                if ($rec->punchin_time && $rec->monday_in_date) {
                    $punch = Carbon::parse($rec->punchin_time)->format('H:i:s');

                    return $punch > $rec->monday_in_date;
                }

                return false;
            })->count();

            $absentCount = $totalUsers - $presentCount;

            $punchTimes = [];
            foreach ($dayRecords as $rec) {
                if ($rec->punchin_time && $rec->punchout_time) {
                    $punchTimes[] = [
                        'employee_id' => $rec->user_id,
                        'day'         => $rec->day,
                        'type'        => 'present',
                        'time'        => $rec->punchin_time,
                    ];
                } elseif (!$rec->punchin_time) {
                    $punchTimes[] = [
                        'employee_id' => $rec->user_id,
                        'day'         => $rec->day,
                        'type'        => 'absent',
                        'time'        => null,
                    ];
                } else {
                    $punchTimes[] = [
                        'employee_id' => $rec->user_id,
                        'day'         => $rec->day,
                        'type'        => 'incomplete',
                        'time'        => $rec->punchin_time,
                    ];
                }
            }

            $present[] = $totalUsers ? round(($presentCount / $totalUsers) * 100, 2) : 0;
            $absent[]  = $totalUsers ? round(($absentCount / $totalUsers) * 100, 2) : 0;
            $late[]    = $totalUsers ? round(($lateCount / $totalUsers) * 100, 2) : 0;

            $dailyPunchData[$dayDate] = $punchTimes;
        }

        return response()->json([
            'date_range' => [
                'start' => $start->format('Y-m-d'),
                'end'   => $end->format('Y-m-d'),
            ],
            'department_filter' => $departmentId ?? 'All',
            'employee_filter'   => $employeeId ?? 'All',
            'labels'      => $labels,
            'present'     => $present,
            'absent'      => $absent,
            'late'        => $late,
            'punch_times' => $dailyPunchData,
        ]);
    }

    /**
     * GET /api/attendance/kpi
     */
    public function kpi(Request $request)
    {
        $context = $this->attendanceContext($request);

        if (!is_array($context)) {
            return $context;
        }

        $subInstituteId = $context['sub_institute_id'];
        $departmentId = $this->activeFilter($request->input('department_id'));
        $employeeId = $this->activeFilter($request->input('employee_id'));

        $today = Carbon::today()->format('Y-m-d');

        $userQuery = DB::table('tbluser')
            ->where('sub_institute_id', $subInstituteId);

        if ($departmentId) {
            $userQuery->where('department_id', $departmentId);
        }

        if ($employeeId) {
            $userQuery->where('id', $employeeId);
        }

        $totalUsers = $userQuery->count();

        $attendanceQuery = DB::table('hrms_attendances')
            ->join('tbluser', 'hrms_attendances.user_id', '=', 'tbluser.id')
            ->where('hrms_attendances.sub_institute_id', $subInstituteId)
            ->where('hrms_attendances.day', $today)
            ->where('hrms_attendances.status', 1);

        if ($departmentId) {
            $attendanceQuery->where('tbluser.department_id', $departmentId);
        }

        if ($employeeId) {
            $attendanceQuery->where('hrms_attendances.user_id', $employeeId);
        }

        $presentCount = $attendanceQuery
            ->distinct()
            ->count('hrms_attendances.user_id');

        $presentPercentage = $totalUsers
            ? round(($presentCount / $totalUsers) * 100, 1)
            : 0;

        // LEAVE UTILIZATION over the April-March leave year.
        $currentYear = Carbon::now()->year;
        $startDate   = $currentYear . '-04-01';
        $endDate     = ($currentYear + 1) . '-03-31';

        $leaveQuery = DB::table('hrms_emp_leaves')
            ->join('tbluser', 'hrms_emp_leaves.user_id', '=', 'tbluser.id')
            ->where('hrms_emp_leaves.sub_institute_id', $subInstituteId)
            ->where('hrms_emp_leaves.status', 'approved')
            ->where('hrms_emp_leaves.from_date', '>=', $startDate)
            ->where('hrms_emp_leaves.to_date', '<=', $endDate);

        if ($departmentId) {
            $leaveQuery->where('tbluser.department_id', $departmentId);
        }

        if ($employeeId) {
            $leaveQuery->where('hrms_emp_leaves.user_id', $employeeId);
        }

        $totalLeaveDaysTaken = $leaveQuery
            ->selectRaw('SUM((DATEDIFF(to_date, from_date) + 1) * day_type) as total_days')
            ->value('total_days') ?? 0;

        // ASSUMING 30 LEAVE DAYS PER EMPLOYEE PER YEAR
        $totalAllocatedDays = $totalUsers * 30;

        $leaveUtilization = $totalAllocatedDays
            ? round(($totalLeaveDaysTaken / $totalAllocatedDays) * 100, 1)
            : 0;

        return response()->json([
            'present_today'     => $presentPercentage . '%',
            'leave_utilization' => $leaveUtilization . '%',
            'active_employees'  => $totalUsers,
        ]);
    }
}
