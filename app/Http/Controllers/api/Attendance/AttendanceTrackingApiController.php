<?php

namespace App\Http\Controllers\api\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Controllers\api\Attendance\Concerns\ResolvesAttendanceContext;
use App\Models\HrmsAttendance;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Attendance self service API backing the Next.js Attendance Tracking screen.
 *
 * The legacy flow is HrmsController::hrmsAttendance (formType=MyAttendance),
 * ::hrmsAttendanceInTimeStore and ::hrmsAttendanceOutTimeStore on the stateful
 * `hrms-attendance*` web routes. Those keep working unchanged for the Blade
 * screens and the mobile app; this controller is the stateless API variant and
 * adds the calendar window, the resolved per day status (present / late /
 * leave / absent / holiday / week-off) and JSON punch responses.
 */
class AttendanceTrackingApiController extends Controller
{
    use ResolvesAttendanceContext;

    private const WEEKDAY_NAMES = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

    /**
     * GET /api/attendance/my-attendance
     *
     * Punch rows for the requested window plus a day by day calendar and the
     * period totals. Defaults to the running month when no range is given.
     */
    public function myAttendance(Request $request)
    {
        $context = $this->attendanceContext($request);

        if (!is_array($context)) {
            return $context;
        }

        $subInstituteId = $context['sub_institute_id'];
        $userId = $context['user_id'];

        if (!$userId) {
            return response()->json(['status' => 0, 'message' => 'user_id is required'], 400);
        }

        $today = Carbon::now()->startOfDay();

        // Calendar window. Falls back to the current month so callers that omit
        // the range keep getting the running month.
        $fromDate = $request->filled('from_date')
            ? Carbon::parse($request->input('from_date'))->startOfDay()
            : $today->copy()->startOfMonth();
        $toDate = $request->filled('to_date')
            ? Carbon::parse($request->input('to_date'))->startOfDay()
            : $today->copy()->endOfMonth();

        if ($toDate->lt($fromDate)) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        $rangeStart = $fromDate->format('Y-m-d');
        $rangeEnd = $toDate->format('Y-m-d');

        $employee = DB::table('tbluser')->where('id', $userId)->first();
        $leaveDates = $this->leaveDates($userId, $subInstituteId, $rangeStart, $rangeEnd);
        $holidayDates = $this->holidayDates($subInstituteId, $employee->department_id ?? null, $rangeStart, $rangeEnd);

        $attendanceData = HrmsAttendance::with([
            'getUser' => function ($query) {
                $query->select(
                    'tbluser.id',
                    DB::raw('CONCAT_WS(" ", COALESCE(first_name,"-"), COALESCE(middle_name,"-"), COALESCE(last_name,"-")) as employee_name')
                );
            },
            'getDepartment' => function ($query) {
                $query->select(
                    'hrms_departments.id',
                    'department'
                );
            },
        ])
            ->where([['user_id', $userId], ['sub_institute_id', $subInstituteId], ['status', 1]])
            ->whereNull('deleted_at')
            ->whereBetween('day', [$rangeStart, $rangeEnd])
            ->orderBy('day')
            ->get()
            ->map(function ($item) use ($employee) {
                $item->employee_name = $item->getUser ? $item->getUser->employee_name : '';
                $item->department = $item->getDepartment ? $item->getDepartment->department : '';
                unset($item->getUser);
                unset($item->getDepartment);
                $item->attendance_status = $this->punchStatus($item, $employee);

                return $item;
            });

        $punchedDays = [];
        foreach ($attendanceData as $entry) {
            $punchedDays[Carbon::parse($entry->day)->format('Y-m-d')] = $entry;
        }

        // A day is scheduled from the employee's weekday roster. When no roster
        // is configured, fall back to "every day except Sunday", which is what
        // the department-wise report assumes.
        $hasRoster = false;
        foreach (self::WEEKDAY_NAMES as $weekdayName) {
            if (!empty($employee->{$weekdayName})) {
                $hasRoster = true;
                break;
            }
        }

        $calendar = [];
        $totalDays = $presentDays = $lateDays = $leaveDays = $absentDays = $holidayCount = $workingDays = 0;

        for ($date = $fromDate->copy(); $date->lte($toDate); $date->addDay()) {
            $totalDays++;
            $key = $date->format('Y-m-d');
            $dayName = strtolower($date->format('l'));
            $isWorkingDay = $hasRoster ? !empty($employee->{$dayName}) : !$date->isSunday();
            $isHoliday = isset($holidayDates[$key]);
            $status = null;

            if (isset($punchedDays[$key])) {
                $status = $punchedDays[$key]->attendance_status;
            } elseif (isset($leaveDates[$key])) {
                $status = 'leave';
            } elseif ($isWorkingDay && !$isHoliday && $date->lt($today)) {
                // Only elapsed scheduled days can be marked absent. Today is
                // still open, and future days, holidays and week-offs stay
                // unmarked.
                $status = 'absent';
            }

            if ($isWorkingDay && !$isHoliday) {
                $workingDays++;
            }
            if ($isHoliday) {
                $holidayCount++;
            }

            switch ($status) {
                case 'present':
                    $presentDays++;
                    break;
                case 'late':
                    $lateDays++;
                    break;
                case 'leave':
                    $leaveDays++;
                    break;
                case 'absent':
                    $absentDays++;
                    break;
            }

            $calendar[] = [
                'date' => $key,
                'day_name' => $date->format('l'),
                'status' => $status,
                'is_working_day' => $isWorkingDay,
                'is_holiday' => $isHoliday,
                'holiday_name' => $isHoliday ? $holidayDates[$key] : null,
                'leave_type' => $leaveDates[$key]['leave_type'] ?? null,
                'day_type' => $leaveDates[$key]['day_type'] ?? null,
                'punchin_time' => isset($punchedDays[$key]) ? $punchedDays[$key]->punchin_time : null,
                'punchout_time' => isset($punchedDays[$key]) ? $punchedDays[$key]->punchout_time : null,
                'timestamp_diff' => isset($punchedDays[$key]) ? $punchedDays[$key]->timestamp_diff : null,
            ];
        }

        return response()->json([
            'status' => 1,
            'message' => 'Success to Find Data',
            'fromDate' => $rangeStart,
            'toDate' => $rangeEnd,
            'daysInMonth' => $totalDays,
            'workingDays' => $workingDays,
            'holidays' => $holidayCount,
            'presentDays' => $presentDays,
            'lateDays' => $lateDays,
            'leaveDays' => $leaveDays,
            'absentDays' => $absentDays,
            'percentege' => $workingDays > 0
                ? round((($presentDays + $lateDays) / $workingDays) * 100, 2)
                : 0,
            'calendar' => $calendar,
            'attendanceData' => $attendanceData,
        ]);
    }

