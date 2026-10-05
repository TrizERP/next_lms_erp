<?php

namespace App\Brain\Intelligence;

use App\Brain\Support\AcademicYear;
use App\Brain\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * HR & Staff Intelligence — one institute, one academic year.
 *
 * ── WHAT ONE ROW MEANS ──────────────────────────────────────────────────────
 *
 * One row of `tbluser` is ONE MEMBER OF STAFF — their role, their department,
 * their contact details and whether their account is still active.
 *
 * ── THREE THINGS THE PROFILER FOUND ─────────────────────────────────────────
 *
 * 1. ZERO WAS BEING REPORTED WHERE THERE IS NO REGISTER. The previous version
 *    showed "Total leaves: 0" and "Average gross salary: 0" at an institute
 *    that has never used either module. Those are not zeros — they are absences,
 *    and a principal reading "0 leaves" concludes their staff took none. Every
 *    such figure here is NULL, renders as an em dash, and coverage says why.
 *
 * 2. HEADCOUNT WAS BEING READ WITHOUT STATUS. One institute holds 556 staff
 *    rows of which 188 are active: reporting 556 as the staff body overstates
 *    it by two hundred people. Both figures are on the screen, and the
 *    breakdowns count the active ones.
 *
 * 3. `leave_applications` IS STUDENT LEAVE, NOT STAFF LEAVE. It carries
 *    `student_id` and is the largest leave-shaped table in the schema, which
 *    makes it exactly the sort of join a module picks up by name and gets
 *    wrong. Staff leave is `hrms_emp_leaves`, and nothing here reads the other.
 *
 * ── YEAR SCOPING ────────────────────────────────────────────────────────────
 *
 * Staff leave carries `from_date` and the punch register carries `day`; neither
 * has a `syear`. Both are scoped through {@see AcademicYear::window()}, which
 * reads the institute's OWN term dates — so a school whose year runs April to
 * March is asked about April to March. Where the institute has no term dates
 * for the year, the figure is null and says so rather than quietly counting
 * every year at once.
 *
 * ── WHAT IS DELIBERATELY NOT READ ───────────────────────────────────────────
 *
 * `tbluser` carries `password`, `plain_password`, `account_no`, `ifsc_code`,
 * `pan_no` and `aadhar_no`. None of them is read, aggregated or counted here.
 * No staffing question needs them, and an Intelligence payload is the last
 * place a bank account should be able to appear.
 *
 * `hrms_attendances` carries `ipaddress_in`, `ipaddress_out`, `photo_in` and
 * `photo_out` — the network address a member of staff punched from and a
 * photograph of them doing it. Nothing here reads any of the four.
 *
 * ── AND WHY THE PUNCH REGISTER IS ONLY EVER AGGREGATED ──────────────────────
 *
 * A per-person league table of who came in least is the most misusable thing
 * this database could put on a screen, and it would not even be true: approved
 * leave, a school trip and a split week are indistinguishable from absence in
 * this table. So every punch figure below is a COUNT, a MEDIAN or a BAND, the
 * breakdown groups by role, and no finding names an individual. The one
 * exception is the open-shift count, which is a payroll record to close rather
 * than a judgement about anybody.
 */
final class HrIntelligence
{
    private const USER_TABLE = 'tbluser';

    private const ROLE_TABLE = 'tbluserprofilemaster';

    private const DEPARTMENT_TABLE = 'hrms_departments';

    private const LEAVE_TABLE = 'hrms_emp_leaves';

    private const LEAVE_TYPE_TABLE = 'hrms_leave_types';

    private const PUNCH_TABLE = 'hrms_attendances';

    private const SALARY_TABLE = 'employee_monthly_salary_data';

    /** Below this, a role's leave or contact profile is about the individuals in it. */
    public const MIN_ROLE_COHORT = 5;

    /** Share of a cohort missing a contact route before it is worth naming. */
    public const CONTACT_GAP_SHARE = 10.0;

    /**
     * Below this many people on the punch register, it is a device or a gate
     * rather than an institute-wide record. One tenant profiled has a register
     * of exactly one person; reporting "median attendance 100%" from it would be
     * arithmetically true and completely meaningless.
     */
    public const MIN_PUNCH_REGISTER = 10;

    /**
     * A day counts as a WORKING DAY when at least this share of the register
     * punched on it.
     *
     * The register holds 359 distinct days across a 361-day year at the richest
     * institute, because security and boarding staff punch on Sundays too. A
     * denominator of 359 would mean every teacher looked absent a fifth of the
     * year. Counting only days the institute actually ran gives 262 — a Monday
     * to Saturday school year, which is what that institute works — and 265 at
     * the second institute profiled. The calendar is DERIVED FROM THE REGISTER
     * ITSELF, never from a weekday convention invented here.
     */
    public const WORKING_DAY_SHARE = 20.0;

    /** Days present below this share of the institute's own working days. */
    public const LOW_ATTENDANCE_SHARE = 70.0;

    private readonly string $tenantId;

    private readonly ?string $syear;

    /** @var array<string,mixed> */
    private array $memo = [];

    public function __construct(string $tenantId, ?string $syear)
    {
        $this->tenantId = (string) $tenantId;
        $this->syear = $syear === null || $syear === '' ? null : (string) $syear;
    }

    public function syear(): ?string
    {
        return $this->syear;
    }

    public function tenantId(): string
    {
        return $this->tenantId;
    }

    /** @return array{start:string,end:string}|null */
    public function yearWindow(): ?array
    {
        return $this->memo['window'] ??= AcademicYear::window($this->tenantId, $this->syear);
    }

    /* -------------------------------------------------------- L0: coverage */

    /** @return array<string,mixed> */
    public function coverage(): array
    {
        return $this->memo['coverage'] ??= $this->computeCoverage();
    }

