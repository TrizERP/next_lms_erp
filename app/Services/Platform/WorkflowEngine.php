<?php

namespace App\Services\Platform;

use App\Models\Platform\PlatformWorkflow;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Runs an approval chain against a real record.
 *
 * The chain itself is configured in platform_workflows (WorkflowController); this
 * class is the half that executes it. A run is one request for sign-off, split
 * into one row per step. Only one step is `pending` at a time: approving it opens
 * the next, rejecting it ends the run according to the chain's `on_reject`.
 *
 * ANYONE-WITH-RIGHTS RULE. A step names an approver type. `user` is enforced here
 * (only that user may act). `role`, `principal`, `class_teacher` and
 * `reporting_manager` are resolved from tables this service does not own, so for
 * those the route's `perm:platform.workflow` check is the gate and the acting
 * user is recorded against the step. The run says so in its step rows
 * (assignee_user_id stays null) rather than pretending it resolved a person.
 */
class WorkflowEngine
{
    /** @return array<string,mixed> the run, with its steps */
    public function start(
        int $tenantId,
        string $flowKey,
        string $entityType,
        string $entityId,
        ?int $requestedBy,
        ?string $requestedByName,
        ?string $title = null,
        array $payload = [],
        bool $isSample = false
    ): array {
        $workflow = PlatformWorkflow::forTenant($tenantId)
            ->where('flow_key', $flowKey)
            ->where('status', 'active')
            ->orderByDesc('id')
            ->first();

        if (! $workflow) {
            throw new InvalidArgumentException("There is no active approval chain for {$flowKey}. Activate one in Approval workflows first.");
        }

        $steps = array_values((array) $workflow->steps);
        if ($steps === []) {
            throw new InvalidArgumentException('That approval chain has no steps.');
        }

        return DB::transaction(function () use ($tenantId, $workflow, $flowKey, $entityType, $entityId, $requestedBy, $requestedByName, $title, $payload, $isSample, $steps) {
            $now = now();
            $runId = DB::table('platform_workflow_runs')->insertGetId([
                'sub_institute_id' => $tenantId,
                'workflow_id' => $workflow->id,
                'flow_key' => $flowKey,
                'entity_type' => mb_substr($entityType, 0, 128),
                'entity_id' => mb_substr($entityId, 0, 128),
                'title' => $title !== null ? mb_substr($title, 0, 255) : null,
                'payload' => json_encode($payload),
                'requested_by' => $requestedBy,
                'requested_by_name' => $requestedByName,
                'status' => 'pending',
                'current_step' => 1,
                'is_sample' => $isSample ? 1 : 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($steps as $i => $step) {
                $order = $i + 1;
                $sla = (int) ($step['sla_hours'] ?? 0);
                DB::table('platform_workflow_run_steps')->insert([
                    'run_id' => $runId,
                    'step_order' => $order,
                    'step_name' => (string) ($step['name'] ?? 'Step '.$order),
                    'approver_type' => (string) ($step['approver_type'] ?? 'role'),
                    'approver' => (string) ($step['approver'] ?? ''),
                    'status' => $order === 1 ? 'pending' : 'waiting',
                    'assignee_user_id' => ($step['approver_type'] ?? '') === 'user' && is_numeric($step['approver'] ?? null)
                        ? (int) $step['approver'] : null,
                    'due_at' => $order === 1 && $sla > 0 ? $now->copy()->addHours($sla) : null,
                    'on_breach' => (string) ($step['on_breach'] ?? 'none'),
                    'allow_delegate' => ! empty($step['allow_delegate']) ? 1 : 0,
                    'require_comment' => ! empty($step['require_comment']) ? 1 : 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            return $this->find($tenantId, $runId);
        });
    }

    /** @return array<string,mixed>|null */
    public function find(int $tenantId, int $runId): ?array
    {
        $run = DB::table('platform_workflow_runs')->where('sub_institute_id', $tenantId)->where('id', $runId)->first();
        if (! $run) {
            return null;
        }
        $steps = DB::table('platform_workflow_run_steps')->where('run_id', $runId)->orderBy('step_order')->get();

        return $this->present($run, $steps->all());
    }

    /**
     * @param  'approve'|'reject'|'delegate'  $action
     * @return array<string,mixed>
     */
    public function act(int $tenantId, int $runId, string $action, int $userId, ?string $userName, ?string $comment, ?int $delegateTo = null): array
    {
        return DB::transaction(function () use ($tenantId, $runId, $action, $userId, $userName, $comment, $delegateTo) {
            $run = DB::table('platform_workflow_runs')
                ->where('sub_institute_id', $tenantId)->where('id', $runId)->lockForUpdate()->first();
            if (! $run) {
                throw new InvalidArgumentException('That approval request was not found.');
            }
            if ($run->status !== 'pending') {
                throw new InvalidArgumentException("This request is already {$run->status}.");
            }

            $step = DB::table('platform_workflow_run_steps')
                ->where('run_id', $runId)->where('status', 'pending')->orderBy('step_order')->lockForUpdate()->first();
            if (! $step) {
                throw new InvalidArgumentException('This request has no step waiting for a decision.');
            }

            if ($step->assignee_user_id !== null && (int) $step->assignee_user_id !== $userId) {
                throw new InvalidArgumentException('This step is assigned to someone else.');
            }
            if ($step->require_comment && trim((string) $comment) === '' && $action !== 'delegate') {
                throw new InvalidArgumentException('This step needs a comment with the decision.');
            }

            $now = now();

            if ($action === 'delegate') {
                if (! $step->allow_delegate) {
                    throw new InvalidArgumentException('This step cannot be delegated.');
                }
                if (! $delegateTo || $delegateTo === $userId) {
                    throw new InvalidArgumentException('Choose another person to delegate to.');
                }
                DB::table('platform_workflow_run_steps')->where('id', $step->id)->update([
                    'assignee_user_id' => $delegateTo,
                    'comment' => trim("Delegated by {$userName}. ".(string) $comment),
                    'updated_at' => $now,
                ]);

                return $this->find($tenantId, $runId);
            }

            $approved = $action === 'approve';
            DB::table('platform_workflow_run_steps')->where('id', $step->id)->update([
                'status' => $approved ? 'approved' : 'rejected',
                'acted_by' => $userId,
                'acted_by_name' => $userName,
                'acted_at' => $now,
                'comment' => $comment,
                'updated_at' => $now,
            ]);

            if (! $approved) {
                $workflow = PlatformWorkflow::forTenant($tenantId)->find($run->workflow_id);
                $outcome = ($workflow->on_reject ?? 'return_to_requester') === 'close' ? 'rejected' : 'returned';
                $this->finish($runId, $outcome, $now);
            } else {
                $this->advance($runId, (int) $step->step_order, $now);
            }

            return $this->find($tenantId, $runId);
        });
    }

    /**
     * Steps past their SLA. `auto_approve`, `escalate_to_next` and `notify` are
     * the escalation actions the chain can name; `none` leaves the step alone.
     *
     * @return int number of steps acted on
     */
    public function escalateOverdue(): int
    {
        $due = DB::table('platform_workflow_run_steps as s')
            ->join('platform_workflow_runs as r', 'r.id', '=', 's.run_id')
            ->where('s.status', 'pending')
            ->whereNotNull('s.due_at')
            ->where('s.due_at', '<', now())
            ->whereNull('s.escalated_at')
            ->where('s.on_breach', '!=', 'none')
            ->where('r.status', 'pending')
            ->select('s.*', 'r.sub_institute_id as tenant', 'r.requested_by')
            ->get();

        $count = 0;
        foreach ($due as $step) {
            DB::transaction(function () use ($step, &$count) {
                $now = now();
                DB::table('platform_workflow_run_steps')->where('id', $step->id)->update([
                    'escalated_at' => $now,
                    'updated_at' => $now,
                ]);

                if ($step->on_breach === 'auto_approve') {
                    DB::table('platform_workflow_run_steps')->where('id', $step->id)->update([
                        'status' => 'approved',
                        'acted_by_name' => 'Auto-approved after SLA',
                        'acted_at' => $now,
                    ]);
                    $this->advance((int) $step->run_id, (int) $step->step_order, $now);
                } elseif ($step->on_breach === 'escalate_to_next') {
                    DB::table('platform_workflow_run_steps')->where('id', $step->id)->update(['status' => 'escalated']);
                    $this->advance((int) $step->run_id, (int) $step->step_order, $now);
                }

                if (class_exists(AuditTrail::class)) {
                    AuditTrail::record((int) $step->tenant, 'platform', 'workflow', 'escalated', [
                        'entity_type' => 'workflow_run',
                        'entity_id' => $step->run_id,
                        'after' => ['step' => $step->step_name, 'on_breach' => $step->on_breach],
                    ]);
                }
                $count++;
            });
        }

        return $count;
    }

    private function advance(int $runId, int $fromOrder, $now): void
    {
        $next = DB::table('platform_workflow_run_steps')
            ->where('run_id', $runId)->where('step_order', '>', $fromOrder)->where('status', 'waiting')
            ->orderBy('step_order')->first();

        if (! $next) {
            $this->finish($runId, 'approved', $now);

            return;
        }

        $steps = DB::table('platform_workflow_runs as r')->join('platform_workflows as w', 'w.id', '=', 'r.workflow_id')
            ->where('r.id', $runId)->value('w.steps');
        $config = array_values((array) json_decode((string) $steps, true))[$next->step_order - 1] ?? [];
        $sla = (int) ($config['sla_hours'] ?? 0);

        DB::table('platform_workflow_run_steps')->where('id', $next->id)->update([
            'status' => 'pending',
            'due_at' => $sla > 0 ? $now->copy()->addHours($sla) : null,
            'updated_at' => $now,
        ]);
        DB::table('platform_workflow_runs')->where('id', $runId)->update([
            'current_step' => $next->step_order,
            'updated_at' => $now,
        ]);
    }

    private function finish(int $runId, string $status, $now): void
    {
        DB::table('platform_workflow_runs')->where('id', $runId)->update([
            'status' => $status,
            'completed_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('platform_workflow_run_steps')
            ->where('run_id', $runId)->whereIn('status', ['waiting', 'pending'])->update(['status' => 'skipped', 'updated_at' => $now]);
    }

    /** @param  object  $run @param  array<int,object>  $steps */
    private function present(object $run, array $steps): array
    {
        return [
            'id' => (int) $run->id,
            'flow_key' => $run->flow_key,
            'entity_type' => $run->entity_type,
            'entity_id' => $run->entity_id,
            'title' => $run->title,
            'status' => $run->status,
            'current_step' => (int) $run->current_step,
            'requested_by' => $run->requested_by !== null ? (int) $run->requested_by : null,
            'requested_by_name' => $run->requested_by_name,
            'is_sample' => (bool) $run->is_sample,
            'created_at' => $run->created_at,
            'completed_at' => $run->completed_at,
            'steps' => array_map(fn ($s) => [
                'id' => (int) $s->id,
                'order' => (int) $s->step_order,
                'name' => $s->step_name,
                'approver_type' => $s->approver_type,
                'approver' => $s->approver,
                'status' => $s->status,
                'assignee_user_id' => $s->assignee_user_id !== null ? (int) $s->assignee_user_id : null,
                'acted_by_name' => $s->acted_by_name,
                'acted_at' => $s->acted_at,
                'comment' => $s->comment,
                'due_at' => $s->due_at,
                'escalated_at' => $s->escalated_at,
                'allow_delegate' => (bool) $s->allow_delegate,
                'require_comment' => (bool) $s->require_comment,
            ], $steps),
        ];
    }
}
