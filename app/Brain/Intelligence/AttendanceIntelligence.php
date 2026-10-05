<?php

namespace App\Brain\Intelligence;

use App\Brain\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * Attendance Intelligence — one institute, one academic year.
 *
 * ── WHAT ONE ROW MEANS ──────────────────────────────────────────────────────
 *
 * One row of `result_student_attendance_master` is ONE STUDENT'S ATTENDANCE IN
 * ONE TERM: days present, the term's working days, and a stored percentage. A
 * student with two terms has two rows, so every student-level figure here sums
 * the terms first and divides once.
 *
 * ── TWO THINGS THE PROFILER FOUND, AND WHY THEY CHANGED THIS FILE ───────────
 *
 * 1. A YEAR CAN HOLD ATTENDANCE ROWS AND NO ATTENDANCE. At one institute this
 *    year holds 6,736 rows carrying 363,426 working days and THREE rows with a
 *    day present recorded against them. The old gate was "at least one usable
 *    row", so the screen passed, computed over those three students, and
 *    reported a 36.9% institute attendance rate under a headline saying
 *    "3 students evaluated". A figure like that is worse than an empty screen:
 *    it is wrong, it is specific, and it looks like an answer.
 *
 *    THE GATE IS NOW A SHARE, NOT A COUNT. A year where attendance has been
 *    entered runs at 98–100% of rows carrying days present; the two years in
 *    this database where it has not run at 0.04% and 8.6%. Anything below
 *    {@see self::MIN_RECORDED_SHARE} reports unavailable and says what was
 *    found, because "nobody has entered attendance yet" and "attendance is
 *    poor" are opposite conclusions from the same empty column.
 *
 * 2. THE ROLL IS BIGGER THAN THE ATTENDANCE REGISTER. At the institute above,
 *    2,752 students have an attendance row and 3,717 are enrolled — a quarter
 *    of the school is absent from the register rather than from school. The old
 *    screen said "2,750 students were evaluated" and left the reader to assume
 *    that was the school. Roll coverage is now a figure on the page and a rule
 *    of its own.
 *
 * ── WHY THE STORED `percentage` IS NOT TRUSTED ──────────────────────────────
 *
 * It is zero for every row of one institute's second term while `attendance`
 * and `working_day` on the same rows are populated and consistent. Every rate
 * here is therefore computed from days present over working days, and the
 * disagreement between the two is reported as a record check rather than
 * silently preferred one way or the other.
 */
final class AttendanceIntelligence
{
    private const ATTENDANCE_TABLE = 'result_student_attendance_master';

    private const WORKING_DAYS_TABLE = 'result_working_day_master';

    private const STANDARD_TABLE = 'standard';

    private const ENROLLMENT_TABLE = 'tblstudent_enrollment';

    /**
     * The share of attendance rows that must actually carry days present before
     * any rate on this screen means anything.
     *
     * Not a tuned number: the gap in the data is between 8.6% and 98.7%, and
     * fifty is the middle of it. A rate computed over less than half the rows
     * is not the institute's rate.
     */
    public const MIN_RECORDED_SHARE = 50.0;

    /** CBSE and most state boards treat attendance below this as chronic. */
    public const CHRONIC_THRESHOLD = 75.0;

    /** Below this the board's own remedial provisions apply. */
    public const SEVERE_THRESHOLD = 65.0;

    /**
     * A class smaller than this cannot support a comparison against the
     * institute. Four students at 60% and two hundred at 60% are different
     * facts and only the second is a class-level pattern.
     */
    public const MIN_CLASS_COHORT = 10;

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

        if ($this->syear === null) {
            return $empty + [
                'available' => false,
                'reason' => 'No academic year was specified or resolved for this institute.',
            ];
        }

        if (! SchemaCache::hasTable(self::ATTENDANCE_TABLE)) {
            return $empty + [
                'available' => false,
                'reason' => "Table '".self::ATTENDANCE_TABLE."' does not exist in this deployment.",
            ];
        }

        $shape = $this->scoped()
            ->selectRaw(
                'COUNT(*) as rows_total,
                 COUNT(DISTINCT student_id) as students,
                 COUNT(DISTINCT standard) as standards,
                 COUNT(DISTINCT term_id) as terms,
                 SUM(CASE WHEN attendance > 0 THEN 1 ELSE 0 END) as rows_with_attendance,
                 SUM(CASE WHEN working_day > 0 THEN 1 ELSE 0 END) as rows_with_working_days'
            )
            ->first();

