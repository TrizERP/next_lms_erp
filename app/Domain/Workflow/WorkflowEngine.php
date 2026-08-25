<?php

namespace App\Domain\Workflow;

use App\Domain\AI\Support\AiAuditLogger;
use App\Domain\Governance\GovernanceValidator;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * The workflow engine.
 *
 * Configuration-driven: a workflow is a `workflow_versions.steps` document, and the
 * engine only knows how to walk a step graph, evaluate conditions, dispatch to a
 * handler, and park on an approval. Adding a business process means adding a
 * definition row, not code.
 *
 * Two invariants the engine enforces and no workflow author can waive:
 *
 *  1. A run pins its version at start. Editing the definition afterwards does not
 *     change what an in-flight run executes.
 *  2. Before any consequential step runs, GovernanceValidator::authorizeExecute()
 *     must find an approving human Decision for the run's recommendation. A workflow
 *     definition that forgets an approval step therefore still cannot act — the gate
 *     is in the engine, not in the configuration.
 */
class WorkflowEngine
{
    /** Guards against a mis-authored definition looping forever. */
    private const MAX_STEPS_PER_ADVANCE = 50;

    public function __construct(
        private readonly StepHandlerRegistry $handlers,
        private readonly ConditionEvaluator $conditions,
        private readonly GovernanceValidator $governance,
        private readonly AiAuditLogger $audit,
    ) {
    }

    /**
     * Start a workflow run and advance it as far as it will go.
     *
     * @return array{run_id:int|null, status:string, message:string, current_step:string|null}
     */
    public function start(
        string $workflowKey,
        McpRequestContext $scope,
        array $input = [],
        array $links = []
    ): array {
        $definition = $this->findDefinition($workflowKey, $scope);

        if (! $definition) {
            return $this->result(null, 'failed', 'No such workflow, or it is disabled.');
        }

        $roleReport = $this->governance->authorizeRole($scope, $this->decodeArray($definition->allowed_roles));

        if (! $roleReport->passed) {
            $this->audit->recordRejection($roleReport->reason() ?? 'Role not permitted.', $scope, [
                'related_type' => 'workflow_definitions',
                'related_id' => $definition->id,
                'payload' => ['workflow_key' => $workflowKey],
            ]);

            return $this->result(null, 'rejected', $roleReport->reason() ?? 'Not permitted.');
        }

        $version = $this->activeVersion($definition);

        if (! $version) {
            return $this->result(null, 'failed', 'This workflow has no published version.');
        }

        // Entry conditions are checked before a run exists, so a workflow that should
        // not have started leaves no half-open run behind.
        $definitionConditions = $this->decodeArray($definition->conditions);

        if ($definitionConditions !== [] && ! $this->conditions->passes($definitionConditions, $input)) {
            $failures = $this->conditions->explainFailures($definitionConditions, $input);

            return $this->result(null, 'rejected', implode(' ', $failures) ?: 'Workflow conditions were not met.');
        }

        $runId = $this->openRun($definition, $version, $scope, $input, $links);

        return $this->advance($runId, $scope);
    }

