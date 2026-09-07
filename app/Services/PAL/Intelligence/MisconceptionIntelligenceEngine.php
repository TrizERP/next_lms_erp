<?php

namespace App\Services\PAL\Intelligence;

use App\Models\PAL\LearnerMisconception;
use App\Models\PAL\Misconception;
use App\Models\PAL\MisconceptionCorrective;
use App\Models\PAL\MisconceptionLibrary;
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
        // TWO REGISTRIES, and this used to read only the smaller one.
        //
        //   pal_misconception_library  3,659 rows -- the curated set, keyed by
        //                              chapter_ref_id / concept_ref_id
        //   pal_misconceptions             2 rows -- only what analyze() has
        //                              detected at runtime
        //
        // Querying pal_misconceptions alone meant "Misconception analysis by
        // concept" was empty for every concept in the estate bar two, which is
        // why the panel looked inert.
        //
        // IDS ARE UNIQUE ONLY WITHIN A REGISTRY, so every row carries `source`
        // and getRemediation() must be given it back -- library id 19 and
        // runtime id 19 are different misconceptions.
        $runtime = Misconception::where('concept_id', $conceptId)
            ->get()
            ->map(fn ($m) => [
                'source' => 'runtime',
                'id' => $m->id,
                'pattern' => $m->pattern,
                'category' => $m->category,
                'root_cause' => $m->root_cause,
                'frequency' => (int) $m->frequency,
                'severity' => null,
                'quality_status' => null,
            ]);

        // The library is tagged against chapter ids in this estate; a caller
        // passing a concept id should still match, so both keys are tried.
        // Deliberately NOT filtered by scopeServable(): only 1 of the 3,659
        // rows is `approved`, so a servable filter would empty this panel
        // again. quality_status is returned instead so the UI can mark drafts
        // rather than silently hide them.
        $library = MisconceptionLibrary::query()
            ->where(function ($q) use ($conceptId) {
                $q->where('chapter_ref_id', $conceptId)
                  ->orWhere('concept_ref_id', $conceptId);
            })
            ->orderByDesc('prevalence_rate')
            ->orderByDesc('detection_count')
            ->limit(200)
            ->get()
            ->map(fn ($m) => [
                'source' => 'library',
                'id' => $m->id,
                'tag' => $m->tag,
                'pattern' => $m->error_pattern,
                'category' => $m->subject,
                'root_cause' => $m->description,
                'corrective_action' => $m->corrective_action,
                // detection_count is how often this library entry has actually
                // fired, which is the library's analogue of frequency.
                'frequency' => (int) ($m->detection_count ?? 0),
                'prevalence_rate' => $m->prevalence_rate,
                'severity' => $m->severity,
                'teacher_confirmed' => (bool) $m->teacher_confirmed,
                'quality_status' => $m->quality_status,
            ]);

        return $runtime->concat($library)->values()->all();
    }

    /**
     * Get targeted remediation
     * @param int $learnerId
     * @param int $misconceptionId
     * @return array
     */
    public function getRemediation(int $learnerId, int $misconceptionId, string $source = 'runtime'): array
    {
        // cluster() returns rows from two registries whose ids overlap, so the
        // caller has to say which one this id came from. Defaulting to
        // 'runtime' preserves the old single-registry behaviour for existing
        // callers.
        if ($source === 'library') {
            return $this->getLibraryRemediation($learnerId, $misconceptionId);
        }

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
            'source' => 'runtime',
            'pre_defined_remediations' => $remediations,
            'alternative_pedagogies' => $this->getAlternativePedagogies($misconception),
            'recommended_sequence' => $this->getRemediationSequence($remediations),
        ];
    }

    /**
     * Remediation for a curated library entry, served from
     * pal_misconception_corrective (7,304 rows) -- the CORRECTS_WITH content
     * the library was built to carry.
     *
     * Like cluster(), this does not apply scopeServable(): 2 of the 7,304
     * corrective rows are `approved`, so filtering would return nothing for
     * essentially every misconception. quality_status rides along on each row
     * instead so the caller can label drafts.
     */
    protected function getLibraryRemediation(int $learnerId, int $misconceptionId): array
    {
        $entry = MisconceptionLibrary::find($misconceptionId);

        if (!$entry) {
            return ['error' => 'Misconception not found'];
        }

        $correctives = MisconceptionCorrective::where('misconception_id', $misconceptionId)
            ->orderByDesc('resolution_rate')
            ->orderBy('priority_level')
            ->limit(20)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'title' => $c->title,
                'body' => $c->body,
                'format' => $c->format,
                'h5p_type' => $c->h5p_type,
                'media_url' => $c->media_url,
                'language' => $c->language,
                'estimated_duration_minutes' => $c->estimated_duration_minutes,
                // Effectiveness is measured, not assumed: null until this
                // corrective has actually been served to someone.
                'resolution_rate' => $c->served_count > 0 ? $c->resolution_rate : null,
                'served_count' => (int) $c->served_count,
                'quality_status' => $c->quality_status,
            ]);

        return [
            'source' => 'library',
            'misconception' => [
                'id' => $entry->id,
                'tag' => $entry->tag,
                'pattern' => $entry->error_pattern,
                'description' => $entry->description,
                'corrective_action' => $entry->corrective_action,
                'severity' => $entry->severity,
                'quality_status' => $entry->quality_status,
            ],
            'pre_defined_remediations' => $correctives,
            'attempts_by_learner' => LearnerMisconception::where('learner_id', $learnerId)
                ->where('misconception_id', $misconceptionId)
                ->count(),
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