<?php

namespace App\Domain\GenerativeAI;

use App\Domain\AI\Support\AiAuditLogger;
use App\Domain\Templates\TemplateRegistry;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * One generation layer for the whole platform.
 *
 * The estate had four LLM call sites with four different shapes: OpenAIService
 * (OpenAI + DeepSeek, with key rotation), AIOrchestrationService (OpenRouter),
 * QuestionGenerationService, and GammaService — none of them validating output,
 * none recording provenance, and none marking their text as generated. This service
 * is the one path new work uses. The existing call sites keep working untouched;
 * consolidating them is a later cleanup, not a prerequisite.
 *
 * It reuses the estate's own key pool (`ai_api_keys` via the getAIKey helper, with
 * per-key limits in `ai_daily_used_api`) rather than introducing a second one — that
 * rotation logic is the genuinely valuable part of OpenAIService and it is preserved.
 *
 * Every call writes a request row and an output row. That is what makes generated
 * content auditable, and what lets a piece of text on screen be traced to the
 * template version, model and case that produced it.
 */
class GenerationService
{
    private const DEFAULT_MODEL = 'deepseek/deepseek-chat';

    private const DEFAULT_MAX_TOKENS = 1466;

    private const DEFAULT_TIMEOUT = 45;

    public function __construct(
        private readonly TemplateRegistry $templates,
        private readonly OutputValidator $validator,
        private readonly SafetyChecker $safety,
        private readonly AiAuditLogger $audit,
    ) {
    }

    public function generate(GenerationRequest $request, McpRequestContext $scope): GenerationResult
    {
        $template = $this->templates->find($request->templateKey, $scope->selectedInstituteId);

        if (! $template) {
            return GenerationResult::failure(
                sprintf('No published template "%s".', $request->templateKey)
            );
        }

        // Inbound safety: interpolated data must not carry instructions.
        $promptSafety = $this->safety->inspectPrompt($request->variables, $template->safetyRules);

        if (! $promptSafety['passed']) {
            $requestId = $this->recordRequest($request, $template, null, $scope, 'blocked_by_safety');

            $this->audit->recordRejection('Generation blocked by prompt safety checks.', $scope, [
                'related_type' => 'ai_generation_requests',
                'related_id' => $requestId,
                'payload' => ['findings' => $promptSafety['findings']],
            ]);

            return GenerationResult::failure(
                'This request could not be generated safely.',
                $requestId,
                $promptSafety['findings']
            );
        }

        // Grounding, before anything is rendered or sent.
        //
        // A template that declares which variables carry its source data must actually
        // receive some. Without this, a summary template handed an empty page produces a
        // confident sentence about an empty catalogue — a statement about the prompt,
        // read by a teacher as a statement about the school. Refusing is both cheaper
        // and truthful, and it matches the rule the explanation layer already applies.
        $grounding = GroundingCheck::inspect($template, $request->variables);

        if ($grounding['required'] && ! $grounding['grounded']) {
            $requestId = $this->recordRequest($request, $template, null, $scope, 'refused_no_grounding');

            $this->audit->recordRejection('Generation refused: no grounding data.', $scope, [
                'related_type' => 'ai_generation_requests',
                'related_id' => $requestId,
                'payload' => [
                    'template_key' => $template->key,
                    'expected' => $grounding['variables'],
                    'empty' => $grounding['empty'],
                ],
            ]);

            return GenerationResult::failure(
                GroundingCheck::refusalMessage($template, $grounding),
                $requestId,
                ['missing_grounding' => $grounding['empty']]
            );
        }

        try {
            $rendered = $this->templates->render($template, $request->variables);
        } catch (Throwable $exception) {
            $requestId = $this->recordRequest($request, $template, null, $scope, 'failed', $exception->getMessage());

            return GenerationResult::failure($exception->getMessage(), $requestId);
        }

        $requestId = $this->recordRequest($request, $template, $rendered, $scope, 'running');

        $this->audit->record(AiAuditLogger::GENERATION_REQUESTED, $scope, [
            'actor_type' => 'system',
            'related_type' => 'ai_generation_requests',
            'related_id' => $requestId,
            'subject_entity_key' => $request->subjectEntityKey,
            'subject_id' => $request->subjectId,
            'message' => sprintf('Generating "%s" from template %s v%d.', $request->purpose, $template->key, $template->version),
        ]);

        $startedAt = microtime(true);
        $model = $request->modelOverride ?? $template->model ?? self::DEFAULT_MODEL;

        try {
            $content = $this->callModel($rendered, $template, $model);
        } catch (Throwable $exception) {
            $this->updateRequest($requestId, 'failed', $exception->getMessage());

            return GenerationResult::failure($exception->getMessage(), $requestId);
        }

        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        if ($content === null || trim($content) === '') {
            $this->updateRequest($requestId, 'failed', 'The model returned no content.');

            return GenerationResult::failure('The model returned no content.', $requestId);
        }

        $outputSafety = $this->safety->inspectOutput($content, $template->safetyRules);
        $validation = $this->validator->validate($content, $template->outputSchema, $template->outputFormat);

        $outputId = $this->recordOutput(
            $requestId,
            $content,
            $validation,
            $outputSafety,
            $template->provider ?? 'openrouter',
            $model,
            $latencyMs,
            $scope
        );

        $this->updateRequest(
            $requestId,
            $outputSafety['passed'] ? ($validation['valid'] ? 'completed' : 'invalid_output') : 'blocked_by_safety'
        );

        if (! $outputSafety['passed']) {
            $this->audit->recordRejection('Generated content failed output safety checks.', $scope, [
                'related_type' => 'ai_generation_outputs',
                'related_id' => $outputId,
                'payload' => ['findings' => $outputSafety['findings']],
            ]);
        }

        return GenerationResult::success(
            content: $content,
            structured: $validation['data'],
            requestId: $requestId,
            outputId: $outputId,
            provider: $template->provider ?? 'openrouter',
            model: $model,
            schemaValid: $validation['valid'],
            schemaErrors: $validation['errors'],
            safetyPassed: $outputSafety['passed'],
            safetyReport: $outputSafety['findings'],
            requiresReview: $template->requiresReview,
            latencyMs: $latencyMs
        );
    }

