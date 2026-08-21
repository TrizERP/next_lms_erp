<?php

namespace App\Http\Controllers\api\TaskManagement;

use App\Http\Controllers\api\TaskManagement\Concerns\ResolvesTaskManagementContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ported from hp_erp's `App\Http\Controllers\Api\TaskManagement\TaskRecurrenceController`.
 *
 * A task's recurrence rule (one per task). The legacy `repeat_days` column
 * on `task` is left untouched because the old screens still read it.
 */
class TaskRecurrenceController extends Controller
{
    use ResolvesTaskManagementContext;

    public function show(Request $request, int $id)
    {
        $context = $this->taskManagementContext($request);

        $row = DB::table('task_management_recurrences')
            ->where('task_id', $id)
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->first();

        return $this->taskManagementResponse(['recurrence' => $row ? $this->resource($row) : null], 'Recurrence retrieved successfully.');
    }

    public function upsert(Request $request, int $id)
    {
        $context = $this->taskManagementContext($request);

        $request->validate([
            'frequency' => 'required|in:daily,weekly,monthly',
            'interval' => 'nullable|integer|min:1|max:52',
            'until' => 'nullable|date',
        ]);

        $taskExists = DB::table('task')
            ->where('ID', $id)
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->whereNull('deleted_at')
            ->exists();

        if (!$taskExists) {
            return $this->taskManagementError('Task not found.', 404);
        }

        DB::table('task_management_recurrences')->updateOrInsert(
            ['task_id' => $id],
            [
                'sub_institute_id' => $context['sub_institute_id'],
                'frequency' => $request->input('frequency'),
                'interval' => (int) $request->input('interval', 1),
                'until' => $request->input('until'),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $row = DB::table('task_management_recurrences')->where('task_id', $id)->first();

        return $this->taskManagementResponse(['recurrence' => $this->resource($row)], 'Recurrence saved.');
    }

    public function destroy(Request $request, int $id)
    {
        $context = $this->taskManagementContext($request);

        $deleted = DB::table('task_management_recurrences')
            ->where('task_id', $id)
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->delete();

        return $deleted
            ? $this->taskManagementResponse(null, 'Recurrence removed.')
            : $this->taskManagementError('No recurrence on this task.', 404);
    }

    private function resource(object $row): array
    {
        return [
            'task_id' => (string) $row->task_id,
            'frequency' => (string) $row->frequency,
            'interval' => (int) $row->interval,
            'until' => $row->until,
        ];
    }
}
