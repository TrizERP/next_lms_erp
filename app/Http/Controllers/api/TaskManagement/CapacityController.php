<?php

namespace App\Http\Controllers\api\TaskManagement;

use App\Http\Controllers\api\TaskManagement\Concerns\ResolvesTaskManagementContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ported from hp_erp's `App\Http\Controllers\Api\TaskManagement\CapacityController`.
 *
 * Pre-assignment capacity check: how loaded is each proposed assignee before
 * the task is created, so over-allocation is a warning at assignment time
 * rather than a discovery at the deadline.
 */
class CapacityController extends Controller
{
    use ResolvesTaskManagementContext;

    private const DEFAULT_THRESHOLD = 10;

    public function check(Request $request)
    {
        $context = $this->taskManagementContext($request);

        $request->validate([
            'assignee_ids' => 'required|array|min:1|max:100',
            'assignee_ids.*' => 'integer',
            'threshold' => 'nullable|integer|min:1|max:500',
        ]);

        $threshold = (int) $request->input('threshold', self::DEFAULT_THRESHOLD);
        $ids = array_map('intval', $request->input('assignee_ids'));

        $counts = DB::table('task')
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->where('SYEAR', $context['syear'])
            ->whereNull('deleted_at')
            ->whereIn('TASK_ALLOCATED_TO', $ids)
            ->whereRaw("UPPER(COALESCE(STATUS, 'PENDING')) <> 'COMPLETED'")
            ->selectRaw('TASK_ALLOCATED_TO as assignee_id, COUNT(*) as open_tasks')
            ->groupBy('TASK_ALLOCATED_TO')
            ->pluck('open_tasks', 'assignee_id');

        $names = DB::table('tbluser')
            ->whereIn('id', $ids)
            ->selectRaw("id, TRIM(CONCAT_WS(' ', first_name, middle_name, last_name)) as name")
            ->pluck('name', 'id');

        $result = array_map(function (int $id) use ($counts, $names, $threshold) {
            $open = (int) ($counts[$id] ?? 0);

            return [
                'assignee_id' => (string) $id,
                'name' => (string) ($names[$id] ?? ''),
                'open_tasks' => $open,
                'threshold' => $threshold,
                'over_capacity' => $open >= $threshold,
            ];
        }, $ids);

        return $this->taskManagementResponse(['assignees' => $result], 'Capacity checked.');
    }
}
