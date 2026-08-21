<?php

namespace App\Http\Controllers\api\TaskManagement;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ported from hp_erp's `App\Http\Controllers\Api\TaskManagement\IdempotentTaskController`.
 *
 * Create-task with an idempotency key: retrying the same request (flaky
 * network, double click, queue redelivery) returns the task the first
 * attempt created instead of a duplicate.
 */
class IdempotentTaskController extends LegacyTaskController
{
    public function store(Request $request)
    {
        $context = $this->taskManagementContext($request);

        $request->validate($this->rules() + ['idempotency_key' => 'required|string|max:100']);

        $key = (string) $request->input('idempotency_key');

        $existing = DB::table('task_management_idempotency_keys')
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->where('idempotency_key', $key)
            ->first();

        if ($existing) {
            // 200, not 201: nothing was created by THIS request.
            return $this->taskManagementResponse([
                'id' => (string) $existing->task_id,
                'replayed' => true,
            ], 'Task already created for this idempotency key.');
        }

        $response = parent::store($request);

        if ($response->getStatusCode() === 201) {
            $taskId = (int) (json_decode((string) $response->getContent(), true)['data']['id'] ?? 0);

            if ($taskId > 0) {
                DB::table('task_management_idempotency_keys')->insert([
                    'sub_institute_id' => $context['sub_institute_id'],
                    'idempotency_key' => $key,
                    'task_id' => $taskId,
                    'created_at' => now(),
                ]);
            }
        }

        return $response;
    }
}
