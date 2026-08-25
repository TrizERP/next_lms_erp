<?php

namespace App\Domain\Workflow;

/**
 * The identity of a running workflow, handed to each step handler.
 *
 * Carries the links back to what caused the run — the recommendation, the decision,
 * the case — because a step needs them to attribute what it does, and because the
 * consequential-action check needs the recommendation id to find the human approval.
 */
final class WorkflowRunContext
{
    public function __construct(
        public readonly int $runId,
        public readonly string $workflowKey,
        public readonly int $definitionId,
        public readonly int $versionId,
        public readonly ?int $recommendationId = null,
        public readonly ?int $decisionId = null,
        public readonly ?int $caseId = null,
        public readonly ?int $agentRunId = null,
        public readonly ?string $subjectEntityKey = null,
        public readonly int|string|null $subjectId = null,
        public readonly array $input = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'run_id' => $this->runId,
            'workflow_key' => $this->workflowKey,
            'recommendation_id' => $this->recommendationId,
            'decision_id' => $this->decisionId,
            'case_id' => $this->caseId,
            'agent_run_id' => $this->agentRunId,
            'subject_entity_key' => $this->subjectEntityKey,
            'subject_id' => $this->subjectId,
        ];
    }
}
