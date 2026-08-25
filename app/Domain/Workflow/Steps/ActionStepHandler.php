<?php

namespace App\Domain\Workflow\Steps;

use App\Domain\AI\Support\AiAuditLogger;
use App\Domain\Workflow\Actions\ActionRegistry;
use App\Domain\Workflow\StepHandler;
use App\Domain\Workflow\StepResult;
use App\Domain\Workflow\WorkflowRunContext;
use App\Services\Mcp\McpRequestContext;
use Throwable;

/**
 * The step that actually changes something.
 *
 * Always consequential, unconditionally. A step type whose whole purpose is to write
 * to the ERP has no configuration under which it becomes safe, so this handler does
 * not let configuration argue otherwise — which means the engine's approval gate
 * always fires before it.
 */
class ActionStepHandler implements StepHandler
{
    public function __construct(
        private readonly ActionRegistry $actions,
        private readonly AiAuditLogger $audit,
    ) {
    }

    public function type(): string
    {
        return 'action';
    }

    public function isConsequential(array $config): bool
    {
        return true;
    }

    public function handle(
        array $config,
        array $state,
        WorkflowRunContext $run,
        McpRequestContext $scope
    ): StepResult {
        $actionKey = (string) ($config['action'] ?? '');

        if ($actionKey === '') {
            return StepResult::failed('This action step names no action.');
        }

        $action = $this->actions->find($actionKey);

        if (! $action) {
            return StepResult::failed(sprintf('Action "%s" is not registered.', $actionKey));
        }

        try {
            $output = $action->execute($config, $state, $run, $scope);
        } catch (Throwable $exception) {
            $this->audit->record(AiAuditLogger::WORKFLOW_TRANSITION, $scope, [
                'related_type' => 'workflow_runs',
                'related_id' => $run->runId,
                'outcome' => 'failure',
                'message' => sprintf('Action "%s" failed: %s', $actionKey, $exception->getMessage()),
            ]);

            return StepResult::failed($exception->getMessage());
        }

        $this->audit->record(AiAuditLogger::WORKFLOW_TRANSITION, $scope, [
            'related_type' => 'workflow_runs',
            'related_id' => $run->runId,
            'subject_entity_key' => $run->subjectEntityKey,
            'subject_id' => $run->subjectId,
            'message' => sprintf('Action "%s" executed.', $action->label()),
            'payload' => ['action' => $actionKey, 'output' => $output],
        ]);

        return StepResult::completed($output, sprintf('%s completed.', $action->label()));
    }
}
