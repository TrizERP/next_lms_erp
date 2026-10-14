<?php

namespace App\Domain\AI\Support;

use App\Domain\AI\Configuration\ResolvedAiConfiguration;
use Throwable;

/**
 * A model client that tries other models of the same provider when its own fails.
 *
 * A hosted model can be unavailable for reasons that have nothing to do with the question: its
 * daily quota is spent, or it is overloaded for an afternoon. For a feature that must answer
 * (reading a sentence in another language, say) it is better to ask a sibling model of the same
 * provider, with the same key, than to give up. The first model is always tried first, so the
 * fallbacks cost nothing while it works.
 *
 * Only `chat()` falls back; everything else is the first model's own. The models are named in
 * configuration (`ai.language.fallback_models`), never in a database row.
 */
final class FallbackModelClient implements ModelClient
{
    /** @param  array<int, string>  $fallbacks  model names of the same provider, in order */
    public function __construct(
        private readonly ModelClient $primary,
        private readonly array $fallbacks,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->primary->isConfigured();
    }

    public function forInstitute(int|string|null $subInstituteId): static
    {
        return new self($this->primary->forInstitute($subInstituteId), $this->fallbacks);
    }

    public function withConfiguration(ResolvedAiConfiguration $configuration): static
    {
        return new self($this->primary->withConfiguration($configuration), $this->fallbacks);
    }

    public function defaultModel(): string
    {
        return $this->primary->defaultModel();
    }

    public function chat(
        array $messages,
        ?string $model = null,
        ?int $maxTokens = null,
        ?float $temperature = null,
        bool $expectJson = false,
        ?int $timeout = null,
    ): ?string {
        $first = $this->attempt($model, $messages, $maxTokens, $temperature, $expectJson, $timeout);

        if ($first !== null || $model !== null) {
            return $first;
        }

        foreach ($this->fallbacks as $name) {
            if ($name === '' || $name === $this->primary->defaultModel()) {
                continue;
            }

            $reply = $this->attempt($name, $messages, $maxTokens, $temperature, $expectJson, $timeout);

            if ($reply !== null) {
                return $reply;
            }
        }

        return null;
    }

    public function stream(array $messages, callable $onDelta, ?string $model = null, ?int $maxTokens = null, ?float $temperature = null): ?string
    {
        return $this->primary->stream($messages, $onDelta, $model, $maxTokens, $temperature);
    }

    public function json(array $messages, ?string $model = null, int $maxTokens = 900, float $temperature = 0.0): ?array
    {
        return $this->primary->json($messages, $model, $maxTokens, $temperature);
    }

    private function attempt(?string $model, array $messages, ?int $maxTokens, ?float $temperature, bool $expectJson, ?int $timeout): ?string
    {
        try {
            $reply = $this->primary->chat($messages, $model, $maxTokens, $temperature, $expectJson, $timeout);
        } catch (Throwable) {
            return null;
        }

        return $reply !== null && trim($reply) !== '' ? $reply : null;
    }
}
