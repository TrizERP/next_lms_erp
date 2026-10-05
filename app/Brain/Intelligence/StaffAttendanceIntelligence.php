<?php

namespace App\Brain\Intelligence;

use App\Brain\Support\AcademicYear;
use App\Brain\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * Staff Attendance Intelligence — one institute, one academic year.
 *
 * ── WHAT ONE ROW MEANS ──────────────────────────────────────────────────────
 *
 * One row of `hrms_attendances` is ONE MEMBER OF STAFF PUNCHING (or attempting
 * to) ON ONE DAY: a punch-in, usually a punch-out, and a `status` flag whose
 * real meaning is not consistent across the code that already reads this table
 * (`AttendanceApiController::weeklySummary()` treats "present" as punch-in AND
 * punch-out both recorded; `AttendanceApiController::KPI()` treats it as
 * `status = 1`). Neither is trusted here. A row existing for a user on a day is
 * what counts as a punch; whether it carries a punch-out is tracked separately
 * as a record-quality question, never folded into the presence figure.
 *
 * ── THIS TABLE HAS NO `syear` ────────────────────────────────────────────────
 *
 * `hrms_attendances` carries `day`, not an academic year. Every figure here is
 * therefore scoped through {@see AcademicYear::window()}, which reads the
 * institute's OWN term dates, exactly as {@see HrIntelligence} already does for
 * the same table. Where the institute has no term dates on file for the
 * requested year, the window is null and every query it gates returns
 * "unavailable" rather than silently scanning every row the table holds.
 *
 * ── THE WORKING CALENDAR IS DERIVED, NOT ASSUMED ────────────────────────────
 *
 * There is no institute calendar table this class can read a working-day list
 * from, and treating every weekday as one is wrong here in the same way it was
 * found to be wrong for the pupil register: security and boarding staff punch
 * on Sundays, so a naive Monday-to-Friday assumption would either miss real
 * working days or mark six-day schools absent on the seventh. Instead, a day
 * counts as a working day for this institute when at least
 * {@see self::WORKING_DAY_SHARE} of the people who ever punch this year punched
 * on it — the same technique {@see HrIntelligence::workingDays()} already
 * proved out against real registers, reused here rather than re-derived badly.
 *
 * ── `hrms_departments.department_id` IS OFTEN EMPTY ─────────────────────────
 *
 * {@see HrIntelligence} notes that the department column on `tbluser` "is
 * absent at every institute profiled: the column exists and is never
 * populated." {@see self::byDepartment()} does not assume otherwise — where no
 * active member of staff on the register carries a department, the breakdown
 * reports itself unavailable and says why, rather than drawing an empty table.
 *
 * ── WHAT IS DELIBERATELY NOT READ OR NAMED ──────────────────────────────────
 *
 * `hrms_attendances` carries `ipaddress_in`, `ipaddress_out`, `photo_in` and
 * `photo_out`. None of them is read here. And, as in {@see HrIntelligence}, no
 * figure below is a per-person list: every attendance rate is folded into a
 * count, a share, a median or a department aggregate before it leaves this
 * class, and {@see self::MIN_DEPARTMENT_COHORT} keeps a department of two or
 * three people from being named as if it were a pattern.
 *
 * ── WHY THE STAFF THRESHOLDS ARE NOT THE PUPIL THRESHOLDS ───────────────────
 *
 * {@see AttendanceIntelligence} uses 75%/65% because those are the board's own
 * published attendance lines for students. There is no equivalent published
 * line for staff, and this register does not net off approved leave (that is
 * `hrms_emp_leaves`, a separate table this class does not join), so a low
 * figure here means "punched on fewer of the institute's working days", not
 * "was absent without leave". {@see self::IRREGULAR_THRESHOLD} and
 * {@see self::CHRONIC_THRESHOLD} are therefore deliberately conservative — set
 * low enough that ordinary leave-taking does not trip them, so that crossing
 * either line is worth a manual look rather than an accusation.
 */
final class StaffAttendanceIntelligence
{
    private const USER_TABLE = 'tbluser';

    private const PUNCH_TABLE = 'hrms_attendances';

    private const DEPARTMENT_TABLE = 'hrms_departments';

