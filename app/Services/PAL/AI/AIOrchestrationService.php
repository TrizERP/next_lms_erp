<?php

namespace App\Services\PAL\AI;

use App\Domain\AI\Configuration\AiConfigurationException;
use App\Domain\AI\Configuration\AiModelClientFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\PAL\Remediation;

/**
 * AI Orchestration Service
 * Handles LLM prompt orchestration for PAL V4
 */
class AIOrchestrationService
{
    protected int $maxTokens = 2000;

    /** The school this service acts for; null means platform keys only. */
    protected int|string|null $subInstituteId = null;

    /**
     * A copy scoped to one school, so that school's `pal_intelligence` AI configuration applies.
     * Learner-scoped methods resolve the learner's own school and do not need this.
     */
    public function forInstitute(int|string|null $subInstituteId): static
    {
        $copy = clone $this;
        $copy->subInstituteId = $subInstituteId;

        return $copy;
    }

    /** A learner's own school, from the database -- never from request input. */
    protected function learnerInstitute(int $learnerId): ?int
    {
        $value = DB::table('tblstudent')->where('id', $learnerId)->value('sub_institute_id')
            ?? DB::table('tbluser')->where('id', $learnerId)->value('sub_institute_id');

        return $value !== null && $value !== '' ? (int) $value : null;
    }

    /**
     * Generate personalized explanation
     * @param int $learnerId
     * @param int $conceptId
     * @param string $style (simple|detailed|analogy|story)
     * @return array
     */
    public function generateExplanation(int $learnerId, int $conceptId, string $style = 'simple'): array
    {
        $concept = \App\Models\PAL\Concept::find($conceptId);

        if (!$concept) {
            return ['error' => 'Concept not found'];
        }

        $learnerLevel = $this->getLearnerLevel($learnerId);
        $previousMisconceptions = $this->getPreviousMisconceptions($learnerId, $conceptId);

        $prompt = $this->buildExplanationPrompt($concept, $learnerLevel, $style, $previousMisconceptions);

        return  $this->callAI($prompt, [
            'style' => $style,
            'concept_id' => $conceptId,
            'learner_level' => $learnerLevel,
        ], $this->learnerInstitute($learnerId));

    }

    /**
     * Generate misconception remediation
     * @param int $learnerId
     * @param int $misconceptionId
     * @return array
     */
    public function generateRemediation(int $learnerId, int $misconceptionId): array
    {
        $misconception = \App\Models\PAL\Misconception::find($misconceptionId);
        
        if (!$misconception) {
            return ['error' => 'Misconception not found'];
        }

        $prompt = $this->buildRemediationPrompt($misconception);

        $result = $this->callAI($prompt, [
            'type' => 'remediation',
            'misconception_id' => $misconceptionId,
        ], $this->learnerInstitute($learnerId));

        // Cache the result for reuse
        Cache::put("ai_remediation_{$misconceptionId}", $result, now()->addHours(24));

        // Save to database if successful
        if (!isset($result['error']) || !$result['error']) {
            Remediation::create([
                'misconception_id' => $misconceptionId,
                'type' => 'ai_generated',
                'content' => $result['content'],
                'pedagogy' => null,
                'effectiveness_score' => 0,
                'status' => 'active',
            ]);
        }

        return $result;
    }

    /**
     * Generate personalized practice
     * @param int $learnerId
     * @param int $conceptId
     * @param string $difficulty
     * @return array
     */
    public function generatePractice(int $learnerId, int $conceptId, string $difficulty = 'medium'): array
    {
        $concept = \App\Models\PAL\Concept::find($conceptId);
        
        if (!$concept) {
            return ['error' => 'Concept not found'];
        }

        $weakAreas = $this->getWeakAreas($learnerId, $conceptId);

        $prompt = $this->buildPracticePrompt($concept, $weakAreas, $difficulty);

        return $this->callAI($prompt, [
            'type' => 'practice',
            'concept_id' => $conceptId,
            'difficulty' => $difficulty,
        ], $this->learnerInstitute($learnerId));
    }

