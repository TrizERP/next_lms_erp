<?php

namespace App\Http\Controllers\api\HRITDashboard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\api\Concerns\ResolvesApiIdentity;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class AttendanceApiController extends Controller
{
    use GetsJwtToken;
    use ResolvesApiIdentity;

    /**
     * True when $token validates as this app's own JWT (see
     * App\Http\Middleware\ApiSessionHydrator for the established precedent).
     * Not a Laravel Sanctum personal access token - a different, incompatible
     * token format this codebase's real tokens never match.
     */
    private function isValidJwt(Request $request): bool
    {
        try {
            return (bool) $this->jwtToken($request)->validate();
        } catch (\Exception $e) {
            return false;
        }
    }

    public function weeklySummary(Request $request)
    {
        $type = $request->input('type');

        // Token validation for API type
        if ($type == "API") {
            $token = $request->input('token');

            if (!$token) {
                return response()->json(['message' => 'Token not provided'], 401);
            }

            if (!$this->isValidJwt($request)) {
                return response()->json(['message' => 'Invalid token'], 401);
            }
        }

        $subInstituteId = $this->apiTenantId($request);
        $departmentId   = $request->department_id;  // <── NEW FILTER VARIABLE

        if (!$subInstituteId) {
            return response()->json(["error" => "sub_institute_id is required"], 400);
        }

        // DATE RANGE LOGIC
        if ($request->from_date && $request->to_date) {
            $start = Carbon::parse($request->from_date);
            $end   = Carbon::parse($request->to_date);
        } else {
            // Default: current week
            $start = Carbon::now()->subWeek()->startOfWeek();  // Monday
            $end   = Carbon::now()->subWeek()->endOfWeek();    // Sunday
        }

        // ============================================================
        //  FETCH ATTENDANCE RECORDS WITH OPTIONAL DEPARTMENT FILTER
        // ============================================================
        $attendanceQuery = DB::table('hrms_attendances')
            ->join('tbluser', 'hrms_attendances.user_id', '=', 'tbluser.id')
            ->where('hrms_attendances.sub_institute_id', $subInstituteId)
            ->whereBetween('hrms_attendances.day', [
                $start->format('Y-m-d'),
                $end->format('Y-m-d')
            ]);

        // APPLY DEPARTMENT FILTER ONLY IF PASSED IN REQUEST
        if ($request->filled('department_id')) {
            $attendanceQuery->where('tbluser.department_id', $departmentId);
        }

        $attendance = $attendanceQuery
            ->select('hrms_attendances.*', 'tbluser.first_name', 'tbluser.monday_in_date', 'tbluser.department_id')
            ->orderBy('hrms_attendances.day', 'ASC')
            ->get();

        // Labels
        $labels = ["Mon","Tue","Wed","Thu","Fri","Sat"];

        $present = [];
        $absent  = [];
        $late    = [];
        $dailyPunchData = [];

        // ============================================================
        //  TOTAL USERS (ALSO FILTER BY DEPARTMENT WHEN SELECTED)
        // ============================================================
        $userQuery = DB::table('tbluser')
            ->where('sub_institute_id', $subInstituteId)
            ->where('status', 1)
            ->whereNull('terminated_date');

        // apply filter only when department_id is provided
        if ($request->filled('department_id')) {
            $userQuery->where('department_id', $departmentId);
        }

        $totalUsers = $userQuery->count();

        // ============================================================
        //  FOR EACH DAY — CALCULATE PRESENT, ABSENT, LATE
        // ============================================================
        foreach ($labels as $index => $dayName) {

            $dayDate = $start->copy()->addDays($index)->format('Y-m-d');

            $dayRecords = $attendance->where('day', $dayDate);

            // Present is when both entries exist
            $presentCount = $dayRecords
                ->whereNotNull('punchin_time')
                ->whereNotNull('punchout_time')
                ->count();

            // Late logic
            $lateCount = $dayRecords->filter(function ($rec) {
                if ($rec->punchin_time && $rec->monday_in_date) {
                    $punch = Carbon::parse($rec->punchin_time)->format('H:i:s');
                    return $punch > $rec->monday_in_date;
                }
                return false;
            })->count();

            // Absent = total - present
            $absentCount = $totalUsers - $presentCount;

            // Punch time list
            $punchTimes = [];
            foreach ($dayRecords as $rec) {

                if ($rec->punchin_time && $rec->punchout_time) {
                    // Present → punch-in
                    $punchTimes[] = [
                        "employee_id" => $rec->user_id,
                        "day"         => $rec->day,
                        "type"        => "present",
                        "time"        => $rec->punchin_time,
                    ];
                } elseif (!$rec->punchin_time) {
                    // Absent
                    $punchTimes[] = [
                        "employee_id" => $rec->user_id,
                        "day"         => $rec->day,
                        "type"        => "absent",
                        "time"        => null,
                    ];
                } else {
                    // Incomplete
                    $punchTimes[] = [
                        "employee_id" => $rec->user_id,
                        "day"         => $rec->day,
                        "type"        => "incomplete",
                        "time"        => $rec->punchin_time,
                    ];
                }
            }

            // Percent values
            $present[] = $totalUsers ? round(($presentCount / $totalUsers) * 100, 2) : 0;
            $absent[]  = $totalUsers ? round(($absentCount / $totalUsers) * 100, 2) : 0;
            $late[]    = $totalUsers ? round(($lateCount / $totalUsers) * 100, 2) : 0;

            // Save punch details
            $dailyPunchData[$dayName] = $punchTimes;
        }

        // ============================================================
        //     FINAL API RESPONSE
        // ============================================================
        return response()->json([
            "date_range" => [
                "start" => $start->format('Y-m-d'),
                "end"   => $end->format('Y-m-d')
            ],
            "department_filter" => $departmentId ?? "All",  // just for info
            "labels"      => $labels,
            "present"     => $present,
            "absent"      => $absent,
            "late"        => $late,
            "punch_times" => $dailyPunchData
        ]);

    }

