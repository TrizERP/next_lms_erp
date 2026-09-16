<?php

namespace App\Brain\Intelligence;

/**
 * The fee rules: the only place allowed to say a fee number MEANS something.
 *
 * FeesIntelligence counts. This class interprets, and every interpretation it
 * makes is written down as a signal with the evidence it rests on, so a reader
 * can always get from the sentence back to the rows.
 *
 * ── The three guarantees ────────────────────────────────────────────────────
 *
 * 1. A RULE THAT CANNOT MEET ITS EVIDENCE FLOOR WRITES NOTHING, and says which
 *    floor it missed. This is the guarantee that matters most here, because fee
 *    data is sparse in exactly the way that fools proportion-based rules: three
 *    accounts in arrears are always "100% concentrated in the top three", and a
 *    cycle with one receipt always "fell 100%" against the cycle before it.
 *    Both sentences are arithmetically true and completely uninformative. Every
 *    rule below therefore declares a minimum population, and skip() records the
 *    refusal so the screen can show "insufficient evidence" instead of a
 *    confident fabrication.
 *
 * 2. EVERY NUMBER IN A SIGNAL CAME FROM A QUERY IN THIS RUN. There are no
 *    defaults, no seeds, no illustrative figures, and no rounding of an unknown
 *    to a plausible-looking value. Where a quantity is genuinely unknown the
 *    metadata carries null and the surface says so.
 *
 * 3. A RULE NEVER PREDICTS AN OUTCOME. Recommendations say "worth reviewing"
 *    and "potentially addresses", and the expected-impact figure is always an
 *    amount that is ALREADY OUTSTANDING — money that exists and is measurable
 *    today, not recovery this class has no basis to forecast. What actually
 *    happens is the Outcome stage's business, and it is recorded, not guessed.
 *
 * ── Year and tenant ─────────────────────────────────────────────────────────
 *
 * The writer is constructed with the academic year, so every signal, piece of
 * evidence, case and recommendation this class produces is stamped with it and
 * a run for 2020 cannot overwrite 2021's findings (SignalWriter, note 1).
 *
 * ── Why these rules and not others ──────────────────────────────────────────
 *
 * Each rule answers a question a bursar actually asks, and only those the
 * database can answer from real columns. Aging uses the cycle calendar, which
 * exists. Repeat-defaulter history across years is NOT implemented, because
 * establishing it needs a per-year ledger for every prior year and this
 * institute's older years have no matching fee structure at all — a rule that
 * would silently report "no repeat defaulters" from missing data is worse than
 * no rule. Cross-module claims (fees against attendance, fees against marks)
 * are likewise absent: two modules having data is not evidence that one
 * explains the other, and this engine does not assert correlation it has not
 * measured.
 */
final class FeesSignalRules
{
    private readonly FeesIntelligence $fees;

    public function __construct(
        private readonly string $tenantId,
        private readonly SignalWriter $writer,
        private readonly ?string $syear = null,
        ?FeesIntelligence $fees = null,
    ) {
        $this->fees = $fees ?? new FeesIntelligence($this->tenantId, $this->syear);
    }

    /**
     * The rules that can run at all for this institute-year.
     *
     * A year with no fee position produces an EMPTY rule set rather than a set
     * of rules that each independently discover there is no data. The caller
     * reports "no fee data for this year" once, in coverage()'s own words.
     *
     * @return array<string, callable(): array>
     */
    public function applicable(): array
    {
        if (! $this->fees->coverage()['available']) {
            return [];
        }

        return [
            'fee_collection_shortfall' => fn () => $this->collectionShortfall(),
            'fee_receipt_coverage' => fn () => $this->receiptCoverage(),
            'fee_outstanding_concentration' => fn () => $this->outstandingConcentration(),
            'fee_overdue_backlog' => fn () => $this->overdueBacklog(),
            'fee_cycle_decline' => fn () => $this->cycleDecline(),
            'fee_class_collection_gap' => fn () => $this->classCollectionGap(),
            'fee_head_collection_gap' => fn () => $this->headCollectionGap(),
            'fee_payment_mode_concentration' => fn () => $this->paymentModeConcentration(),
            'fee_cancellation_pressure' => fn () => $this->cancellationPressure(),
            'fee_reconciliation_gap' => fn () => $this->reconciliationGap(),
        ];
    }