    /**
     * Below this many people on the punch register, it is a device or a gate
     * rather than an institute-wide record. Matches
     * {@see HrIntelligence::MIN_PUNCH_REGISTER} — the same register, the same
     * failure mode.
     */
    public const MIN_PUNCH_REGISTER = 10;

    /**
     * A day counts as a WORKING DAY when at least this share of the register
     * punched on it. See the class note; reused from {@see HrIntelligence}
     * rather than re-derived, because it is the same table and the same
     * institute-runs-a-six-day-week problem.
     */
    public const WORKING_DAY_SHARE = 20.0;

    /**
     * Present on fewer than three in four of the institute's own working days.
     * Conservative on purpose (see class note): this register cannot see
     * approved leave, so the line sits well below what a leave-blind figure
     * would otherwise flag as unusual.
     */
    public const IRREGULAR_THRESHOLD = 75.0;

    /**
     * Present on fewer than half the institute's working days. Not explained by
     * the occasional day off however it is taken, and where HR review is
     * warranted regardless of whether the missing days turn out to be leave.
     */
    public const CHRONIC_THRESHOLD = 50.0;

    /**
     * A department smaller than this cannot support a comparison against the
     * institute — the same reasoning as
     * {@see AttendanceIntelligence::MIN_CLASS_COHORT}, applied to departments.
     */
    public const MIN_DEPARTMENT_COHORT = 5;

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

    /** The academic year's own date range, since the punch table has none of its own. */
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

        $activeStaff = $this->activeStaffQuery()->count();

        if ($activeStaff === 0) {
            return $empty + [
                'available' => false,
                'reason' => 'No active staff records exist for this institute.',
            ];
        }

        if (! SchemaCache::hasTable(self::PUNCH_TABLE)) {
            return $empty + [
                'available' => false,
                'reason' => "Table '".self::PUNCH_TABLE."' does not exist in this deployment.",
                'counts' => ['activeStaff' => $activeStaff],
            ];
        }

        $window = $this->yearWindow();
        if ($window === null) {
            return $empty + [
                'available' => false,
                'reason' => 'This institute has no academic-year dates on file for '.($this->syear ?? 'this year')
                    .'. The punch register carries only a date, so it cannot be held to a year that is not defined.',
                'counts' => ['activeStaff' => $activeStaff],
            ];
        }

        $rows = (int) $this->scoped()->count();
        if ($rows === 0) {
            return $empty + [
                'available' => false,
                'reason' => 'This institute recorded no staff punches between '.$window['start'].' and '
                    .$window['end'].'. That is an unused register, not a staff body that never came in.',
                'counts' => ['activeStaff' => $activeStaff],
            ];
        }

        $staffOnRegister = (int) $this->scoped()->distinct()->count('user_id');

        if ($staffOnRegister < self::MIN_PUNCH_REGISTER) {
            return $empty + [
                'available' => false,
                'reason' => 'Only '.$staffOnRegister.' '.($staffOnRegister === 1 ? 'person appears' : 'people appear')
                    .' on the punch register this year, which is a single device or gate rather than an '
                    .'institute-wide record. An attendance figure drawn from it would describe '
                    .($staffOnRegister === 1 ? 'that one person' : 'those '.$staffOnRegister.' people')
                    .' and be read as describing the staff body.',
                'counts' => ['activeStaff' => $activeStaff, 'staffOnRegister' => $staffOnRegister],
            ];
        }

        $departmentsPopulated = SchemaCache::hasTable(self::DEPARTMENT_TABLE)
            && SchemaCache::hasColumn(self::USER_TABLE, 'department_id')
            && $this->activeStaffQuery()->whereNotNull('department_id')->where('department_id', '>', 0)->exists();