    /**
     * Summarize content
     * @param int $contentId
     * @param string $format (bullet|paragraph|flashcard)
     * @return array
     */
    public function summarizeContent(int $contentId, string $format = 'bullet'): array
    {
        $content = \App\Models\PAL\Content::find($contentId);
        
        if (!$content) {
            return ['error' => 'Content not found'];
        }

        $prompt = $this->buildSummaryPrompt($content, $format);

        return $this->callAI($prompt, [
            'type' => 'summary',
            'content_id' => $contentId,
            'format' => $format,
        ], $content->sub_institute_id ?? null);
    }

    /**
     * Get teacher insights
     * @param int $learnerId
     * @param int $classroomId
     * @return array
     */
    public function getTeacherInsights(int $learnerId, int $classroomId): array
    {
        $learner = \App\Models\User::find($learnerId);
        
        if (!$learner) {
            return ['error' => 'Learner not found'];
        }

        $metrics = $this->getLearnerMetrics($learnerId, $classroomId);
        $pedagogyPerformance = $this->getPedagogyPerformance($learnerId);
        $riskFactors = $this->getRiskFactors($learnerId);

        $prompt = $this->buildTeacherInsightPrompt($learner, $metrics, $pedagogyPerformance, $riskFactors);

        return $this->callAI($prompt, [
            'type' => 'teacher_insight',
            'learner_id' => $learnerId,
            'classroom_id' => $classroomId,
        ], $this->learnerInstitute($learnerId));
    }

    /**
     * Generate metacognitive prompt
     * @param int $learnerId
     * @param string $context
     * @return array
     */
    public function generateMetacognitivePrompt(int $learnerId, string $context = 'general'): array
    {
        $history = $this->getRecentPerformance($learnerId);

        $prompt = $this->buildMetacognitionPrompt($history, $context);

        return $this->callAI($prompt, [
            'type' => 'metacognition',
            'context' => $context,
        ], $this->learnerInstitute($learnerId));
    }

    /**
     * Batch process for efficiency
     * @param array $prompts
     * @return array
     */
    public function batchProcess(array $prompts): array
    {
        // Combine prompts if under token limit
        $combined = implode("\n\n---\n\n", array_column($prompts, 'prompt'));
        
        if (strlen($combined) < 5000) {
            // Process together
            $result = $this->callAI($combined);
            return array_map(fn($p) => $result, $prompts);
        }

        // Process individually
        return array_map(fn($p) => $this->callAI($p['prompt']), $prompts);
    }

    protected function callAI(string $systemPrompt, array $metadata = [], int|string|null $subInstituteId = null): array
    {
        try {
            // Provider, model, key and fallbacks come from the school's saved PAL Intelligence configuration.
            $client = app(AiModelClientFactory::class)->for('pal_intelligence', $subInstituteId ?? $this->subInstituteId);

            $content = $client->chat(
                [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ...($metadata['history'] ?? []),
                ],
                null,
                $this->maxTokens,
                0.7,
                false,
                30,
            );

            if (is_string($content) && trim($content) !== '') {
                return [
                    'content' => $content,
                    'model' => $client->lastUsed()?->model,
                    'metadata' => $metadata,
                    'generated_at' => now()->toIso8601String(),
                ];
            }
        } catch (AiConfigurationException $e) {
            Log::warning('AI call not configured', ['error' => $e->getMessage()]);

            return [
                'content' => $e->getMessage(),
                'error' => true,
                'metadata' => $metadata,
            ];
        } catch (\Exception $e) {
            Log::warning('AI call failed', ['error' => $e->getMessage()]);
        }

        return [
            'content' => 'AI generation temporarily unavailable. Please try again.',
            'error' => true,
            'metadata' => $metadata,
        ];
    }

    protected function buildExplanationPrompt($concept, int $learnerLevel, string $style, array $previousMisconceptions): string
    {
        $levelDesc = match($learnerLevel) {
            0 => 'beginner',
            1 => 'intermediate',
            2 => 'advanced',
            default => 'intermediate',
        };

        $misconceptionContext = !empty($previousMisconceptions) 
            ? "Note the learner has shown these misconceptions before: " . implode(', ', $previousMisconceptions)
            : '';

        return <<<PROMPT
You are an expert K12 educator. Explain the following concept to a {$levelDesc} learner in a {$style} manner.

Concept: {$concept->name}
Description: {$concept->description}
{$misconceptionContext}

Requirements:
- Use age-appropriate language
- If using analogy, make it relatable
- If using story, make it engaging
- Address common misconceptions
- Keep it under 300 words
PROMPT;
    }

