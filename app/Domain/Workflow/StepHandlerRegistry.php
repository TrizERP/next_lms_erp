<?php

namespace App\Domain\Workflow;

/**
 * Maps `step_type` to the handler that serves it.
 *
 * Registration happens in AiServiceProvider. A workflow definition naming an
 * unregistered type fails loudly at that step rather than being silently skipped —
 * a missing handler is a configuration error, and pretending the step succeeded
 * would let a workflow claim to have done something it never did.
 */
class StepHandlerRegistry
{
    /** @var array<string, StepHandler> */
    private array $handlers = [];

    /**
     * @param  iterable<StepHandler>  $handlers
     */
    public function __construct(iterable $handlers = [])
    {
        foreach ($handlers as $handler) {
            $this->register($handler);
        }
    }

    public function register(StepHandler $handler): void
    {
        $this->handlers[$handler->type()] = $handler;
    }

    public function find(string $type): ?StepHandler
    {
        return $this->handlers[$type] ?? null;
    }

    public function has(string $type): bool
    {
        return isset($this->handlers[$type]);
    }

    /** @return array<int, string> */
    public function types(): array
    {
        return array_keys($this->handlers);
    }
}