    /** @return array<string,mixed> */
    private function computeCoverage(): array
    {
        $empty = ['sources' => [], 'counts' => []];

        if (! SchemaCache::hasTable(self::USER_TABLE)) {
            return $empty + [
                'available' => false,
                'reason' => "Table '".self::USER_TABLE."' does not exist in this deployment.",
            ];
        }

        $shape = DB::table(self::USER_TABLE)
            ->where('sub_institute_id', $this->tenantId)
            ->selectRaw(
                'COUNT(*) as staff,
                 SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active,
                 COUNT(DISTINCT NULLIF(user_profile_id, 0)) as roles,
                 COUNT(DISTINCT NULLIF(department_id, 0)) as departments'
            )
            ->first();

        $staff = (int) ($shape->staff ?? 0);

        if ($staff === 0) {
            return $empty + [
                'available' => false,
                'reason' => 'No staff records exist for this institute.',
            ];
        }

        $window = $this->yearWindow();
        $leaves = $this->leaveApplicationsThisYear();
        $punches = $this->punchRecordsThisYear();
        $payrollPeriods = $this->payrollPeriods();

        return [
            'available' => true,
            'reason' => null,
            'sources' => [
                'staff' => true,
                'roles' => (int) $shape->roles > 0,
                // Absent at every institute profiled: the column exists and is
                // never populated. Saying so is why the department breakdown can
                // be honestly omitted instead of drawn empty.
                'departments' => (int) $shape->departments > 0,
                'leaveRegister' => $leaves !== null && $leaves > 0,
                'punchRegister' => $punches !== null && $punches > 0,
                'payroll' => $payrollPeriods > 0,
                'yearWindow' => $window !== null,
            ],
            'counts' => [
                'staff' => $staff,
                'activeStaff' => (int) $shape->active,
                'roles' => (int) $shape->roles,
                'departments' => (int) $shape->departments,
                'leaveApplications' => $leaves ?? 0,
                'punchRecords' => $punches ?? 0,
                'payrollPeriods' => $payrollPeriods,
            ],
        ];
    }

    /* -------------------------------------------------------- L1: position */

    /** @return array<string,mixed>|null */
    public function position(): ?array
    {
        return $this->memo['position'] ??= $this->computePosition();
    }

    /** @return array<string,mixed>|null */
    private function computePosition(): ?array
    {
        $coverage = $this->coverage();
        if (! $coverage['available']) {
            return null;
        }

        $counts = $coverage['counts'];
        $gender = $this->genderSplit();
        $leave = $this->leaveProfile();
        $roles = $this->byRole();
        $teaching = 0;
        foreach ($roles as $role) {
            if ($role['teaching']) {
                $teaching += $role['staff'];
            }
        }

        $active = (int) $counts['activeStaff'];
        $contact = $this->contactCompleteness();
        $punch = $this->punchProfile();

        return [
            'staff' => (int) $counts['staff'],
            'activeStaff' => $active,
            'inactiveStaff' => (int) $counts['staff'] - $active,
            'roles' => (int) $counts['roles'],
            'teachingStaff' => $teaching,
            // NULL, NOT ZERO: with no active staff there is no denominator.
            'teachingShare' => $active > 0 ? round($teaching / $active * 100, 1) : null,
            'femaleShare' => $gender['femaleShare'],
            'maleShare' => $gender['maleShare'],
            'genderUnrecorded' => $gender['unrecorded'],
            // NULL when the institute does not keep a staff leave register at
            // all. "0 leave days" and "we do not record leave" are different
            // statements, and only one of them is about the staff.
            'leaveApplications' => $leave['applications'],
            'leaveDays' => $leave['days'],
            'staffTakingLeave' => $leave['staff'],
            'avgLeaveDaysPerStaff' => $leave['avgDaysPerStaff'],
            'leaveWithoutPayDays' => $leave['withoutPayDays'],
            'pendingLeave' => $leave['pending'],
            'missingContact' => $contact['missingBoth'],
            'payrollPeriods' => (int) $counts['payrollPeriods'] > 0 ? (int) $counts['payrollPeriods'] : null,
            // NULL throughout where the institute keeps no usable punch register.
            // An absent register is not a staff body that never came in, and the
            // coverage block carries the reason in its own words.
            'staffOnRegister' => $punch['staff'],
            'workingDays' => $punch['workingDays'],
            'medianDaysPresent' => $punch['medianDaysPresent'],
            'medianAttendance' => $punch['medianAttendance'],
            'openShifts' => $punch['openShifts'],
            'activeMissingFromRegister' => $punch['available'] ? $punch['activeMissingFromRegister'] : null,
            'inactiveOnRegister' => $punch['available'] ? $punch['inactiveOnRegister'] : null,
        ];
    }

    /* ---------------------------------------------------- L2: distribution */