    public function KPI(Request $request)
    {
        $type = $request->input('type');

        // ============================================================
        // TOKEN VALIDATION (ONLY FOR API TYPE)
        // ============================================================
        if ($type === "API") {
            $token = $request->input('token');

            if (!$token) {
                return response()->json(['message' => 'Token not provided'], 401);
            }

            if (!$this->isValidJwt($request)) {
                return response()->json(['message' => 'Invalid token'], 401);
            }
        }

        $subInstituteId = $this->apiTenantId($request);
        $departmentId   = $request->department_id;

        if (!$subInstituteId) {
            return response()->json(['error' => 'sub_institute_id is required'], 400);
        }

        $today = Carbon::today()->format('Y-m-d');

        // ============================================================
        // TOTAL USERS (OPTIONAL DEPARTMENT FILTER)
        // ============================================================
        $userQuery = DB::table('tbluser')
            ->where('sub_institute_id', $subInstituteId);

        if ($request->filled('department_id')) {
            $userQuery->where('department_id', $departmentId);
        }

        $totalUsers = $userQuery->count();

        // ============================================================
        // PRESENT EMPLOYEES TODAY
        // ============================================================
        $attendanceQuery = DB::table('hrms_attendances')
            ->join('tbluser', 'hrms_attendances.user_id', '=', 'tbluser.id')
            ->where('hrms_attendances.sub_institute_id', $subInstituteId)
            ->where('hrms_attendances.day', $today)
            ->where('hrms_attendances.status', 1);

        if ($request->filled('department_id')) {
            $attendanceQuery->where('tbluser.department_id', $departmentId);
        }

        $presentCount = $attendanceQuery
            ->distinct()
            ->count('hrms_attendances.user_id');

        $presentPercentage = $totalUsers
            ? round(($presentCount / $totalUsers) * 100, 1)
            : 0;

        // ============================================================
        // LEAVE UTILIZATION
        // ============================================================
        $currentYear = Carbon::now()->year;
        $startDate   = $currentYear . '-04-01';
        $endDate     = ($currentYear + 1) . '-03-31';

        $leaveQuery = DB::table('hrms_emp_leaves')
            ->join('tbluser', 'hrms_emp_leaves.user_id', '=', 'tbluser.id')
            ->where('hrms_emp_leaves.sub_institute_id', $subInstituteId)
            ->where('hrms_emp_leaves.status', 'approved')
            ->where('hrms_emp_leaves.from_date', '>=', $startDate)
            ->where('hrms_emp_leaves.to_date', '<=', $endDate);

        if ($request->filled('department_id')) {
            $leaveQuery->where('tbluser.department_id', $departmentId);
        }

        $totalLeaveDaysTaken = $leaveQuery
            ->selectRaw('SUM((DATEDIFF(to_date, from_date) + 1) * day_type) as total_days')
            ->value('total_days') ?? 0;

        // ASSUMING 30 LEAVE DAYS PER EMPLOYEE PER YEAR
        $totalAllocatedDays = $totalUsers * 30;

        $leaveUtilization = $totalAllocatedDays
            ? round(($totalLeaveDaysTaken / $totalAllocatedDays) * 100, 1)
            : 0;

        // ============================================================
        // FINAL RESPONSE
        // ============================================================
        return response()->json([
            "present_today"     => $presentPercentage . "%",
            "leave_utilization" => $leaveUtilization . "%",
            "active_employees"  => $totalUsers
        ]);
    }

