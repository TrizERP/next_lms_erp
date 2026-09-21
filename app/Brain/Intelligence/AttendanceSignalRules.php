<?php

namespace App\Brain\Intelligence;

/**
 * What the attendance figures mean, and what is worth someone's morning.
 *
 * ── THREE THINGS CHANGED FROM THE FIRST VERSION ─────────────────────────────
 *
 * 1. ONE FINDING PER CLASS, NOT TWO. "High chronic absence in Standard 11 Sci."
 *    and "Standard 11 Sci. attendance significantly below school average" were
 *    raised as separate findings about the same class from the same rows. They
 *    are one fact — that class attends less — and reporting it twice made the
 *    screen look like it had found twice as much as it had.
 *
 * 2. "EXEMPLARY ATTENDANCE IN STANDARD 6" IS NOT A FINDING PER CLASS. Two of
 *    them were raised, capped at two by an `array_slice`, which is the shape of
 *    a rule that exists to populate the page. Strong attendance is worth one
 *    sentence naming every class that achieved it, not one card each.
 *
 * 3. THE ROLL IS CHECKED. A quarter of one institute's students have no
 *    attendance row at all. Nothing in the old catalogue could see that,
 *    because every rule read the register and the register is what is missing.
 *
 * ── SMALL COHORTS ───────────────────────────────────────────────────────────
 *
 * Nothing is compared below {@see AttendanceIntelligence::MIN_CLASS_COHORT}
 * students, findings are ordered by how many children they touch rather than by
 * the size of the percentage gap, and confidence is capped where the cohort is
 * thin — so a class of eleven can never present more strongly than a class of
 * two hundred merely because its gap is wider.
 */
final class AttendanceSignalRules
{
    /** A class must trail the institute by this much before it is a gap rather than noise. */
    private const CLASS_GAP_POINTS = 7.0;

    /** And by this much before it is the first thing a reader should look at. */
    private const SEVERE_CLASS_GAP_POINTS = 12.0;

    /** A share of a class below the chronic line that is worth naming on its own. */
    private const CHRONIC_SHARE_ALERT = 20.0;

    /** Institute-wide share below the board's remediation line that warrants an alert. */
    private const SEVERE_COHORT_SHARE = 5.0;

    /** Share of the roll that may be missing from the register before it is a finding. */
    private const ROLL_GAP_SHARE = 10.0;

    /** Term-on-term movement that counts as a change rather than drift. */
    private const TERM_DRIFT_POINTS = 3.0;

    /** Classes named inside one finding's evidence before it stops being readable. */
    private const MAX_NAMED_IN_EVIDENCE = 6;

    public function __construct(
        private readonly AttendanceIntelligence $analytics,
        private readonly ?string $syear,
    ) {
    }

    /** @return array{findings:array<int,array<string,mixed>>,ruleStatus:array<int,array<string,mixed>>} */
    public function run(): array
    {
        if (! $this->analytics->coverage()['available']) {
            return ['findings' => [], 'ruleStatus' => []];
        }

        $rules = [
            'class_attendance_gap' => [
                'Classes attending materially less than the institute',
                fn () => $this->classAttendanceGap(),
            ],
            'severe_absence_cohort' => [
                'Students below the board’s remediation line',
                fn () => $this->severeAbsenceCohort(),
            ],
            'roll_not_in_register' => [
                'Enrolled students missing from the register',
                fn () => $this->rollNotInRegister(),
            ],
            'term_movement' => [
                'Attendance moving between terms',
                fn () => $this->termMovement(),
            ],
            'sustained_attendance' => [
                'Classes holding attendance above 95%',
                fn () => $this->sustainedAttendance(),
            ],
        ];

        $findings = [];
        $ruleStatus = [];

        foreach ($rules as $key => [$label, $check]) {
            $raised = $check();
            $ruleStatus[] = [
                'key' => $key,
                'label' => $label,
                'checked' => true,
                'raised' => $raised !== [],
            ];
            foreach ($raised as $finding) {
                $findings[] = $finding;
            }
        }

        $rank = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3, 'info' => 4];
        usort($findings, function ($a, $b) use ($rank) {
            $bySeverity = ($rank[$a['severity']] ?? 5) <=> ($rank[$b['severity']] ?? 5);

            return $bySeverity !== 0
                ? $bySeverity
                : ($b['affected']['count'] ?? 0) <=> ($a['affected']['count'] ?? 0);
        });

