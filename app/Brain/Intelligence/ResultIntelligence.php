<?php

namespace App\Brain\Intelligence;

use App\Brain\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * The academic position of one institute, for one academic year, read from
 * vivek_erp.
 *
 * THIS IS THE ANALYTICS LAYER ONLY — "what is happening". It counts, sums and
 * groups; it draws no conclusions. Interpretation lives in ResultSignalRules,
 * which reads this class and is the only thing allowed to say a number means
 * something. Keeping the two apart is what stops a chart caption from quietly
 * becoming a finding nobody can trace.
 *
 * ── WHICH TABLE, AND WHY IT IS NOT THE OBVIOUS ONE ──────────────────────────
 *
 * Marks come from `result_personalize_marks`, NOT from `result_marks`.
 *
 * `result_marks` is what the name promises and it holds 12 rows in the whole
 * installation. `result_personalize_marks` holds 1.3 million. This was found by
 * `php artisan brain:profile-module result`, and it is the single most
 * important fact in this file: a layer built on the table with the right name
 * would have reported an empty school.
 *
 * ── THE GRAIN ───────────────────────────────────────────────────────────────
 *
 * ONE ROW IS ONE STUDENT'S MARK IN ONE SUBJECT FOR ONE EXAM. Every figure below
 * therefore aggregates twice: marks roll up to a student-subject, and
 * student-subjects roll up to a class or a cohort. A percentage is always
 * SUM(obtain) / SUM(total) over the rows in scope — never an average of
 * percentages, which would weight a 5-mark notebook entry the same as an
 * 80-mark annual paper.
 *
 * ── KEYED vs TEXT-ONLY INSTITUTES ───────────────────────────────────────────
 *
 * The profiler found that some institutes carry this table as a legacy import
 * with `student_id`, `subject_id` and `exam_id` all NULL (institute 254) or all
 * 0 (institute 47), keeping only the denormalised text. Those rows can still be
 * counted and grouped by `student_name` / `subject` / `exam`, but they cannot be
 * joined to the roll or the class master.
 *
 * SO EVERY GROUPING HERE USES THE TEXT COLUMNS, and `coverage()` reports
 * whether the keys are usable. That choice costs nothing on a keyed institute
 * and is the difference between a working screen and an empty one on a legacy
 * institute — and the screen says which kind it is looking at rather than
 * silently showing less.
 *
 * ── HONESTY ─────────────────────────────────────────────────────────────────
 *
 * Nothing here substitutes a zero for an absence. A year with no marks reports
 * `markEntries = 0` AND `hasMarks = false`, and coverage() says so in words, so
 * the layer above can decline to draw a distribution instead of drawing a flat
 * one through nothing. A percentage over a zero maximum is returned as null,
 * because it is undefined rather than zero.
 */
final class ResultIntelligence
{
    /** The table the profiler identified as authoritative. */
    private const MARKS = 'result_personalize_marks';

    /**
     * The share of mark rows that must carry a student id before any figure
     * here can be tied to the roll.
     *
     * Not a tuned number: the split in this database is absolute. One institute
     * keys 100% of its 10,123 entries; another keys 0% of its 152,821. Fifty is
     * the middle of a gap nothing sits inside, and a rate computed over rows
     * that name no student is not that school's rate.
     */
    public const MIN_KEYED_SHARE = 50.0;

    /**
     * The mark share below which a student-subject is reported as below
     * threshold.
     *
     * IT IS NAMED, NOT ASSUMED. 35% is the common CBSE pass mark and it is what
     * this institute's own grade scale uses, but it is not universal — so every
     * figure derived from it is LABELLED with it on the screen ("below 35%")
     * rather than being presented as a pass/fail truth the data does not carry.
     * A school on a different scale gets a correctly-labelled figure, not a
     * wrong one.
     */
    public const THRESHOLD = 35.0;

    private readonly string $tenantId;

    private readonly ?string $syear;

    /** @var array<string, mixed> memo, so one request does not re-run an aggregate */
    private array $memo = [];

    public function __construct(string $tenantId, ?string $syear)
    {
        $this->tenantId = $tenantId;
        // A null year is not defaulted to "this year". Marks are year-owned; a
        // caller that has not resolved one gets the unavailable answer rather
        // than a plausible wrong one.
        $this->syear = $syear === null || $syear === '' ? null : (string) $syear;
    }

