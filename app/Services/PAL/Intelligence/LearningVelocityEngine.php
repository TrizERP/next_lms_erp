<?php

namespace App\Services\PAL\Intelligence;

use App\Models\PAL\Competency;
use App\Models\PAL\LearningSession;
use Carbon\Carbon;

/**
 * Learning Velocity Engine
 * Measures how quickly a learner masters concepts over time
 *
 * WINDOW ANCHORING -- read before touching any date filter here.
 *
 * `pal_competencies.updated_at` is deliberately backdated to the answer that
 * produced the row (see `pal:derive-competencies`), so the estate's evidence
 * runs 2023 -> current term rather than up to today. Every window in this
 * class used to be `now()->subDays(N)`, which matched 9 rows out of 24,003 and
 * therefore reported 0 concepts/day for essentially all 1,199 learners -- and
 * 0 falls into the `default` arm of classifyVelocity(), so the entire cohort
 * read as "struggling", including the estate's strongest learner at 90.44%
 * mastery. That is what made this screen look static.
 *
 * Windows are now anchored to each learner's OWN most recent evidence
 * (Competency::evidenceAnchor). The payload carries `as_of` and
 * `days_since_evidence` so a caller can tell "measured over the learner's last
 * active week" apart from "measured over the last calendar week", and
 * `has_data` false when there is no evidence to anchor to at all.
 */
class LearningVelocityEngine
{
    /**
     * Calculate learning velocity
     * Formula: Velocity = Concepts Mastered / Time Period
     * @param int $learnerId
     * @param string $period (day|week|month)
     * @return array
     */
    public function calculate(int $learnerId, string $period = 'week'): array
    {
        $days = $this->daysFor($period);
        $anchor = Competency::evidenceAnchor($learnerId);

        if (!$anchor) {
            return $this->unmeasured($learnerId, $period);
        }

        $currentPeriod = $this->conceptsMasteredBetween($learnerId, $anchor->copy()->subDays($days), $anchor);
        $previousPeriod = $this->conceptsMasteredBetween(
            $learnerId,
            $anchor->copy()->subDays($days * 2),
            $anchor->copy()->subDays($days)
        );

        $retentionStability = $this->calculateRetentionStability($learnerId, $anchor, $days);
        $remediationCycles = $this->countRemediationCycles($learnerId, $anchor, $days);
        $bloomGrowth = $this->calculateBloomGrowth($learnerId, $anchor, $days);
        $timeToProficiency = $this->calculateTimeToProficiency($learnerId);

        $velocity = $days > 0 ? $currentPeriod / $days : 0;
        $velocityChange = $previousPeriod != 0
            ? (($currentPeriod - $previousPeriod) / $previousPeriod) * 100
            : null;

        $cohort = $this->cohortVelocities($days);
        $percentile = $this->percentileOf($velocity, $cohort);

        return [
            'learner_id' => $learnerId,
            'period' => $period,
            'has_data' => true,
            'as_of' => $anchor->toIso8601String(),
            'days_since_evidence' => $anchor->diffInDays(now()),
            'concepts_mastered' => $currentPeriod,
            'velocity' => round($velocity, 2),
            'velocity_percentile' => $percentile === null ? null : round($percentile, 1),
            'cohort_size' => count($cohort),
            // null (not 0) when the previous window is empty: "no prior window
            // to compare against" is not "no change".
            'velocity_change_percent' => $velocityChange === null ? null : round($velocityChange, 2),
            'retention_stability' => $retentionStability === null ? null : round($retentionStability, 2),
            'remediation_cycles' => $remediationCycles,
            'bloom_growth' => $bloomGrowth,
            'time_to_proficiency_hours' => $timeToProficiency,
            'classification' => $this->classifyVelocity($velocity, $percentile),
        ];
    }

