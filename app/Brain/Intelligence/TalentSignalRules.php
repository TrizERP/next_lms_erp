<?php

namespace App\Brain\Intelligence;

/**
 * What the Talent Management figures mean, and what is worth someone's morning.
 *
 * Four rules, one per stage of the loop {@see TalentIntelligence::position()}
 * measures: recruitment moving people forward, postings actually attracting
 * candidates, performance reviews being closed out rather than left pending,
 * and offboarding cases being cleared rather than left open. Each rule reads
 * only the figures `TalentIntelligence` already computed — nothing here
 * issues its own query — so a finding can always be traced back to a number
 * already on the screen.
 */
final class TalentSignalRules
{
    /**
     * Applications sitting in screening with none reaching interview or hire
     * is a stall, not noise, once this many are involved.
     */
    private const SCREENING_STALL_MIN_APPLICATIONS = 3;

    /** Open postings with zero applicants, worth naming once this many exist. */
    private const DEAD_POSTING_MIN_COUNT = 1;

    /** Share of all performance reviews still pending before it is a finding. */
    private const PENDING_REVIEW_SHARE_ALERT = 60.0;

    /** Performance reviews must number at least this many before a share means anything. */
    private const MIN_REVIEWS_FOR_SHARE = 5;

    /** Open offboarding cases, worth naming once this many exist. */
    private const OPEN_OFFBOARDING_MIN_COUNT = 1;