    /**
     * Staff by role, with the role NAMED.
     *
     * @return array<int,array<string,mixed>>
     */
    public function byRole(): array
    {
        return $this->memo['byRole'] ??= (function (): array {
            if (! $this->coverage()['available']) {
                return [];
            }

            $hasMaster = SchemaCache::hasTable(self::ROLE_TABLE);

            $query = DB::table(self::USER_TABLE.' as u')
                ->where('u.sub_institute_id', $this->tenantId)
                ->where('u.status', 1);

            if ($hasMaster) {
                $query->leftJoin(self::ROLE_TABLE.' as p', 'p.id', '=', 'u.user_profile_id')
                    ->groupBy('u.user_profile_id', 'p.name')
                    ->select(
                        'u.user_profile_id',
                        DB::raw('NULLIF(p.name, "") as role_name'),
                        DB::raw('COUNT(*) as staff'),
                        DB::raw('SUM(CASE WHEN u.gender = "F" THEN 1 ELSE 0 END) as female'),
                        DB::raw('SUM(CASE WHEN u.gender = "M" THEN 1 ELSE 0 END) as male'),
                        DB::raw('SUM(CASE WHEN (u.email IS NULL OR u.email = "") AND (u.mobile IS NULL OR u.mobile = "") THEN 1 ELSE 0 END) as no_contact')
                    );
            } else {
                $query->groupBy('u.user_profile_id')
                    ->select(
                        'u.user_profile_id',
                        DB::raw('NULL as role_name'),
                        DB::raw('COUNT(*) as staff'),
                        DB::raw('SUM(CASE WHEN u.gender = "F" THEN 1 ELSE 0 END) as female'),
                        DB::raw('SUM(CASE WHEN u.gender = "M" THEN 1 ELSE 0 END) as male'),
                        DB::raw('SUM(CASE WHEN (u.email IS NULL OR u.email = "") AND (u.mobile IS NULL OR u.mobile = "") THEN 1 ELSE 0 END) as no_contact')
                    );
            }

            $rows = $query->orderByDesc('staff')->get();
            $total = array_sum(array_map(static fn ($r) => (int) $r->staff, $rows->all()));

            $out = [];
            foreach ($rows as $row) {
                $name = $row->role_name !== null
                    ? (string) $row->role_name
                    : "Role #{$row->user_profile_id} (not in the role master)";
                $recorded = (int) $row->female + (int) $row->male;

                $out[] = [
                    'key' => (string) $row->user_profile_id,
                    'label' => $name,
                    'named' => $row->role_name !== null,
                    // Whether a role teaches is read from ITS OWN NAME, which is
                    // the only place this schema records it. It decides a
                    // presentation figure and never a finding.
                    'teaching' => (bool) preg_match('/teacher|faculty|lecturer|professor|tutor/i', $name),
                    'staff' => (int) $row->staff,
                    'share' => $total > 0 ? round((int) $row->staff / $total * 100, 1) : null,
                    'female' => (int) $row->female,
                    'male' => (int) $row->male,
                    'femaleShare' => $recorded > 0 ? round((int) $row->female / $recorded * 100, 1) : null,
                    'noContact' => (int) $row->no_contact,
                ];
            }

            return $out;
        })();
    }

    /**
     * Leave taken this academic year, by type.
     *
     * @return array<int,array<string,mixed>>
     */
    public function byLeaveType(): array
    {
        return $this->memo['byLeaveType'] ??= (function (): array {
            $window = $this->yearWindow();
            if ($window === null || ! SchemaCache::hasTable(self::LEAVE_TABLE)) {
                return [];
            }

            $hasMaster = SchemaCache::hasTable(self::LEAVE_TYPE_TABLE);

            $query = $this->scopedLeave()
                ->select(
                    'l.leave_type_id',
                    DB::raw('COUNT(*) as applications'),
                    DB::raw('COUNT(DISTINCT l.user_id) as staff'),
                    DB::raw('SUM(DATEDIFF(l.to_date, l.from_date) + 1) as days')
                );

            if ($hasMaster) {
                $query->leftJoin(self::LEAVE_TYPE_TABLE.' as lt', 'lt.id', '=', 'l.leave_type_id')
                    ->addSelect(DB::raw('NULLIF(lt.leave_type, "") as type_name'))
                    ->groupBy('l.leave_type_id', 'lt.leave_type');
            } else {
                $query->addSelect(DB::raw('NULL as type_name'))->groupBy('l.leave_type_id');
            }

            $rows = $query->orderByDesc('days')->get();

            return array_map(static fn ($row) => [
                'key' => (string) $row->leave_type_id,
                'label' => $row->type_name !== null
                    ? (string) $row->type_name
                    : "Leave type #{$row->leave_type_id} (not in the leave-type master)",
                'named' => $row->type_name !== null,
                'applications' => (int) $row->applications,
                'staff' => (int) $row->staff,
                'days' => (int) $row->days,
            ], $rows->all());
        })();
    }

    /**
     * Leave taken this academic year, by the role that took it.
     *
     * @return array<int,array<string,mixed>>
     */
    public function leaveByRole(): array
    {
        return $this->memo['leaveByRole'] ??= (function (): array {
            $window = $this->yearWindow();
            if ($window === null || ! SchemaCache::hasTable(self::LEAVE_TABLE)) {
                return [];
            }

            $rows = $this->scopedLeave()
                ->join(self::USER_TABLE.' as u', 'u.id', '=', 'l.user_id')
                ->leftJoin(self::ROLE_TABLE.' as p', 'p.id', '=', 'u.user_profile_id')
                ->where('u.sub_institute_id', $this->tenantId)
                ->groupBy('u.user_profile_id', 'p.name')
                ->select(
                    'u.user_profile_id',
                    DB::raw('COALESCE(NULLIF(p.name, ""), CONCAT("Role #", u.user_profile_id)) as role_name'),
                    DB::raw('COUNT(*) as applications'),
                    DB::raw('COUNT(DISTINCT l.user_id) as staff'),
                    DB::raw('SUM(DATEDIFF(l.to_date, l.from_date) + 1) as days')
                )
                ->orderByDesc('days')
                ->get();

            $headcount = [];
            foreach ($this->byRole() as $role) {
                $headcount[$role['key']] = $role['staff'];
            }

            return array_map(static function ($row) use ($headcount) {
                $key = (string) $row->user_profile_id;
                $onRoll = $headcount[$key] ?? null;

                return [
                    'key' => $key,
                    'label' => (string) $row->role_name,
                    'applications' => (int) $row->applications,
                    'staff' => (int) $row->staff,
                    'days' => (int) $row->days,
                    'headcount' => $onRoll,
                    // An average over no headcount is undefined, not zero.
                    'daysPerStaff' => $onRoll > 0 ? round((int) $row->days / $onRoll, 1) : null,
                ];
            }, $rows->all());
        })();
    }

    /* ------------------------------------------------------- the DQ ledger */

    /** @return array<string,mixed> */
    public function dataQuality(): array
    {
        return $this->memo['dataQuality'] ??= $this->computeDataQuality();
    }