    /**
     * Detect learning plateau
     * @param int $learnerId
     * @return array
     */
    public function detectPlateau(int $learnerId): array
    {
        $anchor = Competency::evidenceAnchor($learnerId);

        if (!$anchor) {
            return [
                'learner_id' => $learnerId,
                'has_data' => false,
                'as_of' => null,
                'is_plateau' => null,
                'days_in_plateau' => null,
                'recent_velocity' => null,
                'older_velocity' => null,
                'trigger_intervention' => false,
                'recommended_actions' => [],
            ];
        }

        $recentVelocity = $this->conceptsMasteredBetween($learnerId, $anchor->copy()->subDays(7), $anchor);
        $olderVelocity = $this->conceptsMasteredBetween(
            $learnerId,
            $anchor->copy()->subDays(14),
            $anchor->copy()->subDays(7)
        );

        $isPlateau = $recentVelocity > 0 && abs($recentVelocity - $olderVelocity) < 1;
        $daysInPlateau = $this->getDaysInPlateau($learnerId);

        return [
            'learner_id' => $learnerId,
            'has_data' => true,
            'as_of' => $anchor->toIso8601String(),
            'is_plateau' => $isPlateau,
            'days_in_plateau' => $daysInPlateau,
            'recent_velocity' => $recentVelocity,
            'older_velocity' => $olderVelocity,
            'trigger_intervention' => $isPlateau && $daysInPlateau >= 5,
            'recommended_actions' => $isPlateau ? [
                'Introduce new pedagogy',
                'Change content format',
                'Add collaboration element',
                'Review with spaced repetition',
            ] : [],
        ];
    }

    /**
     * Detect mastery regression
     * @param int $learnerId
     * @return array
     */
    public function detectRegression(int $learnerId): array
    {
        $anchor = Competency::evidenceAnchor($learnerId);

        if (!$anchor) {
            return $this->unmeasuredRegression($learnerId, null);
        }

        $currentMastery = Competency::query()
            ->atFinestGrain($learnerId)
            ->whereBetween('updated_at', [$anchor->copy()->subDays(7), $anchor])
            ->avg('mastery_score');

        $previousMastery = Competency::query()
            ->atFinestGrain($learnerId)
            ->whereBetween('updated_at', [$anchor->copy()->subDays(14), $anchor->copy()->subDays(7)])
            ->avg('mastery_score');

        // Both windows empty means the learner's evidence is a single point in
        // time -- there is nothing to compare, so regression is unknown rather
        // than false.
        if ($currentMastery === null && $previousMastery === null) {
            return $this->unmeasuredRegression($learnerId, $anchor);
        }

        $isRegressing = $currentMastery !== null
            && $previousMastery !== null
            && $currentMastery < ($previousMastery - 5);

        return [
            'learner_id' => $learnerId,
            'has_data' => true,
            'as_of' => $anchor->toIso8601String(),
            'is_regressing' => $isRegressing,
            'current_mastery' => $currentMastery === null ? null : round($currentMastery, 2),
            'previous_mastery' => $previousMastery === null ? null : round($previousMastery, 2),
            'decline_percent' => ($currentMastery !== null && $previousMastery > 0)
                ? round((($previousMastery - $currentMastery) / $previousMastery) * 100, 2)
                : null,
            'declining_concepts' => $this->getDecliningConcepts($learnerId, $anchor),
            'trigger_spaced_review' => $isRegressing,
            'recommended_actions' => $isRegressing ? [
                'Schedule spaced review session',
                'Strengthen weak concepts',
                'Review foundational material',
            ] : [],
        ];
    }

    protected function daysFor(string $period): int
    {
        return match($period) {
            'day' => 1,
            'week' => 7,
            'month' => 30,
            default => 7,
        };
    }

