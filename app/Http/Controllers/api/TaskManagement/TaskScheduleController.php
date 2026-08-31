<?php

namespace App\Http\Controllers\api\TaskManagement;

use App\Http\Controllers\api\TaskManagement\Concerns\ResolvesTaskManagementContext;
use App\Http\Controllers\Controller;
use App\Models\TaskManagement\Task;
use Illuminate\Http\Request;

/**
 * Ported from hp_erp's `App\Http\Controllers\Api\TaskManagement\TaskScheduleController`.
 *
 * A task's schedule window: planned start, due date and the effort envelope.
 * Reads and writes columns on `task` itself.
 */
class TaskScheduleController extends Controller
{
    use ResolvesTaskManagementContext;

    public function show(Request $request, int $id)
    {
        $context = $this->taskManagementContext($request);

        $task = $this->find($context, $id);
        if (!$task) {
            return $this->taskManagementError('Task not found.', 404);
        }

        return $this->taskManagementResponse(['schedule' => $this->resource($task)], 'Schedule retrieved successfully.');
    }

    public function update(Request $request, int $id)
    {
        $context = $this->taskManagementContext($request);

        $request->validate([
            'planned_start_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:planned_start_date',
            'estimated_hours' => 'nullable|numeric|min:0|max:10000',
            'remaining_hours' => 'nullable|numeric|min:0|max:10000',
        ]);

        $task = $this->find($context, $id);
        if (!$task) {
            return $this->taskManagementError('Task not found.', 404);
        }

        $update = ['updated_by' => $context['user_id'], 'updated_at' => now()];
        if ($request->has('planned_start_date')) {
            $update['planned_start_date'] = $request->input('planned_start_date');
        }
        if ($request->has('due_date')) {
            $update['TASK_DATE'] = $request->input('due_date');
        }
        if ($request->has('estimated_hours')) {
            $update['estimated_hours'] = $request->input('estimated_hours');
        }
        if ($request->has('remaining_hours')) {
            $update['remaining_hours'] = $request->input('remaining_hours');
        }

        Task::where('ID', $id)->update($update);

        $detail = 'planned_start ' . ($task->planned_start_date ?? '-')
            . ', due ' . ($task->TASK_DATE ?? '-')
            . ', estimated ' . ($task->estimated_hours ?? '-') . 'h';

        $this->logTaskActivity($context['sub_institute_id'], $context['user_id'], 'schedule_updated', $detail, null, null, null, null, $id);

        return $this->taskManagementResponse(['schedule' => $this->resource($this->find($context, $id))], 'Schedule updated.');
    }

    private function find(array $context, int $id): ?Task
    {
        return Task::where('ID', $id)
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->whereNull('deleted_at')
            ->first();
    }

    private function resource(Task $task): array
    {
        return [
            'task_id' => (string) $task->ID,
            'planned_start_date' => $task->planned_start_date,
            'due_date' => $task->TASK_DATE,
            'estimated_hours' => $task->estimated_hours !== null ? (float) $task->estimated_hours : null,
            'actual_hours' => $task->actual_hours !== null ? (float) $task->actual_hours : null,
            'remaining_hours' => $task->remaining_hours !== null ? (float) $task->remaining_hours : null,
        ];
    }
}