    /** @return array<string,mixed> */
    private function computeDataQuality(): array
    {
        $coverage = $this->coverage();
        if (! $coverage['available']) {
            return ['available' => false, 'reason' => $coverage['reason'], 'checks' => []];
        }

        $active = (int) $coverage['counts']['activeStaff'];
        $contact = $this->contactCompleteness();
        $records = $this->recordCompleteness();
        $unnamedRoles = count(array_filter($this->byRole(), static fn ($r) => ! $r['named']));

        return [
            'available' => true,
            'reason' => null,
            'checks' => [
                [
                    'key' => 'no_contact_route',
                    'label' => 'Active staff with neither email nor mobile',
                    'value' => $contact['missingBoth'],
                    'format' => 'count',
                    'sharePercent' => $active > 0 ? round($contact['missingBoth'] / $active * 100, 2) : null,
                    'shareLabel' => 'of active staff',
                    'state' => $contact['missingBoth'] > 0 ? 'attention' : 'ok',
                    'note' => $contact['missingBoth'] > 0
                        ? 'There is no way to reach these staff through the system at all — not for a roster change, '
                            .'not for a closure, not for a password reset.'
                        : 'Every active member of staff can be reached by email or mobile.',
                ],
                [
                    'key' => 'no_email',
                    'label' => 'Active staff with no email',
                    'value' => $contact['missingEmail'],
                    'format' => 'count',
                    'sharePercent' => $active > 0 ? round($contact['missingEmail'] / $active * 100, 2) : null,
                    'shareLabel' => 'of active staff',
                    'state' => $contact['missingEmail'] > 0 ? 'attention' : 'ok',
                    'note' => $contact['missingEmail'] > 0
                        ? 'Anything the system sends by email will not reach these staff.'
                        : 'Every active member of staff has an email address.',
                ],
                [
                    'key' => 'inactive_accounts',
                    'label' => 'Staff records no longer active',
                    'value' => (int) $coverage['counts']['staff'] - $active,
                    'format' => 'count',
                    'sharePercent' => $coverage['counts']['staff'] > 0
                        ? round(((int) $coverage['counts']['staff'] - $active) / (int) $coverage['counts']['staff'] * 100, 2)
                        : null,
                    'shareLabel' => 'of all staff records',
                    'state' => 'ok',
                    'note' => 'Every figure on this screen counts ACTIVE staff. This is the difference between the '
                        .'headcount and the number of rows, which is history rather than a problem.',
                ],
                [
                    'key' => 'no_joining_date',
                    'label' => 'Active staff with no joining date',
                    'value' => $records['noJoiningDate'],
                    'format' => 'count',
                    'sharePercent' => $active > 0 ? round($records['noJoiningDate'] / $active * 100, 2) : null,
                    'shareLabel' => 'of active staff',
                    'state' => $records['noJoiningDate'] > 0 ? 'attention' : 'ok',
                    'note' => $records['noJoiningDate'] > 0
                        ? 'Length of service cannot be computed for these staff, so nothing on this screen reports '
                            .'tenure, seniority or increment eligibility.'
                        : 'Every active member of staff has a joining date.',
                ],
                [
                    'key' => 'no_qualification',
                    'label' => 'Active staff with no qualification recorded',
                    'value' => $records['noQualification'],
                    'format' => 'count',
                    'sharePercent' => $active > 0 ? round($records['noQualification'] / $active * 100, 2) : null,
                    'shareLabel' => 'of active staff',
                    'state' => $records['noQualification'] > 0 ? 'attention' : 'ok',
                    'note' => $records['noQualification'] > 0
                        ? 'Board inspections ask for staff qualifications by name. Where the field is empty the answer '
                            .'has to be assembled by hand.'
                        : 'Every active member of staff has a qualification recorded.',
                ],
                [
                    'key' => 'unnamed_roles',
                    'label' => 'Roles missing from the role master',
                    'value' => $unnamedRoles,
                    'format' => 'count',
                    'sharePercent' => null,
                    'state' => $unnamedRoles > 0 ? 'attention' : 'ok',
                    'note' => $unnamedRoles > 0
                        ? 'These role keys are assigned to staff but have no row in the role master, so they can only '
                            .'be shown by number.'
                        : 'Every role in use resolves to a named row in the role master.',
                ],
                ...$this->punchChecks(),
            ],
        ];
    }

    /**
     * The punch register's own data-quality checks.
     *
     * Returned empty rather than as a row of zeros where there is no usable
     * register: "0 open shifts" and "we do not run a punch register" are
     * different statements, and the coverage block makes the second one.
     *
     * @return array<int,array<string,mixed>>
     */
    private function punchChecks(): array
    {
        $punch = $this->punchProfile();
        if (! $punch['available']) {
            return [];
        }

        $rows = (int) $punch['rows'];
        $active = (int) $punch['activeStaff'];

        return [
            [
                'key' => 'punch_open_shifts',
                'label' => 'Punch days with no punch-out',
                'value' => $punch['openShifts'],
                'format' => 'count',
                'sharePercent' => $rows > 0 ? round($punch['openShifts'] / $rows * 100, 2) : null,
                'shareLabel' => 'of punch days',
                'state' => $punch['openShifts'] > 0 ? 'attention' : 'ok',
                'note' => $punch['openShifts'] > 0
                    ? 'These days have a punch-in and no closing punch, so no hours can be computed for them. Any '
                        .'overtime or hours-based payment drawn from this register silently omits them.'
                    : 'Every punch day this year has both a punch-in and a punch-out.',
            ],
            [
                'key' => 'punch_out_before_in',
                'label' => 'Punch days where the punch-out precedes the punch-in',
                'value' => $punch['outBeforeIn'],
                'format' => 'count',
                'sharePercent' => null,
                'state' => $punch['outBeforeIn'] > 0 ? 'attention' : 'ok',
                'note' => $punch['outBeforeIn'] > 0
                    ? 'A closing punch earlier than the opening one is a device or clock fault. The span for these '
                        .'days is negative and cannot be read as hours worked.'
                    : 'Every punch day closes after it opens.',
            ],
            [
                'key' => 'punch_duplicate_days',
                'label' => 'Staff with more than one punch row on the same day',
                'value' => $punch['duplicates'],
                'format' => 'count',
                'sharePercent' => null,
                'state' => $punch['duplicates'] > 0 ? 'attention' : 'ok',
                'note' => $punch['duplicates'] > 0
                    ? 'Every figure on this screen counts DISTINCT days per person and is therefore unaffected — but '
                        .'a report that counts rows will over-state attendance for these days.'
                    : 'Each member of staff has at most one punch row per day.',
            ],
            [
                'key' => 'punch_orphan_users',
                'label' => 'Punch records against a user the staff master does not hold',
                'value' => $punch['orphans'],
                'format' => 'count',
                'sharePercent' => null,
                'state' => $punch['orphans'] > 0 ? 'attention' : 'ok',
                'note' => $punch['orphans'] > 0
                    ? 'These punches cannot be attributed to a member of staff at all, so they appear in the register '
                        .'totals and in no role.'
                    : 'Every punch record resolves to a member of staff in the master.',
            ],
            [
                'key' => 'punch_inactive_on_register',
                'label' => 'Staff marked inactive who punched this year',
                'value' => $punch['inactiveOnRegister'],
                'format' => 'count',
                'sharePercent' => null,
                'state' => $punch['inactiveOnRegister'] > 0 ? 'attention' : 'ok',
                'note' => $punch['inactiveOnRegister'] > 0
                    ? 'Either the staff master is behind — people who still work here are marked inactive — or the '
                        .'device is still admitting people who have left. The register cannot tell which, and the two '
                        .'need opposite responses.'
                    : 'Everybody on the register is an active member of staff.',
            ],
            [
                'key' => 'punch_active_missing',
                'label' => 'Active staff with no punch record this year',
                'value' => $punch['activeMissingFromRegister'],
                'format' => 'count',
                'sharePercent' => $active > 0 && $punch['activeMissingFromRegister'] !== null
                    ? round($punch['activeMissingFromRegister'] / $active * 100, 2)
                    : null,
                'shareLabel' => 'of active staff',
                'state' => (int) $punch['activeMissingFromRegister'] > 0 ? 'attention' : 'ok',
                'note' => (int) $punch['activeMissingFromRegister'] > 0
                    ? 'These staff are active in the master and appear nowhere in the register. They are usually '
                        .'exempt from punching or based off site — but until that is recorded, they are indistinguishable '
                        .'from staff whose attendance is simply not being captured.'
                    : 'Every active member of staff appears on the punch register.',
            ],
        ];
    }

