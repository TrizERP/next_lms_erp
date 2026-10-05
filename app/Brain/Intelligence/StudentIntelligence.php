<?php

namespace App\Brain\Intelligence;

use App\Brain\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * Student Intelligence — one institute, one academic year.
 *
 * ── WHAT ONE ROW MEANS ──────────────────────────────────────────────────────
 *
 * One row of `tblstudent_enrollment` is ONE STUDENT'S PLACE IN ONE YEAR: the
 * standard and the section they sit in, the quota they were admitted under, the
 * house they belong to, and the dates the place began and ended.
 *
 * ── THE MISTAKE THIS FILE WAS BUILT TO CORRECT ──────────────────────────────
 *
 * A CLASS IS A STANDARD AND A SECTION, NOT EITHER ONE ALONE. The previous
 * version divided the roll by the number of distinct `section_id` values and
 * called the result the average class size. At the largest institute that is
 * 3,902 students over ten sections — an average class of 390 children, printed
 * on the screen as a fact. The sections are named A to I: they are the letter
 * after the standard, not the class. The real unit is the pair, there are 109
 * of them, and the average class holds 36.
 *
 * The same mistake produced the module's loudest finding, "High student-section
 * density in Noon-6" — Noon-6 is a standard with several sections, and no
 * child was ever in a room with the other 47.
 *
 * ── WHAT IS DELIBERATELY NOT READ ───────────────────────────────────────────
 *
 * `tblstudent` carries religion, caste, sub-caste, blood group and Aadhaar.
 * They are not read here and they are not aggregated. A screen that any
 * office-holder can open should not be the place a caste breakdown of the roll
 * becomes available, and no finding in this module needs one to be true.
 */
final class StudentIntelligence
{
    private const ENROLLMENT_TABLE = 'tblstudent_enrollment';

    private const STUDENT_TABLE = 'tblstudent';

    private const STANDARD_TABLE = 'standard';

    private const SECTION_TABLE = 'division';

    private const QUOTA_TABLE = 'student_quota';

    /**
     * A class above this is crowded by the norms most boards publish and most
     * timetables assume. It is a threshold for ATTENTION, not a rule: the
     * finding states the institute's own median beside it so a school that runs
     * larger classes throughout can see that its outlier is its own norm.
     */
    public const CROWDED_CLASS = 45;

    /** Below this, a class's composition is about the individuals in it. */
    public const MIN_CLASS_COHORT = 15;

    /**
     * How far a class's girl/boy split may sit from the institute's own before
     * it is worth naming. Measured against THE INSTITUTE, never against 50/50 —
     * a single-sex or heavily skewed school is not an anomaly in every class.
     */
    public const GENDER_GAP_POINTS = 20.0;

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

        if (! SchemaCache::hasTable(self::ENROLLMENT_TABLE)) {
            return $empty + [
                'available' => false,
                'reason' => "Table '".self::ENROLLMENT_TABLE."' does not exist in this deployment.",
            ];
        }

        $shape = $this->scoped()
            ->selectRaw(
                'COUNT(*) as enrolments,
                 COUNT(DISTINCT student_id) as students,
                 COUNT(DISTINCT standard_id) as standards,
                 COUNT(DISTINCT section_id) as sections,
                 COUNT(DISTINCT CONCAT(standard_id, "-", section_id)) as classes,
                 COUNT(DISTINCT NULLIF(house_id, 0)) as houses,
                 COUNT(DISTINCT NULLIF(student_quota, "")) as quotas,
                 SUM(CASE WHEN end_date IS NOT NULL THEN 1 ELSE 0 END) as ended'
            )
            ->first();

        $students = (int) ($shape->students ?? 0);

        if ($students === 0) {
            return $empty + [
                'available' => false,
                'reason' => "No students are enrolled for academic year {$this->syear}.",
            ];
        }

        $previousYear = $this->previousYearRoll();
        $withProfile = SchemaCache::hasTable(self::STUDENT_TABLE)
            ? (int) $this->scoped()
                ->join(self::STUDENT_TABLE.' as s', 's.id', '=', self::ENROLLMENT_TABLE.'.student_id')
                ->distinct()
                ->count(self::ENROLLMENT_TABLE.'.student_id')
            : 0;

