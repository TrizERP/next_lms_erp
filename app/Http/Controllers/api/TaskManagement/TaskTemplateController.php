<?php

namespace App\Http\Controllers\api\TaskManagement;

use App\Http\Controllers\api\TaskManagement\Concerns\ResolvesTaskManagementContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ported from hp_erp's `App\Http\Controllers\Api\TaskManagement\TaskTemplateController`.
 *
 * Reusable task templates: a saved set of task fields the create form can
 * pre-fill from. The payload is stored as submitted - the create screen owns
 * which fields it uses.
 */
class TaskTemplateController extends Controller
{
    use ResolvesTaskManagementContext;

    public function index(Request $request)
    {
        $context = $this->taskManagementContext($request);

        $rows = DB::table('task_management_templates as tpl')
            ->leftJoin('tbluser as creator', 'creator.id', '=', 'tpl.created_by')
            ->where('tpl.sub_institute_id', $context['sub_institute_id'])
            ->orderBy('tpl.name')
            ->selectRaw("tpl.id, tpl.name, tpl.payload, tpl.created_at,
                TRIM(CONCAT_WS(' ', creator.first_name, creator.middle_name, creator.last_name)) as created_by_name")
            ->get()
            ->map(fn ($row) => [
                'id' => (string) $row->id,
                'name' => (string) $row->name,
                'payload' => json_decode((string) $row->payload, true) ?: [],
                'created_by' => $row->created_by_name ?: null,
                'created_at' => $row->created_at,
            ]);

        return $this->taskManagementResponse(['templates' => $rows->all()], 'Templates retrieved successfully.');
    }

    public function store(Request $request)
    {
        $context = $this->taskManagementContext($request);

        $request->validate([
            'name' => 'required|string|max:191',
            'payload' => 'required|array',
        ]);

        $id = DB::table('task_management_templates')->insertGetId([
            'sub_institute_id' => $context['sub_institute_id'],
            'name' => $request->input('name'),
            'payload' => json_encode($request->input('payload')),
            'created_by' => $context['user_id'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->taskManagementResponse(['id' => (string) $id], 'Template saved.', 201);
    }

    public function destroy(Request $request, int $id)
    {
        $context = $this->taskManagementContext($request);

        $deleted = DB::table('task_management_templates')
            ->where('id', $id)
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->delete();

        return $deleted
            ? $this->taskManagementResponse(null, 'Template deleted.')
            : $this->taskManagementError('Template not found.', 404);
    }
}