    /** Shape returned when the learner has no competency evidence at all. */
    protected function unmeasured(int $learnerId, string $period): array
    {
        return [
            'learner_id' => $learnerId,
            'period' => $period,
            'has_data' => false,
            'as_of' => null,
            'days_since_evidence' => null,
            'concepts_mastered' => null,
            'velocity' => null,
            'velocity_percentile' => null,
            'cohort_size' => null,
            'velocity_change_percent' => null,
            'retention_stability' => null,
            'remediation_cycles' => null,
            'bloom_growth' => null,
            'time_to_proficiency_hours' => null,
            // Never 'struggling' here -- an unassessed learner is not a slow
            // one, and this screen drives intervention.
            'classification' => null,
        ];
    }

    protected function unmeasuredRegression(int $learnerId, ?Carbon $anchor): array
    {
        return [
            'learner_id' => $learnerId,
            'has_data' => false,
            'as_of' => $anchor?->toIso8601String(),
            'is_regressing' => null,
            'current_mastery' => null,
            'previous_mastery' => null,
            'decline_percent' => null,
            'declining_concepts' => [],
            'trigger_spaced_review' => false,
            'recommended_actions' => [],
        ];
    }

    protected function conceptsMasteredBetween(int $learnerId, Carbon $from, Carbon $to): int
    {
        return Competency::query()
            ->atFinestGrain($learnerId)
            ->where('mastery_score', '>=', 80)
            ->whereBetween('updated_at', [$from, $to])
            ->count();
    }

    protected function calculateRetentionStability(int $learnerId, Carbon $anchor, int $days): ?float
    {
        $mastered = Competency::query()
            ->atFinestGrain($learnerId)
            ->whereBetween('updated_at', [$anchor->copy()->subDays($days), $anchor])
            ->where('mastery_score', '>=', 80)
            ->pluck('concept_id')
            ->filter()
            ->unique()
            ->all();

        if (empty($mastered)) {
            return null;
        }

        $stillMastered = Competency::query()
            ->atFinestGrain($learnerId)
            ->whereIn('concept_id', $mastered)
            ->where('mastery_score', '>=', 70)
            ->distinct()
            ->count('concept_id');

        return ($stillMastered / count($mastered)) * 100;
    }

    protected function countRemediationCycles(int $learnerId, Carbon $anchor, int $days): int
    {
        return \App\Models\PAL\RemediationSession::where('learner_id', $learnerId)
            ->whereBetween('created_at', [$anchor->copy()->subDays($days), $anchor])
            ->count();
    }

    protected function calculateBloomGrowth(int $learnerId, Carbon $anchor, int $days): ?int
    {
        // bloom_level is nullable -- 6,043 of 24,003 rows have no Bloom tag on
        // their source questions. avg() already skips NULLs; the guard below
        // keeps an all-NULL window from being reported as growth of 0.
        $recentAvg = Competency::query()
            ->atFinestGrain($learnerId)
            ->whereBetween('updated_at', [$anchor->copy()->subDays($days), $anchor])
            ->avg('bloom_level');

        $olderAvg = Competency::query()
            ->atFinestGrain($learnerId)
            ->whereBetween('updated_at', [$anchor->copy()->subDays($days * 2), $anchor->copy()->subDays($days)])
            ->avg('bloom_level');

        if ($recentAvg === null || $olderAvg === null) {
            return null;
        }

        return (int) round($recentAvg - $olderAvg);
    }

    protected function calculateTimeToProficiency(int $learnerId): ?float
    {
        $sessions = LearningSession::where('learner_id', $learnerId)
            ->where('status', 'completed')
            ->orderBy('created_at')
            ->limit(10)
            ->get();

        if ($sessions->isEmpty()) {
            return null;
        }

        return round($sessions->sum('duration_minutes') / 60, 1);
    }

