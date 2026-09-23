<?php

namespace App\Brain\Intelligence;

/**
 * What the timetable means, and what is worth someone's morning.
 *
 * ── WHAT THIS REPLACED ──────────────────────────────────────────────────────
 *
 * The workload rule counted a teacher's TIMETABLE ROWS and called the result
 * their weekly load. A row is one class-division's period on one weekday in one
 * marking period, so the count runs across both terms and every division taught
 * — and the rule reported "129 weekly periods" for a named member of staff at
 * an institute whose week holds 72 slots at the absolute most. It then raised
 * one finding per teacher and capped the list at three with an `array_slice`,
 * which is the shape of a rule written to fill a page.
 *
 * Counted properly — distinct (weekday, period) slots within one marking period
 * — the same institute's heaviest teacher works 46 and the median works 17.
 * Nobody was overloaded. What WAS true, and what nothing in this module could
 * see, is that 427 slots put a teacher in two classrooms at the same time.
 */
final class AcademicSignalRules
{
    /**
     * A genuinely heavy week, once slots are counted as slots.
     *
     * Most school weeks run 40 to 48 teaching periods; a teacher at 40 of them
     * has almost no free period left for preparation or marking.
     */
    private const HEAVY_WEEK_SLOTS = 40;

    /** Teachers named inside one finding's evidence before it stops being readable. */
    private const MAX_NAMED_IN_EVIDENCE = 6;