    /* ------------------------------------------------------------ helpers */

    /** @return array{female:int,male:int,unrecorded:int,femaleShare:?float,maleShare:?float} */
    public function genderSplit(): array
    {
        return $this->memo['gender'] ??= (function (): array {
            $row = DB::table(self::USER_TABLE)
                ->where('sub_institute_id', $this->tenantId)
                ->where('status', 1)
                ->selectRaw(
                    'SUM(CASE WHEN gender = "F" THEN 1 ELSE 0 END) as female,
                     SUM(CASE WHEN gender = "M" THEN 1 ELSE 0 END) as male,
                     SUM(CASE WHEN gender IS NULL OR gender NOT IN ("F", "M") THEN 1 ELSE 0 END) as unrecorded'
                )
                ->first();

            $female = (int) ($row->female ?? 0);
            $male = (int) ($row->male ?? 0);
            $recorded = $female + $male;

            return [
                'female' => $female,
                'male' => $male,
                'unrecorded' => (int) ($row->unrecorded ?? 0),
                'femaleShare' => $recorded > 0 ? round($female / $recorded * 100, 1) : null,
                'maleShare' => $recorded > 0 ? round($male / $recorded * 100, 1) : null,
            ];
        })();
    }

    /**
     * Staff leave for THIS academic year, or nulls where no register is kept.
     *
     * @return array{applications:?int,days:?int,staff:?int,avgDaysPerStaff:?float,withoutPayDays:?int,pending:?int}
     */
    public function leaveProfile(): array
    {
        return $this->memo['leaveProfile'] ??= (function (): array {
            $none = [
                'applications' => null,
                'days' => null,
                'staff' => null,
                'avgDaysPerStaff' => null,
                'withoutPayDays' => null,
                'pending' => null,
            ];

            $window = $this->yearWindow();
            if ($window === null || ! SchemaCache::hasTable(self::LEAVE_TABLE)) {
                return $none;
            }

            $row = $this->scopedLeave()
                ->selectRaw(
                    'COUNT(*) as applications,
                     COUNT(DISTINCT l.user_id) as staff,
                     SUM(DATEDIFF(l.to_date, l.from_date) + 1) as days,
                     SUM(CASE WHEN l.status LIKE "%lwp%" THEN DATEDIFF(l.to_date, l.from_date) + 1 ELSE 0 END) as lwp_days,
                     SUM(CASE WHEN l.status = "pending" THEN 1 ELSE 0 END) as pending'
                )
                ->first();

            $applications = (int) ($row->applications ?? 0);

            // No rows at all means this institute does not keep a staff leave
            // register here. That is an absence, not a zero, and every figure
            // stays null so the screen shows an em dash.
            if ($applications === 0) {
                return $none;
            }

            $active = (int) $this->coverage()['counts']['activeStaff'];
            $days = (int) ($row->days ?? 0);

            return [
                'applications' => $applications,
                'days' => $days,
                'staff' => (int) ($row->staff ?? 0),
                'avgDaysPerStaff' => $active > 0 ? round($days / $active, 1) : null,
                'withoutPayDays' => (int) ($row->lwp_days ?? 0),
                'pending' => (int) ($row->pending ?? 0),
            ];
        })();
    }

    /** @return array{missingEmail:int,missingMobile:int,missingBoth:int} */
    public function contactCompleteness(): array
    {
        return $this->memo['contact'] ??= (function (): array {
            $row = DB::table(self::USER_TABLE)
                ->where('sub_institute_id', $this->tenantId)
                ->where('status', 1)
                ->selectRaw(
                    'SUM(CASE WHEN email IS NULL OR email = "" THEN 1 ELSE 0 END) as no_email,
                     SUM(CASE WHEN mobile IS NULL OR mobile = "" THEN 1 ELSE 0 END) as no_mobile,
                     SUM(CASE WHEN (email IS NULL OR email = "") AND (mobile IS NULL OR mobile = "") THEN 1 ELSE 0 END) as no_both'
                )
                ->first();

            return [
                'missingEmail' => (int) ($row->no_email ?? 0),
                'missingMobile' => (int) ($row->no_mobile ?? 0),
                'missingBoth' => (int) ($row->no_both ?? 0),
            ];
        })();
    }

