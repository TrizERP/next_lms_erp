<?php

namespace App\Services\PAL\Content;

use App\Models\PAL\Content as ContentModel;
use App\Models\PAL\ContentRecommendation;

/**
 * Content Intelligence Service
 * Handles content routing and remediation
 */
class ContentIntelligenceService
{
    /**
     * Get recommended content for learner
     * @param int $learnerId
     * @param int $conceptId
     * @param array $context
     * @return array
     */
    public function getRecommendation(int $learnerId, int $conceptId, array $context = []): array
    {
        $contents = ContentModel::where('concept_id', $conceptId)
            ->where('status', 'active')
            ->get();

        if ($contents->isEmpty()) {
            return $this->getAIRemediatedContent($learnerId, $conceptId, $context);
        }

        // Filter by difficulty matching learner level
        $learnerLevel = $this->getLearnerLevel($learnerId);
        $filtered = $contents->filter(fn($c) => $this->matchesDifficulty($c, $learnerLevel));

        // Sort by multiple factors
        $scored = $this->scoreContent($filtered, $learnerId, $context);

        return [
            'content' => $scored->first(),
            'alternatives' => $scored->slice(1, 5)->values(),
            'count' => $scored->count(),
        ];
    }

    /**
     * Get content variants for H5P
     * @param int $conceptId
     * @param string $h5pType
     * @return array
     */
    public function getH5PVariants(int $conceptId, string $h5pType): array
    {
        return ContentModel::where('concept_id', $conceptId)
            ->where('h5p_type', $h5pType)
            ->where('status', 'active')
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'title' => $c->title,
                'difficulty' => $c->difficulty_level,
                'bloom_level' => $c->bloom_level,
            ])
            ->toArray();
    }

    /**
     * Get misconception content
     * @param int $misconceptionId
     * @return array
     */
    public function getMisconceptionContent(int $misconceptionId): array
    {
        $contents = ContentModel::whereJsonContains('misconception_tags', $misconceptionId)
            ->where('status', 'active')
            ->get();

        return [
            'contents' => $contents->map(fn($c) => [
                'id' => $c->id,
                'type' => $c->content_type,
                'title' => $c->title,
                'format' => $c->format,
            ]),
            'count' => $contents->count(),
        ];
    }

    /**
     * Track content engagement
     * @param int $learnerId
     * @param int $contentId
     * @param string $eventType
     * @return void
     */
    public function trackEngagement(int $learnerId, int $contentId, string $eventType): void
    {
        ContentRecommendation::create([
            'learner_id' => $learnerId,
            'content_id' => $contentId,
            'event_type' => $eventType,
            'engagement_score' => $this->calculateEngagementScore($eventType),
        ]);
    }

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

    protected function matchesDifficulty($content, int $learnerLevel): bool
    {
        // Allow 1 level variance for optimal challenge
        return abs($content->difficulty_level - $learnerLevel) <= 1;
    }

    protected function scoreContent($contents, int $learnerId, array $context): \Illuminate\Database\Eloquent\Collection
    {
        return $contents->map(function ($content) use ($learnerId) {
            $score = 50;

            // Boost from previous engagement
            $history = ContentRecommendation::where('learner_id', $learnerId)
                ->where('content_id', $content->id)
                ->avg('engagement_score');

            if ($history) {
                $score += ($history - 50) * 0.4;
            }

            // Boost from pedagogy match
            if (in_array($context['pedagogy'] ?? '', $content->pedagogy_tags ?? [])) {
                $score += 15;
            }

            // Penalize recently consumed
            $recent = ContentRecommendation::where('learner_id', $learnerId)
                ->where('content_id', $content->id)
                ->where('created_at', '>=', now()->subDays(1))
                ->exists();

            if ($recent) {
                $score -= 20;
            }

            $content->recommendation_score = round($score);
            return $content;
        })->sortByDesc('recommendation_score');
    }

    protected function getAIRemediatedContent(int $learnerId, int $conceptId, array $context): array
    {
        // Use AI to generate content on-the-fly
        // This would call the AI service
        return [
            'ai_generated' => true,
            'concept_id' => $conceptId,
            'fallback_message' => 'No content available - requesting AI generation',
        ];
    }

    protected function calculateEngagementScore(string $eventType): int
    {
        return match($eventType) {
            'completed' => 100,
            'partial' => 50,
            'skipped' => 10,
            'started' => 30,
            default => 20,
        };
    }
}