    public function __construct(
        private readonly AcademicIntelligence $analytics,
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
            'teacher_clash' => ['Teachers timetabled in two classes at once', fn () => $this->teacherClashes()],
            'class_double_booked' => ['Classes carrying two lessons in one slot', fn () => $this->classDoubleBookings()],
            'unassigned_period' => ['Scheduled periods with no teacher', fn () => $this->unassignedPeriods()],
            'heavy_week' => ['Teachers with almost no free period', fn () => $this->heavyWeeks()],
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

    /* ------------------------------------------------------------- clashes */

    /** @return array<int,array<string,mixed>> */
    private function teacherClashes(): array
    {
        $clashes = $this->analytics->teacherClashes();
        if ($clashes === []) {
            return [];
        }

        $position = $this->analytics->position();
        $slots = array_sum(array_column($clashes, 'clashingSlots'));
        $surplus = array_sum(array_column($clashes, 'surplusBookings'));
        $worst = $clashes[0];

        return [[
            'id' => "academic-teacher-clash-{$this->syear}",
            'rule' => 'teacher_clash',
            'severity' => 'high',
            'severityLabel' => 'High',
            'title' => count($clashes).' teachers are timetabled into two classes at the same time',
            'whatHappened' => $this->sentence([
                "The timetable puts {$slots} weekly slots in a position where one teacher is scheduled to be in more "
                    .'than one class-division at once, across '.count($clashes).' teachers.',
                "The most affected is {$worst['label']}, with {$worst['clashingSlots']} such slots.",
                "{$surplus} lessons in total have no teacher who can actually be present for them.",
            ]),
            'whyItMatters' => 'Whichever class the teacher walks into, the other one is unsupervised — every week, at '
                .'the same time, for as long as the timetable stands. This is not a scheduling preference; it is a '
                .'period in which children are in a room and nobody is coming.',
            'evidence' => array_map(static fn ($c) => [
                'label' => $c['label'],
                'value' => "{$c['clashingSlots']} slots",
                'note' => "{$c['surplusBookings']} lessons without a teacher present",
            ], array_slice($clashes, 0, self::MAX_NAMED_IN_EVIDENCE)),
            'likelyCause' => 'A timetable built per standard without a cross-check against the teacher’s own week, '
                .'which is how the same teacher ends up allocated twice in the same slot by two different people.',
            'causeConfirmed' => false,
            'recommendation' => 'Resolve the named teachers’ clashing slots before the term’s timetable is issued — '
                .'each one is a class that will be left alone otherwise.',
            'owner' => 'Academic coordinator',
            'priority' => 'high',
            'confidence' => ['band' => 'High', 'value' => 0.95],
            'affected' => [
                'count' => count($clashes),
                'total' => $position['teachers'] ?? null,
                'unit' => 'teachers',
            ],
            'impact' => ['value' => $surplus, 'display' => (string) $surplus, 'label' => 'lessons with nobody free to teach them'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /** @return array<int,array<string,mixed>> */
    private function classDoubleBookings(): array
    {
        $position = $this->analytics->position();
        $doubled = $position['classDoubleBookings'] ?? 0;

        if ($doubled === 0) {
            return [];
        }

        return [[
            'id' => "academic-class-double-{$this->syear}",
            'rule' => 'class_double_booked',
            'severity' => 'medium',
            'severityLabel' => 'Medium',
            'title' => "{$doubled} class slots carry more than one lesson",
            'whatHappened' => "In {$doubled} weekly slots a single class-division has more than one timetable row "
                .'against it, so two lessons are scheduled for the same children at the same time.',
            'whyItMatters' => 'The class can only attend one of them. Which one it attends is decided on the day by '
                .'whoever arrives first, and the other subject quietly loses that period every week.',
            'evidence' => [
                ['label' => 'Class slots double-booked', 'value' => (string) $doubled],
                ['label' => 'Timetable rows this year', 'value' => (string) ($position['totalPeriods'] ?? 0)],
                ['label' => 'Class-divisions', 'value' => (string) ($position['divisions'] ?? 0)],
                ['label' => 'Standards', 'value' => (string) ($position['standards'] ?? 0)],
            ],
            'likelyCause' => 'Elective or split-batch teaching is recorded the same way as a clash in this table, so '
                .'some of these will be two groups of the same class taught separately. The timetable does not '
                .'distinguish them, and that is itself the problem — nothing downstream can either.',
            'causeConfirmed' => false,
            'recommendation' => 'Separate genuine electives from accidental double-bookings; until they are '
                .'distinguishable, neither a substitution rota nor an attendance register can tell which lesson a '
                .'child should be in.',
            'owner' => 'Academic coordinator',
            'priority' => 'medium',
            'confidence' => ['band' => 'Medium', 'value' => 0.65],
            'affected' => ['count' => $doubled, 'total' => $position['totalPeriods'] ?? null, 'unit' => 'slots'],
            'impact' => ['value' => $doubled, 'display' => (string) $doubled, 'label' => 'slots to disambiguate'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /* --------------------------------------------------------------- gaps */

    /** @return array<int,array<string,mixed>> */
    private function unassignedPeriods(): array
    {
        $position = $this->analytics->position();
        if ($position === null || ($position['unassignedPeriods'] ?? 0) === 0) {
            return [];
        }

        $unassigned = $position['unassignedPeriods'];
        $total = max(1, $position['totalPeriods']);
        $share = round($unassigned / $total * 100, 1);

        return [[
            'id' => "academic-unassigned-{$this->syear}",
            'rule' => 'unassigned_period',
            'severity' => $share >= 5.0 ? 'high' : 'medium',
            'severityLabel' => $share >= 5.0 ? 'High' : 'Medium',
            'title' => "{$unassigned} scheduled periods have no teacher assigned",
            'whatHappened' => "{$unassigned} of {$total} timetable rows ({$share}%) carry no teacher.",
            'whyItMatters' => 'A period with no teacher on the timetable becomes a substitution on the morning it '
                .'falls due, decided by whoever is free. It is the same lesson lost every week until it is filled.',
            'evidence' => [
                ['label' => 'Rows with no teacher', 'value' => (string) $unassigned],
                ['label' => 'Timetable rows', 'value' => (string) $total],
                ['label' => 'Share', 'value' => "{$share}%"],
                ['label' => 'Teachers on the timetable', 'value' => (string) ($position['teachers'] ?? 0)],
            ],
            'likelyCause' => null,
            'causeConfirmed' => false,
            'recommendation' => 'Fill the unassigned slots before the term begins, or record who is covering them so '
                .'the decision is made once rather than every week.',
            'owner' => 'Academic coordinator',
            'priority' => $share >= 5.0 ? 'high' : 'medium',
            'confidence' => ['band' => 'High', 'value' => 0.95],
            'affected' => ['count' => $unassigned, 'total' => $total, 'unit' => 'periods'],
            'impact' => ['value' => $unassigned, 'display' => (string) $unassigned, 'label' => 'periods uncovered'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /**
     * Teachers with almost no free period — ONE finding naming all of them.
     *
     * @return array<int,array<string,mixed>>
     */
    private function heavyWeeks(): array
    {
        $heavy = array_values(array_filter(
            $this->analytics->byTeacherLoad(),
            static fn ($t) => $t['periodsPerWeek'] >= self::HEAVY_WEEK_SLOTS,
        ));

        if ($heavy === []) {
            return [];
        }

        $position = $this->analytics->position();
        $worst = $heavy[0];

        return [[
            'id' => "academic-heavy-week-{$this->syear}",
            'rule' => 'heavy_week',
            'severity' => 'medium',
            'severityLabel' => 'Medium',
            'title' => count($heavy).' teachers are scheduled for '.self::HEAVY_WEEK_SLOTS.' or more periods a week',
            'whatHappened' => $this->sentence([
                count($heavy).' of '.($position['teachers'] ?? 0).' teachers on the timetable work '
                    .self::HEAVY_WEEK_SLOTS.' or more distinct periods in a week.',
                "The heaviest is {$worst['label']} at {$worst['periodsPerWeek']} periods across "
                    ."{$worst['classes']} classes and {$worst['subjects']} subjects.",
                'The median teacher works '.($position['medianPeriodsPerTeacher'] ?? 0).'.',
            ]),
            'whyItMatters' => 'Preparation and marking happen in free periods. A teacher at this load has almost '
                .'none, so both move outside the school day — and the first thing that gives way is the marking '
                .'turnaround the students depend on.',
            'evidence' => array_map(static fn ($t) => [
                'label' => $t['label'],
                'value' => "{$t['periodsPerWeek']} periods a week",
                'note' => "{$t['classes']} classes · {$t['subjects']} subjects · {$t['standards']} standards",
            ], array_slice($heavy, 0, self::MAX_NAMED_IN_EVIDENCE)),
            'likelyCause' => null,
            'causeConfirmed' => false,
            'recommendation' => 'Read this beside the clash finding — a teacher who is double-booked is also counted '
                .'here, and resolving the clash reduces both.',
            'owner' => 'Academic coordinator',
            'priority' => 'medium',
            'confidence' => ['band' => 'High', 'value' => 0.9],
            'affected' => [
                'count' => count($heavy),
                'total' => $position['teachers'] ?? null,
                'unit' => 'teachers',
            ],
            'impact' => [
                'value' => $worst['periodsPerWeek'],
                'display' => (string) $worst['periodsPerWeek'],
                'label' => 'periods for the heaviest week',
            ],
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
