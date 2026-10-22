<?php

namespace App\Domain\AI\Configuration;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Asks a provider what it actually offers, and whether a key is actually accepted.
 *
 * WHY THIS EXISTS
 *
 * The model catalogue (`ai_models`) says what an administrator wrote down. It cannot say that
 * a model has since been retired, that this particular key is not entitled to it, that its
 * context window is 128k, or that it takes images. Every provider this platform can drive
 * publishes a model list; reading it is how "the model exists in the database" stops being
 * mistaken for "the model is usable".
 *
 * WHAT IT COSTS
 *
 * Listing models spends no generation quota on any of the supported providers. Answers are
 * cached — an hour for a list, ten minutes for a credential verdict — so a screen that asks
 * fourteen times asks the vendor once. A failure is cached briefly rather than not at all, so
 * an outage at the vendor does not turn every page view into a slow request.
 *
 * WHAT IT NEVER DOES
 *
 * It never logs, returns or caches a key: cache entries are keyed by a hash of provider and
 * key, and hold only the verdict. It never throws — an unreachable vendor is a status, because
 * "could not check" and "checked and refused" must not be confused on an admin screen.
 */
final class ProviderModelDiscovery
{
    private const TIMEOUT = 6;

    private const LIST_TTL = 3600;

    private const VERDICT_TTL = 600;

    private const FAILURE_TTL = 120;

    public function __construct(private readonly ProviderCatalog $providers)
    {
    }

    /**
     * The models this key can see at the provider, normalised.
     *
     * @return array{status:string, models:array<int, array<string, mixed>>, message:string|null}
     *         status: `ok`, `invalid` (key refused), `unreachable`, or `unsupported`.
     */
    public function models(string $provider, string $apiKey): array
    {
        $key = $this->cacheKey('models', $provider, $apiKey);
        $cached = $this->recall($key);

        if ($cached !== null) {
            return $cached;
        }

        $result = $this->fetchModels($provider, $apiKey);

        $this->remember($key, $result, $result['status'] === 'ok' ? self::LIST_TTL : self::FAILURE_TTL);

        return $result;
    }

    /**
     * Whether the provider accepts this key.
     *
     * @return array{status:string, message:string|null}
     *         status: `valid`, `invalid`, `unreachable`, or `unverified` (no way to check).
     */
    public function verify(string $provider, string $apiKey, bool $fresh = false): array
    {
        $key = $this->cacheKey('verdict', $provider, $apiKey);

        if (! $fresh) {
            $cached = $this->recall($key);

            if ($cached !== null) {
                return $cached;
            }
        }

        $result = $this->check($provider, $apiKey);

        $this->remember($key, $result, $result['status'] === 'unreachable' ? self::FAILURE_TTL : self::VERDICT_TTL);

        return $result;
    }

    /** The verdict already on hand for a key, without calling the vendor. */
    public function cachedVerdict(string $provider, string $apiKey): ?array
    {
        return $this->recall($this->cacheKey('verdict', $provider, $apiKey));
    }

    /** Drop what is cached for a key — called when it is saved, so the next read is a real one. */
    public function forget(string $provider, string $apiKey): void
    {
        foreach (['models', 'verdict'] as $kind) {
            try {
                Cache::forget($this->cacheKey($kind, $provider, $apiKey));
            } catch (Throwable) {
                // A cache outage must not block saving a configuration.
            }
        }
    }

    // ---------------------------------------------------------------------

    /** @return array{status:string, message:string|null} */
    private function check(string $provider, string $apiKey): array
    {
        try {
            if ($provider === 'openrouter') {
                // Its model list is public, so a list proves nothing about the key. This
                // endpoint answers for the key itself.
                $response = $this->http($provider, $apiKey)->get($this->base($provider) . '/key');

                if ($response->status() === 404) {
                    $response = $this->http($provider, $apiKey)->get($this->base($provider) . '/auth/key');
                }

                return $this->verdict($response);
            }

            if ($provider === 'gemini') {
                $response = $this->http($provider, $apiKey)->get($this->base($provider) . '/models', ['pageSize' => 1]);

                return $this->verdict($response);
            }

            if ($this->providers->driver($provider) === null) {
                return ['status' => 'unverified', 'message' => 'This platform has no client for the provider, so its key cannot be checked.'];
            }

            return $this->verdict($this->http($provider, $apiKey)->get($this->base($provider) . '/models'));
        } catch (ConnectionException $exception) {
            return ['status' => 'unreachable', 'message' => 'The provider could not be reached to check the key.'];
        } catch (Throwable $exception) {
            return ['status' => 'unreachable', 'message' => 'The key could not be checked: ' . class_basename($exception) . '.'];
        }
    }

