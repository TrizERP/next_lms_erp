<?php

namespace App\Http\Controllers\AI;

use App\Services\AI\Attendance\AttendanceAgentPipelineService;
use App\Services\AI\Pipeline\ModuleAgentPipeline;
use App\Services\AI\Pipeline\PipelineHistoryService;
use App\Services\AI\Users\UsersAgentPipelineService;
use App\Services\Mcp\AdministratorCheck;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * `/api/ai/{users|attendance}/agent/{state,eso,pipeline}` — the gated, one-agent-per-request
 * pipelines for the Users and Attendance AI Stack Automations tab.
 *
 * The institute and the caller come from the authenticated MCP context, never from the body.
 * There is no start/stop/execute route: these pipelines read, analyse and draft. Anything that
 * would change a record or send a message stays a human-approved action elsewhere.
 *
 *   Users       administrators only — it reports on named colleagues' accounts and access.
 *   Attendance  staff (students are refused) — the same audience the attendance tools serve.
 */
class ModuleAgentPipelineController extends AiController
{
    public function __construct(
        private readonly UsersAgentPipelineService $users,
        private readonly AttendanceAgentPipelineService $attendance,
    ) {
    }

    public function state(Request $request, string $module): JsonResponse
    {
        try {
            [$pipeline, $scope] = $this->resolve($request, $module, false);

            $state = $pipeline->liveState($scope->selectedInstituteId, $scope, $request->only(['academic_year']));
            $state['automation'] = $pipeline->autoState($scope->selectedInstituteId);
            $this->ensureWorkerWhileActive($state['automation']);

            return $this->success(ucfirst($module) . ' agent state loaded.', ['state' => $state]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }

    /**
     * While an automation is active, a detached worker must be alive to run its cycles with no
     * screen open and no scheduler on the host. Starting it is idempotent (a heartbeat says whether
     * one is already running), so every state poll can safely ask.
     *
     * @param array<string,mixed> $automation
     */
    private function ensureWorkerWhileActive(array $automation): void
    {
        if (! empty($automation['active'])) {
            \App\Services\AI\Pipeline\BackgroundTick::ensureWorker();
        }
    }

    /** Start the automation, then make sure the worker that keeps it going is running. */
    private function startAndKeepAlive(ModuleAgentPipeline $pipeline, McpRequestContext $scope, ?int $interval, array $options): array
    {
        $automation = $pipeline->startAuto($scope->selectedInstituteId, $scope, $interval, $options);
        \App\Services\AI\Pipeline\BackgroundTick::ensureWorker();

        return $automation;
    }

    /**
     * Start the continuous automation: from now on the whole agent sequence runs, cycle after
     * cycle, on the latest database data until stopped. An administrator's decision, like Fees.
     */
    public function start(Request $request, string $module): JsonResponse
    {
        try {
            [$pipeline, $scope] = $this->resolve($request, $module, true, true);
            $interval = $request->filled('interval_seconds') ? (int) $request->input('interval_seconds') : null;

            return $this->success(ucfirst($module) . ' automation started.', [
                'automation' => $this->startAndKeepAlive($pipeline, $scope, $interval, $request->only(['days', 'threshold', 'limit'])),
            ]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }

    /** Stop the automation. Any cycle in flight is cancelled before its next agent. */
    public function stop(Request $request, string $module): JsonResponse
    {
        try {
            [$pipeline, $scope] = $this->resolve($request, $module, true, true);

            return $this->success(ucfirst($module) . ' automation stopped.', [
                'automation' => $pipeline->stopAuto($scope->selectedInstituteId, $scope),
            ]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }

    public function interval(Request $request, string $module): JsonResponse
    {
        try {
            [$pipeline, $scope] = $this->resolve($request, $module, true, true);
            $seconds = (int) $request->validate(['interval_seconds' => 'required|integer|min:' . ModuleAgentPipeline::AUTO_MIN_INTERVAL . '|max:' . ModuleAgentPipeline::AUTO_MAX_INTERVAL])['interval_seconds'];

            return $this->success('Interval updated.', ['automation' => $pipeline->setAutoInterval($scope->selectedInstituteId, $seconds)]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }

    /** Final outputs (drafts, findings, summary) with live approval status, for the Admin Dashboard. */
    public function outputs(Request $request, string $module): JsonResponse
    {
        try {
            [$pipeline, $scope] = $this->resolve($request, $module, false);
            $history = app(PipelineHistoryService::class);

            return $this->success(ucfirst($module) . ' agent outputs loaded.', [
                'module' => $module,
                'outputs' => $history->outputs($pipeline, $scope->selectedInstituteId, (int) $request->input('limit', 5)),
                'roster' => $history->roster($pipeline, $scope->selectedInstituteId),
            ]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }

    public function eso(Request $request, string $module): JsonResponse
    {
        try {
            [$pipeline, $scope] = $this->resolve($request, $module, false);

            return $this->success(ucfirst($module) . ' agent specification loaded.', ['eso' => [
                'module' => $pipeline->module(),
                'agents' => array_values($pipeline->agents()),
                'execution_order' => array_map(static fn ($a) => $a['name'], array_values($pipeline->agents())),
                'safety' => 'Read, analyse and draft only. No record is written, no message is sent, and the institute always comes from the signed-in session.',
            ]]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }

    public function pipeline(Request $request, string $module): JsonResponse
    {
        try {
            [$pipeline, $scope] = $this->resolve($request, $module, true);
            $options = $request->only(['step', 'run_id', 'academic_year', 'days', 'threshold', 'limit', 'persist', 'auto']);
            $report = $pipeline->runPipeline($scope->selectedInstituteId, $scope, $options);

            if (! ($report['success'] ?? false)) {
                return $this->failure($report['error'] ?? 'The pipeline stopped at a quality gate.', 422, $report);
            }

            return $this->success(ucfirst($module) . ' agent pipeline executed.', ['report' => $report]);
        } catch (Throwable $e) {
            return $this->handle($e);
        }
    }

    /** @return array{0: ModuleAgentPipeline, 1: McpRequestContext} */
    private function resolve(Request $request, string $module, bool $runs, bool $adminOnly = false): array
    {
        $scope = $this->scope($request);

        if ($scope->role === 'student' || $scope->isStudent) {
            throw new AuthorizationException('This agent pipeline is not available to student accounts.');
        }

        $pipeline = match ($module) {
            'users' => $this->users,
            'attendance' => $this->attendance,
            default => throw new \RuntimeException('Unknown agent pipeline.'),
        };

        // Users reports on named colleagues' accounts and access, so even reading it is an
        // administrator's. AdministratorCheck, not the raw role string: `tbluser.is_admin` is
        // empty for real admins on this estate (see its own docblock).
        if ($module === 'users' && ! app(AdministratorCheck::class)->isAdministrator($scope)) {
            throw new AuthorizationException('Only an administrator can use the Users agent pipeline.');
        }

        if ($adminOnly && ! app(AdministratorCheck::class)->isAdministrator($scope)) {
            throw new AuthorizationException('Only an administrator can start or stop the agent automation.');
        }

        return [$pipeline, $scope];
    }
}
