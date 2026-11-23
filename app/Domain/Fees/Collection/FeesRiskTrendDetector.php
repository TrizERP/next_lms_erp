<?php

namespace App\Domain\Fees\Collection;

use App\Domain\AI\Evidence\EvidenceItem;
use App\Domain\AI\Signals\DetectedSignal;
use App\Domain\AI\Signals\ThresholdRegistry;
use App\Services\Mcp\FeesArrearsService;
use App\Services\Mcp\McpRequestContext;

/**
 * Students whose outstanding balance is trending upward across recorded
 * sweeps, as a forward-looking signal the lifecycle can build on.
 *
 * Candidates come from the same `FeesArrearsService::arrears()` sweep
 * `FeeArrearsDetector` already uses — nothing here invents a second cohort
 * query. For each current defaulter, FeesCollectionForecastService reads
 * that student's own `fees_outstanding_balance` signal history and reports
 * whether the trend is increasing, flat, or decreasing. No signal is
 * raised at all when there isn't enough history, or when the trend isn't
 * increasing — "no forecast" beats "a forecast nobody should act on."
 */
final class FeesRiskTrendDetector
{
    public const KEY = 'fees_projected_default_risk';

    public function __construct(
        private readonly FeesArrearsService $arrears,
        private readonly FeesCollectionForecastService $forecastService,
        private readonly ThresholdRegistry $thresholds,
    ) {
    }

    /**
     * @param  array<string, mixed>  $arguments  Same filters as FeeArrearsDetector::detect() —
     *                                           standard_id, section_id, min_amount, limit.
     * @return array<int, DetectedSignal>
     */
    public function detect(McpRequestContext $scope, array $arguments = []): array
    {
        $data = $this->sweepData($scope, $arguments);
        $defaulters = is_array($data['students_with_arrears'] ?? null) ? $data['students_with_arrears'] : [];

        $signals = [];

        foreach ($defaulters as $row) {
            if (! is_array($row)) {
                continue;
            }

            $studentId = (int) ($row['student_id'] ?? 0);

            if ($studentId <= 0) {
                continue;
            }

            $signal = $this->signalFor($scope, $studentId, (string) ($row['student_name'] ?? ''));

            if ($signal !== null) {
                $signals[] = $signal;
            }
        }

        return $signals;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{checked:int, cohort:int, complete:bool, failed:int}
     */
    public function coverage(McpRequestContext $scope, array $arguments = []): array
    {
        $data = $this->sweepData($scope, $arguments);

        $checked = (int) ($data['students_checked'] ?? 0);
        $cohort = (int) ($data['cohort_size'] ?? 0);

        return [
            'checked' => $checked,
            'cohort' => $cohort,
            'complete' => $cohort > 0 && $checked >= $cohort,
            'failed' => count(is_array($data['failed'] ?? null) ? $data['failed'] : []),
        ];
    }

    private function sweepData(McpRequestContext $scope, array $arguments): array
    {
        $result = $this->arrears->arrears($scope, $arguments);

        return ($result['success'] ?? false) === true ? ($result['data'] ?? []) : [];
    }

    private function signalFor(McpRequestContext $scope, int $studentId, string $name): ?DetectedSignal
    {
        $forecast = $this->forecastService->forecastFor($scope, $studentId);

        if (! $forecast['available'] || $forecast['trend'] !== 'increasing') {
            return null;
        }

        $label = $name !== '' ? $name : ('Student #' . $studentId);
        $score = $forecast['projected_30d_change'];

        $evidence = [EvidenceItem::fromComputation(
            kind: 'fee_balance_trend',
            subjectEntityKey: 'student',
            subjectId: $studentId,
            summary: sprintf(
                "%s's outstanding balance moved from %s to %s across %d recorded sweep(s) (%s to %s) — projected to grow by %s over the next 30 days at the current pace.",
                $label,
                $this->money($forecast['first_score']),
                $this->money($forecast['last_score']),
                $forecast['history_points'],
                $forecast['first_detected_at'],
                $forecast['last_detected_at'],
                $this->money($forecast['projected_30d_change'])
            ),
            sourceService: FeesCollectionForecastService::class,
            value: $forecast,
            numericValue: $score,
            unit: 'INR',
            confidence: $forecast['confidence'],
        )];

        return new DetectedSignal(
            signalKey: self::KEY,
            subjectEntityKey: 'student',
            subjectId: $studentId,
            score: $score,
            severity: $this->thresholds->classify($score, $scope->selectedInstituteId, self::KEY),
            evidence: $evidence,
            components: [
                'projected_30d_change' => $score,
                'history_points' => $forecast['history_points'],
                'rate_per_day' => $forecast['rate_per_day'],
                'first_score' => $forecast['first_score'],
                'last_score' => $forecast['last_score'],
            ],
            confidence: $forecast['confidence'],
            subjectLabel: $label,
            domain: 'k12',
            context: ['academic_year' => $scope->academicYear],
        );
    }

    private function money(float $amount): string
    {
        return '₹' . number_format($amount, 2);
    }
}
