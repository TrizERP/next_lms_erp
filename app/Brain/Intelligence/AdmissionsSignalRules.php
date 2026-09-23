<?php

namespace App\Brain\Intelligence;

/**
 * What the admission funnel means, and what is worth someone's morning.
 *
 * ── WHAT THIS REPLACED ──────────────────────────────────────────────────────
 *
 * The only rule that fired at the largest institute was "Active admissions
 * intake cycle (554 registrations)" — an `info` finding whose entire content was
 * that admissions had happened. A finding must detect a risk, an anomaly, a gap,
 * a trend or an opportunity; "the module is in use" is none of those, and a rule
 * that exists to put a card on the screen is the thing this whole layer was
 * built to avoid. It is gone.
 *
 * ── NOTHING CLAIMS A CAUSE THE FUNNEL CANNOT SHOW ───────────────────────────
 *
 * A candidate who did not confirm may have gone to another school, may have
 * been declined, or may simply never have been telephoned. The records show
 * which stage they stopped at and nothing about why, and every finding here is
 * worded to that limit.
 */
final class AdmissionsSignalRules
{
    /** Rows named inside one finding's evidence before it stops being readable. */
    private const MAX_NAMED_IN_EVIDENCE = 6;

    /** Conversion below this is worth naming. */
    private const CONVERSION_ALERT = 60.0;

    /** Share of candidates left with no outcome before it is worth naming. */
    private const UNDECIDED_ALERT = 10.0;

    /** Share of confirmed places with no fee recorded before it is worth naming. */
    private const UNPAID_ALERT = 15.0;

