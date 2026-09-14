<?php

namespace App\Domain\AI\Support;

use App\Domain\AI\Configuration\ProviderCatalog;
use App\Domain\AI\Configuration\ResolvedAiConfiguration;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * One client for every provider that speaks OpenAI's chat-completions shape.
 *
 * OpenAI, DeepSeek, Groq and Mistral accept the same request body at the same path and
 * answer with the same envelope; the only thing that differs is the base URL and the
 * key. So they get one client parameterised by provider rather than four near-copies —
 * the four-copies version is exactly how this codebase ended up with two different
 * GeminiClients that resolve keys differently.
 *
 * WHY OpenRouterClient IS NOT FOLDED INTO THIS
 *
 * OpenRouter is also OpenAI-compatible and would fit here. It keeps its own client
 * because it is a driver a live path already runs on, it sends two headers this one
 * does not (`HTTP-Referer`, `X-Title`, which OpenRouter uses for attribution), and
 * replacing a working driver to save a file is a change with risk and no user-visible
 * benefit. If it is ever consolidated, that should be its own change with its own test.
 *
 * CONFIGURATION COMES FROM OUTSIDE
 *
 * Unlike the two older clients, this one is never expected to find its own credential
 * in the pool. It is built by `AiModelClientFactory` from an already-resolved
 * configuration — provider, model and key decided by `AiConfigurationResolver` — so
 * there is one precedence chain in the estate rather than one per client.
 */
class OpenAiCompatibleClient implements ModelClient
{
    private const DEFAULT_MAX_TOKENS = 1466;

    private const DEFAULT_TIMEOUT = 45;

    private string $provider = 'openai';

    private int|string|null $subInstituteId = null;

    private ?ResolvedAiConfiguration $configuration = null;

    public function __construct(private readonly ProviderCatalog $providers)
    {
    }

    /** A copy bound to one provider — `openai`, `deepseek`, `groq`, `mistral`. */
    public function forProvider(string $provider): static
    {
        $clone = clone $this;
        $clone->provider = $provider;

        return $clone;
    }

    public function forInstitute(int|string|null $subInstituteId): static
    {
        $clone = clone $this;
        $clone->subInstituteId = $subInstituteId;

        return $clone;
    }

    public function withConfiguration(ResolvedAiConfiguration $configuration): static
    {
        $clone = clone $this;
        $clone->configuration = $configuration;
        $clone->provider = $configuration->provider;

        return $clone;
    }

    public function isConfigured(): bool
    {
        return $this->resolveApiKey() !== null;
    }

    public function defaultModel(): string
    {
        return $this->configuration?->model
            ?? $this->providers->defaultModel($this->provider)
            ?? 'gpt-4o-mini';
    }

    public function chat(
        array $messages,
        ?string $model = null,
        ?int $maxTokens = null,
        ?float $temperature = null,
        bool $expectJson = false,
        ?int $timeout = null,
    ): ?string {
        $key = $this->requireKey();

        $response = Http::withHeaders($this->headers($key))
            ->timeout($timeout ?? self::DEFAULT_TIMEOUT)
            ->post($this->endpoint(), array_filter([
                'model' => $model ?? $this->defaultModel(),
                'messages' => $messages,
                'max_tokens' => $maxTokens ?? $this->maxTokens(),
                'temperature' => $temperature,
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

    public function stream(
        array $messages,
        callable $onDelta,
        ?string $model = null,
        ?int $maxTokens = null,
        ?float $temperature = null,
    ): ?string {
        $key = $this->requireKey();

        try {
            $response = Http::withHeaders($this->headers($key))
                ->timeout(self::DEFAULT_TIMEOUT)
                ->withOptions(['stream' => true])
                ->post($this->endpoint(), array_filter([
                    'model' => $model ?? $this->defaultModel(),
                    'messages' => $messages,
                    'max_tokens' => $maxTokens ?? $this->maxTokens(),
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

                    $text = json_decode($payload, true)['choices'][0]['delta']['content'] ?? null;

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
            // Streaming unavailable: the interface requires an answer in one piece
            // rather than a failure.
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

    /** @return array<string, mixed>|null */
    private function decode(string $content): ?array
    {
        $decoded = json_decode(trim($content), true);

        if (is_array($decoded)) {
            return $decoded;
        }

        // The prose-around-JSON habit. One salvage attempt is cheaper than a
        // discarded turn.
        if (preg_match('/\{.*\}/s', $content, $matches)) {
            $decoded = json_decode($matches[0], true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /** @return array<string, string> */
    private function headers(string $key): array
    {
        return [
            'Authorization' => 'Bearer ' . $key,
            'Content-Type' => 'application/json',
        ];
    }

    private function endpoint(): string
    {
        $base = rtrim((string) $this->providers->baseUrl($this->provider), '/');

        return $base . '/chat/completions';
    }

    private function maxTokens(): int
    {
        return $this->configuration?->maxOutputTokens
            ?? (int) config("ai.provider.{$this->provider}.max_output_tokens")
            ?: self::DEFAULT_MAX_TOKENS;
    }

    private function requireKey(): string
    {
        $key = $this->resolveApiKey();

        if ($key === null) {
            throw new RuntimeException(sprintf(
                'No usable API key is configured for %s.',
                $this->providers->label($this->provider)
            ));
        }

        return $key;
    }

    /**
     * The resolved configuration's key, else this provider's own pool row.
     *
     * The pool branch exists so a client built by hand — a console command, a test —
     * still finds a credential the same way the older clients do.
     */
    private function resolveApiKey(): ?string
    {
        if ($this->configuration?->hasKey()) {
            return $this->configuration->apiKey;
        }

        $key = app(ProviderKeyResolver::class)->resolve(
            $this->providers->apiType($this->provider),
            $this->subInstituteId,
            $this->providers->envKey($this->provider),
        );

        return $key['api_key'] ?? null;
    }
}