        $rows = (int) ($shape->rows_total ?? 0);

        if ($rows === 0) {
            return $empty + [
                'available' => false,
                'reason' => "No attendance records exist for academic year {$this->syear}.",
            ];
        }

        $recorded = (int) $shape->rows_with_attendance;
        $share = round($recorded / $rows * 100, 1);

        $onRoll = $this->studentsOnRoll();
        $workingDayRows = SchemaCache::hasTable(self::WORKING_DAYS_TABLE)
            ? (int) DB::table(self::WORKING_DAYS_TABLE)
                ->where('sub_institute_id', $this->tenantId)
                ->where('syear', $this->syear)
                ->count()
            : 0;

        $sources = [
            'attendanceRows' => true,
            'daysPresentEntered' => $share >= self::MIN_RECORDED_SHARE,
            'workingDayMaster' => $workingDayRows > 0,
            'roll' => $onRoll > 0,
            'multipleTerms' => (int) $shape->terms > 1,
        ];

        $counts = [
            'rows' => $rows,
            'rowsWithDaysPresent' => $recorded,
            'rowsWithWorkingDays' => (int) $shape->rows_with_working_days,
            'studentsWithAttendance' => (int) $shape->students,
            'studentsOnRoll' => $onRoll,
            'standards' => (int) $shape->standards,
            'terms' => (int) $shape->terms,
            'workingDayRows' => $workingDayRows,
        ];

        if ($share < self::MIN_RECORDED_SHARE) {
            return [
                'available' => false,
                // The reason carries the figures, because "no data" would be
                // false — there is a great deal of data, and none of it is
                // attendance.
                'reason' => "Attendance has not been entered for academic year {$this->syear}. "
                    ."{$rows} attendance rows exist covering {$shape->students} students, and "
                    ."{$shape->rows_with_working_days} of them carry the term's working days, but only {$recorded} "
                    ."({$share}%) record any days present. A rate computed over those would describe the handful of "
                    .'rows that were filled in, not the school.',
                'sources' => $sources,
                'counts' => $counts,
            ];
        }

        return [
            'available' => true,
            'reason' => null,
            'sources' => $sources,
            'counts' => $counts,
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

        $students = $this->studentRecords();
        if ($students === []) {
            return null;
        }

        $present = 0;
        $working = 0;
        $rates = [];
        $chronic = 0;
        $severe = 0;
        $standards = [];

        foreach ($students as $student) {
            $rates[] = $student['percentage'];
            $present += $student['present'];
            $working += $student['workingDays'];
            $standards[$student['standardId']] = true;

            if ($student['percentage'] < self::SEVERE_THRESHOLD) {
                $severe++;
                $chronic++;
            } elseif ($student['percentage'] < self::CHRONIC_THRESHOLD) {
                $chronic++;
            }
        }

        sort($rates);
        $count = count($students);
        $onRoll = (int) $coverage['counts']['studentsOnRoll'];

        return [
            'students' => $count,
            'studentsOnRoll' => $onRoll > 0 ? $onRoll : null,
            // NULL, NOT ZERO: with no roll on file the share of it covered is
            // unknown, not nought per cent.
            'rollCoverage' => $onRoll > 0 ? round(min($count, $onRoll) / $onRoll * 100, 1) : null,
            'studentsWithoutRecord' => $onRoll > $count ? $onRoll - $count : 0,
            'standards' => count($standards),
            'terms' => (int) $coverage['counts']['terms'],
            'attendanceRate' => $working > 0 ? round($present / $working * 100, 1) : null,
            'medianRate' => round($rates[(int) floor($count / 2)], 1),
            'totalPresentDays' => $present,
            'totalWorkingDays' => $working,
            'chronicAbsenceCount' => $chronic,
            'chronicAbsenceShare' => round($chronic / $count * 100, 1),
            'severeAbsenceCount' => $severe,
            'severeAbsenceShare' => round($severe / $count * 100, 1),
        ];
    }

    /* ---------------------------------------------------- L2: distribution */

    /** @return array<int,array<string,mixed>> */
    public function byStandard(): array
    {
        return $this->memo['byStandard'] ??= $this->computeByStandard();
    }

