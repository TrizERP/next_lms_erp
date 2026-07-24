<?php

namespace App\Services\PAL\Intelligence;

use App\Models\PAL\LearningSession;
use App\Models\PAL\SessionEvent;
use App\Models\PAL\AssessmentResult;

/**
 * Predictive Intervention Engine
 * Predicts disengagement, failure, burnout, and learning stagnation
 */
class PredictiveInterventionEngine
{
    protected float $disengagementThreshold = 0.6;
    protected float $failureThreshold = 0.7;
    protected float $burnoutThreshold = 0.75;

    /**
     * Predict disengagement risk
     * @param int $learnerId
     * @return array
     */
    public function predictDisengagement(int $learnerId): array
    {
        $signals = $this->collectDisengagementSignals($learnerId);
        $riskScore = $this->calculateRiskScore($signals, [
            'engagement_score' => 0.25,
            'session_frequency' => 0.20,
            'session_duration' => 0.20,
            'error_recovery' => 0.15,
            'frustration' => 0.20,
        ]);

        return [
            'learner_id' => $learnerId,
            'risk_score' => round($riskScore, 2),
            'risk_level' => $this->classifyRisk($riskScore),
            'signals' => $signals,
            'trigger_intervention' => $riskScore > $this->disengagementThreshold,
            'predicted_days_until_disengage' => $this->predictDaysUntilDisengage($riskScore),
            'recommended_actions' => $this->getDisengagementActions($riskScore),
        ];
    }

    /**
     * Predict failure risk
     * @param int $learnerId
     * @return array
     */
    public function predictFailure(int $learnerId): array
    {
        $signals = $this->collectFailureSignals($learnerId);
        $riskScore = $this->calculateRiskScore($signals, [
            'success_rate_trend' => 0.30,
            'mastery_gaps' => 0.25,
            'recent_performance' => 0.20,
            'peer_comparison' => 0.15,
            'cognitive_load' => 0.10,
        ]);

        return [
            'learner_id' => $learnerId,
            'risk_score' => round($riskScore, 2),
            'risk_level' => $this->classifyRisk($riskScore),
            'signals' => $signals,
            'trigger_intervention' => $riskScore > $this->failureThreshold,
            'recommended_actions' => $this->getFailureActions($riskScore),
        ];
    }

    /**
     * Predict burnout risk
     * @param int $learnerId
     * @return array
     */
    public function predictBurnout(int $learnerId): array
    {
        $signals = $this->collectBurnoutSignals($learnerId);
        $riskScore = $this->calculateRiskScore($signals, [
            'session_duration_trend' => 0.25,
            'engagement_decay' => 0.25,
            'intermediate_difficulty' => 0.15,
            'recovery_time' => 0.20,
            'intrinsic_motivation' => 0.15,
        ]);

        return [
            'learner_id' => $learnerId,
            'risk_score' => round($riskScore, 2),
            'risk_level' => $this->classifyRisk($riskScore),
            'signals' => $signals,
            'trigger_intervention' => $riskScore > $this->burnoutThreshold,
            'recommended_actions' => $this->getBurnoutActions($riskScore),
        ];
    }

    /**
     * Get overall risk score
     * @param int $learnerId
     * @return float
     */
    public function getRiskScore(int $learnerId): float
    {
        $disengagement = $this->predictDisengagement($learnerId)['risk_score'];
        $failure = $this->predictFailure($learnerId)['risk_score'];
        $burnout = $this->predictBurnout($learnerId)['risk_score'];

        return ($disengagement + $failure + $burnout) / 3;
    }

    protected function collectDisengagementSignals(int $learnerId): array
    {
        $sessions = LearningSession::where('learner_id', $learnerId)
            ->where('created_at', '>=', now()->subDays(14))
            ->orderBy('created_at')
            ->get();

        $lastWeek = $sessions->where('created_at', '>=', now()->subDays(7));
        $prevWeek = $sessions->whereBetween('created_at', [now()->subDays(14), now()->subDays(7)]);

        return [
            'engagement_score' => $this->calculateEngagementSignal($lastWeek, $prevWeek),
            'session_frequency' => $this->calculateFrequencySignal($lastWeek),
            'session_duration' => $this->calculateDurationSignal($lastWeek, $prevWeek),
            'error_recovery' => $this->calculateErrorRecoverySignal($learnerId),
            // Key must match the weight in predictDisengagement() ('frustration'),
            // otherwise the 0.20 frustration weight is silently dropped.
            'frustration' => $this->getFrustrationSignal($learnerId),
        ];
    }

