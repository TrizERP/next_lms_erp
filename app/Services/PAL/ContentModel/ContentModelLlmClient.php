<?php

namespace App\Services\PAL\ContentModel;

use App\Domain\AI\Configuration\AiConfigurationException;
use App\Domain\AI\Configuration\AiConfigurationResolver;
use App\Domain\AI\Configuration\AiModelClientFactory;
use App\Domain\AI\Support\FallbackChainModelClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The Content Model's only outbound LLM call.
 *
 * Provider, model, key and fallbacks come from the platform's centralised AI
 * configuration (module `pal_content_model`, via AiModelClientFactory). Nothing is
 * read from the environment or from `ai_api_keys` directly, so what the administrator
 * saves in AI Providers is what is called.
 *
 * Every call is JSON-in / JSON-out and every response is cached in
 * `pal_cm_enrichment` against a fingerprint of the exact input the model saw —
 * so a changed source paragraph invalidates its own answer, and a page reload
 * never re-bills the provider.
 *
 * The model may only ever PROPOSE. Callers stamp what comes back as
 * tagged_by = 'ai', quality_status = 'draft'; nothing here can write an
 * approved status (CONTENT LAW C5).
 */
class ContentModelLlmClient
{
    /** The school whose keys and bindings apply; null means platform keys only. */
    protected int|string|null $subInstituteId = null;

    /** Per-request memo of the module's provider chain. */
    protected ?FallbackChainModelClient $chain = null;

    /** The configuration that answered the last call, if any. */
    protected ?\App\Domain\AI\Configuration\ResolvedAiConfiguration $answered = null;

    /**
     * A copy of this client scoped to one school, so that school's own configuration applies.
     * An immutable copy, because the container's instance is shared between requests.
     */
    public function forInstitute(int|string|null $subInstituteId): static
    {
        $copy = clone $this;
        $copy->subInstituteId = $subInstituteId;
        $copy->chain = null;
        $copy->answered = null;

        return $copy;
    }

    public function enabled(): bool
    {
        return (bool) config('pal_content_model.llm.enabled', true) && $this->chain()->isConfigured();
    }

    /** Why the LLM is unavailable, for an honest message in the UI. */
    public function unavailableReason(): ?string
    {
        if (! config('pal_content_model.llm.enabled', true)) {
            return 'AI enrichment is switched off for this deployment (PAL_CONTENT_MODEL_LLM).';
        }
        if (! $this->chain()->isConfigured()) {
            return app(AiConfigurationResolver::class)
                ->explainEmpty('pal_content_model', $this->subInstituteId)
                ->getMessage();
        }

        return null;
    }

    public function model(): string
    {
        $override = config('pal_content_model.llm.model');
        if (! empty($override)) {
            return (string) $override;
        }

        return (string) ($this->answered?->model ?? $this->chain()->defaultModel());
    }

    /** Which provider answered (or would answer first), for display next to a generated proposal. */
    public function providerName(): ?string
    {
        return $this->answered?->provider ?? ($this->chain()->configurations()[0]->provider ?? null);
    }

    protected function chain(): FallbackChainModelClient
    {
        return $this->chain ??= app(AiModelClientFactory::class)->for('pal_content_model', $this->subInstituteId);
    }

