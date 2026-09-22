<?php

namespace App\Domain\Attendance\Risk;

use App\Domain\AI\Evidence\EvidenceItem;
use App\Domain\AI\Signals\DetectedSignal;
use App\Domain\AI\Signals\ThresholdRegistry;
use App\Services\Mcp\AttendanceInsightService;
use App\Services\Mcp\McpRequestContext;
use Throwable;

/**
 * Students whose attendance has fallen below the school's bar, as a signal the
 * lifecycle can build on.
 *
 * WHY IT DELEGATES RATHER THAN QUERIES
 *
 * There is exactly one correct answer to "how often has this child been here", and it is
 * the one the Attendance screens and the `attendance.overview` tool already give:
 * `AttendanceInsightService`, which reads `attendance_student` with the estate's own
 * coding ('P' present, 'A' absent, anything else neither). This detector therefore
 * computes nothing about attendance. It asks that service and turns what comes back into
 * signals.
 *
 * That is the same rule `FeeArrearsDetector` follows, for the same reason: a detector
 * with its own SQL would be a second opinion about a child's record, and a parent would
 * be told a percentage no class teacher could reproduce. Reusing the proven path is how
 * the AI and the register cannot disagree.
 *
 * WHAT "TOO FEW DAYS TO JUDGE" MEANS HERE
 *
 * The service already refuses to state a rate for a student with fewer than five coded
 * days, and reports how many it excluded. That distinction is carried through rather
 * than flattened: a student nobody has marked is not a student with bad attendance, and
 * a signal must never be raised for one. `coverage()` reports the excluded count so the
 * agent can say "among the 26 we could judge" instead of implying the whole school.
 *
 * SEVERITY USES THE ESTATE'S EXISTING ABSENCE CALIBRATION
 *
 * The score handed to `ThresholdRegistry::classify()` is not the raw absence rate. It is
 * that rate put through the same floor-and-ceiling scaling
 * `AcademicRisk\AttendanceRiskDetector` already applies — risk starts registering at 10%
 * absence and saturates at 40% — because the estate has already decided what those
 * numbers mean about a child, and a second opinion here would make one screen call a
 * student "moderate" while the other calls them "high".
 *
 * Handing the registry the raw rate instead would have been quietly worse than wrong:
 * its default bands put `high` at 0.5, so no case would open until a child had missed
 * more than half the year, and a student attending 70% would be detected, reported, and
 * then silently dropped by `CaseBuilder` with no evidence, no recommendation and no
 * approval to show for it.
 *
 * WHAT IS STILL CONFIGURABLE
 *
 * A school that disagrees with the bands overrides `ai_signal_definitions.thresholds`
 * for its own `sub_institute_id`, exactly as the academic-risk detectors are overridden.
 * Nothing here hard-codes a band; the two constants below are a scale, and they are the
 * scale the estate already uses.
 */
final class LowAttendanceDetector
{
    public const KEY = 'attendance_low_rate';

    /**
     * The attendance rate at or below which a student is worth raising.
     *
     * 0.75 because that is the bar the question is almost always asked at ("show me
     * students below 75%"), and it is overridable per call through `min_attendance_rate`
     * so a school with a different policy does not need a code change. It is a filter on
     * who is reported, not a severity band — severity still comes from the registry.
     */
    private const DEFAULT_MIN_RATE = 0.75;

    /** How many days of register the sweep reads when the caller names no window. */
    private const DEFAULT_WINDOW_DAYS = 30;

    /**
     * Absence rate at which risk begins to register, and at which it saturates.
     *
     * Mirrors `App\Domain\K12\AcademicRisk\AttendanceRiskDetector::RATE_FLOOR` and
     * `::RATE_CEILING`. They are repeated rather than imported because those are private
     * to a detector in a different domain; if that calibration is ever retuned, these two
     * lines are the ones that have to follow it.
     */
    private const RATE_FLOOR = 0.10;

    private const RATE_CEILING = 0.40;

    /**
     * How many students get their individual absence dates read for evidence.
     *
     * Each is a second call into the attendance service. The cap bounds a cohort sweep's
     * cost without changing who is found — the list itself is not capped here, only how
     * many get day-by-day evidence.
     */
    private const MAX_ITEMISED = 25;

    /** One overview read per (scope, filters) within a request. See sweep(). */
    private array $memo = [];

    public function __construct(
        private readonly AttendanceInsightService $attendance,
        private readonly ThresholdRegistry $thresholds,
    ) {
    }

