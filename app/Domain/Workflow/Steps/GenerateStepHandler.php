<?php

namespace App\Domain\Workflow\Steps;

use App\Domain\GenerativeAI\GenerationRequest;
use App\Domain\GenerativeAI\GenerationService;
use App\Domain\Workflow\StepHandler;
use App\Domain\Workflow\StepResult;
use App\Domain\Workflow\WorkflowRunContext;
use App\Services\Mcp\McpRequestContext;

/**
 * Generates content inside a workflow — an intervention activity, a message draft,
 * a training plan.
 *
 * Not consequential: generating produces a draft, and the draft only reaches anyone
 * through a later notify or action step, both of which are gated. The output is
 * carried into the run state flagged `is_generated`, so any step or screen that
 * consumes it knows what it is looking at.
 */
class GenerateStepHandler implements StepHandler
{
    public function __construct(private readonly GenerationService $generation)
    {
    }

    public function type(): string
    {
        return 'generate';
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
        $templateKey = (string) ($config['template'] ?? '');

        if ($templateKey === '') {
            return StepResult::failed('This generate step names no template.');
        }

        // Variables may be given literally or pulled from the run state by path, so a
        // template can be fed the case and evidence an earlier step produced.
        $variables = is_array($config['variables'] ?? null) ? $config['variables'] : [];

        foreach ((array) ($config['variables_from'] ?? []) as $key => $path) {
            $value = $this->resolvePath((string) $path, $state);

            if ($value !== null) {
                $variables[$key] = $value;
            }
        }

        $request = new GenerationRequest(
            templateKey: $templateKey,
            purpose: (string) ($config['purpose'] ?? 'workflow_generation'),
            variables: $variables,
            domain: (string) ($config['domain'] ?? 'k12'),
            subjectEntityKey: $run->subjectEntityKey,
            subjectId: $run->subjectId,
            caseId: $run->caseId,
            workflowRunId: $run->runId,
            context: ['workflow_key' => $run->workflowKey],
        );

        $result = $this->generation->generate($request, $scope);

        if (! $result->succeeded) {
            return ($config['required'] ?? true)
                ? StepResult::failed($result->error ?? 'Generation failed.')
                : StepResult::skipped($result->error ?? 'Optional generation skipped.');
        }

        if (! $result->isUsable()) {
            $reason = $result->safetyPassed
                ? 'Generated content did not match the expected format.'
                : 'Generated content failed safety checks.';

            return ($config['required'] ?? true)
                ? StepResult::failed($reason)
                : StepResult::skipped($reason);
        }

        return StepResult::completed([
            'content' => $result->content,
            'structured' => $result->structured,
            'is_generated' => true,
            'requires_review' => $result->requiresReview,
            'generation_output_id' => $result->outputId,
            'model' => $result->model,
        ], 'Content generated.');
    }

    private function resolvePath(string $path, array $state): mixed
    {
        if ($path === '') {
            return null;
        }

        $value = $state;

        foreach (explode('.', $path) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }
}
