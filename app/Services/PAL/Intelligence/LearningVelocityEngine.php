<?php

namespace App\Services\PAL\Intelligence;

use App\Models\PAL\Competency;
use App\Models\PAL\LearningSession;

/**
 * Learning Velocity Engine
 * Measures how quickly a learner masters concepts over time
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
        $days = match($period) {
            'day' => 1,
            'week' => 7,
            'month' => 30,
            default => 7,
        };

        $currentPeriod = $this->getConceptsMastered($learnerId, $days);
        $previousPeriod = $this->getConceptsMastered($learnerId, $days * 2) - $currentPeriod;

        $retentionStability = $this->calculateRetentionStability($learnerId, $days);
        $remediationCycles = $this->countRemediationCycles($learnerId, $days);
        $bloomGrowth = $this->calculateBloomGrowth($learnerId, $days);
        $timeToProficiency = $this->calculateTimeToProficiency($learnerId);

        $velocity = $days > 0 ? $currentPeriod / $days : 0;
        $velocityChange = $previousPeriod != 0 
            ? (($currentPeriod - $previousPeriod) / $previousPeriod) * 100 
            : 0;

        return [
            'learner_id' => $learnerId,
            'period' => $period,
            'concepts_mastered' => $currentPeriod,
            'velocity' => round($velocity, 2),
            'velocity_change_percent' => round($velocityChange, 2),
            'retention_stability' => round($retentionStability, 2),
            'remediation_cycles' => $remediationCycles,
            'bloom_growth' => $bloomGrowth,
            'time_to_proficiency_hours' => $timeToProficiency,
            'classification' => $this->classifyVelocity($velocity),
        ];
    }

    /**
     * Detect learning plateau
     * @param int $learnerId
     * @return array
     */
    public function detectPlateau(int $learnerId): array
    {
        $recentVelocity = $this->getConceptsMastered($learnerId, 7);
        $olderVelocity = $this->getConceptsMastered($learnerId, 14) - $this->getConceptsMastered($learnerId, 7);

        $isPlateau = $recentVelocity > 0 && abs($recentVelocity - $olderVelocity) < 1;
        $daysInPlateau = $this->getDaysInPlateau($learnerId);

        return [
            'learner_id' => $learnerId,
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
        $currentMastery = Competency::where('learner_id', $learnerId)
            ->where('updated_at', '>=', now()->subDays(7))
            ->avg('mastery_score') ?? 0;

        $previousMastery = Competency::where('learner_id', $learnerId)
            ->whereBetween('updated_at', [now()->subDays(14), now()->subDays(7)])
            ->avg('mastery_score') ?? $currentMastery;

        $isRegressing = $currentMastery < ($previousMastery - 5);
        $conceptsDeclining = $this->getDecliningConcepts($learnerId);

        return [
            'learner_id' => $learnerId,
            'is_regressing' => $isRegressing,
            'current_mastery' => round($currentMastery, 2),
            'previous_mastery' => round($previousMastery, 2),
            'decline_percent' => $previousMastery > 0 
                ? round((($previousMastery - $currentMastery) / $previousMastery) * 100, 2) 
                : 0,
            'declining_concepts' => $conceptsDeclining,
            'trigger_spaced_review' => $isRegressing,
            'recommended_actions' => $isRegressing ? [
                'Schedule spaced review session',
                'Strengthen weak concepts',
                'Review foundational material',
            ] : [],
        ];
    }

    protected function getConceptsMastered(int $learnerId, int $days): int
    {
        return Competency::where('learner_id', $learnerId)
            ->where('mastery_score', '>=', 80)
            ->where('updated_at', '>=', now()->subDays($days))
            ->count();
    }

    protected function calculateRetentionStability(int $learnerId, int $days): float
    {
        $mastered = Competency::where('learner_id', $learnerId)
            ->where('updated_at', '>=', now()->subDays($days))
            ->where('mastery_score', '>=', 80)
            ->pluck('concept_id')
            ->toArray();

        if (empty($mastered)) return 0;

        $stillMastered = Competency::where('learner_id', $learnerId)
            ->whereIn('concept_id', $mastered)
            ->where('mastery_score', '>=', 70)
            ->count();

        return count($mastered) > 0 
            ? ($stillMastered / count($mastered)) * 100 
            : 0;
    }

    protected function countRemediationCycles(int $learnerId, int $days): int
    {
        return \App\Models\PAL\RemediationSession::where('learner_id', $learnerId)
            ->where('created_at', '>=', now()->subDays($days))
            ->count();
    }

    protected function calculateBloomGrowth(int $learnerId, int $days): int
    {
        $recentAvg = Competency::where('learner_id', $learnerId)
            ->where('updated_at', '>=', now()->subDays($days))
            ->avg('bloom_level') ?? 1;

        $olderAvg = Competency::where('learner_id', $learnerId)
            ->whereBetween('updated_at', [now()->subDays($days * 2), now()->subDays($days)])
            ->avg('bloom_level') ?? 1;

        return max(0, round($recentAvg - $olderAvg));
    }

    protected function calculateTimeToProficiency(int $learnerId): float
    {
        $sessions = LearningSession::where('learner_id', $learnerId)
            ->where('status', 'completed')
            ->orderBy('created_at')
            ->limit(10)
            ->get();

        if ($sessions->isEmpty()) return 0;

        $totalHours = $sessions->sum('duration_minutes') / 60;
        return round($totalHours, 1);
    }

    protected function classifyVelocity(float $velocity): string
    {
        return match(true) {
            $velocity >= 2.0 => 'accelerated',
            $velocity >= 1.0 => 'normal',
            $velocity >= 0.5 => 'slow',
            default => 'struggling',
        };
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

    protected function getDecliningConcepts(int $learnerId): array
    {
        return Competency::where('learner_id', $learnerId)
            ->where('mastery_score', '<', 60)
            ->where('updated_at', '>=', now()->subDays(7))
            ->pluck('concept_id')
            ->toArray();
    }
}