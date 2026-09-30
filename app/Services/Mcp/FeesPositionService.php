<?php

namespace App\Services\Mcp;

use App\Brain\Intelligence\FeesIntelligence;
use Throwable;

/**
 * The school-wide fee position and the ranked accounts behind it, for the chat.
 *
 * ## Why this exists next to `FeesArrearsService`
 *
 * `fees.arrears` reuses the fee screen's per-student calculation, which costs one call per
 * student — so it can only ever examine a bounded cohort (25 by default, 100 at most) and
 * says so. That is honest, but it cannot answer the questions a bursar actually asks: "how
 * much is pending?", "who owes the most?", "which class is worst?". On a 268-student
 * institute it reported 7 defaulters and ₹54,799 from the first 25 students in id order,
 * when the real position was 66 accounts and ₹2,92,299.
 *
 * This service answers those from `FeesIntelligence`, the ledger the Brain's Fees
 * dashboard already reads: one grouped query per institute and year, arrears floored per
 * student, so an overpayer never cancels out a debtor. It does not define arrears a second
 * time. For the accounts it returns it agrees with the fee screen's own per-student figure
 * to the rupee — checked account by account against `FeesPendingService`, which is the
 * screen's calculation.
 *
 * ## What it will not do
 *
 * It never substitutes a zero for an absence. An institute with no fee structure for the
 * year is reported as having no fee data, not as owing nothing.
 */
class FeesPositionService
{
    /** Detail lookups are one fee-screen call per student, so they are capped hard. */
    private const MAX_DUE_LOOKUPS = 15;

    /** Fields in this service's payloads that are rupee amounts, declared so they are shown as money. */
    private const MONEY = [
        'total_demand', 'total_collected', 'total_outstanding', 'overdue_amount', 'average_owed_per_student',
        'concession_amount', 'outstanding', 'collected', 'oldest_due_amount', 'shown_outstanding',
        'class_total_outstanding',
    ];

    private const DEFAULT_LIMIT = 10;

    private const MAX_LIMIT = 50;