    protected function collectFailureSignals(int $learnerId): array
    {
        $results = AssessmentResult::where('learner_id', $learnerId)
            ->where('created_at', '>=', now()->subDays(14))
            ->orderBy('created_at')
            ->get();

        $lastWeek = $results->where('created_at', '>=', now()->subDays(7));
        $prevWeek = $results->whereBetween('created_at', [now()->subDays(14), now()->subDays(7)]);

        return [
            'success_rate_trend' => $this->calculateSuccessRateSignal($lastWeek, $prevWeek),
            'mastery_gaps' => $this->getMasteryGapsSignal($learnerId),
            'recent_performance' => $this->calculateRecentPerformance($results),
            'peer_comparison' => $this->getPeerComparisonSignal($learnerId),
            'cognitive_load' => $this->getCognitiveLoadSignal($learnerId),
        ];
    }

    protected function collectBurnoutSignals(int $learnerId): array
    {
        $sessions = LearningSession::where('learner_id', $learnerId)
            ->where('created_at', '>=', now()->subDays(21))
            ->orderBy('created_at')
            ->get();

        $recent = $sessions->where('created_at', '>=', now()->subDays(7));
        $older = $sessions->whereBetween('created_at', [now()->subDays(14), now()->subDays(7)]);
        $oldest = $sessions->whereBetween('created_at', [now()->subDays(21), now()->subDays(14)]);

        return [
            'session_duration_trend' => $this->calculateDurationTrendSignal($recent, $older),
            'engagement_decay' => $this->calculateEngagementDecaySignal($recent, $older, $oldest),
            'intermediate_difficulty' => $this->getDifficultySignal($learnerId),
            'recovery_time' => $this->getRecoveryTimeSignal($learnerId),
            'intrinsic_motivation' => $this->getIntrinsicMotivationSignal($learnerId),
        ];
    }

    protected function calculateRiskScore(array $signals, array $weights): float
    {
        $score = 0;
        foreach ($weights as $signal => $weight) {
            $score += ($signals[$signal] ?? 0) * $weight;
        }
        return min(1, $score);
    }

    protected function calculateEngagementSignal($lastWeek, $prevWeek): float
    {
        $recent = $lastWeek->avg('engagement_score') ?? 50;
        $older = $prevWeek->avg('engagement_score') ?? 50;
        return $older > 0 ? min(1, max(0, ($older - $recent) / $older)) : 0;
    }

    protected function calculateFrequencySignal($lastWeek): float
    {
        $count = $lastWeek->count();
        return match(true) {
            $count >= 5 => 0,
            $count >= 3 => 0.3,
            $count >= 1 => 0.6,
            default => 1,
        };
    }

    protected function calculateDurationSignal($lastWeek, $prevWeek): float
    {
        if ($lastWeek->isEmpty()) return 0;
        
        $recentAvg = $lastWeek->avg('duration_minutes') ?? 0;
        $olderAvg = $prevWeek->avg('duration_minutes') ?? 0;
        
        if ($olderAvg === 0) return 0;
        
        $change = ($recentAvg - $olderAvg) / $olderAvg;
        return $change < -0.3 ? min(1, abs($change)) : 0;
    }

    protected function calculateErrorRecoverySignal(int $learnerId): float
    {
        $events = SessionEvent::where('learner_id', $learnerId)
            ->where('created_at', '>=', now()->subDays(7))
            ->get();

        $failures = $events->where('event_type', 'incorrect_answer')->count();
        $retries = $events->where('event_type', 'retry')->count();

        if ($failures === 0) return 0;
        return min(1, $retries / $failures);
    }

    protected function getFrustrationSignal(int $learnerId): float
    {
        $events = SessionEvent::where('learner_id', $learnerId)
            ->where('created_at', '>=', now()->subDays(7))
            ->get();

        $rageClicks = $events->where('event_type', 'rage_click')->count();
        $rapidGuessing = $events->where('event_type', 'rapid_guessing')->count();

        return min(1, ($rageClicks * 0.2 + $rapidGuessing * 0.1));
    }

    protected function calculateSuccessRateSignal($lastWeek, $prevWeek): float
    {
        if ($lastWeek->isEmpty()) return 0;
        
        $recent = $lastWeek->where('is_correct', true)->count() / $lastWeek->count();
        $older = $prevWeek->where('is_correct', true)->count() / ($prevWeek->count() ?: 1);
        
        return $older > 0 ? max(0, ($older - $recent) / $older) : 0;
    }

    protected function getMasteryGapsSignal(int $learnerId): float
    {
        $gaps = \App\Models\PAL\Competency::where('learner_id', $learnerId)
            ->where('mastery_score', '<', 50)
            ->count();

        return min(1, $gaps * 0.15);
    }

