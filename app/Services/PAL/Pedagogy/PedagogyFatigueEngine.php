<?php

namespace App\Services\PAL\Pedagogy;

use App\Models\PAL\LearningSession;
use App\Models\PAL\PedagogyEffectiveness;

/**
 * Pedagogy Fatigue Engine
 * Prevents repetitive learning experiences
 */
class PedagogyFatigueEngine
{
    protected int $rotationThreshold = 3;
    protected float $decayThreshold = 20;

    /**
     * Check for pedagogy fatigue
     * @param int $learnerId
     * @return array
     */
    public function check(int $learnerId): array
    {
        $recent = $this->getRecentPedagogies($learnerId);
        $engagement = $this->calculateEngagementTrend($learnerId);

        $isFatigued = $this->detectFatigue($recent, $engagement);

        return [
            'is_fatigued' => $isFatigued,
            'recent_pedagogies' => $recent,
            'engagement_trend' => $engagement,
            'signals' => $this->getFatigueSignals($recent, $engagement),
            'recommended_rotation' => $isFatigued,
        ];
    }

    /**
     * Rotate to different pedagogy
     * @param array $selected
     * @return array
     */
    public function rotate(array $selected): array
    {
        $alternatives = [
            'concept-based' => 'practice-based',
            'practice-based' => 'visual-learning',
            'visual-learning' => 'story-based',
            'story-based' => 'inquiry-based',
            'inquiry-based' => 'concept-based',
        ];

        $currentType = $selected['type'] ?? 'concept-based';
        $newType = $alternatives[$currentType] ?? 'concept-based';

        $selected['type'] = $newType;
        $selected['rotation_applied'] = true;
        $selected['rotation_reason'] = 'fatigue_prevention';

        return $selected;
    }

    protected function getRecentPedagogies(int $learnerId): array
    {
        return PedagogyEffectiveness::where('learner_id', $learnerId)
            ->where('created_at', '>=', now()->subDays(3))
            ->pluck('pedagogy_type')
            ->toArray();
    }

    protected function calculateEngagementTrend(int $learnerId): float
    {
        $recentSessions = LearningSession::where('learner_id', $learnerId)
            ->where('created_at', '>=', now()->subDays(7))
            ->orderBy('created_at')
            ->get();

        $olderSessions = LearningSession::where('learner_id', $learnerId)
            ->whereBetween('created_at', [now()->subDays(14), now()->subDays(7)])
            ->orderBy('created_at')
            ->get();

        if ($recentSessions->isEmpty() || $olderSessions->isEmpty()) return 0;

        $recentAvg = $recentSessions->avg('engagement_score') ?? 50;
        $olderAvg = $olderSessions->avg('engagement_score') ?? 50;

        return $olderAvg > 0 ? (($recentAvg - $olderAvg) / $olderAvg) * 100 : 0;
    }

    protected function detectFatigue(array $recent, float $engagement): bool
    {
        $counts = array_count_values($recent);
        $maxUsage = !empty($counts) ? max($counts) : 0;

        // Overuse of same pedagogy
        if ($maxUsage >= $this->rotationThreshold) {
            return true;
        }

        // Engagement decay
        if ($engagement < -$this->decayThreshold) {
            return true;
        }

        return false;
    }

    protected function getFatigueSignals(array $recent, float $engagement): array
    {
        $signals = [];

        $counts = array_count_values($recent);
        if (!empty($counts) && max($counts) >= $this->rotationThreshold) {
            $signals[] = 'pedagogy_overuse';
        }

        if ($engagement < -$this->decayThreshold) {
            $signals[] = 'engagement_decay';
        }

        return $signals;
    }
}