    /** @return array{noJoiningDate:int,noQualification:int} */
    private function recordCompleteness(): array
    {
        return $this->memo['records'] ??= (function (): array {
            $row = DB::table(self::USER_TABLE)
                ->where('sub_institute_id', $this->tenantId)
                ->where('status', 1)
                ->selectRaw(
                    'SUM(CASE WHEN joined_date IS NULL OR joined_date = "0000-00-00" THEN 1 ELSE 0 END) as no_join,
                     SUM(CASE WHEN qualification IS NULL OR qualification = "" THEN 1 ELSE 0 END) as no_qual'
                )
                ->first();

            return [
                'noJoiningDate' => (int) ($row->no_join ?? 0),
                'noQualification' => (int) ($row->no_qual ?? 0),
            ];
        })();
    }

    /** Null when no year window exists, so the caller can say the year could not be applied. */
    private function leaveApplicationsThisYear(): ?int
    {
        $window = $this->yearWindow();
        if ($window === null || ! SchemaCache::hasTable(self::LEAVE_TABLE)) {
            return null;
        }

        return (int) $this->scopedLeave()->count();
    }

    private function punchRecordsThisYear(): ?int
    {
        $window = $this->yearWindow();
        if ($window === null || ! SchemaCache::hasTable(self::PUNCH_TABLE)) {
            return null;
        }

        return (int) DB::table(self::PUNCH_TABLE)
            ->where('sub_institute_id', $this->tenantId)
            ->whereBetween('day', [$window['start'], $window['end']])
            ->count();
    }

    private function payrollPeriods(): int
    {
        if (! SchemaCache::hasTable(self::SALARY_TABLE)) {
            return 0;
        }

        return (int) DB::table(self::SALARY_TABLE)
            ->where('sub_institute_id', $this->tenantId)
            ->distinct()
            ->count(DB::raw('CONCAT(year, "-", month)'));
    }

    /**
     * Staff leave inside this academic year's own dates.
     *
     * The window comes from the institute's `academic_year` rows, never from a
     * convention invented here.
     */
    private function scopedLeave(): \Illuminate\Database\Query\Builder
    {
        $window = $this->yearWindow();

        return DB::table(self::LEAVE_TABLE.' as l')
            ->where('l.sub_institute_id', $this->tenantId)
            ->whereNull('l.deleted_at')
            ->whereBetween('l.from_date', [$window['start'], $window['end']]);
    }

    /* ------------------------------------------------- the punch register --- */

    /**
     * What the staff punch register holds for this academic year.
     *
     * ONE ROW IS ONE MEMBER OF STAFF ON ONE DAY: a punch-in, usually a punch-out,
     * and the span between them. The table carries no `syear`, so it is scoped
     * through the institute's own term dates exactly as leave is.
     *
     * Everything below is either a count, a median or a band — see the class
     * note on why this register is never listed per person.
     *
     * @return array{
     *     available:bool, reason:?string, rows:?int, staff:?int, daysRecorded:?int,
     *     workingDays:?int, medianDaysPresent:?int, medianAttendance:?float,
     *     openShifts:?int, orphans:int, duplicates:int, outBeforeIn:int,
     *     inactiveOnRegister:int, activeMissingFromRegister:?int, activeStaff:int
     * }
     */
    public function punchProfile(): array
    {
        return $this->memo['punch'] ??= $this->computePunchProfile();
    }

    /** @return array<string,mixed> */
    private function computePunchProfile(): array
    {
        $none = [
            'available' => false, 'reason' => null, 'rows' => null, 'staff' => null,
            'daysRecorded' => null, 'workingDays' => null, 'medianDaysPresent' => null,
            'medianAttendance' => null, 'openShifts' => null, 'orphans' => 0, 'duplicates' => 0,
            'outBeforeIn' => 0, 'inactiveOnRegister' => 0, 'activeMissingFromRegister' => null,
            'activeStaff' => (int) ($this->coverage()['counts']['activeStaff'] ?? 0),
        ];

        if (! SchemaCache::hasTable(self::PUNCH_TABLE)) {
            // array_merge, NOT `+`: the union operator keeps the left-hand null
            // and would silently drop every reason below it.
            return array_merge($none, [
                'reason' => "Table '".self::PUNCH_TABLE."' does not exist in this deployment.",
            ]);
        }

        $window = $this->yearWindow();
        if ($window === null) {
            return array_merge($none, [
                'reason' => 'This institute has no academic-year dates on file for '.($this->syear ?? 'this year')
                    .'. The punch register carries only a date, so it cannot be held to a year that is not defined.',
            ]);
        }

        $shape = $this->scopedPunches()
            ->selectRaw(
                'COUNT(*) as rows_total,
                 COUNT(DISTINCT user_id) as staff,
                 COUNT(DISTINCT day) as days,
                 SUM(CASE WHEN punchout_time IS NULL OR CAST(punchout_time AS CHAR) LIKE "0000%" THEN 1 ELSE 0 END) as open_shifts,
                 SUM(CASE WHEN punchout_time > "1000-01-01" AND punchout_time < punchin_time THEN 1 ELSE 0 END) as out_before_in'
            )
            ->first();

        $rows = (int) ($shape->rows_total ?? 0);
        $staff = (int) ($shape->staff ?? 0);

        if ($rows === 0) {
            return array_merge($none, [
                'reason' => 'This institute recorded no staff punches between '.$window['start'].' and '
                    .$window['end'].'. That is an unused register, not a staff body that never came in.',
            ]);
        }

        if ($staff < self::MIN_PUNCH_REGISTER) {
            return array_merge($none, [
                'rows' => $rows,
                'staff' => $staff,
                'reason' => 'Only '.$staff.' '.($staff === 1 ? 'person appears' : 'people appear').' on the punch '
                    .'register this year, which is a single device rather than an institute-wide record. An attendance '
                    .'figure drawn from it would describe '.($staff === 1 ? 'that one person' : 'those '.$staff.' people')
                    .' and be read as describing the staff body.',
            ]);
        }

        $workingDays = $this->workingDays();
        $daysPresent = $this->daysPresentPerStaff();
        $count = count($daysPresent);
        $median = $count > 0 ? $daysPresent[(int) floor($count / 2)] : null;

        $roll = $this->registerAgainstRoll();

        return [
            'available' => true,
            'reason' => null,
            'rows' => $rows,
            'staff' => $staff,
            'daysRecorded' => (int) $shape->days,
            'workingDays' => $workingDays,
            'medianDaysPresent' => $median,
            // NULL, NOT ZERO, where no working day could be derived: a rate over
            // no denominator is undefined.
            'medianAttendance' => $workingDays > 0 && $median !== null
                ? round(min($median, $workingDays) / $workingDays * 100, 1)
                : null,
            'openShifts' => (int) $shape->open_shifts,
            'orphans' => $roll['orphans'],
            'duplicates' => $this->duplicatePunchDays(),
            'outBeforeIn' => (int) $shape->out_before_in,
            'inactiveOnRegister' => $roll['inactiveOnRegister'],
            'activeMissingFromRegister' => $roll['activeMissing'],
            'activeStaff' => $roll['activeStaff'],
        ];
    }

