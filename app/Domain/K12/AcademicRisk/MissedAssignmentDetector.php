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
 * Detects repeatedly incomplete assigned work.
 *
 * Reads the `homework` table, which already records per-student assignments with a
 * `completion_status` and a `submission_date`. An item counts as missed when its due
 * date has passed and neither field shows completion — inferred from the estate's own
 * columns rather than from a new status vocabulary.
 *
 * Subject concentration is tracked as well as volume, because "three incomplete
 * items, all Mathematics" is a different finding from "three incomplete items across
 * three subjects", and the explanation should be able to say which.
 */
class MissedAssignmentDetector implements SignalDetector
{
    public const KEY = 'academic_missed_assignments';

    private const LOOKBACK_DAYS = 45;

    private const MIN_ASSIGNED = 3;

    /** Miss rate at which risk saturates. */
    private const RATE_CEILING = 0.6;

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
            'needs at least %d assigned activities past their due date within the last %d days.',
            self::MIN_ASSIGNED,
            self::LOOKBACK_DAYS
        );

        if (! Schema::hasTable('homework')) {
            $this->coverage = new DetectorCoverage(self::KEY, 0, 0, 0, $requirement);

            return [];
        }

        // `$limit` caps how many signals come back, not how many students are read.
        $students = $this->scope->students($context, $subjectIds);

        if ($students === []) {
            $this->coverage = new DetectorCoverage(self::KEY, 0, 0, 0, $requirement);

            return [];
        }

        $query = DB::table('homework')
            ->whereIn('student_id', array_keys($students))
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->where('date', '>=', now()->subDays(self::LOOKBACK_DAYS)->toDateString())
            // Only work whose due date has passed can be described as missed.
            ->where('date', '<=', now()->toDateString());

        if ($context->academicYear !== null && Schema::hasColumn('homework', 'syear')) {
            $query->where('syear', $context->academicYear);
        }

        $assignments = $query
            ->orderBy('student_id')
            ->orderByDesc('date')
            ->get(['id', 'student_id', 'subject_id', 'title', 'date', 'submission_date', 'completion_status'])
            ->groupBy('student_id');

        $subjectNames = $this->subjectNames($assignments, $context);

        $signals = [];
        $evaluated = 0;

        foreach ($students as $studentId => $studentName) {
            $items = $assignments->get($studentId);

            if (! $items || $items->count() < self::MIN_ASSIGNED) {
                continue;
            }

            $evaluated++;

            $signal = $this->evaluate($studentId, $studentName, $items->all(), $subjectNames, $context);

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
     * @param  array<int, object>  $items
     * @param  array<int, string>  $subjectNames
     */
    private function evaluate(
        int $studentId,
        string $studentName,
        array $items,
        array $subjectNames,
        McpRequestContext $context
    ): ?DetectedSignal {
        $missed = [];
        $bySubject = [];

        foreach ($items as $item) {
            if (! $this->isMissed($item)) {
                continue;
            }

            $missed[] = $item;
            $subjectId = (int) ($item->subject_id ?? 0);
            $bySubject[$subjectId] = ($bySubject[$subjectId] ?? 0) + 1;
        }

        $missedCount = count($missed);

        if ($missedCount === 0) {
            return null;
        }

        $total = count($items);
        $rate = $missedCount / $total;
        $score = min(1.0, $rate / self::RATE_CEILING);

        // A single missed item is a bad week, not a risk signal.
        if ($missedCount < 2) {
            return null;
        }

        $severity = $this->thresholds->classify($score, $context->selectedInstituteId, self::KEY);

        arsort($bySubject);
        $dominantSubjectId = (int) array_key_first($bySubject);
        $dominantSubjectName = $subjectNames[$dominantSubjectId] ?? null;

        $evidence = [
            EvidenceItem::fromComputation(
                kind: 'assignment_completion',
                subjectEntityKey: 'student',
                subjectId: $studentId,
                summary: sprintf(
                    '%d of %d assigned items were not completed in the last %d days.',
                    $missedCount,
                    $total,
                    self::LOOKBACK_DAYS
                ),
                sourceService: self::class,
                value: [
                    'missed' => $missedCount,
                    'assigned' => $total,
                    'by_subject' => $bySubject,
                ],
                numericValue: round($rate * 100, 2),
                unit: 'percent',
                observedAt: now()->toDateTimeString(),
            ),
        ];

        foreach (array_slice($missed, 0, 8) as $item) {
            $subjectLabel = $subjectNames[(int) ($item->subject_id ?? 0)] ?? null;

            $evidence[] = EvidenceItem::fromRecord(
                kind: 'assignment_missed',
                subjectEntityKey: 'student',
                subjectId: $studentId,
                summary: sprintf(
                    'Did not complete "%s"%s, due %s.',
                    mb_substr((string) ($item->title ?? 'assigned work'), 0, 120),
                    $subjectLabel ? ' (' . $subjectLabel . ')' : '',
                    $item->date
                ),
                sourceTable: 'homework',
                sourceId: (int) $item->id,
                value: [
                    'title' => $item->title,
                    'subject_id' => $item->subject_id,
                    'completion_status' => $item->completion_status,
                ],
                observedAt: $item->date,
                sourceColumn: 'completion_status',
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
                'missed' => $missedCount,
                'assigned' => $total,
                'miss_rate' => round($rate, 4),
                'dominant_subject_id' => $dominantSubjectId ?: null,
                'dominant_subject' => $dominantSubjectName,
                'dominant_subject_missed' => $bySubject[$dominantSubjectId] ?? 0,
            ],
            confidence: round(min(1.0, $total / 10), 2),
            subjectLabel: $studentName,
            domain: 'k12',
            detectedAt: now()->toDateTimeString(),
        );
    }

    /**
     * Completion is inferred from the estate's own columns: an explicit status where
     * one is set, otherwise the presence of a submission date.
     *
     * The rule itself lives in HomeworkCompletion because the MCP homework tool needs
     * the identical answer. A second copy here would let a chat reply call an assignment
     * submitted while this detector counted it as missed, and nothing would look wrong.
     */
    private function isMissed(object $item): bool
    {
        return HomeworkCompletion::isMissed($item);
    }

    /**
     * @return array<int, string>
     */
    private function subjectNames($assignments, McpRequestContext $context): array
    {
        if (! Schema::hasTable('subject')) {
            return [];
        }

        $subjectIds = [];

        foreach ($assignments as $items) {
            foreach ($items as $item) {
                if (! empty($item->subject_id)) {
                    $subjectIds[] = (int) $item->subject_id;
                }
            }
        }

        $subjectIds = array_values(array_unique($subjectIds));

        if ($subjectIds === []) {
            return [];
        }

        return DB::table('subject')
            ->whereIn('id', $subjectIds)
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->pluck('subject_name', 'id')
            ->map(fn ($name) => (string) $name)
            ->all();
    }
}
