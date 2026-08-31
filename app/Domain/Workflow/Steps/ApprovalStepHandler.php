<?php

namespace App\Domain\Workflow\Steps;

use App\Domain\Workflow\StepHandler;
use App\Domain\Workflow\StepResult;
use App\Domain\Workflow\WorkflowRunContext;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The approval step: park the run and wait for a person.
 *
 * Reading it is not consequential — creating a request for approval changes nothing
 * in the world — so `isConsequential()` is false. The gate that matters sits on the
 * *action* steps downstream, which is the right place for it: a workflow could
 * legitimately ask for approval and then do nothing.
 *
 * The step is idempotent. Re-advancing a parked run finds the existing pending
 * approval rather than creating a second one, which matters because `advance()` is
 * called by retries and by queued jobs.
 */
class ApprovalStepHandler implements StepHandler
{
    public function type(): string
    {
        return 'approval';
    }

    public function isConsequential(array $config): bool
    {
        return false;
    }

    public function handle(
        array $config,
        array $state,
        WorkflowRunContext $run,
        McpRequestContext $scope
    ): StepResult {
        if (! Schema::hasTable('workflow_approvals')) {
            return StepResult::failed('The approval store is unavailable.');
        }

        $stepKey = (string) ($config['step_key'] ?? $config['key'] ?? 'approval');

        $existing = DB::table('workflow_approvals')
            ->where('run_id', $run->runId)
            ->where('step_key', $stepKey)
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            if ($existing->status === 'approved') {
                return StepResult::completed([
                    'approved' => true,
                    'approval_id' => (int) $existing->id,
                    'decided_by' => $existing->decided_by,
                    'decided_at' => $existing->decided_at,
                ], 'Approved.');
            }

            if ($existing->status === 'rejected') {
                return StepResult::failed('The workflow was rejected at the approval step.');
            }

            if ($existing->status === 'expired') {
                return StepResult::failed('The approval request expired.');
            }

            return StepResult::paused(
                $this->pauseMessage($config),
                ['approval_id' => (int) $existing->id, 'status' => 'pending']
            );
        }

        $expiresAt = isset($config['expires_in_hours']) && is_numeric($config['expires_in_hours'])
            ? now()->addHours((int) $config['expires_in_hours'])
            : null;

        $approvalId = (int) DB::table('workflow_approvals')->insertGetId([
            'run_id' => $run->runId,
            'step_key' => $stepKey,
            'approver_role' => $config['approver_role'] ?? null,
            'assigned_to' => $this->resolveAssignee($config, $state),
            'assigned_to_name' => isset($config['assigned_to_name'])
                ? mb_substr((string) $config['assigned_to_name'], 0, 150)
                : null,
            'status' => 'pending',
            'expires_at' => $expiresAt,
            'sub_institute_id' => $scope->selectedInstituteId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return StepResult::paused(
            $this->pauseMessage($config),
            ['approval_id' => $approvalId, 'status' => 'pending']
        );
    }

    /**
     * The assignee may be named directly or read from the run state (for example,
     * the class teacher resolved by an earlier step).
     */
    private function resolveAssignee(array $config, array $state): ?int
    {
        if (isset($config['assigned_to']) && is_numeric($config['assigned_to'])) {
            return (int) $config['assigned_to'];
        }

        $path = $config['assigned_to_from'] ?? null;

        if (! is_string($path) || $path === '') {
            return null;
        }

        $value = $state;

        foreach (explode('.', $path) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private function pauseMessage(array $config): string
    {
        $role = $config['approver_role'] ?? null;

        return $role
            ? sprintf('Waiting for %s approval.', $role)
            : 'Waiting for approval.';
    }
}