    /** @return array{status:string, message:string|null} */
    private function verdict(Response $response): array
    {
        if ($response->successful()) {
            return ['status' => 'valid', 'message' => null];
        }

        $status = $response->status();
        $body = $response->body();

        // Google answers an unknown key 400 API_KEY_INVALID rather than 401.
        if (in_array($status, [401, 403], true) || ($status === 400 && str_contains($body, 'API_KEY_INVALID'))) {
            return ['status' => 'invalid', 'message' => 'The provider rejected this API key.'];
        }

        if ($status === 429) {
            // Rate-limited means the key was recognised.
            return ['status' => 'valid', 'message' => 'The key is accepted; the provider is rate-limiting it right now.'];
        }

        return ['status' => 'unreachable', 'message' => sprintf('The provider answered %d when the key was checked.', $status)];
    }

    /** @return array{status:string, models:array<int, array<string, mixed>>, message:string|null} */
    private function fetchModels(string $provider, string $apiKey): array
    {
        if ($this->providers->driver($provider) === null) {
            return ['status' => 'unsupported', 'models' => [], 'message' => 'No client for this provider.'];
        }

        try {
            return match ($provider) {
                'gemini' => $this->fetchGemini($apiKey),
                'openrouter' => $this->fetchOpenRouter($apiKey),
                default => $this->fetchOpenAiShaped($provider, $apiKey),
            };
        } catch (ConnectionException) {
            return ['status' => 'unreachable', 'models' => [], 'message' => 'The provider could not be reached.'];
        } catch (Throwable $exception) {
            return ['status' => 'unreachable', 'models' => [], 'message' => class_basename($exception)];
        }
    }

    /** @return array{status:string, models:array<int, array<string, mixed>>, message:string|null} */
    private function fetchGemini(string $apiKey): array
    {
        $models = [];
        $pageToken = null;

        for ($page = 0; $page < 6; $page++) {
            $response = $this->http('gemini', $apiKey)->get(
                $this->base('gemini') . '/models',
                array_filter(['pageSize' => 200, 'pageToken' => $pageToken])
            );

            if (! $response->successful()) {
                $verdict = $this->verdict($response);

                return [
                    'status' => $verdict['status'] === 'invalid' ? 'invalid' : 'unreachable',
                    'models' => [],
                    'message' => $verdict['message'],
                ];
            }

            foreach ((array) $response->json('models', []) as $row) {
                $id = preg_replace('#^models/#', '', (string) ($row['name'] ?? ''));

                if ($id === '') {
                    continue;
                }

                $methods = (array) ($row['supportedGenerationMethods'] ?? []);
                $embedding = in_array('embedContent', $methods, true);
                $generates = in_array('generateContent', $methods, true);
                $imageOnly = ! $generates && in_array('predict', $methods, true);
                $imageOut = ($generates || $imageOnly) && (bool) preg_match('/image|imagen/i', $id);
                $audioOnly = (bool) preg_match('/tts|native-audio|live|embedding/i', $id) && ! $embedding;

                $chat = $generates && ! $embedding && ! $imageOut && ! $audioOnly;

                $models[] = [
                    'id' => $id,
                    'chat' => $chat,
                    'embedding' => $embedding,
                    'image_output' => $imageOut,
                    'vision_input' => $generates && ! $embedding,
                    'structured_output' => $chat ? true : null,
                    'tools' => $chat ? true : null,
                    'context_tokens' => isset($row['inputTokenLimit']) ? (int) $row['inputTokenLimit'] : null,
                    'max_output_tokens' => isset($row['outputTokenLimit']) ? (int) $row['outputTokenLimit'] : null,
                ];
            }

            $pageToken = $response->json('nextPageToken');

            if (! is_string($pageToken) || $pageToken === '') {
                break;
            }
        }

        return ['status' => 'ok', 'models' => $models, 'message' => null];
    }

