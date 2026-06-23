<?php

namespace App\Services\PAL\Pedagogy;

/**
 * Emotional Safety Engine
 * Ensures adaptive learning never emotionally harms students
 */
class EmotionalSafetyEngine
{
    /**
     * Apply emotional safety rules to pedagogy selection
     * @param array $selected
     * @return array
     */
    public function apply(array $selected): array
    {
        $selected['safety_checks'] = [
            'no_public_ranking' => true,
            'positive_failure_language' => true,
            'celebrate_micro_progress' => true,
        ];

        // Ensure no harsh language in feedback
        $selected = $this->sanitizeLanguage($selected);

        return $selected;
    }

    /**
     * Detect confidence collapse
     * @param int $learnerId
     * @return array
     */
    public function detectConfidenceCollapse(int $learnerId): array
    {
        $recentResults = \App\Models\PAL\AssessmentResult::where('learner_id', $learnerId)
            ->where('created_at', '>=', now()->subDays(3))
            ->orderBy('created_at')
            ->get();

        if ($recentResults->count() < 3) {
            return ['detected' => false, 'signals' => []];
        }

        $signals = [];
        
        // Check for consecutive failures
        $consecutiveFailures = 0;
        foreach ($recentResults as $result) {
            if (!$result->is_correct) {
                $consecutiveFailures++;
            } else {
                $consecutiveFailures = 0;
            }
        }
        
        if ($consecutiveFailures >= 3) {
            $signals[] = 'consecutive_failures';
        }

        // Check for rapid decline
        $firstHalf = $recentResults->take(floor($recentResults->count() / 2));
        $secondHalf = $recentResults->skip(floor($recentResults->count() / 2));
        
        $firstAccuracy = $firstHalf->where('is_correct', true)->count() / ($firstHalf->count() ?: 1);
        $secondAccuracy = $secondHalf->where('is_correct', true)->count() / ($secondHalf->count() ?: 1);

        if ($secondAccuracy < $firstAccuracy - 0.3) {
            $signals[] = 'rapid_decline';
        }

        return [
            'detected' => count($signals) > 0,
            'signals' => $signals,
            'recommended_actions' => $this->getCollapseActions($signals),
        ];
    }

    /**
     * Detect frustration loops
     * @param int $learnerId
     * @param int $sessionId
     * @return array
     */
    public function detectFrustrationLoop(int $learnerId, int $sessionId): array
    {
        $events = \App\Models\PAL\SessionEvent::where('learner_id', $learnerId)
            ->where('session_id', $sessionId)
            ->orderBy('created_at')
            ->get();

        // Check for retry loops
        $retries = $events->where('event_type', 'retry');
        $retryPattern = [];
        
        foreach ($retries->chunk(3) as $chunk) {
            if ($chunk->count() >= 3) {
                $retryPattern[] = 'loop_detected';
            }
        }

        return [
            'detected' => count($retryPattern) > 0,
            'pattern' => $retryPattern,
            'action' => count($retryPattern) > 0 ? 'intervene' : 'continue',
        ];
    }

    /**
     * Generate emotionally safe feedback
     * @param string $type
     * @param array $data
     * @return string
     */
    public function generateFeedback(string $type, array $data = []): string
    {
        return match($type) {
            'correct' => $this->getPositiveFeedback($data['streak'] ?? 0),
            'incorrect' => $this->getEncouragingFeedback(),
            'partial' => $this->getProgressFeedback($data['percentage'] ?? 0),
            'mastered' => $this->getCelebrationFeedback(),
            default => 'Keep going!',
        };
    }

    protected function sanitizeLanguage(array $selected): array
    {
        $positivePhrases = [
            'struggling' => 'still learning',
            'failing' => 'making progress',
            'wrong' => 'not quite right',
            'bad' => 'room for improvement',
            'poor' => 'developing',
            'weak' => 'growing',
        ];

        if (isset($selected['message'])) {
            foreach ($positivePhrases as $negative => $positive) {
                $selected['message'] = str_replace($negative, $positive, $selected['message']);
            }
        }

        return $selected;
    }

    protected function getPositiveFeedback(int $streak): string
    {
        return match($streak) {
            0 => 'Great start!',
            1 => 'Nice work!',
            2 => 'You\'re on a roll!',
            3 => 'Fantastic! Keep it up!',
            4 => 'Amazing streak!',
            default => 'Incredible! You\'re unstoppable!',
        };
    }

    protected function getEncouragingFeedback(): string
    {
        $feedbacks = [
            'Almost there! Try considering another approach.',
            'That\'s one way to look at it. Let\'s explore more!',
            'Good attempt! Mistakes help us learn.',
            'Interesting thinking! Let\'s try a different angle.',
        ];
        return $feedbacks[array_rand($feedbacks)];
    }

    protected function getProgressFeedback(float $percentage): string
    {
        return match(true) {
            $percentage >= 80 => 'Great progress! Almost mastered!',
            $percentage >= 60 => 'Good progress! Keep going!',
            $percentage >= 40 => 'You\'re making progress!',
            default => 'Every attempt helps you learn!',
        };
    }

    protected function getCelebrationFeedback(): string
    {
        return 'Congratulations! You\'ve mastered this concept! 🎉';
    }

    protected function getCollapseActions(array $signals): array
    {
        $actions = [];
        
        if (in_array('consecutive_failures', $signals)) {
            $actions[] = 'Reduce difficulty immediately';
            $actions[] = 'Offer hints more freely';
            $actions[] = 'Provide encouragement';
        }
        
        if (in_array('rapid_decline', $signals)) {
            $actions[] = 'Review base concepts';
            $actions[] = 'Take a break';
        }

        return $actions;
    }
}