    public function syear(): ?string
    {
        return $this->syear;
    }

    /** Every read starts here, so no query can forget the tenant or the year. */
    private function marks()
    {
        return DB::table(self::MARKS)
            ->where('sub_institute_id', $this->tenantId)
            ->where('syear', $this->syear);
    }

    private function memo(string $key, callable $compute)
    {
        return $this->memo[$key] ??= $compute();
    }

    /* ===================================================== data availability */

    /**
     * What this institute-year actually has, in the layer's own words.
     *
     * READ THIS BEFORE ANY FIGURE. Every downstream empty state keys off it.
     */
    public function coverage(): array
    {
        return $this->memo('coverage', function () {
            if (! SchemaCache::hasTable(self::MARKS)) {
                return $this->unavailable('This installation has no '.self::MARKS.' table.');
            }

            if ($this->syear === null) {
                return $this->unavailable('No academic year is selected, and marks are recorded per year.');
            }

            $totals = $this->marks()->selectRaw(
                'COUNT(*) AS rows_,
                 COUNT(DISTINCT student_id) AS keyed_students,
                 COUNT(DISTINCT enrollment_no) AS students,
                 COUNT(DISTINCT subject) AS subjects,
                 COUNT(DISTINCT exam) AS exams,
                 COUNT(DISTINCT standard) AS classes,
                 SUM(CASE WHEN student_id IS NULL OR student_id = 0 THEN 1 ELSE 0 END) AS unkeyed'
            )->first();

            $rows = (int) ($totals->rows_ ?? 0);

            if ($rows === 0) {
                return $this->unavailable(
                    "No marks have been entered for academic year {$this->syear}."
                );
            }

            // A row whose student_id is NULL or 0 cannot be joined to the roll.
            // Reporting the SHARE rather than a boolean matters: an institute
            // that is 3% unkeyed has a data-entry problem, one that is 100%
            // unkeyed has a legacy import, and those need different answers.
            $unkeyed = (int) ($totals->unkeyed ?? 0);
            $keyedShare = $rows > 0 ? round(($rows - $unkeyed) / $rows * 100, 1) : 0.0;
            $keysUsable = $keyedShare >= self::MIN_KEYED_SHARE;

            return [
                'available' => true,
                'reason' => null,
                'syear' => $this->syear,
                'sources' => [
                    'marks' => true,
                    'keyedToRoll' => $keysUsable,
                    'examMaster' => $this->hasRowsIn('result_exam_master', 'SubInstituteId'),
                    'gradeScale' => $this->hasRowsIn('grade_master_data', 'sub_institute_id', true),
                    'coScholastic' => $this->hasRowsIn('result_co_scholastic_grades', 'sub_institute_id'),
                    'attendance' => $this->hasRowsIn('result_student_attendance_master', 'sub_institute_id', true),
                    'previousYear' => $this->previousYearRows() > 0,
                ],
                'counts' => [
                    'markEntries' => $rows,
                    'students' => (int) ($totals->students ?? 0),
                    'subjects' => (int) ($totals->subjects ?? 0),
                    'exams' => (int) ($totals->exams ?? 0),
                    'classes' => (int) ($totals->classes ?? 0),
                    'unkeyedRows' => $unkeyed,
                    'previousYearEntries' => $this->previousYearRows(),
                ],
            ];
        });
    }

    private function unavailable(string $reason): array
    {
        return [
            'available' => false,
            'reason' => $reason,
            'syear' => $this->syear,
            'sources' => [],
            'counts' => [],
        ];
    }

    private function hasRowsIn(string $table, string $tenantColumn, bool $hasYear = false): bool
    {
        if (! SchemaCache::hasTable($table) || ! SchemaCache::hasColumn($table, $tenantColumn)) {
            return false;
        }

        $query = DB::table($table)->where($tenantColumn, $this->tenantId);

        if ($hasYear && $this->syear !== null && SchemaCache::hasColumn($table, 'syear')) {
            $query->where('syear', $this->syear);
        }

        return $query->exists();
    }

    /** The same institute's previous academic year, for trend comparisons. */
    public function previousYear(): ?string
    {
        if ($this->syear === null || ! is_numeric($this->syear)) {
            return null;
        }

        return (string) ((int) $this->syear - 1);
    }

