<?php

namespace App\Domain\AI\Configuration;

/**
 * One provider, model and credential a module can be sent to.
 *
 * A module resolves to an ORDERED LIST of these — the first is what it calls, the rest are what
 * it falls back to — see `AiConfigurationResolver::resolveChain()`.
 *
 * `priority` is the position the administrator gave it (1 = first), or null when unranked.
 * `inheritedFrom` names the module whose configuration this one is using, when it has none of
 * its own.
 */
final class ResolvedAiConfiguration
{
    public function __construct(
        public readonly string $provider,
        public readonly ?string $model,
        public readonly ?string $apiKey,
        public readonly string $source,
        public readonly int|string|null $keyId = null,
        public readonly string $scope = 'config',
        public readonly ?int $maxOutputTokens = null,
        public readonly ?int $priority = null,
        public readonly ?string $inheritedFrom = null,
        public readonly ?string $module = null,
    ) {
    }

    public function hasKey(): bool
    {
        return $this->apiKey !== null && trim($this->apiKey) !== '';
    }

    /** The same configuration attributed to a module — used so usage rows name the caller. */
    public function forModule(?string $module): self
    {
        return new self(
            $this->provider,
            $this->model,
            $this->apiKey,
            $this->source,
            $this->keyId,
            $this->scope,
            $this->maxOutputTokens,
            $this->priority,
            $this->inheritedFrom,
            $module,
        );
    }

    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'model' => $this->model,
            'source' => $this->source,
            'scope' => $this->scope,
            'key_id' => $this->keyId,
            'has_key' => $this->hasKey(),
            'priority' => $this->priority,
            'inherited_from' => $this->inheritedFrom,
        ];
    }
}
