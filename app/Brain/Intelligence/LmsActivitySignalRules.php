<?php

namespace App\Brain\Intelligence;

/**
 * What LMS activity means, and what is worth somebody's morning.
 *
 * ── WHAT A RULE HERE MAY NOT DO ─────────────────────────────────────────────
 *
 * IT MAY NOT INVENT A DEADLINE. `homework` carries no due date, only the date
 * the work was set and the date it was submitted, so no rule below computes an
 * "on-time" rate. The turnaround rule below reads a real median lag in days
 * instead, over the rows {@see LmsActivityIntelligence::submissionLagStats()}
 * — through the module's own `position()` metric — has already restricted to
 * submissions whose dates do not contradict the status.
 *
 * IT MAY NOT CALL AN UNUSED HALF A FAILING ONE. The blend rules below are
 * gated on a real course-pair cohort ({@see LmsActivityIntelligence::MIN_CROSS_COURSES}),
 * and the volume rule is gated on `HomeworkIntelligence`'s own coverage and
 * cohort floor. An institute that runs the content library and not the
 * homework module — or the other way round — is not told its alignment is
 * zero; it is told nothing, because a share computed over an unused half
 * describes the absence of the module, not a gap in it.
 *
 * IT MAY NOT READ A FILE, A REMARK OR A NAME. Every figure here is a count or
 * a share over (class, subject) pairs and dates — never a title, description
 * or student-identifying field.
 */
final class LmsActivitySignalRules
{
    /**
     * Above this share of content-bearing courses carrying no homework, the
     * gap describes how the two are used rather than a handful of courses
     * nobody has got to yet.
     */
    private const CONTENT_WITHOUT_ACTIVITY_THRESHOLD = 40.0;

    /** Below this submission rate, most work set is not coming back. */
    private const LOW_SUBMISSION_RATE_THRESHOLD = 50.0;

    /** Above this many days' median turnaround, work is not returned inside a normal week. */
    private const HIGH_LAG_DAYS = 5.0;

    /**
     * Above this many pairs, homework set outside the catalogue is a pattern
     * in how the two systems are used rather than one or two stray rows.
     */
    private const OUTSIDE_CATALOGUE_THRESHOLD = 5;

    public function __construct(
        private readonly LmsActivityIntelligence $analytics,
        private readonly ?string $syear,
    ) {
    }

