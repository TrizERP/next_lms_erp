<?php

namespace App\Domain\K12\Fees;

use App\Domain\AI\Agents\AgentContext;
use App\Domain\AI\Evidence\EvidenceItem;
use App\Domain\AI\Signals\DetectedSignal;
use App\Domain\AI\Signals\ThresholdRegistry;
use App\Services\Mcp\FeesArrearsService;

final class FeeArrearsDetector
{
    /**
     * One arrears sweep per set of arguments, and one fee read per student.
     *
     * Both are memoised because both are expensive in a way that is invisible from the
     * call site: `FeesArrearsService::arrears()` loops the cohort calling
     * `studentFeesDetailAPI` per student, and one of those measures 2-5 seconds against
     * this estate's database. Unmemoised, a single agent run paid for the cohort sweep
     * twice — `detect()` asks for the signals and `coverage()` asks for the breadth, and
     * they are two questions about one sweep — and then re-read each defaulter a third
     * time to itemise its heads. A four-student run did not finish inside ten minutes,
     * which is why the agent stage was unusable from a chat request even once it was
     * correctly registered.
     *
     * Held per instance, not statically: the detector is resolved per run, and a sweep
     * kept across runs would answer a later question with an earlier tenant's figures.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $sweeps = [];

    /** @var array<string, array<int, array<string, mixed>>> */
    private array $pendingItems = [];