    /* ================================================== collection position */

    /**
     * The year's collection rate is materially short of what the institute
     * billed.
     */
    private function collectionShortfall(): array
    {
        $position = $this->fees->position();
        $rate = $position['collectionRate'];

        if ($rate === null) {
            return $this->skip('no_demand');
        }
        if ($position['feeAccounts'] < $this->floor('fee_min_accounts', 10)) {
            return $this->skip('too_few_accounts');
        }

        $floor = (float) $this->threshold('fee_collection_rate_floor', 70.0);
        if ($rate >= $floor) {
            return $this->skip('above_threshold');
        }

        $evidence = [$this->writer->recordEvidence([
            'source' => 'vivek_erp.fees_breackoff + fees_collect',
            'issue' => 'collection for the year is short of the amount billed',
            'syear' => $this->syear,
            'demandAmount' => $position['demandAmount'],
            'collectedAmount' => $position['collectedAmount'],
            'outstandingAmount' => $position['outstandingAmount'],
            'collectionRatePercent' => $rate,
            'feeAccounts' => $position['feeAccounts'],
            'payingAccounts' => $position['payingAccounts'],
            'currency' => 'INR',
        ])];

        return $this->writer->raise([
            'source' => 'vivek_erp.fees_collect',
            'classification' => 'collection_performance',
            // Severity tracks how far short the year is, not how large the
            // school is — a small institute 90% short is in more trouble than a
            // large one 20% short.
            'severity' => $rate < $floor / 2 ? 'high' : 'medium',
            'priority' => 'high',
            'confidence' => 1.0,
            'relatedEntityType' => 'Fees',
            'metadata' => [
                'rule' => 'fee_collection_shortfall',
                'title' => sprintf(
                    '%s of the %s billed this year has been collected',
                    Narrative::money($position['collectedAmount']),
                    Narrative::money($position['demandAmount'])
                ),
                'syear' => $this->syear,
                'collectionRatePercent' => $rate,
                'thresholdPercent' => $floor,
                'demandAmount' => $position['demandAmount'],
                'collectedAmount' => $position['collectedAmount'],
                'outstandingAmount' => $position['outstandingAmount'],
                'affectedCount' => $position['defaulterAccounts'],
                'totalCount' => $position['feeAccounts'],
                'impactAmount' => $position['outstandingAmount'],
                'unit' => 'fee accounts',
            ],
        ], $evidence);
    }

    /**
     * Most fee accounts have no receipt against them at all.
     *
     * Distinct from the shortfall rule: that one is about money, this one is
     * about whether collection is being RECORDED. A school that collects at the
     * gate and enters receipts late looks identical to one that has not
     * collected, and the difference matters to what anybody should do next —
     * which is why the recommendation is to check recording before chasing.
     */
    private function receiptCoverage(): array
    {
        $position = $this->fees->position();
        $accounts = $position['feeAccounts'];

        if ($accounts < $this->floor('fee_min_accounts', 10)) {
            return $this->skip('too_few_accounts');
        }

        $without = $accounts - $position['payingAccounts'];
        $share = $without / $accounts;

        if ($share < (float) $this->threshold('fee_receipt_coverage', 0.50)) {
            return $this->skip('below_threshold');
        }

        $evidence = [$this->writer->recordEvidence([
            'source' => 'vivek_erp.fees_collect',
            'issue' => 'most fee accounts carry no receipt for this year',
            'syear' => $this->syear,
            'feeAccounts' => $accounts,
            'accountsWithReceipts' => $position['payingAccounts'],
            'accountsWithoutReceipts' => $without,
            'receipts' => $position['receipts'],
        ])];

        return $this->writer->raise([
            'source' => 'vivek_erp.fees_collect',
            'classification' => 'process_adoption',
            'severity' => $share >= 0.90 ? 'high' : 'medium',
            'priority' => 'high',
            'confidence' => 1.0,
            'relatedEntityType' => 'Fees',
            'metadata' => [
                'rule' => 'fee_receipt_coverage',
                'title' => sprintf('%d of %d fee accounts have no receipt recorded this year', $without, $accounts),
                'syear' => $this->syear,
                'affectedCount' => $without,
                'totalCount' => $accounts,
                'share' => round($share, 4),
                'receipts' => $position['receipts'],
                'unit' => 'fee accounts',
            ],
        ], $evidence);
    }