    /** @return array<int,array<string,mixed>> */
    private function computeByStandard(): array
    {
        if (! $this->coverage()['available']) {
            return [];
        }

        $grouped = [];
        foreach ($this->studentRecords() as $student) {
            $key = (string) $student['standardId'];
            $grouped[$key] ??= [
                'key' => $key,
                'label' => $student['standardName'],
                'students' => 0,
                'present' => 0,
                'workingDays' => 0,
                'chronic' => 0,
                'severe' => 0,
                'rates' => [],
            ];

            $grouped[$key]['students']++;
            $grouped[$key]['present'] += $student['present'];
            $grouped[$key]['workingDays'] += $student['workingDays'];
            $grouped[$key]['rates'][] = $student['percentage'];

            if ($student['percentage'] < self::SEVERE_THRESHOLD) {
                $grouped[$key]['severe']++;
                $grouped[$key]['chronic']++;
            } elseif ($student['percentage'] < self::CHRONIC_THRESHOLD) {
                $grouped[$key]['chronic']++;
            }
        }

        $out = [];
        foreach ($grouped as $group) {
            $rates = $group['rates'];
            sort($rates);

            $out[] = [
                'key' => $group['key'],
                'label' => $group['label'],
                'students' => $group['students'],
                'attendanceRate' => $group['workingDays'] > 0
                    ? round($group['present'] / $group['workingDays'] * 100, 1)
                    : null,
                'medianRate' => round($rates[(int) floor(count($rates) / 2)], 1),
                'chronicCount' => $group['chronic'],
                'chronicShare' => round($group['chronic'] / $group['students'] * 100, 1),
                'severeCount' => $group['severe'],
            ];
        }

        // Weakest first — this table is read to find where to look.
        usort($out, static fn ($a, $b) => ($a['attendanceRate'] ?? 101) <=> ($b['attendanceRate'] ?? 101));

        return $out;
    }

    /** @return array<int,array<string,mixed>> */
    public function byTier(): array
    {
        return $this->memo['byTier'] ??= $this->computeByTier();
    }

    /** @return array<int,array<string,mixed>> */
    private function computeByTier(): array
    {
        $students = $this->studentRecords();
        if ($students === []) {
            return [];
        }

        $tiers = [
            'excellent' => ['label' => '95% and above', 'count' => 0],
            'good' => ['label' => '85% to 94.9%', 'count' => 0],
            'moderate' => ['label' => '75% to 84.9%', 'count' => 0],
            'chronic' => ['label' => '65% to 74.9% — chronic absence', 'count' => 0],
            'severe' => ['label' => 'Below 65% — board remediation applies', 'count' => 0],
        ];

        foreach ($students as $student) {
            $rate = $student['percentage'];
            $key = match (true) {
                $rate >= 95.0 => 'excellent',
                $rate >= 85.0 => 'good',
                $rate >= self::CHRONIC_THRESHOLD => 'moderate',
                $rate >= self::SEVERE_THRESHOLD => 'chronic',
                default => 'severe',
            };
            $tiers[$key]['count']++;
        }

        $total = count($students);

        return array_values(array_map(static fn ($key, $tier) => [
            'key' => $key,
            'label' => $tier['label'],
            'students' => $tier['count'],
            'share' => round($tier['count'] / $total * 100, 1),
        ], array_keys($tiers), $tiers));
    }

    /**
     * Attendance per term, which is the only trend this table can support.
     *
     * There is no date on an attendance row — only a term — so "attendance is
     * falling" can be said across terms and cannot be said across weeks. The
     * module says the thing the grain supports and no more.
     *
     * @return array<int,array<string,mixed>>
     */
    public function byTerm(): array
    {
        return $this->memo['byTerm'] ??= $this->computeByTerm();
    }

