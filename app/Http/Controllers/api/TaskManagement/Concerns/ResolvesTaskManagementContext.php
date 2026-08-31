<?php

namespace App\Http\Controllers\api\TaskManagement\Concerns;

use App\Models\TaskManagement\Task;
use App\Models\TaskManagement\TaskDependency;
use App\Models\TaskManagement\TaskStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ported from hp_erp's `App\Http\Controllers\Api\TaskManagement\Concerns\ResolvesTaskContext`
 * (Sanctum-per-request identity) plus hp_erp's
 * `App\Services\TaskManagement\{TaskStatusTransitionService,
 * TaskDependencyResolutionService, TaskOptionSetService, TaskAuditService,
 * TaskStatusWriter}` folded together, following this repo's
 * `ResolvesPerformanceContext` (TalentManagement) precedent: identity comes
 * from the session hydrated by the `api.session` middleware rather than a
 * bearer token, and the source's event-sourced audit/status-history tables
 * become one direct write per call.
 *
 * `task_management_audit_logs` here has no subject_type/subject_name/
 * description/project_id columns (only sub_institute_id, task_id, event,
 * actor_id, before, created_at) - logTaskActivity folds all of that
 * secondary detail into the `before` JSON payload rather than needing a
 * wider table shape.
 */
trait ResolvesTaskManagementContext
{
    /** System status categories; the workflow engine's four fixed states. */
    public const STATUS_CATEGORIES = ['PENDING', 'IN-PROGRESS', 'ON HOLD', 'COMPLETED'];

    /** System priorities; a tenant may add its own alongside these. */
    public const SYSTEM_PRIORITIES = ['High', 'Medium', 'Low'];

    /** approve_status spellings that count as "approved". */
    public const APPROVED_VALUES = ['approved', '1', 'yes'];

    /** @var array<string, array<int, string>> legal status moves. */
    private const ALLOWED_TRANSITIONS = [
        'PENDING' => ['IN-PROGRESS', 'ON HOLD', 'COMPLETED'],
        'IN-PROGRESS' => ['ON HOLD', 'COMPLETED', 'PENDING'],
        'ON HOLD' => ['PENDING', 'IN-PROGRESS', 'COMPLETED'],
        'COMPLETED' => ['IN-PROGRESS'],
    ];

    /**
     * Tenant + actor context for one request, read from the session hydrated
     * by the `api.session` middleware (never re-derived from a token).
     *
     * @return array{sub_institute_id:int, user_id:?int, syear:string, department_id:int}
     */
    protected function taskManagementContext(Request $request): array
    {
        $userId = session()->get('user_id');

        return [
            'sub_institute_id' => (int) session()->get('sub_institute_id'),
            'user_id' => $userId !== null ? (int) $userId : null,
            'syear' => (string) (session()->get('syear') ?: $request->input('syear', '')),
            'department_id' => (int) session()->get('department_id', 0),
        ];
    }

    /** Common list filters, matching the source controllers' query params. */
    protected function taskManagementFilters(Request $request): array
    {
        return [
            'search' => $this->activeTaskFilter($request->input('search')),
            'status' => $this->activeTaskFilter($request->input('status')),
            'priority' => $this->activeTaskFilter($request->input('priority')),
            'assignee_id' => $this->activeTaskFilter($request->input('assignee_id')),
            'project_id' => $this->activeTaskFilter($request->input('project_id')),
            'from' => $this->activeTaskFilter($request->input('from')),
            'to' => $this->activeTaskFilter($request->input('to')),
        ];
    }

    /** Treat 'all', '0' and empty string as "no filter". */
    protected function activeTaskFilter($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return ($value === '' || $value === '0' || strtolower($value) === 'all') ? null : $value;
    }

    /** page / per_page, clamped so a hostile per_page cannot dump the table. */
    protected function taskManagementPaging(Request $request, int $defaultPerPage = 20): array
    {
        $page = (int) ($request->input('page') ?: 1);
        $perPage = (int) ($request->input('per_page') ?: $defaultPerPage);

        return [
            'page' => max(1, $page),
            'per_page' => min(100, max(1, $perPage)),
        ];
    }