    /**
     * The institute's own working calendar, read out of its own register.
     *
     * See {@see WORKING_DAY_SHARE}. A day on which a skeleton crew punched — 9
     * people out of 234, which is what Sundays look like at the institute
     * profiled — is not a day the school ran, and counting it would make every
     * teacher look absent.
     */
    public function workingDays(): int
    {
        return $this->memo['workingDays'] ??= (function (): int {
            $window = $this->yearWindow();
            if ($window === null || ! SchemaCache::hasTable(self::PUNCH_TABLE)) {
                return 0;
            }

            $register = (int) $this->scopedPunches()->distinct()->count('user_id');
            if ($register < self::MIN_PUNCH_REGISTER) {
                return 0;
            }

            $threshold = $register * self::WORKING_DAY_SHARE / 100;

            return (int) $this->scopedPunches()
                ->groupBy('day')
                ->havingRaw('COUNT(DISTINCT user_id) >= ?', [$threshold])
                ->get(['day'])
                ->count();
        })();
    }

    /**
     * Days present per member of staff on the institute's working days, sorted
     * ascending. Values only — the user ids are deliberately dropped here, so
     * that nothing downstream can turn this into a named list.
     *
     * @return array<int,int>
     */
    private function daysPresentPerStaff(): array
    {
        return $this->memo['daysPresent'] ??= (function (): array {
            $window = $this->yearWindow();
            if ($window === null || ! SchemaCache::hasTable(self::PUNCH_TABLE)) {
                return [];
            }

            $days = $this->workingDayList();
            if ($days === []) {
                return [];
            }

            $rows = $this->scopedPunches()
                ->whereIn('day', $days)
                ->groupBy('user_id')
                ->orderByRaw('COUNT(DISTINCT day)')
                ->get([DB::raw('COUNT(DISTINCT day) as d')]);

            return array_map(static fn ($r) => (int) $r->d, $rows->all());
        })();
    }

    /**
     * The working days themselves, so per-staff figures can be measured against
     * the same calendar the institute ran.
     *
     * @return array<int,string>
     */
    private function workingDayList(): array
    {
        return $this->memo['workingDayList'] ??= (function (): array {
            $window = $this->yearWindow();
            if ($window === null || ! SchemaCache::hasTable(self::PUNCH_TABLE)) {
                return [];
            }

            $register = (int) $this->scopedPunches()->distinct()->count('user_id');
            if ($register < self::MIN_PUNCH_REGISTER) {
                return [];
            }

            $threshold = $register * self::WORKING_DAY_SHARE / 100;

            return $this->scopedPunches()
                ->groupBy('day')
                ->havingRaw('COUNT(DISTINCT user_id) >= ?', [$threshold])
                ->get(['day'])
                ->map(static fn ($r) => (string) $r->day)
                ->all();
        })();
    }

    /**
     * How the register and the staff master agree with each other.
     *
     * They disagree in both directions at the institute profiled — 61 people
     * punching whom the master marks inactive, and 16 active staff who never
     * punched — and the two mean opposite things, so they are counted
     * separately and never netted off.
     *
     * @return array{activeStaff:int,activeOnRegister:int,inactiveOnRegister:int,orphans:int,activeMissing:?int}
     */
    private function registerAgainstRoll(): array
    {
        return $this->memo['registerRoll'] ??= (function (): array {
            $window = $this->yearWindow();
            $active = (int) ($this->coverage()['counts']['activeStaff'] ?? 0);
            $empty = [
                'activeStaff' => $active, 'activeOnRegister' => 0, 'inactiveOnRegister' => 0,
                'orphans' => 0, 'activeMissing' => null,
            ];

            if ($window === null || ! SchemaCache::hasTable(self::PUNCH_TABLE)) {
                return $empty;
            }

            $row = DB::table(self::PUNCH_TABLE.' as a')
                ->leftJoin(self::USER_TABLE.' as u', function ($join) {
                    $join->on('u.id', '=', 'a.user_id')
                        ->on('u.sub_institute_id', '=', 'a.sub_institute_id');
                })
                ->where('a.sub_institute_id', $this->tenantId)
                ->whereNull('a.deleted_at')
                ->whereBetween('a.day', [$window['start'], $window['end']])
                ->selectRaw(
                    'COUNT(DISTINCT CASE WHEN u.id IS NOT NULL AND u.status = 1 THEN a.user_id END) as active_on_register,
                     COUNT(DISTINCT CASE WHEN u.id IS NOT NULL AND u.status <> 1 THEN a.user_id END) as inactive_on_register,
                     COUNT(DISTINCT CASE WHEN u.id IS NULL THEN a.user_id END) as orphans'
                )
                ->first();

            $activeOnRegister = (int) ($row->active_on_register ?? 0);

            return [
                'activeStaff' => $active,
                'activeOnRegister' => $activeOnRegister,
                'inactiveOnRegister' => (int) ($row->inactive_on_register ?? 0),
                'orphans' => (int) ($row->orphans ?? 0),
                'activeMissing' => $active > 0 ? max(0, $active - $activeOnRegister) : null,
            ];
        })();
    }

