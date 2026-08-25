<?php

namespace App\Http\Controllers\AI;

use App\Domain\AI\Agents\AgentManifest;
use App\Domain\AI\Agents\AgentRegistry;
use App\Domain\AI\Agents\AgentRunner;
use Illuminate\Http\Request;
use Throwable;

/**
 * Lists and runs agents.
 *
 * `run` is synchronous today because the estate's queue is only just being switched
 * to a database driver; a run is bounded by the manifest's timeout and by the
 * detectors' own limits, so it stays inside a request. Once a worker is running,
 * this is the one place that needs to change to dispatch instead.
 */
class AgentController extends AiController
{
    public function __construct(
        private readonly AgentRegistry $registry,
        private readonly AgentRunner $runner,
    ) {
    }

    public function index(Request $request)
    {
        try {
            $scope = $this->scope($request);

            $agents = $this->registry->forRole(
                $scope->role,
                $scope->selectedInstituteId,
                $request->input('domain')
            );

            return $this->success('Agents loaded.', [
                'agents' => array_map(
                    fn (AgentManifest $manifest) => $manifest->toArray(),
                    $agents
                ),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    public function show(Request $request, string $agent)
    {
        try {
            $scope = $this->scope($request);
            $manifest = $this->registry->find($agent, $scope->selectedInstituteId);

            if (! $manifest || ! $manifest->permitsRole($scope->role)) {
                return $this->failure('No such agent.', 404);
            }

            return $this->success('Agent loaded.', [
                'agent' => $manifest->toArray(),
                'runs' => $this->runner->runs($scope, $agent, 20),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    public function run(Request $request, string $agent)
    {
        try {
            $scope = $this->scope($request);

            $validated = $request->validate([
                'subject_id' => 'nullable|integer|min:1',
                'student_ids' => 'nullable|array|max:200',
                'student_ids.*' => 'integer|min:1',
                'limit' => 'nullable|integer|min:1|max:200',
            ]);

            $result = $this->runner->run(
                $agent,
                $scope,
                $validated,
                'manual',
                'api'
            );

            if ($result['status'] === 'rejected') {
                return $this->failure($result['summary'], 403);
            }

            return $this->success($result['summary'], $result);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    public function runs(Request $request)
    {
        try {
            $scope = $this->scope($request);

            return $this->success('Agent runs loaded.', [
                'runs' => $this->runner->runs($scope, $request->input('agent_key'), $this->limit($request)),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }
}