        return [
            'available' => true,
            'reason' => null,
            'sources' => [
                'enrolments' => true,
                'studentProfiles' => $withProfile > 0,
                'classStructure' => (int) $shape->classes > 0,
                'houses' => (int) $shape->houses > 0,
                'quotas' => (int) $shape->quotas > 1,
                // Without last year there is no retention figure, and saying so
                // is better than a rate against an empty denominator.
                'previousYear' => $previousYear > 0,
            ],
            'counts' => [
                'enrolments' => (int) $shape->enrolments,
                'students' => $students,
                'standards' => (int) $shape->standards,
                'sections' => (int) $shape->sections,
                'classes' => (int) $shape->classes,
                'houses' => (int) $shape->houses,
                'quotas' => (int) $shape->quotas,
                'endedEnrolments' => (int) $shape->ended,
                'withProfile' => $withProfile,
                'previousYearRoll' => $previousYear,
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
        $classes = $this->byClass();
        $sizes = array_column($classes, 'students');
        sort($sizes);

        $gender = $this->genderSplit();
        $retention = $this->retention();

        return [
            'students' => (int) $counts['students'],
            'standards' => (int) $counts['standards'],
            'sections' => (int) $counts['sections'],
            'classes' => (int) $counts['classes'],
            // THE CLASS IS THE PAIR. Dividing by sections alone is what put an
            // average class of 390 children on this screen.
            'medianClassSize' => $sizes === [] ? null : $sizes[(int) floor(count($sizes) / 2)],
            'largestClassSize' => $sizes === [] ? null : $sizes[count($sizes) - 1],
            'crowdedClasses' => count(array_filter($sizes, static fn ($n) => $n > self::CROWDED_CLASS)),
            'femaleShare' => $gender['femaleShare'],
            'maleShare' => $gender['maleShare'],
            'genderUnrecorded' => $gender['unrecorded'],
            'returningStudents' => $retention['returning'],
            'previousYearRoll' => $retention['previousRoll'],
            // NULL, NOT ZERO: with no previous year on file, retention is
            // undefined rather than nought per cent.
            'retentionRate' => $retention['rate'],
            'newThisYear' => $retention['new'],
            'notReturning' => $retention['notReturning'],
            'endedEnrolments' => (int) $counts['endedEnrolments'],
        ];
    }

    /* ---------------------------------------------------- L2: distribution */

    /**
     * One row per real class — a standard AND a section.
     *
     * @return array<int,array<string,mixed>>
     */
    public function byClass(): array
    {
        return $this->memo['byClass'] ??= $this->computeByClass();
    }

    /** @return array<int,array<string,mixed>> */
    private function computeByClass(): array
    {
        if (! $this->coverage()['available']) {
            return [];
        }

        $rows = DB::table(self::ENROLLMENT_TABLE.' as e')
            ->leftJoin(self::STANDARD_TABLE.' as st', 'st.id', '=', 'e.standard_id')
            ->leftJoin(self::SECTION_TABLE.' as d', 'd.id', '=', 'e.section_id')
            ->leftJoin(self::STUDENT_TABLE.' as s', 's.id', '=', 'e.student_id')
            ->where('e.sub_institute_id', $this->tenantId)
            ->where('e.syear', $this->syear)
            ->groupBy('e.standard_id', 'e.section_id', 'st.name', 'd.name')
            ->select(
                'e.standard_id',
                'e.section_id',
                DB::raw('COALESCE(NULLIF(st.name, ""), CONCAT("Standard #", e.standard_id)) as standard_name'),
                DB::raw('NULLIF(d.name, "") as section_name'),
                DB::raw('COUNT(DISTINCT e.student_id) as students'),
                DB::raw('SUM(CASE WHEN s.gender = "F" THEN 1 ELSE 0 END) as female'),
                DB::raw('SUM(CASE WHEN s.gender = "M" THEN 1 ELSE 0 END) as male')
            )
            ->orderByDesc('students')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $students = (int) $row->students;
            $female = (int) $row->female;
            $male = (int) $row->male;
            $recorded = $female + $male;

            $out[] = [
                'key' => "{$row->standard_id}-{$row->section_id}",
                'label' => $row->section_name !== null
                    ? "{$row->standard_name} · {$row->section_name}"
                    : (string) $row->standard_name,
                'standardKey' => (string) $row->standard_id,
                'standardName' => (string) $row->standard_name,
                'students' => $students,
                'female' => $female,
                'male' => $male,
                // A share over no recorded genders is undefined, not zero.
                'femaleShare' => $recorded > 0 ? round($female / $recorded * 100, 1) : null,
                'genderRecorded' => $recorded,
            ];
        }

        return $out;
    }

