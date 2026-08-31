<?php

namespace App\Http\Controllers\api\TaskManagement;

use App\Http\Controllers\api\TaskManagement\Concerns\ResolvesTaskManagementContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ported from hp_erp's `App\Http\Controllers\Api\TaskManagement\TaskListController`
 * (the Dashboard's lightweight, cursor-paginated task list).
 */
class TaskListController extends Controller
{
    use ResolvesTaskManagementContext;

    public function index(Request $request)
    {
        $context = $this->taskManagementContext($request);

        $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
            'cursor' => 'nullable|string|max:2000',
            'search' => 'nullable|string|max:191',
            'status' => 'nullable|string|max:50',
            'priority' => 'nullable|string|max:50',
            'assignee_id' => 'nullable|integer',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        $query = DB::table('task')
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->where('SYEAR', $context['syear'])
            ->whereNull('deleted_at');

        if ($request->filled('search')) {
            $search = $this->escapeTaskLike(trim((string) $request->input('search')));
            $query->where(fn ($q) => $q->where('TASK_TITLE', 'like', "%{$search}%")->orWhere('TASK_DESCRIPTION', 'like', "%{$search}%"));
        }
        if ($request->filled('status')) {
            $query->whereRaw('UPPER(STATUS) = ?', [strtoupper($request->input('status'))]);
        }
        if ($request->filled('priority')) {
            $query->where('task_type', $request->input('priority'));
        }
        if ($request->filled('assignee_id')) {
            $query->where('TASK_ALLOCATED_TO', $request->integer('assignee_id'));
        }
        if ($request->filled('from')) {
            $query->whereDate('TASK_DATE', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('TASK_DATE', '<=', $request->input('to'));
        }

        $tasks = $query->orderByDesc('ID')->cursorPaginate($request->integer('per_page', 50));

        return $this->taskManagementResponse([
            'tasks' => collect($tasks->items())->map(fn ($task) => [
                'id' => (string) $task->ID,
                'task_code' => (string) $task->ID,
                'title' => $task->TASK_TITLE,
                'description' => $task->TASK_DESCRIPTION,
                'assignee_id' => $task->TASK_ALLOCATED_TO ? (string) $task->TASK_ALLOCATED_TO : null,
                'owner_id' => $task->TASK_ALLOCATED ? (string) $task->TASK_ALLOCATED : null,
                'status' => $task->STATUS,
                'priority' => $task->task_type,
                'due_date' => $task->TASK_DATE,
            ])->all(),
            'pagination' => [
                'per_page' => $tasks->perPage(),
                'next_cursor' => $tasks->nextCursor()?->encode(),
                'previous_cursor' => $tasks->previousCursor()?->encode(),
                'has_more' => $tasks->hasMorePages(),
            ],
        ], 'Tasks retrieved successfully.');
    }
}