    public function __construct(
        private readonly ThresholdRegistry $thresholds,
        private readonly FeesArrearsService $arrearsService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function sweep($scope, array $arguments): array
    {
        $key = md5((string) json_encode([
            $scope->selectedInstituteId,
            $scope->academicYear,
            $arguments,
        ]));

        return $this->sweeps[$key] ??= $this->arrearsService->arrears($scope, $arguments);
    }

    /**
     * Detect fee arrears signals for the given students.
     *
     * @param  McpRequestContext  $scope
     * @param  array<string, mixed>  $arguments
     * @return array<int, DetectedSignal>
     */
    public function detect(AgentContext $context, array $arguments): array
    {
        $scope = $context->scope;
        $instituteId = $scope->selectedInstituteId;
        $academicYear = $scope->academicYear ?? (int) date('Y');

        $signals = [];

        $result = $this->sweep($scope, $arguments);

        if (($result['data']['defaulter_count'] ?? 0) === 0) {
            return [];
        }

        foreach ($result['data']['students_with_arrears'] as $student) {
            $studentId = (int) $student['student_id'];
            $outstanding = (float) ($student['outstanding'] ?? 0);
            $items = $this->fetchPendingItems($scope, $studentId);

            $demand = 0.0;
            foreach ($items as $item) {
                $amount = (float) str_replace([',', ' '], '', (string) ($item['amount'] ?? $item['demand'] ?? 0));
                $demand += $amount;
            }

            $score = $demand > 0 ? min(100.0, round(($outstanding / $demand) * 100, 1)) : ($outstanding > 0 ? 100.0 : 0.0);
            $severity = $this->thresholds->classify($score, $instituteId, 'fee_arrears');

            $evidence = $this->buildEvidence($studentId, $instituteId, $academicYear, $items, $outstanding);
            $components = $this->buildComponents($items, $outstanding, $student);

            $signals[] = new DetectedSignal(
                signalKey: 'fee_arrears',
                subjectEntityKey: 'student',
                subjectId: $studentId,
                score: $score,
                severity: $severity,
                evidence: $evidence,
                components: $components,
                confidence: 0.95,
                subjectLabel: $student['student_name'] ?? "Student #{$studentId}",
                domain: 'k12',
                context: [
                    'academic_year' => $academicYear,
                    'sub_institute_id' => $instituteId,
                    'outstanding' => round($outstanding, 2),
                    'total_demand' => round($demand, 2),
                ],
            );
        }

        return $signals;
    }

    /**
     * Coverage metadata for the sweep.
     *
     * @param  McpRequestContext  $scope
     * @param  array<string, mixed>  $arguments
     * @return array{checked:int, cohort:int, complete:bool, failed:int}
     */
    public function coverage($scope, array $arguments): array
    {
        // The same sweep `detect()` already paid for, not a second one.
        $result = $this->sweep($scope, $arguments);

        $checked = (int) ($result['data']['students_checked'] ?? 0);
        $cohort = (int) ($result['data']['cohort_size'] ?? 0);
        $failed = (int) ($result['data']['failed'] ?? 0);

        return [
            'checked' => $checked,
            'cohort' => $cohort,
            'complete' => $checked > 0 && $cohort === $checked,
            'failed' => $failed,
        ];
    }

    /**
     * @return array<int, EvidenceItem>
     */
    private function buildEvidence(
        int $studentId,
        int $instituteId,
        int $academicYear,
        array $items,
        float $outstanding
    ): array {
        $evidence = [];

        $evidence[] = EvidenceItem::fromRecord(
            kind: 'fee_balance_summary',
            subjectEntityKey: 'student',
            subjectId: $studentId,
            summary: "Total outstanding fees: {$outstanding}",
            sourceTable: 'fees_breakoff_other',
            sourceId: $studentId,
            value: round($outstanding, 2),
            numericValue: round($outstanding, 2),
            // When the ledger was read. Left unset, the row is written with a null
            // `observed_at`, which sorts last and eventually falls outside the window
            // `EvidenceStore::forSubject()` returns — making the newest evidence the
            // hardest to cite. A balance has no timestamp of its own, so the moment of
            // reading is the honest answer.
            observedAt: now()->toDateTimeString(),
            unit: 'currency',
        );

        foreach ($items as $item) {
            $head = (string) ($item['fee_title'] ?? $item['head'] ?? 'Unknown fee');
            $amount = (float) str_replace([',', ' '], '', (string) ($item['amount'] ?? $item['demand'] ?? 0));
            $remain = (float) str_replace([',', ' '], '', (string) ($item['remain'] ?? $item['balance'] ?? 0));

            if ($remain > 0) {
                $evidence[] = EvidenceItem::fromRecord(
                    kind: 'fee_head_balance',
                    subjectEntityKey: 'student',
                    subjectId: $studentId,
                    summary: "{$head}: outstanding {$remain} of {$amount}",
                    sourceTable: 'fees_breakoff_other',
                    sourceId: $studentId,
                    value: [
                        'fee_head' => $head,
                        'demand' => round($amount, 2),
                        'outstanding' => round($remain, 2),
                    ],
                    numericValue: round($remain, 2),
                    observedAt: now()->toDateTimeString(),
                    unit: 'currency',
                );
            }
        }

        return $evidence;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildComponents(array $items, float $outstanding, array $student): array
    {
        $components = [
            'outstanding' => round($outstanding, 2),
            'pending_items' => count($items),
            'enrollment_no' => $student['enrollment_no'] ?? null,
            'standard_name' => $student['standard_name'] ?? null,
        ];

        foreach ($items as $item) {
            $head = (string) ($item['fee_title'] ?? $item['head'] ?? 'Unknown fee');
            $remain = (float) str_replace([',', ' '], '', (string) ($item['remain'] ?? $item['balance'] ?? 0));
            if ($remain > 0) {
                $components[$head] = round($remain, 2);
            }
        }

        return $components;
    }

    /**
     * Fetch the detailed pending items for a single student using the same
     * controller path the FeesPendingService uses.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchPendingItems($scope, int $studentId): array
    {
        $key = $scope->selectedInstituteId . ':' . $scope->academicYear . ':' . $studentId;

        if (array_key_exists($key, $this->pendingItems)) {
            return $this->pendingItems[$key];
        }

        try {
            $result = app(\App\Services\Mcp\FeesPendingService::class)
                ->getPending($scope, ['student_id' => $studentId]);

            return $this->pendingItems[$key] = is_array($result['data']['pending_items'] ?? null)
                ? $result['data']['pending_items']
                : [];
        } catch (\Throwable) {
            // One student's itemisation failing must not drop that student's signal: the
            // summary evidence still stands. Cached so a retry inside the same run does
            // not pay for the same failure again.
            return $this->pendingItems[$key] = [];
        }
    }
}