    /** @return array<int,array<string,mixed>> */
    public function byStandard(): array
    {
        return $this->memo['byStandard'] ??= (function (): array {
            $grouped = [];
            foreach ($this->byClass() as $class) {
                $key = $class['standardKey'];
                $grouped[$key] ??= [
                    'key' => $key,
                    'label' => $class['standardName'],
                    'students' => 0,
                    'sections' => 0,
                    'female' => 0,
                    'male' => 0,
                ];
                $grouped[$key]['students'] += $class['students'];
                $grouped[$key]['sections']++;
                $grouped[$key]['female'] += $class['female'];
                $grouped[$key]['male'] += $class['male'];
            }

            $out = [];
            foreach ($grouped as $group) {
                $recorded = $group['female'] + $group['male'];
                $group['avgSectionSize'] = $group['sections'] > 0
                    ? round($group['students'] / $group['sections'], 1)
                    : null;
                $group['femaleShare'] = $recorded > 0 ? round($group['female'] / $recorded * 100, 1) : null;
                $out[] = $group;
            }

            usort($out, static fn ($a, $b) => $b['students'] <=> $a['students']);

            return $out;
        })();
    }

    /**
     * The roll by the year each student was admitted.
     *
     * This is the shape of the school's intake history seen from today: a bulge
     * in one admission year is a cohort moving through, and a thin one is a year
     * the school did not fill.
     *
     * @return array<int,array<string,mixed>>
     */
    public function byAdmissionCohort(): array
    {
        return $this->memo['byCohort'] ??= (function (): array {
            if (! $this->coverage()['available'] || ! SchemaCache::hasTable(self::STUDENT_TABLE)) {
                return [];
            }

            $rows = DB::table(self::ENROLLMENT_TABLE.' as e')
                ->join(self::STUDENT_TABLE.' as s', 's.id', '=', 'e.student_id')
                ->where('e.sub_institute_id', $this->tenantId)
                ->where('e.syear', $this->syear)
                ->whereNotNull('s.admission_year')
                ->where('s.admission_year', '>', 0)
                ->groupBy('s.admission_year')
                ->orderByDesc('s.admission_year')
                ->select('s.admission_year', DB::raw('COUNT(DISTINCT e.student_id) as students'))
                ->get();

            $total = array_sum(array_map(static fn ($r) => (int) $r->students, $rows->all()));

            return array_map(static fn ($row) => [
                'key' => (string) $row->admission_year,
                'label' => "Admitted in {$row->admission_year}",
                'students' => (int) $row->students,
                'share' => $total > 0 ? round((int) $row->students / $total * 100, 1) : null,
            ], $rows->all());
        })();
    }

