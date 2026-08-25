<?php

namespace App\Services\Leave;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use function App\Helpers\countDays;

/**
 * Single home for the Leave Management business rules that were previously
 * duplicated across LeaveSummaryReportController, LeaveReportController and
 * LeaveAuthorisationController:
 *
 *  - the leave year is April 1 {Y} to March 31 {Y+1}
 *  - entitlement comes from hrms_leave_allocation, employee rows overriding
 *    department rows for Casual (LTY001) and Earned (LTY009) leave, and only
 *    while the employee is still inside probation_period_to
 *  - 'approved' and 'pending' consume balance; 'approved_lwp' accrues into a
 *    synthetic "Leave Without Pay" bucket instead
 *  - entitlement is capped at 180.00 days
 */
class LeaveAnalyticsService
{
    public const MAX_OPENING_LEAVE = 180.00;
    public const LWP_BUCKET = 'Leave Without Pay';

    /** Statuses that draw down the balance. */
    public const CONSUMING_STATUSES = ['approved', 'pending'];

    /**
     * The leave year window for $subInstituteId's $year.
     *
     * G2G's original logic always assumed a literal April {Y} - March {Y+1}
     * calendar window derived purely from the syear number. That breaks for
     * institutes whose `academic_year` rows don't follow a normal 12-month
     * cycle (e.g. a syear whose real start_date/end_date spans several
     * calendar years) - leave requests dated well inside the institute's
     * actual current academic year silently vanished from every leave list,
     * because they fell outside the hardcoded April-March math.
     *
     * Prefer the institute's real academic_year date range for this syear
     * (MIN(start_date)..MAX(end_date) across all its terms, so a leave
     * anywhere in any term of the year is included); fall back to the
     * original April-March calculation only when no academic_year rows
     * exist for this sub_institute_id/syear (e.g. a syear that was never a
     * real academic term).
     */
    public function leaveYearBounds(int $subInstituteId, int $year): array
    {
        $bounds = DB::table('academic_year')
            ->where('sub_institute_id', $subInstituteId)
            ->where('syear', $year)
            ->selectRaw('MIN(start_date) as from_date, MAX(end_date) as to_date')
            ->first();

        if ($bounds && $bounds->from_date && $bounds->to_date) {
            return [$bounds->from_date, $bounds->to_date];
        }

        return [$year . '-04-01', ($year + 1) . '-03-31'];
    }