    /**
     * POST /api/attendance/punch-in
     *
     * Re-punching the same day resets the out side of the row so the day does
     * not keep a stale punch out / duration from the previous punch.
     */
    public function punchIn(Request $request)
    {
        $context = $this->attendanceContext($request);

        if (!is_array($context)) {
            return $context;
        }

        $validator = Validator::make($request->all(), [
            'employee' => 'required',
            'indate'   => 'required',
            'intime'   => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 0, 'message' => $validator->errors()->first()], 422);
        }

        $subInstituteId = $context['sub_institute_id'];
        $formattedDate = Carbon::parse($request->input('indate'))->format('Y-m-d');

        $record = HrmsAttendance::where('user_id', $request->input('employee'))
            ->where('sub_institute_id', $subInstituteId)
            ->whereDate('day', $formattedDate)
            ->first();

        if ($record) {
            $record->punchin_time = Carbon::parse($formattedDate . ' ' . $request->input('intime'))->format('Y-m-d H:i:s');
            $record->punchout_time = null;
            $record->timestamp_diff = null;
            $record->ipaddress_in = $request->ip();
            $record->ipaddress_out = null;
            $record->in_note = 1;
            $record->out_note = 0;
            $record->save();
        } else {
            $record = new HrmsAttendance();
            $record->user_id = $request->input('employee');
            $record->punchin_time = Carbon::parse($formattedDate . ' ' . $request->input('intime'))->format('Y-m-d H:i:s');
            $record->day = $formattedDate;
            $record->in_note = 1;
            $record->ipaddress_in = $request->ip();
            $record->sub_institute_id = $subInstituteId;
            $record->save();
        }

