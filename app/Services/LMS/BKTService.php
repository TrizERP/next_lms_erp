<?php

namespace App\Services\LMS;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BKTService
{
    // BKT Parameters
    private float $pLearn = 0.2;      // Probability of learning
    private float $pGuess = 0.25;     // Probability of guessing correctly
    private float $pSlip = 0.1;       // Probability of slipping (making error despite knowing)
    private float $pInit = 0.3;       // Initial knowledge probability
    
    /**
     * Update learner knowledge probability using Bayesian Knowledge Tracing
     * 
     * @param bool $isCorrect
     * @param int|null $studentId
     * @param string|null $conceptId
     * @return float
     */
    public function update(bool $isCorrect, ?int $studentId = null, ?string $conceptId = null): float
    {
        // Get current knowledge probability for this student-concept pair
        $cacheKey = $studentId && $conceptId ? "bkt_{$studentId}_{$conceptId}" : "bkt_default";
        $currentPKnow = Cache::get($cacheKey, $this->pInit);
        
        // Apply Bayesian update formula
        $pKnow = $this->applyBayesianUpdate($currentPKnow, $isCorrect);
        
        // Apply learning update (probability that student learned from this attempt)
        $pKnow = $this->applyLearningUpdate($pKnow);
        
        // Store updated probability
        Cache::put($cacheKey, $pKnow, now()->addDays(30));
        
        Log::debug('BKT Update', [
            'current_p_know' => $currentPKnow,
            'is_correct' => $isCorrect,
            'new_p_know' => $pKnow
        ]);
        
        return round($pKnow, 4);
    }
    
    /**
     * Apply Bayesian update formula
     * 
     * @param float $currentPKnow
     * @param bool $isCorrect
     * @return float
     */
    private function applyBayesianUpdate(float $currentPKnow, bool $isCorrect): float
    {
        if ($isCorrect) {
            // P(correct|know) = 1 - pSlip
            // P(correct|~know) = pGuess
            $pCorrectGivenKnow = 1 - $this->pSlip;
            $pCorrectGivenNotKnow = $this->pGuess;
            
            // P(know|correct) = [P(correct|know) * P(know)] / P(correct)
            $numerator = $pCorrectGivenKnow * $currentPKnow;
            $denominator = $numerator + ($pCorrectGivenNotKnow * (1 - $currentPKnow));
            
            return $denominator > 0 ? $numerator / $denominator : $currentPKnow;
        } else {
            // P(incorrect|know) = pSlip
            // P(incorrect|~know) = 1 - pGuess
            $pIncorrectGivenKnow = $this->pSlip;
            $pIncorrectGivenNotKnow = 1 - $this->pGuess;
            
            // P(know|incorrect) = [P(incorrect|know) * P(know)] / P(incorrect)
            $numerator = $pIncorrectGivenKnow * $currentPKnow;
            $denominator = $numerator + ($pIncorrectGivenNotKnow * (1 - $currentPKnow));
            
            return $denominator > 0 ? $numerator / $denominator : $currentPKnow;
        }
    }
    
    /**
     * Apply learning update (probability that student learned from this attempt)
     * 
     * @param float $currentPKnow
     * @return float
     */
    private function applyLearningUpdate(float $currentPKnow): float
    {
        // After update, apply learning probability
        // P(know after attempt) = P(know before) + P(~know before) * pLearn
        return $currentPKnow + ((1 - $currentPKnow) * $this->pLearn);
    }
    
    /**
     * Get current knowledge probability
     * 
     * @param int $studentId
     * @param string $conceptId
     * @return float
     */
    public function getCurrentKnowledge(int $studentId, string $conceptId): float
    {
        $cacheKey = "bkt_{$studentId}_{$conceptId}";
        return Cache::get($cacheKey, $this->pInit);
    }
    
    /**
     * Reset knowledge for a student-concept pair
     * 
     * @param int $studentId
     * @param string $conceptId
     * @return void
     */
    public function resetKnowledge(int $studentId, string $conceptId): void
    {
        $cacheKey = "bkt_{$studentId}_{$conceptId}";
        Cache::forget($cacheKey);
        
        Log::info('BKT knowledge reset', [
            'student_id' => $studentId,
            'concept_id' => $conceptId
        ]);
    }
}