    /* ====================================================== where it sits */

    /** A small number of accounts hold most of the outstanding balance. */
    private function outstandingConcentration(): array
    {
        $concentration = $this->fees->concentration(
            (int) $this->threshold('fee_concentration_accounts', 10)
        );

        if (! $concentration['available']) {
            return $this->skip('insufficient_accounts');
        }
        if ($concentration['defaulterAccounts'] < $this->floor('fee_min_defaulters', 5)) {
            return $this->skip('too_few_defaulters');
        }

        $share = $concentration['share'] / 100;
        if ($share < (float) $this->threshold('fee_outstanding_concentration', 0.60)) {
            return $this->skip('below_threshold');
        }

        $top = $this->fees->outstandingAccounts($concentration['topCount']);

        $evidence = [
            $this->writer->recordEvidence([
                'source' => 'vivek_erp.fees_breackoff + fees_collect',
                'issue' => 'outstanding fees are concentrated in a small number of accounts',
                'syear' => $this->syear,
                'outstandingAmount' => $concentration['totalAmount'],
                'accountsInArrears' => $concentration['defaulterAccounts'],
                'topAccounts' => $concentration['topCount'],
                'topAccountsAmount' => $concentration['topAmount'],
                'topAccountsSharePercent' => $concentration['share'],
                'currency' => 'INR',
            ]),
            $this->writer->recordEvidence([
                'source' => 'vivek_erp.fees_breackoff + fees_collect',
                'issue' => 'the largest overdue accounts, by amount',
                'syear' => $this->syear,
                // Class and amount only. Naming the account is what makes the
                // finding actionable; guardian contact details belong to the
                // Fees module's own screens and their own permissions.
                'accounts' => array_map(fn ($row) => [
                    'enrollmentNo' => $row['enrollmentNo'],
                    'class' => $row['className'],
                    'outstandingAmount' => $row['outstandingAmount'],
                ], $top['rows']),
            ]),
        ];

        return $this->writer->raise([
            'source' => 'vivek_erp.fees_collect',
            'classification' => 'collection_concentration',
            'severity' => $share >= 0.80 ? 'high' : 'medium',
            'priority' => 'high',
            'confidence' => 1.0,
            'relatedEntityType' => 'Fees',
            'metadata' => [
                'rule' => 'fee_outstanding_concentration',
                'title' => sprintf(
                    '%s%% of outstanding fees sit in %d of %d accounts in arrears',
                    number_format($concentration['share'], 1),
                    $concentration['topCount'],
                    $concentration['defaulterAccounts']
                ),
                'syear' => $this->syear,
                'affectedCount' => $concentration['topCount'],
                'totalCount' => $concentration['defaulterAccounts'],
                'share' => round($share, 4),
                'outstandingAmount' => $concentration['totalAmount'],
                // What reviewing those accounts would put in front of someone.
                // It is money already owed and already counted, not recovery.
                'impactAmount' => $concentration['topAmount'],
                'unit' => 'accounts',
            ],
        ], $evidence);
    }

