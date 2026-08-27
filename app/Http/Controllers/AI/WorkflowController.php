<?php

namespace App\Http\Controllers\AI;

use App\Domain\Workflow\WorkflowEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Workflow definitions, runs, and the approval steps inside them.
 *
 * Note there is no "execute this step" endpoint. A run advances only through
 * `advance` (which the engine drives) or through resolving an approval. There is
 * deliberately no way for a caller to reach past a pending approval and trigger the
 * step behind it.
 */
class WorkflowController extends AiController
{
    public function __construct(private readonly WorkflowEngine $engine)
    {
    }

    public function index(Request $request)
    {
        try {
            $scope = $this->scope($request);

            if (! Schema::hasTable('workflow_definitions')) {
                return $this->success('No workflows are configured.', ['workflows' => []]);
            }

            $workflows = DB::table('workflow_definitions')
                ->where('status', 1)
                ->where(function ($query) use ($scope) {
                    $query->whereNull('sub_institute_id')
                        ->orWhere('sub_institute_id', $scope->selectedInstituteId);
                })
                ->orderBy('name')
                ->get()
                ->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'workflow_key' => $row->workflow_key,
                    'name' => $row->name,
                    'domain' => $row->domain,
                    'module' => $row->module,
                    'description' => $row->description,
                    'trigger_type' => $row->trigger_type,
                    'requires_approval' => (bool) $row->requires_approval,
                    'is_consequential' => (bool) $row->is_consequential,
                ])
                ->all();

            return $this->success('Workflows loaded.', ['workflows' => $workflows]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    public function execute(Request $request, string $workflow)
    {
        try {
            $scope = $this->scope($request);

            $validated = $request->validate([
                'input' => 'nullable|array',
                'recommendation_id' => 'nullable|integer|min:1',
                'case_id' => 'nullable|integer|min:1',
                'subject_entity_key' => 'nullable|string|max:100',
                'subject_id' => 'nullable|integer|min:1',
            ]);

            $result = $this->engine->start(
                $workflow,
                $scope,
                $validated['input'] ?? [],
                array_filter([
                    'trigger_type' => 'manual',
                    'recommendation_id' => $validated['recommendation_id'] ?? null,
                    'case_id' => $validated['case_id'] ?? null,
                    'subject_entity_key' => $validated['subject_entity_key'] ?? null,
                    'subject_id' => $validated['subject_id'] ?? null,
                ], fn ($value) => $value !== null)
            );

            if ($result['status'] === 'rejected') {
                return $this->failure($result['message'], 403);
            }

            return $this->success($result['message'], $result);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    public function status(Request $request, int $run)
    {
        try {
            $status = $this->engine->status($run, $this->scope($request));

            if (! $status) {
                return $this->failure('No such workflow run.', 404);
            }

            return $this->success('Workflow run loaded.', ['run' => $status]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    public function runs(Request $request)
    {
        try {
            $scope = $this->scope($request);

            if (! Schema::hasTable('workflow_runs')) {
                return $this->success('No workflow runs.', ['runs' => []]);
            }

            $query = DB::table('workflow_runs')
                ->where('sub_institute_id', $scope->selectedInstituteId);

            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            if ($request->filled('workflow_key')) {
                $query->where('workflow_key', $request->input('workflow_key'));
            }

            $runs = $query->orderByDesc('id')
                ->limit($this->limit($request))
                ->get()
                ->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'reference' => $row->run_reference,
                    'workflow_key' => $row->workflow_key,
                    'status' => $row->status,
                    'current_step_key' => $row->current_step_key,
                    'subject_entity_key' => $row->subject_entity_key,
                    'subject_id' => $row->subject_id,
                    'started_at' => $row->started_at,
                    'finished_at' => $row->finished_at,
                    'error_message' => $row->error_message,
                ])
                ->all();

            return $this->success('Workflow runs loaded.', ['runs' => $runs]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * The approvals waiting on the current user, or on their role.
     */
    public function pendingApprovals(Request $request)
    {
        try {
            $scope = $this->scope($request);

            if (! Schema::hasTable('workflow_approvals')) {
                return $this->success('No pending approvals.', ['approvals' => []]);
            }

            $approvals = DB::table('workflow_approvals')
                ->join('workflow_runs', 'workflow_runs.id', '=', 'workflow_approvals.run_id')
                ->where('workflow_approvals.sub_institute_id', $scope->selectedInstituteId)
                ->where('workflow_approvals.status', 'pending')
                ->where(function ($query) use ($scope) {
                    // Assigned to me, to my role, or to nobody in particular.
                    $query->where('workflow_approvals.assigned_to', $scope->userId)
                        ->orWhereNull('workflow_approvals.assigned_to')
                        ->orWhere('workflow_approvals.approver_role', $scope->role);
                })
                ->orderBy('workflow_approvals.created_at')
                ->limit($this->limit($request))
                ->select(
                    'workflow_approvals.*',
                    'workflow_runs.workflow_key',
                    'workflow_runs.subject_entity_key',
                    'workflow_runs.subject_id',
                    'workflow_runs.recommendation_id',
                    'workflow_runs.case_id'
                )
                ->get()
                ->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'run_id' => (int) $row->run_id,
                    'workflow_key' => $row->workflow_key,
                    'step_key' => $row->step_key,
                    'approver_role' => $row->approver_role,
                    'assigned_to' => $row->assigned_to,
                    'subject_entity_key' => $row->subject_entity_key,
                    'subject_id' => $row->subject_id,
                    'recommendation_id' => $row->recommendation_id,
                    'case_id' => $row->case_id,
                    'expires_at' => $row->expires_at,
                    'created_at' => $row->created_at,
                ])
                ->all();

            return $this->success('Pending approvals loaded.', ['approvals' => $approvals]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    public function resolveApproval(Request $request, int $approval)
    {
        try {
            $scope = $this->scope($request);

            $validated = $request->validate([
                'decision' => 'required|string|in:approved,rejected',
                'comment' => 'nullable|string|max:2000',
                'modifications' => 'nullable|array',
            ]);

            $result = $this->engine->resolveApproval(
                $approval,
                $validated['decision'],
                $scope,
                $validated['comment'] ?? null,
                $validated['modifications'] ?? []
            );

            return $this->success($result['message'], $result);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }
}
