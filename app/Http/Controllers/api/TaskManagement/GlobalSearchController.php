<?php

namespace App\Http\Controllers\api\TaskManagement;

use App\Http\Controllers\api\TaskManagement\Concerns\ResolvesTaskManagementContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ported from hp_erp's `App\Http\Controllers\Api\TaskManagement\GlobalSearchController`.
 *
 * One search box over the whole task module: tasks, projects and people,
 * grouped so the client can render sectioned results.
 */
class GlobalSearchController extends Controller
{
    use ResolvesTaskManagementContext;

    public function index(Request $request)
    {
        $context = $this->taskManagementContext($request);

        $request->validate([
            'q' => 'required|string|min:2|max:191',
            'limit' => 'nullable|integer|min:1|max:25',
        ]);

        $term = '%' . $this->escapeTaskLike(trim((string) $request->input('q'))) . '%';
        $limit = (int) $request->input('limit', 10);
        $sid = $context['sub_institute_id'];

        $tasks = DB::table('task')
            ->where('sub_institute_id', $sid)
            ->where('SYEAR', $context['syear'])
            ->whereNull('deleted_at')
            ->where(fn ($q) => $q->where('TASK_TITLE', 'like', $term)->orWhere('TASK_DESCRIPTION', 'like', $term))
            ->orderByDesc('ID')
            ->limit($limit)
            ->get(['ID', 'TASK_TITLE', 'STATUS', 'TASK_DATE'])
            ->map(fn ($row) => [
                'id' => (string) $row->ID,
                'title' => (string) $row->TASK_TITLE,
                'status' => $row->STATUS,
                'due_date' => $row->TASK_DATE,
            ]);

        $projects = DB::table('task_management_projects')
            ->where('sub_institute_id', $sid)
            ->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('code', 'like', $term))
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'code', 'name', 'status'])
            ->map(fn ($row) => [
                'id' => (string) $row->id,
                'code' => (string) $row->code,
                'name' => (string) $row->name,
                'status' => $row->status,
            ]);

        $users = DB::table('tbluser')
            ->where('sub_institute_id', $sid)
            ->where('status', 1)
            ->whereRaw("TRIM(CONCAT_WS(' ', first_name, middle_name, last_name)) like ?", [$term])
            ->orderBy('first_name')
            ->limit($limit)
            ->selectRaw("id, TRIM(CONCAT_WS(' ', first_name, middle_name, last_name)) as name")
            ->get()
            ->map(fn ($row) => ['id' => (string) $row->id, 'name' => (string) $row->name]);

        return $this->taskManagementResponse([
            'tasks' => $tasks->all(),
            'projects' => $projects->all(),
            'users' => $users->all(),
        ], 'Search results.');
    }
}
