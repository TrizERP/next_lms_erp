<?php

namespace App\Services\PAL\Intelligence;

use App\Models\PAL\LearnerMisconception;
use App\Models\PAL\Misconception;
use App\Models\PAL\Remediation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Misconception Intelligence Engine
 * Moves from wrong answer detection to root cause diagnostic intelligence
 */
class MisconceptionIntelligenceEngine
{
    /**
     * Analyze misconception patterns
     * @param int $learnerId
     * @param int $conceptId
     * @param array $attemptData
     * @return array
     */
    public function analyze(int $learnerId, int $conceptId, array $attemptData): array
    {
        $pattern = $this->collectErrorPattern($learnerId, $conceptId, $attemptData);
        $cluster = $this->clusterPattern($pattern, $conceptId);
        $rootCause = $this->inferRootCause($learnerId, $cluster);
        $severity = $this->calculateSeverity($pattern);

        // Persist to database
        $misconception = Misconception::firstOrCreate(
            [
                'concept_id' => $conceptId,
                'pattern'    => $cluster,
            ],
            [
                'category'   => $cluster,
                'root_cause' => $rootCause['ai_diagnostic'] ?? $cluster,
                'frequency'  => 0,
            ]
        );

        if (!$misconception->wasRecentlyCreated) {
            $misconception->increment('frequency');
        }

        $learnerMisconception = LearnerMisconception::create([
            'learner_id'       => $learnerId,
            'concept_id'       => $conceptId,
            'misconception_id' => $misconception->id,
            'status'           => 'active',
            'severity'         => $severity === 'high' ? 3 : ($severity === 'medium' ? 2 : 1),
        ]);

        return [
            'learner_id'               => $learnerId,
            'concept_id'               => $conceptId,
            'error_pattern'            => $pattern,
            'misconception_cluster'    => $cluster,
            'root_cause'               => $rootCause,
            'severity'                 => $severity,
            'requires_intervention'    => $this->requiresIntervention($cluster),
            'ai_diagnostic'            => $rootCause['ai_diagnostic'] ?? null,
            // Storage info
            'stored'                   => true,
            'misconception_id'         => $misconception->id,
            'learner_misconception_id' => $learnerMisconception->id,
        ];
    }

    /**
     * Cluster misconception patterns
     * @param int $conceptId
     * @return array
     */
    public function cluster(int $conceptId): array
    {
        $misconceptions = Misconception::where('concept_id', $conceptId)->get();
        
        return $misconceptions->map(function ($m) {
            return [
                'id' => $m->id,
                'pattern' => $m->pattern,
                'category' => $m->category,
                'root_cause' => $m->root_cause,
                'frequency' => $m->frequency,
            ];
        })->toArray();
    }

    /**
     * Get targeted remediation
     * @param int $learnerId
     * @param int $misconceptionId
     * @return array
     */
    public function getRemediation(int $learnerId, int $misconceptionId): array
    {
        $misconception = Misconception::find($misconceptionId);
        
        if (!$misconception) {
            return ['error' => 'Misconception not found'];
        }

        $remediations = Remediation::where('misconception_id', $misconceptionId)
            ->where('status', 'active')
            ->get()
            ->map(function ($r) {
                return [
                    'id' => $r->id,
                    'type' => $r->type,
                    'content' => $r->content,
                    'pedagogy' => $r->pedagogy,
                    'effectiveness' => $r->effectiveness_score,
                ];
            });

        // If no pre-defined remediations, use AI
        if ($remediations->isEmpty()) {
            $aiRemediation = $this->generateAIRemediation($misconception);
            return [
                'ai_generated' => $aiRemediation,
                'alternative_pedagogies' => $this->getAlternativePedagogies($misconception),
            ];
        }

        return [
            'pre_defined_remediations' => $remediations,
            'alternative_pedagogies' => $this->getAlternativePedagogies($misconception),
            'recommended_sequence' => $this->getRemediationSequence($remediations),
        ];
    }

    protected function collectErrorPattern(int $learnerId, int $conceptId, array $attemptData): array
    {
        return [
            'incorrect_option' => $attemptData['selected_option'] ?? null,
            'time_spent' => $attemptData['time_seconds'] ?? 0,
            'hint_used' => $attemptData['hint_used'] ?? false,
            'repeated_pattern' => $this->checkRepeatedPattern($learnerId, $conceptId),
            'previous_misconception' => $this->getPreviousMisconception($learnerId, $conceptId),
        ];
    }

    protected function checkRepeatedPattern(int $learnerId, int $conceptId): bool
    {
        return LearnerMisconception::where('learner_id', $learnerId)
            ->where('concept_id', $conceptId)
            ->where('status', 'active')
            ->exists();
    }