    /**
     * Employee Attendance Report - User ID + Month Wise
     * Perfect for Flutter integration
     */
    public function employeeMonthlyReport(Request $request)
    {
        $type = $request->input('type');

        // Token validation (same pattern as other APIs)
        if ($type === "API") {
            $token = $request->input('token');

            if (!$token) {
                return response()->json(['message' => 'Token not provided'], 401);
            }

            if (!$this->isValidJwt($request)) {
                return response()->json(['message' => 'Invalid token'], 401);
            }
        }

        $subInstituteId = $this->apiTenantId($request);
        $userId         = $request->user_id;
        $month          = $request->month; // Expected format: YYYY-MM

        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|integer',
            'user_id'          => 'required|integer',
            'month'            => 'required|date_format:Y-m',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 0,
                'message' => $validator->errors()->first()
            ], 422);
        }

        // Month range
        $startOfMonth = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $endOfMonth   = Carbon::createFromFormat('Y-m', $month)->endOfMonth();

        // Employee info
        $employee = DB::table('tbluser')
            ->select('id', 'first_name', 'middle_name', 'last_name', 'employee_id', 'department_id', 'jobtitle_id',
                     'monday_in_date', 'tuesday_in_date', 'wednesday_in_date', 'thursday_in_date',
                     'friday_in_date', 'saturday_in_date', 'sunday_in_date',
                     'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday')
            ->where('id', $userId)
            ->where('sub_institute_id', $subInstituteId)
            ->where('status', 1)
            ->first();

        if (!$employee) {
            return response()->json(['message' => 'Employee not found'], 404);
        }

        // Fetch attendance for the month
        $attendanceRecords = DB::table('hrms_attendances')
            ->where('user_id', $userId)
            ->where('sub_institute_id', $subInstituteId)
            ->whereBetween('day', [$startOfMonth->format('Y-m-d'), $endOfMonth->format('Y-m-d')])
            ->get()
            ->keyBy('day');

        // Fetch approved leaves
        $leaves = DB::table('hrms_emp_leaves as hel')
            ->leftJoin('hrms_leave_types as hlt', 'hlt.id', '=', 'hel.leave_type_id')
            ->where('hel.user_id', $userId)
            ->where('hel.sub_institute_id', $subInstituteId)
            ->where('hel.status', 'approved')
            ->where(function ($q) use ($startOfMonth, $endOfMonth) {
                $q->whereBetween('hel.from_date', [$startOfMonth, $endOfMonth])
                  ->orWhereBetween('hel.to_date', [$startOfMonth, $endOfMonth]);
            })
            ->select('hel.*', 'hlt.leave_type as leave_type_name')
            ->get();

        // Build leave map (date => leave info)
        $leaveMap = [];
        foreach ($leaves as $leave) {
            $from = Carbon::parse($leave->from_date);
            $to   = Carbon::parse($leave->to_date);
            for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
                if ($date->between($startOfMonth, $endOfMonth)) {
                    $leaveMap[$date->format('Y-m-d')] = [
                        'leave_id'        => $leave->id,
                        'leave_type'      => $leave->leave_type_name,
                        'day_type'        => $leave->day_type, // 1 = full, 0.5 = half
                        'reason'          => $leave->reason,
                    ];
                }
            }
        }

        // Fetch holidays
        $holidays = DB::table('hrms_holidays')
            ->where('sub_institute_id', $subInstituteId)
            ->where(function ($q) use ($startOfMonth, $endOfMonth) {
                $q->whereBetween('from_date', [$startOfMonth, $endOfMonth])
                  ->orWhereBetween('to_date', [$startOfMonth, $endOfMonth]);
            })
            ->get();

        $holidayMap = [];
        foreach ($holidays as $holiday) {
            $from = Carbon::parse($holiday->from_date);
            $to   = Carbon::parse($holiday->to_date);
            for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
                if ($date->between($startOfMonth, $endOfMonth)) {
                    $holidayMap[$date->format('Y-m-d')] = $holiday->holiday_name ?? 'Holiday';
                }
            }
        }

        // Build daily report
        $dailyReport = [];
        $presentCount = 0;
        $absentCount  = 0;
        $lateCount    = 0;
        $leaveCount   = 0;
        $holidayCount = 0;
        $weekendCount = 0;

        $current = $startOfMonth->copy();
        while ($current->lte($endOfMonth)) {
            $dateStr   = $current->format('Y-m-d');
            $dayName   = $current->format('l'); // Monday, Tuesday...
            $dayLower  = strtolower($dayName);  // sunday, monday etc.
            $dayKey    = $dayLower . '_in_date';

            $shiftTime = $employee->$dayKey ?? null;

            // Check if this day is a working day for the employee (0 = off / weekend)
            $isWorkingDay = isset($employee->$dayLower) ? (int)$employee->$dayLower : 1;

            $record = $attendanceRecords->get($dateStr);

            $status       = 'absent';
            $punchIn      = null;
            $punchOut     = null;
            $workingHours = null;
            $isLate       = false;
            $leaveInfo    = $leaveMap[$dateStr] ?? null;
            $holidayName  = $holidayMap[$dateStr] ?? null;

            if ($holidayName) {
                $status = 'holiday';
                $holidayCount++;
            } elseif ($leaveInfo) {
                $status = 'leave';
                $leaveCount += $leaveInfo['day_type'] ?? 1;
            } elseif ($isWorkingDay === 0) {
                // Off day / Weekend (Sunday etc.)
                $status = 'weekend';
                $weekendCount++;
            } elseif ($record) {
                $punchIn  = $record->punchin_time  ? Carbon::parse($record->punchin_time)->format('H:i:s')  : null;
                $punchOut = $record->punchout_time ? Carbon::parse($record->punchout_time)->format('H:i:s') : null;

                if ($punchIn && $punchOut) {
                    $status = 'present';
                    $presentCount++;

                    // Working hours
                    $diffMinutes = Carbon::parse($record->punchout_time)->diffInMinutes(Carbon::parse($record->punchin_time));
                    $hours = floor($diffMinutes / 60);
                    $mins  = $diffMinutes % 60;
                    $workingHours = sprintf('%02d:%02d', $hours, $mins);

                    // Late check
                    if ($shiftTime && $punchIn > $shiftTime) {
                        $isLate = true;
                        $lateCount++;
                    }
                } elseif ($punchIn) {
                    $status = 'incomplete';
                }
            } else {
                // Working day but no attendance record → Absent
                $status = 'absent';
                $absentCount++;
            }

            $dailyReport[] = [
                'date'           => $dateStr,
                'day_name'       => $dayName,
                'status'         => $status,
                'punchin_time'   => $punchIn,
                'punchout_time'  => $punchOut,
                'working_hours'  => $workingHours,
                'is_late'        => $isLate,
                'shift_time'     => $shiftTime,
                'leave'          => $leaveInfo,
                'holiday_name'   => $holidayName,
            ];

            $current->addDay();
        }

        $totalDays     = $startOfMonth->daysInMonth;
        $scheduledWorkingDays = $totalDays - $weekendCount - $holidayCount;

        return response()->json([
            'status'  => 1,
            'message' => 'Success',
            'data'    => [
                'employee' => [
                    'id'           => $employee->id,
                    'name'         => trim(($employee->first_name ?? '') . ' ' . ($employee->middle_name ?? '') . ' ' . ($employee->last_name ?? '')),
                    'employee_id'  => $employee->employee_id,
                ],
                'month'    => $month,
                'summary'  => [
                    'total_days'      => $totalDays,
                    'present_days'    => $presentCount,
                    'absent_days'     => $absentCount,
                    'leave_days'      => $leaveCount,
                    'holiday_days'    => $holidayCount,
                    'late_days'       => $lateCount,
                    'weekend_days'    => $weekendCount,
                    'working_days'    => max(0, $scheduledWorkingDays),
                ],
                'daily_report' => $dailyReport
            ]
        ]);
    }
}
