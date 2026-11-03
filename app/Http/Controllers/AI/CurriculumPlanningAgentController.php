<?php

namespace App\Http\Controllers\AI;

use App\Services\Lms\CurriculumPlanningAgentPipelineService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Web Management API for Curriculum Planning 3-Agent Automation System.
 *
 * Provides control over:
 *   1. Curriculum Structure & Coverage Reader (agt_curriculum_coverage_reader)
 *   2. Lesson Plan Pace Auditor (agt_lesson_plan_pace_auditor)
 *   3. Learning Outcomes Alignment Drafter (agt_curriculum_outcomes_alignment_drafter)
 *
 * Supports continuous monitoring, on-demand execution, full 3-agent pipeline synchronization,
 * and live Executive Skill Object (ESO).
 */
class CurriculumPlanningAgentController extends AiController
{
    public function __construct(
        private readonly CurriculumPlanningAgentPipelineService $pipelineService,
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

        return (int) ($request->input('sub_institute_id') ?: 1);
    }

    /**
     * GET /api/ai/curriculum-planning/agent/state
     * Returns agent continuous loop state and live metrics.
     */
    public function state(Request $request): JsonResponse
    {
        try {
            $instituteId = $this->resolveInstituteId($request);
            $state = $this->pipelineService->getAgentState($instituteId);

            return $this->success('Curriculum Planning agent state loaded successfully.', [
                'state' => $state,
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }

    /**
     * POST /api/ai/curriculum-planning/agent/start
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

            return $this->success('Curriculum Planning monitoring loop started successfully.', [
                'activation' => $result,
                'state' => $state,
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }

    /**
     * POST /api/ai/curriculum-planning/agent/stop
     * Deactivates continuous background monitoring loop.
     */
    public function stop(Request $request): JsonResponse
    {
        try {
            $instituteId = $this->resolveInstituteId($request);
            $result = $this->pipelineService->deactivateContinuousMonitoring($instituteId);
            $state = $this->pipelineService->getAgentState($instituteId);

            return $this->success('Curriculum Planning monitoring loop paused successfully.', [
                'deactivation' => $result,
                'state' => $state,
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }

    /**
     * POST /api/ai/curriculum-planning/agent/run-now
     * Triggers an immediate on-demand curriculum sweep.
     */
    public function runNow(Request $request): JsonResponse
    {
        try {
            $instituteId = $this->resolveInstituteId($request);
            $options = $request->all();

            $report = $this->pipelineService->runPipeline($instituteId, $options);
            $state = $this->pipelineService->getAgentState($instituteId);

            return $this->success('Curriculum Planning sweep completed successfully.', [
                'report' => $report,
                'state' => $state,
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }

    /**
     * POST /api/ai/curriculum-planning/agent/interval
     * Configures the continuous monitoring loop interval.
     */
    public function setInterval(Request $request): JsonResponse
    {
        try {
            $instituteId = $this->resolveInstituteId($request);
            $intervalSeconds = (int) ($request->input('interval_seconds') ?: 60);

            $result = $this->pipelineService->setInterval($instituteId, $intervalSeconds);
            $state = $this->pipelineService->getAgentState($instituteId);

            return $this->success('Curriculum Planning monitoring interval updated successfully.', [
                'interval' => $result,
                'state' => $state,
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }

    /**
     * POST /api/ai/curriculum-planning/agent/pipeline
     * Executes the synchronized 3-agent Curriculum Planning pipeline.
     */
    public function pipeline(Request $request): JsonResponse
    {
        try {
            $instituteId = $this->resolveInstituteId($request);
            $options = $request->all();

            $report = $this->pipelineService->runPipeline($instituteId, $options);

            return $this->success('Curriculum Planning 3-agent pipeline executed successfully.', [
                'report' => $report,
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }

    /**
     * GET /api/ai/curriculum-planning/agent/eso
     * Returns the complete Executive Skill Object (ESO) for all three agents.
     */
    public function eso(Request $request): JsonResponse
    {
        try {
            $instituteId = $this->resolveInstituteId($request);
            $eso = $this->pipelineService->getExpertSystemOverview($instituteId);

            return $this->success('Curriculum Planning Executive Skill Object (ESO) loaded successfully.', [
                'eso' => $eso,
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }
}

