<?php

namespace App\Brain\Intelligence;

/**
 * What the stock-take means, and what is worth someone's morning.
 *
 * ── WHAT THIS REPLACED ──────────────────────────────────────────────────────
 *
 * The previous catalogue's loudest rule fired "Asset audit discrepancies (1105
 * items pending / unverified, 100%)" at an institute whose scans simply carry
 * no outcome column. It was reading an unfilled field as a missing asset and
 * reporting it as high severity, with ten raw scan rows attached as evidence.
 *
 * NOTHING HERE FIRES ON AN ABSENT COLUMN. Where no scan carries an outcome the
 * verification rules return nothing and coverage says why; the only finding
 * available then is that the stock-take has no result recorded, which is a
 * statement about the RECORDS and is worded as one.
 */
final class InventorySignalRules
{
    /** Rows named inside one finding's evidence before it stops being readable. */
    private const MAX_NAMED_IN_EVIDENCE = 6;

    public function __construct(
        private readonly InventoryIntelligence $analytics,
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
            'stocktake_without_outcome' => [
                'Stock-takes recorded with no outcome',
                fn () => $this->stocktakeWithoutOutcome(),
            ],
            'items_not_found' => ['Items scanned and not found', fn () => $this->itemsNotFound()],
            'repeat_scans' => ['Codes scanned more than once', fn () => $this->repeatScans()],
            'requisitions_awaiting' => ['Requisitions still awaiting a decision', fn () => $this->requisitionsAwaiting()],
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

    /**
     * The stock-take ran and nobody recorded what it found.
     *
     * A statement about the RECORDS, and worded as one. The failure mode this
     * replaces reported the same rows as missing assets.
     *
     * @return array<int,array<string,mixed>>
     */
    private function stocktakeWithoutOutcome(): array
    {
        $position = $this->analytics->position();
        if ($position === null) {
            return [];
        }

        $missing = $position['scans'] - $position['scansWithOutcome'];
        if ($missing === 0) {
            return [];
        }

        $share = $position['outcomeCoverage'] === null ? null : round(100 - $position['outcomeCoverage'], 1);

        return [[
            'id' => "inventory-no-outcome-{$this->syear}",
            'rule' => 'stocktake_without_outcome',
            'severity' => $position['scansWithOutcome'] === 0 ? 'high' : 'medium',
            'severityLabel' => $position['scansWithOutcome'] === 0 ? 'High' : 'Medium',
            'title' => $position['scansWithOutcome'] === 0
                ? "All {$position['scans']} scans in this stock-take carry no outcome"
                : "{$missing} of {$position['scans']} scans carry no outcome",
            'whatHappened' => $this->sentence([
                $position['scansWithOutcome'] === 0
                    ? "{$position['scans']} scans covering {$position['itemCodes']} distinct item codes were recorded "
                        .'this year, and not one of them records whether the item was found.'
                    : "{$missing} of {$position['scans']} scans ({$share}%) record no outcome.",
                $position['scansWithOutcome'] === 0
                    ? 'The stock-take therefore has no result: nothing on this screen can say what was verified, and '
                        .'nothing can say what is missing.'
                    : 'Those scans are excluded from the verification rate rather than counted against it.',
            ]),
            'whyItMatters' => 'A stock-take exists to answer one question — is the asset where the register says it '
                .'is. Scans without an outcome record that somebody walked past the shelf, not what they found, so '
                .'the work has been done and the answer has not been captured.',
            'evidence' => [
                ['label' => 'Scans', 'value' => (string) $position['scans']],
                ['label' => 'With an outcome', 'value' => (string) $position['scansWithOutcome']],
                ['label' => 'Without', 'value' => (string) $missing],
                ['label' => 'Distinct item codes', 'value' => (string) $position['itemCodes']],
                [
                    'label' => 'Verification rate',
                    // The em dash IS the finding: there is no rate to show.
                    'value' => $position['verificationRate'] === null ? '—' : "{$position['verificationRate']}%",
                    'note' => $position['verificationRate'] === null ? 'Undefined — no outcome was recorded' : null,
                ],
            ],
            'likelyCause' => 'The outcome field is optional in the scanning workflow, or the scanning device writes '
                .'the code without it. This data shows the field is empty, not which of those emptied it.',
            'causeConfirmed' => false,
            'recommendation' => 'Establish whether the outcome is captured anywhere outside this table before the '
                .'next stock-take; if it is not, the scan is recording attendance at the shelf rather than the state '
                .'of the asset.',
            'owner' => 'Store in-charge',
            'priority' => $position['scansWithOutcome'] === 0 ? 'high' : 'medium',
            'confidence' => ['band' => 'High', 'value' => 0.95],
            'affected' => ['count' => $missing, 'total' => $position['scans'], 'unit' => 'scans'],
            'impact' => ['value' => $missing, 'display' => (string) $missing, 'label' => 'scans with no result'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /** @return array<int,array<string,mixed>> */
    private function itemsNotFound(): array
    {
        $position = $this->analytics->position();
        // NULL means no outcome was recorded, which is not the same as nothing
        // being found. Nothing to say either way.
        if ($position === null || $position['verificationRate'] === null) {
            return [];
        }
        if ($position['verificationRate'] >= InventoryIntelligence::VERIFICATION_ALERT) {
            return [];
        }

        $notFound = $position['itemsNotFound'];
        $prefixes = array_values(array_filter(
            $this->analytics->byCodePrefix(),
            static fn ($p) => $p['verificationRate'] !== null
                && $p['verificationRate'] < InventoryIntelligence::VERIFICATION_ALERT,
        ));
        usort($prefixes, static fn ($a, $b) => $a['verificationRate'] <=> $b['verificationRate']);

        return [[
            'id' => "inventory-not-found-{$this->syear}",
            'rule' => 'items_not_found',
            'severity' => $position['verificationRate'] < 80.0 ? 'high' : 'medium',
            'severityLabel' => $position['verificationRate'] < 80.0 ? 'High' : 'Medium',
            'title' => "{$notFound} scanned items were not found during the stock-take",
            'whatHappened' => $this->sentence([
                "Of {$position['scansWithOutcome']} scans that record an outcome, {$notFound} record the item as not "
                    ."found — a verification rate of {$position['verificationRate']}%.",
                $prefixes !== []
                    ? "The weakest code group is “{$prefixes[0]['key']}” at {$prefixes[0]['verificationRate']}% "
                        ."across {$prefixes[0]['scans']} scans."
                    : null,
            ]),
            'whyItMatters' => 'An asset the register holds and the shelf does not is either misplaced or gone, and '
                .'the difference is usually recoverable only while the stock-take is fresh in someone’s memory.',
            'evidence' => array_merge(
                [
                    ['label' => 'Not found', 'value' => (string) $notFound],
                    ['label' => 'Scans with an outcome', 'value' => (string) $position['scansWithOutcome']],
                    ['label' => 'Verification rate', 'value' => "{$position['verificationRate']}%"],
                ],
                array_map(static fn ($p) => [
                    'label' => "Codes beginning “{$p['key']}”",
                    'value' => "{$p['verificationRate']}% found",
                    'note' => "{$p['scans']} scans across {$p['codes']} codes",
                ], array_slice($prefixes, 0, self::MAX_NAMED_IN_EVIDENCE - 3)),
            ),
            // The prefix groups are real; what they MEAN is not recorded
            // anywhere this module reads, and the finding says so rather than
            // naming a department it cannot see.
            'likelyCause' => 'Item codes group by prefix, but this schema records nothing about what a prefix stands '
                .'for, so where the losses concentrate cannot be turned into a location or a department from here.',
            'causeConfirmed' => false,
            'recommendation' => 'Take the weakest code groups back to whoever assigns the prefixes — the grouping is '
                .'the only structure in the data, and it is the fastest route to where the items were.',
            'owner' => 'Store in-charge',
            'priority' => $position['verificationRate'] < 80.0 ? 'high' : 'medium',
            'confidence' => ['band' => 'High', 'value' => 0.9],
            'affected' => ['count' => $notFound, 'total' => $position['scansWithOutcome'], 'unit' => 'items'],
            'impact' => ['value' => $notFound, 'display' => (string) $notFound, 'label' => 'items not found'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /** @return array<int,array<string,mixed>> */
    private function repeatScans(): array
    {
        $position = $this->analytics->position();
        if ($position === null || $position['repeatShare'] === null) {
            return [];
        }
        if ($position['repeatShare'] < InventoryIntelligence::RESCAN_ALERT_SHARE) {
            return [];
        }

        return [[
            'id' => "inventory-repeat-scans-{$this->syear}",
            'rule' => 'repeat_scans',
            'severity' => 'low',
            'severityLabel' => 'Low',
            'title' => "{$position['scans']} scans cover only {$position['itemCodes']} distinct items",
            'whatHappened' => "{$position['repeatScans']} scans ({$position['repeatShare']}%) repeat a code that had "
                ."already been scanned, so the stock-take covers {$position['itemCodes']} items rather than "
                ."{$position['scans']}.",
            'whyItMatters' => 'The scan count is not the item count. Anything that reads this stock-take as a stock '
                .'figure — a valuation, a coverage claim, a comparison with last year — is out by the repeats unless '
                .'it counts distinct codes.',
            'evidence' => [
                ['label' => 'Scans', 'value' => (string) $position['scans']],
                ['label' => 'Distinct item codes', 'value' => (string) $position['itemCodes']],
                ['label' => 'Repeats', 'value' => (string) $position['repeatScans']],
                ['label' => 'Share of scans', 'value' => "{$position['repeatShare']}%"],
            ],
            'likelyCause' => 'A stock-take that passes the same location more than once, which is ordinary practice '
                .'and not itself a problem.',
            'causeConfirmed' => false,
            'recommendation' => null,
            'owner' => 'Store in-charge',
            'priority' => 'low',
            'confidence' => ['band' => 'High', 'value' => 0.95],
            'affected' => ['count' => $position['repeatScans'], 'total' => $position['scans'], 'unit' => 'scans'],
            'impact' => null,
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /** @return array<int,array<string,mixed>> */
    private function requisitionsAwaiting(): array
    {
        $statuses = $this->analytics->byRequisitionStatus();
        if ($statuses === []) {
            return [];
        }

        $waiting = array_values(array_filter(
            $statuses,
            static fn ($s) => preg_match('/pending|awaiting|requested|no status/i', $s['label']) === 1,
        ));

        if ($waiting === []) {
            return [];
        }

        $lines = array_sum(array_column($waiting, 'lines'));
        $total = array_sum(array_column($statuses, 'lines'));
        $share = $total > 0 ? round($lines / $total * 100, 1) : null;

        return [[
            'id' => "inventory-requisitions-{$this->syear}",
            'rule' => 'requisitions_awaiting',
            'severity' => 'medium',
            'severityLabel' => 'Medium',
            'title' => $lines === 1
                ? 'One requisition line is still awaiting a decision'
                : "{$lines} requisition lines are still awaiting a decision",
            'whatHappened' => $this->sentence([
                "{$lines} of {$total} requisition lines this year".($share !== null ? " ({$share}%)" : '')
                    .($lines === 1 ? ' sits' : ' sit').' in a status that has not been decided.',
                'Requested quantity on those lines totals '
                    .array_sum(array_column($waiting, 'requested')).'.',
            ]),
            'whyItMatters' => 'A requisition nobody has decided is a department waiting without knowing it is '
                .'waiting. The cost of the delay lands where the item was needed, not in the store.',
            'evidence' => array_map(static fn ($s) => [
                'label' => $s['label'],
                'value' => "{$s['lines']} lines",
                'note' => "{$s['requested']} requested · {$s['approved']} approved",
            ], array_slice($statuses, 0, self::MAX_NAMED_IN_EVIDENCE)),
            'likelyCause' => null,
            'causeConfirmed' => false,
            'recommendation' => 'Clear the undecided lines, or record the decision that was taken outside the system.',
            'owner' => 'Store in-charge',
            'priority' => 'medium',
            'confidence' => ['band' => 'High', 'value' => 0.9],
            'affected' => ['count' => $lines, 'total' => $total, 'unit' => 'requisition lines'],
            'impact' => ['value' => $lines, 'display' => (string) $lines, 'label' => 'lines undecided'],
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
