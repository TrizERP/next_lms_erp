<?php

namespace App\Domain\NewPal\Risk;

use App\Domain\AI\Evidence\EvidenceItem;
use App\Domain\AI\Signals\DetectedSignal;
use App\Domain\AI\Signals\ThresholdRegistry;
use App\Domain\K12\AcademicRisk\StudentScope;
use App\Services\Mcp\McpRequestContext;
use App\Services\PAL\Gamification\LearnerActivitySource;
use Throwable;

/**
 * Learners whose attempted concepts remain stuck at the lowest mastery tier ("Stream"),
 * as a signal the lifecycle can build on.
 *
 * WHY IT DELEGATES RATHER THAN QUERIES
 *
 * There is exactly one correct answer to "what tier is this concept at for this
 * learner", and it is the one `GamificationService::overview()` already gives every
 * New PAL screen: `LearnerActivitySource::conceptRecords()`, the same building block
 * `NewPalReportService::gamificationSummary()` reads through. This detector recomputes
 * no mastery tier of its own — it reads the recorded tier and turns a pattern across
 * them into a signal, the same rule `LowAttendanceDetector` and `FeeArrearsDetector`
 * follow for the same reason: a second, private opinion about a learner's mastery would
 * let this agent disagree with the learner's own Coherence Map.
 *
 * NEVER THE OLDER `pal` MODULE'S TABLES
 *
 * Every read here goes through New PAL's own gamification services
 * (`app/Services/PAL/Gamification/*`), exactly as `NewPalReportService` and
 * `lib/new-pal/new-pal-ai-stack.ts` document. Nothing here touches the legacy `pal`
 * module.
 *
 * WHAT "STUCK AT STREAM" MEANS HERE
 *
 * A concept only counts once the learner has actually practised it — `sessions >= 1` —
 * so a concept the learner has never opened is absent from the judgement rather than
 * silently counted as "stuck". A learner needs at least three attempted concepts before
 * a share is judged at all (mirrors `AssessmentDeclineDetector::MIN_ANSWERED_QUESTIONS`
 * — one or two attempts carry too little information to call a pattern). The share of
 * those attempted concepts still sitting at Stream is the score itself: it is already a
 * 0..1 proportion, so it is handed to `ThresholdRegistry::classify()` unscaled, the same
 * way `FeeArrearsDetector` hands its own already-bounded ratio through.
 *
 * WHAT "NOTHING FOUND" MEANS HERE
 *
 * A learner nobody has enrolled in a class, or one who has never opened a concept, is
 * not a learner who is doing well — they are a learner this detector cannot judge.
 * `coverage()` reports how many of the cohort fell into that bucket so the agent can say
 * "among the 40 we could judge" instead of implying the whole class.
 */
final class PalInterventionDetector
{
    public const KEY = 'pal_low_mastery_progress';

    /** Share of attempted concepts stuck at Stream at or above which a learner is raised. */
    private const DEFAULT_MIN_STREAM_SHARE = 0.5;

    /** A learner needs at least this many practised concepts before a share is judged. */
    private const MIN_ATTEMPTED_CONCEPTS = 3;

    /** How many learners get their stuck concepts itemised as evidence. */
    private const MAX_ITEMISED = 25;

    private const DEFAULT_COHORT_LIMIT = 60;

    private const MAX_COHORT_LIMIT = 150;

    /** One evaluation per (scope, filters) within a request. See evaluate(). */
    private array $memo = [];

    public function __construct(
        private readonly StudentScope $scope,
        private readonly LearnerActivitySource $activity,
        private readonly ThresholdRegistry $thresholds,
    ) {
    }