    public function __construct(private readonly FeesPendingService $pending)
    {
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function position(McpRequestContext $context, array $arguments): array
    {
        $ledger = $this->ledger($context);

        if (is_array($ledger) && isset($ledger['success'])) {
            return $ledger;
        }

        /** @var FeesIntelligence $fees */
        $fees = $ledger;

        $position = $fees->position();
        $classes = $fees->classes();

        $limit = $this->limit($arguments);
        $rankedClasses = [];

        foreach (array_slice($classes, 0, $limit) as $class) {
            $rankedClasses[] = [
                'standard_name' => $class['label'],
                'standard_id' => (int) $class['standardId'],
                'outstanding' => $class['outstandingAmount'],
                'students_owing' => $class['defaulterAccounts'],
                'fee_accounts' => $class['accounts'],
                'collection_rate' => $class['collectionRate'] === null ? null : $class['collectionRate'] . '%',
            ];
        }

        $concentration = $fees->concentration(10);

        $sentence = $this->positionSentence($position, $context);

        return ToolResult::success(
            'fees.position',
            $sentence,
            [
                'headline' => $sentence,
                'money_fields' => self::MONEY,
                'academic_year' => $context->academicYear,
                'fee_accounts' => $position['feeAccounts'],
                'students_owing' => $position['defaulterAccounts'],
                'students_fully_paid' => $position['fullySettledAccounts'],
                'total_demand' => $position['demandAmount'],
                'total_collected' => $position['collectedAmount'],
                'total_outstanding' => $position['outstandingAmount'],
                'overdue_amount' => $position['overdueAmount'],
                'overdue_cycles' => $position['overdueCycles'],
                'collection_rate' => $position['collectionRate'] === null ? null : $position['collectionRate'] . '%',
                'average_owed_per_student' => $position['averageOutstandingPerDefaulter'],
                'concession_amount' => $position['concessionAmount'],
                'aging' => $position['agingBands'],
                'classes_with_most_pending' => $rankedClasses,
                'classes_total' => count($classes),
                'top_ten_share_percent' => ($concentration['available'] ?? false) ? $concentration['share'] : null,
                'basis' => 'School-wide, from the fee ledger for this institute and academic year. '
                    . 'Arrears are counted per student, so a family that overpaid never offsets one that owes.',
            ]
        );
    }

    /**
     * Ranked accounts that owe money — the whole school, one class, or named students.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function accounts(McpRequestContext $context, array $arguments): array
    {
        $ledger = $this->ledger($context);

        if (is_array($ledger) && isset($ledger['success'])) {
            return $ledger;
        }

        /** @var FeesIntelligence $fees */
        $fees = $ledger;

        $limit = $this->limit($arguments);
        $standardId = ! empty($arguments['standard_id']) ? (string) (int) $arguments['standard_id'] : null;

        $requested = null;

        if (! empty($arguments['student_ids']) && is_array($arguments['student_ids'])) {
            $requested = array_values(array_unique(array_filter(
                array_map('intval', $arguments['student_ids']),
                static fn (int $id) => $id > 0
            )));
        }

        $result = $fees->outstandingAccounts($limit, 0, $standardId, $requested);
        $rows = $result['rows'];

        $withDue = (bool) ($arguments['include_due_months'] ?? false);
        $accounts = [];

        foreach ($rows as $index => $row) {
            $account = [
                // Order is display order: the composer shows the first few fields of a row,
                // so what a bursar reads first - class and amount - leads.
                'student_id' => (int) $row['studentId'],
                'student_name' => $row['name'],
                'standard_name' => $row['className'],
                'outstanding' => $row['outstandingAmount'],
                'collected' => $row['collectedAmount'],
                'enrollment_no' => $row['enrollmentNo'],
                'failed_payments' => $row['failureCount'],
                'autopay_registered' => $row['mandateRegistered'],
            ];

            if ($withDue && $index < self::MAX_DUE_LOOKUPS) {
                // Placed ahead of collected/enrolment so the oldest unpaid month is visible.
                $account = array_slice($account, 0, 4, true)
                    + $this->dueMonths($context, (int) $row['studentId'])
                    + array_slice($account, 4, null, true);
            }

            $accounts[] = $account;
        }

        $total = (float) ($result['scope']['outstandingAmount'] ?? 0);
        $shown = array_sum(array_column($accounts, 'outstanding'));

        $notOwing = [];

        if ($requested !== null) {
            $found = array_column($accounts, 'student_id');
            $notOwing = array_values(array_diff($requested, $found));
            $total = $shown;
        }

        $sentence = $this->accountsSentence([
            'accounts_matching' => $result['total'],
            'accounts_shown' => count($accounts),
            'shown_outstanding' => round($shown, 2),
            'scope' => $result['scope'],
        ], $requested);

        $data = [
            'headline' => $sentence,
            'money_fields' => self::MONEY,
            'academic_year' => $context->academicYear,
            'accounts' => $accounts,
            'accounts_matching' => $result['total'],
            'accounts_shown' => count($accounts),
            'shown_outstanding' => round($shown, 2),
            'scope' => $result['scope'],
            'students_not_owing' => $notOwing,
            'due_months_included' => $withDue,
            'due_months_note' => $withDue && count($rows) > self::MAX_DUE_LOOKUPS
                ? sprintf('Due months were looked up for the first %d accounts only.', self::MAX_DUE_LOOKUPS)
                : null,
            'ranked_by' => 'amount outstanding, largest first',
            'basis' => 'Read from the fee ledger for this institute and academic year, per student.',
        ];

        if ($total > 0 && $requested === null && $standardId !== null) {
            $data['class_total_outstanding'] = round($total, 2);
        }

        return ToolResult::success('fees.outstanding_accounts', $sentence, $data);
    }

    // ---------------------------------------------------------------- internals

    /**
     * The ledger, or a ready-made failure when this institute has none to read.
     *
     * @return FeesIntelligence|array<string, mixed>
     */
    private function ledger(McpRequestContext $context): FeesIntelligence|array
    {
        if ($context->academicYear === null) {
            return ToolResult::failure(
                'fees.position',
                'No academic year is selected, and fees belong to a year, so there is nothing to read yet.',
                'NO_ACADEMIC_YEAR'
            );
        }

        try {
            $fees = new FeesIntelligence((string) $context->selectedInstituteId, (string) $context->academicYear);
            $coverage = $fees->coverage();
        } catch (Throwable $exception) {
            return ToolResult::failure(
                'fees.position',
                'The fee ledger could not be read just now. This is not a finding that nothing is owed.',
                'FEE_LEDGER_UNAVAILABLE',
                ['reason' => $exception->getMessage()]
            );
        }

        if (! ($coverage['available'] ?? false)) {
            return ToolResult::failure(
                'fees.position',
                (string) ($coverage['reason'] ?? 'This institute has no fee data for the selected academic year.'),
                'NO_FEE_DATA'
            );
        }

        return $fees;
    }

