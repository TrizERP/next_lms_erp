<?php

namespace App\Domain\AI\Configuration;

/**
 * The providers this platform can be configured to call, and which client drives each.
 *
 * WHAT "SUPPORTED" MEANS HERE
 *
 * A provider is listed with the class that actually talks to it. Three shapes exist:
 *
 *   - `gemini`     — Google's own REST shape, driven by `GeminiClient`.
 *   - `openrouter` — OpenAI-compatible, driven by the existing `OpenRouterClient`.
 *     It keeps its own client rather than moving to the generic one below, because it
 *     is the driver a live path already runs on and a rewrite buys nothing.
 *   - everything else OpenAI-compatible (`openai`, `deepseek`, `groq`, `mistral`) —
 *     driven by `OpenAiCompatibleClient`, which is the same wire format with a
 *     different base URL.
 *
 * `anthropic` is listed with no driver on purpose. Its Messages API is a different
 * shape — system prompt outside the message list, its own content blocks — so an
 * OpenAI-compatible client cannot call it, and pretending otherwise would put a
 * provider in a dropdown that fails on first use. It appears with `driver: null`, the
 * screen shows it as unavailable, and saving against it is refused.
 *
 * `api_type` IS THE BRIDGE TO EXISTING ROWS
 *
 * `ai_api_keys.api_type` on this estate holds two different conventions — `gemini` for
 * one provider and `OPENROUTER_API_KEY` for another, because they were added at
 * different times by different code. Rather than rewrite four live rows, each entry
 * below declares the `api_type` its credentials are tagged with, read from
 * `config/ai.php` where that file already knows. New rows written by the admin screen
 * use the same value, so old and new rows resolve through one lookup.
 */
final class ProviderCatalog
{
    /**
     * @var array<string, array{label:string, driver:class-string|null, base_url:string|null, docs:string}>
     */
    private const PROVIDERS = [
        'gemini' => [
            'label' => 'Google Gemini',
            'driver' => \App\Domain\AI\Support\GeminiClient::class,
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'docs' => 'https://aistudio.google.com/apikey',
        ],
        'openrouter' => [
            'label' => 'OpenRouter',
            'driver' => \App\Domain\AI\Support\OpenRouterClient::class,
            'base_url' => 'https://openrouter.ai/api/v1',
            'docs' => 'https://openrouter.ai/keys',
        ],
        'openai' => [
            'label' => 'OpenAI',
            'driver' => \App\Domain\AI\Support\OpenAiCompatibleClient::class,
            'base_url' => 'https://api.openai.com/v1',
            'docs' => 'https://platform.openai.com/api-keys',
        ],
        'deepseek' => [
            'label' => 'DeepSeek',
            'driver' => \App\Domain\AI\Support\OpenAiCompatibleClient::class,
            'base_url' => 'https://api.deepseek.com/v1',
            'docs' => 'https://platform.deepseek.com/api_keys',
        ],
        'groq' => [
            'label' => 'Groq',
            'driver' => \App\Domain\AI\Support\OpenAiCompatibleClient::class,
            'base_url' => 'https://api.groq.com/openai/v1',
            'docs' => 'https://console.groq.com/keys',
        ],
        'mistral' => [
            'label' => 'Mistral',
            'driver' => \App\Domain\AI\Support\OpenAiCompatibleClient::class,
            'base_url' => 'https://api.mistral.ai/v1',
            'docs' => 'https://console.mistral.ai/api-keys',
        ],
        'anthropic' => [
            'label' => 'Anthropic Claude',
            // No driver: the Messages API is not OpenAI-compatible. See the class note.
            'driver' => null,
            'base_url' => 'https://api.anthropic.com/v1',
            'docs' => 'https://console.anthropic.com/settings/keys',
        ],
    ];

    /** @return array<int, string> */
    public function keys(): array
    {
        return array_keys(self::PROVIDERS);
    }

    public function exists(string $provider): bool
    {
        return array_key_exists($provider, self::PROVIDERS);
    }

    /** Whether a provider can actually be called, as opposed to merely listed. */
    public function isDriveable(string $provider): bool
    {
        return ($this->driver($provider)) !== null;
    }

    /** @return class-string|null */
    public function driver(string $provider): ?string
    {
        return self::PROVIDERS[$provider]['driver'] ?? null;
    }

    public function label(string $provider): string
    {
        return self::PROVIDERS[$provider]['label'] ?? $provider;
    }

    public function baseUrl(string $provider): ?string
    {
        // A provider already configured in config/ai.php keeps whatever base URL that
        // file resolves — an estate may point a driver at a proxy — and the constant
        // above is only the fallback for providers that file has never heard of.
        $configured = trim((string) config("ai.provider.{$provider}.base_url", ''));

        return $configured !== '' ? $configured : (self::PROVIDERS[$provider]['base_url'] ?? null);
    }

    /**
     * The `ai_api_keys.api_type` value credentials for this provider are tagged with.
     *
     * Falls back to the provider key itself, which is the convention every row written
     * by the admin screen follows.
     */
    public function apiType(string $provider): string
    {
        $configured = trim((string) config("ai.provider.{$provider}.api_type", ''));

        return $configured !== '' ? $configured : $provider;
    }

    /** The env-configured key for this provider, used only as a last-resort fallback. */
    public function envKey(string $provider): ?string
    {
        $value = trim((string) config("ai.provider.{$provider}.api_key", ''));

        return $value !== '' ? $value : null;
    }

    /**
     * The provider's default model, as `config/ai.php` resolves it.
     *
     * Only used when neither the saved configuration nor the catalogue names one, so
     * that a provider with no catalogue row still has somewhere to go.
     */
    public function defaultModel(string $provider): ?string
    {
        $value = trim((string) config("ai.provider.{$provider}.model", ''));

        return $value !== '' ? $value : null;
    }

    /**
     * Every provider, shaped for the admin screen's dropdown.
     *
     * @return array<int, array{key:string, label:string, driveable:bool, api_type:string, docs:string}>
     */
    public function all(): array
    {
        $out = [];

        foreach (self::PROVIDERS as $key => $provider) {
            $out[] = [
                'key' => $key,
                'label' => $provider['label'],
                'driveable' => $provider['driver'] !== null,
                'api_type' => $this->apiType($key),
                'docs' => $provider['docs'],
            ];
        }

        return $out;
    }
}
