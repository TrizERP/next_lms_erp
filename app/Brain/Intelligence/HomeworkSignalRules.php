<?php

namespace App\Brain\Intelligence;

/**
 * What the homework register means, and what is worth someone's morning.
 *
 * ── WHAT A RULE HERE MAY NOT DO ─────────────────────────────────────────────
 *
 * IT MAY NOT CALL A SUBMISSION RATE A COMPLETION RATE. `completion_status` holds
 * 'Y' exactly when `submission_date` is set, and `reviewed_by` is populated on
 * ZERO rows in this database. Nothing here has been marked by a teacher, so no
 * rule below may say anything about whether the work was done well — only about
 * whether it came back.
 *
 * IT MAY NOT REPORT THE SAME ROWS TWICE. At an institute with 88 child-assignments
 * in one subject and one class, the previous version raised both "Low homework
 * completion in ENGLISH (0%)" and "Elevated unsubmitted homework volume (88
 * pending, 100%)" — the same 88 rows counted from both ends and presented as two
 * discoveries. The per-subject rule stands down where there is only one subject,
 * because the subject with the lowest rate is not a finding when there is nothing
 * for it to be lowest than.
 *
 * IT MAY NOT TREAT THIN ADOPTION AS A STUDENT PROBLEM. Homework is set at two
 * real institutes in this entire database, at one of which it covers a single
 * class. A submission rate over that is a fact about one teacher's class and is
 * gated and labelled accordingly.
 *
 * "CURRICULAR AREAS REACHING EXEMPLARY COMPLETION (>85%)" IS GONE. Reporting that
 * some homework was done is not a risk, an anomaly, a gap, a trend or an
 * opportunity.
 */
final class HomeworkSignalRules
{
    /** Rows named inside one finding's evidence before it stops being readable. */
    private const MAX_NAMED_IN_EVIDENCE = 6;

    /** Share of child-assignments outstanding before it is worth naming. */
    private const OUTSTANDING_SHARE = 30.0;

    /** Submission rate below which a subject is named, given it clears the cohort floor. */
    private const LOW_SUBMISSION_RATE = 60.0;

    /** Share of the institute's classes reached before adoption stops being the story. */
    private const ADOPTION_SHARE = 25.0;

    public function __construct(
        private readonly HomeworkIntelligence $analytics,
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
            'thin_adoption' => ['Homework reaching only part of the institute', fn () => $this->thinAdoption()],
            'submission_status_unusable' => ['A submission status its own dates contradict', fn () => $this->submissionStatusUnusable()],
            'no_teacher_review' => ['Submitted work that no teacher has seen', fn () => $this->noTeacherReview()],
            'outstanding_burden' => ['Homework set and never returned', fn () => $this->outstandingBurden()],
            'subject_low_submission' => ['Subjects whose work comes back least', fn () => $this->subjectLowSubmission()],
            'year_label_mismatch' => ['Homework filed under a year its dates contradict', fn () => $this->yearLabelMismatch()],
        ];

        $findings = [];
        $ruleStatus = [];

        foreach ($rules as $key => [$label, $check]) {
            $raised = $check();
            $ruleStatus[] = ['key' => $key, 'label' => $label, 'checked' => true, 'raised' => $raised !== []];

            // Tagged HERE rather than in each rule, so a rule added later cannot
            // forget to. ModuleSignalBridge dedupes the ledger on (tenant, rule,
            // year); a finding with no rule key cannot be deduped and is skipped.
            foreach ($raised as $finding) {
                $findings[] = $finding + ['rule' => $key];
            }
        }

