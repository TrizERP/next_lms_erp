<?php

namespace App\Domain\Workflow;

use App\Services\Mcp\McpRequestContext;

/**
 * A workflow step type.
 *
 * Handlers are registered by `step_type`, which is what makes the engine
 * configuration-driven: adding a capability means registering a handler, not editing
 * the engine.
 *
 * `isConsequential()` is the important one. A handler that returns true is refused
 * unless GovernanceValidator finds an approving human Decision for the run's
 * recommendation. The engine asks the handler rather than inferring, because only the
 * handler knows whether its particular configuration writes to the world.
 */
interface StepHandler
{
    /** The `step_type` this handler serves. */
    public function type(): string;

    /**
     * Does running this step change data outside the intelligence tables?
     *
     * When true, the engine will not run it without an approved decision.
     */
    public function isConsequential(array $config): bool;

    /**
     * Execute the step.
     *
     * @param  array  $config  The step's declared configuration
     * @param  array  $state   Accumulated outputs of earlier steps
     * @return StepResult
     */
    public function handle(
        array $config,
        array $state,
        WorkflowRunContext $run,
        McpRequestContext $scope
    ): StepResult;
}
