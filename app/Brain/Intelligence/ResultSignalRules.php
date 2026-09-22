<?php

namespace App\Brain\Intelligence;

/**
 * What the figures in ResultIntelligence MEAN.
 *
 * This is the only class allowed to say a number is a problem. ResultIntelligence
 * counts; this interprets. The separation is what makes every sentence on the
 * screen traceable to a rule and a threshold rather than to a chart caption
 * somebody wrote once.
 *
 * ── EVERY RULE DECLARES ITS THRESHOLD, IN THE FINDING ───────────────────────
 *
 * A finding that says "Class 9 mathematics is underperforming" is an opinion. A
 * finding that says "Class 9 mathematics sits 22.4 points below the 68.1% this
 * institute averages in mathematics across its other 13 classes, over 156 mark
 * entries" is a measurement a head of department can check and argue with. Only
 * the second kind is written here, and the evidence array carries the figures.
 *
 * ── WHAT IS DELIBERATELY NOT CONCLUDED ──────────────────────────────────────
 *
 * No rule here claims a CAUSE. A subject-class cluster can be a weak cohort, a
 * hard paper, a teacher vacancy or a marking error, and this data cannot tell
 * those apart. `likelyCause` is therefore set with `causeConfirmed = false` and
 * rendered as "Possible cause, not confirmed". Presenting a hypothesis as a
 * conclusion is the fastest way to lose a reader's trust in every other finding
 * on the page.
 *
 * ── MINIMUM EVIDENCE ────────────────────────────────────────────────────────
 *
 * Every rule has a floor below which it will not fire, because a 40-point gap
 * over three mark entries is noise. The floors are named constants, not magic
 * numbers buried in a condition.
 */
final class ResultSignalRules
{
    /** A class-subject needs at least this many mark entries before it is judged. */
    private const MIN_ENTRIES = 30;

    /**
     * A cohort smaller than this is not compared at all.
     *
     * Mark entries alone are a poor floor: a class of four students sitting six
     * subjects across three exams clears 30 entries easily, and a 26-point gap
     * over four students is noise wearing the costume of a signal. The first run
     * against real data raised exactly that — two commerce streams of four
     * students each outranked a 23-student mathematics cluster that mattered far
     * more.
     */
    private const MIN_STUDENTS = 5;

    /** Below this, a comparison is reported but its confidence is capped. */
    private const SMALL_COHORT = 10;

    /** Points below the subject's own institute-wide mean before a cluster is raised. */
    private const CLUSTER_GAP = 12.0;

    /** Points below the cohort mean before a whole subject is flagged. */
    private const SUBJECT_GAP = 10.0;

    /** Points below the cohort mean before a whole class is flagged. */
    private const CLASS_GAP = 10.0;

    /** Year-on-year movement, in points, before a trend is reported. */
    private const TREND_DELTA = 3.0;

    /** Share of a class below the threshold before the class is called at risk. */
    private const AT_RISK_SHARE = 20.0;

    public function __construct(
        private readonly ResultIntelligence $analytics,
        private readonly ?string $syear,
    ) {
    }

    /**
     * Run every rule and return what fired, plus what was checked.
     *
     * @return array{findings: array, ruleStatus: array}
     */
    public function run(): array
    {
        $coverage = $this->analytics->coverage();

        if (! $coverage['available']) {
            return ['findings' => [], 'ruleStatus' => []];
        }

        $rules = [
            'class_subject_cluster' => ['Weak class-subject pairs', fn () => $this->classSubjectClusters()],
            'weak_subject' => ['Subjects below the cohort', fn () => $this->weakSubjects()],
            'class_outlier' => ['Classes below the cohort', fn () => $this->classOutliers()],
            'students_at_risk' => ['Students below the threshold', fn () => $this->studentsAtRisk()],
            'sparse_entry' => ['Incomplete mark entry', fn () => $this->sparseEntry()],
            'year_on_year' => ['Movement against last year', fn () => $this->yearOnYear()],
        ];

        $findings = [];
        $ruleStatus = [];

        foreach ($rules as $key => [$label, $rule]) {
            try {
                $raised = $rule();
            } catch (\Throwable $e) {
                // One rule that cannot read its table must not abort the other
                // five. The failure is reported rather than swallowed, so a
                // broken rule is visible instead of silently producing "no
                // findings" — which reads identically to "everything is fine".
                $ruleStatus[] = ['key' => $key, 'label' => $label, 'checked' => false, 'raised' => false];

                continue;
            }

            // Tagged HERE rather than in each rule, so a rule added later
            // cannot forget to. ModuleSignalBridge dedupes the signal ledger on
            // (tenant, rule, year); a finding with no rule key cannot be deduped
            // and is skipped, which is how this module's findings never reached
            // a recommendation.
            $raised = array_map(
                static fn (array $finding): array => $finding + ['rule' => $key],
                $raised,
            );

            $findings = array_merge($findings, $raised);
            $ruleStatus[] = [
                'key' => $key,
                'label' => $label,
                'checked' => true,
                'raised' => $raised !== [],
            ];
        }

        // Most severe first, then by how much evidence sits behind it.
        $order = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
        usort($findings, function ($a, $b) use ($order) {
            $bySeverity = ($order[$a['severity']] ?? 9) <=> ($order[$b['severity']] ?? 9);

            return $bySeverity !== 0
                ? $bySeverity
                : ($b['confidence']['value'] <=> $a['confidence']['value']);
        });

        return ['findings' => $findings, 'ruleStatus' => $ruleStatus];
    }

