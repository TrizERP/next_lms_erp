<?php

namespace App\Http\Controllers\api\TaskManagement;

use App\Http\Controllers\api\TaskManagement\Concerns\ResolvesTaskManagementContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ported from hp_erp's `App\Http\Controllers\Api\TaskManagement\ActivityController`.
 *
 * One task's history: its audit events and comments merged into a single
 * chronological feed for the task drawer's activity panel.
 */
class ActivityController extends Controller
{
    use ResolvesTaskManagementContext;

    public function index(Request $request, int $id)
    {
        $context = $this->taskManagementContext($request);
        $sid = $context['sub_institute_id'];

        $exists = DB::table('task')->where('ID', $id)->where('sub_institute_id', $sid)->exists();
        if (!$exists) {
            return $this->taskManagementError('Task not found.', 404);
        }

        $events = DB::table('task_management_audit_logs as a')
            ->leftJoin('tbluser as actor', 'actor.id', '=', 'a.actor_id')
            ->where('a.task_id', $id)
            ->where('a.sub_institute_id', $sid)
            ->selectRaw("a.id, a.event as type, a.before, a.created_at,
                TRIM(CONCAT_WS(' ', actor.first_name, actor.middle_name, actor.last_name)) as actor_name")
            ->get()
            ->map(function ($row) {
                $before = $row->before ? json_decode((string) $row->before, true) : null;

                return [
                    'id' => (string) $row->id,
                    'type' => (string) $row->type,
                    'actor' => $row->actor_name ?: null,
                    'content' => $before['description'] ?? null,
                    'created_at' => $row->created_at,
                ];
            });

        $comments = DB::table('task_management_comments as c')
            ->leftJoin('tbluser as author', 'author.id', '=', 'c.user_id')
            ->where('c.task_id', $id)
            ->selectRaw("c.id, 'comment' as type, c.content, c.created_at,
                TRIM(CONCAT_WS(' ', author.first_name, author.middle_name, author.last_name)) as actor_name")
            ->get()
            ->map(fn ($row) => [
                'id' => (string) $row->id,
                'type' => (string) $row->type,
                'actor' => $row->actor_name ?: null,
                'content' => $row->content,
                'created_at' => $row->created_at,
            ]);

        $feed = $events->concat($comments)->sortByDesc('created_at')->values();

        return $this->taskManagementResponse(['activity' => $feed->all()], 'Task activity retrieved successfully.');
    }
}