    /**
     * The roll by admission quota, with the quota NAMED.
     *
     * @return array<int,array<string,mixed>>
     */
    public function byQuota(): array
    {
        return $this->memo['byQuota'] ??= (function (): array {
            if (! $this->coverage()['available']) {
                return [];
            }

            $hasMaster = SchemaCache::hasTable(self::QUOTA_TABLE);

            $query = DB::table(self::ENROLLMENT_TABLE.' as e')
                ->where('e.sub_institute_id', $this->tenantId)
                ->where('e.syear', $this->syear)
                ->whereNotNull('e.student_quota')
                ->where('e.student_quota', '!=', '');

            if ($hasMaster) {
                $query->leftJoin(self::QUOTA_TABLE.' as q', 'q.id', '=', 'e.student_quota')
                    ->groupBy('e.student_quota', 'q.title')
                    ->select(
                        'e.student_quota',
                        DB::raw('NULLIF(q.title, "") as quota_title'),
                        DB::raw('COUNT(DISTINCT e.student_id) as students')
                    );
            } else {
                $query->groupBy('e.student_quota')
                    ->select(
                        'e.student_quota',
                        DB::raw('NULL as quota_title'),
                        DB::raw('COUNT(DISTINCT e.student_id) as students')
                    );
            }

            $rows = $query->orderByDesc('students')->get();
            $total = array_sum(array_map(static fn ($r) => (int) $r->students, $rows->all()));

            return array_map(static fn ($row) => [
                'key' => (string) $row->student_quota,
                // An unresolved quota says so rather than printing its key as
                // though it were a name.
                'label' => $row->quota_title !== null
                    ? (string) $row->quota_title
                    : "Quota #{$row->student_quota} (not in the quota master)",
                'named' => $row->quota_title !== null,
                'students' => (int) $row->students,
                'share' => $total > 0 ? round((int) $row->students / $total * 100, 1) : null,
            ], $rows->all());
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

        $counts = $coverage['counts'];
        $students = (int) $counts['students'];

        $orphaned = (int) $students - (int) $counts['withProfile'];
        $orphaned = max(0, $orphaned);

        $duplicates = (int) $counts['enrolments'] - $students;
        $duplicates = max(0, $duplicates);

        $noClass = (int) $this->scoped()
            ->where(fn ($q) => $q->whereNull('standard_id')->orWhere('standard_id', '<=', 0))
            ->distinct()
            ->count('student_id');

        $noSection = (int) $this->scoped()
            ->where(fn ($q) => $q->whereNull('section_id')->orWhere('section_id', '<=', 0))
            ->distinct()
            ->count('student_id');

        $unnamedQuotas = count(array_filter($this->byQuota(), static fn ($q) => ! $q['named']));

        $missingDob = SchemaCache::hasTable(self::STUDENT_TABLE)
            ? (int) $this->scoped()
                ->join(self::STUDENT_TABLE.' as s', 's.id', '=', self::ENROLLMENT_TABLE.'.student_id')
                ->where(fn ($q) => $q->whereNull('s.dob')->orWhere('s.dob', '0000-00-00')->orWhere('s.dob', ''))
                ->distinct()
                ->count(self::ENROLLMENT_TABLE.'.student_id')
            : 0;

        $gender = $this->genderSplit();

        return [
            'available' => true,
            'reason' => null,
            'checks' => [
                [
                    'key' => 'orphaned_enrolments',
                    'label' => 'Enrolments with no student record',
                    'value' => $orphaned,
                    'format' => 'count',
                    'sharePercent' => $students > 0 ? round($orphaned / $students * 100, 2) : null,
                    'shareLabel' => 'of the roll',
                    'state' => $orphaned > 0 ? 'attention' : 'ok',
                    'note' => $orphaned > 0
                        ? 'These students hold a place this year but have no row in the student master, so nothing '
                            .'about them — name, gender, date of birth — can be read.'
                        : 'Every enrolment resolves to a student record.',
                ],
                [
                    'key' => 'duplicate_enrolments',
                    'label' => 'Students enrolled more than once',
                    'value' => $duplicates,
                    'format' => 'count',
                    'sharePercent' => $students > 0 ? round($duplicates / $students * 100, 2) : null,
                    'shareLabel' => 'of the roll',
                    'state' => $duplicates > 0 ? 'attention' : 'ok',
                    'note' => $duplicates > 0
                        ? "{$counts['enrolments']} enrolment rows cover {$students} students. Every figure here counts "
                            .'students rather than rows, so the duplicates do not inflate them — but a student in two '
                            .'classes appears in both.'
                        : 'Every student holds exactly one place this year.',
                ],
                [
                    'key' => 'no_class',
                    'label' => 'Students with no standard',
                    'value' => $noClass,
                    'format' => 'count',
                    'sharePercent' => $students > 0 ? round($noClass / $students * 100, 2) : null,
                    'shareLabel' => 'of the roll',
                    'state' => $noClass > 0 ? 'attention' : 'ok',
                    'note' => $noClass > 0
                        ? 'These students are on the roll but belong to no standard, so they appear in the totals and '
                            .'in no class below.'
                        : 'Every student on the roll belongs to a standard.',
                ],
                [
                    'key' => 'no_section',
                    'label' => 'Students with no section',
                    'value' => $noSection,
                    'format' => 'count',
                    'sharePercent' => $students > 0 ? round($noSection / $students * 100, 2) : null,
                    'shareLabel' => 'of the roll',
                    'state' => $noSection > 0 ? 'attention' : 'ok',
                    'note' => $noSection > 0
                        ? 'A class here is a standard and a section together. A student with no section is grouped '
                            .'under their standard alone, which makes that group look larger than any real room.'
                        : 'Every student on the roll is placed in a section.',
                ],
                [
                    'key' => 'gender_unrecorded',
                    'label' => 'Students with no gender recorded',
                    'value' => $gender['unrecorded'],
                    'format' => 'count',
                    'sharePercent' => $students > 0 ? round($gender['unrecorded'] / $students * 100, 2) : null,
                    'shareLabel' => 'of the roll',
                    'state' => $gender['unrecorded'] > 0 ? 'attention' : 'ok',
                    'note' => $gender['unrecorded'] > 0
                        ? 'Every gender share on this screen is computed over the students who have one recorded, '
                            .'and these are excluded from both the numerator and the denominator.'
                        : 'Every student on the roll has a gender recorded.',
                ],
                [
                    'key' => 'missing_dob',
                    'label' => 'Students with no date of birth',
                    'value' => $missingDob,
                    'format' => 'count',
                    'sharePercent' => $students > 0 ? round($missingDob / $students * 100, 2) : null,
                    'shareLabel' => 'of the roll',
                    'state' => $missingDob > 0 ? 'attention' : 'ok',
                    'note' => $missingDob > 0
                        ? 'Age-based eligibility — for admission, for board registration, for any age-banded scheme — '
                            .'cannot be checked for these students.'
                        : 'Every student on the roll has a date of birth.',
                ],
                [
                    'key' => 'unnamed_quotas',
                    'label' => 'Quotas missing from the quota master',
                    'value' => $unnamedQuotas,
                    'format' => 'count',
                    'sharePercent' => null,
                    'state' => $unnamedQuotas > 0 ? 'attention' : 'ok',
                    'note' => $unnamedQuotas > 0
                        ? 'These quota keys are in use on the roll but have no row in the quota master, so they can '
                            .'only be shown by number.'
                        : 'Every quota in use resolves to a named row in the quota master.',
                ],
            ],
        ];
    }

    /* ------------------------------------------------------------ helpers */

    /** @return array{female:int,male:int,unrecorded:int,femaleShare:?float,maleShare:?float} */
    public function genderSplit(): array
    {
        return $this->memo['gender'] ??= (function (): array {
            if (! SchemaCache::hasTable(self::STUDENT_TABLE)) {
                return ['female' => 0, 'male' => 0, 'unrecorded' => 0, 'femaleShare' => null, 'maleShare' => null];
            }

            $row = $this->scoped()
                ->leftJoin(self::STUDENT_TABLE.' as s', 's.id', '=', self::ENROLLMENT_TABLE.'.student_id')
                ->selectRaw(
                    'COUNT(DISTINCT CASE WHEN s.gender = "F" THEN s.id END) as female,
                     COUNT(DISTINCT CASE WHEN s.gender = "M" THEN s.id END) as male,
                     COUNT(DISTINCT CASE WHEN s.gender IS NULL OR s.gender NOT IN ("F", "M") THEN '
                         .self::ENROLLMENT_TABLE.'.student_id END) as unrecorded'
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
     * How much of last year's roll came back.
     *
     * @return array{previousRoll:int,returning:int,new:int,notReturning:int,rate:?float}
     */
    public function retention(): array
    {
        return $this->memo['retention'] ??= (function (): array {
            $previous = $this->previousYearRoll();
            if ($previous === 0) {
                return [
                    'previousRoll' => 0,
                    'returning' => 0,
                    'new' => 0,
                    'notReturning' => 0,
                    // No previous year is no denominator. NULL, never 0%.
                    'rate' => null,
                ];
            }

            $returning = (int) DB::table(self::ENROLLMENT_TABLE.' as prev')
                ->join(self::ENROLLMENT_TABLE.' as now', function ($join) {
                    $join->on('now.student_id', '=', 'prev.student_id')
                        ->on('now.sub_institute_id', '=', 'prev.sub_institute_id')
                        ->where('now.syear', '=', $this->syear);
                })
                ->where('prev.sub_institute_id', $this->tenantId)
                ->where('prev.syear', (int) $this->syear - 1)
                ->distinct()
                ->count('prev.student_id');

            $thisYear = (int) $this->coverage()['counts']['students'];

            return [
                'previousRoll' => $previous,
                'returning' => $returning,
                'new' => max(0, $thisYear - $returning),
                'notReturning' => max(0, $previous - $returning),
                'rate' => round($returning / $previous * 100, 1),
            ];
        })();
    }

    private function previousYearRoll(): int
    {
        return $this->memo['previousRoll'] ??= (int) DB::table(self::ENROLLMENT_TABLE)
            ->where('sub_institute_id', $this->tenantId)
            ->where('syear', (int) $this->syear - 1)
            ->distinct()
            ->count('student_id');
    }

    /**
     * Every query in this class starts here, so no figure can escape the
     * tenant-year filter.
     *
     * The columns are TABLE-QUALIFIED because several callers join the student
     * master, which carries its own `sub_institute_id` and would otherwise make
     * the filter ambiguous — and an ambiguous tenant filter is the one kind of
     * error that must never reach a query.
     */
    private function scoped(): \Illuminate\Database\Query\Builder
    {
        return DB::table(self::ENROLLMENT_TABLE)
            ->where(self::ENROLLMENT_TABLE.'.sub_institute_id', $this->tenantId)
            ->where(self::ENROLLMENT_TABLE.'.syear', $this->syear);
    }
}