    public function __construct(
        private readonly TalentIntelligence $analytics,
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
            'hiring_funnel_stalled_at_screening' => [
                'Applications stalled in screening with none progressing',
                fn () => $this->hiringFunnelStalledAtScreening(),
            ],
            'open_postings_without_applicants' => [
                'Open postings with zero applicants',
                fn () => $this->openPostingsWithoutApplicants(),
            ],
            'performance_reviews_stuck_pending' => [
                'Performance reviews left pending',
                fn () => $this->performanceReviewsStuckPending(),
            ],
            'offboarding_cases_open' => [
                'Offboarding cases still open',
                fn () => $this->offboardingCasesOpen(),
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

    /* --------------------------------------------------------- recruitment */

    /** @return array<int,array<string,mixed>> */
    private function hiringFunnelStalledAtScreening(): array
    {
        $position = $this->analytics->position();
        if ($position === null) {
            return [];
        }

        $screening = $position['screeningApplications'];
        if ($screening < self::SCREENING_STALL_MIN_APPLICATIONS) {
            return [];
        }
        if ($position['interviewApplications'] > 0 || $position['hiredApplications'] > 0) {
            return [];
        }

        return [[
            'id' => "talent-funnel-stall-{$this->syear}",
            'rule' => 'hiring_funnel_stalled_at_screening',
            'severity' => 'medium',
            'severityLabel' => 'Medium',
            'title' => "{$screening} applications are in screening with none moved to interview or hire",
            'whatHappened' => "{$screening} of {$position['applications']} applications sit in a screening status "
                .'(Pending Review, Under Review or Shortlisted) and none has been advanced to an interview or a '
                .'hire.',
            'whyItMatters' => 'A funnel with intake but no movement past the first stage is not "slow hiring" — it is '
                .'hiring that has stopped. Every day a candidate sits unscreened is a day they are also talking to '
                .'someone else.',
            'evidence' => [
                ['label' => 'In screening', 'value' => (string) $screening],
                ['label' => 'Total applications', 'value' => (string) $position['applications']],
                ['label' => 'Interviewing', 'value' => (string) $position['interviewApplications']],
                ['label' => 'Hired', 'value' => (string) $position['hiredApplications']],
            ],
            'likelyCause' => 'No reviewer has been assigned to these applications, or the reviewer assigned has not '
                .'acted on them yet. This data shows the stall; it cannot show which.',
            'causeConfirmed' => false,
            'recommendation' => 'Assign a reviewer to the applications in screening and set a decision date for each.',
            'owner' => 'Recruiter',
            'priority' => 'medium',
            'confidence' => ['band' => 'Medium', 'value' => 0.7],
            'affected' => ['count' => $screening, 'total' => $position['applications'], 'unit' => 'applications'],
            'impact' => ['value' => $screening, 'display' => (string) $screening, 'label' => 'applications stalled'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /** @return array<int,array<string,mixed>> */
    private function openPostingsWithoutApplicants(): array
    {
        $position = $this->analytics->position();
        if ($position === null || $position['openPostings'] === 0) {
            return [];
        }

        $byStatus = $this->analytics->byApplicationStatus();
        $totalApplications = array_sum(array_column($byStatus, 'applications'));

        // The class does not carry per-posting applicant counts, only a
        // tenant-wide application total, so the finding can only be raised
        // where NO posting could possibly have an applicant: open postings
        // exist and the tenant has zero applications at all.
        if ($totalApplications > 0) {
            return [];
        }
        if ($position['openPostings'] < self::DEAD_POSTING_MIN_COUNT) {
            return [];
        }

        return [[
            'id' => "talent-dead-postings-{$this->syear}",
            'rule' => 'open_postings_without_applicants',
            'severity' => 'medium',
            'severityLabel' => 'Medium',
            'title' => "{$position['openPostings']} open posting(s) have attracted no applicants at all",
            'whatHappened' => "{$position['openPostings']} posting(s) are open and accepting applications, and this "
                .'tenant has zero applications recorded against any posting.',
            'whyItMatters' => 'An open requisition with no applicants is not filling itself while it waits. Every '
                .'day it stays open without reach is a day the position goes unfilled for a reason nobody has looked '
                .'at.',
            'evidence' => [
                ['label' => 'Open postings', 'value' => (string) $position['openPostings']],
                ['label' => 'Total postings', 'value' => (string) $position['postings']],
                ['label' => 'Applications, any status', 'value' => (string) $totalApplications],
            ],
            'likelyCause' => 'The posting has not been distributed to a job board or referral channel, or the role '
                .'and compensation are not competitive for the market. This data shows the absence of applicants; it '
                .'cannot say why.',
            'causeConfirmed' => false,
            'recommendation' => 'Check where each open posting has actually been published before assuming the '
                .'market has no candidates for it.',
            'owner' => 'Recruiter',
            'priority' => 'medium',
            'confidence' => ['band' => 'Medium', 'value' => 0.65],
            'affected' => ['count' => $position['openPostings'], 'total' => $position['postings'], 'unit' => 'postings'],
            'impact' => [
                'value' => $position['openPostings'],
                'display' => (string) $position['openPostings'],
                'label' => 'postings with no reach',
            ],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /* --------------------------------------------------------- performance */

    /** @return array<int,array<string,mixed>> */
    private function performanceReviewsStuckPending(): array
    {
        $position = $this->analytics->position();
        if ($position === null || $position['pendingReviewShare'] === null) {
            return [];
        }
        if ($position['totalPerformanceReviews'] < self::MIN_REVIEWS_FOR_SHARE) {
            return [];
        }
        if ($position['pendingReviewShare'] < self::PENDING_REVIEW_SHARE_ALERT) {
            return [];
        }

        return [[
            'id' => "talent-reviews-pending-{$this->syear}",
            'rule' => 'performance_reviews_stuck_pending',
            'severity' => 'high',
            'severityLabel' => 'High',
            'title' => "{$position['pendingReviewShare']}% of performance reviews have not moved past pending",
            'whatHappened' => "{$position['pendingPerformanceReviews']} of {$position['totalPerformanceReviews']} "
                ."performance reviews on file ({$position['pendingReviewShare']}%) are still in the 'pending' stage "
                .'— neither the self-review nor the manager review has started.',
            'whyItMatters' => 'A review cycle where most reviews never leave pending is not a cycle in progress, it '
                .'is a cycle that has not started for almost everyone in it. Ratings, calibration and compensation '
                .'decisions downstream of these reviews cannot happen until they do.',
            'evidence' => [
                ['label' => 'Pending', 'value' => (string) $position['pendingPerformanceReviews']],
                ['label' => 'Total reviews', 'value' => (string) $position['totalPerformanceReviews']],
                ['label' => 'Share pending', 'value' => "{$position['pendingReviewShare']}%"],
            ],
            'likelyCause' => 'The review cycle was launched but employees and managers have not been notified or '
                .'reminded, or the self-review window has not opened yet. This data shows the stall; it cannot show '
                .'which.',
            'causeConfirmed' => false,
            'recommendation' => 'Send a reminder to every employee and manager with a pending review, and check '
                .'whether the cycle’s self-review window is actually open.',
            'owner' => 'HR Business Partner',
            'priority' => 'high',
            'confidence' => ['band' => 'High', 'value' => 0.85],
            'affected' => [
                'count' => $position['pendingPerformanceReviews'],
                'total' => $position['totalPerformanceReviews'],
                'unit' => 'reviews',
            ],
            'impact' => [
                'value' => $position['pendingPerformanceReviews'],
                'display' => (string) $position['pendingPerformanceReviews'],
                'label' => 'reviews not started',
            ],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /* ---------------------------------------------------------- offboarding */

    /** @return array<int,array<string,mixed>> */
    private function offboardingCasesOpen(): array
    {
        $position = $this->analytics->position();
        if ($position === null || $position['activeOffboardingCases'] < self::OPEN_OFFBOARDING_MIN_COUNT) {
            return [];
        }

        return [[
            'id' => "talent-offboarding-open-{$this->syear}",
            'rule' => 'offboarding_cases_open',
            'severity' => 'medium',
            'severityLabel' => 'Medium',
            'title' => "{$position['activeOffboardingCases']} offboarding case(s) are still open",
            'whatHappened' => "{$position['activeOffboardingCases']} offboarding case(s) have not reached 'Closed' — "
                .'they are somewhere in notice period, clearance, exit interview or awaiting full-and-final '
                .'settlement.',
            'whyItMatters' => 'An offboarding case left open past the departing employee’s last working day is a '
                .'live compliance and access risk: system access, equipment and final settlement all depend on the '
                .'clearance checklist inside it being finished, not just started.',
            'evidence' => [
                ['label' => 'Open cases', 'value' => (string) $position['activeOffboardingCases']],
            ],
            'likelyCause' => 'A clearance item — IT, finance or the reporting manager — has not signed off inside '
                .'the case. This data shows the case is open; the checklist inside each case shows which item is '
                .'holding it.',
            'causeConfirmed' => false,
            'recommendation' => 'Open each case and check which clearance item is still outstanding, prioritising '
                .'any case past its recorded last working day.',
            'owner' => 'HR Business Partner',
            'priority' => 'medium',
            'confidence' => ['band' => 'Medium', 'value' => 0.7],
            'affected' => ['count' => $position['activeOffboardingCases'], 'total' => null, 'unit' => 'cases'],
            'impact' => [
                'value' => $position['activeOffboardingCases'],
                'display' => (string) $position['activeOffboardingCases'],
                'label' => 'cases pending clearance',
            ],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }
}