    /** @param array<string, mixed> $arguments */
    private function limit(array $arguments): int
    {
        return min(max((int) ($arguments['limit'] ?? self::DEFAULT_LIMIT), 1), self::MAX_LIMIT);
    }

    /**
     * Which fee months a student still owes, from the fee screen's own per-student figures.
     *
     * The ledger is per account, not per month, so the month detail comes from the same
     * calculation the fee screen uses. A failed lookup is reported on the row rather than
     * dropped, so a missing date never reads as "nothing pending".
     *
     * @return array<string, mixed>
     */
    private function dueMonths(McpRequestContext $context, int $studentId): array
    {
        try {
            $result = $this->pending->getPending($context, ['student_id' => $studentId]);
        } catch (Throwable) {
            return ['due_months_error' => 'The month-by-month detail could not be read for this student.'];
        }

        $items = $result['data']['pending_items'] ?? [];
        $cycles = [];

        foreach ($items as $item) {
            $row = (array) $item;
            $remain = (float) str_replace([',', ' '], '', (string) ($row['remain'] ?? 0));
            $monthId = (string) ($row['month_id'] ?? '');

            if ($remain <= 0 || ! preg_match('/^(\d{1,2})(\d{4})$/', $monthId, $m)) {
                continue;
            }

            $cycles[] = [
                'sort' => ((int) $m[2]) * 100 + (int) $m[1],
                'label' => (string) ($row['month'] ?? $monthId),
                'remain' => $remain,
                'overdue' => sprintf('%04d%02d', (int) $m[2], (int) $m[1]) < date('Ym'),
            ];
        }

        if ($cycles === []) {
            return ['months_pending' => 0, 'oldest_due_month' => null, 'overdue_months' => 0];
        }

        usort($cycles, static fn (array $a, array $b) => $a['sort'] <=> $b['sort']);

        return [
            'months_pending' => count($cycles),
            'oldest_due_month' => $cycles[0]['label'],
            'oldest_due_amount' => round($cycles[0]['remain'], 2),
            'overdue_months' => count(array_filter($cycles, static fn (array $c) => $c['overdue'])),
        ];
    }

    /** @param array<string, mixed> $position */
    private function positionSentence(array $position, McpRequestContext $context): string
    {
        if ($position['feeAccounts'] <= 0) {
            return 'No student has a fee set up for this academic year, so there is no pending amount to report.';
        }

        $rate = $position['collectionRate'] === null ? '' : sprintf(', with %s%% of the fees collected', $position['collectionRate']);

        return sprintf(
            '%s is pending across %d of %d students%s.',
            $this->rupees($position['outstandingAmount']),
            $position['defaulterAccounts'],
            $position['feeAccounts'],
            $rate
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, int>|null  $requested
     */
    private function accountsSentence(array $data, ?array $requested): string
    {
        $matching = $data['accounts_matching'];

        if ($matching === 0) {
            return $requested !== null
                ? 'None of those students has an outstanding balance.'
                : 'No student matching that has an outstanding balance.';
        }

        $scope = $data['scope']['label'] ?? null;
        $where = $scope !== null ? ' in ' . $scope : '';

        if ($requested !== null) {
            return sprintf(
                '%d of the %d students asked about %s an outstanding balance, %s in total.',
                $matching,
                count($requested),
                $matching === 1 ? 'has' : 'have',
                $this->rupees($data['shown_outstanding'])
            );
        }

        return sprintf(
            '%d student%s%s %s an outstanding balance. Showing the %d who owe the most.',
            $matching,
            $matching === 1 ? '' : 's',
            $where,
            $matching === 1 ? 'has' : 'have',
            $data['accounts_shown']
        );
    }

    /** ₹ with Indian digit grouping (₹2,92,299). */
    private function rupees(float|int $amount): string
    {
        $whole = (string) (int) round($amount);

        if (strlen($whole) > 3) {
            $tail = substr($whole, -3);
            $head = substr($whole, 0, -3);
            $whole = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $head) . ',' . $tail;
        }

        return '₹' . $whole;
    }
}
