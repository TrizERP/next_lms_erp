<?php

namespace App\Domain\Workflow\Actions;

/**
 * The catalogue of things workflows are allowed to do.
 *
 * A step naming an unregistered action fails. There is deliberately no fallback and
 * no dynamic class resolution from configuration: if an action is not in this
 * registry, no workflow definition can invoke it, however it is written.
 */
class ActionRegistry
{
    /** @var array<string, WorkflowAction> */
    private array $actions = [];

    /**
     * @param  iterable<WorkflowAction>  $actions
     */
    public function __construct(iterable $actions = [])
    {
        foreach ($actions as $action) {
            $this->register($action);
        }
    }

    public function register(WorkflowAction $action): void
    {
        $this->actions[$action->key()] = $action;
    }

    public function find(string $key): ?WorkflowAction
    {
        return $this->actions[$key] ?? null;
    }

    /** @return array<int, array{key:string,label:string}> */
    public function catalogue(): array
    {
        return array_values(array_map(
            fn (WorkflowAction $action) => ['key' => $action->key(), 'label' => $action->label()],
            $this->actions
        ));
    }
}