    /**
     * Advance a run until it completes, fails, or parks on something it needs.
     *
     * Safe to call repeatedly — this is what an approval, a queued job, or a retry
     * calls to resume.
     */
    public function advance(int $runId, McpRequestContext $scope): array
    {
        $run = $this->findRun($runId, $scope);

        if (! $run) {
            return $this->result($runId, 'failed', 'No such workflow run in your scope.');
        }

        if (in_array($run->status, ['completed', 'failed', 'cancelled', 'rejected'], true)) {
            return $this->result($runId, $run->status, 'This run has already finished.', $run->current_step_key);
        }

        $version = DB::table('workflow_versions')->where('id', $run->version_id)->first();

        if (! $version) {
            $this->closeRun($runId, 'failed', 'The workflow version behind this run is missing.');

            return $this->result($runId, 'failed', 'The workflow version behind this run is missing.');
        }

        $steps = $this->indexSteps($this->decodeArray($version->steps));

        if ($steps === []) {
            $this->closeRun($runId, 'failed', 'This workflow version declares no steps.');

            return $this->result($runId, 'failed', 'This workflow version declares no steps.');
        }

        $state = $this->decodeArray($run->state);
        $input = $this->decodeArray($run->input);
        $state['input'] = $input;

        $context = new WorkflowRunContext(
            runId: $runId,
            workflowKey: $run->workflow_key,
            definitionId: (int) $run->definition_id,
            versionId: (int) $run->version_id,
            recommendationId: $run->recommendation_id ? (int) $run->recommendation_id : null,
            decisionId: $run->decision_id ? (int) $run->decision_id : null,
            caseId: $run->case_id ? (int) $run->case_id : null,
            agentRunId: $run->agent_run_id ? (int) $run->agent_run_id : null,
            subjectEntityKey: $run->subject_entity_key,
            subjectId: $run->subject_id,
            input: $input,
        );

        $currentKey = $run->current_step_key ?: ($version->entry_step_key ?: array_key_first($steps));

        DB::table('workflow_runs')->where('id', $runId)->update([
            'status' => 'running',
            'started_at' => $run->started_at ?: now(),
            'updated_at' => now(),
        ]);

        $guard = 0;

        while ($currentKey !== null && $guard++ < self::MAX_STEPS_PER_ADVANCE) {
            $step = $steps[$currentKey] ?? null;

            if (! $step) {
                $this->closeRun($runId, 'failed', sprintf('Step "%s" is not defined.', $currentKey));

                return $this->result($runId, 'failed', sprintf('Step "%s" is not defined.', $currentKey));
            }

            $outcome = $this->executeStep($step, $state, $context, $scope);

            $state = $outcome['state'];

            if ($outcome['result']->status === 'paused') {
                DB::table('workflow_runs')->where('id', $runId)->update([
                    'status' => 'awaiting_approval',
                    'current_step_key' => $currentKey,
                    'state' => json_encode($state),
                    'updated_at' => now(),
                ]);

                $this->recordTransition($runId, $currentKey, 'awaiting_approval', $scope, $outcome['result']->message);

                return $this->result(
                    $runId,
                    'awaiting_approval',
                    $outcome['result']->message ?? 'Waiting for approval.',
                    $currentKey
                );
            }

            if ($outcome['result']->status === 'failed') {
                $this->closeRun($runId, 'failed', $outcome['result']->message);
                $this->recordTransition($runId, $currentKey, 'failed', $scope, $outcome['result']->message);

                return $this->result($runId, 'failed', $outcome['result']->message ?? 'Step failed.', $currentKey);
            }

            $nextKey = $outcome['result']->nextStepKey ?? $this->resolveNext($step, $state);

            DB::table('workflow_runs')->where('id', $runId)->update([
                'current_step_key' => $nextKey,
                'state' => json_encode($state),
                'updated_at' => now(),
            ]);

            $currentKey = $nextKey;
        }

        if ($guard >= self::MAX_STEPS_PER_ADVANCE) {
            $this->closeRun($runId, 'failed', 'The workflow exceeded its step budget; check for a loop.');

            return $this->result($runId, 'failed', 'The workflow exceeded its step budget.');
        }

        $this->closeRun($runId, 'completed', null);
        $this->recordTransition($runId, null, 'completed', $scope, 'Workflow completed.');

        return $this->result($runId, 'completed', 'Workflow completed.', null);
    }