    /** Whitelisted sorting - never interpolate a client string into ORDER BY. */
    protected function taskManagementSort(Request $request, array $allowed, string $default, string $defaultDir = 'desc'): array
    {
        $column = (string) $request->input('sort_by', $default);
        $direction = strtolower((string) $request->input('sort_dir', $defaultDir)) === 'asc' ? 'asc' : 'desc';

        return [
            in_array($column, $allowed, true) ? $column : $default,
            $direction,
        ];
    }

    /** The {status,message,data} envelope every task-management endpoint returns. */
    protected function taskManagementResponse($data = null, string $message = 'Success', int $code = 200, array $extra = [])
    {
        $payload = ['status' => 1, 'message' => $message];
        if ($data !== null) {
            $payload['data'] = $data;
        }

        return response()->json(array_merge($payload, $extra), $code);
    }

    protected function taskManagementError(string $message, int $code = 400, array $extra = [])
    {
        return response()->json(array_merge(['status' => 0, 'message' => $message], $extra), $code);
    }

    /**
     * Escape LIKE wildcards in a user-supplied search term - without this a
     * "%" search matches every row and "_" matches any single character.
     */
    protected function escapeTaskLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }

    /**
     * Append a row to the task audit trail. Ported from hp_erp's
     * TaskAuditService (`taskChanged` / `configChanged`), collapsed from an
     * event-sourced write into one direct insert.
     *
     * `task_management_audit_logs.task_id` is NOT NULL on this table, so a
     * configuration event (no task involved, e.g. "status created") writes
     * 0 as a sentinel - there is no FK constraint on this column, matching
     * the legacy `task` table's own convention.
     *
     * @param array<int, array{field:string, label:string, old:mixed, new:mixed}>|null $changes
     */
    protected function logTaskActivity(
        int $subInstituteId,
        ?int $actorId,
        string $action,
        string $description,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?string $subjectName = null,
        ?array $changes = null,
        ?int $taskId = null,
        ?int $projectId = null
    ): void {
        $before = array_filter([
            'description' => $description,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'subject' => $subjectName,
            'changes' => ($changes !== null && $changes !== []) ? array_values($changes) : null,
            'project_id' => $projectId,
        ], fn ($value) => $value !== null);

        DB::table('task_management_audit_logs')->insert([
            'sub_institute_id' => $subInstituteId,
            'task_id' => $taskId ?? 0,
            'event' => $action,
            'actor_id' => $actorId,
            'before' => $before !== [] ? json_encode($before) : null,
            'created_at' => now(),
        ]);
    }

    /**
     * Build the field-level diff an audit entry's "before" payload carries.
     * Only columns present in $after AND actually different are returned.
     *
     * @param  object|array<string, mixed> $before
     * @param  array<string, mixed>        $after
     * @param  array<string, string>       $labels column => display label
     * @return array<int, array{field:string, label:string, old:mixed, new:mixed}>
     */
    protected function diffTaskChanges($before, array $after, array $labels): array
    {
        $before = is_object($before) ? (array) $before : $before;
        $changes = [];

        foreach ($after as $column => $newValue) {
            if (!array_key_exists($column, $labels)) {
                continue;
            }

            $oldValue = $before[$column] ?? null;

            if ((string) ($oldValue ?? '') === (string) ($newValue ?? '')) {
                continue;
            }

            $changes[] = [
                'field' => $column,
                'label' => $labels[$column],
                'old' => $oldValue,
                'new' => $newValue,
            ];
        }

        return $changes;
    }

    /** Display name for the audit trail / activity feed, from the single user master. */
    protected function resolveActorName(?int $userId): ?string
    {
        if (!$userId) {
            return null;
        }

        $user = DB::table('tbluser')->where('id', $userId)
            ->selectRaw("TRIM(CONCAT_WS(' ', first_name, middle_name, last_name)) as name")
            ->first();

        return $user && $user->name !== '' ? $user->name : null;
    }

    /* ------------------------------------------------------------------ *
     * Status / priority vocabularies - ported from TaskOptionSetService
     * ------------------------------------------------------------------ */

    /** @return array<int, array{id: ?string, name: string, category: string, color: ?string, sort_order: int, is_system: bool, active: bool}> */
    protected function taskStatusOptions(int $sid, bool $activeOnly = true): array
    {
        $systemStatuses = [
            ['name' => 'PENDING', 'category' => 'PENDING', 'sort_order' => 10],
            ['name' => 'IN-PROGRESS', 'category' => 'IN-PROGRESS', 'sort_order' => 20],
            ['name' => 'ON HOLD', 'category' => 'ON HOLD', 'sort_order' => 30],
            ['name' => 'COMPLETED', 'category' => 'COMPLETED', 'sort_order' => 40],
        ];

        $query = DB::table('task_management_statuses')->where('sub_institute_id', $sid);
        if ($activeOnly) {
            $query->where('active', true);
        }

        $custom = $query->orderBy('sort_order')->get()->map(fn ($row) => [
            'id' => (string) $row->id,
            'name' => (string) $row->name,
            'category' => (string) $row->category,
            'color' => $row->color,
            'sort_order' => (int) $row->sort_order,
            'is_system' => false,
            'active' => (bool) $row->active,
        ])->all();

        $system = array_map(fn (array $status) => $status + [
            'id' => null, 'color' => null, 'is_system' => true, 'active' => true,
        ], $systemStatuses);

        $merged = array_merge($system, $custom);
        usort($merged, fn ($a, $b) => $a['sort_order'] <=> $b['sort_order']);

        return $merged;
    }

    /** @return array<int, array{id: ?string, name: string, sort_order: int, sla_hours: ?int, is_system: bool, active: bool}> */
    protected function taskPriorityOptions(int $sid, bool $activeOnly = true): array
    {
        $systemPriorities = [
            ['name' => 'High', 'sort_order' => 10],
            ['name' => 'Medium', 'sort_order' => 20],
            ['name' => 'Low', 'sort_order' => 30],
        ];

        $query = DB::table('task_management_priorities')->where('sub_institute_id', $sid);
        if ($activeOnly) {
            $query->where('active', true);
        }

        $custom = $query->orderBy('sort_order')->get()->map(fn ($row) => [
            'id' => (string) $row->id,
            'name' => (string) $row->name,
            'sort_order' => (int) $row->sort_order,
            'sla_hours' => $row->sla_hours !== null ? (int) $row->sla_hours : null,
            'is_system' => false,
            'active' => (bool) $row->active,
        ])->all();

        $system = array_map(fn (array $priority) => $priority + [
            'id' => null, 'sla_hours' => null, 'is_system' => true, 'active' => true,
        ], $systemPriorities);

        $merged = array_merge($system, $custom);
        usort($merged, fn ($a, $b) => $a['sort_order'] <=> $b['sort_order']);

        return $merged;
    }

    /**
     * Resolve a submitted status to what task.status/status_label should
     * store. A system category stores itself with no label. A tenant's
     * custom name stores its CATEGORY in task.status (so every board,
     * summary and transition keeps working) and the name in status_label.
     *
     * @return array{status: string, label: ?string}|null null = not legal
     */
    protected function resolveTaskStatusValue(int $sid, string $value): ?array
    {
        $value = trim($value);

        foreach (self::STATUS_CATEGORIES as $category) {
            if (strcasecmp($value, $category) === 0) {
                return ['status' => $category, 'label' => null];
            }
        }

        $custom = DB::table('task_management_statuses')
            ->where('sub_institute_id', $sid)
            ->where('active', true)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($value)])
            ->first();

        return $custom
            ? ['status' => (string) $custom->category, 'label' => (string) $custom->name]
            : null;
    }

    /** The stored priority name, or null when the value is not legal. */
    protected function resolveTaskPriorityValue(int $sid, string $value): ?string
    {
        $value = trim($value);

        foreach (self::SYSTEM_PRIORITIES as $priority) {
            if (strcasecmp($value, $priority) === 0) {
                return $priority;
            }
        }

        $custom = DB::table('task_management_priorities')
            ->where('sub_institute_id', $sid)
            ->where('active', true)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($value)])
            ->value('name');

        return $custom !== null ? (string) $custom : null;
    }

    /* ------------------------------------------------------------------ *
     * Status transitions + writer - ported from TaskStatusTransitionService
     * and (the source's separately-referenced but never-committed)
     * TaskStatusWriter, collapsed into one direct write.
     * ------------------------------------------------------------------ */

    /** The table holds historical spellings; judge transitions on one form. */
    private function normaliseTaskStatus(?string $status): string
    {
        $status = strtoupper(trim((string) $status));

        return match ($status) {
            'IN PROGRESS', 'IN-PROGRES' => 'IN-PROGRESS',
            '' => 'PENDING',
            default => $status,
        };
    }

    private function taskStatusTransitionAllowed(?string $from, string $to): bool
    {
        $from = $this->normaliseTaskStatus($from);
        $to = $this->normaliseTaskStatus($to);

        if ($from === $to) {
            return true;
        }

        return in_array($to, self::ALLOWED_TRANSITIONS[$from] ?? [], true);
    }

    private function taskStatusTransitionMessage(?string $from, string $to): string
    {
        $from = $this->normaliseTaskStatus($from);
        $to = $this->normaliseTaskStatus($to);

        if ($from === 'COMPLETED') {
            return 'A completed task can only be reopened to In Progress.';
        }

        return "A task cannot move from {$from} to {$to}.";
    }

    /**
     * Validates the transition, then updates task.status/status_label (+
     * delay fields when given) AND inserts one task_status_history row, in
     * the same call. Replaces the source's TaskStatusWriter + the
     * event-store projector that fed TaskStatusHistory.
     *
     * @return array{ok: bool, reason: ?string}
     */
    protected function writeTaskStatus(
        Task $task,
        string $newStatus,
        ?string $statusLabel,
        int $actorId,
        ?string $delayCategory = null,
        ?string $delayReason = null
    ): array {
        $from = (string) $task->STATUS;
        $to = $this->normaliseTaskStatus($newStatus);

        if (!$this->taskStatusTransitionAllowed($from, $to)) {
            return ['ok' => false, 'reason' => $this->taskStatusTransitionMessage($from, $to)];
        }

        $fromApprove = (string) ($task->approve_status ?? '');
        $fromTerminal = $this->normaliseTaskStatus($from) === 'COMPLETED'
            || in_array(strtolower(trim($fromApprove)), self::APPROVED_VALUES, true);
        $toActive = $to !== 'COMPLETED';
        $isReopen = $fromTerminal && $toActive;

        // Delay fields belong to the hold state. Leaving a stale reason on a
        // task that has resumed would explain a delay that is over: on hold,
        // take what the caller supplied or keep what is already there; off
        // hold, clear both.
        $onHold = $to === 'ON HOLD';

        $update = [
            'STATUS' => $to,
            'status_label' => $statusLabel,
            'delay_category' => $onHold ? ($delayCategory ?? $task->delay_category ?? null) : null,
            'delay_reason' => $onHold ? ($delayReason ?? $task->delay_reason ?? null) : null,
            'updated_by' => $actorId,
            'updated_at' => now(),
        ];

        Task::where('ID', $task->ID)->update($update);

        TaskStatusHistory::create([
            'sub_institute_id' => (int) $task->sub_institute_id,
            'task_id' => (int) $task->ID,
            'from_status' => $from,
            'to_status' => $to,
            'from_approve_status' => $fromApprove !== '' ? $fromApprove : null,
            'to_approve_status' => null,
            'from_terminal' => $fromTerminal,
            'to_active' => $toActive,
            'is_reopen' => $isReopen,
            'actor_id' => $actorId ?: null,
            'occurred_at' => now(),
        ]);

        return ['ok' => true, 'reason' => null];
    }

    /**
     * Ported from TaskDependencyResolutionService::resolveAfterCompletion.
     *
     * When a task completes, unblock successors that were parked ON HOLD
     * with delay_category 'Dependency' AND have no other open predecessor.
     * Scope is deliberately narrow: a task someone put ON HOLD for a
     * resource or scope reason stays exactly where they left it.
     */
    protected function resolveTaskDependenciesAfterCompletion(int $taskId, ?int $actorId): void
    {
        $successorIds = TaskDependency::where('predecessor_task_id', $taskId)->pluck('successor_task_id');

        foreach ($successorIds as $successorId) {
            $openPredecessors = DB::table('task_management_dependencies as d')
                ->join('task as p', 'p.ID', '=', 'd.predecessor_task_id')
                ->where('d.successor_task_id', $successorId)
                ->whereRaw("UPPER(COALESCE(p.STATUS, 'PENDING')) <> 'COMPLETED'")
                ->count();

            if ($openPredecessors > 0) {
                continue;
            }

            $successor = Task::where('ID', $successorId)
                ->whereRaw("UPPER(STATUS) = 'ON HOLD'")
                ->where('delay_category', 'Dependency')
                ->first();

            if (!$successor) {
                continue;
            }

            $this->writeTaskStatus($successor, 'PENDING', null, (int) ($actorId ?? 0));
        }
    }

    /**
     * Ported from hp_erp's `TaskDependencyResolutionService::openPredecessors`.
     *
     * Predecessors of $taskId that are still not COMPLETED. Nothing in this
     * product consults the dependency graph when a status changes - a task
     * whose predecessor was never touched could move to IN-PROGRESS or
     * COMPLETED in complete silence. Callers use this to build a
     * non-blocking `warnings` array on the response: real work runs out of
     * order, and a hard refusal here would strand people whose predecessor's
     * owner is on leave, so the move proceeds and the caller is told what it
     * stepped over.
     *
     * @return array<int, array{id: string, title: string, status: string}>
     */
    protected function openPredecessors(int $taskId, int $tenantId, string $syear): array
    {
        return DB::table('task_management_dependencies as d')
            ->join('task as p', 'p.ID', '=', 'd.predecessor_task_id')
            ->where('d.successor_task_id', $taskId)
            ->where('d.sub_institute_id', $tenantId)
            ->where('d.syear', $syear)
            ->whereNull('p.deleted_at')
            ->whereRaw("UPPER(COALESCE(p.STATUS, 'PENDING')) <> 'COMPLETED'")
            ->get(['p.ID', 'p.TASK_TITLE', 'p.STATUS'])
            ->map(fn ($row) => [
                'id' => (string) $row->ID,
                'title' => $row->TASK_TITLE,
                'status' => $row->STATUS ?: 'PENDING',
            ])
            ->all();
    }

    /**
     * Normalise a date-only value before it reaches a `date` column.
     *
     * A calendar date has no timezone - it is three numbers. A validator of
     * `required|date` happily accepts a full ISO datetime with a `Z` suffix
     * (exactly what a browser's `toISOString()` produces), and routing that
     * through `Carbon::parse()` applies the app's timezone to what is really
     * a UTC instant, which can shift the calendar day for any tenant east of
     * Greenwich - the same class of bug `lib/date-only.ts` documents and
     * fixes on the frontend by reading the leading Y-M-D digits directly
     * instead of going through `Date`/`toISOString()`.
     *
     * This mirrors that: a leading `YYYY-MM-DD` is read off the string
     * as-is, with no timezone conversion applied. Only a value that does not
     * start with a plain date falls back to `Carbon::parse()`.
     */
    protected function dateOnly(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $matches)) {
            return "{$matches[1]}-{$matches[2]}-{$matches[3]}";
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