        return [
            'available' => true,
            'reason' => null,
            'sources' => [
                'staff' => true,
                'punchRegister' => true,
                'yearWindow' => true,
                'departments' => $departmentsPopulated,
            ],
            'counts' => [
                'activeStaff' => $activeStaff,
                'staffOnRegister' => $staffOnRegister,
                'rows' => $rows,
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

        $workingDays = count($this->workingDayList());
        if ($workingDays === 0) {
            // The register exists but never reached the share of the register
            // punching on the same day that this institute's own calendar would
            // need to be derived from. A rate over zero working days is
            // undefined, not zero.
            return null;
        }

        $records = $this->staffDayCounts();
        $active = array_values(array_filter($records, static fn ($r) => $r['active']));

        if ($active === []) {
            return null;
        }

        $rates = [];
        $irregular = 0;
        $chronic = 0;
        $openShiftRows = 0;
        $departments = [];

        foreach ($active as $record) {
            $rate = round(min($record['daysPresent'], $workingDays) / $workingDays * 100, 2);
            $rates[] = $rate;
            $openShiftRows += $record['openShifts'];

            if ($rate < self::CHRONIC_THRESHOLD) {
                $chronic++;
                $irregular++;
            } elseif ($rate < self::IRREGULAR_THRESHOLD) {
                $irregular++;
            }

            if ($record['departmentId'] !== null) {
                $departments[$record['departmentId']] = true;
            }
        }

        sort($rates);
        $count = count($active);
        $activeStaff = (int) $coverage['counts']['activeStaff'];
        $activeOnRegister = $count;
        $activeMissing = max(0, $activeStaff - $activeOnRegister);

        $inactiveOnRegister = count($records) - $count;

        $totalRows = (int) $coverage['counts']['rows'];
        $openShiftsTotal = $this->openShiftRowCount();

        return [
            'activeStaff' => $activeStaff,
            'staffOnRegister' => $activeOnRegister,
            'activeMissingFromRegister' => $activeMissing,
            'inactiveOnRegister' => max(0, $inactiveOnRegister),
            'workingDays' => $workingDays,
            'averageAttendanceRate' => round(array_sum($rates) / $count, 1),
            'medianAttendanceRate' => round($rates[(int) floor($count / 2)], 1),
            'irregularStaffCount' => $irregular,
            'irregularStaffShare' => round($irregular / $count * 100, 1),
            'chronicStaffCount' => $chronic,
            'chronicStaffShare' => round($chronic / $count * 100, 1),
            'departmentsWithData' => count($departments),
            'totalPunchRows' => $totalRows,
            'openShiftRows' => $openShiftsTotal,
            'openShiftShare' => $totalRows > 0 ? round($openShiftsTotal / $totalRows * 100, 1) : null,
        ];
    }

    /* ---------------------------------------------------- L2: distribution */

    /**
     * Attendance rate by `tbluser.department_id`, joined to `hrms_departments`.
     *
     * Empty, not zero-filled, where no active member of staff on the register
     * carries a department — see the class note on how rarely that column is
     * populated in practice.
     *
     * @return array<int,array<string,mixed>>
     */
    public function byDepartment(): array
    {
        return $this->memo['byDepartment'] ??= $this->computeByDepartment();
    }

    /** @return array<int,array<string,mixed>> */
    private function computeByDepartment(): array
    {
        if (! $this->coverage()['available']) {
            return [];
        }

        $workingDays = count($this->workingDayList());
        if ($workingDays === 0) {
            return [];
        }

        $grouped = [];
        foreach ($this->staffDayCounts() as $record) {
            if (! $record['active'] || $record['departmentId'] === null) {
                continue;
            }

            $key = (string) $record['departmentId'];
            $grouped[$key] ??= [
                'key' => $key,
                'label' => $record['departmentName'],
                'staff' => 0,
                'rates' => [],
            ];

            $grouped[$key]['staff']++;
            $grouped[$key]['rates'][] = round(min($record['daysPresent'], $workingDays) / $workingDays * 100, 2);
        }

        $out = [];
        foreach ($grouped as $group) {
            $rates = $group['rates'];
            sort($rates);
            $n = count($rates);

            $irregular = count(array_filter($rates, static fn ($r) => $r < self::IRREGULAR_THRESHOLD));
            $chronic = count(array_filter($rates, static fn ($r) => $r < self::CHRONIC_THRESHOLD));

            $out[] = [
                'key' => $group['key'],
                'label' => $group['label'],
                'staff' => $n,
                'averageAttendanceRate' => round(array_sum($rates) / $n, 1),
                'medianAttendanceRate' => round($rates[(int) floor($n / 2)], 1),
                'irregularCount' => $irregular,
                'chronicCount' => $chronic,
            ];
        }

        // Weakest first — read to find where to look, same convention as
        // AttendanceIntelligence::byStandard().
        usort($out, static fn ($a, $b) => $a['averageAttendanceRate'] <=> $b['averageAttendanceRate']);

        return $out;
    }

    /**
     * How the active, on-register staff body spreads across attendance bands.
     *
     * Bands rather than a list, same as {@see HrIntelligence::attendanceBands()}
     * and for the same reason: this answers "how many people is this about"
     * without turning into a per-person register.
     *
     * @return array<int,array<string,mixed>>
     */
    public function byAttendanceBand(): array
    {
        return $this->memo['byAttendanceBand'] ??= $this->computeByAttendanceBand();
    }

    /** @return array<int,array<string,mixed>> */
    private function computeByAttendanceBand(): array
    {
        if (! $this->coverage()['available']) {
            return [];
        }

        $workingDays = count($this->workingDayList());
        if ($workingDays === 0) {
            return [];
        }

        $bands = [
            'strong' => ['label' => '90% and above', 'count' => 0],
            'adequate' => ['label' => '75% to 89.9%', 'count' => 0],
            'irregular' => ['label' => '50% to 74.9% — irregular', 'count' => 0],
            'chronic' => ['label' => 'Below 50% — chronic', 'count' => 0],
        ];

        $total = 0;
        foreach ($this->staffDayCounts() as $record) {
            if (! $record['active']) {
                continue;
            }

            $rate = round(min($record['daysPresent'], $workingDays) / $workingDays * 100, 2);
            $key = match (true) {
                $rate >= 90.0 => 'strong',
                $rate >= self::IRREGULAR_THRESHOLD => 'adequate',
                $rate >= self::CHRONIC_THRESHOLD => 'irregular',
                default => 'chronic',
            };
            $bands[$key]['count']++;
            $total++;
        }

        if ($total === 0) {
            return [];
        }

        return array_values(array_map(static fn ($key, $band) => [
            'key' => $key,
            'label' => $band['label'],
            'staff' => $band['count'],
            'share' => round($band['count'] / $total * 100, 1),
        ], array_keys($bands), $bands));
    }

    /* ------------------------------------------------------------ the DQ ledger */

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

        $rows = (int) $coverage['counts']['rows'];
        $activeStaff = (int) $coverage['counts']['activeStaff'];

        $position = $this->position();

        $activeMissing = $position['activeMissingFromRegister'] ?? null;
        $inactiveOnRegister = $position['inactiveOnRegister'] ?? null;

        $missingPunchIn = (int) $this->scoped()->whereNull('punchin_time')->count();

        $openShifts = $this->openShiftRowCount();

        $orphans = (int) DB::table(self::PUNCH_TABLE.' as a')
            ->leftJoin(self::USER_TABLE.' as u', function ($join) {
                $join->on('u.id', '=', 'a.user_id')
                    ->on('u.sub_institute_id', '=', 'a.sub_institute_id');
            })
            ->where('a.sub_institute_id', $this->tenantId)
            ->whereNull('a.deleted_at')
            ->whereBetween('a.day', [$this->yearWindow()['start'], $this->yearWindow()['end']])
            ->whereNull('u.id')
            ->distinct()
            ->count('a.user_id');

        $duplicates = (int) $this->scoped()
            ->groupBy('user_id', 'day')
            ->havingRaw('COUNT(*) > 1')
            ->get(['user_id'])
            ->count();

        $withoutDepartment = SchemaCache::hasColumn(self::USER_TABLE, 'department_id')
            ? (int) $this->activeStaffQuery()
                ->where(fn ($q) => $q->whereNull('department_id')->orWhere('department_id', '<=', 0))
                ->count()
            : $activeStaff;

        return [
            'available' => true,
            'reason' => null,
            'checks' => [
                [
                    'key' => 'active_staff_missing_from_register',
                    'label' => 'Active staff with no punch this year',
                    'value' => $activeMissing,
                    'format' => 'count',
                    'sharePercent' => $activeStaff > 0 && $activeMissing !== null
                        ? round($activeMissing / $activeStaff * 100, 2) : null,
                    'shareLabel' => 'of active staff',
                    'state' => ($activeMissing ?? 0) > 0 ? 'attention' : 'ok',
                    'note' => ($activeMissing ?? 0) > 0
                        ? 'Every attendance figure on this screen is computed over the staff who have a punch, so '
                            .'these members of staff are absent from the figures rather than necessarily from work.'
                        : 'Every active member of staff has at least one punch on the institute\'s own working days.',
                ],
                [
                    'key' => 'inactive_staff_on_register',
                    'label' => 'Punches from staff no longer marked active',
                    'value' => $inactiveOnRegister,
                    'format' => 'count',
                    'sharePercent' => null,
                    'shareLabel' => null,
                    'state' => ($inactiveOnRegister ?? 0) > 0 ? 'attention' : 'ok',
                    'note' => ($inactiveOnRegister ?? 0) > 0
                        ? 'These punches belong to staff whose record is no longer marked active — likely someone who '
                            .'left partway through the window. They are excluded from every rate above.'
                        : 'No punch this year belongs to a member of staff marked inactive.',
                ],
                [
                    'key' => 'rows_missing_punchin',
                    'label' => 'Punch rows with no punch-in recorded',
                    'value' => $missingPunchIn,
                    'format' => 'count',
                    'sharePercent' => $rows > 0 ? round($missingPunchIn / $rows * 100, 2) : null,
                    'shareLabel' => 'of rows',
                    'state' => $missingPunchIn > 0 ? 'attention' : 'ok',
                    'note' => $missingPunchIn > 0
                        ? 'The row exists for the day but carries no punch-in time — a device fault or a manually '
                            .'entered record left incomplete.'
                        : 'Every punch row records a punch-in time.',
                ],
                [
                    'key' => 'open_shifts',
                    'label' => 'Rows with no punch-out recorded',
                    'value' => $openShifts,
                    'format' => 'count',
                    'sharePercent' => $rows > 0 ? round($openShifts / $rows * 100, 2) : null,
                    'shareLabel' => 'of rows',
                    'state' => $rows > 0 && round($openShifts / $rows * 100, 2) > 15.0 ? 'attention' : 'ok',
                    'note' => $openShifts > 0
                        ? 'A punch-in with no punch-out is either a shift still open or a device that never captured '
                            .'the exit. It is counted as a punch here regardless, and flagged here separately.'
                        : 'Every punch row this year carries a punch-out.',
                ],
                [
                    'key' => 'orphan_punches',
                    'label' => 'Punches with no matching staff record',
                    'value' => $orphans,
                    'format' => 'count',
                    'sharePercent' => null,
                    'shareLabel' => null,
                    'state' => $orphans > 0 ? 'attention' : 'ok',
                    'note' => $orphans > 0
                        ? 'These user ids appear on the punch register but not in the staff table for this institute — '
                            .'a deleted account or a device syncing against the wrong institute.'
                        : 'Every punch on the register matches a staff record at this institute.',
                ],
                [
                    'key' => 'duplicate_punch_days',
                    'label' => 'Staff with more than one row on the same day',
                    'value' => $duplicates,
                    'format' => 'count',
                    'sharePercent' => null,
                    'shareLabel' => null,
                    'state' => $duplicates > 0 ? 'attention' : 'ok',
                    'note' => $duplicates > 0
                        ? 'More than one punch row exists for the same person on the same day — most often a double '
                            .'punch at the gate. Days present is counted once regardless.'
                        : 'No member of staff has more than one punch row on the same day.',
                ],
                [
                    'key' => 'staff_without_department',
                    'label' => 'Active staff with no department on file',
                    'value' => $withoutDepartment,
                    'format' => 'count',
                    'sharePercent' => $activeStaff > 0 ? round($withoutDepartment / $activeStaff * 100, 2) : null,
                    'shareLabel' => 'of active staff',
                    'state' => $activeStaff > 0 && round($withoutDepartment / $activeStaff * 100, 2) > 50.0
                        ? 'attention' : 'ok',
                    'note' => $withoutDepartment > 0
                        ? 'These staff carry no department, so they can never appear in the department breakdown '
                            .'above however their attendance runs.'
                        : 'Every active member of staff carries a department.',
                ],
            ],
        ];
    }

