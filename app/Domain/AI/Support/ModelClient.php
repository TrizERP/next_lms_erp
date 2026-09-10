<?php

namespace App\Domain\AI\Support;

use App\Domain\AI\Configuration\ResolvedAiConfiguration;
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
     * A copy of this client scoped to one school.
     *
     * Credential lookup is tenant-aware — a school's own `ai_api_keys` row beats the
     * platform key — but nothing in `chat()`'s signature carries who is asking, and a
     * client resolved from the container is shared. So the scope is applied by taking
     * an immutable copy rather than by setting state on the shared instance, which
     * would leak one request's tenant into the next.
     *
     * Callers that hold a McpRequestContext should pass `$scope->selectedInstituteId`.
     * Passing null (or not calling this at all) resolves platform keys only, which is
     * the behaviour every existing call site already had.
     */
    public function forInstitute(int|string|null $subInstituteId): static;

    /**
     * A copy of this client bound to an already-resolved configuration.
     *
     * `forInstitute()` says *who* is asking and lets the client find its own key.
     * This says *what was decided* — provider, model and credential, resolved once by
     * `AiConfigurationResolver` from the module's saved row. It exists because the
     * per-module bindings an administrator saves cannot be discovered by a client that
     * only knows a provider: two modules can be on the same provider with different
     * keys and different models, and only the resolver can tell them apart.
     *
     * Immutable copy for the same reason `forInstitute()` is: the container's client is
     * shared, and setting state on it would leak one module's key into the next call.
     *
     * Not calling this is the behaviour every pre-existing call site has — the client
     * resolves its own key from the pool exactly as before.
     */
    public function withConfiguration(ResolvedAiConfiguration $configuration): static;

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