    private function previousYearRows(): int
    {
        return $this->memo('previousYearRows', function () {
            $previous = $this->previousYear();
            if ($previous === null || ! SchemaCache::hasTable(self::MARKS)) {
                return 0;
            }

            return (int) DB::table(self::MARKS)
                ->where('sub_institute_id', $this->tenantId)
                ->where('syear', $previous)
                ->count();
        });
    }

    /* ================================================================ position */

    /**
     * The headline figures.
     *
     * `meanPercentage` is SUM(obtain)/SUM(total) across every mark row — a
     * mark-weighted mean, not an average of student averages. The two differ
     * whenever classes sit different numbers of papers, which they always do.
     */
    public function position(): ?array
    {
        return $this->memo('position', function () {
            $coverage = $this->coverage();
            if (! $coverage['available']) {
                return null;
            }

            $totals = $this->marks()->selectRaw(
                'COUNT(*) AS entries,
                 COUNT(DISTINCT enrollment_no) AS students,
                 COUNT(DISTINCT subject) AS subjects,
                 COUNT(DISTINCT standard) AS classes,
                 COUNT(DISTINCT exam) AS exams,
                 SUM(total) AS max_marks,
                 SUM(obtain) AS obtained'
            )->first();

            $maxMarks = (float) ($totals->max_marks ?? 0);
            $obtained = (float) ($totals->obtained ?? 0);

            $students = $this->studentTotals();
            $belowThreshold = 0;
            $percentages = [];

            foreach ($students as $student) {
                if ($student->max_marks > 0) {
                    $percentage = $student->obtained / $student->max_marks * 100;
                    $percentages[] = $percentage;
                    if ($percentage < self::THRESHOLD) {
                        $belowThreshold++;
                    }
                }
            }

            sort($percentages);
            $count = count($percentages);

            return [
                'markEntries' => (int) ($totals->entries ?? 0),
                'students' => (int) ($totals->students ?? 0),
                'subjects' => (int) ($totals->subjects ?? 0),
                'classes' => (int) ($totals->classes ?? 0),
                'exams' => (int) ($totals->exams ?? 0),
                'maxMarks' => $maxMarks,
                'obtainedMarks' => $obtained,
                // Undefined rather than zero when nothing was out of anything.
                'meanPercentage' => $maxMarks > 0 ? round($obtained / $maxMarks * 100, 1) : null,
                'medianStudentPercentage' => $count > 0 ? round($percentages[intdiv($count, 2)], 1) : null,
                'studentsBelowThreshold' => $belowThreshold,
                'studentsBelowThresholdShare' => $count > 0 ? round($belowThreshold / $count * 100, 1) : null,
                'threshold' => self::THRESHOLD,
            ];
        });
    }