    /* ==================================================================== rules */

    /**
     * A class-subject pair far below what this institute achieves in that same
     * subject elsewhere.
     *
     * COMPARED AGAINST THE SUBJECT, NOT THE COHORT. Mathematics is harder than
     * drawing everywhere; comparing Class 9 maths to the all-subject mean would
     * flag every maths class in the school and tell a head of department
     * nothing. Comparing it to the other classes' maths is the comparison that
     * isolates something actionable.
     */
    private function classSubjectClusters(): array
    {
        $pairs = $this->analytics->byClassSubject();
        $subjectMeans = [];

        foreach ($this->analytics->bySubject() as $subject) {
            if ($subject['percentage'] !== null) {
                $subjectMeans[$subject['key']] = $subject['percentage'];
            }
        }

        $findings = [];

        foreach ($pairs as $pair) {
            $subjectMean = $subjectMeans[$pair['subject']] ?? null;

            if ($subjectMean === null
                || $pair['entries'] < self::MIN_ENTRIES
                || $pair['students'] < self::MIN_STUDENTS) {
                continue;
            }

            $gap = $subjectMean - $pair['percentage'];

            if ($gap < self::CLUSTER_GAP) {
                continue;
            }

            $severity = $gap >= 25 ? 'critical' : ($gap >= 18 ? 'high' : 'medium');

            $findings[] = $this->finding(
                key: 'cluster-'.md5($pair['standard'].$pair['subject']),
                severity: $severity,
                title: "{$pair['standard']} {$pair['subject']}: {$pair['percentage']}% against {$subjectMean}% elsewhere",
                whatHappened: "{$pair['standard']} scored {$pair['percentage']}% in {$pair['subject']} across "
                    ."{$pair['entries']} mark entries for {$pair['students']} students. The same subject averages "
                    ."{$subjectMean}% across this institute's other classes — a gap of ".round($gap, 1).' points.',
                whyItMatters: 'A gap this size against the same subject in other classes is not explained by the '
                    .'subject being difficult. It is localised to this class, which means it has a local answer.',
                evidence: [
                    ['label' => 'This class-subject', 'value' => $pair['percentage'].'%'],
                    ['label' => 'Subject across institute', 'value' => $subjectMean.'%'],
                    ['label' => 'Gap', 'value' => round($gap, 1).' points'],
                    ['label' => 'Mark entries', 'value' => (string) $pair['entries'], 'note' => 'the evidence base'],
                    ['label' => 'Students', 'value' => (string) $pair['students']],
                    ...$this->cohortCaveat($pair['students']),
                ],
                likelyCause: 'A teaching-capacity gap, a harder paper set for this class, or a marking '
                    .'inconsistency. This data cannot distinguish between them.',
                recommendation: "Review the {$pair['subject']} papers and marking for {$pair['standard']} "
                    .'against another class before drawing any conclusion about the cohort.',
                owner: 'Head of department',
                confidence: $this->confidenceFor($pair['entries'], $gap, $pair['students']),
                affected: ['count' => $pair['students'], 'total' => null, 'unit' => 'students'],
                impactValue: $pair['students'],
                impactLabel: 'students in this class-subject',
            );
        }

        // Three is enough to act on in a term. A list of nineteen is a report,
        // and a reader who cannot act on all of it acts on none of it.
        return array_slice($findings, 0, 3);
    }

