<?php

namespace App\Services\PAL\ContentModel;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The Content Model's only outbound LLM call.
 *
 * Reuses the provider, base URL and key-resolution chain that
 * QuestionGenerationService already uses (config/deepseek.php → the
 * `ai_api_keys` table → env), so this module does not introduce a second AI
 * configuration surface that could drift out of sync or be rotated separately.
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
    /** Per-request memo of the resolved provider. */
    protected ?array $resolved = null;

    protected bool $resolvedAttempted = false;

    public function enabled(): bool
    {
        return (bool) config('pal_content_model.llm.enabled', true) && $this->provider() !== null;
    }

    /** Why the LLM is unavailable, for an honest message in the UI. */
    public function unavailableReason(): ?string
    {
        if (! config('pal_content_model.llm.enabled', true)) {
            return 'AI enrichment is switched off for this deployment (PAL_CONTENT_MODEL_LLM).';
        }
        if ($this->provider() === null) {
            $types = array_column(config('pal_content_model.llm.providers', []), 'api_type');

            return 'No AI provider key is configured. Add an enabled `ai_api_keys` row for one of: '
                . implode(', ', $types) . ' — or set the matching environment key.';
        }

        return null;
    }

    public function model(): string
    {
        $override = config('pal_content_model.llm.model');
        if (! empty($override)) {
            return (string) $override;
        }

        return (string) ($this->provider()['model'] ?? config('deepseek.model', 'deepseek-chat'));
    }

    /** Which provider answered, for display next to a generated proposal. */
    public function providerName(): ?string
    {
        return $this->provider()['name'] ?? null;
    }

    /**
     * Ask for one JSON object.
     *
     * @return array{ok:bool, data?:array, error?:string, model?:string, usage?:array}
     */
    public function json(string $system, string $user): array
    {
        $provider = $this->provider();
        if ($provider === null) {
            return ['ok' => false, 'error' => $this->unavailableReason() ?? 'No AI provider key configured.'];
        }
        $apiKey = $provider['api_key'];

        $timeout = (int) config('pal_content_model.llm.timeout_seconds', 180);
        $body = [
            'model' => $this->model(),
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
            'temperature' => (float) config('pal_content_model.llm.temperature', 0.2),
            'stream' => false,
        ];

        $maxTokens = (int) config('pal_content_model.llm.max_output_tokens', 0);
        if ($maxTokens > 0) {
            $body['max_tokens'] = $maxTokens;
        }

        try {
            // Guard against PHP's own limit firing before the HTTP timeout.
            @set_time_limit($timeout + 60);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout($timeout)
                ->connectTimeout(20)
                ->post(rtrim((string) $provider['base_url'], '/') . '/chat/completions', $body);

            if (! $response->successful()) {
                return ['ok' => false, 'error' => 'AI request failed (' . $response->status() . '): ' . mb_substr($response->body(), 0, 300)];
            }

            $json = $response->json();
            $content = $json['choices'][0]['message']['content'] ?? null;
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
                'model' => $json['model'] ?? $this->model(),
                'usage' => $json['usage'] ?? [],
            ];
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

    // ── Internals ────────────────────────────────────────────────────────────

    /**
     * The first provider in the configured chain whose key resolves.
     *
     * Keys come from the `ai_api_keys` table (status = 1) first, matching the
     * getAIKey() convention the rest of the ERP uses, then from the environment.
     * `env_key_alt` covers the case where a key for one provider is stored under
     * another provider's variable name — it is only accepted when the value
     * actually carries that provider's key prefix, so a genuine OpenAI key in
     * OPENAI_API_KEY is never sent to OpenRouter.
     *
     * @return array{name:string, api_key:string, base_url:string, model:string}|null
     */
    protected function provider(): ?array
    {
        if ($this->resolvedAttempted) {
            return $this->resolved;
        }
        $this->resolvedAttempted = true;

        foreach (config('pal_content_model.llm.providers', []) as $provider) {
            $key = $this->keyFor($provider);
            if ($key === null) {
                continue;
            }

            return $this->resolved = [
                'name' => (string) ($provider['name'] ?? 'provider'),
                'api_key' => $key,
                'base_url' => (string) ($provider['base_url'] ?? config('deepseek.base_url', 'https://api.deepseek.com')),
                'model' => (string) ($provider['model'] ?? config('deepseek.model', 'deepseek-chat')),
            ];
        }

        return $this->resolved = null;
    }

    protected function keyFor(array $provider): ?string
    {
        if (! empty($provider['api_type'])) {
            try {
                $row = DB::table('ai_api_keys')
                    ->where('api_type', $provider['api_type'])
                    ->where('status', 1)
                    ->first();
                if ($row !== null && ! empty($row->api_key) && $row->api_key !== '-') {
                    return trim((string) $row->api_key, " \t\n\r\0\x0B'\"");
                }
            } catch (Throwable) {
                // No DB or no table — fall through to the environment.
            }
        }

        foreach (['env_key', 'env_key_alt'] as $slot) {
            $name = $provider[$slot] ?? null;
            if ($name === null) {
                continue;
            }

            $value = trim((string) (env($name) ?? ''), " \t\n\r\0\x0B'\"");
            if ($value === '' || $value === '-') {
                continue;
            }

            // An alternate slot must prove the key belongs to this provider.
            $prefix = $provider['env_key_prefix'] ?? null;
            if ($slot === 'env_key_alt' && $prefix !== null && ! str_starts_with($value, $prefix)) {
                continue;
            }

            return $value;
        }

        return null;
    }

    /** Test seam — drops the memoised provider. */
    public function flushProvider(): void
    {
        $this->resolved = null;
        $this->resolvedAttempted = false;
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
