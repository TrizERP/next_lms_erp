<?php

namespace App\Domain\Workflow\Actions;

use App\Domain\Workflow\WorkflowRunContext;
use App\Services\Mcp\McpRequestContext;

/**
 * A concrete thing a workflow can do to the ERP.
 *
 * Actions are the only place the intelligence layer writes to business tables, and
 * they are reachable only through an `action` step, which the engine will not run
 * without an approving human decision. Keeping them behind a named registry rather
 * than letting steps call arbitrary code is what makes "what can this workflow
 * actually do?" an answerable question.
 */
interface WorkflowAction
{
    /** Stable key referenced by a step's `config.action`. */
    public function key(): string;

    /** Human-readable, shown in the AI Administration workflow editor. */
    public function label(): string;

    /**
     * Perform the action.
     *
     * @return array The action's output, merged into the run state
     */
    public function execute(
        array $config,
        array $state,
        WorkflowRunContext $run,
        McpRequestContext $scope
    ): array;
}
