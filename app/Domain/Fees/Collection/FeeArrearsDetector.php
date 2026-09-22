<?php

namespace App\Domain\Fees\Collection;

use App\Domain\AI\Evidence\EvidenceItem;
use App\Domain\AI\Signals\DetectedSignal;
use App\Domain\AI\Signals\ThresholdRegistry;
use App\Services\Mcp\FeesArrearsService;
use App\Services\Mcp\FeesPendingService;
use App\Services\Mcp\McpRequestContext;
use Throwable;

/**
 * Students carrying an outstanding fee balance, as a signal the lifecycle can build on.
 *
 * WHY IT DELEGATES RATHER THAN QUERIES
 *
 * There is exactly one correct answer to "what does this child owe", and it is the one
 * the Fees screen itself shows: `fees_collect_controller::getBk`, reached through
 * `FeesPendingService`. This detector therefore computes nothing. It asks
 * `FeesArrearsService` — the same service `fees.arrears` answers the chatbot with — and
 * turns what comes back into signals.
 *
 * That is not laziness, it is the whole point. A detector with its own SQL would be a
 * second opinion about money: the screen would say one figure, the AI another, and a
 * parent would be chased for a number no clerk could reproduce. The first attempt at a
 * Fees agent did write its own query, against `fees_breackoff.balance` and
 * `fees_breackoff.student_id` — neither column exists, that table holds the fee
 * *structure* per grade, and the query would have raised a SQL error the first time it
 * ran. Reusing the proven path is how the AI and the ledger cannot disagree.
 *
 * SEVERITY IS DATA, NOT CODE
 *
 * The bands live in `ai_signal_definitions.thresholds` and are read per institute by
 * `ThresholdRegistry`, exactly as the academic-risk detectors read theirs. A school that
 * considers ₹5,000 serious and one that considers ₹50,000 serious are both configured,
 * not forked. The score handed to the classifier is the outstanding amount itself, so
 * the bands are written in rupees and a reader of the row can see what they mean.
 */
final class FeeArrearsDetector
{
    public const KEY = 'fees_outstanding_balance';

    /**
     * How many defaulters get their individual fee heads read for evidence.
     *
     * Every one of these is a second call into the fee engine, and a case is built from
     * the first few heads rather than all of them. The cap bounds a cohort sweep's cost
     * without changing which students are found — the arrears list itself is not capped
     * here, only how many get itemised evidence.
     */
    private const MAX_ITEMISED = 25;

    public function __construct(
        private readonly FeesArrearsService $arrears,
        private readonly FeesPendingService $pending,
        private readonly ThresholdRegistry $thresholds,
    ) {
    }

    /**
     * @param  array<string, mixed>  $arguments  Passed through to fees.arrears — standard_id,
     *                                           section_id, min_amount, limit.
     * @return array<int, DetectedSignal>
     */
    public function detect(McpRequestContext $scope, array $arguments = []): array
    {
        $result = $this->sweep($scope, $arguments);

        if (($result['success'] ?? false) !== true) {
            // A failed sweep is not a finding of no arrears. Returning nothing lets the
            // agent report that it could not judge, rather than reporting that nobody
            // owes anything — the two look identical in the numbers and mean opposites.
            return [];
        }

        $data = $result['data'] ?? [];
        $defaulters = is_array($data['students_with_arrears'] ?? null) ? $data['students_with_arrears'] : [];
        $signals = [];
        $itemised = 0;

        foreach ($defaulters as $row) {
            if (! is_array($row)) {
                continue;
            }

            $signal = $this->signalFor($scope, $row, $itemised < self::MAX_ITEMISED);

            if ($signal !== null) {
                $signals[] = $signal;
                $itemised++;
            }
        }

        return $signals;
    }

    /**
     * The breadth of the sweep that produced those signals.
     *
     * Reported alongside them because "nobody owes anything" and "nobody among the 25 we
     * read owes anything" are different statements, and only the first is a finding about
     * the school.
     *
     * @param  array<string, mixed>  $arguments
     * @return array{checked:int, cohort:int, complete:bool, failed:int}
     */
    public function coverage(McpRequestContext $scope, array $arguments = []): array
    {
        $result = $this->sweep($scope, $arguments);
        $data = ($result['success'] ?? false) === true ? ($result['data'] ?? []) : [];

        $checked = (int) ($data['students_checked'] ?? 0);
        $cohort = (int) ($data['cohort_size'] ?? 0);

        return [
            'checked' => $checked,
            'cohort' => $cohort,
            'complete' => $cohort > 0 && $checked >= $cohort,
            'failed' => count(is_array($data['failed'] ?? null) ? $data['failed'] : []),
        ];
    }

