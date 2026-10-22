<?php

namespace App\Domain\AI\Support;

use App\Domain\AI\Configuration\AiConfigurationException;
use App\Domain\AI\Configuration\ResolvedAiConfiguration;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;
use RuntimeException;
use Throwable;

/**
 * A module's client: its providers, in the order the administrator ranked them.
 *
 * The first provider answers. When it FAILS — quota spent, overloaded, key refused, model
 * retired, unreachable — the next ranked provider is tried with the same request, and so on.
 * With one provider configured this is a pass-through, so a module that has not set up
 * fallbacks behaves exactly as it did.
 *
 * WHAT COUNTS AS A FAILURE WORTH FALLING BACK ON
 *
 * Only failures a different provider could plausibly fix: 429 (quota or rate limit), 5xx
 * (overload or outage), 401/403 (this key is refused), 404 (this model is gone), a request that
 * timed out or never connected, and a leg with no usable credential. A 400 is the request being
 * malformed — the next provider would receive the same bytes — so it is surfaced, not retried
 * elsewhere. The one exception is Google's invalid-key answer, which arrives as a 400
 * (`API_KEY_INVALID`) and is a credential problem.
 *
 * AN EMPTY ANSWER IS A FAILURE TOO
 *
 * A leg that returns nothing usable (null or blank) is passed over, because a module cannot do
 * anything with silence and the next provider might.
 *
 * THE MODEL OVERRIDE BELONGS TO THE FIRST LEG
 *
 * A caller that names a model named it for the provider it was configured against. Sending
 * `gemini-flash-latest` to OpenRouter is a guaranteed 404, so an explicit model applies to the
 * first leg only and every fallback leg uses its own configured model.
 *
 * WHAT HAPPENED IS RECORDED
 *
 * Each leg is wrapped by `UsageTrackingModelClient`, so every attempt — including the failed
 * ones — is a row naming the real provider, model and module. `lastUsed()` tells the caller
 * which configuration actually answered, for the rows it writes itself.
 */
final class FallbackChainModelClient implements ModelClient, SupportsVisionAnalysis, SupportsEmbedding, SupportsImageGeneration
{
    private ?ResolvedAiConfiguration $used = null;

    /** @var array<int, array{provider:string, model:string|null, reason:string}> */
    private array $failures = [];

    /**
     * @param  array<int, array{config: ResolvedAiConfiguration, client: ModelClient}>  $legs
     * @param  Closure(ResolvedAiConfiguration): ModelClient|null  $legBuilder  Builds one leg, so
     *         `withConfiguration()` can re-target the chain.
     */
    public function __construct(
        private readonly array $legs,
        private readonly ?string $module = null,
        private readonly ?Closure $legBuilder = null,
        private readonly int|string|null $subInstituteId = null,
        private readonly ?AiConfigurationException $unavailable = null,
    ) {
    }

    /** @return array<int, ResolvedAiConfiguration> */
    public function configurations(): array
    {
        return array_map(fn (array $leg) => $leg['config'], $this->legs);
    }

    /** The configuration that actually answered the last call, or null if none has. */
    public function lastUsed(): ?ResolvedAiConfiguration
    {
        return $this->used;
    }

    /** Providers that failed on the last call, in order — what the fallback skipped past. @return array<int, array<string, mixed>> */
    public function lastFailures(): array
    {
        return $this->failures;
    }

    public function isConfigured(): bool
    {
        foreach ($this->legs as $leg) {
            if ($leg['client']->isConfigured()) {
                return true;
            }
        }

        return false;
    }

    public function forInstitute(int|string|null $subInstituteId): static
    {
        return new self(
            array_map(fn (array $leg) => ['config' => $leg['config'], 'client' => $leg['client']->forInstitute($subInstituteId)], $this->legs),
            $this->module,
            $this->legBuilder,
            $subInstituteId,
            $this->unavailable,
        );
    }

