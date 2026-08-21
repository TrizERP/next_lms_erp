<?php

namespace App\Http\Controllers\api\TaskManagement;

use App\Http\Controllers\api\TaskManagement\Concerns\ResolvesTaskManagementContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ported from hp_erp's `App\Http\Controllers\Api\TaskManagement\DeadlineExtensionController`.
 *
 * Deadline extension requests: the executor asks for a new due date, the
 * observer approves or rejects; approval moves task.TASK_DATE.
 */
class DeadlineExtensionController extends Controller
{
    use ResolvesTaskManagementContext;

    public function index(Request $request)
    {
        $context = $this->taskManagementContext($request);

        $query = DB::table('task_deadline_extensions as e')
            ->join('task as t', 't.ID', '=', 'e.task_id')
            ->leftJoin('tbluser as requester', 'requester.id', '=', 'e.requested_by')
            ->leftJoin('tbluser as decider', 'decider.id', '=', 'e.decided_by')
            ->where('e.sub_institute_id', $context['sub_institute_id'])
            ->orderByDesc('e.id')
            ->selectRaw("e.id, e.task_id, e.requested_date, e.reason, e.status, e.decided_on, e.decision_remarks, e.created_at,
                t.TASK_TITLE as task_title, t.TASK_DATE as current_due_date,
                TRIM(CONCAT_WS(' ', requester.first_name, requester.middle_name, requester.last_name)) as requested_by_name,
                TRIM(CONCAT_WS(' ', decider.first_name, decider.middle_name, decider.last_name)) as decided_by_name");

        if ($request->filled('task_id')) {
            $query->where('e.task_id', $request->integer('task_id'));
        }
        if ($request->filled('status')) {
            $query->where('e.status', $request->input('status'));
        }

        $rows = $query->limit(200)->get()->map(fn ($row) => [
            'id' => (string) $row->id,
            'task_id' => (string) $row->task_id,
            'task_title' => (string) $row->task_title,
            'current_due_date' => $row->current_due_date,
            'requested_date' => $row->requested_date,
            'reason' => $row->reason,
            'status' => (string) $row->status,
            'requested_by' => $row->requested_by_name ?: null,
            'decided_by' => $row->decided_by_name ?: null,
            'decided_on' => $row->decided_on,
            'decision_remarks' => $row->decision_remarks,
            'created_at' => $row->created_at,
        ]);

        return $this->taskManagementResponse(['extensions' => $rows->all()], 'Deadline extensions retrieved successfully.');
    }

    /** The executor asks for more time. */
    public function store(Request $request)
    {
        $context = $this->taskManagementContext($request);

        $request->validate([
            'task_id' => 'required|integer',
            'requested_date' => 'required|date|after:today',
            'reason' => 'required|string|max:5000',
        ]);

        $task = DB::table('task')
            ->where('ID', $request->integer('task_id'))
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->whereNull('deleted_at')
            ->first();

        if (!$task) {
            return $this->taskManagementError('Task not found.', 404);
        }

        $pending = DB::table('task_deadline_extensions')->where('task_id', $task->ID)->where('status', 'pending')->exists();
        if ($pending) {
            return $this->taskManagementError('A pending extension request already exists for this task.', 409);
        }

        $id = DB::table('task_deadline_extensions')->insertGetId([
            'sub_institute_id' => $context['sub_institute_id'],
            'task_id' => $task->ID,
            'requested_by' => $context['user_id'],
            'requested_date' => $request->input('requested_date'),
            'reason' => $request->input('reason'),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        NotificationController::notify(
            $context['sub_institute_id'],
            (int) ($task->TASK_ALLOCATED ?: $task->CREATED_BY),
            'deadline_extension_requested',
            'Deadline extension requested: ' . $task->TASK_TITLE,
            $request->input('reason'),
            (int) $task->ID
        );

        return $this->taskManagementResponse(['id' => (string) $id], 'Extension requested.', 201);
    }

    /** The observer decides. Approval moves the task's due date. */
    public function decide(Request $request, int $id)
    {
        $context = $this->taskManagementContext($request);

        $request->validate([
            'decision' => 'required|in:approve,reject',
            'remarks' => 'nullable|string|max:5000',
        ]);

        $extension = DB::table('task_deadline_extensions')->where('id', $id)->where('sub_institute_id', $context['sub_institute_id'])->first();
        if (!$extension) {
            return $this->taskManagementError('Extension request not found.', 404);
        }
        if ($extension->status !== 'pending') {
            return $this->taskManagementError('This request has already been decided.', 409);
        }

        $approving = $request->input('decision') === 'approve';

        DB::table('task_deadline_extensions')->where('id', $id)->update([
            'status' => $approving ? 'approved' : 'rejected',
            'decided_by' => $context['user_id'],
            'decided_on' => now(),
            'decision_remarks' => $request->input('remarks'),
            'updated_at' => now(),
        ]);

        if ($approving) {
            $task = DB::table('task')->where('ID', $extension->task_id)->first();

            DB::table('task')->where('ID', $extension->task_id)->update([
                'TASK_DATE' => $extension->requested_date,
                'updated_by' => $context['user_id'],
                'updated_at' => now(),
            ]);

            if ($task) {
                $this->logTaskActivity($context['sub_institute_id'], $context['user_id'], 'deadline_extended', 'Deadline extended to ' . $extension->requested_date, null, null, null, null, (int) $extension->task_id);
            }
        }

        NotificationController::notify(
            $context['sub_institute_id'],
            (int) $extension->requested_by,
            $approving ? 'deadline_extension_approved' : 'deadline_extension_rejected',
            'Deadline extension ' . ($approving ? 'approved' : 'rejected'),
            $request->input('remarks'),
            (int) $extension->task_id
        );

        return $this->taskManagementResponse(null, $approving ? 'Extension approved and due date updated.' : 'Extension rejected.');
    }
}