    protected function calculateRecentPerformance($results): float
    {
        if ($results->isEmpty()) return 0;
        
        $accuracy = $results->where('is_correct', true)->count() / $results->count();
        return $accuracy < 0.5 ? (0.5 - $accuracy) * 2 : 0;
    }

    protected function getPeerComparisonSignal(int $learnerId): float
    {
        // Placeholder - would compare to classroom/grade peers
        return 0;
    }

    protected function getCognitiveLoadSignal(int $learnerId): float
    {
        $events = SessionEvent::where('learner_id', $learnerId)
            ->where('created_at', '>=', now()->subDays(7))
            ->get();

        $hintRequests = $events->where('event_type', 'hint_opened')->count();
        $rewatches = $events->where('event_type', 'video_replayed')->count();
        $slowResponses = $events->where('response_time_ms', '>', 30000)->count();

        return min(1, ($hintRequests * 0.05 + $rewatches * 0.05 + $slowResponses * 0.1));
    }

    protected function calculateDurationTrendSignal($recent, $older): float
    {
        if ($recent->isEmpty() || $older->isEmpty()) return 0;
        
        $recentAvg = $recent->avg('duration_minutes');
        $olderAvg = $older->avg('duration_minutes');
        
        return $olderAvg > 0 ? max(0, ($olderAvg - $recentAvg) / $olderAvg) : 0;
    }

    protected function calculateEngagementDecaySignal($recent, $older, $oldest): float
    {
        $recent = $recent->avg('engagement_score') ?? 50;
        $older = $older->avg('engagement_score') ?? 50;
        $oldest = $oldest->avg('engagement_score') ?? 50;

        $decay = $oldest - $recent;
        return max(0, $decay / 100);
    }

    protected function getDifficultySignal(int $learnerId): float
    {
        // Check if learner is stuck at intermediate difficulty
        $sessions = LearningSession::where('learner_id', $learnerId)
            ->where('created_at', '>=', now()->subDays(14))
            ->get();

        $difficultyChanges = $sessions->pluck('difficulty_level')->unique()->count();
        return $difficultyChanges <= 1 ? 0.5 : 0;
    }

    protected function getRecoveryTimeSignal(int $learnerId): float
    {
        $gap = \App\Models\PAL\LearningSession::where('learner_id', $learnerId)
            ->orderBy('created_at', 'desc')
            ->first()?->created_at?->diffInDays(now()) ?? 0;

        return $gap > 3 ? min(1, ($gap - 3) * 0.2) : 0;
    }

    protected function getIntrinsicMotivationSignal(int $learnerId): float
    {
        $sessions = LearningSession::where('learner_id', $learnerId)
            ->where('created_at', '>=', now()->subDays(14))
            ->get();

        $voluntary = $sessions->where('initiated_by', 'learner')->count();
        $total = $sessions->count();

        return $total > 0 ? 1 - ($voluntary / $total) : 0;
    }

    protected function classifyRisk(float $score): string
    {
        return match(true) {
            $score >= 0.75 => 'critical',
            $score >= 0.5 => 'high',
            $score >= 0.25 => 'moderate',
            default => 'low',
        };
    }

    protected function predictDaysUntilDisengage(float $riskScore): int
    {
        return match(true) {
            $riskScore >= 0.75 => 3,
            $riskScore >= 0.5 => 7,
            $riskScore >= 0.25 => 14,
            default => 30,
        };
    }

    protected function getDisengagementActions(float $riskScore): array
    {
        $actions = [];
        
        if ($riskScore > 0.5) {
            $actions[] = 'Send motivational notification';
            $actions[] = 'Recommend engaging content';
        }
        if ($riskScore > 0.7) {
            $actions[] = 'Notify teacher for check-in';
            $actions[] = 'Reduce difficulty slightly';
        }
        
        return $actions;
    }

    protected function getFailureActions(float $riskScore): array
    {
        $actions = [];
        
        if ($riskScore > 0.5) {
            $actions[] = 'Schedule remediation session';
            $actions[] = 'Provide additional practice';
        }
        if ($riskScore > 0.7) {
            $actions[] = 'Alert teacher immediately';
            $actions[] = 'Recommend one-on-one support';
        }
        
        return $actions;
    }

    protected function getBurnoutActions(float $riskScore): array
    {
        $actions = [];
        
        if ($riskScore > 0.5) {
            $actions[] = 'Suggest break period';
            $actions[] = 'Introduce gamified content';
        }
        if ($riskScore > 0.7) {
            $actions[] = 'Mandatory break recommendation';
            $actions[] = 'Lower difficulty temporarily';
        }
        
        return $actions;
    }
}