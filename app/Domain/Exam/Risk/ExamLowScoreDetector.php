<?php

namespace App\Domain\Exam\Risk;

use App\Domain\AI\Evidence\EvidenceItem;
use App\Domain\AI\Signals\DetectedSignal;
use App\Domain\AI\Signals\ThresholdRegistry;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\ResultReportService;
use Throwable;

/**
 * Students who scored below the passing mark on a recorded exam result, as a signal the
 * lifecycle can build on.
 *
 * WHY IT DELEGATES RATHER THAN QUERIES
 *
 * There is exactly one correct answer to "what did this child score", and it is the one
 * the Exam AI Stack's report tab and the `exams.results` tool already give:
 * `ResultReportService::report()`, which reads `result_marks` and reports an absence
 * separately rather than averaging it in as a zero. This detector computes nothing about
 * a mark. It asks that service and turns what comes back into signals — the same rule
 * `LowAttendanceDetector` and `FeeArrearsDetector` follow, for the same reason: a
 * detector with its own SQL would be a second opinion about a child's result, and a
 * parent would be told a percentage no result screen could reproduce.
 *
 * WHAT "BELOW PASSING" MEANS HERE
 *
 * `result_marks.per` is the percentage `ResultReportService` already computes and the
 * result screens already show. A row is below passing when its percentage sits under
 * the configured bar — 35% by default, overridable per call through
 * `passing_percentage` (or `min_percentage`), exactly as `LowAttendanceDetector` lets
 * `min_attendance_rate` be overridden. This estate stores no per-exam pass mark of its
 * own, so the same kind of caller-supplied, overridable bar attendance already uses is
 * applied here rather than a fixed number hidden from the school.
 *
 * An absence is never scored. `report()` already excludes it from every average and
 * reports it separately; this detector inherits that and never raises a signal from an
 * absence.
 */
final class ExamLowScoreDetector
{
    public const KEY = 'exam_low_score';

    /** The percentage at or below which a result is worth raising. */
    private const DEFAULT_PASSING_PERCENTAGE = 35.0;

    /** How many students get up to ten of their below-passing rows cited as evidence. */
    private const MAX_ITEMISED = 25;

    /** One overview read per (scope, filters) within a request. See sweep(). */
    private array $memo = [];

    public function __construct(
        private readonly ResultReportService $results,
        private readonly ThresholdRegistry $thresholds,
    ) {
    }