        return ['findings' => $findings, 'ruleStatus' => $ruleStatus];
    }

    /* ---------------------------------------------------------- class gaps */

    /**
     * ONE finding per class that attends materially less than the institute.
     *
     * The chronic share is stated inside the same finding rather than raised as
     * a second one: a class trailing the institute and a class with many
     * students below 75% are the same rows seen two ways.
     *
     * @return array<int,array<string,mixed>>
     */
    private function classAttendanceGap(): array
    {
        $position = $this->analytics->position();
        if ($position === null || $position['attendanceRate'] === null) {
            return [];
        }

        $instituteRate = $position['attendanceRate'];
        $instituteChronic = $position['chronicAbsenceShare'];
        $findings = [];

        foreach ($this->analytics->byStandard() as $class) {
            if ($class['students'] < AttendanceIntelligence::MIN_CLASS_COHORT) {
                continue;
            }
            if ($class['attendanceRate'] === null) {
                continue;
            }

            $gap = round($instituteRate - $class['attendanceRate'], 1);
            if ($gap < self::CLASS_GAP_POINTS) {
                continue;
            }

            $chronicGap = round($class['chronicShare'] - $instituteChronic, 1);
            $isSevere = $gap >= self::SEVERE_CLASS_GAP_POINTS;

            $findings[] = [
                'id' => "attendance-class-gap-{$class['key']}-{$this->syear}",
                'rule' => 'class_attendance_gap',
                'severity' => $isSevere ? 'high' : 'medium',
                'severityLabel' => $isSevere ? 'High' : 'Medium',
                'title' => "{$class['label']} attends {$class['attendanceRate']}% against {$instituteRate}% "
                    .'across the institute',
                'whatHappened' => $this->sentence([
                    "{$class['students']} students in {$class['label']} attended {$class['attendanceRate']}% of their "
                        ."working days, {$gap} points below the institute's {$instituteRate}%.",
                    $class['chronicCount'] > 0
                        ? "{$class['chronicCount']} of them ({$class['chronicShare']}%) are below the 75% chronic line"
                            .($chronicGap > 0 ? ", {$chronicGap} points above the institute's rate." : '.')
                        : 'No student in the class is below the 75% chronic line, so the gap is spread across the '
                            .'cohort rather than concentrated in a few.',
                    $class['severeCount'] > 0
                        ? "{$class['severeCount']} are below 65%, where the board's remediation provisions apply."
                        : null,
                ]),
                'whyItMatters' => 'A gap this size against the same institute in the same year is not explained by the '
                    .'calendar — every class shares it. It is localised to this class, which means it has a local '
                    .'cause and a local answer.',
                'evidence' => [
                    ['label' => 'This class', 'value' => "{$class['attendanceRate']}%", 'note' => "{$class['students']} students"],
                    ['label' => 'Institute', 'value' => "{$instituteRate}%"],
                    ['label' => 'Gap', 'value' => "{$gap} points"],
                    ['label' => 'Median student here', 'value' => "{$class['medianRate']}%"],
                    ['label' => 'Below 75%', 'value' => "{$class['chronicCount']} ({$class['chronicShare']}%)"],
                    ['label' => 'Below 65%', 'value' => (string) $class['severeCount']],
                ],
                'likelyCause' => 'A timetable or transport friction particular to this class, a cohort with longer '
                    .'journeys, or a register kept differently by one teacher. This data shows the gap; it cannot '
                    .'distinguish between those.',
                'causeConfirmed' => false,
                'recommendation' => 'Compare this class’s week against one attending at the institute rate before '
                    .'drawing any conclusion about the cohort — the answer is usually in the first period or the '
                    .'journey to it.',
                'owner' => 'Class teacher with the academic coordinator',
                'priority' => $isSevere ? 'high' : 'medium',
                'confidence' => $this->cohortConfidence($class['students']),
                'affected' => ['count' => $class['students'], 'total' => $position['students'], 'unit' => 'students'],
                'impact' => [
                    'value' => $class['chronicCount'],
                    'display' => (string) $class['chronicCount'],
                    'label' => 'students below the chronic line',
                ],
                'status' => 'open',
                'syear' => $this->syear,
            ];
        }

        // Most students first. A class of 206 at a 8-point gap outranks a class
        // of 11 at a 20-point gap, which is the correct reading order.
        usort($findings, static fn ($a, $b) => $b['affected']['count'] <=> $a['affected']['count']);

        return $findings;
    }

    /* ------------------------------------------------------- the whole roll */

    /** @return array<int,array<string,mixed>> */
    private function severeAbsenceCohort(): array
    {
        $position = $this->analytics->position();
        if ($position === null || $position['students'] < AttendanceIntelligence::MIN_CLASS_COHORT) {
            return [];
        }
        if ($position['severeAbsenceShare'] < self::SEVERE_COHORT_SHARE) {
            return [];
        }

        // Where they sit matters as much as how many: concentrated in two
        // classes is a different problem from spread across all of them.
        $classes = array_values(array_filter(
            $this->analytics->byStandard(),
            static fn ($c) => $c['severeCount'] > 0,
        ));
        usort($classes, static fn ($a, $b) => $b['severeCount'] <=> $a['severeCount']);

        $topThree = array_sum(array_column(array_slice($classes, 0, 3), 'severeCount'));
        $concentration = $position['severeAbsenceCount'] > 0
            ? round($topThree / $position['severeAbsenceCount'] * 100, 1)
            : null;

        return [[
            'id' => "attendance-severe-cohort-{$this->syear}",
            'rule' => 'severe_absence_cohort',
            'severity' => 'high',
            'severityLabel' => 'High',
            'title' => "{$position['severeAbsenceCount']} students are below 65% attendance",
            'whatHappened' => $this->sentence([
                "{$position['severeAbsenceCount']} of {$position['students']} students with an attendance record "
                    ."({$position['severeAbsenceShare']}%) attended less than 65% of their working days.",
                $concentration !== null && $classes !== []
                    ? "{$concentration}% of them sit in ".min(3, count($classes)).' classes.'
                    : null,
                "A further ".($position['chronicAbsenceCount'] - $position['severeAbsenceCount'])
                    .' are between 65% and 75%.',
            ]),
            'whyItMatters' => 'Most boards require a minimum attendance to sit the terminal examination, and below 65% '
                .'a condonation has to be applied for rather than assumed. The window for doing that closes before '
                .'the examination, not with it.',
            'evidence' => array_merge(
                [
                    ['label' => 'Below 65%', 'value' => (string) $position['severeAbsenceCount']],
                    ['label' => 'Students with a record', 'value' => (string) $position['students']],
                    ['label' => 'Share', 'value' => "{$position['severeAbsenceShare']}%"],
                ],
                array_map(static fn ($c) => [
                    'label' => $c['label'],
                    'value' => "{$c['severeCount']} below 65%",
                    'note' => "of {$c['students']} students, class rate {$c['attendanceRate']}%",
                ], array_slice($classes, 0, self::MAX_NAMED_IN_EVIDENCE - 3)),
            ),
            'likelyCause' => null,
            'causeConfirmed' => false,
            'recommendation' => 'Identify which of these students are within reach of the threshold before the term '
                .'examination, and which will need a condonation filed.',
            'owner' => 'Principal',
            'priority' => 'high',
            'confidence' => ['band' => 'High', 'value' => 0.95],
            'affected' => [
                'count' => $position['severeAbsenceCount'],
                'total' => $position['students'],
                'unit' => 'students',
            ],
            'impact' => [
                'value' => $position['severeAbsenceCount'],
                'display' => (string) $position['severeAbsenceCount'],
                'label' => 'students at the board threshold',
            ],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /**
     * The register is smaller than the roll.
     *
     * This is the rule the old catalogue could not have had: every other rule
     * reads the register, and what is wrong here is what the register does not
     * contain.
     *
     * @return array<int,array<string,mixed>>
     */
    private function rollNotInRegister(): array
    {
        $position = $this->analytics->position();
        if ($position === null || $position['studentsOnRoll'] === null) {
            return [];
        }

        $missing = $position['studentsWithoutRecord'];
        if ($missing === 0) {
            return [];
        }

        $share = round($missing / $position['studentsOnRoll'] * 100, 1);
        if ($share < self::ROLL_GAP_SHARE) {
            return [];
        }

        return [[
            'id' => "attendance-roll-gap-{$this->syear}",
            'rule' => 'roll_not_in_register',
            'severity' => 'high',
            'severityLabel' => 'High',
            'title' => "{$missing} enrolled students have no attendance record at all",
            'whatHappened' => $this->sentence([
                "{$position['studentsOnRoll']} students are enrolled for {$this->syear} and {$position['students']} "
                    ."have an attendance record, leaving {$missing} ({$share}% of the roll) with none.",
                "Every rate on this screen — including the {$position['attendanceRate']}% institute figure — is "
                    .'computed over the students who have one.',
            ]),
            'whyItMatters' => 'Attendance that was never recorded is unknown, not good. A student with no row cannot '
                .'appear as chronically absent however often they are away, so the figures on this page understate '
                .'the problem by exactly the students they cannot see.',
            'evidence' => [
                ['label' => 'Enrolled this year', 'value' => (string) $position['studentsOnRoll']],
                ['label' => 'With an attendance record', 'value' => (string) $position['students']],
                ['label' => 'With none', 'value' => (string) $missing],
                ['label' => 'Share of the roll', 'value' => "{$share}%"],
                ['label' => 'Terms recorded', 'value' => (string) $position['terms']],
            ],
            'likelyCause' => 'A class or section whose register has not been submitted for this year, or students '
                .'enrolled after the register was generated. This data shows the absence, not which of those caused it.',
            'causeConfirmed' => false,
            'recommendation' => 'Establish which classes the missing students belong to before reading any other '
                .'figure on this screen — a rate over three-quarters of the school is not the school’s rate.',
            'owner' => 'Academic coordinator',
            'priority' => 'high',
            'confidence' => ['band' => 'High', 'value' => 0.95],
            'affected' => [
                'count' => $missing,
                'total' => $position['studentsOnRoll'],
                'unit' => 'students',
            ],
            'impact' => ['value' => $missing, 'display' => (string) $missing, 'label' => 'students unaccounted for'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /* --------------------------------------------------------------- trend */

    /**
     * Attendance between terms — the only trend this grain supports.
     *
     * An attendance row carries a term and no date, so this can say the second
     * term attended less than the first and cannot say attendance is falling
     * week by week. Saying only what the grain supports is the difference
     * between a trend and a guess.
     *
     * @return array<int,array<string,mixed>>
     */
    private function termMovement(): array
    {
        $terms = $this->analytics->byTerm();
        if (count($terms) < 2) {
            return [];
        }

        $first = $terms[0];
        $last = $terms[count($terms) - 1];

        if ($first['attendanceRate'] === null || $last['attendanceRate'] === null) {
            return [];
        }

        $change = round($last['attendanceRate'] - $first['attendanceRate'], 1);
        if (abs($change) < self::TERM_DRIFT_POINTS) {
            return [];
        }

        $falling = $change < 0;

        return [[
            'id' => "attendance-term-movement-{$this->syear}",
            'rule' => 'term_movement',
            'severity' => $falling ? 'medium' : 'info',
            'severityLabel' => $falling ? 'Medium' : 'Notable',
            'title' => $falling
                ? "Attendance fell ".abs($change)." points from {$first['label']} to {$last['label']}"
                : "Attendance rose {$change} points from {$first['label']} to {$last['label']}",
            'whatHappened' => $this->sentence([
                "{$first['label']} ran at {$first['attendanceRate']}% across {$first['students']} students; "
                    ."{$last['label']} ran at {$last['attendanceRate']}% across {$last['students']}.",
                $last['students'] < $first['students']
                    ? 'Fewer students have a record in the later term, so part of the movement may be in who was '
                        .'recorded rather than in who attended.'
                    : null,
            ]),
            'whyItMatters' => $falling
                ? 'A term-on-term fall across the whole institute is not a class-level problem — it is the calendar, '
                    .'the season or something the school changed between the terms, and it affects every cohort at '
                    .'once.'
                : 'A term-on-term rise across the whole institute is worth understanding for the same reason a fall '
                    .'would be: whatever moved it, moved it for everyone.',
            'evidence' => array_map(static fn ($t) => [
                'label' => $t['label'],
                'value' => $t['attendanceRate'] === null ? '—' : "{$t['attendanceRate']}%",
                'note' => "{$t['students']} students · {$t['presentDays']} of {$t['workingDays']} days",
            ], $terms),
            'likelyCause' => 'Seasonal absence, examination timing, or a difference in how completely the later term '
                .'has been entered. The register cannot distinguish between them.',
            'causeConfirmed' => false,
            'recommendation' => $falling
                ? 'Check whether the later term is fully entered before treating this as a fall in attendance.'
                : null,
            'owner' => 'Academic coordinator',
            'priority' => $falling ? 'medium' : 'low',
            'confidence' => $last['students'] >= $first['students']
                ? ['band' => 'High', 'value' => 0.85]
                // Fewer records in the later term is a real alternative
                // explanation, so the finding says so and holds its confidence
                // down rather than asserting a fall it cannot separate.
                : ['band' => 'Medium', 'value' => 0.6],
            'affected' => ['count' => $last['students'], 'total' => null, 'unit' => 'students'],
            'impact' => [
                'value' => abs($change),
                'display' => abs($change).' points',
                'label' => $falling ? 'fall between terms' : 'rise between terms',
            ],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /**
     * Classes holding above 95%, as ONE finding naming all of them.
     *
     * The previous version raised one card per class and capped the list at two,
     * which is the shape of a rule written to fill a page. This is the same
     * information in the form a reader can use: which classes, and how they
     * compare with the institute.
     *
     * @return array<int,array<string,mixed>>
     */
    private function sustainedAttendance(): array
    {
        $position = $this->analytics->position();
        if ($position === null || $position['attendanceRate'] === null) {
            return [];
        }

        $strong = array_values(array_filter(
            $this->analytics->byStandard(),
            static fn ($c) => $c['students'] >= AttendanceIntelligence::MIN_CLASS_COHORT
                && $c['attendanceRate'] !== null
                && $c['attendanceRate'] >= 95.0,
        ));

        if ($strong === []) {
            return [];
        }

        usort($strong, static fn ($a, $b) => $b['attendanceRate'] <=> $a['attendanceRate']);
        $students = array_sum(array_column($strong, 'students'));

        return [[
            'id' => "attendance-sustained-{$this->syear}",
            'rule' => 'sustained_attendance',
            'severity' => 'info',
            'severityLabel' => 'Notable',
            'title' => count($strong).' classes are holding attendance at or above 95%',
            'whatHappened' => $this->sentence([
                count($strong).' classes covering '.$students.' students attended 95% or more of their working days, '
                    ."against {$position['attendanceRate']}% across the institute.",
                "The strongest is {$strong[0]['label']} at {$strong[0]['attendanceRate']}%.",
            ]),
            'whyItMatters' => 'These are the classes the ones below the institute rate are being compared against. '
                .'What differs between them — the first period, the journey, the teacher who calls home — is where '
                .'the answer to the gaps above usually is.',
            'evidence' => array_map(static fn ($c) => [
                'label' => $c['label'],
                'value' => "{$c['attendanceRate']}%",
                'note' => "{$c['students']} students · {$c['chronicCount']} below 75%",
            ], array_slice($strong, 0, self::MAX_NAMED_IN_EVIDENCE)),
            'likelyCause' => null,
            'causeConfirmed' => false,
            'recommendation' => null,
            'owner' => 'Academic coordinator',
            'priority' => 'low',
            'confidence' => $this->cohortConfidence($students),
            'affected' => ['count' => $students, 'total' => $position['students'], 'unit' => 'students'],
            'impact' => null,
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /* ------------------------------------------------------------ helpers */

    /**
     * Confidence in the PATTERN, capped by cohort size.
     *
     * The arithmetic is exact either way. What a small cohort cannot support is
     * the claim that the gap is a property of the class rather than of the
     * handful of students in it.
     *
     * @return array{band:string,value:float}
     */
    private function cohortConfidence(int $students): array
    {
        if ($students >= 60) {
            return ['band' => 'High', 'value' => 0.92];
        }
        if ($students >= 25) {
            return ['band' => 'Medium', 'value' => 0.75];
        }

        return ['band' => 'Low', 'value' => 0.5];
    }

    /** @param array<int,?string> $parts */
    private function sentence(array $parts): string
    {
        return implode(' ', array_filter($parts, static fn ($p) => $p !== null && $p !== ''));
    }
}