    /**
     * Mark a generated output as reviewed by a person.
     *
     * This is the only route by which generated content can later be considered for
     * verification, and even then only if its template declared `allow_as_evidence`.
     */
    public function review(
        int $outputId,
        string $status,
        McpRequestContext $scope,
        ?string $note = null
    ): bool {
        if (! Schema::hasTable('ai_generation_outputs') || $scope->userId <= 0) {
            return false;
        }

        $updated = DB::table('ai_generation_outputs')
            ->where('id', $outputId)
            ->where('sub_institute_id', $scope->selectedInstituteId)
            ->update([
                'reviewed' => true,
                'reviewed_by' => $scope->userId,
                'reviewed_at' => now(),
                'review_status' => in_array($status, ['accepted', 'edited', 'rejected'], true) ? $status : 'accepted',
                'updated_at' => now(),
            ]);

        if ($updated > 0) {
            $this->audit->record('generation.reviewed', $scope, [
                'actor_type' => 'user',
                'related_type' => 'ai_generation_outputs',
                'related_id' => $outputId,
                'message' => $note ?? sprintf('Generated content marked %s.', $status),
            ]);
        }

        return $updated > 0;
    }

    // ---------------------------------------------------------------- internals

    /**
     * Calls OpenRouter using the estate's existing key pool.
     */
    private function callModel(array $rendered, $template, string $model): ?string
    {
        $key = $this->resolveApiKey();

        if ($key === null) {
            throw new \RuntimeException('No usable AI API key is configured.');
        }

        $messages = [];

        if (! empty($rendered['system'])) {
            $messages[] = ['role' => 'system', 'content' => $rendered['system']];
        }

        $messages[] = ['role' => 'user', 'content' => $rendered['user']];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $key['api_key'],
            'Content-Type' => 'application/json',
            'HTTP-Referer' => config('app.url', 'https://nextlms.in'),
            'X-Title' => config('app.name', 'Next LMS ERP'),
        ])
            ->timeout(self::DEFAULT_TIMEOUT)
            ->post('https://openrouter.ai/api/v1/chat/completions', array_filter([
                'model' => $model,
                'messages' => $messages,
                'max_tokens' => $template->maxTokens ?? $key['api_limit'] ?? self::DEFAULT_MAX_TOKENS,
                'temperature' => $template->temperature,
                // Ask for JSON explicitly when the template expects it; it materially
                // reduces the "prose wrapped around JSON" failure the validator has
                // to recover from.
                'response_format' => $template->outputFormat === 'json'
                    ? ['type' => 'json_object']
                    : null,
            ], fn ($value) => $value !== null));

        if (! $response->successful()) {
            throw new \RuntimeException(sprintf(
                'The AI provider returned %d: %s',
                $response->status(),
                mb_substr($response->body(), 0, 300)
            ));
        }

        return $response->json('choices.0.message.content');
    }

    /**
     * Reuses `getAIKey()` (ai_api_keys + daily limits) with an env fallback, matching
     * what OpenAIService::generateContent already does.
     *
     * @return array{api_key:string, api_limit:int|null, id:int|null}|null
     */
    private function resolveApiKey(): ?array
    {
        if (function_exists('getAIKey')) {
            try {
                $key = getAIKey('OPENROUTER_API_KEY', 1);

                if ($key !== '-' && ! empty($key->api_key)) {
                    return [
                        'api_key' => trim((string) $key->api_key),
                        'api_limit' => isset($key->api_limit) ? (int) $key->api_limit : null,
                        'id' => $key->id ?? null,
                    ];
                }
            } catch (Throwable) {
                // Fall through to env — a key-table outage should not stop generation.
            }
        }

        $envKey = config('openrouter.api_key') ?: env('OPENROUTER_API_KEY');

        if (empty($envKey)) {
            return null;
        }

        return [
            'api_key' => trim((string) $envKey, " \t\n\r\0\x0B'\""),
            'api_limit' => self::DEFAULT_MAX_TOKENS,
            'id' => null,
        ];
    }

    private function recordRequest(
        GenerationRequest $request,
        $template,
        ?array $rendered,
        McpRequestContext $scope,
        string $status,
        ?string $error = null
    ): ?int {
        if (! Schema::hasTable('ai_generation_requests')) {
            return null;
        }

        $resolved = $rendered
            ? trim(($rendered['system'] ?? '') . "\n\n" . ($rendered['user'] ?? ''))
            : null;

        return (int) DB::table('ai_generation_requests')->insertGetId([
            'request_reference' => $this->nextReference(),
            'template_key' => $template?->key,
            'template_id' => $template?->id,
            'purpose' => mb_substr($request->purpose, 0, 120),
            'domain' => $request->domain,
            'variables' => json_encode($request->variables),
            'context' => json_encode($request->context),
            'resolved_prompt' => $resolved,
            'prompt_hash' => $resolved ? hash('sha256', $resolved) : null,
            'provider' => $template?->provider ?? 'openrouter',
            'model' => $request->modelOverride ?? $template?->model ?? self::DEFAULT_MODEL,
            'subject_entity_key' => $request->subjectEntityKey,
            'subject_id' => is_numeric($request->subjectId) ? (int) $request->subjectId : null,
            'case_id' => $request->caseId,
            'agent_run_id' => $request->agentRunId,
            'workflow_run_id' => $request->workflowRunId,
            'status' => $status,
            'error_message' => $error,
            'requested_by' => $scope->userId,
            'requested_by_role' => $scope->role,
            'sub_institute_id' => $scope->selectedInstituteId,
            'client_id' => $scope->clientId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function updateRequest(?int $requestId, string $status, ?string $error = null): void
    {
        if ($requestId === null || ! Schema::hasTable('ai_generation_requests')) {
            return;
        }

        DB::table('ai_generation_requests')->where('id', $requestId)->update(array_filter([
            'status' => $status,
            'error_message' => $error,
            'updated_at' => now(),
        ], fn ($value) => $value !== null));
    }

    private function recordOutput(
        ?int $requestId,
        string $content,
        array $validation,
        array $safety,
        string $provider,
        string $model,
        int $latencyMs,
        McpRequestContext $scope
    ): ?int {
        if ($requestId === null || ! Schema::hasTable('ai_generation_outputs')) {
            return null;
        }

        return (int) DB::table('ai_generation_outputs')->insertGetId([
            'request_id' => $requestId,
            'content' => $content,
            'structured_output' => $validation['data'] === null ? null : json_encode($validation['data']),
            // Never anything but true.
            'is_generated' => true,
            'schema_valid' => $validation['valid'],
            'schema_errors' => $validation['errors'] === [] ? null : json_encode($validation['errors']),
            'safety_passed' => $safety['passed'],
            'safety_report' => $safety['findings'] === [] ? null : json_encode($safety['findings']),
            'provider' => $provider,
            'model' => $model,
            'latency_ms' => $latencyMs,
            'sub_institute_id' => $scope->selectedInstituteId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function nextReference(): string
    {
        $prefix = sprintf('GEN-%d-', now()->year);

        $last = DB::table('ai_generation_requests')
            ->where('request_reference', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('request_reference');

        $sequence = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }
}
