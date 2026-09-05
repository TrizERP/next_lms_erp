<?php

namespace App\Http\Controllers\G2gLms;

use App\Http\Controllers\Controller;
use App\Http\Controllers\G2gLms\Concerns\ResolvesLmsIdentity;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * CRUD for `s_competency_assessments` - the individual assessment record
 * (one row per person per campaign).
 *
 * Ported from hp_erp's `App\Http\Controllers\Api\Competency\AssessmentController`.
 * Two adaptations from the source:
 *
 *   - No `jobrole_id` resolution: the source's `s_competency_assessments`
 *     carries both `jobrole` (string) and `jobrole_id` (resolved via
 *     `ResolvesJobRoleId`, a trait that does not exist in this codebase).
 *     This package's approved column list for the table (see the migration)
 *     has `jobrole` only, matching the task's simpler schema - so only the
 *     free-text field is written.
 *   - No `s_competency_activity_log` write: that table is out of scope for
 *     this package. Create/delete are instead logged to `system_audit_logs`
 *     via `App\Models\AuditLog::record()`, module `g2g_lms_assessments`,
 *     matching how `GovernanceController` logs its own writes.
 */
class AssessmentController extends Controller
{
    use ResolvesLmsIdentity;

    private function audit(Request $request, $entityId, string $action, array $changes = []): void
    {
        $context = $this->lmsContext($request);

        AuditLog::record([
            'sub_institute_id' => $context['sub_institute_id'],
            'actor_id'         => $context['user_id'] ?: null,
            'module'           => 'g2g_lms_assessments',
            'action'           => 'assessment.' . $action,
            'entity_type'      => 'assessment',
            'entity_id'        => (string) $entityId,
            'new_values'       => $changes ?: null,
        ]);
    }

    /** GET /assessments */
    public function index(Request $request)
    {
        $sid = $this->lmsContext($request)['sub_institute_id'];

        $perPage = min(max((int) $request->input('per_page', 15), 1), 200);
        $page    = max((int) $request->input('page', 1), 1);

        $query = DB::table('s_competency_assessments')->where('sub_institute_id', $sid)->whereNull('deleted_at');

        if ($status = trim((string) $request->input('status', ''))) {
            $query->where('status', $status);
        }
        if ($search = trim((string) $request->input('search', ''))) {
            $query->where('title', 'like', "%{$search}%");
        }

        $total = (clone $query)->count();
        $rows  = $query->orderByDesc('id')->forPage($page, $perPage)->get();

        return response()->json([
            'status'     => 1,
            'message'    => 'Assessments fetched successfully',
            'data'       => $rows,
            'pagination' => [
                'page'      => $page,
                'per_page'  => $perPage,
                'total'     => $total,
                'last_page' => (int) max(ceil($total / $perPage), 1),
            ],
        ]);
    }

    /** POST /assessments */
    public function store(Request $request)
    {
        $context = $this->lmsContext($request);

        $validator = Validator::make($request->all(), [
            'title'         => 'required|string|max:191',
            'framework_id'  => 'nullable|integer',
            'cycle_id'      => 'nullable|integer',
            'user_id'       => 'nullable|integer',
            'assessor_id'   => 'nullable|integer',
            'department_id' => 'nullable|integer',
            'jobrole'       => 'nullable|string|max:191',
            'status'        => 'nullable|in:open,in_progress,completed,overdue',
            'due_date'      => 'nullable|date',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 0, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $id = DB::table('s_competency_assessments')->insertGetId([
            'sub_institute_id' => $context['sub_institute_id'],
            'title'            => $request->input('title'),
            'framework_id'     => $request->input('framework_id'),
            'cycle_id'         => $request->input('cycle_id'),
            'user_id'          => $request->input('user_id'),
            'assessor_id'      => $request->input('assessor_id'),
            'department_id'    => $request->input('department_id'),
            'jobrole'          => $request->input('jobrole'),
            'status'           => $request->input('status', 'open'),
            'due_date'         => $request->input('due_date'),
            'created_by'       => $context['user_id'] ?: null,
            'updated_by'       => $context['user_id'] ?: null,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $this->audit($request, $id, 'create', ['title' => $request->input('title')]);

        return response()->json(['status' => 1, 'message' => 'Assessment created successfully', 'data' => ['id' => $id]], 201);
    }

    /** DELETE /assessments/{id} */
    public function destroy(Request $request, $id)
    {
        $context = $this->lmsContext($request);

        $existing = DB::table('s_competency_assessments')
            ->where('id', $id)->where('sub_institute_id', $context['sub_institute_id'])->whereNull('deleted_at')->first();

        if (!$existing) {
            return response()->json(['status' => 0, 'message' => 'Assessment not found'], 404);
        }

        DB::table('s_competency_assessments')->where('id', $id)->update([
            'deleted_at' => now(),
            'deleted_by' => $context['user_id'] ?: null,
        ]);

        $this->audit($request, $id, 'delete', ['title' => $existing->title]);

        return response()->json(['status' => 1, 'message' => 'Assessment deleted successfully']);
    }
}
