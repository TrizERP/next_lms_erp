<?php

namespace App\Http\Controllers\AI;

use App\Services\Lms\ContentAgentService;
use App\Services\Lms\CourseCatalogueHierarchyValidator;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Web Management API for the Course Catalogue Reader Agent and Content Agent.
 *
 * Enables complete starting, stopping, interval configuration, on-demand execution,
 * automated content generation, and live continuous monitoring directly from the
 * LMS web interface without requiring any terminal or PowerShell commands.
 */
class TeachLearnCatalogueAgentController extends AiController
{
    public function __construct(
        private readonly CourseCatalogueHierarchyValidator $validator,
        private readonly ContentAgentService $contentAgentService,
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
     * GET /api/ai/teach-learn/catalogue-agent/state
     * Returns continuous agent loop state, latest 5-tier validation findings, and metrics.
     */
    public function state(Request $request): JsonResponse
    {
        try {
            $instituteId = $this->resolveInstituteId($request);
            $state = $this->validator->getAgentState($instituteId);
            $latestRun = $this->validator->getLatestRun($instituteId);
            $contentStats = $this->contentAgentService->getContentStats($instituteId);

            return $this->success('Course Catalogue Reader Agent state loaded.', [
                'state' => $state,
                'latest_run' => $latestRun,
                'content_stats' => $contentStats,
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }

    /**
     * POST /api/ai/teach-learn/catalogue-agent/start
     * Activates continuous background monitoring loop.
     */
    public function start(Request $request): JsonResponse
    {
        try {
            $instituteId = $this->resolveInstituteId($request);
            $intervalSeconds = (int) ($request->input('interval_seconds') ?: 60);
            $intervalSeconds = max(10, min(86400, $intervalSeconds));

            $result = $this->validator->activateContinuousMonitoring($instituteId, $intervalSeconds);
            $state = $this->validator->getAgentState($instituteId);

            return $this->success('Continuous monitoring loop started successfully.', [
                'activation' => $result,
                'state' => $state,
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }

    /**
     * POST /api/ai/teach-learn/catalogue-agent/stop
     * Deactivates continuous background monitoring loop.
     */
    public function stop(Request $request): JsonResponse
    {
        try {
            $instituteId = $this->resolveInstituteId($request);
            $result = $this->validator->deactivateContinuousMonitoring($instituteId);
            $state = $this->validator->getAgentState($instituteId);

            return $this->success('Continuous monitoring loop paused successfully.', [
                'deactivation' => $result,
                'state' => $state,
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }

    /**
     * POST /api/ai/teach-learn/catalogue-agent/run-now
     * Triggers an immediate on-demand full hierarchy validation sweep.
     */
    public function runNow(Request $request): JsonResponse
    {
        try {
            $instituteId = $this->resolveInstituteId($request);
            $report = $this->validator->validate($instituteId, true);
            $state = $this->validator->getAgentState($instituteId);

            return $this->success('Validation sweep completed.', [
                'report' => $report,
                'state' => $state,
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }

    /**
     * POST /api/ai/teach-learn/catalogue-agent/interval
     * Updates the continuous loop validation interval.
     */
    public function setInterval(Request $request): JsonResponse
    {
        try {
            $instituteId = $this->resolveInstituteId($request);
            $intervalSeconds = (int) $request->validate([
                'interval_seconds' => 'required|integer|min:10|max:86400',
            ])['interval_seconds'];

            if (Schema::hasTable('lms_catalogue_agent_state')) {
                $now = Carbon::now();
                DB::table('lms_catalogue_agent_state')
                    ->where('sub_institute_id', $instituteId)
                    ->update([
                        'interval_seconds' => $intervalSeconds,
                        'next_run_at' => $now->copy()->addSeconds($intervalSeconds),
                        'updated_at' => $now,
                    ]);
            }

            $state = $this->validator->getAgentState($instituteId);

            return $this->success("Validation interval updated to {$intervalSeconds} seconds.", [
                'state' => $state,
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }

    /**
     * GET /api/ai/teach-learn/catalogue-agent/content-tasks
     * Returns recent task ledger entries with stored content IDs, titles, and statuses.
     */
    public function contentTasks(Request $request): JsonResponse
    {
        try {
            $instituteId = $this->resolveInstituteId($request);
            $limit = max(5, min(100, (int) ($request->input('limit') ?: 50)));
            $status = $request->input('status');

            $ledger = $this->contentAgentService->getContentTasksLedger($instituteId, $limit, $status);
            $contentStats = $this->contentAgentService->getContentStats($instituteId);

            return $this->success('Content agent task ledger loaded.', [
                'tasks' => $ledger,
                'stats' => $contentStats,
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }

    /**
     * POST /api/ai/teach-learn/catalogue-agent/auto-generate/toggle
     * Enables or disables automatic content generation by the Content Agent.
     */
    public function toggleAutoGenerate(Request $request): JsonResponse
    {
        try {
            $instituteId = $this->resolveInstituteId($request);
            $enabled = (bool) $request->input('enabled', true);

            if (Schema::hasTable('lms_catalogue_agent_state')) {
                DB::table('lms_catalogue_agent_state')
                    ->where('sub_institute_id', $instituteId)
                    ->update([
                        'auto_generate_content' => $enabled,
                        'updated_at' => Carbon::now(),
                    ]);
            }

            $state = $this->validator->getAgentState($instituteId);

            return $this->success(
                $enabled ? 'Content Agent automatic generation enabled.' : 'Content Agent automatic generation paused.',
                [
                    'state' => $state,
                    'server_time' => Carbon::now()->toIso8601String(),
                ]
            );
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }

    /**
     * POST /api/ai/teach-learn/catalogue-agent/auto-generate/batch
     * Triggers the Content Agent to process a batch of pending topics on demand.
     */
    public function runContentBatch(Request $request): JsonResponse
    {
        try {
            $instituteId = $this->resolveInstituteId($request);
            $limit = max(1, min(50, (int) ($request->input('limit') ?: 10)));

            $batchResult = $this->contentAgentService->batchProcessPendingTopics($instituteId, $limit);

            // Run validation sweep to immediately refresh state and hierarchy counts
            $report = $this->validator->validate($instituteId, true, null, false);
            $state = $this->validator->getAgentState($instituteId);
            $contentStats = $this->contentAgentService->getContentStats($instituteId);

            return $this->success('Content Agent batch processing completed.', [
                'batch_result' => $batchResult,
                'report' => $report,
                'state' => $state,
                'content_stats' => $contentStats,
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }
}
