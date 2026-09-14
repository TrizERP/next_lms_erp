<?php

namespace App\Domain\AI\Configuration;

/**
 * What one AI module should call, and where that answer came from.
 *
 * `source` is not decoration. When an administrator saves "Conversational AI uses
 * OpenRouter" and the next answer still comes back from Gemini, the only useful
 * question is which rule won — and without this field the answer is a debugging
 * session. It is carried on the object, logged with the call, and shown on the admin
 * screen beside each module.
 */
final class ResolvedAiConfiguration
{
    public function __construct(
        public readonly string $provider,
        public readonly ?string $model,
        public readonly ?string $apiKey,
        /** `module` | `module_platform` | `pool` | `pool_platform` | `env` | `config` */
        public readonly string $source,
        /** The `ai_api_keys` row this resolved through, when it resolved through one. */
        public readonly int|string|null $keyId = null,
        /** `institute` | `platform` | `env` | `config` */
        public readonly string $scope = 'config',
        public readonly ?int $maxOutputTokens = null,
    ) {
    }

    public function hasKey(): bool
    {
        return $this->apiKey !== null && trim($this->apiKey) !== '';
    }

    /**
     * The configuration without its credential, for logs, audit rows and API responses.
     *
     * There is no method on this class that returns the key inside an array. That is
     * deliberate: `toArray()` is what gets reached for when something needs to be
     * logged or serialised, and a key that can ride along in it will eventually ride
     * into a log file. Callers that need the credential read `$config->apiKey`
     * explicitly, which is a line that stands out in review.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'model' => $this->model,
            'source' => $this->source,
            'scope' => $this->scope,
            'key_id' => $this->keyId,
            'has_key' => $this->hasKey(),
        ];
    }
}