    /** Money billed for cycles that have already passed and is still unpaid. */
    private function overdueBacklog(): array
    {
        $position = $this->fees->position();
        $demand = $position['demandAmount'];
        $overdue = $position['overdueAmount'];

        if ($demand <= 0) {
            return $this->skip('no_demand');
        }
        if ($position['overdueCycles'] < 1) {
            return $this->skip('no_past_cycles');
        }

        $share = $overdue / $demand;
        if ($share < (float) $this->threshold('fee_overdue_share', 0.25)) {
            return $this->skip('below_threshold');
        }

        $evidence = [$this->writer->recordEvidence([
            'source' => 'vivek_erp.fees_breackoff + fees_collect',
            'issue' => 'fees billed for cycles that have already passed remain unpaid',
            'syear' => $this->syear,
            'overdueAmount' => $overdue,
            'demandAmount' => $demand,
            'overdueSharePercent' => round($share * 100, 1),
            'overdueCycles' => $position['overdueCycles'],
            'agingBands' => $position['agingBands'],
            'currency' => 'INR',
        ])];

        return $this->writer->raise([
            'source' => 'vivek_erp.fees_collect',
            'classification' => 'collection_aging',
            'severity' => $share >= 0.60 ? 'high' : 'medium',
            'priority' => 'high',
            'confidence' => 1.0,
            'relatedEntityType' => 'Fees',
            'metadata' => [
                'rule' => 'fee_overdue_backlog',
                'title' => sprintf('%s is overdue across %d past fee cycles', Narrative::money($overdue), $position['overdueCycles']),
                'syear' => $this->syear,
                'overdueAmount' => $overdue,
                'demandAmount' => $demand,
                'share' => round($share, 4),
                'affectedCount' => $position['overdueCycles'],
                'agingBands' => $position['agingBands'],
                'impactAmount' => $overdue,
                'unit' => 'cycles',
            ],
        ], $evidence);
    }

    /**
     * Collection fell between the last two completed cycles that were actually
     * billed.
     *
     * "Actually billed" is doing real work: fee calendars contain cycles with no
     * demand at all, and comparing a billed cycle against an empty one produces
     * a 100% collapse that describes the calendar, not the collection.
     */
    private function cycleDecline(): array
    {
        $minimum = (float) $this->threshold('fee_min_cycle_amount', 1000.0);

        $billed = array_values(array_filter(
            $this->fees->cycles(),
            fn ($cycle) => $cycle['isPast'] && $cycle['demandAmount'] >= $minimum
        ));

        if (count($billed) < 2) {
            return $this->skip('insufficient_history');
        }

        $current = $billed[count($billed) - 1];
        $previous = $billed[count($billed) - 2];

        if ($previous['collectedAmount'] < $minimum) {
            return $this->skip('baseline_too_small');
        }

        $change = ($current['collectedAmount'] - $previous['collectedAmount']) / $previous['collectedAmount'];
        if ($change >= 0 || abs($change) < (float) $this->threshold('fee_cycle_decline', 0.25)) {
            return $this->skip('no_material_decline');
        }

        $evidence = [$this->writer->recordEvidence([
            'source' => 'vivek_erp.fees_collect',
            'issue' => 'collection fell between the last two billed fee cycles',
            'syear' => $this->syear,
            'currentCycle' => $current['label'],
            'currentCollected' => $current['collectedAmount'],
            'currentDemand' => $current['demandAmount'],
            'previousCycle' => $previous['label'],
            'previousCollected' => $previous['collectedAmount'],
            'previousDemand' => $previous['demandAmount'],
            'changePercent' => round($change * 100, 1),
            'currency' => 'INR',
        ])];

        return $this->writer->raise([
            'source' => 'vivek_erp.fees_collect',
            'classification' => 'collection_trend',
            'severity' => abs($change) >= 0.50 ? 'high' : 'medium',
            'priority' => 'normal',
            'confidence' => 1.0,
            'relatedEntityType' => 'Fees',
            'metadata' => [
                'rule' => 'fee_cycle_decline',
                'title' => sprintf(
                    'Collection fell %s%% from %s to %s',
                    number_format(abs($change) * 100, 1),
                    $previous['label'],
                    $current['label']
                ),
                'syear' => $this->syear,
                'currentPeriod' => $current['label'],
                'previousPeriod' => $previous['label'],
                'currentAmount' => $current['collectedAmount'],
                'previousAmount' => $previous['collectedAmount'],
                'changePercent' => round($change * 100, 1),
                'affectedCount' => null,
                'impactAmount' => round($previous['collectedAmount'] - $current['collectedAmount'], 2),
                'unit' => 'cycles',
            ],
        ], $evidence);
    }

    /* ================================================== where to look next */