    /**
     * @param  array<string, mixed>  $arguments  student_id, limit, min_stream_share.
     * @return array<int, DetectedSignal>
     */
    public function detect(McpRequestContext $scope, array $arguments = []): array
    {
        return $this->evaluate($scope, $arguments)['signals'];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{cohort:int, judged:int, insufficient:int, flagged:int, complete:bool, limit:int}
     */
    public function coverage(McpRequestContext $scope, array $arguments = []): array
    {
        return $this->evaluate($scope, $arguments)['coverage'];
    }

    /** The bar this sweep applied, so a caller can state it rather than imply it. */
    public function minStreamShare(array $arguments = []): float
    {
        $given = $arguments['min_stream_share'] ?? null;

        if (! is_numeric($given)) {
            return self::DEFAULT_MIN_STREAM_SHARE;
        }

        $value = (float) $given;

        // Accepts 50 as readily as 0.5 — mirrors LowAttendanceDetector::minimumRate().
        if ($value > 1.0) {
            $value /= 100;
        }

        return max(0.0, min(1.0, $value));
    }

    // ---------------------------------------------------------------- internals

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{signals: array<int, DetectedSignal>, coverage: array<string, mixed>}
     */
    private function evaluate(McpRequestContext $context, array $arguments): array
    {
        $limit = max(1, min(self::MAX_COHORT_LIMIT, (int) ($arguments['limit'] ?? self::DEFAULT_COHORT_LIMIT)));
        $studentId = isset($arguments['student_id']) ? (int) $arguments['student_id'] : null;
        $minShare = $this->minStreamShare($arguments);

        $memoKey = $context->selectedInstituteId . '|' . ($studentId ?? '-') . '|' . $limit . '|' . $minShare;

        if (array_key_exists($memoKey, $this->memo)) {
            return $this->memo[$memoKey];
        }

        $cohort = $this->scope->students($context, $studentId !== null ? [$studentId] : null, $limit);

        if ($cohort === []) {
            return $this->memo[$memoKey] = [
                'signals' => [],
                'coverage' => ['cohort' => 0, 'judged' => 0, 'insufficient' => 0, 'flagged' => 0, 'complete' => false, 'limit' => $limit],
            ];
        }

        $signals = [];
        $judged = 0;
        $itemised = 0;

        foreach ($cohort as $id => $fallbackName) {
            $outcome = $this->evaluateLearner($context, (int) $id, $fallbackName, $minShare, $itemised < self::MAX_ITEMISED);

            if ($outcome === null) {
                continue;
            }

            $judged++;

            if ($outcome instanceof DetectedSignal) {
                $signals[] = $outcome;
                $itemised++;
            }
        }

        $result = [
            'signals' => $signals,
            'coverage' => [
                'cohort' => count($cohort),
                'judged' => $judged,
                'insufficient' => count($cohort) - $judged,
                'flagged' => count($signals),
                'complete' => $judged > 0 && $judged === count($cohort),
                'limit' => $limit,
            ],
        ];

        return $this->memo[$memoKey] = $result;
    }

    /**
     * @return DetectedSignal|true|null  A signal when the learner is flagged, `true` when
     *                                   judged but not flagged, or null when there was not
     *                                   enough data to judge this learner at all.
     */
    private function evaluateLearner(
        McpRequestContext $context,
        int $studentId,
        string $fallbackName,
        float $minShare,
        bool $itemise
    ): DetectedSignal|bool|null {
        try {
            $learner = $this->activity->learner($studentId);
            $concepts = $learner === null ? [] : $this->activity->conceptRecords($studentId);
        } catch (Throwable) {
            // A failed read is not a finding of strong mastery. It is simply not judged.
            return null;
        }

        if ($learner === null) {
            return null;
        }

        $attempted = array_values(array_filter(
            $concepts,
            static fn ($c) => is_array($c) && (int) ($c['sessions'] ?? 0) >= 1
        ));

        if (count($attempted) < self::MIN_ATTEMPTED_CONCEPTS) {
            return null;
        }

        $stuck = array_values(array_filter(
            $attempted,
            static fn ($c) => ($c['tier'] ?? null) === 'stream'
        ));

        $share = count($stuck) / count($attempted);

        if ($share < $minShare) {
            return true;
        }

        $name = trim((string) ($learner['name'] ?? '')) ?: $fallbackName;

        $evidence = [
            EvidenceItem::fromComputation(
                kind: 'pal_mastery_composition',
                subjectEntityKey: 'student',
                subjectId: $studentId,
                summary: sprintf(
                    '%s has %d of %d practised concepts still at the Stream tier, the tier every concept starts at.',
                    $name,
                    count($stuck),
                    count($attempted)
                ),
                sourceService: LearnerActivitySource::class . '::conceptRecords',
                value: [
                    'stream_count' => count($stuck),
                    'attempted_count' => count($attempted),
                    'concepts_tracked' => count($concepts),
                ],
                numericValue: round($share * 100, 2),
                unit: 'percent',
                confidence: 1.0,
                observedAt: now()->toDateTimeString(),
            ),
        ];

        if ($itemise) {
            foreach (array_slice($stuck, 0, 10) as $concept) {
                $sessions = (int) ($concept['sessions'] ?? 0);

                $evidence[] = EvidenceItem::fromRecord(
                    kind: 'pal_concept_stuck',
                    subjectEntityKey: 'student',
                    subjectId: $studentId,
                    summary: sprintf(
                        '%s has practised "%s" (%s) %d time%s and remains at the Stream tier.',
                        $name,
                        $concept['concept_label'] ?? ('concept ' . ($concept['concept_ref'] ?? '?')),
                        $concept['subject_name'] ?? 'an unlabelled subject',
                        $sessions,
                        $sessions === 1 ? '' : 's'
                    ),
                    sourceTable: 'pal_concept_mastery',
                    value: [
                        'concept_ref' => $concept['concept_ref'] ?? null,
                        'subject_name' => $concept['subject_name'] ?? null,
                        'mastery' => $concept['mastery'] ?? null,
                        'sessions' => $sessions,
                        'last_seen_at' => $concept['last_seen_at'] ?? null,
                    ],
                    observedAt: is_string($concept['last_seen_at'] ?? null) ? $concept['last_seen_at'] : null,
                );
            }
        }

        $score = round($share, 4);

        return new DetectedSignal(
            signalKey: self::KEY,
            subjectEntityKey: 'student',
            subjectId: $studentId,
            score: $score,
            severity: $this->thresholds->classify($score, $context->selectedInstituteId, self::KEY),
            evidence: $evidence,
            components: [
                'stream_share' => $score,
                'stream_count' => count($stuck),
                'attempted_concepts' => count($attempted),
                'concepts_tracked' => count($concepts),
                'min_stream_share' => $minShare,
                'standard_name' => $learner['standard_name'] ?? null,
                'division_name' => $learner['division_name'] ?? null,
            ],
            // More attempted concepts behind the share, more confidence in the finding.
            confidence: round(min(1.0, count($attempted) / 6), 2),
            subjectLabel: $name,
            domain: 'k12',
            context: ['academic_year' => $context->academicYear],
            detectedAt: now()->toDateTimeString(),
        );
    }
}
