<?php

namespace App\Brain\Intelligence;

/**
 * What the roll means, and what is worth someone's morning.
 *
 * ── WHAT CHANGED, AND WHY ───────────────────────────────────────────────────
 *
 * The previous catalogue raised "High student-section density in Noon-6" from a
 * count of students per SECTION rather than per class, so it named a standard
 * that has several sections and described 48 children as though they shared a
 * room. Every rule here reads the real class — a standard and a section
 * together — and the crowding rule states the institute's own median beside the
 * threshold, so a school that runs large classes throughout can see that its
 * outlier is its own norm rather than being told it has a problem.
 *
 * ── GENDER IS COMPARED WITH THE INSTITUTE, NOT WITH 50/50 ───────────────────
 *
 * A class at 70% girls in a school that is 65% girls is not skewed. The old
 * rule compared against parity and would have flagged every class in a
 * single-sex school. The baseline here is the institute's own split, and the
 * finding says what that split is.
 */
final class StudentSignalRules
{
    /** Classes named inside one finding's evidence before it stops being readable. */
    private const MAX_NAMED_IN_EVIDENCE = 6;

    /** How far retention may fall before it is worth naming. */
    private const RETENTION_ALERT = 90.0;

    /** How far a class may sit above the institute's median before size is the story. */
    private const SIZE_SPREAD_FACTOR = 1.5;

    public function __construct(
        private readonly StudentIntelligence $analytics,
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
            'crowded_classes' => ['Classes above the crowding threshold', fn () => $this->crowdedClasses()],
            'uneven_class_sizes' => ['Sections of the same standard filled unevenly', fn () => $this->unevenSections()],
            'gender_composition' => ['Classes composed unlike the institute', fn () => $this->genderComposition()],
            'retention' => ['Students who did not return from last year', fn () => $this->retention()],
            'unplaced_students' => ['Students on the roll with no class', fn () => $this->unplacedStudents()],
        ];

        $findings = [];
        $ruleStatus = [];