    protected function buildRemediationPrompt($misconception): string
    {
        return <<<PROMPT
Generate a clear, simple explanation to correct this misconception:

Misconception: {$misconception->pattern}
Root Cause: {$misconception->root_cause}

Provide:
1. Why this misconception is incorrect
2. The correct understanding
3. A different teaching approach (analogy, visual, or story-based)
4. A simple practice question

Keep it under 200 words and encouraging in tone.
PROMPT;
    }

    protected function buildPracticePrompt($concept, array $weakAreas, string $difficulty): string
    {
        $weakAreasStr = !empty($weakAreas) 
            ? "Focus on these areas: " . implode(', ', $weakAreas)
            : '';

        return <<<PROMPT
Generate a personalized practice problem for a {$difficulty} level student.

Concept: {$concept->name}
{$weakAreasStr}

Provide:
1. A clear problem statement
2. Step-by-step solution
3. A similar practice problem

Keep it under 250 words.
PROMPT;
    }

    protected function buildSummaryPrompt($content, string $format): string
    {
        $formatInstr = match($format) {
            'bullet' => 'Use bullet points',
            'paragraph' => 'Use a paragraph format',
            'flashcard' => 'Create flashcard-style summary',
            default => 'Use bullet points',
        };

        return <<<PROMPT
{$formatInstr} for this content:

Title: {$content->title}
Content: {$content->body}

Keep it under 150 words.
PROMPT;
    }

    protected function buildTeacherInsightPrompt($learner, array $metrics, array $pedagogy, array $risk): string
    {
        return <<<PROMPT
Generate concise teacher insights for student {$learner->name}:

Performance Metrics:
- Overall mastery: {$metrics['mastery']}%
- Recent trend: {$metrics['trend']}
- Weak areas: {$metrics['weak_areas']}

Pedagogy Performance:
{$this->formatArray($pedagogy)}

Risk Factors:
{$this->formatArray($risk)}

Provide:
1. Key observations (2-3 points)
2. Recommended interventions
3. Next steps for teacher

Keep it under 200 words.
PROMPT;
    }

    protected function buildMetacognitionPrompt(array $history, string $context): string
    {
        return <<<PROMPT
Generate a metacognitive prompt for a learner.

Context: {$context}
Recent performance: {$history['summary']}

Create an engaging prompt that helps the learner reflect on their learning.
Keep it to 1-2 sentences.
PROMPT;
    }

    protected function getLearnerLevel(int $learnerId): int
    {
        $avg = \App\Models\PAL\Competency::where('learner_id', $learnerId)
            ->avg('mastery_score') ?? 50;

        return match(true) {
            $avg >= 75 => 2,
            $avg >= 50 => 1,
            default => 0,
        };
    }

    protected function getPreviousMisconceptions(int $learnerId, int $conceptId): array
    {
        return \App\Models\PAL\LearnerMisconception::where('learner_id', $learnerId)
            ->where('concept_id', $conceptId)
            ->with('misconception')
            ->get()
            ->pluck('misconception.pattern')
            ->toArray();
    }

    protected function getWeakAreas(int $learnerId, int $conceptId): array
    {
        return \App\Models\PAL\Competency::where('learner_id', $learnerId)
            ->where('concept_id', '!=', $conceptId)
            ->where('mastery_score', '<', 50)
            ->pluck('concept_id')
            ->toArray();
    }

    protected function getLearnerMetrics(int $learnerId, int $classroomId): array
    {
        return [
            'mastery' => \App\Models\PAL\Competency::where('learner_id', $learnerId)->avg('mastery_score') ?? 0,
            'trend' => 'stable',
            'weak_areas' => 'math foundations',
        ];
    }

    protected function getPedagogyPerformance(int $learnerId): array
    {
        return [];
    }

    protected function getRiskFactors(int $learnerId): array
    {
        return [];
    }

    protected function getRecentPerformance(int $learnerId): array
    {
        return ['summary' => 'Improving'];
    }

    protected function formatArray(array $arr): string
    {
        return empty($arr) ? 'None identified' : implode(', ', $arr);
    }
}