    /** Active leave types for the institute, in display order. */
    public function leaveTypes(int $subInstituteId)
    {
        return DB::table('hrms_leave_types')
            ->where('sub_institute_id', $subInstituteId)
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Resolve the numeric primary key of a leave type from its LTY code.
     * The legacy report hardcoded 1 and 9; falling back to those keeps parity
     * on the standard dataset without breaking institutes that differ.
     */
    public function leaveTypeIdByCode(int $subInstituteId, string $code, int $fallback): int
    {
        $row = DB::table('hrms_leave_types')
            ->where('sub_institute_id', $subInstituteId)
            ->where('leave_type_id', $code)
            ->whereNull('deleted_at')
            ->first();

        return (int) ($row->id ?? $fallback);
    }

    /**
     * Days consumed per leave type, per employee, inside the leave year.
     *
     * @return array<string, array<int, float>> [leave_type_name][user_id] => days
     */
    public function consumedByType(int $subInstituteId, int $year, ?int $departmentId = null, ?int $employeeId = null): array
    {
        [$from, $to] = $this->leaveYearBounds($subInstituteId, $year);

        $rows = DB::table('hrms_emp_leaves as hel')
            ->join('tbluser as u', 'u.id', '=', 'hel.user_id')
            ->leftJoin('hrms_leave_types as hlt', function ($join) use ($subInstituteId) {
                $join->on('hlt.id', '=', 'hel.leave_type_id')
                     ->where('hlt.sub_institute_id', '=', $subInstituteId);
            })
            ->selectRaw('hel.user_id, hel.from_date, hel.to_date, hel.day_type, hel.status, hlt.leave_type')
            ->where('hel.sub_institute_id', $subInstituteId)
            ->whereNull('hel.deleted_at')
            ->where('u.status', 1)
            ->where('hel.from_date', '>=', $from)
            ->where('hel.to_date', '<=', $to)
            ->whereIn('hel.status', array_merge(self::CONSUMING_STATUSES, ['approved_lwp']))
            ->when($departmentId, fn ($q) => $q->where('u.department_id', $departmentId))
            ->when($employeeId, fn ($q) => $q->where('hel.user_id', $employeeId))
            ->get();

        $consumed = [];

        foreach ($rows as $row) {
            $bucket = $row->status === 'approved_lwp'
                ? self::LWP_BUCKET
                : ($row->leave_type ?: 'Unassigned');

            $days = (float) countDays($row->from_date, $row->to_date ?: $row->from_date, $row->day_type ?: 1, '');

            $userId = (int) $row->user_id;
            $consumed[$bucket][$userId] = round(($consumed[$bucket][$userId] ?? 0) + $days, 2);
        }

        return $consumed;
    }

    /**
     * Entitlement per leave type, per employee, for the leave year.
     *
     * @return array<string, array<int, float>> [leave_type_name][user_id] => days
     */
    public function entitlementByType(int $subInstituteId, int $year, ?int $departmentId = null, ?int $employeeId = null): array
    {
        $leaveTypes = $this->leaveTypes($subInstituteId);

        if ($leaveTypes->isEmpty()) {
            return [];
        }

        $employees = DB::table('tbluser')
            ->select('id', 'department_id', 'probation_period_to')
            ->where('sub_institute_id', $subInstituteId)
            ->where('status', 1)
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->when($employeeId, fn ($q) => $q->where('id', $employeeId))
            ->get();

        if ($employees->isEmpty()) {
            return [];
        }

        $earnedTypeId = $this->leaveTypeIdByCode($subInstituteId, 'LTY009', 9);
        $casualTypeId = $this->leaveTypeIdByCode($subInstituteId, 'LTY001', 1);

        // One pass over the allocation table instead of 3 queries per employee per type.
        $allocations = DB::table('hrms_leave_allocation')
            ->where('sub_institute_id', $subInstituteId)
            ->where('year', $year)
            ->whereNull('deleted_at')
            ->get();

        $departmentAllocation = [];  // [leave_type_id][department_id] => value
        $employeeAllocation   = [];  // [leave_type_id][employee_id]   => value

        foreach ($allocations as $allocation) {
            $typeId = (int) $allocation->leave_type_id;

            if (empty($allocation->employee_id)) {
                $departmentAllocation[$typeId][(int) $allocation->department_id] = (float) $allocation->value;
            } else {
                $employeeAllocation[$typeId][(int) $allocation->employee_id] = (float) $allocation->value;
            }
        }

        $today = Carbon::today();
        $entitlement = [];

        foreach ($employees as $employee) {
            $userId       = (int) $employee->id;
            $deptId       = (int) ($employee->department_id ?? 0);
            $onProbation  = $employee->probation_period_to
                && Carbon::parse($employee->probation_period_to)->greaterThan($today);

            $employeeEarned = $employeeAllocation[$earnedTypeId][$userId] ?? 0;
            $employeeCasual = $employeeAllocation[$casualTypeId][$userId] ?? 0;

            foreach ($leaveTypes as $leaveType) {
                $typeId       = (int) $leaveType->id;
                $typeCode     = $leaveType->leave_type_id ?? '';
                $departmental = $departmentAllocation[$typeId][$deptId] ?? 0;

                $value = $departmental;

                if ($typeCode === 'LTY009') {
                    // Earned: employee allocation replaces the department grant during
                    // probation, and tops it up afterwards.
                    $value = ($employeeEarned > 0 && $onProbation)
                        ? $employeeEarned
                        : $departmental + $employeeEarned;
                }

                if ($typeCode === 'LTY001' && $employeeCasual > 0 && $onProbation) {
                    $value = $employeeCasual;
                }

                $entitlement[$leaveType->leave_type][$userId] = round(min($value, self::MAX_OPENING_LEAVE), 2);
            }
        }

        return $entitlement;
    }

    /**
     * Total / used / remaining per leave type for one employee.
     *
     * @return array{leave_types: array<int, array<string, mixed>>, overall: array<string, float>}
     */
    public function balancesForEmployee(int $subInstituteId, int $year, int $employeeId): array
    {
        $employee = DB::table('tbluser')->select('department_id')->where('id', $employeeId)->first();
        $departmentId = $employee->department_id ?? null;

        $entitlement = $this->entitlementByType($subInstituteId, $year, $departmentId ? (int) $departmentId : null, $employeeId);
        $consumed    = $this->consumedByType($subInstituteId, $year, null, $employeeId);

        $names = array_unique(array_merge(array_keys($entitlement), array_keys($consumed)));
        sort($names);

        $rows = [];
        $overallTotal = $overallUsed = 0.0;

        foreach ($names as $name) {
            $total = (float) ($entitlement[$name][$employeeId] ?? 0);
            $used  = (float) ($consumed[$name][$employeeId] ?? 0);
            // LWP has no entitlement, so it can never show a remaining balance.
            $remaining = $total > 0 ? round($total - $used, 2) : 0.0;

            $rows[] = [
                'leave_type' => $name,
                'total'      => round($total, 2),
                'used'       => round($used, 2),
                'remaining'  => $remaining,
            ];

            $overallTotal += $total;
            $overallUsed  += $used;
        }

        return [
            'leave_types' => $rows,
            'overall'     => [
                'total'     => round($overallTotal, 2),
                'used'      => round($overallUsed, 2),
                'remaining' => round(max($overallTotal - $overallUsed, 0), 2),
            ],
        ];
    }

    /** Base query for every request-list and aggregate endpoint. */
    public function requestsQuery(int $subInstituteId, int $year)
    {
        [$from, $to] = $this->leaveYearBounds($subInstituteId, $year);

        return DB::table('hrms_emp_leaves as hel')
            ->join('tbluser as u', 'u.id', '=', 'hel.user_id')
            ->leftJoin('hrms_leave_types as hlt', function ($join) use ($subInstituteId) {
                $join->on('hlt.id', '=', 'hel.leave_type_id')
                     ->where('hlt.sub_institute_id', '=', $subInstituteId);
            })
            ->leftJoin('hrms_departments as hd', 'hd.id', '=', 'u.department_id')
            ->leftJoin('hrms_job_titles as hjt', 'hjt.id', '=', 'u.jobtitle_id')
            ->where('hel.sub_institute_id', $subInstituteId)
            ->whereNull('hel.deleted_at')
            ->where('hel.from_date', '>=', $from)
            ->where('hel.to_date', '<=', $to);
    }

    /** Number of days a single request represents. */
    public function requestDays(?string $fromDate, ?string $toDate, $dayType): float
    {
        if (!$fromDate) {
            return 0.0;
        }

        return round((float) countDays($fromDate, $toDate ?: $fromDate, $dayType ?: 1, ''), 2);
    }

    /** "3 days" / "Half Day" for display. */
    public function durationLabel(float $days): string
    {
        if ($days <= 0) {
            return '—';
        }

        if ($days == 0.5) {
            return 'Half Day';
        }

        $formatted = fmod($days, 1) == 0 ? (string) (int) $days : (string) $days;

        return $formatted . ' day' . ($days > 1 ? 's' : '');
    }
}