    /** A subject sitting well below the institute's all-subject mean. */
    private function weakSubjects(): array
    {
        $position = $this->analytics->position();
        $cohortMean = $position['meanPercentage'] ?? null;

        if ($cohortMean === null) {
            return [];
        }

        $findings = [];

        foreach ($this->analytics->bySubject() as $subject) {
            if ($subject['percentage'] === null
                || $subject['entries'] < self::MIN_ENTRIES
                || $subject['students'] < self::MIN_STUDENTS) {
                continue;
            }

            $gap = $cohortMean - $subject['percentage'];

            if ($gap < self::SUBJECT_GAP) {
                continue;
            }

            $findings[] = $this->finding(
                key: 'subject-'.md5($subject['key']),
                severity: $gap >= 20 ? 'high' : 'medium',
                title: "{$subject['label']} sits {$this->points($gap)} below the school average",
                whatHappened: "{$subject['label']} averages {$subject['percentage']}% across {$subject['classes']} "
                    ."classes and {$subject['students']} students, against a school-wide {$cohortMean}%.",
                whyItMatters: 'This is institute-wide rather than confined to one class, so it points at the '
                    .'syllabus, the assessment design or capacity in that department rather than at one cohort.',
                evidence: [
                    ['label' => 'Subject average', 'value' => $subject['percentage'].'%'],
                    ['label' => 'School average', 'value' => $cohortMean.'%'],
                    ['label' => 'Gap', 'value' => $this->points($gap)],
                    ['label' => 'Classes affected', 'value' => (string) $subject['classes']],
                    ['label' => 'Mark entries', 'value' => (string) $subject['entries']],
                    ...$this->cohortCaveat($subject['students']),
                ],
                likelyCause: 'Assessment design or departmental capacity. A subject can also read low simply '
                    .'because its papers are marked harder than others.',
                recommendation: "Compare {$subject['label']} paper difficulty and marking scheme against a "
                    .'subject scoring near the school average.',
                owner: 'Academic head',
                confidence: $this->confidenceFor($subject['entries'], $gap, $subject['students']),
                affected: ['count' => $subject['students'], 'total' => $position['students'], 'unit' => 'students'],
                impactValue: $subject['students'],
                impactLabel: 'students take this subject',
            );
        }

        return array_slice($findings, 0, 2);
    }

    /** A class below the cohort across every subject it sits. */
    private function classOutliers(): array
    {
        $position = $this->analytics->position();
        $cohortMean = $position['meanPercentage'] ?? null;

        if ($cohortMean === null) {
            return [];
        }

        $findings = [];

        foreach ($this->analytics->byClass() as $class) {
            if ($class['percentage'] === null
                || $class['entries'] < self::MIN_ENTRIES
                || $class['students'] < self::MIN_STUDENTS) {
                continue;
            }

            $gap = $cohortMean - $class['percentage'];

            if ($gap < self::CLASS_GAP) {
                continue;
            }

            $findings[] = $this->finding(
                key: 'class-'.md5($class['key']),
                severity: $gap >= 18 ? 'high' : 'medium',
                title: "{$class['label']} is {$this->points($gap)} below the school average",
                whatHappened: "{$class['label']} averages {$class['percentage']}% across {$class['subjects']} "
                    ."subjects and {$class['students']} students, against a school-wide {$cohortMean}%.",
                whyItMatters: 'A class that is down across its whole subject range is a different problem from one '
                    .'weak subject — it usually points at attendance, cohort composition or class-level disruption.',
                evidence: [
                    ['label' => 'Class average', 'value' => $class['percentage'].'%'],
                    ['label' => 'School average', 'value' => $cohortMean.'%'],
                    ['label' => 'Gap', 'value' => $this->points($gap)],
                    ['label' => 'Subjects', 'value' => (string) $class['subjects']],
                    ['label' => 'Students', 'value' => (string) $class['students']],
                    ...$this->cohortCaveat($class['students']),
                ],
                likelyCause: 'Attendance, cohort composition, or disruption affecting the whole class rather than '
                    .'any one subject.',
                recommendation: "Check {$class['label']} attendance against the school average before treating "
                    .'this as an academic problem.',
                owner: 'Class teacher',
                confidence: $this->confidenceFor($class['entries'], $gap, $class['students']),
                affected: ['count' => $class['students'], 'total' => $position['students'], 'unit' => 'students'],
                impactValue: $class['students'],
                impactLabel: 'students in this class',
            );
        }

        return array_slice($findings, 0, 2);
    }

