<?php

namespace App\Brain\Intelligence;

use App\Brain\Support\AcademicYear;
use App\Brain\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * Homework Intelligence — one institute, one academic year.
 *
 * ── WHAT ONE ROW MEANS ──────────────────────────────────────────────────────
 *
 * One row of `homework` is ONE PIECE OF WORK SET FOR ONE CHILD — not one piece
 * of work set for a class. A teacher who sets one assignment for forty children
 * writes forty rows. Every count below is therefore a count of child-assignments
 * and says so, because "88 homework items" and "88 children given the same
 * homework" are very different sentences and this table only supports the first.
 *
 * ── WHAT THE PROFILER FOUND, AND WHAT WAS WRONG BEFORE ──────────────────────
 *
 * 1. ZEROS WERE BEING REPORTED WHERE THERE IS NO MODULE. The previous version
 *    returned `completionRate => 0.0` for an institute that has never set a
 *    piece of homework. A head reading "0% completion" concludes their children
 *    did not do their work. Every figure here is NULL in that case, renders as
 *    an em dash, and coverage says which of four reasons applies.
 *
 * 2. A CLASS WAS READ AS A STANDARD. `distinctClasses` counted `standard_id`
 *    alone, so an institute with 5 standards across 15 sections reported 5
 *    classes and divided every per-class figure by a third of the real number.
 *    A class here is (standard_id, division_id), as it is everywhere else.
 *
 * 3. `submission_date` WAS LABELLED THE DUE DATE. It is the date the child
 *    submitted; the date the work was set is `date`. The old data-quality check
 *    reported every unsubmitted piece of work as "no due date" and then said
 *    nothing could be overdue without one — which inverted the meaning of the
 *    only completion signal the table has.
 *
 * 4. `completion_status` IS NOT A TEACHER'S JUDGEMENT. Measured across the whole
 *    database, 'Y' holds exactly when `submission_date` is set, and `reviewed_by`,
 *    `feedback_published` and `teacher_remarks` are populated on ZERO rows out of
 *    1,535. Nothing here has been marked by a teacher. So this module reports a
 *    SUBMISSION rate and never a completion rate, and the gap is a finding.
 *
 * 5. THE SECOND ASSIGNMENT WORKFLOW WAS INVISIBLE. `lms_assignment` is a live,
 *    separately-routed table with its own controllers, its own submission
 *    statuses and its own AI evaluation job. It is counted and named in coverage
 *    below. It is NOT mixed into the homework figures: it has a two-sided status
 *    (the student submits, the teacher returns) that `homework` does not, so
 *    adding the two would produce a rate whose denominator means two things.
 *
 * ── YEAR SCOPING ────────────────────────────────────────────────────────────
 *
 * `homework` carries its own `syear`, which is what scopes every figure. The
 * institute's term dates are read as well, but ONLY to check the `syear` against
 * — one institute files homework dated 2026 under `syear` 2022, and that
 * contradiction is a data-quality check rather than something to silently paper
 * over by preferring one of the two.
 */
final class HomeworkIntelligence
{
    private const HOMEWORK_TABLE = 'homework';

    private const ASSIGNMENT_TABLE = 'lms_assignment';

    private const SUBJECT_TABLE = 'subject';

    private const STANDARD_TABLE = 'standard';

    private const SECTION_TABLE = 'division';

    private const STUDENT_TABLE = 'tblstudent';

    /**
     * The roll. `tblstudent` is the student MASTER and holds no class — a child's
     * standard and section live on their enrolment for the year, which is also
     * the only place that knows they moved up.
     */
    private const ENROLLMENT_TABLE = 'tblstudent_enrollment';

    /** Below this many child-assignments, a subject's rate is about individuals. */
    public const MIN_SUBJECT_COHORT = 10;

    /** Below this many child-assignments, a class's rate is about individuals. */
    public const MIN_CLASS_COHORT = 10;

