<?php

namespace App\Http\Controllers\AI;

use App\Services\Lms\ContentAgentService;
use App\Services\Lms\CourseCatalogueHierarchyValidator;
use App\Services\Lms\TeachLearnAgentPipelineService;
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
        private readonly TeachLearnAgentPipelineService $pipelineService,
    ) {
    }

    /**
     * Resolve and strictly authorize the active tenant institute ID.
     *
     * Extracts tenant identity dynamically from authenticated MCP context, JWT payload,
     * or active Laravel session. Never trusts unverified request inputs, and never
     * defaults to static institute IDs (e.g. 1, 3, 6).
     */
    private function resolveInstituteId(Request $request): int
    {
        $allowedInstituteIds = [];
        $auth = $request->attributes->get('mcp_auth') ?? [];

        // 1. Check validated McpRequestContext from McpContextHydrator middleware
        $mcpContext = $request->attributes->get('mcp_context');
        if ($mcpContext instanceof \App\Services\Mcp\McpRequestContext) {
            $allowedInstituteIds = $mcpContext->allowedInstituteIds;
            if ($mcpContext->selectedInstituteId > 0) {
                // If a specific institute was requested, verify it is allowed
                $headerId = (int) ($request->header('X-MCP-Institute-Id') ?: $request->input('sub_institute_id'));
                if ($headerId > 0) {
                    if (in_array($headerId, $allowedInstituteIds, true) || $mcpContext->isAdmin) {
                        return $headerId;
                    }
                    abort(403, "Unauthorized tenant access: Institute ID {$headerId} is outside your authorized scope.");
                }
                return (int) $mcpContext->selectedInstituteId;
            }
        }

        // 2. Check verified JWT authentication payload from McpAuth middleware
        if (is_array($auth) && !empty($auth['sub_institute_id'])) {
            $parsed = array_values(array_filter(array_map('intval', explode(',', (string) $auth['sub_institute_id']))));
            if (!empty($parsed)) {
                $allowedInstituteIds = array_unique(array_merge($allowedInstituteIds, $parsed));
            }
        }

        // 3. Check Laravel authenticated session
        $sessionInstitute = session()->get('sub_institute_id');
        if ($sessionInstitute && is_numeric($sessionInstitute) && (int) $sessionInstitute > 0) {
            $allowedInstituteIds[] = (int) $sessionInstitute;
        }

        // 4. Check authenticated user object
        if (auth()->check() && !empty(auth()->user()->sub_institute_id)) {
            $allowedInstituteIds[] = (int) auth()->user()->sub_institute_id;
        }

        $allowedInstituteIds = array_values(array_unique(array_filter($allowedInstituteIds)));

        // 5. If the request explicitly requested an institute, verify server-side authorization
        $requestedInstitute = 0;
        $headerId = $request->header('X-MCP-Institute-Id');
        if ($headerId && is_numeric($headerId)) {
            $requestedInstitute = (int) $headerId;
        } elseif ($request->has('sub_institute_id') && is_numeric($request->input('sub_institute_id'))) {
            $requestedInstitute = (int) $request->input('sub_institute_id');
        }

        if ($requestedInstitute > 0) {
            $isAdmin = (int) ($auth['is_admin'] ?? (auth()->user()?->is_admin ?? 0));
            if (in_array($requestedInstitute, $allowedInstituteIds, true) || $isAdmin === 2) {
                return $requestedInstitute;
            }

            abort(403, "Unauthorized tenant access: Institute ID {$requestedInstitute} is outside your authorized scope.");
        }

        // 6. Default to the primary authorized institute for this tenant
        if (!empty($allowedInstituteIds)) {
            return (int) $allowedInstituteIds[0];
        }

        // 7. If no authorized tenant exists, refuse access with 401 Unauthorized (never hardcoded fallback)
        abort(401, 'Unauthenticated: Valid tenant institute context could not be resolved from authenticated session or JWT.');
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

    /**
     * POST /api/ai/teach-learn/catalogue-agent/pipeline
     * Executes the synchronized 3-agent pipeline:
     *   Step 1: Course Catalogue Agent -> Gate 1
     *   Step 2: Activity Timeline Agent -> Gate 2
     *   Step 3: Content Model Coverage Agent -> Gate 3 -> Unified Report.
     */
    public function pipeline(Request $request): JsonResponse
    {
        try {
            $instituteId = $this->resolveInstituteId($request);
            $options = $request->all();
            $options['sub_institute_id'] = $instituteId; // Enforce dynamic authenticated tenant

            $report = $this->pipelineService->runPipeline($instituteId, $options);

            if (!($report['success'] ?? false)) {
                return $this->failure(
                    $report['error'] ?? 'Pipeline execution failed at validation gate.',
                    422,
                    $report
                );
            }

            return $this->success('Teach/Learn 3-agent synchronized pipeline completed successfully.', [
                'report' => $report,
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }

    /**
     * GET /api/ai/teach-learn/catalogue-agent/eso
     * Returns the complete Executive Skill Object (ESO) for all three agents.
     */
    public function eso(Request $request): JsonResponse
    {
        try {
            $instituteId = $this->resolveInstituteId($request);
            $eso = $this->pipelineService->getExpertSystemOverview($instituteId);

            return $this->success('Teach/Learn Executive Skill Object (ESO) loaded successfully.', [
                'eso' => $eso,
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }

    /**
     * GET /api/ai/teach-learn/catalogue-agent/standards
     * Returns real database standards for the active institute.
     */
    public function standards(Request $request): JsonResponse
    {
        try {
            $instituteId = $this->resolveInstituteId($request);
            $standards = DB::table('standard')
                ->where('sub_institute_id', $instituteId)
                ->select(['id', 'name', 'grade_id'])
                ->orderBy('id')
                ->get();

            return $this->success('Standards retrieved successfully.', [
                'standards' => $standards,
                'count' => count($standards),
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }

    /**
     * GET /api/ai/teach-learn/catalogue-agent/subjects
     * Returns real subjects mapped to the selected standard(s) where allow_content = 'Yes'.
     */
    public function subjects(Request $request): JsonResponse
    {
        try {
            $instituteId = $this->resolveInstituteId($request);
            $standardIds = $request->input('standard_ids') ?: $request->input('standard_id');
            if (is_string($standardIds)) {
                $standardIds = array_filter(array_map('intval', explode(',', $standardIds)));
            } elseif (is_numeric($standardIds)) {
                $standardIds = [(int) $standardIds];
            } elseif (!is_array($standardIds)) {
                $standardIds = [];
            }

            $query = DB::table('sub_std_map as m')
                ->join('subject as s', 's.id', '=', 'm.subject_id')
                ->where('m.sub_institute_id', $instituteId)
                ->where('m.allow_content', 'Yes');

            if (!empty($standardIds)) {
                $query->whereIn('m.standard_id', $standardIds);
            }

            $subjects = $query->select([
                's.id as subject_id',
                DB::raw('COALESCE(m.display_name, s.subject_name) as subject_name'),
                'm.standard_id',
            ])->distinct()->orderBy('subject_name')->get();

            return $this->success('Subjects retrieved successfully.', [
                'subjects' => $subjects,
                'count' => count($subjects),
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }

    /**
     * GET /api/ai/teach-learn/catalogue-agent/chapters
     * Returns real chapters for the selected subject(s) and standard(s).
     */
    public function chapters(Request $request): JsonResponse
    {
        try {
            $instituteId = $this->resolveInstituteId($request);
            $standardIds = $request->input('standard_ids') ?: $request->input('standard_id');
            if (is_string($standardIds)) {
                $standardIds = array_filter(array_map('intval', explode(',', $standardIds)));
            } elseif (is_numeric($standardIds)) {
                $standardIds = [(int) $standardIds];
            } elseif (!is_array($standardIds)) {
                $standardIds = [];
            }

            $subjectIds = $request->input('subject_ids') ?: $request->input('subject_id');
            if (is_string($subjectIds)) {
                $subjectIds = array_filter(array_map('intval', explode(',', $subjectIds)));
            } elseif (is_numeric($subjectIds)) {
                $subjectIds = [(int) $subjectIds];
            } elseif (!is_array($subjectIds)) {
                $subjectIds = [];
            }

            $query = DB::table('chapter_master')
                ->where('sub_institute_id', $instituteId)
                ->where('show_hide', 1);

            if (!empty($standardIds)) {
                $query->whereIn('standard_id', $standardIds);
            }
            if (!empty($subjectIds)) {
                $query->whereIn('subject_id', $subjectIds);
            }

            $chapters = $query->select([
                'id',
                'chapter_name',
                'sort_order',
                'standard_id',
                'subject_id',
            ])->orderBy('sort_order')->orderBy('chapter_name')->get();

            return $this->success('Chapters retrieved successfully.', [
                'chapters' => $chapters,
                'count' => count($chapters),
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }

    /**
     * POST /api/ai/teach-learn/catalogue-agent/check-content
     * Checks whether content already exists for the selected Standard, Subject, and Chapter.
     */
    public function checkContent(Request $request): JsonResponse
    {
        try {
            $instituteId = $this->resolveInstituteId($request);
            $standardId = (int) ($request->input('standard_id') ?: 0);
            $subjectId = $request->input('subject_id') ? (int) $request->input('subject_id') : null;
            $chapterId = $request->input('chapter_id') ? (int) $request->input('chapter_id') : null;

            $coverage = $this->contentAgentService->checkChapterContentCoverage(
                $instituteId,
                $standardId,
                $subjectId,
                $chapterId
            );

            return $this->success('Content coverage checked successfully.', [
                'coverage' => $coverage,
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }
}