    public function __construct(
        private readonly AdmissionsIntelligence $analytics,
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
            'stage_dropoff' => ['Where candidates are lost between stages', fn () => $this->stageDropOff()],
            'undecided_candidates' => ['Candidates left with no outcome', fn () => $this->undecidedCandidates()],
            'confirmed_unpaid' => ['Confirmed places with no fee recorded', fn () => $this->confirmedUnpaid()],
            'conversion_rate' => ['Overall conversion from registration', fn () => $this->conversionRate()],
            'demand_concentration' => ['Demand concentrated in a few standards', fn () => $this->demandConcentration()],
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

    /* ------------------------------------------------------------- funnel */

    /**
     * The single biggest loss between two consecutive stages.
     *
     * ONE FINDING, NOT ONE PER STAGE. A funnel with four stages has three gaps,
     * and reporting all three as separate findings buries the one that matters.
     *
     * @return array<int,array<string,mixed>>
     */
    private function stageDropOff(): array
    {
        $funnel = $this->analytics->funnel();
        $position = $this->analytics->position();

        if (count($funnel) < 2 || $position === null) {
            return [];
        }
        if ($position['registrations'] < AdmissionsIntelligence::MIN_FUNNEL_COHORT) {
            return [];
        }

        $worst = null;
        foreach ($funnel as $index => $stage) {
            if ($index === 0 || $stage['lostFromPrevious'] === null) {
                continue;
            }
            if ($worst === null || $stage['lostFromPrevious'] > $worst['lostFromPrevious']) {
                $worst = $stage + ['previousLabel' => $funnel[$index - 1]['label']];
            }
        }

        if ($worst === null || $worst['lostFromPrevious'] === 0) {
            return [];
        }

        $lost = $worst['lostFromPrevious'];
        $shareOfTotal = round($lost / $position['registrations'] * 100, 1);

        return [[
            'id' => "admissions-dropoff-{$worst['key']}-{$this->syear}",
            'rule' => 'stage_dropoff',
            'severity' => $shareOfTotal >= 30.0 ? 'high' : 'medium',
            'severityLabel' => $shareOfTotal >= 30.0 ? 'High' : 'Medium',
            'title' => "{$lost} candidates are lost between “{$worst['previousLabel']}” and “{$worst['label']}”",
            'whatHappened' => $this->sentence([
                "Of {$position['registrations']} registrations this year, {$lost} ({$shareOfTotal}%) do not appear at "
                    ."the “{$worst['label']}” stage although they reached “{$worst['previousLabel']}”.",
                'That is the largest single loss in the funnel.',
            ]),
            'whyItMatters' => 'Every candidate who reaches a stage has already cost the school the work of getting '
                .'them there. The stage with the biggest drop is where that work is being spent and not converted, '
                .'and it is the only place where a change moves the intake.',
            'evidence' => array_map(static fn ($s) => [
                'label' => $s['label'],
                'value' => "{$s['candidates']} candidates",
                'note' => $s['lostFromPrevious'] === null
                    ? "{$s['share']}% of registrations"
                    : "{$s['share']}% of registrations · {$s['lostFromPrevious']} lost from the stage before",
            ], $funnel),
            // The funnel shows WHERE, never WHY. Saying so is the difference
            // between a finding and an accusation about the admissions office.
            'likelyCause' => 'A candidate who stopped at this stage may have gone elsewhere, may have been declined, '
                .'or may simply not have been followed up. The records show the stage they stopped at and nothing '
                .'about which of those it was.',
            'causeConfirmed' => false,
            'recommendation' => 'Take a sample of the candidates who stopped here and establish which of the three it '
                .'was — the answer decides whether this is a process problem or a market one.',
            'owner' => 'Admissions office',
            'priority' => $shareOfTotal >= 30.0 ? 'high' : 'medium',
            'confidence' => $position['registrations'] >= 100
                ? ['band' => 'High', 'value' => 0.9]
                : ['band' => 'Medium', 'value' => 0.65],
            'affected' => ['count' => $lost, 'total' => $position['registrations'], 'unit' => 'candidates'],
            'impact' => ['value' => $lost, 'display' => (string) $lost, 'label' => 'candidates lost at this stage'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /** @return array<int,array<string,mixed>> */
    private function undecidedCandidates(): array
    {
        $position = $this->analytics->position();
        if ($position === null || ($position['undecided'] ?? 0) === 0) {
            return [];
        }

        $undecided = $position['undecided'];
        $share = round($undecided / $position['registrations'] * 100, 1);
        if ($share < self::UNDECIDED_ALERT) {
            return [];
        }

        return [[
            'id' => "admissions-undecided-{$this->syear}",
            'rule' => 'undecided_candidates',
            'severity' => $share >= 25.0 ? 'high' : 'medium',
            'severityLabel' => $share >= 25.0 ? 'High' : 'Medium',
            'title' => "{$undecided} candidates have been given no answer either way",
            'whatHappened' => $this->sentence([
                "{$undecided} of {$position['registrations']} registrations ({$share}%) carry no confirmation "
                    .'outcome: they are neither confirmed nor declined.',
                $position['medianDaysToConfirm'] !== null
                    ? "Candidates who did receive an outcome waited a median of {$position['medianDaysToConfirm']} "
                        .'days between interview and confirmation.'
                    : null,
            ]),
            'whyItMatters' => 'A family with no answer is deciding between schools without knowing whether they have '
                .'a place at this one. Every day that continues, the decision is made somewhere else. It also drags '
                .'the conversion rate down without anyone having declined anybody.',
            'evidence' => array_values(array_filter([
                ['label' => 'No outcome recorded', 'value' => (string) $undecided],
                ['label' => 'Registrations', 'value' => (string) $position['registrations']],
                ['label' => 'Share', 'value' => "{$share}%"],
                ['label' => 'Confirmed', 'value' => (string) $position['confirmed']],
                ['label' => 'Not proceeding', 'value' => (string) $position['notProceeding']],
                $position['medianDaysToConfirm'] !== null
                    ? ['label' => 'Median days to confirm', 'value' => (string) $position['medianDaysToConfirm']]
                    : null,
            ])),
            'likelyCause' => 'An outcome decided in conversation and never written back, or a candidate still genuinely '
                .'under consideration. The record is blank either way.',
            'causeConfirmed' => false,
            'recommendation' => 'Close the open candidates before the year’s intake is counted — a blank outcome '
                .'costs the same as a rejection and tells the family less.',
            'owner' => 'Admissions office',
            'priority' => $share >= 25.0 ? 'high' : 'medium',
            'confidence' => ['band' => 'High', 'value' => 0.95],
            'affected' => ['count' => $undecided, 'total' => $position['registrations'], 'unit' => 'candidates'],
            'impact' => ['value' => $undecided, 'display' => (string) $undecided, 'label' => 'candidates awaiting an answer'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /** @return array<int,array<string,mixed>> */
    private function confirmedUnpaid(): array
    {
        $position = $this->analytics->position();
        if ($position === null || ($position['confirmed'] ?? 0) === 0) {
            return [];
        }

        $unpaid = $position['confirmedUnpaid'];
        if ($unpaid === 0) {
            return [];
        }

        $share = round($unpaid / $position['confirmed'] * 100, 1);
        if ($share < self::UNPAID_ALERT) {
            return [];
        }

        return [[
            'id' => "admissions-confirmed-unpaid-{$this->syear}",
            'rule' => 'confirmed_unpaid',
            'severity' => 'medium',
            'severityLabel' => 'Medium',
            'title' => "{$unpaid} confirmed places carry no fee payment",
            'whatHappened' => $this->sentence([
                "{$unpaid} of {$position['confirmed']} confirmed candidates ({$share}%) have no payment recorded "
                    .'against them.',
                $position['paymentRate'] !== null
                    ? "The payment rate across confirmed candidates is {$position['paymentRate']}%."
                    : null,
            ]),
            'whyItMatters' => 'A confirmed place is a seat that is no longer available to anybody else. Where no fee '
                .'stands behind it, the school is holding capacity against a commitment it has not been given — and '
                .'if the family does not arrive, the seat is lost for the year rather than offered again.',
            'evidence' => array_map(static fn ($c) => [
                'label' => "Code “{$c['label']}”",
                'value' => "{$c['candidates']} candidates",
                'note' => "{$c['paid']} paid · {$c['meaning']}",
            ], array_slice($this->analytics->byConfirmationCode(), 0, self::MAX_NAMED_IN_EVIDENCE)),
            'likelyCause' => 'Fees collected outside this system, a payment field that is filled in later, or places '
                .'confirmed before payment is asked for. The records show the field is not set, not which of those '
                .'left it that way.',
            'causeConfirmed' => false,
            'recommendation' => 'Reconcile the confirmed-but-unpaid list against the fees module before treating any '
                .'of them as unfunded seats.',
            'owner' => 'Admissions office with the fees office',
            'priority' => 'medium',
            'confidence' => ['band' => 'Medium', 'value' => 0.7],
            'affected' => ['count' => $unpaid, 'total' => $position['confirmed'], 'unit' => 'candidates'],
            'impact' => ['value' => $unpaid, 'display' => (string) $unpaid, 'label' => 'seats held without a fee'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /** @return array<int,array<string,mixed>> */
    private function conversionRate(): array
    {
        $position = $this->analytics->position();
        if ($position === null || $position['registrations'] < AdmissionsIntelligence::MIN_FUNNEL_COHORT) {
            return [];
        }
        if ($position['conversionRate'] >= self::CONVERSION_ALERT) {
            return [];
        }

        return [[
            'id' => "admissions-conversion-{$this->syear}",
            'rule' => 'conversion_rate',
            'severity' => $position['conversionRate'] < 35.0 ? 'high' : 'medium',
            'severityLabel' => $position['conversionRate'] < 35.0 ? 'High' : 'Medium',
            'title' => "{$position['conversionRate']}% of registrations reached a confirmed place",
            'whatHappened' => $this->sentence([
                "{$position['confirmed']} of {$position['registrations']} registrations were confirmed "
                    ."({$position['conversionRate']}%).",
                "{$position['notProceeding']} were recorded as not proceeding and {$position['undecided']} carry no "
                    .'outcome at all.',
            ]),
            'whyItMatters' => 'Registration is the point at which a family has already chosen to apply here. A low '
                .'conversion from that point is not a marketing problem — the interest was already won and something '
                .'after it is losing them.',
            'evidence' => array_map(static fn ($c) => [
                'label' => "Code “{$c['label']}”",
                'value' => "{$c['candidates']} candidates",
                'note' => $c['share'] === null ? $c['meaning'] : "{$c['share']}% · {$c['meaning']}",
            ], array_slice($this->analytics->byConfirmationCode(), 0, self::MAX_NAMED_IN_EVIDENCE)),
            'likelyCause' => 'Candidates going elsewhere, candidates being declined, and candidates never answered '
                .'all reduce this figure identically. The undecided count above is the part the school controls.',
            'causeConfirmed' => false,
            'recommendation' => 'Read this beside the undecided finding: closing the open candidates changes this '
                .'number without changing a single decision.',
            'owner' => 'Admissions office',
            'priority' => $position['conversionRate'] < 35.0 ? 'high' : 'medium',
            'confidence' => $position['registrations'] >= 100
                ? ['band' => 'High', 'value' => 0.9]
                : ['band' => 'Medium', 'value' => 0.65],
            'affected' => [
                'count' => $position['registrations'] - $position['confirmed'],
                'total' => $position['registrations'],
                'unit' => 'candidates',
            ],
            'impact' => [
                'value' => $position['registrations'] - $position['confirmed'],
                'display' => (string) ($position['registrations'] - $position['confirmed']),
                'label' => 'registrations not confirmed',
            ],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /** @return array<int,array<string,mixed>> */
    private function demandConcentration(): array
    {
        $demand = $this->analytics->demandByStandard();
        if (count($demand) < 2) {
            return [];
        }

        $total = array_sum(array_column($demand, 'inquiries'));
        if ($total < AdmissionsIntelligence::MIN_FUNNEL_COHORT) {
            return [];
        }

        $top = $demand[0];
        if ($top['share'] === null || $top['share'] < 60.0) {
            return [];
        }

        return [[
            'id' => "admissions-demand-{$this->syear}",
            'rule' => 'demand_concentration',
            'severity' => 'low',
            'severityLabel' => 'Low',
            'title' => "{$top['share']}% of admission inquiries are for {$top['label']}",
            'whatHappened' => "{$top['inquiries']} of {$total} inquiries this year name {$top['label']} as the "
                .'standard applied for, across '.count($demand).' standards in total.',
            'whyItMatters' => 'Intake demand concentrated in one standard decides where the seats, the sections and '
                .'the staffing have to be, and it is visible before the year starts rather than after.',
            'evidence' => array_map(static fn ($d) => [
                'label' => $d['label'],
                'value' => "{$d['inquiries']} inquiries",
                'note' => $d['share'] === null ? null : "{$d['share']}% of inquiries",
            ], array_slice($demand, 0, self::MAX_NAMED_IN_EVIDENCE)),
            'likelyCause' => null,
            'causeConfirmed' => false,
            'recommendation' => null,
            'owner' => 'Admissions office',
            'priority' => 'low',
            'confidence' => ['band' => 'High', 'value' => 0.85],
            'affected' => ['count' => $top['inquiries'], 'total' => $total, 'unit' => 'inquiries'],
            'impact' => null,
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