    /**
     * Resolve a pending approval and resume the run.
     */
    public function resolveApproval(
        int $approvalId,
        string $decision,
        McpRequestContext $scope,
        ?string $comment = null,
        array $modifications = []
    ): array {
        if (! Schema::hasTable('workflow_approvals')) {
            throw new RuntimeException('The approval store is unavailable.');
        }

        if ($scope->userId <= 0) {
            throw new RuntimeException('An approval requires an authenticated user.');
        }

        $approval = DB::table('workflow_approvals')
            ->where('id', $approvalId)
            ->where('sub_institute_id', $scope->selectedInstituteId)
            ->first();

        if (! $approval) {
            throw new RuntimeException('No such approval in your scope.');
        }

        if ($approval->status !== 'pending') {
            throw new RuntimeException('This approval has already been resolved.');
        }

        if ($approval->expires_at !== null && now()->greaterThan($approval->expires_at)) {
            DB::table('workflow_approvals')->where('id', $approvalId)->update([
                'status' => 'expired',
                'updated_at' => now(),
            ]);

            throw new RuntimeException('This approval has expired.');
        }

        DB::table('workflow_approvals')->where('id', $approvalId)->update([
            'status' => $decision === 'approved' ? 'approved' : 'rejected',
            'comment' => $comment,
            'modifications' => $modifications === [] ? null : json_encode($modifications),
            'decided_by' => $scope->userId,
            'decided_at' => now(),
            'updated_at' => now(),
        ]);

        // ApprovalStepHandler creates the approval before it knows the step row's id, so
        // step_id is usually null. Falling back to run + step_key matters: without it the
        // step row stays `awaiting_approval` forever, and a completed run still reports an
        // unresolved approval step to anything reading its progress.
        $stepQuery = $approval->step_id
            ? DB::table('workflow_steps')->where('id', $approval->step_id)
            : DB::table('workflow_steps')
                ->where('run_id', $approval->run_id)
                ->where('step_key', $approval->step_key);

        $stepQuery->update([
            'status' => $decision === 'approved' ? 'completed' : 'rejected',
            'finished_at' => now(),
            'updated_at' => now(),
        ]);

        $this->audit->record(AiAuditLogger::WORKFLOW_TRANSITION, $scope, [
            'actor_type' => 'user',
            'related_type' => 'workflow_runs',
            'related_id' => (int) $approval->run_id,
            'outcome' => $decision === 'approved' ? 'success' : 'rejected',
            'message' => sprintf('Workflow approval %s at step "%s".', $decision, $approval->step_key),
            'payload' => ['approval_id' => $approvalId, 'comment' => $comment],
        ]);

        if ($decision !== 'approved') {
            $this->closeRun((int) $approval->run_id, 'rejected', $comment ?? 'Rejected at approval step.');

            return $this->result((int) $approval->run_id, 'rejected', 'The workflow was rejected.', $approval->step_key);
        }

        // Move past the approval step before resuming, or the run would park again.
        $this->markApprovalPassed((int) $approval->run_id, (string) $approval->step_key);

        return $this->advance((int) $approval->run_id, $scope);
    }

