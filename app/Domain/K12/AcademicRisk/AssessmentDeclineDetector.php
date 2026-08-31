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
 * Detects declining assessment performance from real exam attempts.
 *
 * Reads `lms_online_exam` — the table the platform already records online exam
 * attempts in — and compares a student's recent window against the one before it.
 * Each attempt that informs the judgement becomes an evidence row pointing back at
 * its own exam id, which is what lets the explanation say "across the last three
 * assessments" and mean it.
 *
 * Scores are normalised to 0..1 and classified through ThresholdRegistry, so this
 * detector agrees with PredictiveInterventionEngine's bands rather than inventing
 * its own.
 */
class AssessmentDeclineDetector implements SignalDetector
{
    public const KEY = 'academic_assessment_decline';

    /** Attempts in the recent window; the same number forms the comparison window. */
    private const WINDOW = 3;

    /** Below this proportion of marks an attempt counts as a weak result outright. */
    private const WEAK_RESULT_RATIO = 0.4;

    /**
     * How far back an attempt may sit and still count towards "recent".
     *
     * A term rather than the attendance detector's 45 days, because assessments are
     * far sparser than attendance days and a tighter window would leave most students
     * with nothing to compare. But it must be bounded: without any window at all this
     * detector was slicing a student's whole exam history into "recent" and "previous",
     * so a paper sat in 2023 became the baseline for a paper sat last week. That is not
     * a decline, and reporting it as one put a case in front of a teacher on the
     * strength of a three-year-old result.
     */
    private const LOOKBACK_DAYS = 180;

    /**
     * Attempts answering fewer questions than this are ignored.
     *
     * A one-question quiz scores 0% or 100% and nothing else. Two of them landing in
     * the recent window moved a student's average by 33 points and switched a live
     * signal off overnight, which is noise being read as a finding. Three questions is
     * the smallest number at which a ratio carries any information at all.
     */
    private const MIN_ANSWERED_QUESTIONS = 3;

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
        $signals = $this->detect($context, [(int) $subjectId], 1);