    /** @return array{findings:array<int,array<string,mixed>>,ruleStatus:array<int,array<string,mixed>>} */
    public function run(): array
    {
        $coverage = $this->analytics->coverage();

        // Neither half in use: the module is not running here, and an unused
        // module raises nothing rather than everything.
        if (! $coverage['available']) {
            return ['findings' => [], 'ruleStatus' => []];
        }

        $rules = [
            'content_without_activity' => [
                'Published content carrying no homework activity',
                fn () => $this->contentWithoutActivity(),
            ],
            'activity_outside_catalogue' => [
                'Homework set outside the mapped course catalogue',
                fn () => $this->activityOutsideCatalogue(),
            ],
            'low_submission_rate' => [
                'Most homework set this year is not coming back',
                fn () => $this->lowSubmissionRate(),
            ],
            'high_submission_lag' => [
                'Submitted work taking well over a week to come back',
                fn () => $this->highSubmissionLag(),
            ],
        ];

        $findings = [];
        $ruleStatus = [];

        foreach ($rules as $key => [$label, $check]) {
            $raised = $check();
            $ruleStatus[] = ['key' => $key, 'label' => $label, 'checked' => true, 'raised' => $raised !== []];

            foreach ($raised as $finding) {
                $findings[] = $finding + ['rule' => $key];
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

    /* --------------------------------------------- published, never assigned */

    /** @return array<int,array<string,mixed>> */
    private function contentWithoutActivity(): array
    {
        $position = $this->analytics->position();
        $alignment = $position['metrics']['contentActivityAlignment'] ?? null;

        if ($alignment === null) {
            // Below MIN_CROSS_COURSES — too few content-bearing courses to
            // say anything about the institute rather than a handful of them.
            return [];
        }

        $noActivityShare = round(100.0 - $alignment, 1);
        if ($noActivityShare < self::CONTENT_WITHOUT_ACTIVITY_THRESHOLD) {
            return [];
        }

        $withoutActivity = (int) ($position['metrics']['contentWithoutActivity'] ?? 0);
        $withContent = (int) ($position['metrics']['coursesWithContent'] ?? 0);

        if ($withoutActivity === 0 || $withContent === 0) {
            return [];
        }

        return [[
            'id' => "lms-activity-content-without-activity-{$this->syear}",
            'severity' => $noActivityShare >= 75.0 ? 'high' : 'medium',
            'severityLabel' => $noActivityShare >= 75.0 ? 'High' : 'Medium',
            'title' => "{$withoutActivity} of {$withContent} courses with published content carry no homework "
                ."for {$this->syear}",
            'whatHappened' => $this->sentence([
                "{$withContent} courses carry published teaching content for {$this->syear}.",
                "{$withoutActivity} of them ({$noActivityShare}%) have no homework set against the same class and "
                    .'subject this year.',
                'The two are read from separate tables — content_master and homework — matched on the same '
                    .'(class, subject) pair, tenant- and year-scoped on both sides.',
            ]),
            'whyItMatters' => 'Published material and set work are different signals: one says a subject has '
                .'something to teach from, the other says children were actually asked to use it. A course can be '
                .'fully stocked and never assigned from, and neither the catalogue screen nor the homework screen '
                .'shows that on its own — each only knows about its own table.',
            'evidence' => [
                ['label' => 'Courses with content', 'value' => (string) $withContent],
                ['label' => 'Also carrying homework', 'value' => (string) ($withContent - $withoutActivity)],
                ['label' => 'Content with no homework', 'value' => (string) $withoutActivity],
                ['label' => 'Share', 'value' => "{$noActivityShare}%"],
                ['label' => 'Academic year', 'value' => (string) $this->syear],
            ],
            'likelyCause' => 'Homework is set by whoever teaches a subject day to day, independently of whether that '
                .'subject has a published library. The two workflows are not linked in this deployment.',
            'causeConfirmed' => false,
            'recommendation' => 'Take the courses from the class breakdown below and decide, subject by subject, '
                .'whether the published material is meant to feed homework. Where it is not, this gap is expected '
                .'and the figure is a description rather than a problem to close.',
            'owner' => 'Academic head',
            'priority' => $noActivityShare >= 75.0 ? 'high' : 'medium',
            'confidence' => ['band' => 'High', 'value' => 0.9],
            'affected' => ['count' => $withoutActivity, 'total' => $withContent, 'unit' => 'courses'],
            'impact' => ['value' => $withoutActivity, 'display' => (string) $withoutActivity, 'label' => 'courses with content and no homework'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /* --------------------------------------------------- assigned, uncatalogued */

    /** @return array<int,array<string,mixed>> */
    private function activityOutsideCatalogue(): array
    {
        $position = $this->analytics->position();
        $outside = (int) ($position['metrics']['activityOutsideCatalogue'] ?? 0);

        if ($outside < self::OUTSIDE_CATALOGUE_THRESHOLD) {
            return [];
        }

        return [[
            'id' => "lms-activity-outside-catalogue-{$this->syear}",
            'severity' => 'low',
            'severityLabel' => 'Low',
            'title' => "Homework set for {$outside} class/subject pairs not in the course catalogue",
            'whatHappened' => $this->sentence([
                "{$outside} distinct (class, subject) pairs carry homework for {$this->syear} that sub_std_map "
                    .'does not currently offer for this institute.',
                'Every one of these rows is counted in the homework totals shown elsewhere on this screen; only '
                    .'their place in the course catalogue is in question.',
            ]),
            'whyItMatters' => 'The course catalogue is meant to be the map of what is taught. Homework set outside '
                .'it means either the catalogue has fallen behind what is actually being taught, or the homework '
                .'was filed against the wrong class or subject — both worth knowing, and neither visible from '
                .'the catalogue screen alone.',
            'evidence' => [
                ['label' => 'Pairs outside the catalogue', 'value' => (string) $outside],
                ['label' => 'Checked against', 'value' => 'sub_std_map, same sub_institute_id, status = 1'],
                ['label' => 'Academic year', 'value' => (string) $this->syear],
            ],
            'likelyCause' => 'The catalogue (sub_std_map) is maintained separately from day-to-day homework entry, '
                .'so a class or subject can drift out of step with what a teacher actually records.',
            'causeConfirmed' => false,
            'recommendation' => 'Check whether these pairs should be added to the catalogue or whether the homework '
                .'rows were filed against the wrong class or subject.',
            'owner' => 'Academic head',
            'priority' => 'low',
            'confidence' => ['band' => 'Medium', 'value' => 0.7],
            'affected' => ['count' => $outside, 'total' => null, 'unit' => 'class/subject pairs'],
            'impact' => ['value' => $outside, 'display' => (string) $outside, 'label' => 'pairs outside the catalogue'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /* ------------------------------------------------------- low return rate */

    /** @return array<int,array<string,mixed>> */
    private function lowSubmissionRate(): array
    {
        if (! $this->analytics->activity()->coverage()['available']) {
            return [];
        }

        // The status/date contradiction is already surfaced by the composed
        // Homework module's own data quality check; this rule does not fire
        // over a rate that check has already said cannot be trusted.
        if (! $this->analytics->activity()->statusReliable()) {
            return [];
        }

        $metrics = $this->analytics->position()['metrics'];
        $rate = $metrics['submissionRate'] ?? null;
        $rows = (int) ($metrics['childAssignments'] ?? 0);

        if ($rate === null || $rows < HomeworkIntelligence::MIN_CLASS_COHORT) {
            return [];
        }

        if ($rate >= self::LOW_SUBMISSION_RATE_THRESHOLD) {
            return [];
        }

        $submitted = (int) ($metrics['submitted'] ?? 0);
        $outstanding = $rows - $submitted;

        return [[
            'id' => "lms-activity-low-submission-{$this->syear}",
            'severity' => $rate < 25.0 ? 'high' : 'medium',
            'severityLabel' => $rate < 25.0 ? 'High' : 'Medium',
            'title' => "Only {$rate}% of homework set for {$this->syear} has been returned",
            'whatHappened' => $this->sentence([
                "{$rows} pieces of homework were set for {$this->syear}.",
                "{$submitted} ({$rate}%) have been submitted; {$outstanding} are outstanding.",
            ]),
            'whyItMatters' => 'Homework that is set and never comes back is work a teacher cannot check and a '
                .'child received no benefit from beyond the assignment itself. Below half returned, the module is '
                .'recording activity rather than completed activity.',
            'evidence' => [
                ['label' => 'Homework set', 'value' => (string) $rows],
                ['label' => 'Submitted', 'value' => (string) $submitted],
                ['label' => 'Outstanding', 'value' => (string) $outstanding],
                ['label' => 'Submission rate', 'value' => "{$rate}%"],
                ['label' => 'Academic year', 'value' => (string) $this->syear],
            ],
            'likelyCause' => null,
            'causeConfirmed' => false,
            'recommendation' => 'Take the classes and subjects with the lowest submission rate from the breakdown '
                .'below and check whether the work is being followed up when it does not come back.',
            'owner' => 'Academic head',
            'priority' => $rate < 25.0 ? 'high' : 'medium',
            'confidence' => ['band' => 'High', 'value' => 0.9],
            'affected' => ['count' => $outstanding, 'total' => $rows, 'unit' => 'child-assignments'],
            'impact' => ['value' => $outstanding, 'display' => (string) $outstanding, 'label' => 'pieces of homework never returned'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /* ------------------------------------------------------- slow turnaround */

    /** @return array<int,array<string,mixed>> */
    private function highSubmissionLag(): array
    {
        $metrics = $this->analytics->position()['metrics'];
        $lag = $metrics['medianSubmissionLagDays'] ?? null;

        if ($lag === null || $lag < self::HIGH_LAG_DAYS) {
            return [];
        }

        return [[
            'id' => "lms-activity-high-lag-{$this->syear}",
            'severity' => $lag >= 10.0 ? 'medium' : 'low',
            'severityLabel' => $lag >= 10.0 ? 'Medium' : 'Low',
            'title' => "Homework is taking a median of {$lag} days to come back",
            'whatHappened' => $this->sentence([
                "Among submissions for {$this->syear} whose set date and submission date agree with the status "
                    .'recorded on the row, the median gap between the two is '.$lag.' days.',
                'There is no due date recorded on homework, so this is a turnaround figure rather than an on-time '
                    .'rate against a deadline the data does not carry.',
            ]),
            'whyItMatters' => 'A week or more between setting work and getting it back is longer than most '
                .'assignments are meant to stay open. Where the gap is this wide, either the work is being held '
                .'past a natural deadline or the submission dates being recorded do not reflect when it was '
                .'actually handed in.',
            'evidence' => [
                ['label' => 'Median turnaround', 'value' => "{$lag} days"],
                ['label' => 'Computed over', 'value' => 'submissions with a set date, a submission date, and a status the two agree with'],
                ['label' => 'Academic year', 'value' => (string) $this->syear],
            ],
            'likelyCause' => null,
            'causeConfirmed' => false,
            'recommendation' => 'Compare this figure against how long this institute intends homework to stay open, '
                .'and check whether the submission date is being recorded at the time of handing in or logged later '
                .'in a batch.',
            'owner' => 'Academic head',
            'priority' => $lag >= 10.0 ? 'medium' : 'low',
            'confidence' => ['band' => 'Medium', 'value' => 0.75],
            'affected' => ['count' => null, 'total' => null, 'unit' => 'days'],
            'impact' => ['value' => $lag, 'display' => "{$lag} days", 'label' => 'median time to return'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /* ------------------------------------------------------------ helpers */

    /** @param array<int,?string> $parts */
    private function sentence(array $parts): string
    {
        return implode(' ', array_filter($parts, static fn ($p) => $p !== null && $p !== ''));
    }
}