    /** One or more classes are collecting materially below the institute's rate. */
    private function classCollectionGap(): array
    {
        $position = $this->fees->position();
        $baseline = $position['collectionRate'];

        if ($baseline === null) {
            return $this->skip('no_demand');
        }

        $gap = (float) $this->threshold('fee_class_gap_points', 15.0);
        $minAccounts = $this->floor('fee_min_class_accounts', 5);

        $behind = array_values(array_filter(
            $this->fees->classes(),
            fn ($row) => $row['collectionRate'] !== null
                && $row['accounts'] >= $minAccounts
                && $row['outstandingAmount'] > 0
                && ($baseline - $row['collectionRate']) >= $gap
        ));

        if ($behind === []) {
            return $this->skip('no_class_behind_baseline');
        }

        usort($behind, fn ($a, $b) => $b['outstandingAmount'] <=> $a['outstandingAmount']);
        $named = array_slice($behind, 0, 8);
        $impact = array_sum(array_map(fn ($row) => $row['outstandingAmount'], $behind));

        $evidence = [$this->writer->recordEvidence([
            'source' => 'vivek_erp.fees_breackoff + fees_collect + tblstudent_enrollment',
            'issue' => 'classes collecting below the institute\'s own collection rate',
            'syear' => $this->syear,
            'instituteRatePercent' => $baseline,
            'gapThresholdPoints' => $gap,
            'classes' => array_map(fn ($row) => [
                'class' => $row['label'],
                'accounts' => $row['accounts'],
                'collectionRatePercent' => $row['collectionRate'],
                'gapPoints' => round($baseline - $row['collectionRate'], 1),
                'outstandingAmount' => $row['outstandingAmount'],
            ], $named),
            'currency' => 'INR',
        ])];

        return $this->writer->raise([
            'source' => 'vivek_erp.fees_collect',
            'classification' => 'collection_distribution',
            'severity' => count($behind) >= 5 ? 'high' : 'medium',
            'priority' => 'normal',
            'confidence' => 1.0,
            'relatedEntityType' => 'Class',
            'metadata' => [
                'rule' => 'fee_class_collection_gap',
                'title' => count($behind) === 1
                    ? sprintf('%s is collecting %s points below the school rate', $named[0]['label'], number_format($baseline - $named[0]['collectionRate'], 1))
                    : sprintf('%d classes are collecting well below the school rate', count($behind)),
                'syear' => $this->syear,
                'affectedCount' => count($behind),
                'totalCount' => count($this->fees->classes()),
                'instituteRatePercent' => $baseline,
                'classes' => array_map(fn ($row) => $row['label'], $named),
                'impactAmount' => round($impact, 2),
                'unit' => 'classes',
            ],
        ], $evidence);
    }

    /** A fee head is collecting materially below the institute's rate. */
    private function headCollectionGap(): array
    {
        $position = $this->fees->position();
        $baseline = $position['collectionRate'];

        if ($baseline === null) {
            return $this->skip('no_demand');
        }

        $gap = (float) $this->threshold('fee_head_gap_points', 15.0);

        $behind = array_values(array_filter(
            $this->fees->heads(),
            fn ($row) => $row['collectionRate'] !== null
                // A head whose collection the receipt tables cannot attribute
                // would always look like 0% collected. Reporting that as a
                // collection failure would be a bug dressed as a finding.
                && $row['collectionAttributable']
                && $row['outstandingAmount'] > 0
                && ($baseline - $row['collectionRate']) >= $gap
        ));

        if ($behind === []) {
            return $this->skip('no_head_behind_baseline');
        }

        usort($behind, fn ($a, $b) => $b['outstandingAmount'] <=> $a['outstandingAmount']);
        $named = array_slice($behind, 0, 8);
        $impact = array_sum(array_map(fn ($row) => $row['outstandingAmount'], $behind));

        $evidence = [$this->writer->recordEvidence([
            'source' => 'vivek_erp.fees_breackoff + fees_title + fees_collect',
            'issue' => 'fee heads collecting below the institute\'s own collection rate',
            'syear' => $this->syear,
            'instituteRatePercent' => $baseline,
            'gapThresholdPoints' => $gap,
            'heads' => array_map(fn ($row) => [
                'head' => $row['label'],
                'demandAmount' => $row['demandAmount'],
                'collectedAmount' => $row['collectedAmount'],
                'collectionRatePercent' => $row['collectionRate'],
                'outstandingAmount' => $row['outstandingAmount'],
            ], $named),
            'currency' => 'INR',
        ])];

        return $this->writer->raise([
            'source' => 'vivek_erp.fees_collect',
            'classification' => 'collection_distribution',
            'severity' => 'medium',
            'priority' => 'normal',
            'confidence' => 1.0,
            'relatedEntityType' => 'Fees',
            'metadata' => [
                'rule' => 'fee_head_collection_gap',
                'title' => count($behind) === 1
                    ? sprintf('%s is collecting well below the school rate', $named[0]['label'])
                    : sprintf('%d fee heads are collecting well below the school rate', count($behind)),
                'syear' => $this->syear,
                'affectedCount' => count($behind),
                'totalCount' => count($this->fees->heads()),
                'instituteRatePercent' => $baseline,
                'heads' => array_map(fn ($row) => $row['label'], $named),
                'impactAmount' => round($impact, 2),
                'unit' => 'fee heads',
            ],
        ], $evidence);
    }

