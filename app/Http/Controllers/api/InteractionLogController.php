<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\InteractionLog;
use Illuminate\Http\Request;

/**
 * The Interactions module's base CRUD: log a touchpoint, list them, close one out.
 *
 * This is genuinely new functionality — no unified staff/parent/student touchpoint log
 * existed anywhere in this estate before it. It follows the same request shape
 * CurriculumPlanningApiController uses: `sub_institute_id` and the acting staff id are
 * validated request parameters, sent by the caller's own session context, the same way
 * this controller family has always done it — the newer MCP layer's server-derived
 * institute id is a separate, AI-specific convention and does not apply to this REST
 * surface.
 */
class InteractionLogController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'sub_institute_id' => 'required|integer',
            'related_type' => 'nullable|string|in:student,parent,staff,visitor',
            'related_id' => 'nullable|integer',
            'status' => 'nullable|string|in:open,closed',
            'limit' => 'nullable|integer|min:1|max:200',
        ]);

        $rows = InteractionLog::query()
            ->where('sub_institute_id', $filters['sub_institute_id'])
            ->when(! empty($filters['related_type']), fn ($q) => $q->where('related_type', $filters['related_type']))
            ->when(! empty($filters['related_id']), fn ($q) => $q->where('related_id', $filters['related_id']))
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->orderByDesc('occurred_at')
            ->limit($filters['limit'] ?? 100)
            ->get();

        return response()->json(['status' => true, 'data' => $rows]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'sub_institute_id' => 'required|integer',
            'staff_id' => 'required|integer',
            'related_type' => 'required|string|in:student,parent,staff,visitor',
            'related_id' => 'required|integer',
            'interaction_type' => 'required|string|in:call,meeting,note,follow_up',
            'subject' => 'required|string|max:191',
            'notes' => 'nullable|string',
            'occurred_at' => 'required|date',
            'follow_up_date' => 'nullable|date',
        ]);

        $data['status'] = 'open';
        $data['created_by'] = $data['staff_id'];

        $log = InteractionLog::create($data);

        return response()->json(['status' => true, 'message' => 'Interaction logged.', 'data' => $log], 201);
    }

    public function update(Request $request, int $id)
    {
        $log = InteractionLog::where('sub_institute_id', $request->input('sub_institute_id'))->find($id);

        if ($log === null) {
            return response()->json(['status' => false, 'message' => 'Interaction not found.'], 404);
        }

        $data = $request->validate([
            'status' => 'nullable|string|in:open,closed',
            'notes' => 'nullable|string',
            'follow_up_date' => 'nullable|date',
            'updated_by' => 'nullable|integer',
        ]);

        $log->update($data);

        return response()->json(['status' => true, 'message' => 'Interaction updated.', 'data' => $log]);
    }
}