    /**
     * Classify a learner's velocity against their COHORT, not an absolute
     * concepts-per-day scale.
     *
     * The old absolute bands (>=2.0/day accelerated, >=1.0 normal, >=0.5 slow)
     * were calibrated for a density this estate does not have. Measured across
     * the 866 learners with a usable window: p50 = 0.29 and the cohort maximum
     * is 1.71 concepts/day -- so those bands labelled 71% of all learners
     * "struggling" and made "accelerated" mathematically unreachable by
     * anyone. A label nobody can earn and a label most people get by default
     * carry no information, which is a large part of why this screen read as
     * static.
     *
     * Percentile bands are self-calibrating: they keep meaning as the estate's
     * data density changes. The absolute scale is kept only as the fallback
     * for a cohort too small to rank against (a fresh tenant), where a
     * percentile would be noise.
     */
    protected function classifyVelocity(float $velocity, ?float $percentile = null): string
    {
        if ($percentile !== null) {
            return match(true) {
                $percentile >= 90 => 'accelerated',
                $percentile >= 60 => 'normal',
                $percentile >= 25 => 'slow',
                default => 'struggling',
            };
        }

        return match(true) {
            $velocity >= 2.0 => 'accelerated',
            $velocity >= 1.0 => 'normal',
            $velocity >= 0.5 => 'slow',
            default => 'struggling',
        };
    }

    /** Minimum ranked peers before a percentile means anything. */
    protected const MIN_COHORT = 30;

    /**
     * Every learner's velocity over their own anchored window, as one grouped
     * query rather than N per-learner round trips. Cached because it is
     * identical for every learner on the screen and changes only when
     * `pal:derive-competencies` runs.
     *
     * @return list<float> ascending
     */
    protected function cohortVelocities(int $days): array
    {
        return \Illuminate\Support\Facades\Cache::remember(
            "pal:velocity-cohort:{$days}",
            now()->addHour(),
            function () use ($days) {
                $rows = \Illuminate\Support\Facades\DB::select(
                    'SELECT c.learner_id, COUNT(*) AS mastered
                       FROM pal_competencies c
                       JOIN (SELECT learner_id, MAX(updated_at) mx
                               FROM pal_competencies
                              WHERE concept_id IS NOT NULL
                              GROUP BY learner_id) a
                         ON a.learner_id = c.learner_id
                      WHERE c.concept_id IS NOT NULL
                        AND c.mastery_score >= 80
                        AND c.updated_at BETWEEN DATE_SUB(a.mx, INTERVAL ? DAY) AND a.mx
                      GROUP BY c.learner_id',
                    [$days]
                );

                $velocities = array_map(
                    static fn ($row) => $days > 0 ? ((int) $row->mastered) / $days : 0.0,
                    $rows
                );
                sort($velocities);

                return $velocities;
            }
        );
    }

    /**
     * Midrank percentile -- ties share a rank rather than all collapsing to
     * the bottom of the band. This estate is tie-heavy (a quarter of learners
     * sit on exactly 0.143), so strict "fraction below" would push a whole
     * tied group under the same threshold on an arbitrary basis.
     *
     * @param list<float> $cohort ascending
     */
    protected function percentileOf(float $velocity, array $cohort): ?float
    {
        $n = count($cohort);
        if ($n < self::MIN_COHORT) {
            return null;
        }

        $below = 0;
        $equal = 0;
        foreach ($cohort as $value) {
            if ($value < $velocity) {
                $below++;
            } elseif ($value === $velocity) {
                $equal++;
            }
        }

        return (($below + ($equal / 2)) / $n) * 100;
    }

    protected function getDaysInPlateau(int $learnerId): int
    {
        $plateauStart = \App\Models\PAL\LearningEvent::where('learner_id', $learnerId)
            ->where('event_type', 'velocity_plateau')
            ->orderBy('created_at', 'desc')
            ->first();

        return $plateauStart
            ? $plateauStart->created_at->diffInDays(now())
            : 0;
    }

    protected function getDecliningConcepts(int $learnerId, Carbon $anchor): array
    {
        return Competency::query()
            ->atFinestGrain($learnerId)
            ->where('mastery_score', '<', 60)
            ->whereBetween('updated_at', [$anchor->copy()->subDays(7), $anchor])
            ->pluck('concept_id')
            ->filter()
            ->values()
            ->all();
    }
}