    /**
     * Almost all fee money arrives through one payment mode.
     *
     * Raised as a handling/continuity observation, not a collection failure —
     * which is why its severity stays low and its recommendation is to review
     * rather than to act.
     */
    private function paymentModeConcentration(): array
    {
        $modes = $this->fees->paymentModes();
        if (count($modes) < 2) {
            return $this->skip('single_mode_recorded');
        }

        $total = array_sum(array_map(fn ($row) => $row['amount'], $modes));
        $receipts = array_sum(array_map(fn ($row) => $row['receipts'], $modes));

        if ($total <= 0 || $receipts < $this->floor('fee_min_receipts', 10)) {
            return $this->skip('too_few_receipts');
        }

        $leader = $modes[0];
        $share = $leader['amount'] / $total;

        if ($share < (float) $this->threshold('fee_payment_mode_concentration', 0.85)) {
            return $this->skip('below_threshold');
        }

        $evidence = [$this->writer->recordEvidence([
            'source' => 'vivek_erp.fees_collect + fees_paid_other',
            'issue' => 'fee collection arrives almost entirely through one payment mode',
            'syear' => $this->syear,
            'mode' => $leader['mode'],
            'modeAmount' => $leader['amount'],
            'totalCollected' => round($total, 2),
            'modeSharePercent' => round($share * 100, 1),
            'receipts' => $receipts,
            'modes' => $modes,
            'currency' => 'INR',
        ])];

        return $this->writer->raise([
            'source' => 'vivek_erp.fees_collect',
            'classification' => 'payment_behaviour',
            'severity' => 'low',
            'priority' => 'low',
            'confidence' => 1.0,
            'relatedEntityType' => 'Fees',
            'metadata' => [
                'rule' => 'fee_payment_mode_concentration',
                'title' => sprintf('%s%% of fee collection arrives as %s', number_format($share * 100, 1), strtolower($leader['mode'])),
                'syear' => $this->syear,
                'mode' => $leader['mode'],
                'share' => round($share, 4),
                'affectedCount' => $leader['receipts'],
                'totalCount' => $receipts,
                'collectedAmount' => round($total, 2),
                'unit' => 'receipts',
            ],
        ], $evidence);
    }

    /* ================================================ data quality / ledger */