    public function withConfiguration(ResolvedAiConfiguration $configuration): static
    {
        if ($this->legBuilder === null) {
            throw new LogicException('This client was not built with a way to re-target it.');
        }

        // Re-targeting names one provider explicitly, so the fallbacks no longer apply.
        return new self(
            [['config' => $configuration, 'client' => ($this->legBuilder)($configuration)]],
            $this->module,
            $this->legBuilder,
            $this->subInstituteId,
        );
    }

    public function defaultModel(): string
    {
        return isset($this->legs[0]) ? $this->legs[0]['client']->defaultModel() : '';
    }

    public function chat(
        array $messages,
        ?string $model = null,
        ?int $maxTokens = null,
        ?float $temperature = null,
        bool $expectJson = false,
        ?int $timeout = null,
    ): ?string {
        return $this->run(
            fn (ModelClient $client, ?string $leg) => $client->chat($messages, $leg, $maxTokens, $temperature, $expectJson, $timeout),
            $model,
            static fn ($result) => is_string($result) && trim($result) !== '',
        );
    }

    public function stream(
        array $messages,
        callable $onDelta,
        ?string $model = null,
        ?int $maxTokens = null,
        ?float $temperature = null,
    ): ?string {
        $emitted = false;
        $wrapped = function (string $delta) use ($onDelta, &$emitted): void {
            $emitted = true;
            $onDelta($delta);
        };

        return $this->run(
            fn (ModelClient $client, ?string $leg) => $client->stream($messages, $wrapped, $leg, $maxTokens, $temperature),
            $model,
            static fn ($result) => is_string($result) && trim($result) !== '',
            // Once text has reached the caller the answer cannot be taken back and re-sent.
            canRetry: function () use (&$emitted): bool {
                return ! $emitted;
            },
        );
    }

    public function json(
        array $messages,
        ?string $model = null,
        int $maxTokens = 900,
        float $temperature = 0.0,
    ): ?array {
        return $this->run(
            fn (ModelClient $client, ?string $leg) => $client->json($messages, $leg, $maxTokens, $temperature),
            $model,
            static fn ($result) => is_array($result),
        );
    }

    public function analyzeImage(
        string $prompt,
        string $imageBytes,
        string $mimeType,
        ?string $model = null,
        ?int $timeout = null,
    ): ?string {
        return $this->run(
            fn (ModelClient $client, ?string $leg) => $client->analyzeImage($prompt, $imageBytes, $mimeType, $leg, $timeout),
            $model,
            static fn ($result) => is_string($result) && trim($result) !== '',
            only: SupportsVisionAnalysis::class,
        );
    }

    public function embed(string $text, ?string $model = null, ?int $timeout = null): ?array
    {
        return $this->run(
            fn (ModelClient $client, ?string $leg) => $client->embed($text, $leg, $timeout),
            $model,
            static fn ($result) => is_array($result) && $result !== [],
            only: SupportsEmbedding::class,
        );
    }

    public function generateImage(string $prompt, ?string $model = null, ?int $timeout = null): ImageGenerationResult
    {
        $result = $this->run(
            fn (ModelClient $client, ?string $leg) => $client->generateImage($prompt, $leg, $timeout),
            $model,
            static fn ($result) => $result instanceof ImageGenerationResult,
            only: SupportsImageGeneration::class,
        );

        if (! $result instanceof ImageGenerationResult) {
            throw new RuntimeException('No configured provider returned an image.');
        }

        return $result;
    }

    // ---------------------------------------------------------------------

