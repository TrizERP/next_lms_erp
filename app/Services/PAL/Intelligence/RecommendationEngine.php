<?php

namespace App\Services\PAL\Intelligence;

use App\Models\PAL\Content;
use App\Models\PAL\ContentRecommendation;

/**
 * Recommendation Engine
 * Next best action, content recommendation, pedagogy recommendation, spaced repetition
 */
class RecommendationEngine
{
    /**
     * Get next best action for learner
     * @param int $learnerId
     * @param int|null $subjectId
     * @return array
     */
    public function getNextBestAction(int $learnerId, ?int $subjectId = null): array
    {
        // Find weak concepts
        $weakConcepts = $this->getWeakConcepts($learnerId, $subjectId);
        
        if (empty($weakConcepts)) {
            return $this->getAdvanceRecommendation($learnerId, $subjectId);
        }

        // Get due for spaced repetition
        $dueForReview = $this->getSpacedRepetitionDue($learnerId);
        
        // Prioritize
        $dueImportance = count($dueForReview) > 0 ? 0.7 : 0.3;
        $weakImportance = count($weakConcepts) > 0 ? 0.8 : 0.2;

        if ($dueImportance > $weakImportance && !empty($dueForReview)) {
            return [
                'action' => 'spaced_review',
                'concepts' => $dueForReview,
                'priority' => $dueImportance,
                'reason' => 'Scheduled review due',
            ];
        }

        return [
            'action' => 'learn_weak_concepts',
            'concepts' => $weakConcepts,
            'priority' => $weakImportance,
            'reason' => 'Build foundation',
        ];
    }

    /**
     * Get content recommendation
     * @param int $learnerId
     * @param int $conceptId
     * @return array
     */
    public function getContentRecommendation(int $learnerId, int $conceptId): array
    {
        $contents = Content::where('concept_id', $conceptId)
            ->where('status', 'active')
            ->get();

        $learnerLevel = $this->getLearnerLevel($learnerId);
        
        // Score content
        $scored = $contents->map(function ($content) use ($learnerId, $learnerLevel) {
            $score = 50;
            
            // Level match
            $levelDiff = abs($content->difficulty_level - $learnerLevel);
            $score -= $levelDiff * 10;
            
            // Previous engagement
            $engagement = ContentRecommendation::where('learner_id', $learnerId)
                ->where('content_id', $content->id)
                ->avg('engagement_score');
            
            if ($engagement) {
                $score += ($engagement - 50) * 0.3;
            }
            
            return ['content' => $content, 'score' => $score];
        })->sortByDesc('score');

        return [
            'recommended' => $scored->first()['content'] ?? null,
            'alternatives' => $scored->slice(1, 3)->values()->pluck('content')->toArray(),
        ];
    }

    /**
     * Get spaced repetition schedule
     * @param int $learnerId
     * @return array
     */
    public function getSpacedRepetitionSchedule(int $learnerId): array
    {
        $competencies = \App\Models\PAL\Competency::where('learner_id', $learnerId)
            ->where('mastery_score', '>=', 70)
            ->get();

        return $competencies->map(function ($comp) {
            $lastReviewed = $comp->updated_at;
            $daysSince = $lastReviewed->diffInDays(now());
            
            // Calculate next review interval based on mastery
            $interval = match(true) {
                $comp->mastery_score >= 90 => 14,
                $comp->mastery_score >= 80 => 7,
                $comp->mastery_score >= 70 => 3,
                default => 1,
            };
            
            return [
                'concept_id' => $comp->concept_id,
                'due' => $daysSince >= $interval,
                'days_overdue' => max(0, $daysSince - $interval),
                'interval_days' => $interval,
            ];
        })->filter(fn($c) => $c['due'])->values();
    }

    /**
     * Get weak concepts for review
     * @param int $learnerId
     * @param int|null $subjectId
     * @return array
     */
    protected function getWeakConcepts(int $learnerId, ?int $subjectId): array
    {
        $query = \App\Models\PAL\Competency::where('learner_id', $learnerId)
            ->where('mastery_score', '<', 60);

        if ($subjectId) {
            $query->where('subject_id', $subjectId);
        }

        return $query->orderBy('mastery_score')
            ->limit(5)
            ->pluck('concept_id')
            ->toArray();
    }

    /**
     * Get concepts due for spaced repetition
     * @param int $learnerId
     * @return array
     */
    protected function getSpacedRepetitionDue(int $learnerId): array
    {
        $due = [];
        
        $competencies = \App\Models\PAL\Competency::where('learner_id', $learnerId)
            ->where('mastery_score', '>=', 70)
            ->get();

        foreach ($competencies as $comp) {
            $daysSince = $comp->updated_at->diffInDays(now());
            $interval = $this->calculateInterval($comp->mastery_score);
            
            if ($daysSince >= $interval) {
                $due[] = $comp->concept_id;
            }
        }

        return $due;
    }

    /**
     * Get advance recommendation for strong learners
     * @param int $learnerId
     * @param int|null $subjectId
     * @return array
     */
    protected function getAdvanceRecommendation(int $learnerId, ?int $subjectId): array
    {
        return [
            'action' => 'advance',
            'priority' => 0.9,
            'reason' => 'Ready for new content',
        ];
    }

    /**
     * Get learner level (0-3)
     * @param int $learnerId
     * @return int
     */
    protected function getLearnerLevel(int $learnerId): int
    {
        $avg = \App\Models\PAL\Competency::where('learner_id', $learnerId)
            ->avg('mastery_score') ?? 50;

        return match(true) {
            $avg >= 80 => 3,
            $avg >= 60 => 2,
            $avg >= 40 => 1,
            default => 0,
        };
    }

    /**
     * Calculate spaced repetition interval
     * @param float $masteryScore
     * @return int
     */
    protected function calculateInterval(float $masteryScore): int
    {
        return match(true) {
            $masteryScore >= 95 => 21,
            $masteryScore >= 90 => 14,
            $masteryScore >= 85 => 10,
            $masteryScore >= 80 => 7,
            $masteryScore >= 70 => 4,
            default => 2,
        };
    }
}