    /**
     * More money was cancelled this year than the collection figure comfortably
     * supports.
     *
     * A cancelled receipt is money the school counted and then un-counted. Where
     * cancellation is a large fraction of collection, the headline collection
     * rate overstates what actually arrived, and the reason is operational —
     * wrong receipts being issued and reversed, or collection being re-keyed.
     */
    private function cancellationPressure(): array
    {
        $adjustments = $this->fees->adjustments();

        if (! $adjustments['available']) {
            return $this->skip('cancellation_not_recorded');
        }
        if ($adjustments['cancelledReceipts'] < $this->floor('fee_min_cancellations', 5)) {
            return $this->skip('too_few_cancellations');
        }

        $share = $adjustments['cancelledShareOfCollection'];
        if ($share === null) {
            return $this->skip('no_collection_to_compare');
        }
        if ($share < (float) $this->threshold('fee_cancellation_share', 25.0)) {
            return $this->skip('below_threshold');
        }

        $position = $this->fees->position();

        $evidence = [$this->writer->recordEvidence([
            'source' => 'vivek_erp.fees_cancel + fees_other_cancel + fees_collect',
            'issue' => 'cancelled receipts are large relative to the collection recorded',
            'syear' => $this->syear,
            'cancelledReceipts' => $adjustments['cancelledReceipts'],
            'cancelledAmount' => $adjustments['cancelledAmount'],
            'collectedAmount' => $position['collectedAmount'],
            'cancelledSharePercent' => $share,
            'refunds' => $adjustments['refunds'],
            'refundedAmount' => $adjustments['refundedAmount'],
            'currency' => 'INR',
        ])];

        return $this->writer->raise([
            'source' => 'vivek_erp.fees_cancel',
            'classification' => 'ledger_quality',
            'severity' => $share >= 100 ? 'high' : 'medium',
            'priority' => 'normal',
            'confidence' => 1.0,
            'relatedEntityType' => 'Fees',
            'metadata' => [
                'rule' => 'fee_cancellation_pressure',
                'title' => sprintf(
                    '%s was cancelled against %s collected this year',
                    Narrative::money($adjustments['cancelledAmount']),
                    Narrative::money($position['collectedAmount'])
                ),
                'syear' => $this->syear,
                'cancelledAmount' => $adjustments['cancelledAmount'],
                'collectedAmount' => $position['collectedAmount'],
                'cancelledSharePercent' => $share,
                'affectedCount' => $adjustments['cancelledReceipts'],
                'unit' => 'cancelled receipts',
            ],
        ], $evidence);
    }

    /**
     * Receipts the school has cancelled that still read as live collection.
     *
     * The most precisely defined rule here: a row present in `fees_cancel` and
     * still flagged as not deleted in `fees_collect` is double-counted by every
     * report that filters on that flag. There is no judgement in it — the rows
     * either match or they do not.
     */
    private function reconciliationGap(): array
    {
        $gaps = $this->fees->reconciliationGaps();

        if (! $gaps['available']) {
            return $this->skip('cancellation_not_recorded');
        }
        if ($gaps['count'] < 1) {
            return $this->skip('no_gap');
        }

        $evidence = [$this->writer->recordEvidence([
            'source' => 'vivek_erp.fees_collect + fees_cancel',
            'issue' => 'receipts recorded as cancelled are still counted as collected',
            'syear' => $this->syear,
            'receipts' => $gaps['count'],
            'amount' => $gaps['amount'],
            'match' => 'present in fees_cancel and still not flagged deleted in fees_collect',
            'currency' => 'INR',
        ])];

        return $this->writer->raise([
            'source' => 'vivek_erp.fees_collect',
            'classification' => 'ledger_quality',
            'severity' => 'high',
            'priority' => 'high',
            'confidence' => 1.0,
            'relatedEntityType' => 'Fees',
            'metadata' => [
                'rule' => 'fee_reconciliation_gap',
                'title' => sprintf(
                    '%d cancelled %s still counted as collected',
                    $gaps['count'],
                    $gaps['count'] === 1 ? 'receipt is' : 'receipts are'
                ),
                'syear' => $this->syear,
                'affectedCount' => $gaps['count'],
                'collectedAmount' => $gaps['amount'],
                'impactAmount' => $gaps['amount'],
                'unit' => 'receipts',
            ],
        ], $evidence);
    }

    /* ============================================================== helpers */

    private function threshold(string $key, float|int $default): float|int
    {
        $value = config('brain.thresholds.'.$key);

        return $value === null ? $default : $value;
    }

    private function floor(string $key, int $default): int
    {
        return (int) $this->threshold($key, $default);
    }

    /**
     * A rule declining to fire, with the reason.
     *
     * The reason is not decoration: it is what lets the screen distinguish
     * "checked, and this is fine" from "could not check", and those are
     * different answers to the user.
     *
     * @return array{created: false, refreshed: false, signalId: null, reason: string}
     */
    private function skip(string $reason): array
    {
        return ['created' => false, 'refreshed' => false, 'signalId' => null, 'reason' => $reason];
    }
}
