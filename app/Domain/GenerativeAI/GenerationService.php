<?php

namespace App\Domain\GenerativeAI;

use App\Domain\AI\Support\AiAuditLogger;
use App\Domain\AI\Support\OpenRouterClient;
use App\Domain\Templates\TemplateRegistry;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Facades\DB;
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

    public function __construct(
        private readonly TemplateRegistry $templates,
        private readonly OutputValidator $validator,
        private readonly SafetyChecker $safety,
        private readonly AiAuditLogger $audit,
        // The transport and the `ai_api_keys` rotation pool, shared with lifecycle
        // planning. This service used to carry its own copy of both.
        private readonly OpenRouterClient $client,
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
     * Send the rendered template to the model.
     *
     * The transport, the headers and the `ai_api_keys` rotation all live in
     * OpenRouterClient now — this method's remaining job is to turn a rendered template
     * into messages and to say what the template expects back. It still throws on
     * failure, because the caller records a failed request row from the exception.
     */
    private function callModel(array $rendered, $template, string $model): ?string
    {
        $messages = [];

        if (! empty($rendered['system'])) {
            $messages[] = ['role' => 'system', 'content' => $rendered['system']];
        }

        $messages[] = ['role' => 'user', 'content' => $rendered['user']];

        return $this->client->chat(
            $messages,
            $model,
            maxTokens: $template->maxTokens ?? null,
            temperature: $template->temperature,
            expectJson: $template->outputFormat === 'json',
        );
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