    /** Students whose year total sits below the named threshold. */
    private function studentsAtRisk(): array
    {
        $position = $this->analytics->position();
        $below = $this->analytics->studentsBelowThreshold();

        if ($below === [] || $position === null) {
            return [];
        }

        $share = $position['studentsBelowThresholdShare'];
        $threshold = ResultIntelligence::THRESHOLD;

        // Group them by class, because that is who acts on the list.
        $byClass = [];
        foreach ($below as $student) {
            $byClass[$student['standard']] = ($byClass[$student['standard']] ?? 0) + 1;
        }
        arsort($byClass);

        $evidence = [
            ['label' => 'Students below '.$threshold.'%', 'value' => (string) count($below)],
            ['label' => 'Share of cohort', 'value' => $share !== null ? $share.'%' : '—'],
            ['label' => 'Lowest', 'value' => $below[0]['percentage'].'%',
                'note' => $below[0]['name'].' · '.$below[0]['standard']],
        ];

        foreach (array_slice($byClass, 0, 3, true) as $standard => $count) {
            $evidence[] = ['label' => $standard, 'value' => $count.' students'];
        }

        return [$this->finding(
            key: 'at-risk',
            severity: ($share ?? 0) >= self::AT_RISK_SHARE ? 'high' : 'medium',
            title: count($below)." students are below {$threshold}% for the year",
            whatHappened: count($below).' of '.$position['students'].' students assessed have a year total below '
                ."{$threshold}%"
                .($share !== null ? " — {$share}% of the cohort" : '')
                .'. The largest concentration is '.array_key_first($byClass)
                .' with '.reset($byClass).'.',
            whyItMatters: 'These are the students for whom an intervention this term still changes the year. '
                .'The threshold is named rather than assumed — a school on a different scale should read it as '
                ."\"below {$threshold}%\" rather than as a pass/fail count.",
            evidence: $evidence,
            likelyCause: 'Mixed. A year total this low is usually attendance or an unaddressed gap in an earlier '
                .'year rather than the current syllabus.',
            recommendation: 'Start with the class holding the largest concentration rather than the single lowest '
                .'student — the same intervention reaches more of them.',
            owner: 'Academic head',
            confidence: ['band' => 'High', 'value' => 0.92],
            affected: ['count' => count($below), 'total' => $position['students'], 'unit' => 'students'],
            impactValue: count($below),
            impactLabel: 'students below the threshold',
        )];
    }

    /**
     * Students carrying far fewer subjects than their classmates.
     *
     * This is an OPERATIONAL finding, not an academic one: it almost always
     * means marks were never entered, and it silently drags down every average
     * the student appears in.
     */
    private function sparseEntry(): array
    {
        $students = [];
        foreach ($this->analytics->byClass() as $class) {
            $students[$class['key']] = $class['subjects'];
        }

        $sparse = [];

        foreach ($this->analytics->studentsBelowThreshold() as $student) {
            $classSubjects = $students[$student['standard']] ?? null;

            // Under half their class's subject count is the floor: a student
            // legitimately sitting fewer electives should not be flagged.
            if ($classSubjects !== null && $classSubjects > 0 && $student['subjects'] < $classSubjects / 2) {
                $sparse[] = $student;
            }
        }

        if ($sparse === []) {
            return [];
        }

        return [$this->finding(
            key: 'sparse-entry',
            severity: 'medium',
            title: count($sparse).' students have marks in under half their class\'s subjects',
            whatHappened: count($sparse).' students below the threshold hold mark entries for fewer than half the '
                .'subjects recorded for their class. A missing subject counts as nothing, not as absent, so each '
                .'of these students reads lower than they may actually be.',
            whyItMatters: 'This is a mark-entry gap, not an academic result. Acting on these students as '
                .'underperformers before the entry is completed would be acting on an artefact.',
            evidence: array_merge(
                [['label' => 'Students affected', 'value' => (string) count($sparse)]],
                array_map(
                    fn ($s) => [
                        'label' => $s['name'],
                        'value' => $s['subjects'].' subjects',
                        'note' => $s['standard'].' · '.$s['percentage'].'%',
                    ],
                    array_slice($sparse, 0, 4),
                ),
            ),
            likelyCause: 'Marks not entered for some subjects, rather than subjects not taken.',
            causeConfirmed: false,
            recommendation: 'Complete mark entry for these students before the result is published, then re-read '
                .'the at-risk list.',
            owner: 'Examination cell',
            confidence: ['band' => 'Medium', 'value' => 0.70],
            affected: ['count' => count($sparse), 'total' => null, 'unit' => 'students'],
            impactValue: count($sparse),
            impactLabel: 'students with incomplete entry',
        )];
    }