    private function duplicatePunchDays(): int
    {
        $window = $this->yearWindow();
        if ($window === null || ! SchemaCache::hasTable(self::PUNCH_TABLE)) {
            return 0;
        }

        return (int) $this->scopedPunches()
            ->groupBy('user_id', 'day')
            ->havingRaw('COUNT(*) > 1')
            ->get(['user_id'])
            ->count();
    }

    /**
     * Attendance by ROLE, never by person.
     *
     * A role below {@see MIN_ROLE_COHORT} is carried with its counts but without
     * a median, because the median of four people is four people.
     *
     * @return array<int,array<string,mixed>>
     */
    public function punchesByRole(): array
    {
        return $this->memo['punchByRole'] ??= (function (): array {
            $profile = $this->punchProfile();
            if (! $profile['available']) {
                return [];
            }

            $days = $this->workingDayList();
            $workingDays = count($days);
            if ($days === []) {
                return [];
            }

            $rows = DB::table(self::PUNCH_TABLE.' as a')
                ->join(self::USER_TABLE.' as u', function ($join) {
                    $join->on('u.id', '=', 'a.user_id')
                        ->on('u.sub_institute_id', '=', 'a.sub_institute_id');
                })
                ->leftJoin(self::ROLE_TABLE.' as p', 'p.id', '=', 'u.user_profile_id')
                ->where('a.sub_institute_id', $this->tenantId)
                ->whereNull('a.deleted_at')
                ->whereIn('a.day', $days)
                ->groupBy('u.user_profile_id', 'p.name', 'a.user_id')
                ->get([
                    'u.user_profile_id as role_key',
                    DB::raw('NULLIF(p.name, "") as role_name'),
                    DB::raw('COUNT(DISTINCT a.day) as days_present'),
                    DB::raw('SUM(CASE WHEN a.punchout_time IS NULL OR CAST(a.punchout_time AS CHAR) LIKE "0000%" THEN 1 ELSE 0 END) as open_shifts'),
                ]);

            // Fold the per-person rows into per-role medians here, in PHP, so
            // that no per-person figure ever leaves this method.
            $byRole = [];
            foreach ($rows as $row) {
                $key = (string) $row->role_key;
                $byRole[$key] ??= [
                    'key' => $key,
                    'label' => $row->role_name !== null
                        ? (string) $row->role_name
                        : "Role #{$row->role_key} (not in the role master)",
                    'named' => $row->role_name !== null,
                    'staff' => 0,
                    'openShifts' => 0,
                    'days' => [],
                ];
                $byRole[$key]['staff']++;
                $byRole[$key]['openShifts'] += (int) $row->open_shifts;
                $byRole[$key]['days'][] = (int) $row->days_present;
            }

            $out = [];
            foreach ($byRole as $role) {
                sort($role['days']);
                $n = count($role['days']);
                $small = $n < self::MIN_ROLE_COHORT;
                $median = $small || $n === 0 ? null : $role['days'][(int) floor($n / 2)];

                $out[] = [
                    'key' => $role['key'],
                    'label' => $role['label'],
                    'named' => $role['named'],
                    'staff' => $role['staff'],
                    // NULL, NOT ZERO, for a cohort too small to describe without
                    // describing the people in it.
                    'medianDaysPresent' => $median,
                    'attendance' => $median !== null && $workingDays > 0
                        ? round(min($median, $workingDays) / $workingDays * 100, 1)
                        : null,
                    'openShifts' => $role['openShifts'],
                    'suppressed' => $small,
                ];
            }

            usort($out, static fn ($a, $b) => $b['staff'] <=> $a['staff']);

            return $out;
        })();
    }

    /**
     * How the staff body spreads across attendance, in bands.
     *
     * Bands rather than a list: they answer "how many people is this about"
     * without answering "which people".
     *
     * @return array<int,array<string,mixed>>
     */
    public function attendanceBands(): array
    {
        return $this->memo['attendanceBands'] ??= (function (): array {
            $profile = $this->punchProfile();
            $workingDays = $this->workingDays();
            if (! $profile['available'] || $workingDays === 0) {
                return [];
            }

            $bands = [
                ['key' => 'under_50', 'label' => 'Under half the working days', 'min' => 0.0, 'max' => 50.0],
                ['key' => '50_70', 'label' => 'Half to 70% of working days', 'min' => 50.0, 'max' => 70.0],
                ['key' => '70_90', 'label' => '70% to 90% of working days', 'min' => 70.0, 'max' => 90.0],
                ['key' => 'over_90', 'label' => 'Over 90% of working days', 'min' => 90.0, 'max' => 100.01],
            ];

            $counts = array_fill_keys(array_column($bands, 'key'), 0);
            $total = 0;
            foreach ($this->daysPresentPerStaff() as $days) {
                $share = min($days, $workingDays) / $workingDays * 100;
                $total++;
                foreach ($bands as $band) {
                    if ($share >= $band['min'] && $share < $band['max']) {
                        $counts[$band['key']]++;
                        break;
                    }
                }
            }

            return array_map(static fn (array $band) => [
                'key' => $band['key'],
                'label' => $band['label'],
                'staff' => $counts[$band['key']],
                'share' => $total > 0 ? round($counts[$band['key']] / $total * 100, 1) : null,
            ], $bands);
        })();
    }

    /**
     * Punches inside this academic year's own dates, soft-deleted rows excluded.
     */
    private function scopedPunches(): \Illuminate\Database\Query\Builder
    {
        $window = $this->yearWindow();

        return DB::table(self::PUNCH_TABLE)
            ->where('sub_institute_id', $this->tenantId)
            ->whereNull('deleted_at')
            ->whereBetween('day', [$window['start'], $window['end']]);
    }
}