        foreach ($rules as $key => [$label, $check]) {
            $raised = $check();
            $ruleStatus[] = ['key' => $key, 'label' => $label, 'checked' => true, 'raised' => $raised !== []];
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

    /* --------------------------------------------------------- class sizes */

    /** @return array<int,array<string,mixed>> */
    private function crowdedClasses(): array
    {
        $position = $this->analytics->position();
        if ($position === null || $position['medianClassSize'] === null) {
            return [];
        }

        $crowded = array_values(array_filter(
            $this->analytics->byClass(),
            static fn ($c) => $c['students'] > StudentIntelligence::CROWDED_CLASS,
        ));

        if ($crowded === []) {
            return [];
        }

        $median = $position['medianClassSize'];
        $students = array_sum(array_column($crowded, 'students'));
        $over = array_sum(array_map(
            static fn ($c) => $c['students'] - StudentIntelligence::CROWDED_CLASS,
            $crowded,
        ));

        // If the institute's own median is already at the threshold, "crowded"
        // is how this school runs and the finding should say so rather than
        // present the norm as an exception.
        $isNorm = $median >= StudentIntelligence::CROWDED_CLASS;

        return [[
            'id' => "student-crowded-classes-{$this->syear}",
            'rule' => 'crowded_classes',
            'severity' => $isNorm ? 'low' : 'medium',
            'severityLabel' => $isNorm ? 'Low' : 'Medium',
            'title' => count($crowded).' classes hold more than '.StudentIntelligence::CROWDED_CLASS.' students',
            'whatHappened' => $this->sentence([
                count($crowded).' of '.$position['classes'].' classes hold more than '
                    .StudentIntelligence::CROWDED_CLASS." students, covering {$students} children.",
                "The largest is {$crowded[0]['label']} with {$crowded[0]['students']}.",
                $isNorm
                    ? "The institute's median class already holds {$median}, so this is close to how the school runs "
                        .'rather than an exception within it.'
                    : "The institute's median class holds {$median}.",
            ]),
            'whyItMatters' => 'Class size is what a timetable, a room and a teacher’s attention are planned around. '
                .'Where one section runs well above the others in the same standard, the difference is usually '
                .'placement rather than demand, and placement can be changed.',
            'evidence' => array_map(static fn ($c) => [
                'label' => $c['label'],
                'value' => "{$c['students']} students",
                'note' => $c['femaleShare'] !== null ? "{$c['femaleShare']}% girls" : 'Gender not recorded',
            ], array_slice($crowded, 0, self::MAX_NAMED_IN_EVIDENCE)),
            'likelyCause' => null,
            'causeConfirmed' => false,
            'recommendation' => 'Read this beside the uneven-sections finding: where a standard has one full section '
                .'and one light one, the crowding is a placement decision rather than a capacity problem.',
            'owner' => 'Academic coordinator',
            'priority' => $isNorm ? 'low' : 'medium',
            'confidence' => ['band' => 'High', 'value' => 0.95],
            'affected' => ['count' => $students, 'total' => $position['students'], 'unit' => 'students'],
            'impact' => ['value' => $over, 'display' => (string) $over, 'label' => 'places above the threshold'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /**
     * Sections of the SAME standard filled unevenly.
     *
     * This is the actionable half of class size: a standard whose sections
     * differ by half again is carrying an imbalance that can be corrected
     * without adding a room or a teacher.
     *
     * @return array<int,array<string,mixed>>
     */
    private function unevenSections(): array
    {
        $byStandard = [];
        foreach ($this->analytics->byClass() as $class) {
            $byStandard[$class['standardKey']][] = $class;
        }

        $uneven = [];
        foreach ($byStandard as $classes) {
            if (count($classes) < 2) {
                continue;
            }

            $sizes = array_column($classes, 'students');
            $largest = max($sizes);
            $smallest = min($sizes);

            if ($smallest < StudentIntelligence::MIN_CLASS_COHORT) {
                // A section of eight is a different decision from an imbalance
                // — it may be a specialism — and comparing it with a full one
                // says nothing useful.
                continue;
            }
            if ($largest < $smallest * self::SIZE_SPREAD_FACTOR) {
                continue;
            }

            usort($classes, static fn ($a, $b) => $b['students'] <=> $a['students']);
            $uneven[] = [
                'standard' => $classes[0]['standardName'],
                'largest' => $classes[0],
                'smallest' => $classes[count($classes) - 1],
                'sections' => count($classes),
                'students' => array_sum($sizes),
                'spread' => $largest - $smallest,
            ];
        }

        if ($uneven === []) {
            return [];
        }

        usort($uneven, static fn ($a, $b) => $b['spread'] <=> $a['spread']);
        $worst = $uneven[0];
        $students = array_sum(array_column($uneven, 'students'));

        return [[
            'id' => "student-uneven-sections-{$this->syear}",
            'rule' => 'uneven_class_sizes',
            'severity' => 'medium',
            'severityLabel' => 'Medium',
            'title' => count($uneven).' standards have sections filled at least half again unevenly',
            'whatHappened' => $this->sentence([
                'In '.count($uneven).' standards the fullest section holds at least half again as many students as '
                    .'the lightest.',
                "The widest is {$worst['standard']}: {$worst['largest']['students']} in "
                    ."{$worst['largest']['label']} against {$worst['smallest']['students']} in "
                    ."{$worst['smallest']['label']}, a spread of {$worst['spread']} students across "
                    ."{$worst['sections']} sections.",
            ]),
            'whyItMatters' => 'Sections of the same standard follow the same syllabus with the same staffing, so a '
                .'spread this wide is a placement outcome rather than a demand one. It is also the cheapest crowding '
                .'to fix: the seats already exist in the same standard.',
            'evidence' => array_map(static fn ($u) => [
                'label' => $u['standard'],
                'value' => "{$u['largest']['students']} vs {$u['smallest']['students']}",
                'note' => "across {$u['sections']} sections · spread of {$u['spread']}",
            ], array_slice($uneven, 0, self::MAX_NAMED_IN_EVIDENCE)),
            'likelyCause' => 'Students placed into sections as they were admitted rather than balanced at the start '
                .'of the year, or a section that carries a subject stream the others do not. This data shows the '
                .'spread; it cannot tell those apart.',
            'causeConfirmed' => false,
            'recommendation' => 'Check whether the fullest and lightest sections of the named standards follow the '
                .'same subjects before rebalancing — where they do, the seats are already there.',
            'owner' => 'Academic coordinator',
            'priority' => 'medium',
            'confidence' => ['band' => 'High', 'value' => 0.9],
            'affected' => [
                'count' => $students,
                'total' => $this->analytics->position()['students'] ?? null,
                'unit' => 'students',
            ],
            'impact' => [
                'value' => $worst['spread'],
                'display' => (string) $worst['spread'],
                'label' => 'students between the widest pair',
            ],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /* -------------------------------------------------------- composition */

    /** @return array<int,array<string,mixed>> */
    private function genderComposition(): array
    {
        $position = $this->analytics->position();
        if ($position === null || $position['femaleShare'] === null) {
            return [];
        }

        $baseline = $position['femaleShare'];

        $skewed = array_values(array_filter(
            $this->analytics->byClass(),
            static fn ($c) => $c['genderRecorded'] >= StudentIntelligence::MIN_CLASS_COHORT
                && $c['femaleShare'] !== null
                && abs($c['femaleShare'] - $baseline) >= StudentIntelligence::GENDER_GAP_POINTS,
        ));

        if ($skewed === []) {
            return [];
        }

        usort(
            $skewed,
            static fn ($a, $b) => abs($b['femaleShare'] - $baseline) <=> abs($a['femaleShare'] - $baseline),
        );

        $students = array_sum(array_column($skewed, 'students'));
        $worst = $skewed[0];
        $gap = round(abs($worst['femaleShare'] - $baseline), 1);

        return [[
            'id' => "student-gender-composition-{$this->syear}",
            'rule' => 'gender_composition',
            'severity' => 'low',
            'severityLabel' => 'Low',
            'title' => count($skewed).' classes are composed unlike the rest of the institute',
            'whatHappened' => $this->sentence([
                'The institute is '.$baseline.'% girls across the students whose gender is recorded.',
                count($skewed).' classes sit at least '.(int) StudentIntelligence::GENDER_GAP_POINTS
                    .' points away from that.',
                "The furthest is {$worst['label']} at {$worst['femaleShare']}%, {$gap} points from the institute.",
            ]),
            'whyItMatters' => 'Where sections of the same standard are composed very differently, the difference is '
                .'usually how students were placed rather than who enrolled. It is worth knowing whether that was a '
                .'decision or an accident — only one of those is reviewable.',
            'evidence' => array_map(static fn ($c) => [
                'label' => $c['label'],
                'value' => "{$c['femaleShare']}% girls",
                'note' => "{$c['female']} girls and {$c['male']} boys of {$c['students']} students",
            ], array_slice($skewed, 0, self::MAX_NAMED_IN_EVIDENCE)),
            // Stated as a hypothesis, because a composition difference is not
            // evidence of a placement decision on its own.
            'likelyCause' => 'A section carrying a subject stream with its own intake pattern, or students placed by '
                .'order of admission. The roll records the composition, not the reason for it.',
            'causeConfirmed' => false,
            'recommendation' => null,
            'owner' => 'Academic coordinator',
            'priority' => 'low',
            'confidence' => $students >= 100
                ? ['band' => 'Medium', 'value' => 0.7]
                : ['band' => 'Low', 'value' => 0.45],
            'affected' => ['count' => $students, 'total' => $position['students'], 'unit' => 'students'],
            'impact' => null,
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /* --------------------------------------------------------- retention */

    /** @return array<int,array<string,mixed>> */
    private function retention(): array
    {
        $position = $this->analytics->position();
        if ($position === null || $position['retentionRate'] === null) {
            return [];
        }
        if ($position['retentionRate'] >= self::RETENTION_ALERT) {
            return [];
        }

        $previousYear = (int) $this->syear - 1;

        return [[
            'id' => "student-retention-{$this->syear}",
            'rule' => 'retention',
            'severity' => $position['retentionRate'] < 80.0 ? 'high' : 'medium',
            'severityLabel' => $position['retentionRate'] < 80.0 ? 'High' : 'Medium',
            'title' => "{$position['notReturning']} students on last year's roll did not return",
            'whatHappened' => $this->sentence([
                "{$position['previousYearRoll']} students were enrolled in {$previousYear} and "
                    ."{$position['returningStudents']} of them are enrolled again in {$this->syear}, a retention rate "
                    ."of {$position['retentionRate']}%.",
                "{$position['newThisYear']} students on this year's roll were not on last year's.",
                $position['endedEnrolments'] > 0
                    ? "{$position['endedEnrolments']} of this year's enrolments already carry an end date."
                    : null,
            ]),
            'whyItMatters' => 'Students leaving is the most expensive thing a school can be unaware of: each one was '
                .'already admitted, already taught and already paying. The intake needed to stand still is exactly '
                .'the number that left.',
            'evidence' => [
                ['label' => "Roll in {$previousYear}", 'value' => (string) $position['previousYearRoll']],
                ['label' => 'Returned', 'value' => (string) $position['returningStudents']],
                ['label' => 'Did not return', 'value' => (string) $position['notReturning']],
                ['label' => 'Retention', 'value' => "{$position['retentionRate']}%"],
                ['label' => 'New this year', 'value' => (string) $position['newThisYear']],
                ['label' => "Roll in {$this->syear}", 'value' => (string) $position['students']],
            ],
            // A student "not returning" can be a school-leaver as easily as a
            // transfer, and this data cannot tell them apart. Saying so is the
            // difference between a finding and an accusation.
            'likelyCause' => 'The terminal standard graduating accounts for part of any year’s non-return, and '
                .'transfers account for the rest. The roll records that a student did not come back, not why — a '
                .'school whose leaving standard holds a hundred students will see a hundred non-returns in a year '
                .'with no attrition at all.',
            'causeConfirmed' => false,
            'recommendation' => 'Separate the leaving standard from the rest before reading this as attrition. The '
                .'number worth acting on is the non-returns from standards that were not the last one.',
            'owner' => 'Principal with the admissions office',
            'priority' => $position['retentionRate'] < 80.0 ? 'high' : 'medium',
            'confidence' => ['band' => 'High', 'value' => 0.9],
            'affected' => [
                'count' => $position['notReturning'],
                'total' => $position['previousYearRoll'],
                'unit' => 'students',
            ],
            'impact' => [
                'value' => $position['notReturning'],
                'display' => (string) $position['notReturning'],
                'label' => 'students not returning',
            ],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /* --------------------------------------------------------- placement */

    /** @return array<int,array<string,mixed>> */
    private function unplacedStudents(): array
    {
        $checks = [];
        foreach ($this->analytics->dataQuality()['checks'] ?? [] as $check) {
            $checks[$check['key']] = $check;
        }

        $noClass = (int) ($checks['no_class']['value'] ?? 0);
        $noSection = (int) ($checks['no_section']['value'] ?? 0);
        $unplaced = max($noClass, $noSection);

        if ($unplaced === 0) {
            return [];
        }

        $position = $this->analytics->position();
        $students = $position['students'] ?? 0;
        $share = $students > 0 ? round($unplaced / $students * 100, 1) : null;

        return [[
            'id' => "student-unplaced-{$this->syear}",
            'rule' => 'unplaced_students',
            'severity' => $share !== null && $share >= 5.0 ? 'high' : 'medium',
            'severityLabel' => $share !== null && $share >= 5.0 ? 'High' : 'Medium',
            'title' => "{$unplaced} students on the roll are not placed in a class",
            'whatHappened' => $this->sentence([
                $noClass > 0 ? "{$noClass} students have no standard recorded." : null,
                $noSection > 0 ? "{$noSection} students have no section recorded." : null,
                $share !== null ? "Together that is {$share}% of the roll." : null,
                'A class here is a standard and a section together, so an unplaced student appears in the totals and '
                    .'in no class below.',
            ]),
            'whyItMatters' => 'A student with no class has no timetable, no register and no class teacher. Every '
                .'module that works class by class — attendance, marks, homework — will pass over them silently.',
            'evidence' => array_values(array_filter([
                $noClass > 0 ? ['label' => 'No standard', 'value' => (string) $noClass] : null,
                $noSection > 0 ? ['label' => 'No section', 'value' => (string) $noSection] : null,
                ['label' => 'Roll this year', 'value' => (string) $students],
                $share !== null ? ['label' => 'Share of the roll', 'value' => "{$share}%"] : null,
            ])),
            'likelyCause' => 'Students admitted after the classes were formed, or an enrolment created from an '
                .'admission record before placement. The roll shows the gap, not its origin.',
            'causeConfirmed' => false,
            'recommendation' => 'Place these students before the next register or mark entry runs, or they will be '
                .'absent from both without appearing to be missing.',
            'owner' => 'Academic coordinator',
            'priority' => $share !== null && $share >= 5.0 ? 'high' : 'medium',
            'confidence' => ['band' => 'High', 'value' => 0.95],
            'affected' => ['count' => $unplaced, 'total' => $students, 'unit' => 'students'],
            'impact' => ['value' => $unplaced, 'display' => (string) $unplaced, 'label' => 'students unplaced'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /** @param array<int,?string> $parts */
    private function sentence(array $parts): string
    {
        return implode(' ', array_filter($parts, static fn ($p) => $p !== null && $p !== ''));
    }
}
