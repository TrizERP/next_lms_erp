<?php

namespace App\Domain\K12\Fees;

use App\Domain\AI\Agents\AgentContext;
use App\Domain\AI\Evidence\EvidenceItem;
use App\Domain\AI\Signals\DetectedSignal;
use App\Domain\AI\Signals\ThresholdRegistry;
use App\Services\Mcp\FeesArrearsService;
use Illuminate\Support\Facades\DB;

final class FeesArrearsDetector
{
    public function __construct(
        private readonly ThresholdRegistry $thresholds,
        private readonly FeesArrearsService $arrearsService,
    ) {
    }

    /**
     * Detect fee arrears signals for the given students.
     *
     * @param  array<int, int>|null  $studentIds  Non-null when the caller named them.
     * @return array<int, DetectedSignal>
     */
    public function detect(AgentContext $context, ?array $studentIds, int $limit): array
    {
        $instituteId = $context->scope->selectedInstituteId;
        $academicYear = $context->scope->academicYear ?? (int) date('Y');

        $signals = [];

        if ($studentIds !== null) {
            $candidates = $studentIds;
        } else {
            $candidates = $this->cohort($instituteId, $academicYear, $limit);
        }

        foreach ($candidates as $studentId) {
            if (count($signals) >= $limit) {
                break;
            }

            $signal = $this->checkStudent($context, (int) $studentId, $instituteId, $academicYear);

            if ($signal !== null) {
                $signals[] = $signal;
            }
        }

        return $signals;
    }

    /**
     * @return array<int, int>
     */
    private function cohort(int $instituteId, int $academicYear, int $limit): array
    {
        return DB::table('fees_breakoff_other')
            ->where('sub_institute_id', $instituteId)
            ->where('syear', $academicYear)
            ->distinct('student_id')
            ->limit($limit)
            ->pluck('student_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function checkStudent(
        AgentContext $context,
        int $studentId,
        int $instituteId,
        int $academicYear
    ): ?DetectedSignal {
        $result = $this->arrearsService->arrears(
            $context->scope,
            ['student_id' => $studentId, 'limit' => 1, 'min_amount' => 0.01]
        );

        if (($result['data']['defaulter_count'] ?? 0) === 0) {
            return null;
        }

        $student = $result['data']['students_with_arrears'][0] ?? [];
        $outstanding = (float) ($student['outstanding'] ?? 0);
        $items = $student['items'] ?? [];

        $demand = 0.0;
        foreach ($items as $item) {
            $amount = (float) str_replace([',', ' '], '', (string) ($item['amount'] ?? $item['demand'] ?? 0));
            $demand += $amount;
        }

        $score = $demand > 0 ? min(100.0, round(($outstanding / $demand) * 100, 1)) : ($outstanding > 0 ? 100.0 : 0.0);
        $severity = $this->thresholds->classify($score, $instituteId, 'fee_arrears');

        $evidence = $this->buildEvidence($studentId, $instituteId, $academicYear, $items, $outstanding);
        $components = $this->buildComponents($items);

        return new DetectedSignal(
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
            unit: 'currency',
            verified: true,
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
                    unit: 'currency',
                    verified: true,
                );
            }
        }

        return $evidence;
    }

    private function buildComponents(array $items): array
    {
        $components = [];
        foreach ($items as $item) {
            $head = (string) ($item['fee_title'] ?? $item['head'] ?? 'Unknown fee');
            $remain = (float) str_replace([',', ' '], '', (string) ($item['remain'] ?? $item['balance'] ?? 0));
            if ($remain > 0) {
                $components[$head] = round($remain, 2);
            }
        }
        return $components;
    }
}