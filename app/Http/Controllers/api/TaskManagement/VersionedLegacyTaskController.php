<?php

namespace App\Http\Controllers\api\TaskManagement;

use Illuminate\Http\Request;

/**
 * Ported from hp_erp's `App\Http\Controllers\Api\TaskManagement\VersionedLegacyTaskController`.
 *
 * Update-task with optimistic locking: the caller states the updated_at it
 * last saw, and a mismatch means someone else changed the task in between -
 * refused with 409 so the second editor merges instead of silently
 * overwriting the first.
 */
class VersionedLegacyTaskController extends LegacyTaskController
{
    public function update(Request $request, int $id)
    {
        $context = $this->taskManagementContext($request);

        $request->validate($this->rules() + ['expected_updated_at' => 'required|date']);

        $task = $this->find($context, $id);
        if (!$task) {
            return $this->taskManagementError('Task not found.', 404);
        }

        $expected = strtotime((string) $request->input('expected_updated_at'));
        $actual = $task->updated_at ? strtotime((string) $task->updated_at) : null;

        if ($actual !== null && $expected !== $actual) {
            return response()->json([
                'status' => 0,
                'message' => 'This task was modified by someone else. Reload it and reapply your changes.',
                'current_updated_at' => $task->updated_at,
            ], 409);
        }

        return parent::update($request, $id);
    }
}