    /** Movement against the same institute's previous academic year. */
    private function yearOnYear(): array
    {
        $position = $this->analytics->position();
        $previous = $this->analytics->previousYearMean();
        $current = $position['meanPercentage'] ?? null;

        if ($previous === null || $current === null) {
            return [];
        }

        $delta = $current - $previous;

        if (abs($delta) < self::TREND_DELTA) {
            return [];
        }

        $improved = $delta > 0;
        $previousYear = $this->analytics->previousYear();

        return [$this->finding(
            key: 'year-on-year',
            severity: $improved ? 'low' : ($delta <= -6 ? 'high' : 'medium'),
            title: $improved
                ? "School average is up {$this->points($delta)} on {$previousYear}"
                : "School average is down {$this->points(abs($delta))} on {$previousYear}",
            whatHappened: "The mark-weighted average across every subject is {$current}% this year against "
                ."{$previous}% in {$previousYear}.",
            whyItMatters: $improved
                ? 'Worth identifying what changed, so it can be repeated deliberately rather than by accident.'
                : 'A whole-school movement of this size is rarely one cohort. It usually reflects a change in '
                    .'assessment design or in what is being entered.',
            evidence: [
                ['label' => 'This year', 'value' => $current.'%', 'note' => 'syear '.$this->syear],
                ['label' => 'Previous year', 'value' => $previous.'%', 'note' => 'syear '.$previousYear],
                ['label' => 'Movement', 'value' => ($improved ? '+' : '−').$this->points(abs($delta))],
            ],
            likelyCause: 'A change in assessment design or in which components are entered, as often as a change '
                .'in attainment. The two look identical in this data.',
            recommendation: 'Before reading this as attainment, check whether the same exam components were '
                .'entered in both years — the exam mix changing is the more common explanation.',
            owner: 'Academic head',
            confidence: ['band' => 'Medium', 'value' => 0.75],
            affected: ['count' => $position['students'], 'total' => null, 'unit' => 'students'],
            impactValue: null,
            impactLabel: '',
        )];
    }

    /* ================================================================ helpers */

    private function points(float $value): string
    {
        return round($value, 1).' points';
    }

    /**
     * Confidence from the evidence base, the size of the gap, and the size of
     * the cohort it was measured over.
     *
     * ALL THREE MATTER AND THE COHORT MATTERS MOST. A large gap over few rows is
     * not confident. A modest gap over thousands of rows is. And a gap of any
     * size measured across four students is arithmetic, not evidence — one
     * student's bad term moves a four-person average by 25 points, so the cohort
     * term CAPS the result rather than merely contributing to it.
     */
    private function confidenceFor(int $entries, float $gap, ?int $students = null): array
    {
        $evidenceScore = min(1.0, $entries / 300);
        $gapScore = min(1.0, $gap / 30);
        $value = 0.5 + 0.25 * $evidenceScore + 0.25 * $gapScore;

        if ($students !== null && $students < self::SMALL_COHORT) {
            // A hard ceiling, not a subtraction: no amount of evidence elsewhere
            // makes a four-student comparison trustworthy.
            $value = min($value, 0.45 + 0.02 * $students);
        }

        $value = round($value, 2);
        $band = $value >= 0.8 ? 'High' : ($value >= 0.65 ? 'Medium' : 'Low');

        return ['band' => $band, 'value' => $value];
    }

    /** Appended to the evidence of anything measured over a small cohort. */
    private function cohortCaveat(int $students): array
    {
        return $students < self::SMALL_COHORT
            ? [[
                'label' => 'Cohort size',
                'value' => $students.' students',
                'note' => 'small cohort — one student moves this average substantially',
            ]]
            : [];
    }

    private function finding(
        string $key,
        string $severity,
        string $title,
        string $whatHappened,
        ?string $whyItMatters,
        array $evidence,
        ?string $likelyCause,
        ?string $recommendation,
        string $owner,
        array $confidence,
        array $affected,
        ?int $impactValue,
        string $impactLabel,
        bool $causeConfirmed = false,
    ): array {
        return [
            'id' => $key,
            'severity' => $severity,
            'severityLabel' => ucfirst($severity),
            'title' => $title,
            'whatHappened' => $whatHappened,
            'whyItMatters' => $whyItMatters,
            'evidence' => $evidence,
            'likelyCause' => $likelyCause,
            'causeConfirmed' => $causeConfirmed,
            'recommendation' => $recommendation,
            'owner' => $owner,
            'priority' => $severity,
            'confidence' => $confidence,
            'affected' => $affected,
            'raisedAt' => now()->toIso8601String(),
            'impact' => $impactValue !== null
                ? ['value' => $impactValue, 'display' => (string) $impactValue, 'label' => $impactLabel]
                : null,
            'syear' => $this->syear,
            'status' => 'open',
        ];
    }
}
