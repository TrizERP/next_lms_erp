<?php

namespace App\Http\Controllers\AI;

use App\Domain\AI\Decisions\DecisionGate;
use App\Domain\AI\Recommendations\RecommendationDrafter;
use App\Domain\Workflow\WorkflowEngine;
use Illuminate\Http\Request;
use Throwable;

/**
 * The human approval surface.
 *
 * Approving does two things and in this order: it records the decision, then — only
 * if the recommendation names a workflow — it starts that workflow. Starting is
 * separate from deciding on purpose: if the workflow cannot start, the teacher's
 * decision still stands and is still recorded, and the run can be retried without
 * asking them to approve again.
 *
 * Nothing here executes an action directly. The workflow engine re-checks the
 * decision at its own consequential steps, so even this endpoint cannot shortcut
 * the gate.
 */
class RecommendationController extends AiController
{
    public function __construct(
        private readonly RecommendationDrafter $recommendations,
        private readonly DecisionGate $decisions,
        private readonly WorkflowEngine $workflows,
    ) {
    }

    public function pending(Request $request)
    {
        try {
            $scope = $this->scope($request);

            return $this->success('Pending recommendations loaded.', [
                'recommendations' => $this->recommendations->pendingApproval($scope, $this->limit($request)),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    public function show(Request $request, int $recommendation)
    {
        try {
            $scope = $this->scope($request);
            $record = $this->recommendations->find($recommendation, $scope);

            if (! $record) {
                return $this->failure('No such recommendation.', 404);
            }

            return $this->success('Recommendation loaded.', [
                'recommendation' => $record,
                'decisions' => $this->decisions->historyFor($recommendation, $scope),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    public function approve(Request $request, int $recommendation)
    {
        try {
            $scope = $this->scope($request);

            $validated = $request->validate([
                'reason' => 'nullable|string|max:2000',
                'modifications' => 'nullable|array',
                'confirmation_token' => 'nullable|string|max:128',
                'decided_by_name' => 'nullable|string|max:150',
                'start_workflow' => 'nullable|boolean',
            ]);

            $decision = $this->decisions->approve(
                $recommendation,
                $scope,
                $validated['reason'] ?? null,
                $validated['modifications'] ?? [],
                $validated['confirmation_token'] ?? null,
                $validated['decided_by_name'] ?? null
            );

            $record = $this->recommendations->find($recommendation, $scope);
            $workflow = null;

            if (($validated['start_workflow'] ?? true) && ! empty($record['workflow_key'])) {
                $workflow = $this->startWorkflow($record, $decision, $scope);
            }

            return $this->success('Recommendation approved.', [
                'decision' => $decision,
                'recommendation' => $this->recommendations->find($recommendation, $scope),
                'workflow' => $workflow,
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    public function reject(Request $request, int $recommendation)
    {
        try {
            $scope = $this->scope($request);

            $validated = $request->validate([
                'reason' => 'nullable|string|max:2000',
                'decided_by_name' => 'nullable|string|max:150',
            ]);

            $decision = $this->decisions->reject(
                $recommendation,
                $scope,
                $validated['reason'] ?? null,
                $validated['decided_by_name'] ?? null
            );

            return $this->success('Recommendation rejected.', [
                'decision' => $decision,
                'recommendation' => $this->recommendations->find($recommendation, $scope),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    public function defer(Request $request, int $recommendation)
    {
        try {
            $scope = $this->scope($request);

            $validated = $request->validate([
                'reason' => 'nullable|string|max:2000',
                'decided_by_name' => 'nullable|string|max:150',
            ]);

            return $this->success('Recommendation deferred.', [
                'decision' => $this->decisions->defer(
                    $recommendation,
                    $scope,
                    $validated['reason'] ?? null,
                    $validated['decided_by_name'] ?? null
                ),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * Starting the workflow is best-effort and reported honestly. A failure here
     * does not undo the approval — it returns the reason so the caller can retry.
     */
    private function startWorkflow(array $recommendation, array $decision, $scope): array
    {
        try {
            $payload = $recommendation['workflow_payload'] ?? [];

            return $this->workflows->start(
                $recommendation['workflow_key'],
                $scope,
                is_array($payload) ? $payload : [],
                [
                    'trigger_type' => 'recommendation_approved',
                    'recommendation_id' => $recommendation['id'],
                    'decision_id' => $decision['decision_id'] ?? null,
                    'case_id' => $recommendation['case_id'] ?? null,
                    'subject_entity_key' => $recommendation['subject_entity_key'] ?? null,
                    'subject_id' => $recommendation['subject_id'] ?? null,
                ]
            );
        } catch (Throwable $exception) {
            report($exception);

            return [
                'run_id' => null,
                'status' => 'failed',
                'message' => 'The decision was recorded, but the workflow could not be started: '
                    . $exception->getMessage(),
                'current_step' => null,
            ];
        }
    }
}
