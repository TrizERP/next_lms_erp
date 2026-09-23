<?php

namespace App\Domain\Eso\Flow;

use InvalidArgumentException;

/**
 * Maps a stage key to the handler that serves it.
 *
 * Mirrors App\Domain\Workflow\StepHandlerRegistry, including its failure mode,
 * which its docblock states and which applies here word for word: a definition
 * naming an unregistered type "fails loudly at that step rather than being
 * silently skipped — a missing handler is a configuration error, and
 * pretending the step succeeded would let a workflow claim to have done
 * something it never did." A flow profile with a typo'd stage key must fail
 * the same way; a silently skipped stage is a learner who never gets taught.
 *
 * ---------------------------------------------------------------------------
 * CLASS NAMES, NOT INSTANCES — THIS IS A CONTAINER SAFETY REQUIREMENT
 * ---------------------------------------------------------------------------
 * The registry is constructed with class-name strings and resolves them
 * through the container on first use. It must NEVER be constructed with stage
 * instances.
 *
 * PALServiceProvider.php:94-100 documents why EsoPolicyService is deliberately
 * not registered in the container: doing so reopens the resolution cycle
 * PedagogySelectorEngine -> EsoPolicyService -> EsoEnrichmentResolver ->
 * PedagogySuggestedContentService -> PedagogyOrchestrationService ->
 * PedagogySelectorEngine. Stages are reached from inside a resolve, by which
 * time the engine already exists, so lazy resolution keeps the registry out of
 * that graph entirely. Eagerly constructing stages at provider-registration
 * time would drag the cycle back in.
 *
 * ---------------------------------------------------------------------------
 * THE DRIFT GUARD
 * ---------------------------------------------------------------------------
 * The key comes from config/pal_flow.php and the handler also declares one via
 * key(). Those are two sources for the same fact, so they can disagree. On
 * first resolution the two are compared and a mismatch throws. Without it, a
 * copy-pasted handler that forgot to change key() would be silently registered
 * under the wrong key and the validator would then read the WRONG tier for it
 * — which is exactly how a LOCKED stage becomes unlocked by accident.
 */
class EsoFlowStageRegistry
{
    /** @var array<string, class-string> */
    private array $classes;

    /** @var array<string, EsoFlowStageMeta> resolved lazily, once each */
    private array $resolved = [];

    /**
     * @param  array<string, class-string>  $classes  stage key => handler class
     */
    public function __construct(array $classes = [])
    {
        foreach ($classes as $key => $class) {
            if (! is_string($key) || $key === '' || ! is_string($class) || $class === '') {
                throw new InvalidArgumentException('Flow stage registry takes a map of stage key => handler class name.');
            }
        }

        $this->classes = $classes;
    }

    /** @return array<int, string> */
    public function keys(): array
    {
        return array_keys($this->classes);
    }

    public function has(string $key): bool
    {
        return isset($this->classes[$key]);
    }

    /**
     * The handler for a stage key.
     *
     * @throws InvalidArgumentException when the key is unregistered, the class
     *         does not implement a stage contract, or its key() disagrees with
     *         the key it was registered under.
     */
    public function find(string $key): EsoFlowStageMeta
    {
        if (isset($this->resolved[$key])) {
            return $this->resolved[$key];
        }

        if (! isset($this->classes[$key])) {
            throw new InvalidArgumentException(
                "No handler is registered for flow stage '{$key}'. The stage set is fixed; "
                . 'a profile may disable a stage but cannot introduce one.'
            );
        }

        $handler = app($this->classes[$key]);

        if (! $handler instanceof EsoFlowStageMeta) {
            throw new InvalidArgumentException(
                "Flow stage '{$key}' resolves to " . $this->classes[$key]
                . ', which does not implement EsoFlowStage or EsoFlowNodeStage.'
            );
        }

        if ($handler->key() !== $key) {
            throw new InvalidArgumentException(
                "Flow stage '{$key}' resolves to a handler declaring key '{$handler->key()}'. "
                . 'Registering a handler under the wrong key would make the validator read the '
                . 'wrong tier for it.'
            );
        }

        return $this->resolved[$key] = $handler;
    }

    /**
     * Handlers for every registered key.
     *
     * Used by the validator, which needs every stage's tier before it can
     * judge a submitted composition. This is the one place resolution is not
     * lazy, and it runs on an administrative write rather than in a resolve.
     *
     * @return array<string, EsoFlowStageMeta>
     */
    public function all(): array
    {
        foreach ($this->keys() as $key) {
            $this->find($key);
        }

        return $this->resolved;
    }
}
