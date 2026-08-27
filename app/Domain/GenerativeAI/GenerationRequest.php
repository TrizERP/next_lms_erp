<?php

namespace App\Domain\GenerativeAI;

/**
 * What the caller is asking to have generated.
 *
 * The linkage fields (caseId, agentRunId, workflowRunId) are not bookkeeping: they
 * are what lets an audit answer "which case produced this text", and what lets a
 * generated explanation be traced back to the run that requested it.
 */
final class GenerationRequest
{
    public function __construct(
        public readonly string $templateKey,
        public readonly string $purpose,
        public readonly array $variables = [],
        public readonly string $domain = 'k12',
        public readonly ?string $subjectEntityKey = null,
        public readonly int|string|null $subjectId = null,
        public readonly ?int $caseId = null,
        public readonly ?int $agentRunId = null,
        public readonly ?int $workflowRunId = null,
        public readonly array $context = [],
        public readonly ?string $modelOverride = null,
    ) {
    }

    public static function make(string $templateKey, string $purpose, array $variables = []): self
    {
        return new self($templateKey, $purpose, $variables);
    }

    public function forCase(int $caseId, ?string $subjectEntityKey = null, int|string|null $subjectId = null): self
    {
        return new self(
            $this->templateKey,
            $this->purpose,
            $this->variables,
            $this->domain,
            $subjectEntityKey ?? $this->subjectEntityKey,
            $subjectId ?? $this->subjectId,
            $caseId,
            $this->agentRunId,
            $this->workflowRunId,
            $this->context,
            $this->modelOverride,
        );
    }

    public function withVariables(array $variables): self
    {
        return new self(
            $this->templateKey,
            $this->purpose,
            array_merge($this->variables, $variables),
            $this->domain,
            $this->subjectEntityKey,
            $this->subjectId,
            $this->caseId,
            $this->agentRunId,
            $this->workflowRunId,
            $this->context,
            $this->modelOverride,
        );
    }
}
