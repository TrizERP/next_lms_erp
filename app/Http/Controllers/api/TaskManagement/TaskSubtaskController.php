<?php

namespace App\Http\Controllers\api\TaskManagement;

use App\Http\Controllers\api\TaskManagement\Concerns\ResolvesTaskManagementContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ported from hp_erp's `App\Http\Controllers\Api\TaskManagement\TaskSubtaskController`.
 *
 * Checklist items under a task: list, add, tick off, remove.
 */
class TaskSubtaskController extends Controller
{
    use ResolvesTaskManagementContext;

    public function index(Request $request, int $id)
    {
        $context = $this->taskManagementContext($request);

        if ($guard = $this->guardTask($context, $id)) {
            return $guard;
        }

        $rows = DB::table('task_management_subtasks')
            ->where('task_id', $id)
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => $this->resource($row));

        return $this->taskManagementResponse([
            'subtasks' => $rows->all(),
            'done' => $rows->where('is_done', true)->count(),
            'total' => $rows->count(),
        ], 'Subtasks retrieved successfully.');
    }

    public function store(Request $request, int $id)
    {
        $context = $this->taskManagementContext($request);

        $request->validate(['title' => 'required|string|max:255']);

        if ($guard = $this->guardTask($context, $id)) {
            return $guard;
        }

        $nextOrder = (int) DB::table('task_management_subtasks')->where('task_id', $id)->max('sort_order') + 1;

        $subtaskId = DB::table('task_management_subtasks')->insertGetId([
            'sub_institute_id' => $context['sub_institute_id'],
            'task_id' => $id,
            'title' => $request->input('title'),
            'sort_order' => $nextOrder,
            'created_by' => $context['user_id'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->logTaskActivity($context['sub_institute_id'], $context['user_id'], 'subtask_added', (string) $request->input('title'), null, null, null, null, $id);

        return $this->taskManagementResponse(['id' => (string) $subtaskId], 'Subtask added.', 201);
    }

    public function toggle(Request $request, int $id, int $subtask)
    {
        $context = $this->taskManagementContext($request);

        $row = DB::table('task_management_subtasks')
            ->where('id', $subtask)
            ->where('task_id', $id)
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->first();

        if (!$row) {
            return $this->taskManagementError('Subtask not found.', 404);
        }

        DB::table('task_management_subtasks')->where('id', $subtask)->update([
            'is_done' => !$row->is_done,
            'updated_at' => now(),
        ]);

        $this->logTaskActivity(
            $context['sub_institute_id'], $context['user_id'],
            $row->is_done ? 'subtask_reopened' : 'subtask_completed',
            (string) $row->title, null, null, null, null, $id
        );

        return $this->taskManagementResponse(['id' => (string) $subtask, 'is_done' => !$row->is_done], 'Subtask updated.');
    }

    public function destroy(Request $request, int $id, int $subtask)
    {
        $context = $this->taskManagementContext($request);

        $row = DB::table('task_management_subtasks')
            ->where('id', $subtask)
            ->where('task_id', $id)
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->first();

        $deleted = $row ? DB::table('task_management_subtasks')->where('id', $subtask)->delete() : 0;

        if ($deleted) {
            $this->logTaskActivity($context['sub_institute_id'], $context['user_id'], 'subtask_removed', (string) $row->title, null, null, null, null, $id);
        }

        return $deleted
            ? $this->taskManagementResponse(null, 'Subtask removed.')
            : $this->taskManagementError('Subtask not found.', 404);
    }

    private function guardTask(array $context, int $taskId)
    {
        $exists = DB::table('task')
            ->where('ID', $taskId)
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->whereNull('deleted_at')
            ->exists();

        return $exists ? null : $this->taskManagementError('Task not found.', 404);
    }

    private function resource(object $row): array
    {
        return [
            'id' => (string) $row->id,
            'title' => (string) $row->title,
            'is_done' => (bool) $row->is_done,
            'sort_order' => (int) $row->sort_order,
        ];
    }
}
