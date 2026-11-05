<?php

namespace App\Http\Controllers\AI;

use App\Services\Lms\PalAgentPipelineService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Web Management API for PAL (Personalized Adaptive Learning) 3-Agent Automation System.
 *
 * Provides control over:
 *   1. PAL Gamification & Streak Reader (agt_pal_gamification_reader)
 *   2. PAL Content Mastery Diagnostic Agent (agt_pal_content_mastery_agent)
 *   3. PAL Adaptive Coherence & Remediation Drafter (agt_pal_coherence_intervention_drafter)
 *
 * Supports continuous monitoring, on-demand execution, full 3-agent pipeline synchronization,
 * and live Executive Skill Object (ESO).
 */
class PalAgentController extends AiController
{
    public function __construct(
        private readonly PalAgentPipelineService $pipelineService,
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
     * GET /api/ai/pal/agent/state
     * Returns agent continuous loop state and live metrics.
     */
    public function state(Request $request): JsonResponse
    {
        try {
            $instituteId = $this->resolveInstituteId($request);
            $state = $this->pipelineService->getAgentState($instituteId);

            return $this->success('PAL agent state loaded successfully.', [
                'state' => $state,
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }

    /**
     * POST /api/ai/pal/agent/start
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

            return $this->success('PAL monitoring loop started successfully.', [
                'activation' => $result,
                'state' => $state,
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }

    /**
     * POST /api/ai/pal/agent/stop
     * Deactivates continuous background monitoring loop.
     */
    public function stop(Request $request): JsonResponse
    {
        try {
            $instituteId = $this->resolveInstituteId($request);
            $result = $this->pipelineService->deactivateContinuousMonitoring($instituteId);
            $state = $this->pipelineService->getAgentState($instituteId);

            return $this->success('PAL monitoring loop paused successfully.', [
                'deactivation' => $result,
                'state' => $state,
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }

    /**
     * POST /api/ai/pal/agent/run-now
     * Triggers an immediate on-demand PAL sweep.
     */
    public function runNow(Request $request): JsonResponse
    {
        try {
            $instituteId = $this->resolveInstituteId($request);
            $options = $request->all();

            $report = $this->pipelineService->runPipeline($instituteId, $options);
            $state = $this->pipelineService->getAgentState($instituteId);

            return $this->success('PAL sweep completed successfully.', [
                'report' => $report,
                'state' => $state,
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }

    /**
     * POST /api/ai/pal/agent/interval
     * Configures the continuous monitoring loop interval.
     */
    public function setInterval(Request $request): JsonResponse
    {
        try {
            $instituteId = $this->resolveInstituteId($request);
            $intervalSeconds = (int) ($request->input('interval_seconds') ?: 60);

            $result = $this->pipelineService->setInterval($instituteId, $intervalSeconds);
            $state = $this->pipelineService->getAgentState($instituteId);

            return $this->success('PAL monitoring interval updated successfully.', [
                'interval' => $result,
                'state' => $state,
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }

    /**
     * POST /api/ai/pal/agent/pipeline
     * Executes the synchronized 3-agent PAL pipeline.
     */
    public function pipeline(Request $request): JsonResponse
    {
        try {
            $instituteId = $this->resolveInstituteId($request);
            $options = $request->all();

            $report = $this->pipelineService->runPipeline($instituteId, $options);

            return $this->success('PAL 3-agent pipeline executed successfully.', [
                'report' => $report,
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }

    /**
     * GET /api/ai/pal/agent/eso
     * Returns the complete Executive Skill Object (ESO) for all three agents.
     */
        /**
     * GET /api/ai/pal/agent/memory
     * Returns the active pedagogical memory file status, loaded frameworks, and verification.
     */
    public function memory(Request $request, \App\Services\PAL\Pedagogy\PedagogicalMemoryService $memoryService): JsonResponse
    {
        try {
            $memory = $memoryService->loadMemory();

            return $this->success('Pedagogical memory loaded successfully.', [
                'file_path' => $memory['file_path'],
                'file_size' => $memory['file_size'],
                'last_modified' => $memory['last_modified'],
                'frameworks' => array_keys($memory['frameworks']),
                'deliverables_count' => count($memory['deliverables']),
                'standards' => $memory['standards'],
                'loaded_at' => $memory['loaded_at'],
            ]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }

    public function eso(Request $request): JsonResponse
    {
        try {
            $instituteId = $this->resolveInstituteId($request);
            $eso = $this->pipelineService->getExpertSystemOverview($instituteId);

            return $this->success('PAL Executive Skill Object (ESO) loaded successfully.', [
                'eso' => $eso,
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }
}