    /**
     * @param  array<string, mixed>  $arguments  Passed through to ResultReportService —
     *                                           student_id, exam_id, subject_name,
     *                                           standard_name, exam_title, limit — plus
     *                                           passing_percentage.
     * @return array<int, DetectedSignal>
     */
    public function detect(McpRequestContext $scope, array $arguments = []): array
    {
        $report = $this->sweep($scope, $arguments);
        $rows = is_array($report['results'] ?? null) ? $report['results'] : [];

        if ($rows === []) {
            return [];
        }

        $floor = $this->passingPercentage($arguments);
        $byStudent = [];

        foreach ($rows as $row) {
            if (! is_array($row) || ($row['absent'] ?? false) === true) {
                continue;
            }

            $percentage = $row['percentage'] ?? null;

            // Null means the row carries no score to judge (absent already excluded
            // above; this also guards a row `report()` could not compute a percentage
            // for). Skipping is the point: an unscored result is not a low-scoring one.
            if (! is_numeric($percentage) || (float) $percentage >= $floor) {
                continue;
            }

            $studentId = (int) ($row['student_id'] ?? 0);

            if ($studentId <= 0) {
                continue;
            }

            $byStudent[$studentId][] = $row;
        }

        $signals = [];
        $itemised = 0;

        foreach ($byStudent as $studentId => $studentRows) {
            $signal = $this->signalFor($scope, (int) $studentId, $studentRows, $floor, $itemised < self::MAX_ITEMISED);

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
     * @param  array<string, mixed>  $arguments
     * @return array{rows_read:int, scored_rows:int, absent_rows:int, students_with_results:int, limit:int, capped:bool}
     */
    public function coverage(McpRequestContext $scope, array $arguments = []): array
    {
        $report = $this->sweep($scope, $arguments);
        $rows = is_array($report['results'] ?? null) ? $report['results'] : [];
        $limit = $this->limit($arguments);

        $students = [];
        $scored = 0;
        $absent = 0;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            if (($row['absent'] ?? false) === true) {
                $absent++;

                continue;
            }

            if (is_numeric($row['percentage'] ?? null)) {
                $scored++;
            }

            if (! empty($row['student_id'])) {
                $students[(int) $row['student_id']] = true;
            }
        }

        return [
            'rows_read' => count($rows),
            'scored_rows' => $scored,
            'absent_rows' => $absent,
            'students_with_results' => count($students),
            'limit' => $limit,
            // `report()` returns at most `limit` rows ordered newest first, so hitting the
            // cap means older results were not read — the sweep is not the whole record.
            'capped' => count($rows) >= $limit,
        ];
    }

    /** The bar this sweep applied, so a caller can state it rather than imply it. */
    public function passingPercentage(array $arguments = []): float
    {
        $given = $arguments['passing_percentage'] ?? $arguments['min_percentage'] ?? null;

        if (! is_numeric($given)) {
            return self::DEFAULT_PASSING_PERCENTAGE;
        }

        $value = (float) $given;

        // Accepts 0.35 as readily as 35 — mirrors LowAttendanceDetector::minimumRate().
        if ($value > 0 && $value <= 1.0) {
            $value *= 100;
        }

        return max(0.0, min(100.0, $value));
    }

    // ---------------------------------------------------------------- internals

    private function limit(array $arguments): int
    {
        $limit = (int) ($arguments['limit'] ?? 100);

        return max(1, min(300, $limit));
    }

    /**
     * One report read, shared by `detect()` and `coverage()` within a request.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function sweep(McpRequestContext $scope, array $arguments): array
    {
        $filters = array_filter([
            'student_id' => $arguments['student_id'] ?? null,
            'exam_id' => $arguments['exam_id'] ?? null,
            'subject_name' => $arguments['subject_name'] ?? null,
            'standard_name' => $arguments['standard_name'] ?? null,
            'exam_title' => $arguments['exam_title'] ?? null,
            'limit' => $this->limit($arguments),
        ], static fn ($value) => $value !== null && $value !== '');

        $memoKey = $scope->selectedInstituteId . '|' . json_encode($filters);

        if (array_key_exists($memoKey, $this->memo)) {
            return $this->memo[$memoKey];
        }

        try {
            $result = $this->results->report($scope, $filters);
        } catch (Throwable) {
            // A failed sweep is not a finding of good results. An empty result lets the
            // agent report that it could not judge, rather than implying every result was
            // fine — the two look identical in the numbers and mean opposites.
            $result = [];
        }

        return $this->memo[$memoKey] = is_array($result) ? $result : [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows  This student's below-passing rows.
     */
    private function signalFor(
        McpRequestContext $scope,
        int $studentId,
        array $rows,
        float $floor,
        bool $itemise
    ): ?DetectedSignal {
        $name = trim((string) ($rows[0]['student_name'] ?? '')) ?: ('Student #' . $studentId);

        // The worst subject drives the score — it is the one most urgently needing
        // attention — but every below-passing subject is still cited as evidence.
        $worst = min(array_map(static fn ($row) => (float) $row['percentage'], $rows));
        $shortfall = $floor > 0 ? max(0.0, min(1.0, ($floor - $worst) / $floor)) : 0.0;

        $evidence = [];

        if ($itemise) {
            foreach (array_slice($rows, 0, 10) as $row) {
                $evidence[] = EvidenceItem::fromRecord(
                    kind: 'exam_result',
                    subjectEntityKey: 'student',
                    subjectId: $studentId,
                    summary: sprintf(
                        '%s scored %s in %s (%s), below the %s bar.',
                        $name,
                        $this->percent((float) $row['percentage']),
                        $row['subject_name'] ?? 'a subject',
                        $row['exam_title'] ?? ('exam #' . ($row['exam_id'] ?? '?')),
                        $this->percent($floor)
                    ),
                    sourceTable: 'result_marks',
                    sourceId: $row['result_id'] ?? null,
                    value: [
                        'percentage' => $row['percentage'],
                        'points' => $row['points'],
                        'grade' => $row['grade'],
                        'exam_id' => $row['exam_id'],
                        'subject_name' => $row['subject_name'],
                        'standard_name' => $row['standard_name'],
                    ],
                    numericValue: (float) $row['percentage'],
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
                'lowest_percentage' => round($worst, 2),
                'passing_percentage' => $floor,
                'subjects_below_passing' => count($rows),
                'standard_name' => $rows[0]['standard_name'] ?? null,
            ],
            // More below-passing subjects cited, more confidence in the finding.
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