    /**
     * Ask for one JSON object.
     *
     * @return array{ok:bool, data?:array, error?:string, model?:string, usage?:array}
     */
    public function json(string $system, string $user): array
    {
        $chain = $this->chain();
        if (! $chain->isConfigured()) {
            return ['ok' => false, 'error' => $this->unavailableReason() ?? 'No AI provider key configured.'];
        }

        $timeout = (int) config('pal_content_model.llm.timeout_seconds', 180);
        $maxTokens = (int) config('pal_content_model.llm.max_output_tokens', 0);
        $override = config('pal_content_model.llm.model');

        try {
            // Guard against PHP's own limit firing before the HTTP timeout.
            @set_time_limit($timeout + 60);

            $content = $chain->chat(
                [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
                ! empty($override) ? (string) $override : null,
                $maxTokens > 0 ? $maxTokens : null,
                (float) config('pal_content_model.llm.temperature', 0.2),
                true,
                $timeout,
            );

            $this->answered = $chain->lastUsed();

            if (! is_string($content) || trim($content) === '') {
                return ['ok' => false, 'error' => 'The AI provider returned an empty message.'];
            }

            $decoded = $this->decodeJson($content);
            if ($decoded === null) {
                return ['ok' => false, 'error' => 'The AI provider did not return parseable JSON.'];
            }

            return [
                'ok' => true,
                'data' => $decoded,
                'model' => $this->answered?->model ?? $this->model(),
                // The shared client does not surface token counts; the usage ledger records them.
                'usage' => [],
            ];
        } catch (AiConfigurationException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        } catch (Throwable $e) {
            Log::error('PAL Content Model LLM call failed: ' . $e->getMessage());

            return ['ok' => false, 'error' => 'AI call failed: ' . $e->getMessage()];
        }
    }

    // ── Cache ────────────────────────────────────────────────────────────────

    public function fingerprint(array $input): string
    {
        return hash('sha256', json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    }

    /** A cached answer for this exact input, or null. */
    public function cached(string $nodeKey, string $kind, ?string $variant, string $fingerprint, int $tenant): ?array
    {
        $days = (int) config('pal_content_model.llm.cache_days', 30);

        $row = DB::table('pal_cm_enrichment')
            ->where('node_key', $nodeKey)
            ->where('kind', $kind)
            ->where('variant', $variant)
            ->where('fingerprint', $fingerprint)
            ->where('sub_institute_id', $tenant)
            ->when($days > 0, fn ($q) => $q->where('created_at', '>=', now()->subDays($days)))
            ->orderByDesc('id')
            ->first();

        if ($row === null) {
            return null;
        }

        $payload = json_decode((string) $row->payload, true);

        return [
            'payload' => is_array($payload) ? $payload : [],
            'confidence' => $row->confidence !== null ? (float) $row->confidence : null,
            'model' => $row->model,
            'cached' => true,
            'generated_at' => $row->created_at,
        ];
    }

    public function remember(
        string $nodeKey,
        string $kind,
        ?string $variant,
        string $fingerprint,
        int $tenant,
        array $payload,
        ?float $confidence,
        ?string $model,
        array $usage,
        ?int $userId
    ): void {
        try {
            DB::table('pal_cm_enrichment')->updateOrInsert(
                [
                    'node_key' => $nodeKey,
                    'kind' => $kind,
                    'variant' => $variant,
                    'fingerprint' => $fingerprint,
                    'sub_institute_id' => $tenant,
                ],
                [
                    'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                    'confidence' => $confidence,
                    'model' => $model,
                    'input_tokens' => (int) ($usage['prompt_tokens'] ?? 0),
                    'output_tokens' => (int) ($usage['completion_tokens'] ?? 0),
                    'requested_by' => $userId,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        } catch (Throwable $e) {
            // A cache write failure must not fail the request the user made.
            Log::warning('PAL Content Model enrichment cache write failed: ' . $e->getMessage());
        }
    }

    /** Test seam — drops the memoised provider. */
    public function flushProvider(): void
    {
        $this->chain = null;
        $this->answered = null;
    }

    /**
     * Providers wrap JSON in prose or fences often enough that a bare
     * json_decode is not good enough. Strips the fence, then brace-matches the
     * first complete object so trailing commentary is ignored.
     */
    protected function decodeJson(string $raw): ?array
    {
        $text = trim($raw);

        if (preg_match('/^```[a-zA-Z0-9_-]*\s*([\s\S]*?)\s*```/', $text, $m)) {
            $text = trim($m[1]);
        }

        $direct = json_decode($text, true);
        if (is_array($direct)) {
            return $direct;
        }

        $start = strpos($text, '{');
        if ($start === false) {
            return null;
        }

        $depth = 0;
        $inString = false;
        $escaped = false;
        $length = strlen($text);

        for ($i = $start; $i < $length; $i++) {
            $char = $text[$i];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === '"') {
                    $inString = false;
                }
                continue;
            }

            if ($char === '"') {
                $inString = true;
            } elseif ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    $candidate = json_decode(substr($text, $start, $i - $start + 1), true);

                    return is_array($candidate) ? $candidate : null;
                }
            }
        }

        return null;
    }
}
