<?php

namespace App\Http\Controllers\api\TaskManagement;

use App\Http\Controllers\api\TaskManagement\Concerns\ResolvesTaskManagementContext;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ported from hp_erp's `App\Http\Controllers\Api\TaskManagement\ReportController`.
 *
 * Management reports over the task table. Everything here is a single
 * aggregate query per report.
 */
class ReportController extends Controller
{
    use ResolvesTaskManagementContext;

    /** Per-assignee throughput: open vs completed, and overdue right now. */
    public function productivity(Request $request)
    {
        $context = $this->taskManagementContext($request);
        $today = Carbon::today()->toDateString();

        $rows = DB::table('task as t')
            ->join('tbluser as u', 'u.id', '=', 't.TASK_ALLOCATED_TO')
            ->where('t.sub_institute_id', $context['sub_institute_id'])
            ->where('t.SYEAR', $context['syear'])
            ->whereNull('t.deleted_at')
            ->groupBy('t.TASK_ALLOCATED_TO', 'u.first_name', 'u.middle_name', 'u.last_name')
            ->selectRaw("t.TASK_ALLOCATED_TO as user_id,
                TRIM(CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name)) as name,
                COUNT(*) as total,
                SUM(CASE WHEN UPPER(COALESCE(t.STATUS,'PENDING')) = 'COMPLETED' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN UPPER(COALESCE(t.STATUS,'PENDING')) <> 'COMPLETED' THEN 1 ELSE 0 END) as open,
                SUM(CASE WHEN t.TASK_DATE < ? AND UPPER(COALESCE(t.STATUS,'PENDING')) <> 'COMPLETED' THEN 1 ELSE 0 END) as overdue",
                [$today])
            ->orderByDesc('total')
            ->limit(200)
            ->get()
            ->map(fn ($row) => [
                'user_id' => (string) $row->user_id,
                'name' => (string) $row->name,
                'total' => (int) $row->total,
                'completed' => (int) $row->completed,
                'open' => (int) $row->open,
                'overdue' => (int) $row->overdue,
                'completion_rate' => $row->total > 0 ? round($row->completed / $row->total * 100, 1) : 0.0,
            ]);

        return $this->taskManagementResponse(['rows' => $rows->all()], 'Productivity report.');
    }

    /** Why work stalls: ON HOLD grouped by delay category, plus the overdue list. */
    public function delays(Request $request)
    {
        $context = $this->taskManagementContext($request);
        $sid = $context['sub_institute_id'];
        $today = Carbon::today()->toDateString();

        $byCategory = DB::table('task')
            ->where('sub_institute_id', $sid)
            ->where('SYEAR', $context['syear'])
            ->whereNull('deleted_at')
            ->whereRaw("UPPER(COALESCE(STATUS,'')) = 'ON HOLD'")
            ->selectRaw("COALESCE(delay_category, 'Uncategorised') as category, COUNT(*) as total")
            ->groupBy('category')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['category' => (string) $row->category, 'total' => (int) $row->total]);

        $overdue = DB::table('task as t')
            ->leftJoin('tbluser as assignee', 'assignee.id', '=', 't.TASK_ALLOCATED_TO')
            ->where('t.sub_institute_id', $sid)
            ->where('t.SYEAR', $context['syear'])
            ->whereNull('t.deleted_at')
            ->where('t.TASK_DATE', '<', $today)
            ->whereRaw("UPPER(COALESCE(t.STATUS,'PENDING')) <> 'COMPLETED'")
            ->orderBy('t.TASK_DATE')
            ->limit(100)
            ->selectRaw("t.ID, t.TASK_TITLE, t.TASK_DATE, t.STATUS, t.delay_category,
                TRIM(CONCAT_WS(' ', assignee.first_name, assignee.middle_name, assignee.last_name)) as assignee")
            ->get()
            ->map(fn ($row) => [
                'id' => (string) $row->ID,
                'title' => (string) $row->TASK_TITLE,
                'due_date' => $row->TASK_DATE,
                'status' => $row->STATUS,
                'delay_category' => $row->delay_category,
                'assignee' => $row->assignee ?: 'Unassigned',
                'days_overdue' => Carbon::parse($row->TASK_DATE)->diffInDays(Carbon::today()),
            ]);

        return $this->taskManagementResponse([
            'by_category' => $byCategory->all(),
            'overdue' => $overdue->all(),
        ], 'Delay report.');
    }
}
