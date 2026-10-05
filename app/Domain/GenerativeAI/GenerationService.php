<?php

namespace App\Domain\GenerativeAI;

use App\Domain\AI\Configuration\AiModelClientFactory;
use App\Domain\AI\Configuration\ResolvedAiConfiguration;
use App\Domain\AI\Support\AiAuditLogger;
use App\Domain\AI\Support\ModelClient;
use App\Domain\AI\Support\SupportsImageGeneration;
use App\Domain\Templates\TemplateRegistry;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
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
    // The model comes from the provider driver; see config/ai.php `provider`.

    public function __construct(
        private readonly TemplateRegistry $templates,
        private readonly OutputValidator $validator,
        private readonly SafetyChecker $safety,
        private readonly AiAuditLogger $audit,
        // The transport and the `ai_api_keys` rotation pool, shared with lifecycle
        // planning. This service used to carry its own copy of both.
        //
        // Kept as the fallback, and as what `defaultModel()` reports when no school is
        // in scope. Live calls go through the factory below instead, which resolves the
        // provider, model and key an administrator configured for this module.
        private readonly ModelClient $client,
        private readonly AiModelClientFactory $clients,
    ) {
    }

    /**
     * The module key this service's calls are configured under.
     *
     * Generation serves both the "generate this" actions and the analyse-this-screen
     * ones, and the AI module registry lists those separately. They share a key here
     * because they share this service: a single prompt-driven call path with one set of
     * credentials. Splitting them would mean promising a configuration split that this
     * code does not make.
     */
    private const MODULE = 'generative_ai';

    /**
     * The module an image-output template resolves its provider/model/key under.
     *
     * Declared in `AiModuleRegistry` for exactly this — a school points it at an
     * image-capable Gemini model independently of whatever `generative_ai` is
     * configured to use for text.
     */
    private const IMAGE_MODULE = 'image_generation';

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

        // An image-output template is a different shape end to end — a different
        // module's configuration, a different client capability, bytes written to
        // storage instead of a validated text body — so it branches here rather than
        // threading a second return type through every step below. Nothing past this
        // point runs for it.
        if ($template->outputFormat === 'image') {
            return $this->generateImageOutput($request, $template, $rendered, $scope);
        }

        // Resolved once and passed down, so the row recorded against this request names
        // the same provider and model the call actually used. Resolving twice would let
        // a configuration saved mid-request produce an audit row that disagrees with
        // what happened.
        // The template names the PRODUCT module it belongs to, so a module that chose
        // its own generative model on its own AI Stack gets it. A shared template
        // carries no module and resolves through the central configuration as before.
        $configuration = $this->clients->configurationFor(
            self::MODULE,
            $scope->selectedInstituteId,
            $template->moduleKey,
        );

        $requestId = $this->recordRequest($request, $template, $rendered, $scope, 'running', null, $configuration);

        $this->audit->record(AiAuditLogger::GENERATION_REQUESTED, $scope, [
            'actor_type' => 'system',
            'related_type' => 'ai_generation_requests',
            'related_id' => $requestId,
            'subject_entity_key' => $request->subjectEntityKey,
            'subject_id' => $request->subjectId,
            'message' => sprintf('Generating "%s" from template %s v%d.', $request->purpose, $template->key, $template->version),
        ]);

        $startedAt = microtime(true);

        // A template that pins a model still wins. Prompts are written against a
        // specific model's behaviour, and a settings screen should not silently
        // overrule the prompt that was tested against it.
        $model = $request->modelOverride ?? $template->model ?? $configuration->model ?? $this->client->defaultModel();

        try {
            $content = $this->callModel($rendered, $template, $model, $scope, $configuration);
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
            // Same rule as the request row: the provider that was actually called, which is
            // the template's when it pins one and the resolved configuration's otherwise.
            // A literal 'openrouter' here labelled every Gemini call as OpenRouter.
            $template->provider ?? $configuration->provider,
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
            provider: $template->provider ?? $configuration->provider,
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

    /**
     * The image-output path: cache lookup, generation, storage, recording.
     *
     * Kept fully separate from the text path above rather than sharing it with a
     * branch on every step — the two produce a request row the same shape, but nothing
     * else (module, client capability, what "content" and "structured" mean, which
     * safety/validation checks even apply) is shared work worth threading through one
     * method.
     */
    private function generateImageOutput(
        GenerationRequest $request,
        $template,
        array $rendered,
        McpRequestContext $scope
    ): GenerationResult {
        // A concept's generated image doesn't change between students or between one
        // viewing and the next. Regenerating it on every page view would mean paying an
        // image-generation provider once per student per concept, forever, for a result
        // that is already sitting in ai_generation_outputs. Reused whenever the caller
        // named a stable subject (subject_entity_key + subject_id) that already has a
        // completed image output on file.
        $cached = $this->cachedImageOutput(
            $template->key,
            $request->subjectEntityKey,
            $request->subjectId,
            $scope
        );

        if ($cached !== null) {
            return GenerationResult::success(
                content: $cached['content'],
                structured: $cached['structured'],
                requestId: $cached['request_id'],
                outputId: $cached['output_id'],
                provider: $cached['provider'],
                model: $cached['model'],
                schemaValid: true,
                schemaErrors: [],
                safetyPassed: true,
                safetyReport: [],
                requiresReview: $template->requiresReview,
                latencyMs: 0,
            );
        }

        $configuration = $this->clients->configurationFor(
            self::IMAGE_MODULE,
            $scope->selectedInstituteId,
            $template->moduleKey,
        );

        $requestId = $this->recordRequest($request, $template, $rendered, $scope, 'running', null, $configuration);

        $this->audit->record(AiAuditLogger::GENERATION_REQUESTED, $scope, [
            'actor_type' => 'system',
            'related_type' => 'ai_generation_requests',
            'related_id' => $requestId,
            'subject_entity_key' => $request->subjectEntityKey,
            'subject_id' => $request->subjectId,
            'message' => sprintf('Generating an image for "%s" from template %s v%d.', $request->purpose, $template->key, $template->version),
        ]);

        // A template that pins a model still wins (see the text path's own note on
        // this). `$configuration->model` is trusted only when an administrator
        // actually chose it FOR THIS CAPABILITY (`source` is `module`/`module_platform`
        // — see ResolvedAiConfiguration) — with nothing configured, step 3-6 of
        // AiConfigurationResolver::resolve() still fills `model` in, but with the
        // *text* default for whichever provider `ai.provider.driver` names (verified
        // live: `gemini-2.5-flash`, not an image-capable model), and calling Gemini's
        // image endpoint with a text-only model is a 400, not a graceful "unconfigured".
        // The ultimate fallback is therefore an image-capable model name, never
        // `$configuration->model` on its own and never the injected default
        // `$this->client`'s `defaultModel()` (that client may not even be Gemini).
        $model = $request->modelOverride
            ?? $template->model
            ?? (in_array($configuration->source, ['module', 'module_platform'], true) ? $configuration->model : null)
            // gemini-2.5-flash-image is Google's deprecated image model (shutdown
            // 2026-10-02); gemini-3.1-flash-image is its current replacement — see
            // GeminiClient::defaultImageModel()'s own note. Kept in sync with that
            // constant rather than centralised in config/ai.php because this fallback
            // only matters when nothing at all was configured, same as that method's.
            ?? (string) config('ai.provider.gemini.image_model', 'gemini-3.1-flash-image');

        $startedAt = microtime(true);

        try {
            $client = $this->clients->fromConfiguration($configuration, $scope->selectedInstituteId);

            if (! $client instanceof SupportsImageGeneration) {
                throw new RuntimeException(sprintf(
                    '%s is configured for image generation but has no image-capable client.',
                    $configuration->provider
                ));
            }

            $prompt = trim(($rendered['system'] ?? '') . "\n\n" . $rendered['user']);
            $image = $client->generateImage($prompt, $model);

            $path = sprintf(
                'ai-generated/%s/%s.%s',
                $scope->selectedInstituteId,
                (string) Str::uuid(),
                $image->extension()
            );

            Storage::disk('public')->put($path, $image->toBinary());
            $imageUrl = Storage::disk('public')->url($path);
        } catch (Throwable $exception) {
            $this->updateRequest($requestId, 'failed', $exception->getMessage());

            return GenerationResult::failure($exception->getMessage(), $requestId);
        }

        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        // The image itself is never text-safety-scanned (SafetyChecker and
        // OutputValidator are both built for text/JSON); the caption is real generated
        // text, though, and gets the same scan any other generated text would.
        $caption = $image->caption ?? sprintf('An illustration for %s.', $request->purpose);
        $outputSafety = $this->safety->inspectOutput($caption, $template->safetyRules);
        $structured = ['image_url' => $imageUrl, 'mime_type' => $image->mimeType];

        $outputId = $this->recordOutput(
            $requestId,
            $caption,
            ['valid' => true, 'errors' => [], 'data' => null],
            $outputSafety,
            $template->provider ?? $configuration->provider,
            $model,
            $latencyMs,
            $scope,
            $structured
        );

        $this->updateRequest($requestId, $outputSafety['passed'] ? 'completed' : 'blocked_by_safety');

        if (! $outputSafety['passed']) {
            $this->audit->recordRejection('Generated image caption failed output safety checks.', $scope, [
                'related_type' => 'ai_generation_outputs',
                'related_id' => $outputId,
                'payload' => ['findings' => $outputSafety['findings']],
            ]);
        }

        return GenerationResult::success(
            content: $caption,
            structured: $structured,
            requestId: $requestId,
            outputId: $outputId,
            provider: $template->provider ?? $configuration->provider,
            model: $model,
            schemaValid: true,
            schemaErrors: [],
            safetyPassed: $outputSafety['passed'],
            safetyReport: $outputSafety['findings'],
            requiresReview: $template->requiresReview,
            latencyMs: $latencyMs
        );
    }

    /**
     * A prior completed image generation for this exact template + subject, if one
     * exists. Null when there is nothing to reuse — including when the caller named no
     * subject at all, which is deliberately not cached, since there is nothing stable
     * to key it on.
     *
     * @return array{request_id:int, output_id:int, content:string, structured:array, provider:string, model:string}|null
     */
    private function cachedImageOutput(
        string $templateKey,
        ?string $subjectEntityKey,
        int|string|null $subjectId,
        McpRequestContext $scope
    ): ?array {
        if (! Schema::hasTable('ai_generation_requests') || ! Schema::hasTable('ai_generation_outputs')) {
            return null;
        }

        if ($subjectEntityKey === null || $subjectId === null || $subjectId === '') {
            return null;
        }

        $row = DB::table('ai_generation_requests as r')
            ->join('ai_generation_outputs as o', 'o.request_id', '=', 'r.id')
            ->where('r.template_key', $templateKey)
            ->where('r.subject_entity_key', $subjectEntityKey)
            ->where('r.subject_id', $subjectId)
            ->where('r.sub_institute_id', $scope->selectedInstituteId)
            ->where('r.status', 'completed')
            ->whereNotNull('o.structured_output')
            ->orderByDesc('r.id')
            ->select(['r.id as request_id', 'o.id as output_id', 'o.content', 'o.structured_output', 'o.provider', 'o.model'])
            ->first();

        if ($row === null) {
            return null;
        }

        $structured = json_decode((string) $row->structured_output, true);

        if (! is_array($structured) || empty($structured['image_url'])) {
            return null;
        }

        return [
            'request_id' => (int) $row->request_id,
            'output_id' => (int) $row->output_id,
            'content' => (string) $row->content,
            'structured' => $structured,
            'provider' => (string) $row->provider,
            'model' => (string) $row->model,
        ];
    }

    // ---------------------------------------------------------------- internals

    /**
     * Send the rendered template to the model.
     *
     * The transport, the headers and the `ai_api_keys` rotation all live in
     * the model client now — this method's remaining job is to turn a rendered template
     * into messages and to say what the template expects back. It still throws on
     * failure, because the caller records a failed request row from the exception.
     */
    private function callModel(
        array $rendered,
        $template,
        string $model,
        McpRequestContext $scope,
        ResolvedAiConfiguration $configuration
    ): ?string
    {
        $messages = [];

        if (! empty($rendered['system'])) {
            $messages[] = ['role' => 'system', 'content' => $rendered['system']];
        }

        $messages[] = ['role' => 'user', 'content' => $rendered['user']];

        // The client this module is configured to use, already carrying the resolved
        // key and scoped to the signed-in school. With nothing configured this returns
        // the same driver, model and key the container binding always did.
        return $this->clients->fromConfiguration($configuration, $scope->selectedInstituteId)->chat(
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
        ?string $error = null,
        ?ResolvedAiConfiguration $configuration = null
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
            // Was hard-coded to 'openrouter', which mislabelled every row once the
            // estate moved to Gemini. The resolved provider is what was actually called.
            //
            // `$configuration` is null on every path that refuses before a model is
            // reached — prompt safety, grounding, a template that would not render.
            // Those rows still need a provider name, and the honest one is what the
            // call would have used, so it is resolved here rather than left undefined.
            // It was left undefined: this line read `$configuration->provider` against a
            // variable that only existed further down `generate()`, so every refusal of a
            // template with no pinned provider raised "Undefined variable $configuration"
            // and the workspace Create tab answered 500 instead of saying why it refused.
            'provider' => $template?->provider
                ?? $configuration?->provider
                ?? $this->clients->configurationFor(self::MODULE, $scope->selectedInstituteId)->provider,
            'model' => $request->modelOverride ?? $template?->model ?? $this->client->defaultModel(),
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
        McpRequestContext $scope,
        ?array $extraStructured = null
    ): ?int {
        if ($requestId === null || ! Schema::hasTable('ai_generation_outputs')) {
            return null;
        }

        // `$extraStructured` is how the image path stores its image_url/mime_type —
        // there is no JSON-schema-validated `structured` for an image template, so it
        // is never in `$validation['data']`, which stays null for that path.
        $structured = $extraStructured ?? $validation['data'];

        return (int) DB::table('ai_generation_outputs')->insertGetId([
            'request_id' => $requestId,
            'content' => $content,
            'structured_output' => $structured === null ? null : json_encode($structured),
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
