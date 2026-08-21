<?php

namespace App\Http\Controllers\api\TalentManagement\Administration;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ported from G2G's `App\Http\Controllers\Api\Talent\AdminWorkflowController`.
 *
 * Backs the Talent Management "Administration & Governance" center's
 * Workflows tab. Source queried `talent_workflows` / `talent_workflow_stages`
 * / `talent_workflow_approvers` directly via `DB::table` (no Eloquent model
 * existed there); the query logic, field names and response shapes are kept
 * unchanged here even though `App\Models\TalentManagement\TalentWorkflow`
 * (+ `TalentWorkflowStage` / `TalentWorkflowApprover`) now exist for the rest
 * of the module to build on.
 *
 * Auth/session pattern mirrors `OnboardingApiController` exactly: tenant
 * identity comes from the JWT-hydrated session (`api.session` middleware),
 * never from request input — `session()->get('sub_institute_id')` /
 * `session()->get('syear')` — replacing source's per-request Sanctum token
 * resolution (`ResolvesTalentContext::talentContext()`).
 *
 * Only `index` (`GET /talent/admin/workflows`) is actually registered in
 * G2G's `routes/api.php` (line 1279). `show` (`GET
 * /talent/admin/workflows/{id}`) exists in the source controller and is
 * called by the frontend's `AdminService.getWorkflowById`, but it is NOT
 * wired into any route there — ported here unrouted too, exactly as found,
 * per this migration's "don't add functionality G2G doesn't have" rule. See
 * this feature's report for the one route line that should be registered.
 */
class AdminWorkflowController extends Controller
{
    /**
     * GET /talent/admin/workflows
     * Returns a paginated list of workflows for the administration center.
     */
    public function index(Request $request)
    {
        $sid = (int) session()->get('sub_institute_id');

        $module = $this->activeAdminFilter($request->input('module'));
        $search = $this->activeAdminFilter($request->input('search'));
        $paging = $this->adminPaging($request, 10);

        $query = DB::table('talent_workflows as w')
            ->where('w.sub_institute_id', $sid)
            ->whereNull('w.deleted_at')
            ->when($module, fn ($q) => $q->where('w.module', $module))
            ->when($search, fn ($q) => $q->where(function ($sq) use ($search) {
                $sq->where('w.name', 'like', "%{$search}%")
                   ->orWhere('w.description', 'like', "%{$search}%");
            }));

        $total = $query->count();
        $workflows = $query->orderByDesc('w.updated_at')
            ->offset(($paging['page'] - 1) * $paging['per_page'])
            ->limit($paging['per_page'])
            ->get([
                'w.id', 'w.name', 'w.module', 'w.status', 'w.version',
                'w.description', 'w.updated_at'
            ]);

        // Eager load the creators/updaters using the directory helper
        $userIds = DB::table('talent_workflows')->whereIn('id', $workflows->pluck('id'))->pluck('updated_by')->toArray();
        $directory = $this->adminEmployeeDirectory($sid, $userIds);

        $result = $workflows->map(function ($w) use ($directory) {
            $updatedBy = DB::table('talent_workflows')->where('id', $w->id)->value('updated_by');
            return [
                'id' => 'wf-' . $w->id,
                'name' => $w->name,
                'module' => $w->module,
                'status' => $w->status,
                'version' => $w->version,
                'description' => $w->description,
                'lastUpdated' => $this->adminDateLabel($w->updated_at),
                'updatedBy' => $updatedBy ? ($directory[$updatedBy]['name'] ?? 'System') : 'System'
            ];
        });

        return $this->success('Success', $result->values()->all(), [
            'pagination' => [
                'total' => $total,
                'per_page' => $paging['per_page'],
                'current_page' => $paging['page'],
                'last_page' => max(1, ceil($total / $paging['per_page']))
            ]
        ]);
    }

    /**
     * GET /talent/admin/workflows/{id}
     * Returns the full workflow details including stages and approvers.
     *
     * NOT routed in G2G — ported unrouted, matching source exactly. See the
     * class docblock.
     */
    public function show(Request $request, $id)
    {
        $sid = (int) session()->get('sub_institute_id');
        $numericId = (int) str_replace('wf-', '', $id);

        $workflow = DB::table('talent_workflows')
            ->where('id', $numericId)
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at')
            ->first();

        if (!$workflow) {
            return $this->failure('Workflow not found', 404);
        }

        $stages = DB::table('talent_workflow_stages')
            ->where('workflow_id', $numericId)
            ->orderBy('step')
            ->get(['step', 'label']);

        $approvers = DB::table('talent_workflow_approvers')
            ->where('workflow_id', $numericId)
            ->orderBy('sort_order')
            ->get(['id', 'role', 'title', 'approval_type', 'escalation']);

        $userIds = array_filter([$workflow->created_by, $workflow->updated_by]);
        $directory = $this->adminEmployeeDirectory($sid, $userIds);

        return $this->success('Success', [
            'id' => 'wf-' . $workflow->id,
            'name' => $workflow->name,
            'module' => $workflow->module,
            'status' => $workflow->status,
            'version' => $workflow->version,
            'description' => $workflow->description,
            'createdBy' => $workflow->created_by ? ($directory[$workflow->created_by]['name'] ?? 'System') : 'System',
            'lastUpdated' => $this->adminDateLabel($workflow->updated_at),
            'updatedBy' => $workflow->updated_by ? ($directory[$workflow->updated_by]['name'] ?? 'System') : 'System',
            'stages' => $stages->map(fn ($s) => [
                'step' => $s->step,
                'label' => $s->label
            ])->values()->all(),
            'approvers' => $approvers->map(fn ($a) => [
                'id' => 'a' . $a->id,
                'role' => $a->role,
                'title' => $a->title,
                'initials' => $this->adminInitialsOf($a->role),
                'approvalType' => $a->approval_type,
                'escalation' => $a->escalation
            ])->values()->all()
        ]);
    }

    // ------------------------------------------------------------------
    // Helpers - ported from G2G's ResolvesTalentContext trait, scoped to
    // this controller (session-based context resolution replaces the
    // source's per-request token identity resolution).
    // ------------------------------------------------------------------

    /** Treat 'all', '0' and empty string as "no filter". */
    private function activeAdminFilter($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            $value = array_values(array_filter(
                $value,
                fn ($item) => $item !== null && $item !== '' && $item !== '0' && $item !== 'all'
            ));

            return empty($value) ? null : implode(',', $value);
        }

        $value = trim((string) $value);

        return ($value === '' || $value === '0' || strtolower($value) === 'all') ? null : $value;
    }

    /** page / per_page, clamped so a hostile per_page cannot dump the table. */
    private function adminPaging(Request $request, int $defaultPerPage = 25): array
    {
        $page = (int) ($request->input('page') ?: 1);
        $perPage = (int) ($request->input('per_page') ?: $defaultPerPage);

        return [
            'page'     => max(1, $page),
            'per_page' => min(200, max(5, $perPage)),
        ];
    }

    /**
     * Employee display bundle, resolved from the single user master (tbluser)
     * plus the department master. Same shape as source's talentEmployeeDirectory.
     */
    private function adminEmployeeDirectory(int $subInstituteId, array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter($userIds)));

        if (empty($userIds)) {
            return [];
        }

        $users = DB::table('tbluser')
            ->where('sub_institute_id', $subInstituteId)
            ->whereIn('id', $userIds)
            ->get(['id', 'first_name', 'last_name', 'user_name', 'employee_no', 'department_id', 'joined_date', 'image']);

        $departmentIds = $users->pluck('department_id')->filter()->unique()->values()->all();

        $departments = empty($departmentIds)
            ? collect()
            : DB::table('hrms_departments')->whereIn('id', $departmentIds)->pluck('department', 'id');

        $designations = DB::table('org_designation')
            ->where('sub_institute_id', $subInstituteId)
            ->whereIn('user_id', $userIds)
            ->pluck('designation', 'user_id');

        $directory = [];

        foreach ($users as $user) {
            $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
            $name = $name !== '' ? $name : ($user->user_name ?? 'Unknown');

            $directory[(int) $user->id] = [
                'id'            => (int) $user->id,
                'name'          => $name,
                'employee_no'   => $user->employee_no,
                'initials'      => $this->adminInitialsOf($name),
                'department_id' => $user->department_id ? (int) $user->department_id : null,
                'department'    => $user->department_id ? ($departments[$user->department_id] ?? null) : null,
                'designation'   => $designations[$user->id] ?? null,
                'joined_date'   => $user->joined_date,
                'image'         => $user->image,
            ];
        }

        return $directory;
    }

    private function adminInitialsOf(?string $name): string
    {
        if (!$name) {
            return '--';
        }

        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $first = mb_substr($parts[0] ?? '', 0, 1);
        $last = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';

        return mb_strtoupper($first . $last) ?: '--';
    }

    /** "15 May 2025" - the date format the talent screens render. */
    private function adminDateLabel($date): ?string
    {
        if (!$date) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($date)->format('d M Y');
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ------------------------------------------------------------------
    // Response envelope helpers - copied from OnboardingApiController.
    // ------------------------------------------------------------------

    private function success(string $message, $data, array $extra = [])
    {
        return response()->json(array_merge([
            'status' => 1,
            'status_code' => 1,
            'message' => $message,
            'data' => $data,
        ], $extra));
    }

    private function failure(string $message, int $code = 422, $errors = null)
    {
        return response()->json([
            'status' => 0,
            'status_code' => 0,
            'message' => $message,
            'errors' => $errors,
            'data' => [],
        ], $code);
    }
}
