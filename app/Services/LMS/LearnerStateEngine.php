<?php

namespace App\Services\LMS;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LearnerStateEngine
{
    /**
     * Update learner's real-time state
     * 
     * @param int $studentId
     * @param array $metrics
     * @return array
     */
    public function update(int $studentId, array $metrics): array
    {
        // Get current state
        $currentState = $this->getCurrentState($studentId);
        
        // Calculate new state metrics
        $engagement = $this->calculateEngagement($metrics, $currentState);
        $fatigue = $this->calculateFatigue($metrics, $currentState);
        $velocity = $this->calculateVelocity($metrics, $currentState);
        $frustration = $this->calculateFrustration($metrics, $currentState);
        
        $newState = [
            'engagement' => $engagement,
            'fatigue' => $fatigue,
            'velocity' => $velocity,
            'frustration' => $frustration,
            'last_updated' => now()->toIso8601String()
        ];
        
        // Store updated state
        $cacheKey = "learner_state_{$studentId}";
        Cache::put($cacheKey, $newState, now()->addHours(24));
        
        Log::debug('Learner state updated', [
            'student_id' => $studentId,
            'state' => $newState
        ]);
        
        return $newState;
    }
    
    /**
     * Get current learner state
     * 
     * @param int $studentId
     * @return array
     */
    private function getCurrentState(int $studentId): array
    {
        $cacheKey = "learner_state_{$studentId}";
        return Cache::get($cacheKey, [
            'engagement' => 'medium',
            'fatigue' => 'low',
            'velocity' => 'medium',
            'frustration' => 'low'
        ]);
    }
    
    /**
     * Calculate engagement level
     * 
     * @param array $metrics
     * @param array $currentState
     * @return string
     */
    private function calculateEngagement(array $metrics, array $currentState): string
    {
        $isCorrect = $metrics['is_correct'] ?? false;
        $timeTaken = $metrics['time_taken'] ?? 60;
        $confidence = $metrics['confidence'] ?? 0.5;
        
        // Engagement formula based on correctness, speed, and confidence
        $score = ($isCorrect ? 0.5 : 0) + ($confidence * 0.3) + (min(60, $timeTaken) / 60 * 0.2);
        
        if ($score >= 0.7) return 'high';
        if ($score >= 0.4) return 'medium';
        return 'low';
    }
    
    /**
     * Calculate fatigue level
     * 
     * @param array $metrics
     * @param array $currentState
     * @return string
     */
    private function calculateFatigue(array $metrics, array $currentState): string
    {
        $timeTaken = $metrics['time_taken'] ?? 60;
        $consecutiveAttempts = $metrics['consecutive_attempts'] ?? 1;
        
        // Fatigue increases with time spent and consecutive attempts
        $fatigueScore = ($timeTaken / 120) + ($consecutiveAttempts / 10);
        
        if ($fatigueScore >= 0.7) return 'high';
        if ($fatigueScore >= 0.4) return 'medium';
        return 'low';
    }
    
    /**
     * Calculate learning velocity (speed of learning)
     * 
     * @param array $metrics
     * @param array $currentState
     * @return string
     */
    private function calculateVelocity(array $metrics, array $currentState): string
    {
        $isCorrect = $metrics['is_correct'] ?? false;
        $timeTaken = $metrics['time_taken'] ?? 60;
        
        // Fast = correct and quick, Slow = incorrect or slow
        if ($isCorrect && $timeTaken < 30) return 'fast';
        if ($isCorrect && $timeTaken < 60) return 'medium';
        return 'slow';
    }
    
    /**
     * Calculate frustration level
     * 
     * @param array $metrics
     * @param array $currentState
     * @return string
     */
    private function calculateFrustration(array $metrics, array $currentState): string
    {
        $isCorrect = $metrics['is_correct'] ?? true;
        $timeTaken = $metrics['time_taken'] ?? 60;
        $currentFrustration = $currentState['frustration'] ?? 'low';
        
        // Frustration increases with incorrect answers and long time
        if (!$isCorrect && $timeTaken > 90) return 'high';
        if (!$isCorrect && $timeTaken > 45) return 'medium';
        if (!$isCorrect) return 'medium';
        
        // Decrease frustration over time with correct answers
        if ($isCorrect && $currentFrustration === 'high') return 'medium';
        if ($isCorrect && $currentFrustration === 'medium') return 'low';
        
        return 'low';
    }
}