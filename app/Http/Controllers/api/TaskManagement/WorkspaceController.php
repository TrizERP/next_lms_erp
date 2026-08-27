<?php

namespace App\Http\Controllers\api\TaskManagement;

use App\Http\Controllers\api\TaskManagement\Concerns\ResolvesTaskManagementContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\TaskManagement\Task;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ported from hp_erp's `App\Http\Controllers\Api\TaskManagement\WorkspaceController`.
 *
 * Stateless API behind the Next.js Task Management > Dashboard screen: KPI
 * summary, scoped/filtered list, single-task detail, edit, archive and the
 * approve/reject review flow. Additive to the legacy /task routes.
 *
 * The response field names are the contract of the ported frontend's
 * WorkspaceTask/WorkspaceResponse types - change them there and here
 * together or not at all.
 */
class WorkspaceController extends Controller
{
    use ResolvesTaskManagementContext;

    public function index(Request $request)
    {
        $context = $this->taskManagementContext($request);

        $request->validate([
            'scope' => 'nullable|in:all,my,mine,assigned,created,team,department,archived',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'search' => 'nullable|string|max:191',
            'status' => 'nullable|string|max:50',
            'priority' => 'nullable|string|max:50',
            'assignee_id' => 'nullable|integer',
            'project_id' => 'nullable|integer',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        $scope = (string) $request->input('scope', 'all');

        $query = $this->baseQuery($context, $scope);
        $this->applyScope($query, $scope, $context);

        if ($request->filled('search')) {
            $search = $this->escapeTaskLike(trim((string) $request->input('search')));
            $query->where(function ($builder) use ($search) {
                $builder->where('t.TASK_TITLE', 'like', "%{$search}%")
                    ->orWhere('t.TASK_DESCRIPTION', 'like', "%{$search}%")
                    ->orWhereRaw("TRIM(CONCAT_WS(' ', assignee.first_name, assignee.middle_name, assignee.last_name)) like ?", ["%{$search}%"])
                    ->orWhere('proj.name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $status = (string) $request->input('status');
            if (in_array(strtoupper($status), self::STATUS_CATEGORIES, true)) {
                $query->whereRaw('UPPER(t.STATUS) = ?', [strtoupper($status)]);
            } else {
                $query->where('t.status_label', $status);
            }
        }
        if ($request->filled('priority')) {
            $query->where('t.task_type', $request->input('priority'));
        }
        if ($request->filled('assignee_id')) {
            $query->where('t.TASK_ALLOCATED_TO', $request->integer('assignee_id'));
        }
        if ($request->filled('project_id')) {
            $query->where('pt.project_id', $request->integer('project_id'));
        }
        if ($request->filled('from')) {
            $query->whereDate('t.TASK_DATE', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('t.TASK_DATE', '<=', $request->input('to'));
        }

        $tasks = $query
            ->orderByRaw('CASE WHEN t.TASK_DATE IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('t.TASK_DATE')
            ->orderByDesc('t.ID')
            ->paginate((int) $request->input('per_page', 50));

        $tasks->getCollection()->transform(fn ($task) => $this->resource($task));

        return $this->taskManagementResponse([
            'tasks' => $tasks->items(),
            'summary' => $this->summary($context, $scope),
            'pagination' => [
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
                'per_page' => $tasks->perPage(),
                'total' => $tasks->total(),
            ],
            'filters' => [
                'statuses' => self::STATUS_CATEGORIES,
                'priorities' => self::SYSTEM_PRIORITIES,
                'users' => $this->assignableUsers($context),
                'status_options' => $this->taskStatusOptions($context['sub_institute_id']),
                'priority_options' => $this->taskPriorityOptions($context['sub_institute_id']),
            ],
        ], 'Workspace tasks retrieved successfully.');
    }

    public function workload(Request $request)
    {
        $context = $this->taskManagementContext($request);

        $rows = DB::table('task as t')
            ->where('t.sub_institute_id', $context['sub_institute_id'])
            ->where('t.SYEAR', $context['syear'])
            ->whereNull('t.deleted_at')
            ->selectRaw('COALESCE(t.TASK_ALLOCATED_TO, 0) as user_id, COUNT(*) as total')
            ->groupBy('user_id')
            ->get();

        return $this->taskManagementResponse($rows, 'Workspace workload retrieved successfully.');
    }

    public function show(Request $request, int $id)
    {
        $context = $this->taskManagementContext($request);

        $task = $this->baseQuery($context, 'all')->where('t.ID', $id)->first();
        if (!$task) {
            return $this->taskManagementError('Task not found.', 404);
        }

        $data = $this->resource($task, true);
        $data['comments'] = $this->comments($context, $id);

        return $this->taskManagementResponse($data, 'Workspace task retrieved successfully.');
    }

    public function update(Request $request, int $id)
    {
        $context = $this->taskManagementContext($request);

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:10000',
            'assignee_id' => 'required|integer',
            'owner_id' => 'required|integer',
            'status' => 'required|string|max:100',
            'priority' => 'required|string|max:100',
            'due_date' => 'nullable|date',
            'remarks' => 'nullable|string|max:5000',
        ]);

        $task = $this->findTenantTask($context, $id);
        if (!$task) {
            return $this->taskManagementError('Task not found.', 404);
        }

        if (!$this->canEditTask($context, $task)) {
            return $this->taskManagementError('You can only edit tasks you own, created, or are assigned to.', 403);
        }

        $resolvedStatus = $this->resolveTaskStatusValue($context['sub_institute_id'], (string) $request->input('status'));
        if ($resolvedStatus === null) {
            return $this->taskManagementError('Unknown status for this organisation.', 422);
        }

        $resolvedPriority = $this->resolveTaskPriorityValue($context['sub_institute_id'], (string) $request->input('priority'));
        if ($resolvedPriority === null) {
            return $this->taskManagementError('Unknown priority for this organisation.', 422);
        }

        Task::where('ID', $id)->update([
            'TASK_TITLE' => $request->input('title'),
            'TASK_DESCRIPTION' => $request->input('description'),
            'TASK_ALLOCATED_TO' => $request->integer('assignee_id'),
            'TASK_ALLOCATED' => $request->integer('owner_id'),
            'task_type' => $resolvedPriority,
            'TASK_DATE' => $request->input('due_date') ?: $task->TASK_DATE,
            'reply' => $request->filled('remarks') ? $request->input('remarks') : $task->reply,
            'updated_by' => $context['user_id'],
            'updated_at' => now(),
        ]);

        $fresh = Task::where('ID', $id)->first();
        $move = $this->writeTaskStatus($fresh, $resolvedStatus['status'], $resolvedStatus['label'], (int) $context['user_id']);
        if (!$move['ok']) {
            return $this->taskManagementError($move['reason'], 422);
        }

        $this->logTaskActivity($context['sub_institute_id'], $context['user_id'], 'workspace_updated', 'Task updated', null, null, null, null, $id);

        // Out-of-order work is reported, not refused - the same rule as
        // MyTasksController::updateStatus. See
        // ResolvesTaskManagementContext::openPredecessors().
        $warnings = [];
        if (in_array($resolvedStatus['status'], ['IN-PROGRESS', 'COMPLETED'], true)) {
            $open = $this->openPredecessors($id, $context['sub_institute_id'], $context['syear']);
            foreach ($open as $predecessor) {
                $warnings[] = [
                    'code' => 'predecessor_open',
                    'message' => 'This task depends on "' . $predecessor['title'] . '", which is still ' . $predecessor['status'] . '.',
                    'task_id' => $predecessor['id'],
                ];
            }
        }

        $updatedTask = $this->baseQuery($context, 'all')->where('t.ID', $id)->first();

        return $this->taskManagementResponse($this->resource($updatedTask, true), 'Task updated successfully.', 200, ['warnings' => $warnings]);
    }

    public function destroy(Request $request, int $id)
    {
        $context = $this->taskManagementContext($request);

        $task = $this->findTenantTask($context, $id);
        if (!$task) {
            return $this->taskManagementError('Task not found.', 404);
        }

        Task::where('ID', $id)->update([
            'deleted_by' => $context['user_id'],
            'deleted_at' => now(),
            'updated_at' => now(),
        ]);

        $this->logTaskActivity($context['sub_institute_id'], $context['user_id'], 'archived', 'Task archived', null, null, null, null, $id);

        return $this->taskManagementResponse(null, 'Task archived successfully.');
    }

    public function approve(Request $request, int $id)
    {
        if ($this->isEmployeeProfile((int) session()->get('user_id'))) {
            return $this->taskManagementError('You do not have permission to approve or reject tasks.', 403);
        }

        $context = $this->taskManagementContext($request);

        $request->validate([
            'decision' => 'required|in:approve,reject',
            'remarks' => 'nullable|string|max:5000',
        ]);

        $task = $this->findTenantTask($context, $id);
        if (!$task) {
            return $this->taskManagementError('Task not found.', 404);
        }

        if (strtoupper((string) $task->STATUS) !== 'COMPLETED') {
            return $this->taskManagementError('Only completed tasks can be reviewed.', 422);
        }

        $approving = $request->input('decision') === 'approve';

        Task::where('ID', $id)->update([
            'approve_status' => $approving ? 'Approved' : 'Rejected',
            'approved_by' => $context['user_id'],
            'approved_on' => now(),
            'approve_remarks' => $request->input('remarks'),
            // A rejected task goes back into the working pile: ON HOLD is
            // how the board represents "rework required".
            'STATUS' => $approving ? $task->STATUS : 'ON HOLD',
            'updated_by' => $context['user_id'],
            'updated_at' => now(),
        ]);

        $this->logTaskActivity($context['sub_institute_id'], $context['user_id'], $approving ? 'approved' : 'rejected', (string) $request->input('remarks'), null, null, null, null, $id);

        AuditLog::record([
            'module' => 'task_management',
            'action' => $approving ? 'task.approve' : 'task.reject',
            'entity_type' => 'task',
            'entity_id' => $id,
            'new_values' => [
                'approve_status' => $approving ? 'Approved' : 'Rejected',
                'approved_by' => $context['user_id'],
                'approve_remarks' => $request->input('remarks'),
                'status' => $approving ? $task->STATUS : 'ON HOLD',
            ],
        ]);

        return $this->taskManagementResponse(null, $approving ? 'Task approved.' : 'Task sent back for rework.');
    }

    public function comment(Request $request, int $id)
    {
        $context = $this->taskManagementContext($request);

        $request->validate(['content' => 'required|string|max:5000']);

        if (!$this->findTenantTask($context, $id)) {
            return $this->taskManagementError('Task not found.', 404);
        }

        $commentId = DB::table('task_management_comments')->insertGetId([
            'task_id' => $id,
            'user_id' => $context['user_id'],
            'content' => $request->input('content'),
            'created_by' => $context['user_id'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->taskManagementResponse(['id' => (string) $commentId], 'Comment added.', 201);
    }

    /** The comment thread, newest first, shaped for WorkspaceTask.comments. */
    private function comments(array $context, int $taskId): array
    {
        return DB::table('task_management_comments as c')
            ->leftJoin('tbluser as author', 'author.id', '=', 'c.user_id')
            ->where('c.task_id', $taskId)
            ->orderByDesc('c.id')
            ->limit(100)
            ->selectRaw("c.id, c.content, c.created_at,
                TRIM(CONCAT_WS(' ', author.first_name, author.middle_name, author.last_name)) as author_name")
            ->get()
            ->map(fn ($row) => [
                'id' => (string) $row->id,
                'author' => (string) ($row->author_name ?: 'Unknown'),
                'content' => (string) $row->content,
                'created_at' => (string) $row->created_at,
            ])
            ->all();
    }

    /**
     * Who may edit a task from the workspace: assigned to them, allocated by
     * them, or created by them - mirroring MyTasksController's scoping.
     * Anyone above the Employee profile manages the whole tenant.
     */
    private function canEditTask(array $context, object $task): bool
    {
        $userId = $context['user_id'];

        if ((int) $task->TASK_ALLOCATED_TO === $userId || (int) $task->TASK_ALLOCATED === $userId || (int) $task->CREATED_BY === $userId) {
            return true;
        }

        return !$this->isEmployeeProfile($userId);
    }

    /** True when the user sits on the Employee profile (the non-managing role). */
    private function isEmployeeProfile(int $userId): bool
    {
        $profile = DB::table('tbluser')
            ->leftJoin('tbluserprofilemaster as p', 'p.id', '=', 'tbluser.user_profile_id')
            ->where('tbluser.id', $userId)
            ->value('p.name');

        return strcasecmp(trim((string) $profile), 'Employee') === 0;
    }

    private function findTenantTask(array $context, int $id): ?object
    {
        return DB::table('task')
            ->where('ID', $id)
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->where('SYEAR', $context['syear'])
            ->whereNull('deleted_at')
            ->first();
    }

    /**
     * The list query. `$scope` decides soft-delete handling: 'archived'
     * shows only trashed rows, everything else hides them.
     */
    private function baseQuery(array $context, string $scope)
    {
        $query = DB::table('task as t')
            ->leftJoin('tbluser as allocator', 'allocator.id', '=', 't.TASK_ALLOCATED')
            ->leftJoin('tbluser as assignee', 'assignee.id', '=', 't.TASK_ALLOCATED_TO')
            ->leftJoin('hrms_departments as department', 'department.id', '=', 'assignee.department_id')
            ->leftJoin(DB::raw('(SELECT task_id, MIN(project_id) AS project_id FROM task_management_project_tasks GROUP BY task_id) pt'), 'pt.task_id', '=', 't.ID')
            ->leftJoin('task_management_projects as proj', 'proj.id', '=', 'pt.project_id')
            ->where('t.sub_institute_id', $context['sub_institute_id'])
            ->where('t.SYEAR', $context['syear'])
            ->selectRaw("t.ID, t.TASK_TITLE, t.TASK_DESCRIPTION, t.task_type, t.TASK_DATE, t.STATUS,
                t.TASK_ALLOCATED, t.TASK_ALLOCATED_TO, t.CREATED_BY, t.CREATED_ON as created_at, t.updated_at,
                t.reply, t.approve_status, t.approved_on, t.TASK_ATTACHMENT, t.FILE_SIZE, t.FILE_TYPE, t.status_label,
                pt.project_id, proj.name as project_name, department.department as department_name,
                TRIM(CONCAT_WS(' ', allocator.first_name, allocator.middle_name, allocator.last_name)) as allocator_name,
                TRIM(CONCAT_WS(' ', assignee.first_name, assignee.middle_name, assignee.last_name)) as assignee_name");

        if ($scope === 'archived') {
            $query->whereNotNull('t.deleted_at');
        } else {
            $query->whereNull('t.deleted_at');
        }

        return $query;
    }

    private function applyScope($query, string $scope, array $context): void
    {
        if ($scope === 'my' || $scope === 'mine') {
            $query->where('t.TASK_ALLOCATED_TO', $context['user_id']);
        } elseif ($scope === 'assigned') {
            $query->where('t.TASK_ALLOCATED', $context['user_id']);
        } elseif ($scope === 'created') {
            $query->where('t.CREATED_BY', $context['user_id']);
        } elseif ($scope === 'team') {
            $query->where(function ($builder) use ($context) {
                $builder->where('t.TASK_ALLOCATED_TO', $context['user_id'])
                    ->orWhereIn('t.TASK_ALLOCATED_TO', DB::table('tbluser')
                        ->select('id')
                        ->where('employee_id', $context['user_id'])
                        ->where('sub_institute_id', $context['sub_institute_id'])
                        ->where('status', 1)
                        ->whereNull('deleted_at'));
            });
        } elseif ($scope === 'department') {
            if ($context['department_id'] > 0) {
                $query->where('assignee.department_id', $context['department_id']);
            }
        }
    }

    /** The four KPI cards, in one round trip via conditional aggregates. */
    private function summary(array $context, string $scope): array
    {
        $effectiveScope = $scope === 'archived' ? 'all' : $scope;

        $query = DB::table('task as t')
            ->leftJoin('tbluser as assignee', 'assignee.id', '=', 't.TASK_ALLOCATED_TO')
            ->where('t.sub_institute_id', $context['sub_institute_id'])
            ->where('t.SYEAR', $context['syear'])
            ->whereNull('t.deleted_at');

        $this->applyScope($query, $effectiveScope, $context);

        $approvedList = "'" . implode("','", self::APPROVED_VALUES) . "'";
        $today = Carbon::today()->toDateString();
        $monthStart = now()->startOfMonth()->toDateTimeString();
        $monthEnd = now()->endOfMonth()->toDateTimeString();

        $row = $query->selectRaw(
            "SUM(CASE WHEN UPPER(COALESCE(t.STATUS,'PENDING')) <> 'COMPLETED' THEN 1 ELSE 0 END) as active,
             SUM(CASE WHEN UPPER(COALESCE(t.STATUS,'')) = 'COMPLETED'
                       AND LOWER(COALESCE(t.approve_status,'')) NOT IN ({$approvedList}) THEN 1 ELSE 0 END) as pending_review,
             SUM(CASE WHEN UPPER(COALESCE(t.STATUS,'')) = 'ON HOLD'
                       OR (t.TASK_DATE < ? AND UPPER(COALESCE(t.STATUS,'PENDING')) <> 'COMPLETED') THEN 1 ELSE 0 END) as blocked_overdue,
             SUM(CASE WHEN UPPER(COALESCE(t.STATUS,'')) = 'COMPLETED'
                       AND t.updated_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as completed_this_month",
            [$today, $monthStart, $monthEnd]
        )->first();

        return [
            'active' => (int) ($row->active ?? 0),
            'pending_review' => (int) ($row->pending_review ?? 0),
            'blocked_overdue' => (int) ($row->blocked_overdue ?? 0),
            'completed_this_month' => (int) ($row->completed_this_month ?? 0),
        ];
    }

    /** Active users of the tenant, for the assignee filter dropdown. */
    private function assignableUsers(array $context): array
    {
        return DB::table('tbluser')
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->where('status', 1)
            ->orderBy('first_name')
            ->limit(200)
            ->selectRaw("id, TRIM(CONCAT_WS(' ', first_name, middle_name, last_name)) as name")
            ->get()
            ->map(fn ($user) => ['id' => (string) $user->id, 'name' => (string) $user->name])
            ->all();
    }

    /** Shapes a row to the frontend's WorkspaceTask type - names must match it. */
    private function resource(object $task, bool $includeMeta = false): array
    {
        $status = strtoupper(trim((string) ($task->STATUS ?: 'PENDING')));
        $status = match ($status) {
            'IN PROGRESS', 'IN-PROGRES' => 'IN-PROGRESS',
            default => in_array($status, self::STATUS_CATEGORIES, true) ? $status : 'PENDING',
        };

        $data = [
            'id' => (string) $task->ID,
            'title' => (string) ($task->TASK_TITLE ?? ''),
            'description' => (string) ($task->TASK_DESCRIPTION ?? ''),
            'project_id' => $task->project_id ? (string) $task->project_id : null,
            'project' => (string) ($task->project_name ?? ''),
            'department' => (string) ($task->department_name ?? ''),
            'assignee_id' => $task->TASK_ALLOCATED_TO ? (string) $task->TASK_ALLOCATED_TO : null,
            'assignee' => (string) ($task->assignee_name ?: 'Unassigned'),
            'owner_id' => $task->TASK_ALLOCATED ? (string) $task->TASK_ALLOCATED : null,
            'owner' => (string) ($task->allocator_name ?: 'Unknown'),
            'status' => $status,
            'status_label' => $task->status_label ?? null,
            'priority' => $task->task_type ?: null,
            'due_date' => $task->TASK_DATE ? Carbon::parse($task->TASK_DATE)->toDateString() : null,
            'remarks' => $task->reply ?? null,
            'approved' => in_array(strtolower(trim((string) ($task->approve_status ?? ''))), self::APPROVED_VALUES, true),
            'approved_on' => $task->approved_on ?? null,
            'created_at' => $task->created_at ? Carbon::parse($task->created_at)->toIso8601String() : null,
            'updated_at' => $task->updated_at ? Carbon::parse($task->updated_at)->toIso8601String() : null,
            'attachment' => null,
        ];

        if ($includeMeta) {
            $data['attachment'] = $task->TASK_ATTACHMENT ? [
                'name' => $task->TASK_ATTACHMENT,
                'type' => $task->FILE_TYPE ?? null,
                'size' => $task->FILE_SIZE ?? null,
            ] : null;
        }

        return $data;
    }
}