    /* ------------------------------------------------------------ helpers */

    /** Active staff for this institute: on the roll and not terminated. */
    private function activeStaffQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table(self::USER_TABLE)
            ->where('sub_institute_id', $this->tenantId)
            ->where('status', 1)
            ->whereNull('terminated_date');
    }

    /**
     * The institute's own working calendar, read out of its own register —
     * see the class note. Values only; sorted so the median is a single index.
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

            $register = (int) $this->scoped()->distinct()->count('user_id');
            if ($register < self::MIN_PUNCH_REGISTER) {
                return [];
            }

            $threshold = $register * self::WORKING_DAY_SHARE / 100;

            return $this->scoped()
                ->groupBy('day')
                ->havingRaw('COUNT(DISTINCT user_id) >= ?', [$threshold])
                ->get(['day'])
                ->map(static fn ($r) => (string) $r->day)
                ->all();
        })();
    }

    /** Rows with no punch-out (or a zero-date sentinel) across the whole window, not just working days. */
    private function openShiftRowCount(): int
    {
        return $this->memo['openShiftRows'] ??= (int) $this->scoped()
            ->where(function ($q) {
                $q->whereNull('punchout_time')
                    ->orWhereRaw('CAST(punchout_time AS CHAR) LIKE "0000%"');
            })
            ->count();
    }

    /**
     * One entry per (staff member, whether their account is currently active),
     * counted over the institute's own working days only, with their
     * department carried alongside for the breakdown. User ids are used only
     * to group the rows — nothing downstream keeps them past this method.
     *
     * @return array<int,array{userId:string,active:bool,departmentId:?string,departmentName:string,daysPresent:int,openShifts:int}>
     */
    private function staffDayCounts(): array
    {
        return $this->memo['staffDayCounts'] ??= $this->fetchStaffDayCounts();
    }

    /** @return array<int,array<string,mixed>> */
    private function fetchStaffDayCounts(): array
    {
        $days = $this->workingDayList();
        if ($days === []) {
            return [];
        }

        $hasDepartments = SchemaCache::hasTable(self::DEPARTMENT_TABLE);

        $query = DB::table(self::PUNCH_TABLE.' as a')
            ->join(self::USER_TABLE.' as u', function ($join) {
                $join->on('u.id', '=', 'a.user_id')
                    ->on('u.sub_institute_id', '=', 'a.sub_institute_id');
            });

        if ($hasDepartments) {
            $query->leftJoin(self::DEPARTMENT_TABLE.' as d', 'd.id', '=', 'u.department_id');
        }

        $rows = $query
            ->where('a.sub_institute_id', $this->tenantId)
            ->whereNull('a.deleted_at')
            ->whereIn('a.day', $days)
            ->groupBy('a.user_id', 'u.department_id', 'u.status', 'u.terminated_date')
            ->select(array_filter([
                'a.user_id',
                'u.department_id',
                $hasDepartments ? DB::raw('NULLIF(d.department, "") as department_name') : DB::raw('NULL as department_name'),
                DB::raw('CASE WHEN u.status = 1 AND u.terminated_date IS NULL THEN 1 ELSE 0 END as is_active'),
                DB::raw('COUNT(DISTINCT a.day) as days_present'),
                DB::raw('SUM(CASE WHEN a.punchout_time IS NULL OR CAST(a.punchout_time AS CHAR) LIKE "0000%" THEN 1 ELSE 0 END) as open_shifts'),
            ]))
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $departmentId = $row->department_id !== null && (int) $row->department_id > 0
                ? (string) $row->department_id
                : null;

            $out[] = [
                'userId' => (string) $row->user_id,
                'active' => (bool) $row->is_active,
                'departmentId' => $departmentId,
                'departmentName' => $departmentId !== null
                    ? (string) ($row->department_name ?? "Department #{$departmentId}")
                    : 'Unassigned',
                'daysPresent' => (int) $row->days_present,
                'openShifts' => (int) $row->open_shifts,
            ];
        }

        return $out;
    }

    /** Every query in this class starts here, so no figure can escape the tenant/window filter. */
    private function scoped(): \Illuminate\Database\Query\Builder
    {
        $window = $this->yearWindow();

        $query = DB::table(self::PUNCH_TABLE)
            ->where('sub_institute_id', $this->tenantId)
            ->whereNull('deleted_at');

        if ($window !== null) {
            $query->whereBetween('day', [$window['start'], $window['end']]);
        }

        return $query;
    }
}