    /** One row per student: their total obtained and total maximum for the year. */
    private function studentTotals()
    {
        return $this->memo('studentTotals', fn () => $this->marks()
            ->selectRaw('enrollment_no, MAX(student_name) AS student_name, MAX(standard) AS standard,
                         SUM(total) AS max_marks, SUM(obtain) AS obtained,
                         COUNT(DISTINCT subject) AS subjects')
            ->groupBy('enrollment_no')
            ->get());
    }

    /* ============================================================== breakdowns */

    /** By class, ordered by how far below the cohort mean they sit. */
    public function byClass(): array
    {
        return $this->memo('byClass', fn () => $this->marks()
            ->selectRaw('standard,
                         COUNT(*) AS entries,
                         COUNT(DISTINCT enrollment_no) AS students,
                         COUNT(DISTINCT subject) AS subjects,
                         SUM(total) AS max_marks,
                         SUM(obtain) AS obtained')
            ->groupBy('standard')
            ->orderByRaw('SUM(obtain) / NULLIF(SUM(total), 0) ASC')
            ->get()
            ->map(fn ($row) => [
                'key' => (string) $row->standard,
                'label' => (string) $row->standard,
                'entries' => (int) $row->entries,
                'students' => (int) $row->students,
                'subjects' => (int) $row->subjects,
                'percentage' => $row->max_marks > 0
                    ? round($row->obtained / $row->max_marks * 100, 1)
                    : null,
            ])
            ->all());
    }

    /** By subject, across every class. */
    public function bySubject(): array
    {
        return $this->memo('bySubject', fn () => $this->marks()
            ->selectRaw('subject,
                         COUNT(*) AS entries,
                         COUNT(DISTINCT enrollment_no) AS students,
                         COUNT(DISTINCT standard) AS classes,
                         SUM(total) AS max_marks,
                         SUM(obtain) AS obtained')
            ->groupBy('subject')
            ->orderByRaw('SUM(obtain) / NULLIF(SUM(total), 0) ASC')
            ->get()
            ->map(fn ($row) => [
                'key' => (string) $row->subject,
                'label' => (string) $row->subject,
                'entries' => (int) $row->entries,
                'students' => (int) $row->students,
                'classes' => (int) $row->classes,
                'percentage' => $row->max_marks > 0
                    ? round($row->obtained / $row->max_marks * 100, 1)
                    : null,
            ])
            ->all());
    }

    /** By exam, which on this data includes non-academic components. */
    public function byExam(): array
    {
        return $this->memo('byExam', fn () => $this->marks()
            ->selectRaw('exam,
                         COUNT(*) AS entries,
                         COUNT(DISTINCT enrollment_no) AS students,
                         SUM(total) AS max_marks,
                         SUM(obtain) AS obtained')
            ->groupBy('exam')
            ->orderByRaw('COUNT(*) DESC')
            ->get()
            ->map(fn ($row) => [
                'key' => (string) $row->exam,
                'label' => (string) $row->exam,
                'entries' => (int) $row->entries,
                'students' => (int) $row->students,
                'percentage' => $row->max_marks > 0
                    ? round($row->obtained / $row->max_marks * 100, 1)
                    : null,
            ])
            ->all());
    }

    /**
     * Every class-subject pair, which is the grain school leaders actually act
     * on — "Class 9 mathematics", not "mathematics" and not "Class 9".
     */
    public function byClassSubject(): array
    {
        return $this->memo('byClassSubject', fn () => $this->marks()
            ->selectRaw('standard, subject,
                         COUNT(*) AS entries,
                         COUNT(DISTINCT enrollment_no) AS students,
                         SUM(total) AS max_marks,
                         SUM(obtain) AS obtained')
            ->groupBy('standard', 'subject')
            ->havingRaw('SUM(total) > 0')
            ->get()
            ->map(fn ($row) => [
                'standard' => (string) $row->standard,
                'subject' => (string) $row->subject,
                'entries' => (int) $row->entries,
                'students' => (int) $row->students,
                'percentage' => round($row->obtained / $row->max_marks * 100, 1),
            ])
            ->all());
    }

    /** Students whose year total sits below the named threshold. */
    public function studentsBelowThreshold(): array
    {
        return $this->memo('studentsBelow', function () {
            $below = [];

            foreach ($this->studentTotals() as $student) {
                if ($student->max_marks <= 0) {
                    continue;
                }

                $percentage = $student->obtained / $student->max_marks * 100;

                if ($percentage < self::THRESHOLD) {
                    $below[] = [
                        'enrollmentNo' => (string) $student->enrollment_no,
                        'name' => (string) $student->student_name,
                        'standard' => (string) $student->standard,
                        'percentage' => round($percentage, 1),
                        'subjects' => (int) $student->subjects,
                    ];
                }
            }

            usort($below, fn ($a, $b) => $a['percentage'] <=> $b['percentage']);

            return $below;
        });
    }

    /** The same institute's mark-weighted mean for the previous year, or null. */
    public function previousYearMean(): ?float
    {
        return $this->memo('previousMean', function () {
            $previous = $this->previousYear();
            if ($previous === null || $this->previousYearRows() === 0) {
                return null;
            }

            $row = DB::table(self::MARKS)
                ->where('sub_institute_id', $this->tenantId)
                ->where('syear', $previous)
                ->selectRaw('SUM(total) AS max_marks, SUM(obtain) AS obtained')
                ->first();

            return $row && $row->max_marks > 0
                ? round($row->obtained / $row->max_marks * 100, 1)
                : null;
        });
    }

    /* ============================================================ data quality */

    /**
     * Checks on the mark ledger itself.
     *
     * These are NOT academic findings and must not be presented as any. "41
     * rows score more than the paper was out of" is a data-entry problem; it
     * says nothing about how the school is teaching, and mixing the two teaches
     * readers to distrust both.
     */
    public function dataQuality(): array
    {
        return $this->memo('dataQuality', function () {
            $coverage = $this->coverage();
            if (! $coverage['available']) {
                return ['available' => false, 'reason' => $coverage['reason'], 'checks' => []];
            }

            $total = (int) $coverage['counts']['markEntries'];

            $impossible = (int) $this->marks()->whereRaw('obtain > total')->count();
            $zeroMax = (int) $this->marks()->where(function ($q) {
                $q->whereNull('total')->orWhere('total', 0);
            })->count();
            $negative = (int) $this->marks()->where('obtain', '<', 0)->count();
            $unkeyed = (int) $coverage['counts']['unkeyedRows'];
            $variants = $this->examNameVariants();
            $linkage = $this->rollLinkage();

            $checks = [
                [
                    'key' => 'impossible_marks',
                    'label' => 'Marks above the maximum',
                    'value' => $impossible,
                    'sharePercent' => $total > 0 ? round($impossible / $total * 100, 2) : null,
                    'state' => $impossible > 0 ? 'attention' : 'ok',
                    'note' => $impossible > 0
                        ? 'Rows where the mark obtained exceeds what the paper was out of. Every percentage they feed is overstated.'
                        : 'No row scores more than its paper maximum.',
                ],
                [
                    'key' => 'zero_maximum',
                    'label' => 'Papers with no maximum',
                    'value' => $zeroMax,
                    'sharePercent' => $total > 0 ? round($zeroMax / $total * 100, 2) : null,
                    'state' => $zeroMax > 0 ? 'attention' : 'ok',
                    'note' => $zeroMax > 0
                        ? 'A mark out of zero has no percentage. These rows are excluded from every rate on this screen.'
                        : 'Every row has a maximum to be measured against.',
                ],
                [
                    'key' => 'negative_marks',
                    'label' => 'Negative marks',
                    'value' => $negative,
                    'sharePercent' => $total > 0 ? round($negative / $total * 100, 2) : null,
                    'state' => $negative > 0 ? 'attention' : 'ok',
                    'note' => $negative > 0 ? 'Rows recording a mark below zero.' : 'No negative marks recorded.',
                ],
                [
                    'key' => 'unkeyed_rows',
                    'label' => 'Rows not linked to a student record',
                    'value' => $unkeyed,
                    'sharePercent' => $total > 0 ? round($unkeyed / $total * 100, 2) : null,
                    'state' => $unkeyed > 0 ? 'attention' : 'ok',
                    // NOT "cannot be joined". They carry `enrollment_no`, which
                    // is the student master's own natural key and resolves for
                    // the large majority of students at every institute
                    // profiled. The surrogate key is missing; the link is not.
                    'note' => $unkeyed > 0
                        ? 'These rows carry no student_id. They are still linkable through enrollment_no — the check '
                            .'below measures how far that reaches — but any join written on student_id will silently '
                            .'drop them.'
                        : 'Every mark row carries a student_id.',
                ],
                [
                    'key' => 'roll_linkage',
                    'label' => 'Students in the marks who reach the roll',
                    'value' => $linkage['matched'],
                    'format' => 'count',
                    'sharePercent' => $linkage['share'],
                    'shareLabel' => 'of students in the marks',
                    'state' => $linkage['share'] !== null && $linkage['share'] < 95.0 ? 'attention' : 'ok',
                    'note' => $linkage['note'],
                ],
                [
                    'key' => 'duplicate_enrollment_numbers',
                    'label' => 'Enrolment numbers held by more than one student',
                    'value' => $linkage['duplicates'],
                    'format' => 'count',
                    'sharePercent' => null,
                    'state' => $linkage['duplicates'] > 0 ? 'attention' : 'ok',
                    'note' => $linkage['duplicates'] > 0
                        ? 'The student master holds the same enrolment number against more than one student, so a '
                            .'join on it multiplies those students’ marks. Every figure on this screen groups by the '
                            .'marks’ own text columns and is therefore unaffected — but a report that joins on '
                            .'enrolment number will over-count.'
                        : 'Every enrolment number in the student master identifies one student.',
                ],
                [
                    'key' => 'exam_name_variants',
                    'label' => 'Exam names that differ only in punctuation',
                    'value' => count($variants),
                    'sharePercent' => null,
                    'state' => count($variants) > 0 ? 'attention' : 'ok',
                    'note' => count($variants) > 0
                        ? 'The same exam is recorded under several spellings, so any per-exam total is split across them: '
                            .implode('; ', array_map(fn ($v) => implode(' / ', $v), array_slice($variants, 0, 3)))
                        : 'Exam names are recorded consistently.',
                ],
            ];

            return ['available' => true, 'reason' => null, 'checks' => $checks];
        });
    }

    /**
     * Exam names that collapse to the same thing once punctuation and spacing
     * are removed — "SA-1", "S.A.-1" and "S.A-1" are one exam recorded three
     * ways, and every per-exam figure is silently split between them.
     *
     * @return array<int, array<int, string>>
     */
    public function examNameVariants(): array
    {
        return $this->memo('examVariants', function () {
            $names = $this->marks()->distinct()->pluck('exam')->filter()->all();
            $grouped = [];

            foreach ($names as $name) {
                $normalised = strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $name) ?? '');
                if ($normalised === '') {
                    continue;
                }
                $grouped[$normalised][] = (string) $name;
            }

            return array_values(array_filter($grouped, fn ($group) => count($group) > 1));
        });
    }

    /**
     * How far the marks reach into the student master, through the key they
     * actually carry.
     *
     * ── WHY enrollment_no AND NOT student_id ────────────────────────────────
     *
     * At several institutes every mark row has a NULL `student_id` and a fully
     * populated `enrollment_no`. The surrogate key was never written by the
     * import; the natural key was. Measured across this database, enrolment
     * number reaches 87.7% of one institute's students and 97.5% of another's —
     * so "these rows cannot be joined to the roll" was wrong, and acting on it
     * would mean rebuilding a link that already works.
     *
     * TWO NUMBERS, NOT ONE. How many reach the student master, and how many
     * enrolment numbers the master has given to more than one student — because
     * the second silently multiplies marks in any report that joins on it, and
     * this screen's own figures are immune only because they group by the marks'
     * own text.
     *
     * @return array{matched:?int,total:?int,share:?float,duplicates:int,note:string}
     */
    private function rollLinkage(): array
    {
        return $this->memo('rollLinkage', function (): array {
            $none = [
                'matched' => null, 'total' => null, 'share' => null, 'duplicates' => 0,
                'note' => 'The student master is not available in this deployment, so the marks cannot be checked '
                    .'against the roll.',
            ];

            if (! SchemaCache::hasTable('tblstudent')) {
                return $none;
            }

            // One grouped pass over the distinct enrolment numbers in scope,
            // rather than a row-by-row lookup over the mark entries.
            $row = DB::table(DB::raw(
                '(SELECT DISTINCT enrollment_no FROM '.self::MARKS.'
                  WHERE sub_institute_id = '.(int) $this->tenantId.'
                    AND syear = '.(int) $this->syear.'
                    AND enrollment_no IS NOT NULL AND TRIM(enrollment_no) <> "") AS m'
            ))
                ->leftJoin('tblstudent as s', function ($join) {
                    $join->on('s.enrollment_no', '=', 'm.enrollment_no')
                        ->where('s.sub_institute_id', '=', $this->tenantId);
                })
                ->selectRaw('COUNT(DISTINCT m.enrollment_no) AS total,
                             COUNT(DISTINCT CASE WHEN s.id IS NOT NULL THEN m.enrollment_no END) AS matched')
                ->first();

            $total = (int) ($row->total ?? 0);
            if ($total === 0) {
                return $none + ['note' => 'No mark row carries an enrolment number, so the marks cannot be reached '
                    .'from the roll by any key.'];
            }

            $matched = (int) ($row->matched ?? 0);
            $share = round($matched / $total * 100, 1);

            $duplicates = (int) DB::table(DB::raw(
                '(SELECT enrollment_no FROM tblstudent
                  WHERE sub_institute_id = '.(int) $this->tenantId.'
                    AND enrollment_no IS NOT NULL AND TRIM(enrollment_no) <> ""
                  GROUP BY enrollment_no HAVING COUNT(*) > 1) AS d'
            ))->count();

            return [
                'matched' => $matched,
                'total' => $total,
                'share' => $share,
                'duplicates' => $duplicates,
                'note' => $share >= 95.0
                    ? $matched.' of '.$total.' students in the marks reach the student master by enrolment number.'
                    : $matched.' of '.$total.' students in the marks reach the student master by enrolment number; '
                        .($total - $matched).' do not. Marks for students the master does not hold cannot be carried '
                        .'into attendance, fees or the report card, and they are usually prior-year entries for '
                        .'students who have since left.',
            ];
        });
    }

}