    public function status(int $runId, McpRequestContext $scope): ?array
    {
        $run = $this->findRun($runId, $scope);

        if (! $run) {
            return null;
        }

        $steps = Schema::hasTable('workflow_steps')
            ? DB::table('workflow_steps')
                ->where('run_id', $runId)
                ->orderBy('sequence')
                ->get()
                ->map(fn ($row) => [
                    'step_key' => $row->step_key,
                    'step_type' => $row->step_type,
                    'label' => $row->label,
                    'status' => $row->status,
                    'output' => $row->output ? json_decode($row->output, true) : null,
                    'error_message' => $row->error_message,
                    'started_at' => $row->started_at,
                    'finished_at' => $row->finished_at,
                    'duration_ms' => $row->duration_ms,
                ])->all()
            : [];

        $approvals = Schema::hasTable('workflow_approvals')
            ? DB::table('workflow_approvals')
                ->where('run_id', $runId)
                ->orderBy('id')
                ->get()
                ->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'step_key' => $row->step_key,
                    'approver_role' => $row->approver_role,
                    'assigned_to' => $row->assigned_to,
                    'status' => $row->status,
                    'comment' => $row->comment,
                    'decided_at' => $row->decided_at,
                    'expires_at' => $row->expires_at,
                ])->all()
            : [];

        return [
            'id' => (int) $run->id,
            'reference' => $run->run_reference,
            'workflow_key' => $run->workflow_key,
            'status' => $run->status,
            'current_step_key' => $run->current_step_key,
            'recommendation_id' => $run->recommendation_id ? (int) $run->recommendation_id : null,
            'case_id' => $run->case_id ? (int) $run->case_id : null,
            'subject_entity_key' => $run->subject_entity_key,
            'subject_id' => $run->subject_id,
            'started_at' => $run->started_at,
            'finished_at' => $run->finished_at,
            'error_message' => $run->error_message,
            'steps' => $steps,
            'approvals' => $approvals,
        ];
    }

    // ---------------------------------------------------------------- internals

    /**
     * @return array{result:StepResult, state:array}
     */
    private function executeStep(
        array $step,
        array $state,
        WorkflowRunContext $context,
        McpRequestContext $scope
    ): array {
        $stepKey = (string) ($step['key'] ?? '');
        $stepType = (string) ($step['type'] ?? '');
        $config = is_array($step['config'] ?? null) ? $step['config'] : [];

        // Per-step conditions: an unmet condition skips, it does not fail.
        $stepConditions = is_array($step['conditions'] ?? null) ? $step['conditions'] : [];

        if ($stepConditions !== [] && ! $this->conditions->passes($stepConditions, $state)) {
            $this->recordStep($context->runId, $step, 'skipped', [], 'Step conditions were not met.', 0);

            return ['result' => StepResult::skipped('Step conditions were not met.'), 'state' => $state];
        }

        $handler = $this->handlers->find($stepType);

        if (! $handler) {
            $message = sprintf('No handler is registered for step type "%s".', $stepType);
            $this->recordStep($context->runId, $step, 'failed', [], $message, 0);

            return ['result' => StepResult::failed($message), 'state' => $state];
        }

        // THE GATE. A consequential step cannot run without an approving human
        // decision on the recommendation this run descends from.
        if ($handler->isConsequential($config)) {
            $gate = $this->authorizeConsequentialStep($context, $scope);

            if ($gate !== null) {
                $this->recordStep($context->runId, $step, 'failed', [], $gate, 0);

                $this->audit->recordRejection($gate, $scope, [
                    'related_type' => 'workflow_runs',
                    'related_id' => $context->runId,
                    'payload' => ['step_key' => $stepKey, 'step_type' => $stepType],
                ]);

                return ['result' => StepResult::failed($gate), 'state' => $state];
            }
        }

        $startedAt = microtime(true);

        try {
            $result = $handler->handle($config, $state, $context, $scope);
        } catch (Throwable $exception) {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            $this->recordStep($context->runId, $step, 'failed', [], $exception->getMessage(), $durationMs);

            return ['result' => StepResult::failed($exception->getMessage()), 'state' => $state];
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        $this->recordStep(
            $context->runId,
            $step,
            $result->status === 'paused' ? 'awaiting_approval' : $result->status,
            $result->output,
            $result->message,
            $durationMs
        );

        if ($result->output !== [] && $stepKey !== '') {
            $state[$stepKey] = $result->output;
        }

        return ['result' => $result, 'state' => $state];
    }

    /**
     * Returns null when the step may run, or a refusal message when it may not.
     */
    private function authorizeConsequentialStep(WorkflowRunContext $context, McpRequestContext $scope): ?string
    {
        if ($context->recommendationId === null) {
            return 'A consequential workflow step needs an approved recommendation behind it; this run has none.';
        }

        $report = $this->governance->authorizeExecute($context->recommendationId, $scope);

        return $report->passed ? null : ($report->reason() ?? 'This action is not authorised.');
    }

    private function resolveNext(array $step, array $state): ?string
    {
        // Branches are evaluated in order; the first matching wins.
        $branches = is_array($step['branches'] ?? null) ? $step['branches'] : [];

        foreach ($branches as $branch) {
            if (! is_array($branch)) {
                continue;
            }

            $when = is_array($branch['when'] ?? null) ? $branch['when'] : [];

            if ($when === [] || $this->conditions->passes($when, $state)) {
                return $branch['next'] ?? null;
            }
        }

        $next = $step['next'] ?? null;

        return is_string($next) && $next !== '' ? $next : null;
    }

    /** @return array<string, array> */
    private function indexSteps(array $steps): array
    {
        $indexed = [];
        $sequence = 0;

        foreach ($steps as $step) {
            if (! is_array($step) || empty($step['key'])) {
                continue;
            }

            $step['sequence'] = $step['sequence'] ?? $sequence++;
            $indexed[(string) $step['key']] = $step;
        }

        return $indexed;
    }

    private function findDefinition(string $workflowKey, McpRequestContext $scope): ?object
    {
        if (! Schema::hasTable('workflow_definitions')) {
            return null;
        }

        return DB::table('workflow_definitions')
            ->where('workflow_key', $workflowKey)
            ->where('status', 1)
            ->where(function ($query) use ($scope) {
                $query->whereNull('sub_institute_id')
                    ->orWhere('sub_institute_id', $scope->selectedInstituteId);
            })
            ->orderByRaw('sub_institute_id IS NULL ASC')
            ->first();
    }

    private function activeVersion(object $definition): ?object
    {
        if (! Schema::hasTable('workflow_versions')) {
            return null;
        }

        if ($definition->active_version_id) {
            $version = DB::table('workflow_versions')
                ->where('id', $definition->active_version_id)
                ->first();

            if ($version) {
                return $version;
            }
        }

        return DB::table('workflow_versions')
            ->where('definition_id', $definition->id)
            ->where('status', 'published')
            ->orderByDesc('version')
            ->first();
    }

    private function findRun(int $runId, McpRequestContext $scope): ?object
    {
        if (! Schema::hasTable('workflow_runs')) {
            return null;
        }

        return DB::table('workflow_runs')
            ->where('id', $runId)
            ->where('sub_institute_id', $scope->selectedInstituteId)
            ->first();
    }

    private function openRun(
        object $definition,
        object $version,
        McpRequestContext $scope,
        array $input,
        array $links
    ): int {
        $runId = (int) DB::table('workflow_runs')->insertGetId([
            'run_reference' => $this->nextReference(),
            'definition_id' => $definition->id,
            // Pinned: later edits to the definition do not alter this run.
            'version_id' => $version->id,
            'workflow_key' => $definition->workflow_key,
            'trigger_type' => $links['trigger_type'] ?? $definition->trigger_type ?? 'manual',
            'recommendation_id' => $links['recommendation_id'] ?? null,
            'decision_id' => $links['decision_id'] ?? null,
            'case_id' => $links['case_id'] ?? null,
            'agent_run_id' => $links['agent_run_id'] ?? null,
            'subject_entity_key' => $links['subject_entity_key'] ?? $definition->subject_entity_key ?? null,
            'subject_id' => $links['subject_id'] ?? null,
            'input' => json_encode($input),
            'state' => json_encode([]),
            'current_step_key' => $version->entry_step_key,
            'status' => 'pending',
            'started_at' => now(),
            'timeout_at' => $definition->timeout_minutes
                ? now()->addMinutes((int) $definition->timeout_minutes)
                : null,
            'initiated_by' => $scope->userId,
            'sub_institute_id' => $scope->selectedInstituteId,
            'client_id' => $scope->clientId,
            'academic_year' => $scope->academicYear,
            'term_id' => $scope->termId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->audit->record(AiAuditLogger::WORKFLOW_TRANSITION, $scope, [
            'related_type' => 'workflow_runs',
            'related_id' => $runId,
            'message' => sprintf('Workflow %s started.', $definition->workflow_key),
            'payload' => ['links' => $links, 'version' => $version->version],
        ]);

        return $runId;
    }

    private function recordStep(
        int $runId,
        array $step,
        string $status,
        array $output,
        ?string $message,
        int $durationMs
    ): void {
        if (! Schema::hasTable('workflow_steps')) {
            return;
        }

        $existing = DB::table('workflow_steps')
            ->where('run_id', $runId)
            ->where('step_key', (string) ($step['key'] ?? ''))
            ->orderByDesc('id')
            ->first();

        $payload = [
            'status' => $status,
            'output' => $output === [] ? null : json_encode($output),
            'error_message' => in_array($status, ['failed'], true) ? $message : null,
            'finished_at' => in_array($status, ['completed', 'failed', 'skipped'], true) ? now() : null,
            'duration_ms' => $durationMs,
            'updated_at' => now(),
        ];

        if ($existing) {
            DB::table('workflow_steps')->where('id', $existing->id)->update($payload + [
                'attempt' => (int) $existing->attempt + 1,
            ]);

            return;
        }

        DB::table('workflow_steps')->insert($payload + [
            'run_id' => $runId,
            'step_key' => (string) ($step['key'] ?? ''),
            'step_type' => (string) ($step['type'] ?? ''),
            'label' => isset($step['label']) ? mb_substr((string) $step['label'], 0, 200) : null,
            'sequence' => (int) ($step['sequence'] ?? 0),
            'input' => isset($step['config']) ? json_encode($step['config']) : null,
            'config' => isset($step['config']) ? json_encode($step['config']) : null,
            'attempt' => 1,
            'max_retries' => (int) ($step['max_retries'] ?? 0),
            'started_at' => now(),
            'created_at' => now(),
        ]);
    }

    private function markApprovalPassed(int $runId, string $stepKey): void
    {
        $run = DB::table('workflow_runs')->where('id', $runId)->first();

        if (! $run) {
            return;
        }

        $version = DB::table('workflow_versions')->where('id', $run->version_id)->first();

        if (! $version) {
            return;
        }

        $steps = $this->indexSteps($this->decodeArray($version->steps));
        $step = $steps[$stepKey] ?? null;

        if (! $step) {
            return;
        }

        $state = $this->decodeArray($run->state);
        $state[$stepKey] = ['approved' => true, 'approved_at' => now()->toIso8601String()];

        DB::table('workflow_runs')->where('id', $runId)->update([
            'status' => 'approved',
            'current_step_key' => $this->resolveNext($step, $state),
            'state' => json_encode($state),
            'updated_at' => now(),
        ]);
    }

    private function recordTransition(
        int $runId,
        ?string $stepKey,
        string $status,
        McpRequestContext $scope,
        ?string $message
    ): void {
        $this->audit->record(AiAuditLogger::WORKFLOW_TRANSITION, $scope, [
            'related_type' => 'workflow_runs',
            'related_id' => $runId,
            'outcome' => $status === 'failed' ? 'failure' : 'success',
            'message' => $message ?? sprintf('Run moved to %s.', $status),
            'payload' => ['step_key' => $stepKey, 'status' => $status],
        ]);
    }

    private function closeRun(int $runId, string $status, ?string $error): void
    {
        if (! Schema::hasTable('workflow_runs')) {
            return;
        }

        DB::table('workflow_runs')->where('id', $runId)->update([
            'status' => $status,
            'error_message' => $error,
            'finished_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function nextReference(): string
    {
        $prefix = sprintf('WFR-%d-', now()->year);

        $last = DB::table('workflow_runs')
            ->where('run_reference', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('run_reference');

        $sequence = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    private function decodeArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function result(?int $runId, string $status, string $message, ?string $currentStep = null): array
    {
        return [
            'run_id' => $runId,
            'status' => $status,
            'message' => $message,
            'current_step' => $currentStep,
        ];
    }
}
