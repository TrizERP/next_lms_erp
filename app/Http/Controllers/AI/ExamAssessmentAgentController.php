<?php

namespace App\Http\Controllers\AI;

use App\Services\Lms\ExamAssessmentAgentPipelineService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Web Management API for the Exam & Assessment 3-Agent System.
 *
 * Provides control over:
 *   1. Online Exam Summary Reader
 *   2. Assignment Status Reader
 *   3. Assessment Review Drafter
 *
 * Supports continuous monitoring, on-demand execution, full 3-agent pipeline synchronization,
 * and live Expert System Overview (ESO).
 */
class ExamAssessmentAgentController extends AiController
{
    public function __construct(
        private readonly ExamAssessmentAgentPipelineService $pipelineService,
    ) {
    }

    /**
     * Resolve the active tenant institute ID.
     */
    private function resolveInstituteId(Request $request): int
    {
        $context = $request->attributes->get('mcp_context');
        if ($context && !empty($context->selectedInstituteId)) {
            return (int) $context->selectedInstituteId;
        }

        $headerId = $request->header('X-MCP-Institute-Id');
        if ($headerId && is_numeric($headerId)) {
            return (int) $headerId;
        }

        return (int) ($request->input('sub_institute_id') ?: 195);
    }

    /**
     * GET /api/ai/exam-assessment/agent/state
     * Returns agent continuous loop state and live metrics.
     */
    public function state(Request $request): JsonResponse
    {
        try {
            $instituteId = $this->resolveInstituteId($request);
            $state = $this->pipelineService->getAgentState($instituteId);

            return $this->success('Exam & Assessment agent state loaded successfully.', [
                'state' => $state,
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }

    /**
     * POST /api/ai/exam-assessment/agent/start
     * Activates continuous background monitoring loop.
     */
    public function start(Request $request): JsonResponse
    {
        try {
            $instituteId = $this->resolveInstituteId($request);
            $intervalSeconds = (int) ($request->input('interval_seconds') ?: 60);
            $intervalSeconds = max(10, min(86400, $intervalSeconds));

            $result = $this->pipelineService->activateContinuousMonitoring($instituteId, $intervalSeconds);
            $state = $this->pipelineService->getAgentState($instituteId);

            return $this->success('Exam & Assessment monitoring loop started successfully.', [
                'activation' => $result,
                'state' => $state,
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }

    /**
     * POST /api/ai/exam-assessment/agent/stop
     * Deactivates continuous background monitoring loop.
     */
    public function stop(Request $request): JsonResponse
    {
        try {
            $instituteId = $this->resolveInstituteId($request);
            $result = $this->pipelineService->deactivateContinuousMonitoring($instituteId);
            $state = $this->pipelineService->getAgentState($instituteId);

            return $this->success('Exam & Assessment monitoring loop paused successfully.', [
                'deactivation' => $result,
                'state' => $state,
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }

    /**
     * POST /api/ai/exam-assessment/agent/run-now
     * Triggers an immediate on-demand assessment sweep.
     */
    public function runNow(Request $request): JsonResponse
    {
        try {
            $instituteId = $this->resolveInstituteId($request);
            $options = $request->all();

            $report = $this->pipelineService->runPipeline($instituteId, $options);
            $state = $this->pipelineService->getAgentState($instituteId);

            return $this->success('Exam & Assessment sweep completed successfully.', [
                'report' => $report,
                'state' => $state,
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }

    /**
     * POST /api/ai/exam-assessment/agent/interval
     * Updates the continuous loop validation interval.
     */
    public function setInterval(Request $request): JsonResponse
    {
        try {
            $instituteId = $this->resolveInstituteId($request);
            $intervalSeconds = (int) $request->validate([
                'interval_seconds' => 'required|integer|min:10|max:86400',
            ])['interval_seconds'];

            $this->pipelineService->activateContinuousMonitoring($instituteId, $intervalSeconds);
            $state = $this->pipelineService->getAgentState($instituteId);

            return $this->success("Assessment sweep interval updated to {$intervalSeconds} seconds.", [
                'state' => $state,
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }

    /**
     * POST /api/ai/exam-assessment/agent/pipeline
     * Executes the synchronized 3-agent pipeline:
     *   Step 1: Online Exam Performance Sweep -> Gate 1
     *   Step 2: Assignment & Work Status -> Gate 2
     *   Step 3: Assessment Review Drafter -> Gate 3 -> Unified Report.
     */
    public function pipeline(Request $request): JsonResponse
    {
        try {
            $instituteId = $this->resolveInstituteId($request);
            $options = $request->all();

            $report = $this->pipelineService->runPipeline($instituteId, $options);

            if (!($report['success'] ?? false)) {
                return $this->failure(
                    $report['error'] ?? 'Exam & Assessment pipeline failed at validation gate.',
                    422,
                    $report
                );
            }

            return $this->success('Exam & Assessment 3-agent synchronized pipeline completed successfully.', [
                'report' => $report,
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }

    /**
     * GET /api/ai/exam-assessment/agent/eso
     * Returns the complete Expert System Overview (ESO) for all three agents.
     */
    public function eso(Request $request): JsonResponse
    {
        try {
            $instituteId = $this->resolveInstituteId($request);
            $eso = $this->pipelineService->getExpertSystemOverview($instituteId);

            return $this->success('Exam & Assessment Expert System Overview loaded successfully.', [
                'eso' => $eso,
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }
}

