<?php

namespace App\Services\PAL\Intelligence;

use App\Models\PAL\SessionEvent;
use App\Models\PAL\LearningSession;

/**
 * Behavioral Analytics Service
 * Processes session events and calculates engagement metrics
 */
class BehavioralAnalyticsService
{
    /**
     * Process real-time event from xAPI/H5P
     * @param array $eventData
     * @return void
     */
    public function processEvent(array $eventData): void
    {
        $event = SessionEvent::create([
            'learner_id' => $eventData['learner_id'],
            'session_id' => $eventData['session_id'] ?? null,
            'event_type' => $eventData['event_type'],
            'event_data' => $eventData['event_data'] ?? [],
            'timestamp' => $eventData['timestamp'] ?? now(),
            'duration_seconds' => $eventData['duration_seconds'] ?? 0,
            'metadata' => $eventData['metadata'] ?? [],
        ]);

        // Update session in real-time
        if ($eventData['session_id'] ?? false) {
            $this->updateSessionMetrics($eventData['session_id'], $eventData);
        }
    }

    /**
     * Detect frustration indicators
     * @param int $learnerId
     * @param int $sessionId
     * @return array
     */
    public function detectFrustration(int $learnerId, int $sessionId): array
    {
        $events = SessionEvent::where('learner_id', $learnerId)
            ->where('session_id', $sessionId)
            ->get();

        $indicators = [];
        $severity = 'none';

        $rageClicks = $events->where('event_type', 'rage_click')->count();
        if ($rageClicks > 3) {
            $indicators[] = ['type' => 'rage_clicks', 'count' => $rageClicks];
            $severity = 'high';
        }

        $rapidGuessing = $events->where('event_type', 'rapid_guessing')->count();
        if ($rapidGuessing > 5) {
            $indicators[] = ['type' => 'rapid_guessing', 'count' => $rapidGuessing];
            $severity = $severity === 'high' ? 'high' : 'medium';
        }

        $repeatedFailure = $events->where('event_type', 'repeated_failure')->count();
        if ($repeatedFailure > 2) {
            $indicators[] = ['type' => 'repeated_failure', 'count' => $repeatedFailure];
            $severity = $severity === 'high' ? 'high' : 'medium';
        }

        $hintAbuse = $events->where('event_type', 'hint_abused')->count();
        if ($hintAbuse > 2) {
            $indicators[] = ['type' => 'hint_abuse', 'count' => $hintAbuse];
        }

        $inactivity = $events->where('event_type', 'inactivity')->count();
        if ($inactivity > 3) {
            $indicators[] = ['type' => 'inactivity', 'count' => $inactivity];
        }

        return [
            'detected' => count($indicators) > 0,
            'severity' => $severity,
            'indicators' => $indicators,
            'recommended_action' => $this->getFrustrationAction($severity),
        ];
    }

    /**
     * Calculate engagement score
     * @param int $learnerId
     * @param int $sessionId
     * @return array
     */
    public function calculateEngagement(int $learnerId, int $sessionId): array
    {
        $session = LearningSession::find($sessionId);
        
        if (!$session || (int) $session->learner_id !== $learnerId) {
            return ['error' => 'Session not found'];
        }

        $events = SessionEvent::where('session_id', $sessionId)->get();

        // Calculate various engagement metrics
        $timeOnTask = $events->sum('duration_seconds') ?? 0;
        $interactionCount = $events->count();
        $uniqueInteractions = $events->pluck('event_type')->unique()->count();
        
        // Participation quality
        $activeInteractions = $events->whereIn('event_type', [
            'answer_selected', 'video_played', 'h5p_interaction', 
            'note_taken', 'discussion_post'
        ])->count();

        // Attention metrics
        $pauses = $events->where('event_type', 'video_paused')->count();
        $replays = $events->where('event_type', 'video_replayed')->count();
        $exits = $events->where('event_type', 'section_exit')->count();

        // Calculate composite score (0-100)
        $timeScore = min(30, $timeOnTask / 60); // Max 30 points from time
        $interactionScore = min(30, $interactionCount / 5); // Max 30 from interactions
        $qualityScore = min(25, $activeInteractions * 5); // Max 25 from quality
        $attentionPenalty = min(15, ($pauses + $replays) * 2); // Penalty for confusion signals

        $totalScore = max(0, $timeScore + $interactionScore + $qualityScore - $attentionPenalty);

        return [
            'session_id' => $sessionId,
            'engagement_score' => round($totalScore),
            'components' => [
                'time_on_task' => $timeOnTask,
                'interaction_count' => $interactionCount,
                'unique_interaction_types' => $uniqueInteractions,
                'active_interactions' => $activeInteractions,
                'confusion_signals' => $pauses + $replays,
            ],
            'classification' => match(true) {
                $totalScore >= 75 => 'highly_engaged',
                $totalScore >= 50 => 'engaged',
                $totalScore >= 25 => 'passively_engaged',
                default => 'disengaged',
            },
        ];
    }

    protected function updateSessionMetrics(int $sessionId, array $eventData): void
    {
        $session = LearningSession::find($sessionId);
        
        if (!$session) return;

        $updates = [];
        
        if (isset($eventData['duration_seconds'])) {
            $updates['duration_minutes'] = ($session->duration_minutes ?? 0) + 
                ($eventData['duration_seconds'] / 60);
        }

        if (in_array($eventData['event_type'] ?? '', [
            'video_played', 'answer_selected', 'h5p_interaction'
        ])) {
            $updates['interaction_count'] = ($session->interaction_count ?? 0) + 1;
        }

        if (!empty($updates)) {
            $session->update($updates);
        }
    }

    protected function getFrustrationAction(string $severity): string
    {
        return match($severity) {
            'high' => 'reduce_difficulty_and_offer_break',
            'medium' => 'simplify_content_and_encourage',
            'none' => 'continue_normal',
            default => 'monitor',
        };
    }
}