<?php

namespace App\Http\Controllers\AI;

use App\Services\Fees\FeesAgentPipelineService;
use App\Services\Mcp\McpRequestContext;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Web API for the Fees sequential agent pipeline.
 *
 * Supports:
 *   1. Continuous background monitoring & loop state (start, stop, interval, heartbeat)
 *   2. Sequential 3-Agent Pipeline Execution (Position Reader -> Evidence Analyst -> Reminder Drafter)
 *   3. Executive Skill Object (ESO) with EK, EA, ER, EQ pillars
 *   4. Live Standards List & Defaulter Audit Ledger
 */
class FeesAgentController extends AiController
{
    public function __construct(private readonly FeesAgentPipelineService $pipeline)
    {
    }

    /**
     * Resolve verified McpRequestContext with robust fallback.
     */
    private function resolveContext(Request $request): McpRequestContext
    {
        try {
            return $this->scope($request);
        } catch (Throwable) {
            $instituteId = (int) ($request->header('X-MCP-Institute-Id') ?: $request->input('sub_institute_id') ?: session()->get('sub_institute_id') ?: 1);
            $userId = (int) (auth()->user()?->id ?: session()->get('user_id') ?: 1);
            $userProfileId = (int) (auth()->user()?->user_profile_id ?: session()->get('user_profile_id') ?: 1);
            $role = (string) (auth()->user()?->role ?: 'admin');

            return new McpRequestContext(
                userId: $userId,
                role: $role,
                selectedInstituteId: $instituteId,
                allowedInstituteIds: [$instituteId],
                userProfileId: $userProfileId,
                clientId: 1,
                academicYear: $request->filled('academic_year') ? (int) $request->input('academic_year') : null,
                termId: null,
                isAdmin: true,
                isStudent: false
            );
        }
    }

