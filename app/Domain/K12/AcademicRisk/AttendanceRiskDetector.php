<?php

namespace App\Domain\K12\AcademicRisk;

use App\Domain\AI\Evidence\EvidenceItem;
use App\Domain\AI\Signals\DetectedSignal;
use App\Domain\AI\Signals\DetectorCoverage;
use App\Domain\AI\Signals\SignalDetector;
use App\Domain\AI\Signals\ThresholdRegistry;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Detects attendance patterns that put learning at risk.
 *
 * Reads `attendance_student` using the estate's own coding — `attendance_code = 'A'`
 * for absent and `'P'` for present, as used throughout dashboardController and the
 * admin API. Those letters are the existing business rule; nothing here reinterprets
 * them.
 *
 * Two things count: the overall absence rate, and consecutive absence. A student
 * missing one day a week and a student missing a fortnight straight have similar
 * rates and very different urgency.
 */
class AttendanceRiskDetector implements SignalDetector
{
    public const KEY = 'academic_attendance_risk';

    /** Days of history considered. */
    private const LOOKBACK_DAYS = 45;

    /** Below this many recorded days there is not enough to judge. */
    private const MIN_RECORDS = 5;

    /** Absence rate at which risk begins to register. */
    private const RATE_FLOOR = 0.10;

    /** Absence rate treated as fully saturated risk. */
    private const RATE_CEILING = 0.40;

    /** Consecutive absences treated as fully saturated risk. */
    private const STREAK_CEILING = 5;

    private ?DetectorCoverage $coverage = null;

    public function __construct(
        private readonly StudentScope $scope,
        private readonly ThresholdRegistry $thresholds,
    ) {
    }

    public function coverage(): ?DetectorCoverage
    {
        return $this->coverage;
    }

    public function key(): string
    {
        return self::KEY;
    }

    public function subjectEntityKey(): string
    {
        return 'student';
    }

    public function domain(): string
    {
        return 'k12';
    }

    public function detectFor(int|string $subjectId, McpRequestContext $context): ?DetectedSignal
    {
        return $this->detect($context, [(int) $subjectId], 1)[0] ?? null;
    }

    /**
     * @return array<int, DetectedSignal>
     */
    public function detect(McpRequestContext $context, ?array $subjectIds = null, int $limit = 100): array
    {
        $requirement = sprintf(
            'needs at least %d attendance records within the last %d days.',
            self::MIN_RECORDS,
            self::LOOKBACK_DAYS
        );

        if (! Schema::hasTable('attendance_student')) {
            $this->coverage = new DetectorCoverage(self::KEY, 0, 0, 0, $requirement);

            return [];
        }

        // `$limit` caps how many signals come back, not how many students are read.
        $students = $this->scope->students($context, $subjectIds);

        if ($students === []) {
            $this->coverage = new DetectorCoverage(self::KEY, 0, 0, 0, $requirement);

            return [];
        }

        $query = DB::table('attendance_student')
            ->whereIn('student_id', array_keys($students))
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->where('attendance_date', '>=', now()->subDays(self::LOOKBACK_DAYS)->toDateString());

        if ($context->academicYear !== null && Schema::hasColumn('attendance_student', 'syear')) {
            $query->where('syear', $context->academicYear);
        }

        $records = $query
            ->orderBy('student_id')
            ->orderByDesc('attendance_date')
            ->get(['id', 'student_id', 'attendance_date', 'attendance_code'])
            ->groupBy('student_id');

        $signals = [];
        $evaluated = 0;

        foreach ($students as $studentId => $studentName) {
            $studentRecords = $records->get($studentId);

            if (! $studentRecords || $studentRecords->count() < self::MIN_RECORDS) {
                continue;
            }

            $evaluated++;

            $signal = $this->evaluate($studentId, $studentName, $studentRecords->all(), $context);

            if ($signal !== null) {
                $signals[] = $signal;
            }

            if (count($signals) >= $limit) {
                break;
            }
        }

        $this->coverage = new DetectorCoverage(
            self::KEY,
            count($students),
            $evaluated,
            count($signals),
            $requirement
        );

        return $signals;
    }

