<?php

namespace App\Http\Controllers\api\TaskManagement;

use App\Http\Controllers\api\TaskManagement\Concerns\ResolvesTaskManagementContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ported from hp_erp's `App\Http\Controllers\Api\TaskManagement\AuditLogController`.
 *
 * The queryable audit trail, fed by ResolvesTaskManagementContext::logTaskActivity
 * on every task write. Backs the Administration > Audit Logs screen and the
 * CSV export.
 */
class AuditLogController extends Controller
{
    use ResolvesTaskManagementContext;

    public function index(Request $request)
    {
        $context = $this->taskManagementContext($request);

        $request->validate([
            'task_id' => 'nullable|integer',
            'actor_id' => 'nullable|integer',
            'event' => 'nullable|string|max:50',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $logs = $this->query($request, $context)->paginate((int) $request->input('per_page', 50));

        return $this->taskManagementResponse([
            'logs' => collect($logs->items())->map(fn ($row) => $this->resource($row))->all(),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ], 'Audit logs retrieved successfully.');
    }

    /** Same filters as index, streamed as CSV so exports do not buffer in memory. */
    public function export(Request $request)
    {
        $context = $this->taskManagementContext($request);
        $rows = $this->query($request, $context)->limit(10000)->get();

        return new StreamedResponse(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'task_id', 'task_title', 'event', 'actor', 'before', 'created_at']);

            foreach ($rows as $row) {
                $line = $this->resource($row);
                fputcsv($out, array_map([$this, 'csvSafe'], [
                    $line['id'], $line['task_id'], $line['task_title'], $line['event'],
                    $line['actor'], json_encode($line['before']), $line['created_at'],
                ]));
            }

            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="task-audit-logs.csv"',
        ]);
    }

    /**
     * Neutralise spreadsheet formula injection: a cell whose value starts
     * with = + - or @ is executed by Excel/Sheets on open. Prefixing a
     * single quote makes it literal text without being displayed.
     */
    private function csvSafe($value): string
    {
        $value = (string) $value;

        return $value !== '' && str_contains('=+-@', $value[0]) ? "'" . $value : $value;
    }

    private function query(Request $request, array $context)
    {
        $query = DB::table('task_management_audit_logs as a')
            ->leftJoin('task as t', 't.ID', '=', 'a.task_id')
            ->leftJoin('tbluser as actor', 'actor.id', '=', 'a.actor_id')
            ->where('a.sub_institute_id', $context['sub_institute_id'])
            ->orderByDesc('a.id')
            ->selectRaw("a.id, a.task_id, a.event, a.actor_id, a.before, a.created_at,
                t.TASK_TITLE as task_title,
                TRIM(CONCAT_WS(' ', actor.first_name, actor.middle_name, actor.last_name)) as actor_name");

        if ($request->filled('task_id')) {
            $query->where('a.task_id', $request->integer('task_id'));
        }
        if ($request->filled('actor_id')) {
            $query->where('a.actor_id', $request->integer('actor_id'));
        }
        if ($request->filled('event')) {
            $query->where('a.event', $request->input('event'));
        }
        if ($request->filled('from')) {
            $query->whereDate('a.created_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('a.created_at', '<=', $request->input('to'));
        }

        return $query;
    }

    private function resource(object $row): array
    {
        $before = $row->before ? json_decode((string) $row->before, true) : null;

        return [
            'id' => (string) $row->id,
            'task_id' => (string) $row->task_id,
            // Configuration events carry task_id=0 (no task), so their
            // subject stands in for the title rather than "Task #0".
            'task_title' => $row->task_title ?? ($before['subject'] ?? null),
            'event' => (string) $row->event,
            'actor_id' => $row->actor_id ? (string) $row->actor_id : null,
            'actor' => $row->actor_name ?: null,
            'before' => $before,
            'created_at' => $row->created_at,
        ];
    }
}