    /**
     * Share of rows on which the submission status and the submission date may
     * disagree before the status stops being a usable signal.
     *
     * Measured at 100% at one institute — every piece of work carries a date and
     * a status saying it was never handed in.
     */
    public const MAX_CONTRADICTION_SHARE = 10.0;

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
        $empty = [
            'sourceTable' => self::HOMEWORK_TABLE,
            'sources' => [],
            'counts' => [],
            'totalRows' => 0,
            'usableRows' => 0,
        ];

        if ($this->syear === null) {
            return $empty + [
                'available' => false,
                'reason' => 'No academic year was specified or resolved for this institute, and homework is filed by '
                    .'year. Every year at once is not an answer to a question about this one.',
            ];
        }

        if (! SchemaCache::hasTable(self::HOMEWORK_TABLE)) {
            return $empty + [
                'available' => false,
                'reason' => "Table '".self::HOMEWORK_TABLE."' does not exist in this deployment.",
            ];
        }

        $shape = $this->scoped()
            ->selectRaw(
                'COUNT(*) as rows_total,
                 COUNT(DISTINCT student_id) as students,
                 COUNT(DISTINCT NULLIF(subject_id, 0)) as subjects,
                 COUNT(DISTINCT CONCAT(COALESCE(standard_id, 0), "-", COALESCE(division_id, 0))) as classes,
                 SUM(CASE WHEN UPPER(TRIM(COALESCE(completion_status, ""))) = "Y" THEN 1 ELSE 0 END) as submitted,
                 SUM(CASE WHEN reviewed_by IS NOT NULL AND reviewed_by > 0 THEN 1 ELSE 0 END) as reviewed'
            )
            ->first();

        $rows = (int) ($shape->rows_total ?? 0);
        $assignments = $this->assignmentCount();

        if ($rows === 0) {
            return array_merge($empty, [
                'available' => false,
                'counts' => ['assignments' => $assignments],
                'reason' => $assignments > 0
                    ? 'This institute set no homework in '.$this->syear.'. It does hold '.$assignments.' '
                        .'record'.($assignments === 1 ? '' : 's').' in the separate assignment workflow, which is a '
                        .'different table with a different submission model and is reported on its own below.'
                    : 'This institute set no homework in '.$this->syear.'. That is an unused module, not a year in '
                        .'which no work was given.',
            ]);
        }