        return $signals[0] ?? null;
    }

    /**
     * @return array<int, DetectedSignal>
     */
    public function detect(McpRequestContext $context, ?array $subjectIds = null, int $limit = 100): array
    {
        $requirement = sprintf(
            'needs at least 2 assessment attempts of %d or more questions within the last %d days.',
            self::MIN_ANSWERED_QUESTIONS,
            self::LOOKBACK_DAYS
        );

        if (! Schema::hasTable('lms_online_exam')) {
            $this->coverage = new DetectorCoverage(self::KEY, 0, 0, 0, $requirement);

            return [];
        }

        // `$limit` caps how many signals come back, not how many students are read.
        // Passing it as the cohort size meant a scan that wanted 50 findings only ever
        // looked at 50 students.
        $students = $this->scope->students($context, $subjectIds);

        if ($students === []) {
            $this->coverage = new DetectorCoverage(self::KEY, 0, 0, 0, $requirement);

            return [];
        }

        // One query for the whole cohort rather than one per student: this runs over
        // a class, and per-student queries would make a 40-student sweep 40 round trips.
        // The date bound is what keeps that single query proportionate now that the
        // cohort is the whole school rather than an arbitrary fifty.
        $attempts = DB::table('lms_online_exam')
            ->whereIn('student_id', array_keys($students))
            ->where('created_at', '>=', now()->subDays(self::LOOKBACK_DAYS))
            ->orderBy('student_id')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['id', 'student_id', 'obtain_marks', 'total_right', 'total_wrong', 'created_at'])
            ->groupBy('student_id');

        $signals = [];
        $evaluated = 0;

        foreach ($students as $studentId => $studentName) {
            $studentAttempts = $attempts->get($studentId);

            if (! $studentAttempts || $studentAttempts->count() < 2) {
                // Nothing to compare. Not a finding — silence is the correct answer.
                continue;
            }

            $evaluated++;

            $signal = $this->evaluate($studentId, $studentName, $studentAttempts->all(), $context);

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
     * @param  array<int, object>  $attempts  Newest first
     */
    private function evaluate(
        int $studentId,
        string $studentName,
        array $attempts,
        McpRequestContext $context
    ): ?DetectedSignal {
        $recent = array_slice($attempts, 0, self::WINDOW);
        $previous = array_slice($attempts, self::WINDOW, self::WINDOW);

        $recentRatios = $this->ratios($recent);

        if ($recentRatios === []) {
            return null;
        }

        $recentAverage = array_sum($recentRatios) / count($recentRatios);
        $previousRatios = $this->ratios($previous);
        $previousAverage = $previousRatios === []
            ? null
            : array_sum($previousRatios) / count($previousRatios);

        // Two independent contributors: how far performance has fallen, and how weak
        // the current level is in absolute terms. A student who was always weak
        // shows no decline but is still at risk, and vice versa.
        $declineComponent = 0.0;

        if ($previousAverage !== null && $previousAverage > 0) {
            $drop = ($previousAverage - $recentAverage) / $previousAverage;
            $declineComponent = max(0.0, min(1.0, $drop));
        }

        $weaknessComponent = max(0.0, min(1.0, (self::WEAK_RESULT_RATIO - $recentAverage) / self::WEAK_RESULT_RATIO));

        $score = min(1.0, ($declineComponent * 0.6) + ($weaknessComponent * 0.4));

        if ($score <= 0.0) {
            return null;
        }

        $severity = $this->thresholds->classify($score, $context->selectedInstituteId, self::KEY);

        $evidence = [];

        foreach ($recent as $attempt) {
            $ratio = $this->ratio($attempt);

            if ($ratio === null) {
                continue;
            }

            $evidence[] = EvidenceItem::fromRecord(
                kind: 'assessment_score',
                subjectEntityKey: 'student',
                subjectId: $studentId,
                summary: sprintf(
                    'Scored %s (%d correct, %d incorrect) on an assessment attempt.',
                    $this->formatPercent($ratio),
                    (int) ($attempt->total_right ?? 0),
                    (int) ($attempt->total_wrong ?? 0)
                ),
                sourceTable: 'lms_online_exam',
                sourceId: (int) $attempt->id,
                value: [
                    'obtain_marks' => $attempt->obtain_marks,
                    'total_right' => $attempt->total_right,
                    'total_wrong' => $attempt->total_wrong,
                ],
                numericValue: round($ratio * 100, 2),
                observedAt: $attempt->created_at,
                unit: 'percent',
                sourceColumn: 'obtain_marks',
            );
        }

        if ($previousAverage !== null) {
            $evidence[] = EvidenceItem::fromComputation(
                kind: 'assessment_trend',
                subjectEntityKey: 'student',
                subjectId: $studentId,
                summary: sprintf(
                    'Assessment average moved from %s to %s across the last %d and previous %d attempts.',
                    $this->formatPercent($previousAverage),
                    $this->formatPercent($recentAverage),
                    count($recentRatios),
                    count($previousRatios)
                ),
                sourceService: self::class,
                value: [
                    'recent_average' => round($recentAverage * 100, 2),
                    'previous_average' => round($previousAverage * 100, 2),
                    'recent_attempts' => count($recentRatios),
                    'previous_attempts' => count($previousRatios),
                ],
                numericValue: round(($recentAverage - $previousAverage) * 100, 2),
                unit: 'percentage_points',
                observedAt: now()->toDateTimeString(),
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
                'decline' => round($declineComponent, 4),
                'weakness' => round($weaknessComponent, 4),
                'recent_average_percent' => round($recentAverage * 100, 2),
                'previous_average_percent' => $previousAverage === null ? null : round($previousAverage * 100, 2),
                'attempts_considered' => count($recentRatios),
            ],
            // More attempts, more confidence. A judgement on two exams is not a
            // judgement on six.
            confidence: round(min(1.0, count($recentRatios) / self::WINDOW), 2),
            subjectLabel: $studentName,
            domain: 'k12',
            context: ['threshold_source' => $this->thresholds->sourceFor('failure', $context->selectedInstituteId)],
            detectedAt: now()->toDateTimeString(),
        );
    }

    /**
     * @param  array<int, object>  $attempts
     * @return array<int, float>
     */
    private function ratios(array $attempts): array
    {
        $ratios = [];

        foreach ($attempts as $attempt) {
            $ratio = $this->ratio($attempt);

            if ($ratio !== null) {
                $ratios[] = $ratio;
            }
        }

        return $ratios;
    }

    /**
     * Proportion of the attempt answered correctly.
     *
     * `obtain_marks` alone is not comparable across papers of different totals, so
     * right/wrong counts are preferred where present.
     */
    private function ratio(object $attempt): ?float
    {
        $right = (int) ($attempt->total_right ?? 0);
        $wrong = (int) ($attempt->total_wrong ?? 0);
        $answered = $right + $wrong;

        if ($answered > 0) {
            // An attempt too short to carry a meaningful ratio is discarded rather than
            // averaged in. It is not fed to the marks fallback either: the counts are
            // present and they are simply too few, which is a different thing from a row
            // that never recorded them.
            return $answered < self::MIN_ANSWERED_QUESTIONS ? null : $right / $answered;
        }

        // No question counts recorded — fall back to marks, treating 100 as the scale.
        $marks = $attempt->obtain_marks ?? null;

        if ($marks === null || ! is_numeric($marks)) {
            return null;
        }

        return max(0.0, min(1.0, (float) $marks / 100));
    }

    private function formatPercent(float $ratio): string
    {
        return round($ratio * 100, 1) . '%';
    }
}
