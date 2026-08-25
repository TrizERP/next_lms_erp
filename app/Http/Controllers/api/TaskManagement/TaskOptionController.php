<?php

namespace App\Http\Controllers\api\TaskManagement;

use App\Http\Controllers\api\TaskManagement\Concerns\ResolvesTaskManagementContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ported from hp_erp's `App\Http\Controllers\Api\TaskManagement\TaskOptionController`.
 *
 * CRUD for a tenant's custom statuses and priorities (Status Management).
 * System entries are constants and cannot be edited or removed - they are the
 * workflow engine. Custom statuses are labels mapped onto a system category;
 * custom priorities are ordered names with an optional SLA. Delete is a
 * deactivation: tasks may still carry the label, so the row must survive for
 * display.
 */
class TaskOptionController extends Controller
{
    use ResolvesTaskManagementContext;

    /* ---------------- statuses ---------------- */

    public function statuses(Request $request)
    {
        $context = $this->taskManagementContext($request);

        return $this->taskManagementResponse([
            'statuses' => $this->taskStatusOptions($context['sub_institute_id'], false),
            'categories' => self::STATUS_CATEGORIES,
        ], 'Statuses retrieved successfully.');
    }

    public function storeStatus(Request $request)
    {
        $context = $this->taskManagementContext($request);

        $request->validate([
            'name' => 'required|string|max:100',
            'category' => 'required|in:PENDING,IN-PROGRESS,ON HOLD,COMPLETED',
            'color' => 'nullable|string|max:30',
            'sort_order' => 'nullable|integer|min:0|max:1000',
        ]);

        if ($this->statusNameTaken($context['sub_institute_id'], (string) $request->input('name'))) {
            return $this->taskManagementError('A status with this name already exists.', 409);
        }

        $id = DB::table('task_management_statuses')->insertGetId([
            'sub_institute_id' => $context['sub_institute_id'],
            'name' => trim((string) $request->input('name')),
            'category' => $request->input('category'),
            'color' => $request->input('color'),
            'sort_order' => (int) $request->input('sort_order', 50),
            'created_by' => $context['user_id'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->logTaskActivity($context['sub_institute_id'], $context['user_id'], 'status_created', 'Status "' . trim((string) $request->input('name')) . '"', 'status', $id, trim((string) $request->input('name')));

        return $this->taskManagementResponse(['id' => (string) $id], 'Status created.', 201);
    }

    public function updateStatus(Request $request, int $id)
    {
        $context = $this->taskManagementContext($request);

        $request->validate([
            'name' => 'required|string|max:100',
            'category' => 'required|in:PENDING,IN-PROGRESS,ON HOLD,COMPLETED',
            'color' => 'nullable|string|max:30',
            'sort_order' => 'nullable|integer|min:0|max:1000',
            'active' => 'nullable|boolean',
        ]);

        $row = DB::table('task_management_statuses')->where('id', $id)->where('sub_institute_id', $context['sub_institute_id'])->first();
        if (!$row) {
            return $this->taskManagementError('Status not found.', 404);
        }

        $newName = trim((string) $request->input('name'));

        if (strcasecmp($newName, $row->name) !== 0 && $this->statusNameTaken($context['sub_institute_id'], $newName)) {
            return $this->taskManagementError('A status with this name already exists.', 409);
        }

        DB::table('task_management_statuses')->where('id', $id)->update([
            'name' => $newName,
            'category' => $request->input('category'),
            'color' => $request->input('color'),
            'sort_order' => (int) $request->input('sort_order', $row->sort_order),
            'active' => $request->boolean('active', (bool) $row->active),
            'updated_at' => now(),
        ]);

        if ($newName !== $row->name) {
            DB::table('task')->where('sub_institute_id', $context['sub_institute_id'])->where('status_label', $row->name)->update(['status_label' => $newName]);
        }

        $this->logTaskActivity(
            $context['sub_institute_id'], $context['user_id'], 'status_updated',
            'Status "' . $row->name . '"' . ($newName !== $row->name ? ' renamed to "' . $newName . '"' : ''),
            'status', $id, $newName
        );

        return $this->taskManagementResponse(null, 'Status updated.');
    }

    public function destroyStatus(Request $request, int $id)
    {
        $context = $this->taskManagementContext($request);

        $row = DB::table('task_management_statuses')->where('id', $id)->where('sub_institute_id', $context['sub_institute_id'])->first();
        $updated = $row ? DB::table('task_management_statuses')->where('id', $id)->update(['active' => false, 'updated_at' => now()]) : 0;

        if ($updated) {
            $this->logTaskActivity($context['sub_institute_id'], $context['user_id'], 'status_deactivated', 'Status "' . $row->name . '"', 'status', $id, $row->name);
        }

        return $updated
            ? $this->taskManagementResponse(null, 'Status deactivated. Tasks already using it keep their label.')
            : $this->taskManagementError('Status not found.', 404);
    }

    /* ---------------- priorities ---------------- */

    public function priorities(Request $request)
    {
        $context = $this->taskManagementContext($request);

        return $this->taskManagementResponse(['priorities' => $this->taskPriorityOptions($context['sub_institute_id'], false)], 'Priorities retrieved successfully.');
    }

    public function storePriority(Request $request)
    {
        $context = $this->taskManagementContext($request);

        $request->validate([
            'name' => 'required|string|max:100',
            'sort_order' => 'nullable|integer|min:0|max:1000',
            'sla_hours' => 'nullable|integer|min:1|max:8760',
        ]);

        if ($this->priorityNameTaken($context['sub_institute_id'], (string) $request->input('name'))) {
            return $this->taskManagementError('A priority with this name already exists.', 409);
        }

        $id = DB::table('task_management_priorities')->insertGetId([
            'sub_institute_id' => $context['sub_institute_id'],
            'name' => trim((string) $request->input('name')),
            'sort_order' => (int) $request->input('sort_order', 50),
            'sla_hours' => $request->input('sla_hours'),
            'created_by' => $context['user_id'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->logTaskActivity($context['sub_institute_id'], $context['user_id'], 'priority_created', 'Priority "' . trim((string) $request->input('name')) . '"', 'priority', $id, trim((string) $request->input('name')));

        return $this->taskManagementResponse(['id' => (string) $id], 'Priority created.', 201);
    }

    public function updatePriority(Request $request, int $id)
    {
        $context = $this->taskManagementContext($request);

        $request->validate([
            'name' => 'required|string|max:100',
            'sort_order' => 'nullable|integer|min:0|max:1000',
            'sla_hours' => 'nullable|integer|min:1|max:8760',
            'active' => 'nullable|boolean',
        ]);

        $row = DB::table('task_management_priorities')->where('id', $id)->where('sub_institute_id', $context['sub_institute_id'])->first();
        if (!$row) {
            return $this->taskManagementError('Priority not found.', 404);
        }

        $newName = trim((string) $request->input('name'));

        if (strcasecmp($newName, $row->name) !== 0 && $this->priorityNameTaken($context['sub_institute_id'], $newName)) {
            return $this->taskManagementError('A priority with this name already exists.', 409);
        }

        DB::table('task_management_priorities')->where('id', $id)->update([
            'name' => $newName,
            'sort_order' => (int) $request->input('sort_order', $row->sort_order),
            'sla_hours' => $request->input('sla_hours', $row->sla_hours),
            'active' => $request->boolean('active', (bool) $row->active),
            'updated_at' => now(),
        ]);

        if ($newName !== $row->name) {
            DB::table('task')->where('sub_institute_id', $context['sub_institute_id'])->where('task_type', $row->name)->update(['task_type' => $newName]);
        }

        $this->logTaskActivity(
            $context['sub_institute_id'], $context['user_id'], 'priority_updated',
            'Priority "' . $row->name . '"' . ($newName !== $row->name ? ' renamed to "' . $newName . '"' : ''),
            'priority', $id, $newName
        );

        return $this->taskManagementResponse(null, 'Priority updated.');
    }

    public function destroyPriority(Request $request, int $id)
    {
        $context = $this->taskManagementContext($request);

        $row = DB::table('task_management_priorities')->where('id', $id)->where('sub_institute_id', $context['sub_institute_id'])->first();
        $updated = $row ? DB::table('task_management_priorities')->where('id', $id)->update(['active' => false, 'updated_at' => now()]) : 0;

        if ($updated) {
            $this->logTaskActivity($context['sub_institute_id'], $context['user_id'], 'priority_deactivated', 'Priority "' . $row->name . '"', 'priority', $id, $row->name);
        }

        return $updated
            ? $this->taskManagementResponse(null, 'Priority deactivated. Tasks already using it keep it for display.')
            : $this->taskManagementError('Priority not found.', 404);
    }

    /* ---------------- shared ---------------- */

    private function statusNameTaken(int $sid, string $name): bool
    {
        foreach (self::STATUS_CATEGORIES as $category) {
            if (strcasecmp($name, $category) === 0) {
                return true;
            }
        }

        return DB::table('task_management_statuses')->where('sub_institute_id', $sid)->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])->exists();
    }

    private function priorityNameTaken(int $sid, string $name): bool
    {
        foreach (self::SYSTEM_PRIORITIES as $system) {
            if (strcasecmp($name, $system) === 0) {
                return true;
            }
        }

        return DB::table('task_management_priorities')->where('sub_institute_id', $sid)->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])->exists();
    }
}