    /** @return array<int,array<string,mixed>> */
    private function computeByTerm(): array
    {
        if (! $this->coverage()['available']) {
            return [];
        }

        $rows = $this->scoped()
            ->where('attendance', '>', 0)
            ->groupBy('term_id')
            ->orderBy('term_id')
            ->select(
                'term_id',
                DB::raw('COUNT(DISTINCT student_id) as students'),
                DB::raw('SUM(attendance) as present'),
                DB::raw('SUM(working_day) as working_days')
            )
            ->get();

        $out = [];
        $ordinal = 0;
        foreach ($rows as $row) {
            $ordinal++;
            $working = (int) $row->working_days;
            $out[] = [
                'key' => (string) $row->term_id,
                // The term master is not reliably populated per institute, so
                // terms are numbered in the order the register records them
                // rather than given a name this table cannot supply.
                'label' => "Term {$ordinal}",
                'students' => (int) $row->students,
                'presentDays' => (int) $row->present,
                'workingDays' => $working,
                'attendanceRate' => $working > 0 ? round((int) $row->present / $working * 100, 1) : null,
            ];
        }

        return $out;
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

        $counts = $coverage['counts'];
        $rows = (int) $counts['rows'];

        $noDays = $rows - (int) $counts['rowsWithDaysPresent'];
        $noWorkingDays = $rows - (int) $counts['rowsWithWorkingDays'];

        $unmapped = (int) $this->scoped()
            ->where(fn ($q) => $q->whereNull('standard')->orWhere('standard', '<=', 0))
            ->count();

        $overAttended = (int) $this->scoped()
            ->whereColumn('attendance', '>', 'working_day')
            ->where('working_day', '>', 0)
            ->count();

        // The stored percentage disagreeing with days present over working days
        // is why nothing on this screen reads the stored column.
        $percentageDisagrees = (int) $this->scoped()
            ->where('working_day', '>', 0)
            ->where('attendance', '>', 0)
            ->whereRaw('ABS(percentage - (attendance / working_day * 100)) > 1')
            ->count();

        $missingFromRegister = (int) ($counts['studentsOnRoll'] - $counts['studentsWithAttendance']);
        $missingFromRegister = max(0, $missingFromRegister);

        return [
            'available' => true,
            'reason' => null,
            'checks' => [
                [
                    'key' => 'roll_not_in_register',
                    'label' => 'Enrolled students with no attendance row',
                    'value' => $missingFromRegister,
                    'format' => 'count',
                    'sharePercent' => $counts['studentsOnRoll'] > 0
                        ? round($missingFromRegister / $counts['studentsOnRoll'] * 100, 2)
                        : null,
                    'shareLabel' => 'of the roll',
                    'state' => $missingFromRegister > 0 ? 'attention' : 'ok',
                    'note' => $missingFromRegister > 0
                        ? 'Every rate on this screen is computed over the students who have a row, so these students '
                            .'are absent from the figures rather than from school. Their attendance is unknown, not low.'
                        : 'Every student on this year’s roll has an attendance row.',
                ],
                [
                    'key' => 'rows_without_days_present',
                    'label' => 'Rows with no days present',
                    'value' => $noDays,
                    'format' => 'count',
                    'sharePercent' => $rows > 0 ? round($noDays / $rows * 100, 2) : null,
                    'shareLabel' => 'of rows',
                    'state' => $noDays > 0 ? 'attention' : 'ok',
                    'note' => $noDays > 0
                        ? 'The row exists and carries working days, but nobody has entered how many of them the '
                            .'student attended. These rows are excluded from every rate.'
                        : 'Every attendance row records days present.',
                ],
                [
                    'key' => 'rows_without_working_days',
                    'label' => 'Rows with no working days',
                    'value' => $noWorkingDays,
                    'format' => 'count',
                    'sharePercent' => $rows > 0 ? round($noWorkingDays / $rows * 100, 2) : null,
                    'shareLabel' => 'of rows',
                    'state' => $noWorkingDays > 0 ? 'attention' : 'ok',
                    'note' => $noWorkingDays > 0
                        ? 'Days present over no working days has no percentage. Where the term’s working days are on '
                            .'file in the working-day master they are used instead; where they are not, the row is '
                            .'excluded.'
                        : 'Every attendance row records the term’s working days.',
                ],
                [
                    'key' => 'attendance_over_working_days',
                    'label' => 'Days present above the term’s working days',
                    'value' => $overAttended,
                    'format' => 'count',
                    'sharePercent' => $rows > 0 ? round($overAttended / $rows * 100, 2) : null,
                    'shareLabel' => 'of rows',
                    'state' => $overAttended > 0 ? 'attention' : 'ok',
                    'note' => $overAttended > 0
                        ? 'A student cannot attend more days than the term has. Every rate these rows feed is '
                            .'overstated; they are capped at 100% here rather than allowed to lift the average above it.'
                        : 'No student is recorded as attending more days than the term held.',
                ],
                [
                    'key' => 'stored_percentage_disagrees',
                    'label' => 'Rows whose stored percentage disagrees',
                    'value' => $percentageDisagrees,
                    'format' => 'count',
                    'sharePercent' => $rows > 0 ? round($percentageDisagrees / $rows * 100, 2) : null,
                    'shareLabel' => 'of rows',
                    'state' => $percentageDisagrees > 0 ? 'attention' : 'ok',
                    'note' => $percentageDisagrees > 0
                        ? 'The percentage stored on the row differs by more than a point from days present over '
                            .'working days. This screen computes from the days and ignores the stored column, which is '
                            .'why these rows do not distort it.'
                        : 'The stored percentage agrees with days present over working days on every row.',
                ],
                [
                    'key' => 'unmapped_standard',
                    'label' => 'Rows with no class',
                    'value' => $unmapped,
                    'format' => 'count',
                    'sharePercent' => $rows > 0 ? round($unmapped / $rows * 100, 2) : null,
                    'shareLabel' => 'of rows',
                    'state' => $unmapped > 0 ? 'attention' : 'ok',
                    'note' => $unmapped > 0
                        ? 'These rows carry no class, so they count towards the institute rate but appear in no class '
                            .'in the breakdown below.'
                        : 'Every attendance row is assigned to a class.',
                ],
            ],
        ];
    }

