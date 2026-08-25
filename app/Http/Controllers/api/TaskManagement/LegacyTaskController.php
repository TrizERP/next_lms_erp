<?php

namespace App\Http\Controllers\api\TaskManagement;

use App\Http\Controllers\api\TaskManagement\Concerns\ResolvesTaskManagementContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Ported from hp_erp's `App\Http\Controllers\Api\TaskManagement\LegacyTaskController`.
 *
 * JSON CRUD over the legacy `task` table for the new frontend. On create it
 * also does the two side effects the old frontend fired from the browser:
 * notify the assignee, and (when configured) call the n8n task-assigned
 * webhook - server-side now, so they cannot be skipped by a closed tab.
 */
class LegacyTaskController extends Controller
{
    use ResolvesTaskManagementContext;

    public function store(Request $request)
    {
        $context = $this->taskManagementContext($request);

        $request->validate($this->rules());

        $id = DB::table('task')->insertGetId(
            $this->payload($request, $context) + [
                'sub_institute_id' => $context['sub_institute_id'],
                'SYEAR' => $context['syear'],
                'STATUS' => 'PENDING',
                'CREATED_BY' => $context['user_id'],
                'CREATED_ON' => now(),
            ]
        );

        $this->notifyAssignee($request, $context, $id);
        $this->fireWebhook($request, $context, $id);

        return $this->taskManagementResponse(['id' => (string) $id], 'Task created successfully.', 201);
    }

    public function update(Request $request, int $id)
    {
        $context = $this->taskManagementContext($request);

        $request->validate($this->rules());

        $task = $this->find($context, $id);
        if (!$task) {
            return $this->taskManagementError('Task not found.', 404);
        }

        DB::table('task')->where('ID', $id)->update(
            $this->payload($request, $context) + [
                'updated_by' => $context['user_id'],
                'updated_at' => now(),
            ]
        );

        $this->logTaskActivity($context['sub_institute_id'], $context['user_id'], 'legacy_updated', 'Task updated', null, null, null, null, $id);

        return $this->taskManagementResponse(['id' => (string) $id], 'Task updated successfully.');
    }

    public function destroy(Request $request, int $id)
    {
        $context = $this->taskManagementContext($request);

        $task = $this->find($context, $id);
        if (!$task) {
            return $this->taskManagementError('Task not found.', 404);
        }

        DB::table('task')->where('ID', $id)->update([
            'deleted_by' => $context['user_id'],
            'deleted_at' => now(),
            'updated_at' => now(),
        ]);

        $this->logTaskActivity($context['sub_institute_id'], $context['user_id'], 'legacy_deleted', 'Task deleted', null, null, null, null, $id);

        return $this->taskManagementResponse(null, 'Task deleted successfully.');
    }

    /* ------------------------------------------------------------------ */

    protected function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:10000',
            'due_date' => 'nullable|date',
            'priority' => 'nullable|string|max:100',
            'assignee_id' => 'required|integer',
            'owner_id' => 'nullable|integer',
            'kra' => 'nullable|string|max:1000',
            'kpa' => 'nullable|string|max:1000',
            'required_skills' => 'nullable|string|max:2000',
            'observation_point' => 'nullable|string|max:2000',
        ];
    }

    protected function payload(Request $request, array $context): array
    {
        return [
            'TASK_TITLE' => $request->input('title'),
            'TASK_DESCRIPTION' => $request->input('description'),
            'TASK_DATE' => $request->input('due_date'),
            'task_type' => $this->resolveTaskPriorityValue($context['sub_institute_id'], (string) $request->input('priority', 'Medium')) ?? 'Medium',
            'TASK_ALLOCATED_TO' => $request->integer('assignee_id'),
            'TASK_ALLOCATED' => $request->input('owner_id') ? $request->integer('owner_id') : $context['user_id'],
            'KRA' => $request->input('kra'),
            'KPA' => $request->input('kpa'),
            'required_skill' => $request->input('required_skills'),
            'observation_point' => $request->input('observation_point'),
        ];
    }

    protected function find(array $context, int $id): ?object
    {
        return DB::table('task')
            ->where('ID', $id)
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->whereNull('deleted_at')
            ->first();
    }

    private function notifyAssignee(Request $request, array $context, int $taskId): void
    {
        NotificationController::notify(
            $context['sub_institute_id'],
            $request->integer('assignee_id'),
            'task_assigned',
            'New task: ' . $request->input('title'),
            $request->input('description'),
            $taskId
        );
    }

    /**
     * The old frontend posted the new assignment to an n8n webhook from the
     * browser. Server-side and config-driven now: no URL configured, no
     * call, and a webhook failure never fails the create.
     */
    private function fireWebhook(Request $request, array $context, int $taskId): void
    {
        $url = (string) config('services.n8n.task_webhook', '');
        if ($url === '') {
            return;
        }

        try {
            Http::timeout(5)->post($url, [
                'task_id' => $taskId,
                'title' => $request->input('title'),
                'assignee_id' => $request->integer('assignee_id'),
                'due_date' => $request->input('due_date'),
                'sub_institute_id' => $context['sub_institute_id'],
            ]);
        } catch (\Throwable $exception) {
            Log::warning('task.n8n_webhook_failed', ['task_id' => $taskId, 'error' => $exception->getMessage()]);
        }
    }
}
