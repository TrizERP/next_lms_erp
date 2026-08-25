<?php

namespace App\Http\Controllers\api\TaskManagement;

use App\Http\Controllers\api\TaskManagement\Concerns\ResolvesTaskManagementContext;
use App\Http\Controllers\Controller;
use App\Models\TaskManagement\Task;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ported from hp_erp's `App\Http\Controllers\Api\TaskManagement\MyTasksController`.
 *
 * The Next.js Task Management > My Tasks screen. Additive: legacy /task and
 * /task/update-status routes remain unchanged.
 */
class MyTasksController extends Controller
{
    use ResolvesTaskManagementContext;

    public function index(Request $request)
    {
        $context = $this->taskManagementContext($request);

        $request->validate([
            'group' => 'nullable|in:all,today,upcoming,recent,subordinates',
            'search' => 'nullable|string|max:191',
            'status' => 'nullable|string|max:100',
            'priority' => 'nullable|string|max:100',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = $this->baseQuery($context);
        $this->applyGroup($query, $request->input('group', 'all'), $context);

        if ($request->filled('search')) {
            $search = $this->escapeTaskLike(trim((string) $request->input('search')));
            $query->where(function (Builder $builder) use ($search) {
                $builder
                    ->where('t.TASK_TITLE', 'like', "%{$search}%")
                    ->orWhere('t.TASK_DESCRIPTION', 'like', "%{$search}%")
                    ->orWhereRaw("CONCAT_WS(' ', allocator.first_name, allocator.middle_name, allocator.last_name) like ?", ["%{$search}%"]);
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

        $tasks = $query
            ->orderByRaw('CASE WHEN t.TASK_DATE IS NULL THEN 1 ELSE 0 END')
            ->orderBy('t.TASK_DATE')
            ->orderByDesc('t.ID')
            ->paginate((int) $request->input('per_page', 20));

        $tasks->getCollection()->transform(fn ($task) => $this->taskResource($task));

        return $this->taskManagementResponse([
            'tasks' => $tasks->items(),
            'summary' => $this->summary($context),
            'pagination' => [
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
                'per_page' => $tasks->perPage(),
                'total' => $tasks->total(),
            ],
            'filters' => [
                'statuses' => self::STATUS_CATEGORIES,
                'priorities' => self::SYSTEM_PRIORITIES,
                'status_options' => $this->taskStatusOptions($context['sub_institute_id']),
                'priority_options' => $this->taskPriorityOptions($context['sub_institute_id']),
            ],
        ], 'Tasks retrieved successfully.');
    }

    public function show(Request $request, int $id)
    {
        $context = $this->taskManagementContext($request);

        $task = $this->baseQuery($context)
            ->where('t.ID', $id)
            ->where(function (Builder $query) use ($context) {
                $query
                    ->where('t.TASK_ALLOCATED_TO', $context['user_id'])
                    ->orWhere('t.TASK_ALLOCATED', $context['user_id'])
                    ->orWhereIn('t.TASK_ALLOCATED_TO', $this->subordinateIds($context));
            })
            ->first();

        if (!$task) {
            return $this->taskManagementError('Task not found.', 404);
        }

        return $this->taskManagementResponse($this->taskResource($task, true), 'Task retrieved successfully.');
    }

    public function updateStatus(Request $request, int $id)
    {
        $context = $this->taskManagementContext($request);

        $request->validate([
            'status' => 'required|string|max:100',
            'remarks' => 'required|string|max:5000',
            'delay_category' => 'nullable|in:Dependency,Resource,Scope,Technical,Approval,External,Other',
            'delay_reason' => 'nullable|string|max:5000',
        ]);

        $task = Task::where('ID', $id)
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->where('SYEAR', $context['syear'])
            ->where('TASK_ALLOCATED_TO', $context['user_id'])
            ->whereNull('deleted_at')
            ->first();

        if (!$task) {
            return $this->taskManagementError('Task not found or cannot be updated.', 404);
        }

        $resolved = $this->resolveTaskStatusValue($context['sub_institute_id'], (string) $request->input('status'));
        if ($resolved === null) {
            return $this->taskManagementError('Unknown status for this organisation.', 422);
        }

        $move = $this->writeTaskStatus(
            $task,
            $resolved['status'],
            $resolved['label'],
            (int) $context['user_id'],
            $request->input('delay_category'),
            $request->input('delay_reason')
        );

        if (!$move['ok']) {
            return $this->taskManagementError($move['reason'], 422);
        }

        // `reply` is not a status field and stays here.
        Task::where('ID', $id)->update(['reply' => $request->input('remarks')]);
        $this->logTaskActivity($context['sub_institute_id'], $context['user_id'], 'status_changed', $request->input('remarks'), null, null, null, null, $id);

        if ($resolved['status'] === 'COMPLETED') {
            $this->resolveTaskDependenciesAfterCompletion($id, $context['user_id']);
        }

        // Out-of-order work is reported, not refused - see
        // ResolvesTaskManagementContext::openPredecessors().
        $warnings = [];
        if (in_array($resolved['status'], ['IN-PROGRESS', 'COMPLETED'], true)) {
            $open = $this->openPredecessors($id, $context['sub_institute_id'], $context['syear']);
            foreach ($open as $predecessor) {
                $warnings[] = [
                    'code' => 'predecessor_open',
                    'message' => 'This task depends on "' . $predecessor['title'] . '", which is still ' . $predecessor['status'] . '.',
                    'task_id' => $predecessor['id'],
                ];
            }
        }

        return $this->taskManagementResponse([
            'id' => (string) $id,
            'status' => $resolved['status'],
            'status_label' => $resolved['label'],
            'remarks' => $request->input('remarks'),
        ], 'Task status updated successfully.', 200, ['warnings' => $warnings]);
    }

    private function baseQuery(array $context): Builder
    {
        return DB::table('task as t')
            ->leftJoin('tbluser as assignee', function ($join) use ($context) {
                $join->on('t.TASK_ALLOCATED_TO', '=', 'assignee.id')->where('assignee.sub_institute_id', $context['sub_institute_id']);
            })
            ->leftJoin('tbluser as allocator', function ($join) use ($context) {
                $join->on('t.TASK_ALLOCATED', '=', 'allocator.id')->where('allocator.sub_institute_id', $context['sub_institute_id']);
            })
            ->leftJoin('hrms_departments as department', 'department.id', '=', 'assignee.department_id')
            ->where('t.sub_institute_id', $context['sub_institute_id'])
            ->where('t.SYEAR', $context['syear'])
            ->whereNull('t.deleted_at')
            ->select([
                't.ID', 't.TASK_TITLE', 't.TASK_DESCRIPTION', 't.TASK_ATTACHMENT',
                't.FILE_SIZE', 't.FILE_TYPE', 't.TASK_DATE', 't.planned_start_date', 't.estimated_hours',
                't.actual_hours', 't.remaining_hours', 't.acceptance_criteria', 't.task_type',
                't.STATUS', 't.status_label', 't.reply', 't.delay_category', 't.delay_reason', 't.observation_point',
                't.CREATED_ON as created_at', 't.updated_at', 't.TASK_ALLOCATED', 't.TASK_ALLOCATED_TO',
                DB::raw("TRIM(CONCAT_WS(' ', assignee.first_name, assignee.middle_name, assignee.last_name)) as assignee_name"),
                DB::raw("TRIM(CONCAT_WS(' ', allocator.first_name, allocator.middle_name, allocator.last_name)) as allocator_name"),
                'department.department as department_name',
            ]);
    }

    private function applyGroup(Builder $query, string $group, array $context): void
    {
        if ($group === 'subordinates') {
            $query->whereIn('t.TASK_ALLOCATED_TO', $this->subordinateIds($context));
            return;
        }

        $query->where('t.TASK_ALLOCATED_TO', $context['user_id']);
        $today = Carbon::today()->toDateString();

        if ($group === 'today') {
            $query->whereDate('t.TASK_DATE', $today);
        } elseif ($group === 'upcoming') {
            $query->whereDate('t.TASK_DATE', '>', $today)->whereRaw("UPPER(COALESCE(t.STATUS, 'PENDING')) <> 'COMPLETED'");
        } elseif ($group === 'recent') {
            $query->where('t.updated_at', '>=', now()->subDays(7));
        }
    }

    private function summary(array $context): array
    {
        $query = DB::table('task')
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->where('SYEAR', $context['syear'])
            ->where('TASK_ALLOCATED_TO', $context['user_id'])
            ->whereNull('deleted_at');

        return [
            'due_today' => (clone $query)->whereDate('TASK_DATE', Carbon::today())->whereRaw("UPPER(COALESCE(STATUS, 'PENDING')) <> 'COMPLETED'")->count(),
            'on_hold' => (clone $query)->whereRaw("UPPER(STATUS) = 'ON HOLD'")->count(),
            'in_progress' => (clone $query)->whereRaw("UPPER(STATUS) IN ('IN-PROGRESS', 'IN PROGRESS', 'IN-PROGRES')")->count(),
            'completed_this_month' => (clone $query)->whereRaw("UPPER(STATUS) = 'COMPLETED'")->whereBetween('updated_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
        ];
    }

    private function subordinateIds(array $context)
    {
        return DB::table('tbluser')
            ->select('id')
            ->where('employee_id', $context['user_id'])
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->where('status', 1);
    }

    private function taskResource(object $task, bool $includeDetails = false): array
    {
        $status = strtoupper(trim((string) ($task->STATUS ?: 'PENDING')));
        $status = match ($status) {
            'IN PROGRESS', 'IN-PROGRES' => 'IN-PROGRESS',
            default => in_array($status, self::STATUS_CATEGORIES, true) ? $status : 'PENDING',
        };

        $resource = [
            'id' => (string) $task->ID,
            'title' => (string) $task->TASK_TITLE,
            'description' => (string) ($task->TASK_DESCRIPTION ?? ''),
            'assignee' => (string) ($task->assignee_name ?: 'Unassigned'),
            'assignee_id' => $task->TASK_ALLOCATED_TO ? (string) $task->TASK_ALLOCATED_TO : null,
            'owner' => (string) ($task->allocator_name ?: 'Unknown'),
            'owner_id' => $task->TASK_ALLOCATED ? (string) $task->TASK_ALLOCATED : null,
            'department' => (string) ($task->department_name ?? ''),
            'status' => $status,
            'status_label' => $task->status_label ?? null,
            'priority' => $task->task_type ?: null,
            'task_type' => $task->task_type,
            'estimated_hours' => $task->estimated_hours !== null ? (float) $task->estimated_hours : null,
            'actual_hours' => $task->actual_hours !== null ? (float) $task->actual_hours : null,
            'remaining_hours' => $task->remaining_hours !== null ? (float) $task->remaining_hours : null,
            'acceptance_criteria' => json_decode((string) ($task->acceptance_criteria ?? '[]'), true) ?: [],
            'planned_start_date' => $task->planned_start_date ? Carbon::parse($task->planned_start_date)->toDateString() : null,
            'due_date' => $task->TASK_DATE ? Carbon::parse($task->TASK_DATE)->toDateString() : null,
            'delay_category' => $task->delay_category, 'delay_reason' => $task->delay_reason,
            'remarks' => $task->reply,
            'created_at' => $task->created_at ? Carbon::parse($task->created_at)->toIso8601String() : null,
            'updated_at' => $task->updated_at ? Carbon::parse($task->updated_at)->toIso8601String() : null,
        ];

        if ($includeDetails) {
            $resource['observation_point'] = $task->observation_point;
            $resource['attachment'] = $task->TASK_ATTACHMENT ? [
                'name' => $task->TASK_ATTACHMENT,
                'type' => $task->FILE_TYPE,
                'size' => $task->FILE_SIZE,
            ] : null;
        }

        return $resource;
    }
}
