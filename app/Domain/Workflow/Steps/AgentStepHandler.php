<?php

namespace App\Domain\Workflow\Steps;

use App\Domain\AI\Agents\AgentRunner;
use App\Domain\Workflow\StepHandler;
use App\Domain\Workflow\StepResult;
use App\Domain\Workflow\WorkflowRunContext;
use App\Services\Mcp\McpRequestContext;

/**
 * Runs an agent as a workflow step — collect evidence, build a case, draft a
 * recommendation.
 *
 * Not consequential, and it cannot become so: agents are held to their own verb
 * ceiling by AgentRunner, and no agent in this platform is licensed to execute.
 * A workflow that wants something done still has to go through an `action` step,
 * which means through the approval gate.
 */
class AgentStepHandler implements StepHandler
{
    public function __construct(private readonly AgentRunner $runner)
    {
    }

    public function type(): string
    {
        return 'agent';
    }

    public function isConsequential(array $config): bool
    {
        return false;
    }

    public function handle(
        array $config,
        array $state,
        WorkflowRunContext $run,
        McpRequestContext $scope
    ): StepResult {
        $agentKey = (string) ($config['agent'] ?? '');

        if ($agentKey === '') {
            return StepResult::failed('This agent step names no agent.');
        }

        $input = is_array($config['input'] ?? null) ? $config['input'] : [];

        // Let the step inherit the run's subject unless it declares its own.
        $input += array_filter([
            'subject_entity_key' => $run->subjectEntityKey,
            'subject_id' => $run->subjectId,
            'case_id' => $run->caseId,
        ], fn ($value) => $value !== null);

        $result = $this->runner->run(
            $agentKey,
            $scope,
            $input,
            'workflow',
            'workflow_run:' . $run->runId
        );

        if (in_array($result['status'], ['failed', 'rejected'], true)) {
            // A required agent failing stops the run; an optional one lets it carry on.
            return ($config['required'] ?? true)
                ? StepResult::failed($result['error'] ?? 'The agent could not complete.')
                : StepResult::skipped($result['error'] ?? 'Optional agent step skipped.');
        }

        return StepResult::completed(
            [
                'agent_run_id' => $result['run_id'],
                'status' => $result['status'],
                'summary' => $result['summary'],
                'counters' => $result['counters'],
            ] + $result['result'],
            $result['summary']
        );
    }
}