        return response()->json([
            'status' => 1,
            'message' => 'Attendance punch in saved successfully',
            'attendanceData' => $record,
        ]);
    }

    /**
     * POST /api/attendance/punch-out
     */
    public function punchOut(Request $request)
    {
        $context = $this->attendanceContext($request);

        if (!is_array($context)) {
            return $context;
        }

        $validator = Validator::make($request->all(), [
            'employee' => 'required',
            'outdate'  => 'required',
            'outtime'  => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 0, 'message' => $validator->errors()->first()], 422);
        }

        $subInstituteId = $context['sub_institute_id'];
        $dateOnly = Carbon::parse($request->input('outdate'))->format('Y-m-d');
        $punchoutTime = $request->input('outtime')
            ? Carbon::parse($request->input('outdate') . ' ' . $request->input('outtime'))
            : null;

        // Either the open row for the day, or the latest one when a time was sent.
        $attendance = HrmsAttendance::where('user_id', $request->input('employee'))
            ->where('sub_institute_id', $subInstituteId)
            ->where('day', $dateOnly)
            ->when(!$punchoutTime, function ($query) {
                return $query->whereNull('punchout_time');
            }, function ($query) {
                return $query->orderBy('id', 'desc');
            })
            ->first();

        if ($attendance) {
            $attendance->punchout_time = $punchoutTime?->format('Y-m-d H:i:s');
            $attendance->ipaddress_out = $request->ip();
            $attendance->out_note = 1;

            if ($punchoutTime && $attendance->punchin_time) {
                $attendance->timestamp_diff = $this->durationBetween(
                    Carbon::parse($attendance->punchin_time),
                    $punchoutTime
                );
            }

            $attendance->save();
        } else {
            // Already punched out before - overwrite with the current time.
            $existingRecord = HrmsAttendance::where([
                ['user_id', $request->input('employee')],
                ['sub_institute_id', $subInstituteId],
                ['day', $dateOnly],
            ])->orderBy('id', 'desc')->first();

            if ($existingRecord && $existingRecord->punchin_time) {
                $now = Carbon::now();

                $existingRecord->punchout_time = ($request->input('outtime') == '') ? null : $now->format('Y-m-d H:i:s');
                $existingRecord->ipaddress_out = $request->ip();
                $existingRecord->out_note = 1;
                $existingRecord->timestamp_diff = $this->durationBetween(
                    Carbon::parse($existingRecord->punchin_time),
                    $now
                );
                $existingRecord->save();

                $attendance = $existingRecord;
            }
        }

        if (!$attendance) {
            return response()->json([
                'status' => 0,
                'message' => 'No punch in record found for this employee and date',
            ], 404);
        }

        return response()->json([
            'status' => 1,
            'message' => 'Attendance punch out saved successfully',
            'attendanceData' => $attendance,
        ]);
    }

    /** Absolute difference between two punches as HH:MM. */
    private function durationBetween(Carbon $punchIn, Carbon $punchOut): string
    {
        $min = (int) $punchOut->diffInMinutes($punchIn, true);

        return sprintf('%02d:%02d', floor($min / 60), $min % 60);
    }

    /**
     * Approved leave days for an employee, keyed by Y-m-d.
     */
    private function leaveDates($userId, $subInstituteId, $rangeStart, $rangeEnd): array
    {
        $leaves = DB::table('hrms_emp_leaves as hel')
            ->leftJoin('hrms_leave_types as hlt', 'hlt.id', '=', 'hel.leave_type_id')
            ->select('hel.from_date', 'hel.to_date', 'hel.day_type', 'hlt.leave_type')
            ->where('hel.user_id', $userId)
            ->where('hel.sub_institute_id', $subInstituteId)
            ->where('hel.status', 'approved')
            ->whereNull('hel.deleted_at')
            ->where('hel.from_date', '<=', $rangeEnd)
            ->where('hel.to_date', '>=', $rangeStart)
            ->get();

        $leaveDates = [];

        foreach ($leaves as $leave) {
            if (empty($leave->from_date)) {
                continue;
            }

            $start = Carbon::parse($leave->from_date)->startOfDay();
            $end = Carbon::parse($leave->to_date ?: $leave->from_date)->startOfDay();

            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                $leaveDates[$date->format('Y-m-d')] = [
                    'leave_type' => $leave->leave_type ?? null,
                    'day_type' => $leave->day_type ?? null,
                ];
            }
        }

        return $leaveDates;
    }

    /**
     * Holiday days that apply to a department, keyed by Y-m-d => holiday name.
     */
    private function holidayDates($subInstituteId, $departmentId, $rangeStart, $rangeEnd): array
    {
        $holidays = DB::table('hrms_holidays')
            ->where('sub_institute_id', $subInstituteId)
            ->whereNull('deleted_at')
            ->where('from_date', '<=', $rangeEnd)
            ->where('to_date', '>=', $rangeStart)
            ->when($departmentId, function ($q) use ($departmentId) {
                // A holiday with no department applies institute-wide.
                $q->where(function ($q) use ($departmentId) {
                    $q->whereNull('department')
                        ->orWhere('department', '')
                        ->orWhere('department', '0')
                        ->orWhereRaw('FIND_IN_SET(?, department)', [$departmentId]);
                });
            })
            ->get();

        $holidayDates = [];

        foreach ($holidays as $holiday) {
            if (empty($holiday->from_date)) {
                continue;
            }

            $start = Carbon::parse($holiday->from_date)->startOfDay();
            $end = Carbon::parse($holiday->to_date ?: $holiday->from_date)->startOfDay();

            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                $holidayDates[$date->format('Y-m-d')] = $holiday->holiday_name ?: 'Holiday';
            }
        }

        return $holidayDates;
    }

    /**
     * Resolve a punched day as "late" or "present" against the employee's
     * rostered start time for that weekday.
     */
    private function punchStatus($attendance, $employee): ?string
    {
        if (empty($attendance->punchin_time)) {
            return null;
        }

        $dayName = strtolower(Carbon::parse($attendance->day)->format('l'));
        $shiftStart = $employee->{$dayName . '_in_date'} ?? null;

        if (empty($shiftStart)) {
            return 'present';
        }

        try {
            $punchIn = Carbon::parse($attendance->punchin_time);
            $expected = Carbon::parse($attendance->day . ' ' . Carbon::parse($shiftStart)->format('H:i:s'));
        } catch (\Exception $e) {
            return 'present';
        }

        return $punchIn->gt($expected) ? 'late' : 'present';
    }
}
