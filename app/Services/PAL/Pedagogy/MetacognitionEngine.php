<?php

namespace App\Services\PAL\Pedagogy;

use App\Models\PAL\Reflection;

/**
 * Metacognition Engine
 * Teaches students how to learn through reflection prompts
 */
class MetacognitionEngine
{
    /**
     * Get metacognitive prompts for learner
     * @param int $learnerId
     * @param string $context
     * @return array
     */
    public function getPrompts(int $learnerId, string $context = 'general'): array
    {
        $lastReflection = Reflection::where('learner_id', $learnerId)
            ->orderBy('created_at', 'desc')
            ->first();

        // Don't repeat prompts too frequently
        if ($lastReflection && $lastReflection->created_at->diffInHours(now()) < 1) {
            return ['skip' => true, 'reason' => 'recent_prompt'];
        }

        $prompts = match($context) {
            'after_answer' => $this->getPostAnswerPrompts(),
            'after_concept' => $this->getPostConceptPrompts(),
            'session_end' => $this->getSessionEndPrompts(),
            default => $this->getGeneralPrompts(),
        };

        return [
            'prompts' => $prompts,
            'type' => $context,
            'prompt_count' => count($prompts),
        ];
    }

    /**
     * Record learner reflection
     * @param int $learnerId
     * @param array $reflection
     * @return Reflection
     */
    public function record(int $learnerId, array $reflection): Reflection
    {
        return Reflection::create([
            'learner_id' => $learnerId,
            'session_id' => $reflection['session_id'] ?? null,
            'concept_id' => $reflection['concept_id'] ?? null,
            'content_id' => $reflection['content_id'] ?? null,
            'prompt_type' => $reflection['prompt_type'] ?? 'general',
            'learner_response' => $reflection['response'],
            'quality_score' => $this->assessQuality($reflection['response']),
            'context_data' => $reflection['context'] ?? [],
        ]);
    }

    /**
     * Assess reflection quality
     * @param string $response
     * @return int
     */
    public function assessQuality(string $response): int
    {
        if (strlen($response) < 10) return 0;

        $score = 0;

        // Length indicates depth
        if (strlen($response) > 50) $score += 20;
        if (strlen($response) > 100) $score += 20;

        // Self-reference shows metacognition
        if (preg_match('/I think|I feel|I realized|I noticed/i', $response)) {
            $score += 20;
        }

        // Shows planning/strategy
        if (preg_match('/next time|I will try|I should|I could/i', $response)) {
            $score += 20;
        }

        // Shows self-awareness
        if (preg_match('/because|reason|since|therefore/i', $response)) {
            $score += 20;
        }

        return min(100, $score);
    }

    /**
     * Get self-efficacy score
     * @param int $learnerId
     * @return float
     */
    public function getSelfEfficacy(int $learnerId): float
    {
        $reflections = Reflection::where('learner_id', $learnerId)
            ->where('created_at', '>=', now()->subDays(14))
            ->get();

        if ($reflections->isEmpty()) return 50;

        return $reflections->avg('quality_score') ?? 50;
    }

    protected function getPostAnswerPrompts(): array
    {
        return [
            [
                'type' => 'explanation',
                'prompt' => 'Why did you choose this answer?',
                'goal' => 'self_explanation',
            ],
            [
                'type' => 'confidence',
                'prompt' => 'How confident are you in this answer?',
                'goal' => 'confidence_awareness',
            ],
        ];
    }

    protected function getPostConceptPrompts(): array
    {
        return [
            [
                'type' => 'understanding',
                'prompt' => 'What did you find most interesting about this concept?',
                'goal' => 'connection_building',
            ],
            [
                'type' => 'application',
                'prompt' => 'How might you use this knowledge?',
                'goal' => 'transfer_learning',
            ],
        ];
    }

    protected function getSessionEndPrompts(): array
    {
        return [
            [
                'type' => 'summary',
                'prompt' => 'What was the main thing you learned today?',
                'goal' => 'summarization',
            ],
            [
                'type' => 'strategy',
                'prompt' => 'What learning strategy worked best for you?',
                'goal' => 'strategy_awareness',
            ],
            [
                'type' => 'goal',
                'prompt' => 'What would you like to focus on next?',
                'goal' => 'planning',
            ],
        ];
    }

    protected function getGeneralPrompts(): array
    {
        return [
            [
                'type' => 'reflection',
                'prompt' => 'How is your learning going?',
                'goal' => 'self_monitoring',
            ],
            [
                'type' => 'strategy',
                'prompt' => 'What approach are you using to understand new concepts?',
                'goal' => 'strategy_awareness',
            ],
        ];
    }
}