    /* ------------------------------------------------------------ helpers */

    /** This year's roll, for the coverage figures. */
    public function studentsOnRoll(): int
    {
        return $this->memo['onRoll'] ??= SchemaCache::hasTable(self::ENROLLMENT_TABLE)
            ? (int) DB::table(self::ENROLLMENT_TABLE)
                ->where('sub_institute_id', $this->tenantId)
                ->where('syear', $this->syear)
                ->distinct()
                ->count('student_id')
            : 0;
    }

    /**
     * One entry per student, with their terms already summed.
     *
     * THE WORKING DAYS COME FROM THE ROW FIRST AND THE TERM MASTER SECOND.
     * `working_day` on the row is the authoritative figure when it is present;
     * `result_working_day_master` supplies it per (standard, term) when it is
     * not, which is how an institute that keeps the figure centrally still gets
     * a rate. A student with neither is excluded, and counted in the ledger.
     *
     * @return array<int,array<string,mixed>>
     */
    private function studentRecords(): array
    {
        return $this->memo['studentRecords'] ??= $this->fetchStudentRecords();
    }

    /** @return array<int,array<string,mixed>> */
    private function fetchStudentRecords(): array
    {
        $rows = DB::table(self::ATTENDANCE_TABLE.' as a')
            ->leftJoin(self::STANDARD_TABLE.' as s', 's.id', '=', 'a.standard')
            ->leftJoin(self::WORKING_DAYS_TABLE.' as w', function ($join) {
                $join->on('w.sub_institute_id', '=', 'a.sub_institute_id')
                    ->on('w.syear', '=', 'a.syear')
                    ->on('w.standard', '=', 'a.standard')
                    ->on('w.term_id', '=', 'a.term_id');
            })
            ->where('a.sub_institute_id', $this->tenantId)
            ->where('a.syear', $this->syear)
            ->where('a.attendance', '>', 0)
            ->select(
                'a.student_id',
                'a.standard as standard_id',
                DB::raw('COALESCE(NULLIF(s.name, ""), CONCAT("Class ", a.standard)) as standard_name'),
                'a.attendance',
                DB::raw('COALESCE(NULLIF(a.working_day, 0), w.total_working_day) as working_days')
            )
            ->get();

        $byStudent = [];
        foreach ($rows as $row) {
            $workingDays = (int) $row->working_days;
            if ($workingDays <= 0) {
                // No denominator anywhere: the row cannot produce a rate, and
                // guessing one would be the whole failure mode this file exists
                // to avoid. It is counted in the ledger instead.
                continue;
            }

            $id = (string) $row->student_id;
            $byStudent[$id] ??= [
                'studentId' => $id,
                'standardId' => (string) $row->standard_id,
                'standardName' => (string) $row->standard_name,
                'present' => 0,
                'workingDays' => 0,
            ];

            // Days present above the term's working days would lift the rate
            // above 100%; the excess is dropped and reported as a record check.
            $byStudent[$id]['present'] += min((int) $row->attendance, $workingDays);
            $byStudent[$id]['workingDays'] += $workingDays;
        }

        $out = [];
        foreach ($byStudent as $student) {
            $student['percentage'] = round($student['present'] / $student['workingDays'] * 100, 2);
            $out[] = $student;
        }

        return $out;
    }

    /** Every query in this class starts here, so no figure can escape the tenant-year filter. */
    private function scoped(): \Illuminate\Database\Query\Builder
    {
        return DB::table(self::ATTENDANCE_TABLE)
            ->where('sub_institute_id', $this->tenantId)
            ->where('syear', $this->syear);
    }
}