    /**
     * @param  array<int, object>  $records  Newest first
     */
    private function evaluate(
        int $studentId,
        string $studentName,
        array $records,
        McpRequestContext $context
    ): ?DetectedSignal {
        $total = count($records);
        $absentDates = [];
        $streak = 0;
        $streakBroken = false;

        foreach ($records as $record) {
            $isAbsent = strtoupper((string) $record->attendance_code) === 'A';

            if ($isAbsent) {
                $absentDates[] = $record->attendance_date;

                if (! $streakBroken) {
                    $streak++;
                }

                continue;
            }

            // Records are newest first, so the first present day ends the current streak.
            if (strtoupper((string) $record->attendance_code) === 'P') {
                $streakBroken = true;
            }
        }

        $absentCount = count($absentDates);

        if ($absentCount === 0) {
            return null;
        }

        $rate = $absentCount / $total;

        $rateComponent = $rate <= self::RATE_FLOOR
            ? 0.0
            : min(1.0, ($rate - self::RATE_FLOOR) / (self::RATE_CEILING - self::RATE_FLOOR));

        $streakComponent = min(1.0, $streak / self::STREAK_CEILING);

        $score = min(1.0, ($rateComponent * 0.6) + ($streakComponent * 0.4));

        if ($score <= 0.0) {
            return null;
        }

        $severity = $this->thresholds->classify($score, $context->selectedInstituteId, self::KEY);

        $evidence = [
            EvidenceItem::fromComputation(
                kind: 'attendance_rate',
                subjectEntityKey: 'student',
                subjectId: $studentId,
                summary: sprintf(
                    'Absent on %d of %d recorded days in the last %d days (%s).',
                    $absentCount,
                    $total,
                    self::LOOKBACK_DAYS,
                    round($rate * 100, 1) . '%'
                ),
                sourceService: self::class,
                value: [
                    'absent_days' => $absentCount,
                    'recorded_days' => $total,
                    'lookback_days' => self::LOOKBACK_DAYS,
                ],
                numericValue: round($rate * 100, 2),
                unit: 'percent',
                observedAt: now()->toDateTimeString(),
            ),
        ];

        if ($streak >= 2) {
            $evidence[] = EvidenceItem::fromComputation(
                kind: 'attendance_streak',
                subjectEntityKey: 'student',
                subjectId: $studentId,
                summary: sprintf('Absent for %d consecutive recorded days, most recently %s.', $streak, $absentDates[0] ?? 'recently'),
                sourceService: self::class,
                value: ['consecutive_absences' => $streak, 'latest_absence' => $absentDates[0] ?? null],
                numericValue: $streak,
                unit: 'days',
                observedAt: now()->toDateTimeString(),
            );
        }

        // Individual absence rows, so the explanation can cite specific days.
        foreach (array_slice($records, 0, 10) as $record) {
            if (strtoupper((string) $record->attendance_code) !== 'A') {
                continue;
            }

            $evidence[] = EvidenceItem::fromRecord(
                kind: 'attendance_absence',
                subjectEntityKey: 'student',
                subjectId: $studentId,
                summary: sprintf('Marked absent on %s.', $record->attendance_date),
                sourceTable: 'attendance_student',
                sourceId: (int) $record->id,
                value: ['attendance_code' => $record->attendance_code],
                observedAt: $record->attendance_date,
                sourceColumn: 'attendance_code',
            );
        }

        return new DetectedSignal(
            signalKey: self::KEY,
            subjectEntityKey: 'student',
            subjectId: $studentId,
            score: $score,
            severity: $severity,
            evidence: $evidence,
            components: [
                'absence_rate' => round($rate, 4),
                'rate_component' => round($rateComponent, 4),
                'consecutive_absences' => $streak,
                'streak_component' => round($streakComponent, 4),
                'recorded_days' => $total,
            ],
            confidence: round(min(1.0, $total / 20), 2),
            subjectLabel: $studentName,
            domain: 'k12',
            detectedAt: now()->toDateTimeString(),
        );
    }
}
