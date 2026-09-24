<?php

namespace App\Domain\ExamAssessment\Risk;

use App\Domain\AI\Evidence\EvidenceItem;
use App\Domain\AI\Signals\DetectedSignal;
use App\Domain\AI\Signals\ThresholdRegistry;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\OnlineExamReportService;
use Throwable;

/**
 * Students scoring below the passing bar on a recorded online exam attempt, as a signal
 * the lifecycle can build on.
 *
 * WHY IT DELEGATES RATHER THAN QUERIES
 *
 * There is exactly one correct answer to "how did this attempt go", and it is the one
 * `OnlineExamReportService` already gives — the same figures the LMS Result Dashboard
 * shows, scoped through `question_paper` and excluding PAL-generated papers. This
 * detector computes nothing about a score. It asks that service and turns what comes
 * back into signals, the same rule `LowAttendanceDetector`, `FeeArrearsDetector` and
 * `ExamLowScoreDetector` follow for the same reason.
 *
 * 40% IS THE ESTATE'S OWN BAR, NOT A NEW ONE
 *
 * `OnlineExamReportService::DEFAULT_AT_RISK_PERCENT` mirrors
 * `LmsResultDashboardApiController::AT_RISK_PERCENT` — the "needs attention" bar that
 * screen already uses. Reusing it here means a case opened by this agent cannot disagree
 * with what a teacher's own dashboard calls at risk.
 */
final class OnlineExamLowScoreDetector
{
    public const KEY = 'exam_assessment_low_score';

    /** How many attempts get cited as evidence. */
    private const MAX_ITEMISED = 25;

    private array $memo = [];

    public function __construct(
        private readonly OnlineExamReportService $exams,
        private readonly ThresholdRegistry $thresholds,
    ) {
    }

    /**
     * @param  array<string, mixed>  $arguments  standard_id, subject_id, student_id,
     *                                           at_risk_percent, limit.
     * @return array<int, DetectedSignal>
     */
    public function detect(McpRequestContext $scope, array $arguments = []): array
    {
        $summary = $this->sweep($scope, $arguments);
        $attempts = is_array($summary['at_risk_attempts'] ?? null) ? $summary['at_risk_attempts'] : [];

        if ($attempts === []) {
            return [];
        }

        $floor = (float) ($summary['at_risk_percent'] ?? OnlineExamReportService::DEFAULT_AT_RISK_PERCENT);
        $byStudent = [];

        foreach ($attempts as $row) {
            $studentId = (int) ($row['student_id'] ?? 0);

            if ($studentId <= 0 || ! is_numeric($row['percent'] ?? null)) {
                continue;
            }

            $byStudent[$studentId][] = $row;
        }

        $signals = [];
        $itemised = 0;

        foreach ($byStudent as $studentId => $rows) {
            $signal = $this->signalFor($scope, (int) $studentId, $rows, $floor, $itemised < self::MAX_ITEMISED);

            if ($signal !== null) {
                $signals[] = $signal;
                $itemised++;
            }
        }

        return $signals;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{exams_published:int, attempts_recorded:int, students_flagged:int, at_risk_percent:float}
     */
    public function coverage(McpRequestContext $scope, array $arguments = []): array
    {
        $summary = $this->sweep($scope, $arguments);
        $attempts = is_array($summary['at_risk_attempts'] ?? null) ? $summary['at_risk_attempts'] : [];

        return [
            'exams_published' => (int) ($summary['exams_published'] ?? 0),
            'attempts_recorded' => (int) ($summary['attempts_recorded'] ?? 0),
            'students_flagged' => count(array_unique(array_column($attempts, 'student_id'))),
            'at_risk_percent' => (float) ($summary['at_risk_percent'] ?? OnlineExamReportService::DEFAULT_AT_RISK_PERCENT),
        ];
    }

    public function atRiskPercent(array $arguments = []): float
    {
        $given = $arguments['at_risk_percent'] ?? null;

        return is_numeric($given) ? max(0.0, min(100.0, (float) $given)) : OnlineExamReportService::DEFAULT_AT_RISK_PERCENT;
    }

    // ---------------------------------------------------------------- internals

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function sweep(McpRequestContext $scope, array $arguments): array
    {
        $filters = array_filter([
            'standard_id' => $arguments['standard_id'] ?? null,
            'subject_id' => $arguments['subject_id'] ?? null,
            'student_id' => $arguments['student_id'] ?? null,
            'at_risk_percent' => $arguments['at_risk_percent'] ?? null,
            'limit' => $arguments['limit'] ?? null,
        ], static fn ($value) => $value !== null && $value !== '');

        $memoKey = $scope->selectedInstituteId . '|' . ($scope->academicYear ?? '-') . '|' . json_encode($filters);

        if (array_key_exists($memoKey, $this->memo)) {
            return $this->memo[$memoKey];
        }

        try {
            $result = $this->exams->summary($scope, $filters);
        } catch (Throwable) {
            $result = [];
        }

        return $this->memo[$memoKey] = is_array($result) ? $result : [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows  This student's at-risk attempts.
     */
    private function signalFor(
        McpRequestContext $scope,
        int $studentId,
        array $rows,
        float $floor,
        bool $itemise
    ): ?DetectedSignal {
        $name = trim((string) ($rows[0]['student_name'] ?? '')) ?: ('Student #' . $studentId);
        $worst = min(array_map(static fn ($row) => (float) $row['percent'], $rows));
        $shortfall = $floor > 0 ? max(0.0, min(1.0, ($floor - $worst) / $floor)) : 0.0;

        $evidence = [];

        if ($itemise) {
            foreach (array_slice($rows, 0, 10) as $row) {
                $evidence[] = EvidenceItem::fromRecord(
                    kind: 'online_exam_attempt',
                    subjectEntityKey: 'student',
                    subjectId: $studentId,
                    summary: sprintf(
                        '%s scored %s on "%s", below the %s bar.',
                        $name,
                        $this->percent((float) $row['percent']),
                        $row['paper_name'] ?? ('exam #' . ($row['exam_id'] ?? '?')),
                        $this->percent($floor)
                    ),
                    sourceTable: 'lms_online_exam',
                    sourceId: $row['attempt_id'] ?? null,
                    value: [
                        'percent' => $row['percent'],
                        'obtain_marks' => $row['obtain_marks'],
                        'total_marks' => $row['total_marks'],
                        'exam_id' => $row['exam_id'],
                        'paper_name' => $row['paper_name'],
                        'subject_id' => $row['subject_id'],
                    ],
                    numericValue: (float) $row['percent'],
                    observedAt: is_string($row['attempted_at'] ?? null) ? $row['attempted_at'] : null,
                    unit: 'percent',
                );
            }
        }

        if ($evidence === []) {
            return null;
        }

        $score = round($shortfall, 4);

        return new DetectedSignal(
            signalKey: self::KEY,
            subjectEntityKey: 'student',
            subjectId: $studentId,
            score: $score,
            severity: $this->thresholds->classify($score, $scope->selectedInstituteId, self::KEY),
            evidence: $evidence,
            components: [
                'lowest_percent' => round($worst, 2),
                'at_risk_percent' => $floor,
                'attempts_below_bar' => count($rows),
                'standard_id' => $rows[0]['standard_id'] ?? null,
            ],
            confidence: round(min(1.0, count($rows) / 3), 2),
            subjectLabel: $name,
            domain: 'k12',
            context: ['academic_year' => $scope->academicYear],
            detectedAt: now()->toDateTimeString(),
        );
    }

    private function percent(float $value): string
    {
        return number_format($value, 1) . '%';
    }
}
