<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\AI\Concerns\RequiresFeesStaffAccess;
use App\Services\AI\Fees\FeesAgentPipelineService as GovernedFeesPipelineService;
use App\Services\Fees\FeesAgentPipelineService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Web API for the Fees sequential 3-agent pipeline AND continuous governed monitoring.
 *
 * Supports:
 *   1. Continuous monitoring & loop state (start, stop, interval, state)
 *   2. Sequential 3-Agent Pipeline Execution (Position Reader -> Evidence Analyst -> Reminder Drafter)
 *   3. Executive Skill Object (ESO) with EK, EA, ER, EQ pillars
 *   4. Live Standards List, Defaulter Audit Ledger, and Human-Approved Reminder Dispatch
 *
 * Role gating via RequiresFeesStaffAccess ensures students are refused on every endpoint
 * and non-admin staff cannot trigger operational mutations or send reminders.
 */
class FeesAgentController extends AiController
{
    use RequiresFeesStaffAccess;

    public function __construct(
        private readonly FeesAgentPipelineService $pipeline,
        private readonly GovernedFeesPipelineService $agenticPipeline,
    ) {
    }

    /**
     * GET /api/ai/fees/agent/state
     * Returns continuous loop state, live metrics, and recent pipeline runs.
     */
    public function state(Request $request): JsonResponse
    {
        try {
            $scope = $this->scope($request);
            $this->assertStaffAccess($scope);

            $requestedYear = $request->filled('academic_year') ? (int) $request->input('academic_year') : null;
            $explicitYear = $request->boolean('explicit_academic_year') || $request->filled('academic_year');
            $year = $this->pipeline->resolveAcademicYear(
                $scope->selectedInstituteId,
                $requestedYear,
                $scope->academicYear,
                $explicitYear
            );
            $agentState = $this->pipeline->getAgentState($scope->selectedInstituteId, $year['year']);
            $agenticState = $this->agenticPipeline->getAgentState($scope->selectedInstituteId);

            // Merge compatibility fields so both the Automations dashboard and Recommendation card work seamlessly.
            $mergedState = $this->mergeStateCompatibility($agentState, $agenticState, $year['year'], $year['source']);

            $yearContext = new \App\Services\Mcp\McpRequestContext(
                $scope->userId,
                $scope->role,
                $scope->selectedInstituteId,
                $scope->allowedInstituteIds ?: [$scope->selectedInstituteId],
                $scope->userProfileId,
                $scope->clientId,
                $year['year'] ?? $scope->academicYear,
                $scope->termId,
                $scope->isAdmin,
                $scope->isStudent
            );

            $this->ensureWorkerWhileActive($scope->selectedInstituteId);

            $includeLatest = $request->boolean('include_latest', true);
            $latestReport = $includeLatest && $year['year']
                ? $this->pipeline->getLatestReport($scope->selectedInstituteId, $yearContext, $year['year'])
                : null;

            return $this->success('Fees agent state loaded.', [
                'state' => $mergedState,
                'sub_institute_id' => $scope->selectedInstituteId,
                'academic_year' => $year['year'],
                'academic_year_source' => $year['source'],
                'available_years' => $this->pipeline->getAvailableAcademicYears($scope->selectedInstituteId),
                'recent_runs' => $this->pipeline->recentRuns($scope->selectedInstituteId, (int) $request->input('limit', 30)),
                'latest_report' => $latestReport,
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * While the loop is active, keep the detached worker alive so it keeps cycling with no screen open
     * and no scheduler on the host. Idempotent: a heartbeat says whether one is already running.
     */
    private function ensureWorkerWhileActive(int $inst): void
    {
        try {
            $active = \Illuminate\Support\Facades\DB::table('fees_agent_state')
                ->where('sub_institute_id', $inst)
                ->where('agent_key', GovernedFeesPipelineService::STATE_AGENT_KEY)
                ->where('is_active', true)
                ->exists();
            if ($active) {
                \App\Services\AI\Pipeline\BackgroundTick::ensureWorker();
            }
        } catch (Throwable) {
            // Never let the keep-alive break a state read.
        }
    }

    /**
     * POST /api/ai/fees/agent/start
     */
    public function start(Request $request): JsonResponse
    {
        try {
            $scope = $this->scope($request);
            $this->assertAdminAccess($scope);

            $intervalSeconds = (int) ($request->input('interval_seconds') ?: FeesAgentPipelineService::DEFAULT_INTERVAL_SECONDS);
            $intervalSeconds = max(10, min(86400, $intervalSeconds));

            $result = $this->pipeline->activateContinuousMonitoring($scope->selectedInstituteId, $intervalSeconds);
            $agenticResult = $this->agenticPipeline->activateContinuousMonitoring($scope->selectedInstituteId, max(60, $intervalSeconds));
            \App\Services\AI\Pipeline\BackgroundTick::ensureWorker();

            $agentState = $this->pipeline->getAgentState($scope->selectedInstituteId);
            $agenticState = $this->agenticPipeline->getAgentState($scope->selectedInstituteId);

            return $this->success('Fees agent continuous monitoring loop activated.', [
                'activation' => $agenticResult ?: $result,
                'state' => $this->mergeStateCompatibility($agentState, $agenticState),
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * POST /api/ai/fees/agent/stop
     */
    public function stop(Request $request): JsonResponse
    {
        try {
            $scope = $this->scope($request);
            $this->assertAdminAccess($scope);

            $result = $this->pipeline->deactivateContinuousMonitoring($scope->selectedInstituteId);
            $agenticResult = $this->agenticPipeline->deactivate($scope->selectedInstituteId);

            $agentState = $this->pipeline->getAgentState($scope->selectedInstituteId);
            $agenticState = $this->agenticPipeline->getAgentState($scope->selectedInstituteId);

            return $this->success('Fees agent continuous monitoring loop paused.', [
                'deactivation' => $agenticResult ?: $result,
                'state' => $this->mergeStateCompatibility($agentState, $agenticState),
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * POST /api/ai/fees/agent/run-now
     */
    public function runNow(Request $request): JsonResponse
    {
        try {
            $scope = $this->scope($request);
            $this->assertAdminAccess($scope);

            if ($request->input('mode') === 'sequential' || $request->filled('step')) {
                $report = $this->pipeline->runPipeline($scope->selectedInstituteId, $scope, $request->all());
                $agentState = $this->pipeline->getAgentState($scope->selectedInstituteId);
                $agenticState = $this->agenticPipeline->getAgentState($scope->selectedInstituteId);

                return $this->success('Fees sequential sweep completed.', [
                    'report' => $report,
                    'state' => $this->mergeStateCompatibility($agentState, $agenticState),
                    'server_time' => Carbon::now()->toIso8601String(),
                ]);
            }

            $report = $this->agenticPipeline->runPipeline($scope->selectedInstituteId, $request->all());
            $agentState = $this->pipeline->getAgentState($scope->selectedInstituteId);
            $agenticState = $this->agenticPipeline->getAgentState($scope->selectedInstituteId);

            return $this->success('Fees agent sweep completed.', [
                'report' => $report,
                'state' => $this->mergeStateCompatibility($agentState, $agenticState),
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * POST /api/ai/fees/agent/interval
     */
    public function setInterval(Request $request): JsonResponse
    {
        try {
            $scope = $this->scope($request);
            $this->assertAdminAccess($scope);

            $intervalSeconds = (int) ($request->input('interval_seconds') ?: FeesAgentPipelineService::DEFAULT_INTERVAL_SECONDS);
            $intervalSeconds = max(10, min(86400, $intervalSeconds));

            $this->pipeline->activateContinuousMonitoring($scope->selectedInstituteId, $intervalSeconds);
            $intervalResult = $this->agenticPipeline->setInterval($scope->selectedInstituteId, max(60, $intervalSeconds));

            $agentState = $this->pipeline->getAgentState($scope->selectedInstituteId);
            $agenticState = $this->agenticPipeline->getAgentState($scope->selectedInstituteId);

            return $this->success("Fees agent monitoring interval updated to {$intervalSeconds} seconds.", [
                'interval' => $intervalResult,
                'state' => $this->mergeStateCompatibility($agentState, $agenticState),
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * POST /api/ai/fees/agent/pipeline
     * Executes the synchronized 3-agent sequential pipeline (Step 1 -> Gate 1 -> Step 2 -> Gate 2 -> Step 3 -> Gate 3).
     */
    public function pipeline(Request $request): JsonResponse
    {
        try {
            $scope = $this->scope($request);
            $this->assertAdminAccess($scope);

            $options = $request->validate([
                'academic_year' => 'sometimes|nullable|integer|min:2000|max:2100',
                'explicit_academic_year' => 'sometimes|boolean',
                'standard_id' => 'sometimes|nullable|integer|min:1',
                'student_ids' => 'sometimes|nullable|array',
                'student_ids.*' => 'integer|min:1',
                'custom_amounts' => 'sometimes|nullable|array',
                'contact_overrides' => 'sometimes|nullable|array',
                'min_amount' => 'sometimes|nullable|numeric|min:0',
                'limit' => 'sometimes|nullable|integer|min:1|max:100',
                'evidence_limit' => 'sometimes|nullable|integer|min:0|max:15',
                'cooldown_days' => 'sometimes|nullable|integer|min:0|max:90',
                'period' => 'sometimes|nullable|string|max:60',
                'channel' => 'sometimes|nullable|in:email,sms,whatsapp',
                'deadline' => 'sometimes|nullable|date',
                'step' => 'sometimes|nullable|in:1,2,3,all',
                'run_id' => 'sometimes|nullable|string|max:64',
                'persist' => 'sometimes|boolean',
            ]);

            if (! empty($options['academic_year']) && ! array_key_exists('explicit_academic_year', $options)) {
                $options['explicit_academic_year'] = true;
            }

            $report = $this->pipeline->runPipeline(
                $scope->selectedInstituteId,
                $scope,
                array_filter($options, static fn ($v) => $v !== null)
            );

            if (! ($report['success'] ?? false)) {
                return $this->failure($report['error'] ?? 'Pipeline stopped at a validation gate.', 422, $report);
            }

            return $this->success('Fees 3-agent sequential pipeline completed.', [
                'report' => $report,
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * GET /api/ai/fees/agent/eso
     */
    public function eso(Request $request): JsonResponse
    {
        try {
            $scope = $this->scope($request);
            $this->assertStaffAccess($scope);

            $requestedYear = $request->filled('academic_year') ? (int) $request->input('academic_year') : null;
            $year = $this->pipeline->resolveAcademicYear(
                $scope->selectedInstituteId,
                $requestedYear,
                $scope->academicYear,
                $request->filled('academic_year')
            );

            return $this->success('Fees Executive Skill Object (ESO) loaded successfully.', [
                'eso' => $this->pipeline->getExpertSystemOverview(
                    $scope->selectedInstituteId,
                    $year['year']
                ),
                'agentic_eso' => $this->agenticPipeline->eso($scope->selectedInstituteId),
                'academic_year' => $year['year'],
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * GET /api/ai/fees/agent/standards
     */
    public function standards(Request $request): JsonResponse
    {
        try {
            $scope = $this->scope($request);
            $this->assertStaffAccess($scope);

            $standards = $this->pipeline->getStandards($scope->selectedInstituteId);

            return $this->success('Fees standards loaded successfully.', [
                'standards' => $standards,
                'count' => count($standards),
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * GET /api/ai/fees/agent/students
     */
    public function students(Request $request): JsonResponse
    {
        try {
            $scope = $this->scope($request);
            $this->assertStaffAccess($scope);

            $standardId = $request->filled('standard_id') && $request->input('standard_id') !== 'all'
                ? (int) $request->input('standard_id')
                : null;
            $limit = min(max((int) ($request->input('limit') ?: 50), 1), 100);
            $requestedYear = $request->filled('academic_year') ? (int) $request->input('academic_year') : null;
            $year = $this->pipeline->resolveAcademicYear(
                $scope->selectedInstituteId,
                $requestedYear,
                $scope->academicYear,
                $request->filled('academic_year')
            );

            $yearContext = new \App\Services\Mcp\McpRequestContext(
                $scope->userId,
                $scope->role,
                $scope->selectedInstituteId,
                $scope->allowedInstituteIds ?: [$scope->selectedInstituteId],
                $scope->userProfileId,
                $scope->clientId,
                $year['year'] ?? $scope->academicYear,
                $scope->termId,
                $scope->isAdmin,
                $scope->isStudent
            );

            $students = $this->pipeline->getStudentsForStandard($scope->selectedInstituteId, $yearContext, $standardId, $limit);

            return $this->success('Defaulter students loaded successfully.', [
                'students' => $students,
                'count' => count($students),
                'standard_id' => $standardId,
                'academic_year' => $year['year'],
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * POST /api/ai/fees/agent/send
     * Human-confirmed dispatch of previewed fee reminders after explicit administrator sign-off.
     */
    public function send(Request $request): JsonResponse
    {
        try {
            $scope = $this->scope($request);
            $this->assertAdminAccess($scope);

            $validated = $request->validate([
                'fingerprint' => 'required|string',
                'channel' => 'required|in:email,sms,whatsapp',
                'deadline' => 'required|date',
                'academic_year' => 'sometimes|nullable|integer|min:2000|max:2100',
                'student_ids' => 'sometimes|nullable|array',
                'student_ids.*' => 'integer|min:1',
                'custom_amounts' => 'sometimes|nullable|array',
                'contact_overrides' => 'sometimes|nullable|array',
                'standard_id' => 'sometimes|nullable|integer|min:1',
                'message' => 'sometimes|nullable|string|max:480',
            ]);

            $year = $this->pipeline->resolveAcademicYear(
                $scope->selectedInstituteId,
                isset($validated['academic_year']) ? (int) $validated['academic_year'] : null,
                $scope->academicYear,
                ! empty($validated['academic_year'])
            );
            $yearContext = new \App\Services\Mcp\McpRequestContext(
                $scope->userId,
                $scope->role,
                $scope->selectedInstituteId,
                $scope->allowedInstituteIds ?: [$scope->selectedInstituteId],
                $scope->userProfileId,
                $scope->clientId,
                $year['year'] ?? $scope->academicYear,
                $scope->termId,
                $scope->isAdmin,
                $scope->isStudent
            );

            $result = $this->pipeline->sendApprovedReminders($scope->selectedInstituteId, $yearContext, $validated);

            if (! ($result['success'] ?? false)) {
                return $this->failure($result['message'] ?? 'Failed to send reminders.', 422, $result['data'] ?? []);
            }

            return $this->success('Reminders dispatched successfully.', [
                'result' => $result['data'] ?? $result,
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * Combine sequential pipeline state and agentic recommendation state into one compatible object.
     *
     * @param  array<string, mixed>  $agenticState
     * @return array<string, mixed>
     */
    private function mergeStateCompatibility(object $agentState, array $agenticState, ?int $year = null, ?string $yearSource = null): array
    {
        $base = (array) $agentState;
        $liveDefaulters = (int) ($base['summary_counts']['defaulters_count'] ?? 0);
        $agenticCounts = is_array($agenticState['counts'] ?? null) ? $agenticState['counts'] : [];
        if (((int) ($agenticCounts['students_with_arrears'] ?? 0)) === 0 && $liveDefaulters > 0) {
            $agenticCounts['students_with_arrears'] = $liveDefaulters;
        }

        return array_merge($base, [
            'status' => $agenticState['status'] ?? (! empty($base['is_active']) ? 'active' : 'paused'),
            'last_run_time' => $agenticState['last_run_time'] ?? ($base['last_run_at'] ?? null),
            'next_scheduled_run' => $agenticState['next_scheduled_run'] ?? ($base['next_run_at'] ?? null),
            'counts' => $agenticCounts ?: [
                'students_with_arrears' => $liveDefaulters,
                'students_with_worsening_trend' => 0,
                'recommendations_drafted' => 0,
            ],
            'academic_year' => $year ?? ($base['academic_year'] ?? null),
            'academic_year_source' => $yearSource ?? ($base['academic_year_source'] ?? null),
        ]);
    }
}