    /** @return array{status:string, models:array<int, array<string, mixed>>, message:string|null} */
    private function fetchOpenRouter(string $apiKey): array
    {
        $response = $this->http('openrouter', $apiKey)->get($this->base('openrouter') . '/models');

        if (! $response->successful()) {
            return ['status' => 'unreachable', 'models' => [], 'message' => sprintf('The provider answered %d.', $response->status())];
        }

        $models = [];

        foreach ((array) $response->json('data', []) as $row) {
            $id = (string) ($row['id'] ?? '');

            if ($id === '') {
                continue;
            }

            $inputs = (array) ($row['architecture']['input_modalities'] ?? []);
            $outputs = (array) ($row['architecture']['output_modalities'] ?? []);
            $params = (array) ($row['supported_parameters'] ?? []);

            $models[] = [
                'id' => $id,
                'chat' => in_array('text', $outputs, true),
                'embedding' => in_array('embeddings', $outputs, true),
                'image_output' => in_array('image', $outputs, true),
                'vision_input' => in_array('image', $inputs, true),
                'structured_output' => in_array('structured_outputs', $params, true) || in_array('response_format', $params, true),
                'tools' => in_array('tools', $params, true),
                'context_tokens' => isset($row['context_length']) ? (int) $row['context_length'] : null,
                'max_output_tokens' => isset($row['top_provider']['max_completion_tokens'])
                    ? (int) $row['top_provider']['max_completion_tokens']
                    : null,
            ];
        }

        return ['status' => 'ok', 'models' => $models, 'message' => null];
    }

    /** @return array{status:string, models:array<int, array<string, mixed>>, message:string|null} */
    private function fetchOpenAiShaped(string $provider, string $apiKey): array
    {
        $response = $this->http($provider, $apiKey)->get($this->base($provider) . '/models');

        if (! $response->successful()) {
            $verdict = $this->verdict($response);

            return [
                'status' => $verdict['status'] === 'invalid' ? 'invalid' : 'unreachable',
                'models' => [],
                'message' => $verdict['message'],
            ];
        }

        $models = [];

        foreach ((array) $response->json('data', []) as $row) {
            $id = (string) ($row['id'] ?? '');

            if ($id === '') {
                continue;
            }

            // Mistral publishes a capabilities object; the others publish ids only, so for them a
            // model's kind is read from its id and everything else stays unknown (null).
            $caps = is_array($row['capabilities'] ?? null) ? $row['capabilities'] : null;

            $embedding = (bool) preg_match('/embed/i', $id);
            $imageOut = (bool) preg_match('/dall-e|gpt-image|imagen/i', $id);
            $audio = (bool) preg_match('/whisper|tts|transcribe|audio|moderation/i', $id);

            $models[] = [
                'id' => $id,
                'chat' => $caps !== null ? (bool) ($caps['completion_chat'] ?? false) : ! ($embedding || $imageOut || $audio),
                'embedding' => $embedding,
                'image_output' => $imageOut,
                'vision_input' => $caps !== null ? (bool) ($caps['vision'] ?? false) : null,
                'structured_output' => null,
                'tools' => $caps !== null ? (bool) ($caps['function_calling'] ?? false) : null,
                'context_tokens' => isset($row['max_context_length']) ? (int) $row['max_context_length']
                    : (isset($row['context_window']) ? (int) $row['context_window'] : null),
                'max_output_tokens' => null,
            ];
        }

        return ['status' => 'ok', 'models' => $models, 'message' => null];
    }

    private function http(string $provider, string $apiKey): \Illuminate\Http\Client\PendingRequest
    {
        $headers = $provider === 'gemini'
            ? ['x-goog-api-key' => $apiKey]
            : ['Authorization' => 'Bearer ' . $apiKey];

        return Http::withHeaders($headers)->timeout(self::TIMEOUT)->connectTimeout(4);
    }

    private function base(string $provider): string
    {
        return rtrim((string) $this->providers->baseUrl($provider), '/');
    }

    private function cacheKey(string $kind, string $provider, string $apiKey): string
    {
        return 'ai.discovery.' . $kind . '.' . $provider . '.' . hash('sha256', $provider . '|' . $apiKey);
    }

    private function recall(string $key): ?array
    {
        try {
            $value = Cache::get($key);
        } catch (Throwable) {
            return null;
        }

        return is_array($value) ? $value : null;
    }

    private function remember(string $key, array $value, int $ttl): void
    {
        try {
            Cache::put($key, $value, $ttl);
        } catch (Throwable) {
            // The verdict is still returned; it just is not reused.
        }
    }
}