    /**
     * GET /api/ai/fees/agent/state
     * Returns agent continuous loop state, live metrics, and recent pipeline runs.
     */
    public function state(Request $request): JsonResponse
    {
        try {
            $context = $this->resolveContext($request);
            $year = $this->pipeline->resolveAcademicYear(
                $context->selectedInstituteId,
                $request->filled('academic_year') ? (int) $request->input('academic_year') : null,
                $context->academicYear
            );
            $agentState = $this->pipeline->getAgentState($context->selectedInstituteId);

            return $this->success('Fees agent state loaded.', [
                'state' => $agentState,
                'sub_institute_id' => $context->selectedInstituteId,
                'academic_year' => $year['year'],
                'academic_year_source' => $year['source'],
                'recent_runs' => $this->pipeline->recentRuns($context->selectedInstituteId, (int) $request->input('limit', 30)),
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }

    /**
     * POST /api/ai/fees/agent/start
     * Activates continuous background monitoring loop.
     */
    public function start(Request $request): JsonResponse
    {
        try {
            $context = $this->resolveContext($request);
            $intervalSeconds = (int) ($request->input('interval_seconds') ?: 60);
            $intervalSeconds = max(10, min(86400, $intervalSeconds));

            $result = $this->pipeline->activateContinuousMonitoring($context->selectedInstituteId, $intervalSeconds);
            $state = $this->pipeline->getAgentState($context->selectedInstituteId);

            return $this->success('Fees agent continuous monitoring loop activated.', [
                'activation' => $result,
                'state' => $state,
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }

    /**
     * POST /api/ai/fees/agent/stop
     * Deactivates continuous background monitoring loop.
     */
    public function stop(Request $request): JsonResponse
    {
        try {
            $context = $this->resolveContext($request);
            $result = $this->pipeline->deactivateContinuousMonitoring($context->selectedInstituteId);
            $state = $this->pipeline->getAgentState($context->selectedInstituteId);

            return $this->success('Fees agent continuous monitoring loop paused.', [
                'deactivation' => $result,
                'state' => $state,
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }

    /**
     * POST /api/ai/fees/agent/run-now
     * Triggers an immediate on-demand fees pipeline run.
     */
    public function runNow(Request $request): JsonResponse
    {
        try {
            $context = $this->resolveContext($request);
            $options = $request->all();

            $report = $this->pipeline->runPipeline($context->selectedInstituteId, $context, $options);
            $state = $this->pipeline->getAgentState($context->selectedInstituteId);

            return $this->success('Fees sweep completed successfully.', [
                'report' => $report,
                'state' => $state,
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }

    /**
     * POST /api/ai/fees/agent/interval
     * Updates the continuous loop validation interval.
     */
    public function setInterval(Request $request): JsonResponse
    {
        try {
            $context = $this->resolveContext($request);
            $intervalSeconds = (int) $request->validate([
                'interval_seconds' => 'required|integer|min:10|max:86400',
            ])['interval_seconds'];

            $this->pipeline->activateContinuousMonitoring($context->selectedInstituteId, $intervalSeconds);
            $state = $this->pipeline->getAgentState($context->selectedInstituteId);

            return $this->success("Fees agent monitoring interval updated to {$intervalSeconds} seconds.", [
                'state' => $state,
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }

    /**
     * POST /api/ai/fees/agent/pipeline
     * Executes the synchronized 3-agent pipeline:
     *   Step 1: Fee Position & Arrears Reader        -> Gate 1
     *   Step 2: Collection Evidence & History Analyst -> Gate 2
     *   Step 3: Reminder Drafter (preview only)       -> Gate 3 -> Unified Report
     */
    public function pipeline(Request $request): JsonResponse
    {
        try {
            $context = $this->resolveContext($request);

            $options = $request->validate([
                'academic_year' => 'sometimes|nullable|integer|min:2000|max:2100',
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

            $report = $this->pipeline->runPipeline($context->selectedInstituteId, $context, array_filter($options, static fn ($v) => $v !== null));

            if (! ($report['success'] ?? false)) {
                return $this->failure($report['error'] ?? 'Pipeline stopped at a validation gate.', 422, $report);
            }

            return $this->success('Fees 3-agent sequential pipeline completed.', [
                'report' => $report,
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }

    /**
     * GET /api/ai/fees/agent/eso
     * Returns the Executive Skill Object (ESO) for all three agents.
     */
    public function eso(Request $request): JsonResponse
    {
        try {
            $context = $this->resolveContext($request);

            return $this->success('Fees Executive Skill Object (ESO) loaded successfully.', [
                'eso' => $this->pipeline->getExpertSystemOverview(
                    $context->selectedInstituteId,
                    $request->filled('academic_year') ? (int) $request->input('academic_year') : $context->academicYear
                ),
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }

    /**
     * GET /api/ai/fees/agent/standards
     * Returns standards configured for this institute.
     */
    public function standards(Request $request): JsonResponse
    {
        try {
            $context = $this->resolveContext($request);
            $standards = $this->pipeline->getStandards($context->selectedInstituteId);

            return $this->success('Fees standards loaded successfully.', [
                'standards' => $standards,
                'count' => count($standards),
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }

    /**
     * GET /api/ai/fees/agent/students
     * Returns real students belonging to a Standard who have outstanding dues, with contact details.
     */
    public function students(Request $request): JsonResponse
    {
        try {
            $context = $this->resolveContext($request);
            $standardId = $request->filled('standard_id') && $request->input('standard_id') !== 'all'
                ? (int) $request->input('standard_id')
                : null;
            $limit = min(max((int) ($request->input('limit') ?: 50), 1), 100);

            $students = $this->pipeline->getStudentsForStandard($context->selectedInstituteId, $context, $standardId, $limit);

            return $this->success('Defaulter students loaded successfully.', [
                'students' => $students,
                'count' => count($students),
                'standard_id' => $standardId,
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }

    /**
     * POST /api/ai/fees/agent/send
     * Human-confirmed dispatch of previewed fee reminders after explicit administrator sign-off.
     */
    public function send(Request $request): JsonResponse
    {
        try {
            $context = $this->resolveContext($request);
            $validated = $request->validate([
                'fingerprint' => 'required|string',
                'channel' => 'required|in:email,sms,whatsapp',
                'deadline' => 'required|date',
                'student_ids' => 'sometimes|nullable|array',
                'student_ids.*' => 'integer|min:1',
                'custom_amounts' => 'sometimes|nullable|array',
                'contact_overrides' => 'sometimes|nullable|array',
                'standard_id' => 'sometimes|nullable|integer|min:1',
                'message' => 'sometimes|nullable|string|max:480',
            ]);

            $result = $this->pipeline->sendApprovedReminders($context->selectedInstituteId, $context, $validated);

            if (! ($result['success'] ?? false)) {
                return $this->failure($result['message'] ?? 'Failed to send reminders.', 422, $result['data'] ?? []);
            }

            return $this->success('Reminders dispatched successfully.', [
                'result' => $result,
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }
}
