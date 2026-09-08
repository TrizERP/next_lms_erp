<?php

namespace App\Domain\AI\Support;

use RuntimeException;

/**
 * One way of talking to a model, whoever the provider is.
 *
 * Extracted when the estate moved from OpenRouter to Gemini. The two callers want
 * opposite things from a failure, and that asymmetry is the contract rather than an
 * accident of the first implementation:
 *
 *   - `chat()` **throws**. Generation records a failed request row against the audit
 *     trail and surfaces the reason to the user, so it needs the exception.
 *   - `json()` **returns null**. Planning has a deterministic fallback, and a planner
 *     that threw would turn a degraded model into a failed turn — which is exactly the
 *     outcome the fallback exists to prevent.
 *
 * Callers depend on this interface, not on a provider, so a rollback is one config value
 * (`ai.provider.driver`) rather than an edit to every call site.
 */
interface ModelClient
{
    public function isConfigured(): bool;

    /**
     * The provider-native model this client uses when a caller does not name one.
     *
     * Call sites should prefer this over hard-coding a model string: a model name is
     * provider-specific, and `deepseek/deepseek-chat` means nothing to Gemini.
     */
    public function defaultModel(): string;

    /**
     * Send a chat completion and return the content.
     *
     * @param  array<int, array{role:string, content:string}>  $messages
     *
     * @throws RuntimeException when no key is configured or the provider refuses.
     */
    public function chat(
        array $messages,
        ?string $model = null,
        ?int $maxTokens = null,
        ?float $temperature = null,
        bool $expectJson = false,
        ?int $timeout = null,
    ): ?string;

    /**
     * Stream a completion, calling `$onDelta` with each text fragment as it arrives.
     *
     * Returns the complete text, so a caller that also needs the whole answer — to
     * compose it into a reply, or to record the turn — does not have to accumulate the
     * deltas itself.
     *
     * Implementations must fall back to a single non-streaming call rather than fail if
     * streaming is unavailable: a client that cannot stream should still get an answer,
     * arriving in one piece. `$onDelta` is then called once with the whole thing.
     *
     * @param  array<int, array{role:string, content:string}>  $messages
     * @param  callable(string): void  $onDelta
     *
     * @throws RuntimeException when no key is configured or the provider refuses.
     */
    public function stream(
        array $messages,
        callable $onDelta,
        ?string $model = null,
        ?int $maxTokens = null,
        ?float $temperature = null,
    ): ?string;

    /**
     * Ask for a JSON object back, and return it decoded.
     *
     * Never throws. A null means "no usable answer", and the caller is expected to have
     * somewhere else to go.
     *
     * @param  array<int, array{role:string, content:string}>  $messages
     * @return array<string, mixed>|null
     */
    public function json(
        array $messages,
        ?string $model = null,
        int $maxTokens = 900,
        float $temperature = 0.0,
    ): ?array;
}
