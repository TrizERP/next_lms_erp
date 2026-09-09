<?php

namespace App\Domain\AI\Support;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * The estate's one way of talking to a model.
 *
 * Key resolution follows what the platform already does: the `ai_api_keys` pool through
 * `getAIKey()`, with an env fallback so a key-table outage does not take the feature
 * down with it. Both callers — governed generation and lifecycle planning — come through
 * here, so a change to the provider, the headers or the key pool happens once.
 *
 * Two entry points, because the two callers want opposite things from a failure:
 *
 *   - `chat()` **throws**. Generation records a failed request row against the audit
 *     trail and surfaces the reason to the user, so it needs the exception.
 *   - `json()` **returns null**. Planning has a deterministic fallback, and a planner
 *     that threw would turn a degraded model into a failed turn — which is exactly the
 *     outcome the fallback exists to prevent.
 */
class OpenRouterClient implements ModelClient
{
    private const ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';

    private const DEFAULT_MAX_TOKENS = 1466;

    private const DEFAULT_TIMEOUT = 45;

    public function isConfigured(): bool
    {
        return $this->resolveApiKey() !== null;
    }

    /**
     * Send a chat completion and return the content.
     *
     * @param  array<int, array{role:string, content:string}>  $messages
     * @param  int|null  $maxTokens  Falls back to the key's own daily limit, then to a default.
     *
     * @throws RuntimeException when no key is configured or the provider refuses.
     */
    public function defaultModel(): string
    {
        return (string) config('ai.provider.openrouter.model', 'deepseek/deepseek-chat');
    }

    public function chat(
        array $messages,
        ?string $model = null,
        ?int $maxTokens = null,
        ?float $temperature = null,
        bool $expectJson = false,
        ?int $timeout = null,
    ): ?string {
        $key = $this->resolveApiKey();

        if ($key === null) {
            throw new RuntimeException('No usable AI API key is configured.');
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $key['api_key'],
            'Content-Type' => 'application/json',
            'HTTP-Referer' => config('app.url', 'https://nextlms.in'),
            'X-Title' => config('app.name', 'Next LMS ERP'),
        ])
            ->timeout($timeout ?? self::DEFAULT_TIMEOUT)
            ->post(self::ENDPOINT, array_filter([
                'model' => $model ?? $this->defaultModel(),
                'messages' => $messages,
                'max_tokens' => $maxTokens ?? $key['api_limit'] ?? self::DEFAULT_MAX_TOKENS,
                'temperature' => $temperature,
                // Asking for JSON explicitly materially reduces the "prose wrapped
                // around JSON" failure a validator would otherwise have to recover from.
                'response_format' => $expectJson ? ['type' => 'json_object'] : null,
            ], static fn ($value) => $value !== null));

        if (! $response->successful()) {
            throw new RuntimeException(sprintf(
                'The AI provider returned %d: %s',
                $response->status(),
                mb_substr($response->body(), 0, 300)
            ));
        }

        return $response->json('choices.0.message.content');
    }

    /**
     * Ask for a JSON object back, and return it decoded.
     *
     * Never throws — see the class docblock. A null means "no usable answer", and the
     * caller is expected to have somewhere else to go.
     *
     * @param  array<int, array{role:string, content:string}>  $messages
     * @return array<string, mixed>|null
     */
    /**
     * Stream a completion, calling `$onDelta` with each fragment.
     *
     * OpenAI-shaped SSE: `data: {json}` with the text at `choices.0.delta.content`, and
     * a literal `data: [DONE]` to finish. Kept in step with GeminiClient so the rollback
     * driver streams too — a provider switch that silently stopped streaming would look
     * like a frontend bug.
     *
     * @param  array<int, array{role:string, content:string}>  $messages
     * @param  callable(string): void  $onDelta
     */
    public function stream(
        array $messages,
        callable $onDelta,
        ?string $model = null,
        ?int $maxTokens = null,
        ?float $temperature = null,
    ): ?string {
        $key = $this->resolveApiKey();

        if ($key === null) {
            throw new RuntimeException('No usable AI API key is configured.');
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $key['api_key'],
                'Content-Type' => 'application/json',
                'HTTP-Referer' => config('app.url', 'https://nextlms.in'),
                'X-Title' => config('app.name', 'Next LMS ERP'),
            ])
                ->timeout(self::DEFAULT_TIMEOUT)
                ->withOptions(['stream' => true])
                ->post(self::ENDPOINT, array_filter([
                    'model' => $model ?? $this->defaultModel(),
                    'messages' => $messages,
                    'max_tokens' => $maxTokens ?? $key['api_limit'] ?? self::DEFAULT_MAX_TOKENS,
                    'temperature' => $temperature,
                    'stream' => true,
                ], static fn ($value) => $value !== null));

            if (! $response->successful()) {
                throw new RuntimeException(sprintf(
                    'The AI provider returned %d: %s',
                    $response->status(),
                    mb_substr($response->body(), 0, 300)
                ));
            }

            $body = $response->toPsrResponse()->getBody();
            $buffer = '';
            $full = '';

            while (! $body->eof()) {
                $buffer .= $body->read(8192);

                // Only whole lines are consumed; a partial event carries forward.
                while (($newline = strpos($buffer, "\n")) !== false) {
                    $line = trim(substr($buffer, 0, $newline));
                    $buffer = substr($buffer, $newline + 1);

                    if ($line === '' || ! str_starts_with($line, 'data:')) {
                        continue;
                    }

                    $payload = trim(substr($line, 5));

                    if ($payload === '' || $payload === '[DONE]') {
                        continue;
                    }

                    $decoded = json_decode($payload, true);
                    $text = $decoded['choices'][0]['delta']['content'] ?? null;

                    if (is_string($text) && $text !== '') {
                        $full .= $text;
                        $onDelta($text);
                    }
                }
            }

            return $full;
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (Throwable) {
            $text = $this->chat($messages, $model, $maxTokens, $temperature);

            if (is_string($text) && $text !== '') {
                $onDelta($text);
            }

            return $text;
        }
    }

    public function json(array $messages, ?string $model = null, int $maxTokens = 900, float $temperature = 0.0): ?array
    {
        try {
            $content = $this->chat(
                $messages,
                $model,
                maxTokens: $maxTokens,
                temperature: $temperature,
                expectJson: true,
                timeout: 30,
            );
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        return is_string($content) ? $this->decode($content) : null;
    }

    /**
     * Decode the model's reply, tolerating the prose-around-JSON habit.
     *
     * `response_format: json_object` makes this rare rather than impossible, and one
     * salvage attempt is cheaper than a discarded turn.
     *
     * @return array<string, mixed>|null
     */
    private function decode(string $content): ?array
    {
        $decoded = json_decode(trim($content), true);

        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $content, $matches)) {
            $decoded = json_decode($matches[0], true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * @return array{api_key:string, api_limit:int|null, id:int|string|null}|null
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
}
