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

            /*
            | The cohort an agent run is scoped to.
            |
            | `subject_id`, `student_ids` and `limit` are the original four and are
            | unchanged. The rest are ADDITIVE and all nullable: an existing caller that
            | sends none of them produces exactly the request it produced before, and the
            | agents that do not read them ignore them.
            |
            | They exist because a run launched from a module's AI Stack has filters on
            | screen and had no way to send them. The Attendance agent in particular reads
            | a window of the register, and without `days` every run from the console used
            | the detector's own default — which on an estate whose marking is sparse
            | meant a sweep that could detect a student but never gather enough marked days
            | to support a recommendation, so nothing ever reached the approval queue.
            |
            | Bounded here rather than trusted: `days` cannot exceed a year, and the
            | institute and academic year still come from the token and are not parameters
            | at all.
            */
            $validated = $request->validate([
                'subject_id' => 'nullable|integer|min:1',
                'student_ids' => 'nullable|array|max:200',
                'student_ids.*' => 'integer|min:1',
                'limit' => 'nullable|integer|min:1|max:200',
                'days' => 'nullable|integer|min:1|max:365',
                'standard_id' => 'nullable|integer|min:1',
                'division_id' => 'nullable|integer|min:1',
                'min_attendance_rate' => 'nullable|numeric|min:0|max:100',
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