    protected function getPreviousMisconception(int $learnerId, int $conceptId): ?array
    {
        $prev = LearnerMisconception::where('learner_id', $learnerId)
            ->where('concept_id', $conceptId)
            ->orderBy('created_at', 'desc')
            ->first();

        return $prev ? [
            'id' => $prev->misconception_id,
            'pattern' => $prev->misconception?->pattern,
        ] : null;
    }

    protected function clusterPattern(array $pattern, int $conceptId): string
    {
        if ($pattern['repeated_pattern']) {
            return 'persistent_misconception';
        }

        if ($pattern['time_spent'] < 5 && $pattern['incorrect_option']) {
            return 'attention_slip';
        }

        if ($pattern['hint_used'] && $pattern['time_spent'] > 30) {
            return 'procedural_error';
        }

        return 'concept_confusion';
    }

    protected function inferRootCause(int $learnerId, string $cluster): array
    {
        // Use AI to infer root cause
        $prompt = $this->buildDiagnosticPrompt($learnerId, $cluster);

        $headers = config('openrouter.headers');
        $baseUrl = config('openrouter.base_url');

        if (!$headers || !$baseUrl) {
            return [
                'inferred_cause' => $cluster,
                'fallback' => true,
                'reason' => 'OpenRouter not configured',
            ];
        }
        
        try {
            $response = Http::withHeaders($headers)
                ->post($baseUrl . '/chat/completions', [
                    'model' => config('openrouter.model'),
                    'messages' => [
                        ['role' => 'system', 'content' => $this->getDiagnosticSystemPrompt()],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'max_tokens' => 300,
                ]);

            if ($response->successful()) {
                return [
                    'ai_diagnostic' => $response->json('choices.0.message.content'),
                    'inferred_cause' => $cluster,
                ];
            }
        } catch (\Exception $e) {
            Log::warning('AI diagnostic failed', ['error' => $e->getMessage()]);
        }

        return [
            'inferred_cause' => $cluster,
            'fallback' => true,
        ];
    }

    protected function calculateSeverity(array $pattern): string
    {
        if ($pattern['repeated_pattern']) return 'high';
        if ($pattern['time_spent'] < 3) return 'medium';
        return 'low';
    }

    protected function requiresIntervention(string $cluster): bool
    {
        return in_array($cluster, ['persistent_misconception', 'procedural_error']);
    }

    protected function generateAIRemediation($misconception): array
    {
        $prompt = "Generate a clear, simple explanation to correct the misconception: {$misconception->pattern}. Provide a different teaching approach (analogy, visual, or story-based) that would help a student overcome this specific error.";

        $headers = config('openrouter.headers');
        $baseUrl = config('openrouter.base_url');

        if (!$headers || !$baseUrl) {
            return ['content' => 'Please review the concept explanation and try again.', 'fallback' => true, 'reason' => 'OpenRouter not configured'];
        }
        
        try {
            $response = Http::withHeaders($headers)
                ->post($baseUrl . '/chat/completions', [
                    'model' => config('openrouter.model'),
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are an expert K12 educator. Generate personalized remediation content.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'max_tokens' => 500,
                ]);

            if ($response->successful()) {
                return [
                    'content' => $response->json('choices.0.message.content'),
                    'format' => 'text',
                    'generated_at' => now()->toIso8601String(),
                ];
            }
        } catch (\Exception $e) {
            Log::warning('AI remediation generation failed', ['error' => $e->getMessage()]);
        }

        return ['content' => 'Please review the concept explanation and try again.', 'fallback' => true];
    }

    protected function getAlternativePedagogies($misconception): array
    {
        return [
            ['type' => 'visual', 'reason' => 'Visual learners benefit from diagrams'],
            ['type' => 'story', 'reason' => 'Narrative context helps retention'],
            ['type' => 'socratic', 'reason' => 'Questioning reveals misunderstanding'],
            ['type' => 'simulation', 'reason' => 'Interactive practice reinforces'],
        ];
    }

    protected function getRemediationSequence($remediations): array
    {
        return $remediations->sortByDesc('effectiveness')
            ->pluck('id')
            ->toArray();
    }

    protected function buildDiagnosticPrompt(int $learnerId, string $cluster): string
    {
        return "A student has shown the following misconception pattern: {$cluster}. Analyze why they might be making this error. What prerequisite concept might be weak? What teaching approach might work better?";
    }

    protected function getDiagnosticSystemPrompt(): string
    {
        return <<<'PROMPT'
You are an expert educational psychologist specializing in diagnosing student misconceptions.
Analyze the error pattern and infer WHY the student failed.
Provide:
1. Most likely root cause
2. Which prerequisite concept might be weak
3. Which teaching approach might work better
Be concise and actionable.
PROMPT;
    }
}