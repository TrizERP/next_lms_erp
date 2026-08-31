<?php

namespace App\Http\Controllers\api\TaskManagement;

use App\Http\Controllers\api\Concerns\RequiresTalentAdmin;
use App\Http\Controllers\api\TaskManagement\Concerns\ResolvesTaskManagementContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * `POST /api/competency/library/jobrole-tasks` - the Create Task modal's
 * "Also save to the Job Role Task library" checkbox (`saveJobRoleTaskToLibrary`
 * in `app/task-management/_lib/my-tasks-api.ts`), which promotes a
 * custom-typed task title into the job role's task catalogue.
 *
 * hp_erp's equivalent route wires this to
 * `App\Http\Controllers\Api\Competency\LibraryController::storeJobroleTask`,
 * a thin wrapper around a large generic `storeResource('jobrole-task', ...)`
 * CRUD framework (taxonomy/skills/jobroles/KASA/invisible-library resources,
 * none of which exist in this target and none of which the create-task
 * modal calls). Porting that whole framework for one form field is out of
 * scope; this inserts directly into the same `s_user_jobrole_task` table
 * hp_erp's `storeResource('jobrole-task', ...)` ultimately writes to - the
 * same table `getJobRoleTasks` (My Tasks API) and `tbluserController::edit`'s
 * `jobroleTasks` already read from - so a saved title immediately shows up
 * in the "From job role" catalogue dropdown next time.
 */
class JobRoleTaskLibraryController extends Controller
{
    use ResolvesTaskManagementContext;
    use RequiresTalentAdmin;

    public function store(Request $request)
    {
        if ($response = $this->assertIsAdmin()) { return $response; }

        $context = $this->taskManagementContext($request);

        $task = trim((string) $request->input('task'));
        $jobrole = trim((string) $request->input('jobrole'));
        $taskType = trim((string) $request->input('task_type'));

        if ($task === '' || $jobrole === '') {
            return $this->taskManagementError('task and jobrole are required.', 422);
        }

        $existing = DB::table('s_user_jobrole_task')
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->where('jobrole', $jobrole)
            ->where('task', $task)
            ->whereNull('deleted_at')
            ->first();

        if ($existing) {
            return $this->taskManagementResponse(['id' => $existing->id], 'This task is already in the job role library.');
        }

        $id = DB::table('s_user_jobrole_task')->insertGetId([
            'jobrole' => $jobrole,
            'task' => $task,
            'task_type' => $taskType !== '' ? $taskType : null,
            'sub_institute_id' => $context['sub_institute_id'],
            'created_by' => $context['user_id'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        AuditLog::record([
            'module' => 'task_management',
            'action' => 'jobrole_task_library.store',
            'entity_type' => 's_user_jobrole_task',
            'entity_id' => $id,
            'new_values' => [
                'jobrole' => $jobrole,
                'task' => $task,
                'task_type' => $taskType !== '' ? $taskType : null,
            ],
        ]);

        return $this->taskManagementResponse(['id' => $id], 'Added to the job role task library.', 201);
    }
}
