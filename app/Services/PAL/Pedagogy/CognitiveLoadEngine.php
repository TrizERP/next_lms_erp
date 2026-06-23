<?php

namespace App\Services\PAL\Pedagogy;

use App\Models\PAL\SessionEvent;
use App\Models\PAL\LearningSession;

/**
 * Cognitive Load Engine
 * Prevents mental overload during learning
 */
class CognitiveLoadEngine
{
    protected int $warningThreshold = 5;
    protected int $criticalThreshold = 8;

    /**
     * Assess current cognitive load
     * @param int $learnerId
     * @param int|null $sessionId
     * @return array
     */
    public function assess(int $learnerId, ?int $sessionId = null): array
    {
        if (!$sessionId) {
            $sessionId = LearningSession::where('learner_id', $learnerId)
                ->where('status', 'active')
                ->first()?->id;
        }

        if (!$sessionId) {
            return ['status' => 'unknown', 'load_level' => 0, 'signals' => []];
        }

        $signals = $this->collectSignals($learnerId, $sessionId);
        $loadLevel = $this->calculateLoadLevel($signals);

        return [
            'status' => $this->classifyStatus($loadLevel),
            'load_level' => $loadLevel,
            'signals' => $signals,
            'warning_triggered' => $loadLevel >= $this->warningThreshold,
            'critical_triggered' => $loadLevel >= $this->criticalThreshold,
            'recommended_actions' => $this->getActions($loadLevel),
        ];
    }

    /**
     * Adjust pedagogy based on cognitive load
     * @param array $selected
     * @param array $cognitiveLoad
     * @return array
     */
    public function adjustPedagogy(array $selected, array $cognitiveLoad): array
    {
        $load = $cognitiveLoad['load_level'];
        
        if ($load >= $this->criticalThreshold) {
            $selected['adjustments'] = [
                'reduce_difficulty' => true,
                'shorten_session' => true,
                'switch_to_simpler_pedagogy' => true,
            ];
            $selected['type'] = 'visual-learning';
            $selected['reason'] = 'Critical cognitive load - using simplest approach';
        } elseif ($load >= $this->warningThreshold) {
            $selected['adjustments'] = [
                'reduce_difficulty' => true,
                'shorten_session' => true,
            ];
            if ($selected['type'] === 'inquiry-based') {
                $selected['type'] = 'concept-based';
            }
            $selected['reason'] = 'High cognitive load - reducing complexity';
        }

        return $selected;
    }

    protected function collectSignals(int $learnerId, int $sessionId): array
    {
        $events = SessionEvent::where('learner_id', $learnerId)
            ->where('session_id', $sessionId)
            ->get();

        return [
            'hint_requests' => $events->where('event_type', 'hint_opened')->count(),
            'slow_responses' => $events->where('response_time_ms', '>', 30000)->count(),
            'rapid_wrong_answers' => $events->where('event_type', 'rapid_incorrect')->count(),
            'rewatches' => $events->where('event_type', 'video_replayed')->count(),
            'long_inactivity' => $events->where('event_type', 'inactivity')->count(),
            'long_session_duration' => ($events->max('duration_seconds') ?? 0) > 1800, // > 30 min
        ];
    }

    protected function calculateLoadLevel(array $signals): int
    {
        $level = 0;

        if ($signals['hint_requests'] > 3) $level += 2;
        if ($signals['slow_responses'] > 2) $level += 2;
        if ($signals['rapid_wrong_answers'] > 3) $level += 2;
        if ($signals['rewatches'] > 3) $level += 2;
        if ($signals['long_inactivity'] > 2) $level += 1;
        if ($signals['long_session_duration']) $level += 1;

        return min(10, $level);
    }

    protected function classifyStatus(int $level): string
    {
        return match(true) {
            $level >= 8 => 'critical',
            $level >= 5 => 'overloaded',
            $level >= 3 => 'elevated',
            default => 'normal',
        };
    }

    protected function getActions(int $loadLevel): array
    {
        return match(true) {
            $loadLevel >= 8 => [
                'Immediately reduce difficulty',
                'End current session',
                'Suggest break',
                'Serve micro-learning content',
            ],
            $loadLevel >= 5 => [
                'Reduce difficulty',
                'Shorten session duration',
                'Switch to simpler pedagogy',
                'Introduce break',
            ],
            $loadLevel >= 3 => [
                'Monitor closely',
                'Prepare for difficulty adjustment',
            ],
            default => ['Continue normal'],
        };
    }
}