    /**
     * @param  array<string, mixed>  $arguments  Passed through to the attendance service —
     *                                           standard_id, division_id, student_id, days,
     *                                           limit — plus `min_attendance_rate`.
     * @return array<int, DetectedSignal>
     */
    public function detect(McpRequestContext $scope, array $arguments = []): array
    {
        $overview = $this->sweep($scope, $arguments);
        $students = is_array($overview['students'] ?? null) ? $overview['students'] : [];

        if ($students === []) {
            return [];
        }

        $floor = $this->minimumRate($arguments);
        $windowDays = (int) ($overview['window_days'] ?? self::DEFAULT_WINDOW_DAYS);
        $signals = [];
        $itemised = 0;

        foreach ($students as $row) {
            if (! is_array($row)) {
                continue;
            }

            $rate = $row['attendance_rate'] ?? null;

            // Null means the service could not state a rate honestly. Skipping is the
            // whole point: an unjudgeable student is not a low-attendance student.
            if (! is_numeric($rate) || (float) $rate > $floor) {
                continue;
            }

            $signal = $this->signalFor($scope, $row, (float) $rate, $windowDays, $itemised < self::MAX_ITEMISED);

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
     * Reported alongside them because "attendance is fine" and "attendance is fine among
     * the 26 students anybody has marked" are different statements, and only the first is
     * a finding about the school.
     *
     * @param  array<string, mixed>  $arguments
     * @return array{judged:int, insufficient:int, cohort:int, complete:bool, window_days:int, since:string|null, cohort_rate:float|null}
     */
    public function coverage(McpRequestContext $scope, array $arguments = []): array
    {
        $overview = $this->sweep($scope, $arguments);

        $judged = (int) ($overview['students_judged'] ?? 0);
        $insufficient = (int) ($overview['students_with_insufficient_data'] ?? 0);

        return [
            'judged' => $judged,
            'insufficient' => $insufficient,
            'cohort' => $judged + $insufficient,
            'complete' => $insufficient === 0 && $judged > 0,
            'window_days' => (int) ($overview['window_days'] ?? self::DEFAULT_WINDOW_DAYS),
            'since' => isset($overview['since']) ? (string) $overview['since'] : null,
            'cohort_rate' => is_numeric($overview['cohort_attendance_rate'] ?? null)
                ? round((float) $overview['cohort_attendance_rate'], 4)
                : null,
        ];
    }

    /** The bar this sweep applied, so a caller can state it rather than imply it. */
    public function minimumRate(array $arguments = []): float
    {
        $given = $arguments['min_attendance_rate'] ?? null;

        if (! is_numeric($given)) {
            return self::DEFAULT_MIN_RATE;
        }

        $rate = (float) $given;

        // Accepts 75 as readily as 0.75 — the question is asked both ways, and a bar of
        // 75.0 would match every student alive.
        if ($rate > 1.0) {
            $rate /= 100;
        }

        return max(0.0, min(1.0, $rate));
    }

    // ---------------------------------------------------------------- internals

    /**
     * One overview read, shared by `detect()` and `coverage()` within a request.
     *
     * The agent asks for both, and they must describe the same sweep — reading twice
     * would let a mark entered between the two calls make the coverage disagree with the
     * findings it is supposed to be describing.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function sweep(McpRequestContext $scope, array $arguments): array
    {
        $filters = array_filter([
            'standard_id' => $arguments['standard_id'] ?? null,
            'division_id' => $arguments['division_id'] ?? ($arguments['section_id'] ?? null),
            'student_id' => $arguments['student_id'] ?? null,
            'days' => $arguments['days'] ?? self::DEFAULT_WINDOW_DAYS,
            'limit' => $arguments['limit'] ?? 200,
        ], static fn ($value) => $value !== null && $value !== '');

        $memoKey = $scope->selectedInstituteId . '|' . ($scope->academicYear ?? '-') . '|' . json_encode($filters);

        if (array_key_exists($memoKey, $this->memo)) {
            return $this->memo[$memoKey];
        }

        try {
            $result = $this->attendance->overview($scope, $filters);
        } catch (Throwable) {
            // A failed sweep is not a finding of good attendance. An empty result lets the
            // agent report that it could not judge, rather than reporting that everybody
            // is present — the two look identical in the numbers and mean opposites.
            $result = [];
        }

        return $this->memo[$memoKey] = is_array($result) ? $result : [];
    }

    /**
     * @param  array<string, mixed>  $row  One entry of the overview's `students`.
     */
    private function signalFor(
        McpRequestContext $scope,
        array $row,
        float $rate,
        int $windowDays,
        bool $itemise
    ): ?DetectedSignal {
        $studentId = (int) ($row['student_id'] ?? 0);

        if ($studentId <= 0) {
            return null;
        }

        $name = trim((string) ($row['student_name'] ?? '')) ?: ('Student #' . $studentId);
        $present = (int) ($row['present_days'] ?? 0);
        $absent = (int) ($row['absent_days'] ?? 0);
        $absenceRate = round(1 - $rate, 4);

        $evidence = [
            // The aggregate is always citable, and is the figure the class teacher's own
            // screen shows. Itemised absences are added to it, never instead of it.
            new EvidenceItem(
                kind: 'attendance_rate',
                subjectEntityKey: 'student',
                subjectId: $studentId,
                summary: sprintf(
                    '%s was present on %d of %d coded days in the last %d days (%s).',
                    $name,
                    $present,
                    $present + $absent,
                    $windowDays,
                    $this->percent($rate)
                ),
                sourceService: AttendanceInsightService::class . '::overview',
                observedAt: now()->toDateTimeString(),
                value: [
                    'present_days' => $present,
                    'absent_days' => $absent,
                    'uncoded_days' => (int) ($row['uncoded_days'] ?? 0),
                    'window_days' => $windowDays,
                    'attendance_rate' => $rate,
                ],
                numericValue: round($rate * 100, 2),
                unit: 'percent',
                confidence: 1.0,
                verified: true,
                evidenceKey: 'attendance_rate:' . $studentId,
            ),
        ];

        if ($itemise) {
            foreach ($this->absenceEvidence($scope, $studentId, $name, $windowDays) as $item) {
                $evidence[] = $item;
            }
        }

        $score = $this->riskScore($absenceRate);

        return new DetectedSignal(
            signalKey: self::KEY,
            subjectEntityKey: 'student',
            subjectId: $studentId,
            score: $score,
            severity: $this->thresholds->classify($score, $scope->selectedInstituteId, self::KEY),
            evidence: $evidence,
            components: [
                'attendance_rate' => $rate,
                'absence_rate' => $absenceRate,
                'risk_score' => $score,
                'present_days' => $present,
                'absent_days' => $absent,
                'uncoded_days' => (int) ($row['uncoded_days'] ?? 0),
                'window_days' => $windowDays,
                'standard_name' => (string) ($row['standard_name'] ?? ''),
                'division_name' => (string) ($row['division_name'] ?? ''),
            ],
            // The register is a record, not an estimate. Confidence is bounded by how much
            // of it there is: five coded days support a weaker claim than fifty.
            confidence: round(min(1.0, ($present + $absent) / 20), 2),
            subjectLabel: $name,
            domain: 'k12',
            context: ['academic_year' => $scope->academicYear],
            detectedAt: now()->toDateTimeString(),
        );
    }

    /**
     * One evidence item per recorded absence, so a claim can cite the day.
     *
     * @return array<int, EvidenceItem>
     */
    private function absenceEvidence(McpRequestContext $scope, int $studentId, string $name, int $days): array
    {
        try {
            $result = $this->attendance->forStudent($scope, ['student_id' => $studentId, 'days' => $days]);
        } catch (Throwable) {
            // One student's itemisation failing must not drop that student's signal — the
            // aggregate above still stands, and it is still evidence.
            return [];
        }

        if (($result['success'] ?? false) !== true) {
            return [];
        }

        $dates = is_array($result['data']['absence_dates'] ?? null) ? $result['data']['absence_dates'] : [];
        $evidence = [];

        // Ten is what an explanation can readably cite; the count above carries the rest.
        foreach (array_slice($dates, 0, 10) as $date) {
            $day = trim((string) $date);

            if ($day === '') {
                continue;
            }

            $evidence[] = new EvidenceItem(
                kind: 'attendance_absence',
                subjectEntityKey: 'student',
                subjectId: $studentId,
                summary: sprintf('%s was marked absent on %s.', $name, $day),
                sourceTable: 'attendance_student',
                sourceColumn: 'attendance_code',
                sourceService: AttendanceInsightService::class . '::forStudent',
                observedAt: $day,
                value: ['attendance_date' => $day, 'attendance_code' => 'A'],
                confidence: 1.0,
                verified: true,
                evidenceKey: sprintf('attendance_absence:%d:%s', $studentId, $day),
            );
        }

        return $evidence;
    }

    /**
     * An absence rate on the estate's 0..1 risk scale.
     *
     * Below the floor there is nothing to report; at and above the ceiling the risk is as
     * high as this measure can express, and a child missing 60% of school is not usefully
     * distinguished from one missing 90% — both need the same conversation today.
     */
    private function riskScore(float $absenceRate): float
    {
        if ($absenceRate <= self::RATE_FLOOR) {
            return 0.0;
        }

        return round(
            min(1.0, ($absenceRate - self::RATE_FLOOR) / (self::RATE_CEILING - self::RATE_FLOOR)),
            4
        );
    }

    private function percent(float $rate): string
    {
        return number_format($rate * 100, 1) . '%';
    }
}