    // ---------------------------------------------------------------- internals

    /**
     * @param  array<string, mixed>  $row  One entry of `students_with_arrears`.
     */
    private function signalFor(McpRequestContext $scope, array $row, bool $itemise): ?DetectedSignal
    {
        $studentId = (int) ($row['student_id'] ?? 0);
        $outstanding = (float) ($row['outstanding'] ?? 0);

        if ($studentId <= 0 || $outstanding <= 0) {
            return null;
        }

        $name = (string) ($row['student_name'] ?? '') ?: ('Student #' . $studentId);
        $evidence = $itemise ? $this->evidenceFor($scope, $studentId, $name, $outstanding) : [];

        if ($evidence === []) {
            // Every signal must carry evidence — a case cannot cite what was never
            // collected. Where the itemised read was skipped or came back empty, the
            // aggregate the arrears engine returned is itself the citable observation.
            $evidence[] = new EvidenceItem(
                kind: 'fee_outstanding_total',
                subjectEntityKey: 'student',
                subjectId: $studentId,
                summary: sprintf('%s has %s outstanding across %d fee head(s).', $name, $this->money($outstanding), (int) ($row['pending_items'] ?? 0)),
                sourceService: 'fees_collect_controller::getBk',
                value: $row,
                numericValue: $outstanding,
                unit: 'INR',
                verified: true,
                evidenceKey: 'fee_outstanding_total:' . $studentId,
            );
        }

        return new DetectedSignal(
            signalKey: self::KEY,
            subjectEntityKey: 'student',
            subjectId: $studentId,
            score: $outstanding,
            severity: $this->thresholds->classify($outstanding, $scope->selectedInstituteId, self::KEY),
            evidence: $evidence,
            components: [
                'outstanding' => round($outstanding, 2),
                'pending_items' => (int) ($row['pending_items'] ?? 0),
                'enrollment_no' => (string) ($row['enrollment_no'] ?? ''),
                'standard_name' => (string) ($row['standard_name'] ?? ''),
            ],
            // The arrears engine is deterministic: it read the ledger, it did not
            // estimate. The figure is as certain as the records behind it.
            confidence: 1.0,
            subjectLabel: $name,
            domain: 'k12',
            context: ['academic_year' => $scope->academicYear],
        );
    }

    /**
     * One evidence item per unpaid fee head, so a claim can cite the head by name.
     *
     * @return array<int, EvidenceItem>
     */
    private function evidenceFor(McpRequestContext $scope, int $studentId, string $name, float $outstanding): array
    {
        try {
            $result = $this->pending->getPending($scope, ['student_id' => $studentId]);
        } catch (Throwable) {
            // One student's itemisation failing must not drop that student's signal —
            // the aggregate above still stands, and it is still evidence.
            return [];
        }

        $items = is_array($result['data']['pending_items'] ?? null) ? $result['data']['pending_items'] : [];
        $evidence = [];

        foreach ($items as $index => $item) {
            $itemRow = is_object($item) ? (array) $item : (array) $item;
            $remain = $this->amount($itemRow['remain'] ?? 0);

            if ($remain <= 0) {
                continue;
            }

            $head = trim((string) ($itemRow['fees_title'] ?? $itemRow['title'] ?? $itemRow['head'] ?? ''));
            $head = $head !== '' ? $head : 'Fee head ' . ($index + 1);

            $evidence[] = new EvidenceItem(
                kind: 'fee_pending_item',
                subjectEntityKey: 'student',
                subjectId: $studentId,
                summary: sprintf('%s: %s outstanding on %s.', $name, $this->money($remain), $head),
                sourceService: 'fees_collect_controller::getBk',
                value: $itemRow,
                numericValue: $remain,
                unit: 'INR',
                confidence: 1.0,
                verified: true,
                evidenceKey: sprintf('fee_pending_item:%d:%s', $studentId, $head),
            );
        }

        return $evidence;
    }

    /**
     * `remain` arrives as a string and occasionally carries thousands separators.
     *
     * `(float) "1,200"` is 1.0, which understates a debt by three orders of magnitude —
     * the same normalisation `FeesArrearsService::sumRemaining()` applies, for the same
     * reason.
     */
    private function amount(mixed $raw): float
    {
        return (float) str_replace([',', ' '], '', (string) $raw);
    }

    private function money(float $amount): string
    {
        return '₹' . number_format($amount, 2);
    }
}