        $rank = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3, 'info' => 4];
        usort($findings, function ($a, $b) use ($rank) {
            $bySeverity = ($rank[$a['severity']] ?? 5) <=> ($rank[$b['severity']] ?? 5);

            // Ordered by how many children it touches, never by the size of a
            // percentage — a 100% rate over four pieces of work is not the most
            // important thing on the screen.
            return $bySeverity !== 0
                ? $bySeverity
                : ($b['affected']['count'] ?? 0) <=> ($a['affected']['count'] ?? 0);
        });

        return ['findings' => $findings, 'ruleStatus' => $ruleStatus];
    }

    /* ------------------------------------------------------------ adoption */

    /**
     * The module is in use, but only somewhere.
     *
     * This runs FIRST and is the most important thing on the screen when it
     * fires, because every other figure here has to be read as being about those
     * classes rather than about the school.
     *
     * @return array<int,array<string,mixed>>
     */
    private function thinAdoption(): array
    {
        $reach = $this->analytics->classReach();
        if ($reach['share'] === null || $reach['share'] >= self::ADOPTION_SHARE) {
            return [];
        }

        $metrics = $this->analytics->position()['metrics'];
        $classes = (int) $reach['classes'];
        $total = (int) $reach['total'];

        return [[
            'id' => "homework-adoption-{$this->syear}",
            'severity' => 'medium',
            'severityLabel' => 'Medium',
            'title' => $classes === 1
                ? "Homework is set in one of this institute's {$total} classes"
                : "Homework is set in {$classes} of this institute's {$total} classes",
            'whatHappened' => $this->sentence([
                "{$metrics['childAssignments']} pieces of homework were set for {$metrics['students']} children this "
                    ."year, across {$classes} of the {$total} classes on the roll ({$reach['share']}%).",
                (int) $metrics['subjects'] === 1
                    ? 'All of it sits in a single subject.'
                    : "It covers {$metrics['subjects']} subjects.",
            ]),
            'whyItMatters' => 'Every other figure on this screen is drawn from those classes, so it describes them and '
                .'not the school. A submission rate read as institute-wide would be a statement about children whose '
                .'homework was never recorded here at all.',
            'evidence' => [
                ['label' => 'Classes with homework', 'value' => (string) $classes],
                ['label' => 'Classes on the roll', 'value' => (string) $total],
                ['label' => 'Reach', 'value' => "{$reach['share']}%"],
                ['label' => 'Children with homework', 'value' => (string) $metrics['students']],
                ['label' => 'Pieces of homework set', 'value' => (string) $metrics['childAssignments']],
            ],
            'likelyCause' => 'A module being trialled by one teacher or one department, which is the usual shape of a '
                .'new workflow in its first year. The register records where homework was set and not why it was set '
                .'only there.',
            'causeConfirmed' => false,
            'recommendation' => 'Decide whether this is a trial or a rollout before anybody reads these figures as '
                .'institute-wide. If it is a trial, the classes using it are the only ones these numbers describe.',
            'owner' => 'Academic coordinator',
            'priority' => 'medium',
            'confidence' => ['band' => 'High', 'value' => 0.95],
            'affected' => ['count' => $total - $classes, 'total' => $total, 'unit' => 'classes'],
            'impact' => [
                'value' => $total - $classes,
                'display' => (string) ($total - $classes),
                'label' => 'classes outside the module',
            ],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /* ---------------------------------------------------- the status itself */

    /**
     * The submission status contradicts the submission date.
     *
     * At one institute every one of 88 pieces of homework carries a submission
     * date AND a status saying it was never handed in. "0% returned" is
     * arithmetically correct from the status and would be read as a statement
     * about 44 children who, on the evidence of the dates, handed their work in.
     *
     * This fires INSTEAD OF the submission findings below, not alongside them —
     * see the guard in {@see outstandingBurden()}. A screen that says both "0%
     * returned" and "the 0% cannot be believed" is worse than one that says only
     * the second.
     *
     * @return array<int,array<string,mixed>>
     */
    private function submissionStatusUnusable(): array
    {
        if ($this->analytics->statusReliable()) {
            return [];
        }

        $metrics = $this->analytics->position()['metrics'];
        $set = (int) $metrics['childAssignments'];
        $contradictions = (int) $metrics['statusContradictions'];

        if ($set < HomeworkIntelligence::MIN_CLASS_COHORT || $contradictions === 0) {
            return [];
        }

        $share = round($contradictions / $set * 100, 1);

        return [[
            'id' => "homework-status-unusable-{$this->syear}",
            'severity' => 'high',
            'severityLabel' => 'High',
            'title' => "{$contradictions} of {$set} pieces of homework carry a submission date and a status saying "
                .'they were never submitted',
            'whatHappened' => "Every figure this module reports about submission is read from `completion_status`. "
                ."On {$contradictions} of the {$set} pieces of homework set this year ({$share}%), that status says "
                ."the work never came back while the row also carries the date it came back on. The two fields "
                .'disagree, and nothing in the table decides which is right.',
            'whyItMatters' => 'The submission rate on this screen is drawn from the status, so where the status is '
                .'wrong the rate is wrong — and it is wrong in the direction that makes children look as though they '
                .'did not do their work. No follow-up should be based on it until this is settled, because the '
                .'dates suggest the opposite of what the status says.',
            'evidence' => [
                ['label' => 'Status and date disagree', 'value' => (string) $contradictions],
                ['label' => 'Pieces of homework set', 'value' => (string) $set],
                ['label' => 'Share', 'value' => "{$share}%"],
                ['label' => 'Children affected', 'value' => (string) $metrics['students']],
                ['label' => 'Rate the status reports', 'value' => "{$metrics['submissionRate']}%"],
            ],
            'likelyCause' => 'A submission that writes the date without setting the status, which is the usual shape '
                .'of two fields updated by different code paths. The register holds both and reconciles neither.',
            'causeConfirmed' => false,
            'recommendation' => 'Settle which field the homework module treats as the submission signal, and backfill '
                .'the other from it. Until then no submission figure from this module should be acted on, and no '
                .'child should be followed up for work the dates say they handed in.',
            'owner' => 'Academic coordinator',
            'priority' => 'high',
            'confidence' => ['band' => 'High', 'value' => 0.95],
            'affected' => ['count' => $contradictions, 'total' => $set, 'unit' => 'pieces of work'],
            'impact' => [
                'value' => $contradictions,
                'display' => (string) $contradictions,
                'label' => 'pieces whose status cannot be trusted',
            ],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /* -------------------------------------------------------------- review */

    /**
     * Work came back and nobody looked at it.
     *
     * Measured across this whole database, `reviewed_by` and `feedback_published`
     * are populated on zero rows out of 1,535. That is not a backlog that built
     * up — it is a half of the module that has never been used.
     *
     * @return array<int,array<string,mixed>>
     */
    private function noTeacherReview(): array
    {
        $metrics = $this->analytics->position()['metrics'];
        $submitted = (int) $metrics['submitted'];
        $reviewed = (int) $metrics['reviewed'];
        $unreviewed = $submitted - $reviewed;

        if ($submitted < HomeworkIntelligence::MIN_CLASS_COHORT || $unreviewed === 0) {
            return [];
        }

        $share = round($unreviewed / $submitted * 100, 1);
        $never = $reviewed === 0;

        return [[
            'id' => "homework-unreviewed-{$this->syear}",
            'severity' => $never ? 'medium' : 'low',
            'severityLabel' => $never ? 'Medium' : 'Low',
            'title' => $never
                ? "None of the {$submitted} pieces of work handed in this year has been reviewed"
                : "{$unreviewed} pieces of work handed in this year carry no teacher review",
            'whatHappened' => $this->sentence([
                "{$unreviewed} of the {$submitted} pieces of work marked submitted this year ({$share}%) have no "
                    .'reviewer, no published feedback and no teacher remark against them.',
                $this->analytics->anomalies()['noArtefact'] > 0
                    ? $this->analytics->anomalies()['noArtefact'].' of the submitted pieces carry no image, file or '
                        .'remark either — the status was set without anything being uploaded.'
                    : null,
            ]),
            'whyItMatters' => 'This is the half of the module the child experiences. Work that goes in and produces '
                .'nothing back teaches children that submitting it does not matter, and it means the submission '
                .'figures above say the work returned and nothing whatever about whether it was any good.',
            'evidence' => array_values(array_filter([
                ['label' => 'Submitted with no review', 'value' => (string) $unreviewed],
                ['label' => 'Submitted this year', 'value' => (string) $submitted],
                ['label' => 'Share', 'value' => "{$share}%"],
                $reviewed > 0 ? ['label' => 'Reviewed', 'value' => (string) $reviewed] : null,
                ['label' => 'Children affected', 'value' => (string) $metrics['students']],
            ])),
            'likelyCause' => 'Work marked on paper and never written back, or a review step nobody was asked to use. '
                .'The register records the absence of a review and cannot tell the two apart — and a mark in an '
                .'exercise book is feedback the system simply cannot see.',
            'causeConfirmed' => false,
            'recommendation' => 'Establish whether the marking exists on paper before treating this as a backlog. If '
                .'it does, the question is whether the review step is worth asking anyone to duplicate; if it does '
                .'not, these children have had no response at all.',
            'owner' => 'Academic coordinator',
            'priority' => $never ? 'medium' : 'low',
            'confidence' => ['band' => 'High', 'value' => 0.9],
            'affected' => ['count' => $unreviewed, 'total' => $submitted, 'unit' => 'pieces of work'],
            'impact' => ['value' => $unreviewed, 'display' => (string) $unreviewed, 'label' => 'pieces awaiting a response'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /* --------------------------------------------------------- submissions */

    /** @return array<int,array<string,mixed>> */
    private function outstandingBurden(): array
    {
        // Stands down where the status contradicts the dates. Reporting work as
        // outstanding on the strength of a field that also says it was handed in
        // would send somebody to chase children who did their homework.
        if (! $this->analytics->statusReliable()) {
            return [];
        }

        $metrics = $this->analytics->position()['metrics'];
        $set = (int) $metrics['childAssignments'];
        $outstanding = (int) $metrics['outstanding'];

        if ($set < HomeworkIntelligence::MIN_CLASS_COHORT || $outstanding === 0) {
            return [];
        }

        $share = round($outstanding / $set * 100, 1);
        if ($share < self::OUTSTANDING_SHARE) {
            return [];
        }

        $byMonth = array_values(array_filter(
            $this->analytics->byMonth(),
            static fn ($m) => $m['submissionRate'] !== null,
        ));

        return [[
            'id' => "homework-outstanding-{$this->syear}",
            'severity' => $share > 50.0 ? 'medium' : 'low',
            'severityLabel' => $share > 50.0 ? 'Medium' : 'Low',
            'title' => "{$outstanding} of {$set} pieces of homework were never marked returned",
            'whatHappened' => $this->sentence([
                "{$outstanding} pieces of homework ({$share}%) set for {$metrics['students']} children this year "
                    .'carry no submission.',
                count($byMonth) > 1
                    ? 'The work was set across '.count($byMonth).' months, so this is a pattern rather than one week.'
                    : 'All of the work was set inside a single month, so this is one burst rather than a pattern.',
            ]),
            'whyItMatters' => 'Outstanding work accumulates against the child rather than the calendar. Whether this '
                .'is work not done or work done and not recorded decides completely which of those two problems the '
                .'school has, and the register cannot tell you.',
            'evidence' => array_merge(
                [
                    ['label' => 'Never marked returned', 'value' => (string) $outstanding],
                    ['label' => 'Pieces set', 'value' => (string) $set],
                    ['label' => 'Share', 'value' => "{$share}%"],
                    ['label' => 'Children affected', 'value' => (string) $metrics['students']],
                ],
                array_map(static fn ($m) => [
                    'label' => $m['label'],
                    'value' => "{$m['submissionRate']}% returned",
                    'note' => "{$m['set']} pieces set",
                ], array_slice($byMonth, 0, self::MAX_NAMED_IN_EVIDENCE - 4)),
            ),
            'likelyCause' => 'Work not done, work done on paper and never marked off in the system, or deadlines '
                .'falling together across subjects. The register holds a status flag and none of the three.',
            'causeConfirmed' => false,
            'recommendation' => 'Read the outstanding work by the date it was set rather than by subject. A cluster on '
                .'one date is a timetabling problem; an even spread is not.',
            'owner' => 'Academic coordinator',
            'priority' => 'medium',
            'confidence' => ['band' => 'Medium', 'value' => 0.65],
            'affected' => ['count' => $outstanding, 'total' => $set, 'unit' => 'pieces of work'],
            'impact' => ['value' => $outstanding, 'display' => (string) $outstanding, 'label' => 'pieces outstanding'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /**
     * Subjects whose work comes back least.
     *
     * Stands down entirely below two subjects with enough work to compare, for
     * the reason in the class note.
     *
     * @return array<int,array<string,mixed>>
     */
    private function subjectLowSubmission(): array
    {
        // Same guard as above: a per-subject rate drawn from a status the dates
        // contradict is a per-subject way of being wrong.
        if (! $this->analytics->statusReliable()) {
            return [];
        }

        $comparable = array_values(array_filter(
            $this->analytics->bySubject(),
            static fn ($s) => ! $s['suppressed'],
        ));

        if (count($comparable) < 2) {
            return [];
        }

        $low = array_values(array_filter(
            $comparable,
            static fn ($s) => $s['submissionRate'] < self::LOW_SUBMISSION_RATE,
        ));

        if ($low === []) {
            return [];
        }

        // Ordered by children affected, never by the lowest percentage.
        usort($low, static fn ($a, $b) => ($b['set'] - $b['submitted']) <=> ($a['set'] - $a['submitted']));
        $worst = $low[0];
        $outstanding = array_sum(array_map(static fn ($s) => $s['set'] - $s['submitted'], $low));
        $children = array_sum(array_column($low, 'students'));

        return [[
            'id' => "homework-subject-submission-{$this->syear}",
            'severity' => 'low',
            'severityLabel' => 'Low',
            'title' => count($low) === 1
                ? "{$worst['label']} homework comes back {$worst['submissionRate']}% of the time"
                : count($low).' subjects have homework that comes back less than half the time',
            'whatHappened' => $this->sentence([
                count($low).' of the '.count($comparable).' subjects with enough homework to compare sit below '
                    .self::LOW_SUBMISSION_RATE.'% returned.',
                "The largest is {$worst['label']}: ".($worst['set'] - $worst['submitted'])." of {$worst['set']} "
                    ."pieces outstanding across {$worst['students']} children.",
            ]),
            'whyItMatters' => 'Homework is set subject by subject and chased class by class, so a subject that comes '
                .'back least is a specific teacher’s week rather than a school-wide policy question. It is also the '
                .'one comparison on this screen that holds the children constant.',
            'evidence' => array_map(static fn ($s) => [
                'label' => $s['label'],
                'value' => "{$s['submissionRate']}% returned",
                'note' => ($s['set'] - $s['submitted'])." outstanding of {$s['set']} · {$s['students']} children",
            ], array_slice($low, 0, self::MAX_NAMED_IN_EVIDENCE)),
            'likelyCause' => 'Difficulty with the subject, expectations that were not made clear, or several subjects '
                .'setting work for the same night. The register shows the rate and none of the three.',
            'causeConfirmed' => false,
            'recommendation' => 'Compare the dates the weakest subject set work against the others before reading '
                .'this as comprehension. Work set the same night as two other subjects comes back less for reasons '
                .'that have nothing to do with the subject.',
            'owner' => 'Academic coordinator',
            'priority' => 'low',
            'confidence' => $children >= 30
                ? ['band' => 'Medium', 'value' => 0.6]
                : ['band' => 'Low', 'value' => 0.4],
            'affected' => ['count' => $outstanding, 'total' => (int) $this->analytics->position()['metrics']['childAssignments'], 'unit' => 'pieces of work'],
            'impact' => ['value' => $outstanding, 'display' => (string) $outstanding, 'label' => 'pieces outstanding'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /* -------------------------------------------------------- the year tag */

    /**
     * Homework filed under a year its own dates contradict.
     *
     * One institute in this database files homework dated 2026 under `syear`
     * 2022. This screen scopes by `syear`, which is what the homework module
     * itself writes and reads — so the figures are internally consistent. A
     * report that scopes by date will not find these rows at all.
     *
     * @return array<int,array<string,mixed>>
     */
    private function yearLabelMismatch(): array
    {
        $anomalies = $this->analytics->anomalies();
        $outside = (int) $anomalies['outsideYear'];
        if ($outside === 0 || $this->analytics->yearWindow() === null) {
            return [];
        }

        $set = (int) $this->analytics->position()['metrics']['childAssignments'];
        $share = round($outside / $set * 100, 1);
        $window = $this->analytics->yearWindow();

        return [[
            'id' => "homework-year-mismatch-{$this->syear}",
            'severity' => $share >= 50.0 ? 'medium' : 'low',
            'severityLabel' => $share >= 50.0 ? 'Medium' : 'Low',
            'title' => "{$outside} pieces of homework are dated outside the academic year they are filed under",
            'whatHappened' => "This institute's {$this->syear} academic year runs {$window['start']} to "
                ."{$window['end']}. {$outside} of the {$set} pieces of homework filed under it ({$share}%) carry a "
                .'date outside those bounds.',
            'whyItMatters' => 'This screen scopes by the year tag, which is what the homework module itself writes '
                .'and reads, so its figures hold together. Anything that scopes by date — a report card, an export, '
                .'a parent-facing history — will not find this work at all, and the two will never reconcile.',
            'evidence' => [
                ['label' => 'Dated outside the year', 'value' => (string) $outside],
                ['label' => 'Filed under '.$this->syear, 'value' => (string) $set],
                ['label' => 'Share', 'value' => "{$share}%"],
                ['label' => 'Year runs', 'value' => "{$window['start']} to {$window['end']}"],
            ],
            'likelyCause' => 'A year tag carried over from whatever was selected when the work was created, rather '
                .'than derived from its date. The two fields are written independently and nothing reconciles them.',
            'causeConfirmed' => false,
            'recommendation' => 'Settle which of the two fields the homework module should trust before building any '
                .'report on either. Until then, a date-scoped report and a year-scoped one will disagree and both '
                .'will look right.',
            'owner' => 'Academic coordinator',
            'priority' => 'low',
            'confidence' => ['band' => 'High', 'value' => 0.95],
            'affected' => ['count' => $outside, 'total' => $set, 'unit' => 'pieces of work'],
            'impact' => ['value' => $outside, 'display' => (string) $outside, 'label' => 'pieces filed against the wrong year'],
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
