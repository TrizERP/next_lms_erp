<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\AI\Concerns\RequiresFeesStaffAccess;
use App\Services\AI\Fees\FeesAgentPipelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Operational control for the Fees Agentic Intelligence capability — the
 * same `{module}/agent/*` shape every other module already offers
 * (EngagementAgentController, PalAgentController, etc.), driving the two
 * real governed agents (`k12_fees`, `k12_fees_predictive`) via
 * FeesAgentPipelineService rather than a one-off tool pipeline.
 *
 * No `execute` action exists here and never will: per this module's
 * locked-in design, every Fees recommendation stays human-approved
 * (RecommendationController::approve), regardless of how the sweep that
 * drafted it was triggered.
 */
class FeesAgentController extends AiController
{
    use RequiresFeesStaffAccess;

    public function __construct(private readonly FeesAgentPipelineService $pipelineService)
    {
    }

    public function state(Request $request): JsonResponse
    {
        try {
            $scope = $this->scope($request);
            $this->assertStaffAccess($scope);

            return $this->success('Fees agent state loaded.', [
                'state' => $this->pipelineService->getAgentState($scope->selectedInstituteId),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    public function start(Request $request): JsonResponse
    {
        try {
            $scope = $this->scope($request);
            $this->assertAdminAccess($scope);
            $intervalSeconds = (int) ($request->input('interval_seconds') ?: FeesAgentPipelineService::DEFAULT_INTERVAL_SECONDS);

            $result = $this->pipelineService->activateContinuousMonitoring($scope->selectedInstituteId, $intervalSeconds);

            return $this->success('Fees continuous monitoring started.', [
                'activation' => $result,
                'state' => $this->pipelineService->getAgentState($scope->selectedInstituteId),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    public function stop(Request $request): JsonResponse
    {
        try {
            $scope = $this->scope($request);
            $this->assertAdminAccess($scope);
            $result = $this->pipelineService->deactivate($scope->selectedInstituteId);

            return $this->success('Fees continuous monitoring stopped.', [
                'deactivation' => $result,
                'state' => $this->pipelineService->getAgentState($scope->selectedInstituteId),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    public function runNow(Request $request): JsonResponse
    {
        try {
            $scope = $this->scope($request);
            $this->assertAdminAccess($scope);
            $report = $this->pipelineService->runPipeline($scope->selectedInstituteId, $request->all());

            return $this->success('Fees agent sweep completed.', ['report' => $report]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    public function setInterval(Request $request): JsonResponse
    {
        try {
            $scope = $this->scope($request);
            $this->assertAdminAccess($scope);
            $intervalSeconds = (int) ($request->input('interval_seconds') ?: FeesAgentPipelineService::DEFAULT_INTERVAL_SECONDS);

            $result = $this->pipelineService->setInterval($scope->selectedInstituteId, $intervalSeconds);

            return $this->success('Fees monitoring interval updated.', [
                'interval' => $result,
                'state' => $this->pipelineService->getAgentState($scope->selectedInstituteId),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    public function pipeline(Request $request): JsonResponse
    {
        try {
            $scope = $this->scope($request);
            $this->assertAdminAccess($scope);
            $report = $this->pipelineService->runPipeline($scope->selectedInstituteId, $request->all());

            return $this->success('Fees agent pipeline executed.', ['report' => $report]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    public function eso(Request $request): JsonResponse
    {
        try {
            $scope = $this->scope($request);
            $this->assertStaffAccess($scope);

            return $this->success('Fees agentic ESO loaded.', ['eso' => $this->pipelineService->eso($scope->selectedInstituteId)]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }
}