        return [
            'available' => true,
            'reason' => null,
            'sourceTable' => self::HOMEWORK_TABLE,
            'sources' => [
                'homework' => true,
                // Named separately and never added in: see the class note.
                'assignments' => $assignments > 0,
                'subjects' => SchemaCache::hasTable(self::SUBJECT_TABLE),
                'classes' => SchemaCache::hasTable(self::STANDARD_TABLE),
                // Populated on zero rows database-wide. Saying so is why the
                // marking figures can be honestly absent rather than drawn as 0%.
                'teacherReview' => (int) $shape->reviewed > 0,
                'yearWindow' => $this->yearWindow() !== null,
            ],
            'counts' => [
                'childAssignments' => $rows,
                'students' => (int) $shape->students,
                'subjects' => (int) $shape->subjects,
                'classes' => (int) $shape->classes,
                'submitted' => (int) $shape->submitted,
                'reviewed' => (int) $shape->reviewed,
                'assignments' => $assignments,
            ],
            'totalRows' => $rows,
            'usableRows' => $rows,
            'period' => "Academic Year {$this->syear}",
        ];
    }

    /* -------------------------------------------------------- L1: position */

    /** @return array<string,mixed> */
    public function position(): array
    {
        return $this->memo['position'] ??= $this->computePosition();
    }

    /** @return array<string,mixed> */
    private function computePosition(): array
    {
        $coverage = $this->coverage();

        if (! $coverage['available']) {
            return [
                // NULL, NOT ZERO, throughout. "0% submitted" is a statement about
                // the children; "this institute does not set homework here" is a
                // statement about the institute, and only the second is true.
                'metrics' => [
                    'childAssignments' => null,
                    'students' => null,
                    'classes' => null,
                    'subjects' => null,
                    'submitted' => null,
                    'outstanding' => null,
                    'submissionRate' => null,
                    'reviewed' => null,
                    'reviewRate' => null,
                    'assignments' => $coverage['counts']['assignments'] ?? null,
                    'classReach' => null,
                    'statusReliable' => false,
                    'statusContradictions' => null,
                ],
                'summary' => $coverage['reason'] ?? 'No homework data is available for this year.',
            ];
        }

        $counts = $coverage['counts'];
        $rows = (int) $counts['childAssignments'];
        $submitted = (int) $counts['submitted'];
        $reviewed = (int) $counts['reviewed'];
        $reach = $this->classReach();

        return [
            'metrics' => [
                'childAssignments' => $rows,
                'students' => (int) $counts['students'],
                'classes' => (int) $counts['classes'],
                'subjects' => (int) $counts['subjects'],
                'submitted' => $submitted,
                'outstanding' => $rows - $submitted,
                'submissionRate' => round($submitted / $rows * 100, 1),
                'reviewed' => $reviewed,
                'reviewRate' => round($reviewed / $rows * 100, 1),
                'assignments' => (int) $counts['assignments'],
                // NULL where the roll cannot be read, never 0%.
                'classReach' => $reach['share'],
                // Whether the submission rate above can be believed at all. See
                // statusReliable(); at one institute every row carries a
                // submission date and a status that says it was never submitted.
                'statusReliable' => $this->statusReliable(),
                'statusContradictions' => $this->anomalies()['statusContradiction'],
            ],
            'summary' => $this->summarySentence($counts, $submitted, $rows, $reviewed, $reach),
        ];
    }

    /**
     * Whether `completion_status` can be read as the submission signal at all.
     *
     * At one institute all 88 pieces of homework carry a submission date AND a
     * status of 'N'. Reporting "0% returned" from that is arithmetically correct
     * and completely false — so where the two fields disagree on a material
     * share of rows, the rate is still shown but is marked unreliable, and every
     * sentence built on it carries the contradiction alongside.
     */
    public function statusReliable(): bool
    {
        $rows = (int) ($this->coverage()['counts']['childAssignments'] ?? 0);
        if ($rows === 0) {
            return false;
        }

        return $this->anomalies()['statusContradiction'] / $rows * 100 < self::MAX_CONTRADICTION_SHARE;
    }

    /** @param array<string,mixed> $counts @param array<string,mixed> $reach */
    private function summarySentence(array $counts, int $submitted, int $rows, int $reviewed, array $reach): string
    {
        $children = (int) $counts['students'];
        $classes = (int) $counts['classes'];
        $subjects = (int) $counts['subjects'];
        $contradictions = $this->anomalies()['statusContradiction'];

        $parts = [
            "{$rows} pieces of homework were set for {$children} "
                .($children === 1 ? 'child' : 'children')." across {$classes} "
                .($classes === 1 ? 'class' : 'classes')." and {$subjects} "
                .($subjects === 1 ? 'subject' : 'subjects').'.',
        ];

        $parts[] = $this->statusReliable()
            ? round($submitted / $rows * 100, 1).'% were marked returned ('.$submitted.' of '.$rows.').'
            : 'The submission status says '.round($submitted / $rows * 100, 1).'% were returned ('.$submitted.' of '
                .$rows.') — but '.$contradictions.' of these rows carry a submission date and a status that says '
                .'they were never submitted, so the two fields contradict each other and neither figure can be '
                .'relied on.';

        $parts[] = $reviewed === 0
            ? 'None of them carries a teacher review, so nothing here reflects whether the work was any good — only '
                .'whether it came back.'
            : "{$reviewed} carry a teacher review.";

        if ($reach['share'] !== null && $reach['share'] < 100.0) {
            $parts[] = "The module reaches {$reach['classes']} of the institute's {$reach['total']} classes "
                ."({$reach['share']}%).";
        }

        return implode(' ', $parts);
    }

    /* ---------------------------------------------------- L2: distribution */

    /**
     * How far the module has spread through the institute.
     *
     * Volume alone cannot distinguish a school that sets homework everywhere
     * from one class trying the module out, and the two need completely
     * different reading.
     *
     * @return array{classes:?int,total:?int,share:?float}
     */
    public function classReach(): array
    {
        return $this->memo['reach'] ??= (function (): array {
            if (! SchemaCache::hasTable(self::ENROLLMENT_TABLE) || ! $this->coverage()['available']) {
                return ['classes' => null, 'total' => null, 'share' => null];
            }

            $total = (int) DB::table(self::ENROLLMENT_TABLE)
                ->where('sub_institute_id', $this->tenantId)
                ->where('syear', $this->syear)
                ->whereNotNull('standard_id')
                ->distinct()
                ->count(DB::raw('CONCAT(standard_id, "-", COALESCE(section_id, 0))'));

            if ($total === 0) {
                return ['classes' => null, 'total' => null, 'share' => null];
            }

            $withHomework = (int) $this->coverage()['counts']['classes'];

            return [
                'classes' => $withHomework,
                'total' => $total,
                'share' => round(min($withHomework, $total) / $total * 100, 1),
            ];
        })();
    }

    /**
     * Child-assignments by subject, with the subject NAMED.
     *
     * @return array<int,array<string,mixed>>
     */
    public function bySubject(): array
    {
        return $this->memo['bySubject'] ??= (function (): array {
            if (! $this->coverage()['available']) {
                return [];
            }

            $hasMaster = SchemaCache::hasTable(self::SUBJECT_TABLE);

            $query = $this->scoped('h')->groupBy('h.subject_id');

            if ($hasMaster) {
                $query->leftJoin(self::SUBJECT_TABLE.' as s', 's.id', '=', 'h.subject_id')
                    ->addSelect(DB::raw('NULLIF(TRIM(s.subject_name), "") as subject_name'))
                    ->groupBy('s.subject_name');
            } else {
                $query->addSelect(DB::raw('NULL as subject_name'));
            }

            $rows = $query->addSelect(
                'h.subject_id',
                DB::raw('COUNT(*) as set_count'),
                DB::raw('COUNT(DISTINCT h.student_id) as students'),
                DB::raw('SUM(CASE WHEN UPPER(TRIM(COALESCE(h.completion_status, ""))) = "Y" THEN 1 ELSE 0 END) as submitted')
            )->orderByDesc('set_count')->get();

            return array_map(static function ($row) {
                $set = (int) $row->set_count;
                $submitted = (int) $row->submitted;
                $small = $set < self::MIN_SUBJECT_COHORT;

                return [
                    'key' => (string) $row->subject_id,
                    'label' => $row->subject_name !== null
                        ? (string) $row->subject_name
                        : "Subject #{$row->subject_id} (not in the subject master)",
                    'named' => $row->subject_name !== null,
                    'set' => $set,
                    'students' => (int) $row->students,
                    'submitted' => $submitted,
                    // NULL, NOT ZERO, for a cohort too small to describe without
                    // describing the children in it.
                    'submissionRate' => $small ? null : round($submitted / $set * 100, 1),
                    'suppressed' => $small,
                ];
            }, $rows->all());
        })();
    }

    /**
     * Child-assignments by CLASS — (standard, section), never a standard alone.
     *
     * @return array<int,array<string,mixed>>
     */
    public function byClass(): array
    {
        return $this->memo['byClass'] ??= (function (): array {
            if (! $this->coverage()['available']) {
                return [];
            }

            $query = $this->scoped('h')->groupBy('h.standard_id', 'h.division_id');

            if (SchemaCache::hasTable(self::STANDARD_TABLE)) {
                $query->leftJoin(self::STANDARD_TABLE.' as st', 'st.id', '=', 'h.standard_id')
                    ->addSelect(DB::raw('NULLIF(TRIM(st.name), "") as standard_name'))
                    ->groupBy('st.name');
            } else {
                $query->addSelect(DB::raw('NULL as standard_name'));
            }

            if (SchemaCache::hasTable(self::SECTION_TABLE)) {
                $query->leftJoin(self::SECTION_TABLE.' as dv', 'dv.id', '=', 'h.division_id')
                    ->addSelect(DB::raw('NULLIF(TRIM(dv.name), "") as division_name'))
                    ->groupBy('dv.name');
            } else {
                $query->addSelect(DB::raw('NULL as division_name'));
            }

            $rows = $query->addSelect(
                'h.standard_id',
                'h.division_id',
                DB::raw('COUNT(*) as set_count'),
                DB::raw('COUNT(DISTINCT h.student_id) as students'),
                DB::raw('SUM(CASE WHEN UPPER(TRIM(COALESCE(h.completion_status, ""))) = "Y" THEN 1 ELSE 0 END) as submitted')
            )->orderByDesc('set_count')->get();

            return array_map(static function ($row) {
                $set = (int) $row->set_count;
                $submitted = (int) $row->submitted;
                $small = $set < self::MIN_CLASS_COHORT;

                // Homework with no standard at all is its own row rather than a
                // silently dropped one: it is counted in every total above, so
                // it has to be visible somewhere or the breakdown will not sum.
                $standard = match (true) {
                    $row->standard_id === null || (int) $row->standard_id <= 0 => 'No class recorded',
                    $row->standard_name !== null => (string) $row->standard_name,
                    default => "Standard #{$row->standard_id}",
                };
                $label = $row->division_name !== null && $standard !== 'No class recorded'
                    ? $standard.' '.$row->division_name
                    : $standard;

                return [
                    'key' => ($row->standard_id ?? 0).'-'.($row->division_id ?? 0),
                    'label' => $label,
                    'named' => $row->standard_name !== null,
                    'set' => $set,
                    'students' => (int) $row->students,
                    'submitted' => $submitted,
                    'submissionRate' => $small ? null : round($submitted / $set * 100, 1),
                    'suppressed' => $small,
                ];
            }, $rows->all());
        })();
    }

    /**
     * When the work was set, by month, so a burst is distinguishable from a habit.
     *
     * @return array<int,array<string,mixed>>
     */
    public function byMonth(): array
    {
        return $this->memo['byMonth'] ??= (function (): array {
            if (! $this->coverage()['available']) {
                return [];
            }

            $rows = $this->scoped()
                ->whereNotNull('date')
                ->groupBy(DB::raw('DATE_FORMAT(date, "%Y-%m")'))
                ->orderByRaw('DATE_FORMAT(date, "%Y-%m")')
                ->get([
                    DB::raw('DATE_FORMAT(date, "%Y-%m") as month'),
                    DB::raw('COUNT(*) as set_count'),
                    DB::raw('COUNT(DISTINCT student_id) as students'),
                    DB::raw('SUM(CASE WHEN UPPER(TRIM(COALESCE(completion_status, ""))) = "Y" THEN 1 ELSE 0 END) as submitted'),
                ]);

            return array_map(static function ($row) {
                $set = (int) $row->set_count;

                return [
                    'key' => (string) $row->month,
                    'label' => date('F Y', strtotime($row->month.'-01')),
                    'set' => $set,
                    'students' => (int) $row->students,
                    'submitted' => (int) $row->submitted,
                    'submissionRate' => $set >= self::MIN_CLASS_COHORT
                        ? round((int) $row->submitted / $set * 100, 1)
                        : null,
                    'suppressed' => $set < self::MIN_CLASS_COHORT,
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

        $rows = (int) $coverage['counts']['childAssignments'];
        $anomalies = $this->anomalies();
        $reviewed = (int) $coverage['counts']['reviewed'];

        return [
            'available' => true,
            'reason' => null,
            'checks' => [
                [
                    'key' => 'status_contradicts_submission',
                    'label' => 'Marked not submitted, yet carrying a submission date',
                    'value' => $anomalies['statusContradiction'],
                    'format' => 'count',
                    'sharePercent' => $rows > 0 ? round($anomalies['statusContradiction'] / $rows * 100, 2) : null,
                    'shareLabel' => 'of child-assignments',
                    'state' => $anomalies['statusContradiction'] > 0 ? 'attention' : 'ok',
                    'note' => $anomalies['statusContradiction'] > 0
                        ? 'The status and the date disagree. Every figure on this screen reads the status, so these '
                            .'are counted as outstanding — but a report that reads the date will count them as done, '
                            .'and the two reports will not match.'
                        : 'The submission status and the submission date agree on every piece of work.',
                ],
                [
                    'key' => 'no_teacher_review',
                    'label' => 'Submitted work with no teacher review',
                    'value' => (int) $coverage['counts']['submitted'] - $reviewed,
                    'format' => 'count',
                    'sharePercent' => (int) $coverage['counts']['submitted'] > 0
                        ? round(((int) $coverage['counts']['submitted'] - $reviewed)
                            / (int) $coverage['counts']['submitted'] * 100, 2)
                        : null,
                    'shareLabel' => 'of submitted work',
                    'state' => $reviewed === 0 ? 'attention' : 'ok',
                    'note' => $reviewed === 0
                        ? 'No piece of work this year has been reviewed through the system. The submission figures '
                            .'above therefore say that work came back and nothing at all about whether it was any '
                            .'good — and no child has received feedback through this route.'
                        : 'Some submitted work carries a teacher review.',
                ],
                [
                    'key' => 'no_submission_artefact',
                    'label' => 'Work marked submitted with nothing attached',
                    'value' => $anomalies['noArtefact'],
                    'format' => 'count',
                    'sharePercent' => (int) $coverage['counts']['submitted'] > 0
                        ? round($anomalies['noArtefact'] / (int) $coverage['counts']['submitted'] * 100, 2)
                        : null,
                    'shareLabel' => 'of submitted work',
                    'state' => $anomalies['noArtefact'] > 0 ? 'attention' : 'ok',
                    'note' => $anomalies['noArtefact'] > 0
                        ? 'These carry no image, no file and no remark — the status was set without anything being '
                            .'uploaded. The work may well have been handed in on paper; the system holds no record '
                            .'of it either way.'
                        : 'Every piece of submitted work has something attached to it.',
                ],
                [
                    'key' => 'year_label_mismatch',
                    'label' => 'Homework dated outside the year it is filed under',
                    'value' => $anomalies['outsideYear'],
                    'format' => 'count',
                    'sharePercent' => $rows > 0 ? round($anomalies['outsideYear'] / $rows * 100, 2) : null,
                    'shareLabel' => 'of child-assignments',
                    'state' => $anomalies['outsideYear'] > 0 ? 'attention' : 'ok',
                    'note' => match (true) {
                        $this->yearWindow() === null => 'This institute has no term dates on file for '.$this->syear
                            .', so the dates on its homework cannot be checked against the year it is filed under.',
                        $anomalies['outsideYear'] > 0 => 'The `syear` on these rows and the dates on them disagree. '
                            .'This screen scopes by `syear`, which is what the homework module itself writes and '
                            .'reads — but any report that scopes by date will not find these.',
                        default => 'Every piece of homework is dated inside the academic year it is filed under.',
                    },
                ],
                [
                    'key' => 'unlinked_class',
                    'label' => 'Homework linked to no class',
                    'value' => $anomalies['noClass'],
                    'format' => 'count',
                    'sharePercent' => $rows > 0 ? round($anomalies['noClass'] / $rows * 100, 2) : null,
                    'shareLabel' => 'of child-assignments',
                    'state' => $anomalies['noClass'] > 0 ? 'attention' : 'ok',
                    'note' => $anomalies['noClass'] > 0
                        ? 'These count towards the totals and appear under no class in the breakdown below.'
                        : 'Every piece of homework is linked to a class.',
                ],
                [
                    'key' => 'roll_linkage',
                    'label' => 'Children in the homework who reach the roll',
                    'value' => $anomalies['onRoll'],
                    'format' => 'count',
                    'sharePercent' => $anomalies['students'] > 0
                        ? round($anomalies['onRoll'] / $anomalies['students'] * 100, 2)
                        : null,
                    'shareLabel' => 'of children with homework',
                    'state' => $anomalies['onRoll'] < $anomalies['students'] ? 'attention' : 'ok',
                    'note' => $anomalies['onRoll'] < $anomalies['students']
                        ? ($anomalies['students'] - $anomalies['onRoll']).' of the children with homework this year '
                            .'have no record in the student master, so their work cannot be carried into a report '
                            .'card or shown to a parent.'
                        : 'Every child with homework this year is on the roll.',
                ],
            ],
        ];
    }

    /**
     * The contradictions, counted in one pass.
     *
     * @return array{statusContradiction:int,noArtefact:int,outsideYear:int,noClass:int,students:int,onRoll:int}
     */
    public function anomalies(): array
    {
        return $this->memo['anomalies'] ??= (function (): array {
            $window = $this->yearWindow();

            $row = $this->scoped()
                ->selectRaw(
                    'SUM(CASE WHEN UPPER(TRIM(COALESCE(completion_status, ""))) <> "Y"
                                AND submission_date IS NOT NULL
                                AND CAST(submission_date AS CHAR) NOT LIKE "0000%" THEN 1 ELSE 0 END) as contradiction,
                     SUM(CASE WHEN UPPER(TRIM(COALESCE(completion_status, ""))) = "Y"
                                AND COALESCE(submission_image, "") = ""
                                AND COALESCE(submission_files, "") = ""
                                AND COALESCE(submission_remarks, "") = "" THEN 1 ELSE 0 END) as no_artefact,
                     SUM(CASE WHEN standard_id IS NULL OR standard_id <= 0 THEN 1 ELSE 0 END) as no_class'
                )
                ->first();

            $outsideYear = 0;
            if ($window !== null) {
                $outsideYear = (int) $this->scoped()
                    ->whereNotNull('date')
                    ->where(function ($q) use ($window) {
                        $q->where('date', '<', $window['start'])->orWhere('date', '>', $window['end']);
                    })
                    ->count();
            }

            $students = (int) ($this->coverage()['counts']['students'] ?? 0);
            $onRoll = $students;

            if (SchemaCache::hasTable(self::STUDENT_TABLE) && $students > 0) {
                $onRoll = (int) $this->scoped('h')
                    ->join(self::STUDENT_TABLE.' as s', function ($join) {
                        $join->on('s.id', '=', 'h.student_id')
                            ->on('s.sub_institute_id', '=', 'h.sub_institute_id');
                    })
                    ->distinct()
                    ->count('h.student_id');
            }

            return [
                'statusContradiction' => (int) ($row->contradiction ?? 0),
                'noArtefact' => (int) ($row->no_artefact ?? 0),
                'outsideYear' => $outsideYear,
                'noClass' => (int) ($row->no_class ?? 0),
                'students' => $students,
                'onRoll' => $onRoll,
            ];
        })();
    }

    /* ------------------------------------------------------------ helpers */

    /**
     * How many rows the SEPARATE assignment workflow holds for this year.
     *
     * Counted and reported; never added to the homework figures. `lms_assignment`
     * carries a student status AND a teacher status, so its denominator means
     * something different from `homework`'s and a combined rate would be a
     * number with two meanings.
     */
    public function assignmentCount(): int
    {
        return $this->memo['assignments'] ??= (function (): int {
            if ($this->syear === null || ! SchemaCache::hasTable(self::ASSIGNMENT_TABLE)) {
                return 0;
            }

            return (int) DB::table(self::ASSIGNMENT_TABLE)
                ->where('sub_institute_id', $this->tenantId)
                ->where('syear', $this->syear)
                ->count();
        })();
    }

    /** Homework for this institute and this year. */
    private function scoped(?string $alias = null): \Illuminate\Database\Query\Builder
    {
        $table = $alias === null ? self::HOMEWORK_TABLE : self::HOMEWORK_TABLE.' as '.$alias;
        $prefix = $alias === null ? '' : $alias.'.';

        return DB::table($table)
            ->where($prefix.'sub_institute_id', $this->tenantId)
            ->where($prefix.'syear', $this->syear);
    }
}