    /**
     * Try each leg in order until one gives a usable answer.
     *
     * @param  callable(ModelClient, ?string): mixed  $call
     * @param  callable(mixed): bool  $usable
     * @param  (callable(): bool)|null  $canRetry
     * @param  class-string|null  $only  Skip legs whose client lacks this capability interface.
     */
    private function run(callable $call, ?string $model, callable $usable, ?callable $canRetry = null, ?string $only = null): mixed
    {
        $this->used = null;
        $this->failures = [];

        // A module with nothing usable configured: say why, once, rather than failing somewhere deeper.
        if ($this->legs === [] && $this->unavailable !== null) {
            throw $this->unavailable;
        }

        $last = null;
        $first = true;
        $tried = 0;

        foreach ($this->legs as $leg) {
            if ($only !== null && ! $leg['client'] instanceof $only) {
                continue;
            }

            $tried++;
            $config = $leg['config'];
            $startedAt = microtime(true);
            $named = $first ? $model : null;

            try {
                $result = $call($leg['client'], $named);
            } catch (Throwable $exception) {
                $this->record($config, $named, 'error', $startedAt, $this->reasonFor($exception));
                $last = $exception;
                $this->failures[] = [
                    'provider' => $config->provider,
                    'model' => $config->model,
                    'reason' => $this->reasonFor($exception),
                ];

                if (! self::shouldFallBack($exception) || ($canRetry !== null && ! $canRetry())) {
                    throw $exception;
                }

                Log::warning('AI provider failed; trying the next configured provider.', [
                    'module' => $this->module,
                    'provider' => $config->provider,
                    'model' => $config->model,
                    'reason' => $this->reasonFor($exception),
                ]);

                $first = false;

                continue;
            }

            $first = false;

            if ($usable($result)) {
                $this->record($config, $named, 'ok', $startedAt);
                $this->used = $config;

                return $result;
            }

            $this->record($config, $named, 'error', $startedAt, 'empty answer');
            $this->failures[] = ['provider' => $config->provider, 'model' => $config->model, 'reason' => 'empty answer'];

            if ($canRetry !== null && ! $canRetry()) {
                return $result;
            }
        }

        if ($tried === 0) {
            throw new AiConfigurationException(
                'None of the providers configured for this module can do what this call needs.',
                $this->module,
                'no_capable_provider'
            );
        }

        if ($last !== null) {
            throw $last;
        }

        // Every leg answered, and none of the answers was usable.
        return null;
    }

    /**
     * Whether a different provider could plausibly answer what this one could not.
     */
    public static function shouldFallBack(Throwable $exception): bool
    {
        if ($exception instanceof AiConfigurationException || $exception instanceof ConnectionException) {
            return true;
        }

        $status = null;
        $message = $exception->getMessage();

        if ($exception instanceof AiProviderException) {
            $status = $exception->status;
        } elseif ($exception instanceof RuntimeException && preg_match('/returned (\d{3})/', $message, $m) === 1) {
            // The older clients report the status in words rather than as a typed exception.
            $status = (int) $m[1];
        }

        if ($status !== null) {
            if ($status === 400) {
                return str_contains($message, 'API_KEY_INVALID');
            }

            return in_array($status, [401, 402, 403, 404, 408, 429], true) || $status >= 500;
        }

        // A leg that never had a key, or a transport that never connected.
        return $exception instanceof RuntimeException
            && (str_contains($message, 'No usable') || str_contains($message, 'timed out') || str_contains($message, 'Could not resolve'));
    }

    /**
     * One usage row per attempt — the failed ones too — naming the provider and model that were
     * really called and the module that asked.
     *
     * The interface returns completion text only, never a token count, so tokens and cost stay
     * null: recording a guess would be worse than recording none.
     */
    private function record(ResolvedAiConfiguration $config, ?string $named, string $outcome, float $startedAt, ?string $error = null): void
    {
        // Logging must never be the reason a real call fails or looks slower than it was.
        try {
            DB::table('ai_usage_events')->insert([
                'product' => 'k12',
                'ai_module' => $this->module,
                'provider' => $config->provider,
                'model' => $named ?? $config->model,
                'source' => $config->source,
                'outcome' => $outcome,
                'error' => $error !== null ? mb_substr($error, 0, 500) : null,
                'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'sub_institute_id' => is_numeric($this->subInstituteId) ? (int) $this->subInstituteId : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::channel('daily')->warning('AI usage logging failed', ['error' => $e->getMessage()]);
        }
    }

    private function reasonFor(Throwable $exception): string
    {
        if ($exception instanceof AiProviderException) {
            return sprintf('HTTP %d%s', $exception->status, $exception->providerStatus ? ' ' . $exception->providerStatus : '');
        }

        return mb_substr(preg_replace('/\s+/', ' ', $exception->getMessage()) ?? '', 0, 160);
    }
}
