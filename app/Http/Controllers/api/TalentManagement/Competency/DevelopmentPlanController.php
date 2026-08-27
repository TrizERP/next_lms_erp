<?php

namespace App\Http\Controllers\api\TalentManagement\Competency;

use App\Http\Controllers\api\Concerns\RequiresTalentAdmin;
use App\Http\Controllers\api\TalentManagement\Competency\Concerns\ResolvesCompetencyContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Ported from G2G's `App\Http\Controllers\Api\Competency\DevelopmentPlanController`.
 *
 * Development plans (s_competency_development_plans) for the Development &
 * Career Path Workspace. Logic, field names and response shapes are unchanged
 * from the source; only tenant/actor context resolution was adapted to the
 * hydrated session, per `ResolvesCompetencyContext`.
 *
 * `metrics()`'s "Learning Assigned" card reads `lms_assignments` rows tagged
 * `source = 'competency'`, exactly as the source does. That table did not
 * exist in this schema before this port; it is created (with the
 * `development_plan_id` / `competency_id` / `assigned_by_id` / `source`
 * columns this module needs) by
 * `2026_08_21_090600_add_competency_link_to_lms_assignments_table.php`. No
 * other method in this controller writes to it - the source never ported a
 * writer for it either (CompetencyLearningAssignmentController, which does,
 * is explicitly out of this port's scope).
 */
class DevelopmentPlanController extends Controller
{
    use ResolvesCompetencyContext;
    use RequiresTalentAdmin;

    private const TABLE = 's_competency_development_plans';

    /** Raw DB status -> the label the workspace renders. */
    private const STATUS_LABELS = [
        'active'    => 'In Progress',
        'completed' => 'Completed',
        'overdue'   => 'Overdue',
        'on_hold'   => 'On Hold',
    ];

    private const SORTABLE = [
        'title'      => 'title',
        'status'     => 'status',
        'progress'   => 'progress',
        'due_date'   => 'due_date',
        'created_at' => 'created_at',
    ];

    /** Columns worth reporting in the Audit Center's Change Summary, with the label that card shows. */
    private const CHANGE_LABELS = [
        'title'           => 'Plan Title',
        'objective'       => 'Objective',
        'focus_areas'     => 'Focus Areas',
        'user_id'         => 'Employee',
        'competency_id'   => 'Linked Competency',
        'framework_id'    => 'Framework',
        'career_path_id'  => 'Career Path',
        'department_id'   => 'Department',
        'jobrole'         => 'Job Role',
        'status'          => 'Status',
        'progress'        => 'Progress',
        'start_date'      => 'Start Date',
        'due_date'        => 'Due Date',
        'approver_id'     => 'Approver',
        'approval_status' => 'Approval Status',
    ];

    /* ================================================================== *
     * List
     * ================================================================== */

    /**
     * GET /api/competency/development-plans
     *
     * Filters: status, search, department_id, approver_id (plan owner), user_id,
     *          jobrole, career_path_id
     * Sort:    sort=title|status|progress|due_date|created_at, direction=asc|desc
     */
    public function index(Request $request)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        $perPage = min(max((int) $request->input('per_page', 15), 1), 200);
        $page = max((int) $request->input('page', 1), 1);

        $query = DB::table(self::TABLE)
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at');

        if ($status = $this->activeFilter($request->input('status'))) {
            $query->where('status', $status);
        }
        if ($search = $this->activeFilter($request->input('search'))) {
            $query->where('title', 'like', "%{$search}%");
        }
        if ($departmentId = $this->activeFilter($request->input('department_id'))) {
            $query->where('department_id', $departmentId);
        }
        if ($approverId = $this->activeFilter($request->input('approver_id'))) {
            $query->where('approver_id', $approverId);
        }
        if ($userId = $this->activeFilter($request->input('user_id_filter'))) {
            $query->where('user_id', $userId);
        }
        if ($jobrole = $this->activeFilter($request->input('jobrole'))) {
            $query->where('jobrole', $jobrole);
        }
        if ($pathId = $this->activeFilter($request->input('career_path_id'))) {
            $query->where('career_path_id', $pathId);
        }
        if ($competencyId = $this->activeFilter($request->input('competency_id'))) {
            $query->where('competency_id', $competencyId);
        }
        if ($this->activeFilter($request->input('pending_approval'))) {
            $query->where('approval_status', 'pending_approval');
        }

        $total = (clone $query)->count();

        $sortKey = (string) $request->input('sort', 'created_at');
        $column = self::SORTABLE[$sortKey] ?? 'id';
        $direction = strtolower((string) $request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $rows = $query->orderBy($column, $direction)->orderByDesc('id')
            ->forPage($page, $perPage)->get();

        return response()->json([
            'status'     => 1,
            'message'    => 'Development plans fetched successfully',
            'data'       => $this->decorateRows($rows, $sid),
            'pagination' => [
                'page'      => $page,
                'per_page'  => $perPage,
                'total'     => $total,
                'last_page' => (int) max(ceil($total / $perPage), 1),
            ],
        ]);
    }

    /**
     * GET /api/competency/development-plans/metrics
     *
     * The workspace's five KPI cards. "vs last month" deltas compare the count
     * created (or, for completed, finished) in the current calendar month with
     * the previous one.
     */
    public function metrics(Request $request)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        $base = fn () => DB::table(self::TABLE)
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at');

        $monthStart = now()->startOfMonth();
        $prevStart = now()->subMonthNoOverflow()->startOfMonth();

        $createdDelta = function ($table, $column, $extra = null) use ($sid, $monthStart, $prevStart) {
            $scope = function () use ($table, $sid, $extra) {
                $q = DB::table($table)->where('sub_institute_id', $sid)->whereNull('deleted_at');
                if ($extra) {
                    $extra($q);
                }
                return $q;
            };
            $current = $scope()->where($column, '>=', $monthStart)->count();
            $previous = $scope()->where($column, '>=', $prevStart)->where($column, '<', $monthStart)->count();
            return $current - $previous;
        };

        // 1. Active development plans
        $active = (clone $base())->where('status', 'active')->count();

        // 2. In progress = started but not finished; the ring is their mean progress.
        $inProgressQuery = (clone $base())->where('status', 'active')
            ->where('progress', '>', 0)->where('progress', '<', 100);
        $inProgress = (clone $inProgressQuery)->count();
        $avgProgress = (float) ((clone $inProgressQuery)->avg('progress') ?? 0);

        // 3. Completed
        $completed = (clone $base())->where('status', 'completed')->count();
        $completedCurrent = (clone $base())->where('status', 'completed')
            ->whereRaw('COALESCE(completed_at, updated_at) >= ?', [$monthStart])->count();
        $completedPrevious = (clone $base())->where('status', 'completed')
            ->whereRaw('COALESCE(completed_at, updated_at) >= ?', [$prevStart])
            ->whereRaw('COALESCE(completed_at, updated_at) < ?', [$monthStart])->count();

        // 4. Career paths
        $careerPaths = DB::table('s_competency_career_paths')
            ->where('sub_institute_id', $sid)->whereNull('deleted_at')
            ->where('status', '!=', 'archived')->count();

        // 5. Learning assigned by this module (source='competency' keeps the
        //    LMS module's own assignments out of the competency count).
        $learningQuery = fn () => DB::table('lms_assignments')
            ->where('sub_institute_id', $sid)->whereNull('deleted_at')
            ->where('source', 'competency');
        $learning = $learningQuery()->count();
        $learningCurrent = $learningQuery()->where('created_at', '>=', $monthStart)->count();
        $learningPrevious = $learningQuery()
            ->where('created_at', '>=', $prevStart)->where('created_at', '<', $monthStart)->count();

        return response()->json([
            'status'  => 1,
            'message' => 'Development plan metrics fetched successfully',
            'data'    => [
                'active_plans'          => $active,
                'active_plans_delta'    => $createdDelta(self::TABLE, 'created_at', fn ($q) => $q->where('status', 'active')),
                'in_progress'           => $inProgress,
                'in_progress_percent'   => (int) round($avgProgress),
                'completed'             => $completed,
                'completed_delta'       => $completedCurrent - $completedPrevious,
                'career_paths'          => $careerPaths,
                'career_paths_delta'    => $createdDelta('s_competency_career_paths', 'created_at'),
                'learning_assigned'     => $learning,
                'learning_delta'        => $learningCurrent - $learningPrevious,
                'total_plans'           => (clone $base())->count(),
                'overdue'               => (clone $base())->where('status', 'overdue')->count(),
                'on_hold'               => (clone $base())->where('status', 'on_hold')->count(),
                'pending_approval'      => (clone $base())->where('approval_status', 'pending_approval')->count(),
            ],
        ]);
    }

    /**
     * GET /api/competency/development-plans/owners
     *
     * The "Plan Owner: All" dropdown - only users who actually own a plan in
     * this tenant, so the filter can never produce an empty result set.
     */
    public function owners(Request $request)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        $ids = DB::table(self::TABLE)
            ->where('sub_institute_id', $sid)->whereNull('deleted_at')
            ->whereNotNull('approver_id')->where('approver_id', '!=', 0)
            ->distinct()->pluck('approver_id')->all();

        $users = $this->userMap($ids);

        $data = [];
        foreach ($ids as $id) {
            $user = $users[$id] ?? null;
            $data[] = [
                'value' => (string) $id,
                'label' => $user['name'] ?? ('User ' . $id),
            ];
        }
        usort($data, fn ($a, $b) => strcasecmp($a['label'], $b['label']));

        return response()->json([
            'status'  => 1,
            'message' => 'Plan owners fetched successfully',
            'data'    => $data,
        ]);
    }

    /**
     * GET /api/competency/employee-options
     *
     * Employee picker for "Create Development Plan" and "Assign Learning".
     */
    public function employees(Request $request)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        // No `whereNull('deleted_at')` here: unlike the G2G source's `tbluser`,
        // this target's `tbluser` has no `deleted_at` column (it tracks active
        // employees via `status` instead) - matching how every other tbluser
        // lookup in this ported namespace already queries it.
        $query = DB::table('tbluser')
            ->where('sub_institute_id', $sid);

        if ($search = $this->activeFilter($request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_no', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('first_name')->limit(500)
            ->get(['id', 'first_name', 'last_name', 'employee_no', 'jobtitle_id', 'department_id', 'allocated_standards']);

        $roleIds = [];
        foreach ($users as $u) {
            $roleId = $u->jobtitle_id ?: $this->firstNumeric($u->allocated_standards);
            if ($roleId) {
                $roleIds[$u->id] = $roleId;
            }
        }

        $roles = $roleIds
            ? DB::table('s_user_jobrole')->whereIn('id', array_values($roleIds))->whereNull('deleted_at')
                ->pluck('jobrole', 'id')->all()
            : [];

        $data = $users->map(function ($u) use ($roleIds, $roles) {
            $first = trim((string) $u->first_name);
            $last = trim((string) $u->last_name);
            $name = trim($first . ' ' . $last) ?: ('User ' . $u->id);
            $roleId = $roleIds[$u->id] ?? null;

            return [
                'id'            => (int) $u->id,
                'name'          => $name,
                'initials'      => strtoupper(substr($first ?: $name, 0, 1) . substr($last, 0, 1)) ?: strtoupper(substr($name, 0, 2)),
                'employee_no'   => $u->employee_no,
                'jobrole'       => $roleId ? ($roles[$roleId] ?? null) : null,
                'jobrole_id'    => $roleId ? (int) $roleId : null,
                'department_id' => $u->department_id ? (int) $u->department_id : null,
            ];
        })->all();

        return response()->json([
            'status'  => 1,
            'message' => 'Employees fetched successfully',
            'data'    => $data,
        ]);
    }

    /* ================================================================== *
     * Single plan
     * ================================================================== */

    /** GET /api/competency/development-plans/{id} */
    public function show(Request $request, $id)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        $plan = $this->findPlan($id, $sid);
        if (!$plan) {
            return response()->json(['status' => 0, 'message' => 'Development plan not found'], 404);
        }

        $data = $this->decorateRows(collect([$plan]), $sid)[0];

        $next = DB::table('s_competency_plan_actions')
            ->where('plan_id', $plan->id)->whereNull('deleted_at')
            ->whereIn('status', ['pending', 'in_progress'])
            ->orderByRaw('due_date IS NULL, due_date asc')
            ->orderBy('sequence')->first();

        $data['objective'] = $plan->objective;
        $data['focus_areas'] = $this->splitList($plan->focus_areas);
        $data['career_path'] = $plan->career_path_id
            ? DB::table('s_competency_career_paths')
                ->where('id', $plan->career_path_id)->whereNull('deleted_at')
                ->select('id', 'name', 'status')->first()
            : null;
        $data['next_milestone'] = $next ? [
            'id'       => (int) $next->id,
            'title'    => $next->title,
            'status'   => $next->status,
            'due_date' => $this->humanDate($next->due_date),
        ] : null;
        $data['action_counts'] = [
            'total'     => DB::table('s_competency_plan_actions')->where('plan_id', $plan->id)->whereNull('deleted_at')->count(),
            'completed' => DB::table('s_competency_plan_actions')->where('plan_id', $plan->id)->whereNull('deleted_at')->where('status', 'completed')->count(),
        ];

        return response()->json([
            'status'  => 1,
            'message' => 'Development plan fetched successfully',
            'data'    => $data,
        ]);
    }

    public function store(Request $request)
    {
        if ($response = $this->assertIsAdmin()) { return $response; }

        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $validator = Validator::make($request->all(), $this->planRules(true));

        if ($validator->fails()) {
            return response()->json([
                'status'  => 0,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $id = DB::table(self::TABLE)->insertGetId([
            'sub_institute_id' => $context['sub_institute_id'],
            'title'            => $request->input('title'),
            'objective'        => $request->input('objective'),
            'focus_areas'      => $this->joinList($request->input('focus_areas')),
            'user_id'          => $request->input('user_id_target') ?: $request->input('user_id'),
            'competency_id'    => $request->input('competency_id'),
            'framework_id'     => $request->input('framework_id'),
            'career_path_id'   => $request->input('career_path_id'),
            'department_id'    => $request->input('department_id'),
            'jobrole'          => $request->input('jobrole'),
            'status'           => $request->input('status', 'active'),
            'progress'         => (int) $request->input('progress', 0),
            'start_date'       => $request->input('start_date'),
            'due_date'         => $request->input('due_date'),
            'approver_id'      => $request->input('approver_id'),
            'approval_status'  => $request->input('approver_id') ? 'pending_approval' : null,
            'created_by'       => $context['user_id'],
            'updated_by'       => $context['user_id'],
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        AuditLog::record([
            'module'      => 'competency_management',
            'action'      => 'development_plan.created',
            'entity_type' => 'development_plan',
            'entity_id'   => $id,
            'new_values'  => [
                'title'   => $request->input('title'),
                'user_id' => $request->input('user_id_target') ?: $request->input('user_id'),
                'status'  => $request->input('status', 'active'),
            ],
        ]);

        $this->logCompetencyActivity(
            $context['sub_institute_id'],
            $context['user_id'],
            'created_development_plan',
            'Created development plan "' . $request->input('title') . '"',
            'development_plan',
            $id,
            $request->input('title')
        );

        return response()->json([
            'status'  => 1,
            'message' => 'Development plan created successfully',
            'data'    => ['id' => $id],
        ], 201);
    }

    /** PUT /api/competency/development-plans/{id} */
    public function update(Request $request, $id)
    {
        if ($response = $this->assertIsAdmin()) { return $response; }

        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        $plan = $this->findPlan($id, $sid);
        if (!$plan) {
            return response()->json(['status' => 0, 'message' => 'Development plan not found'], 404);
        }

        $validator = Validator::make($request->all(), $this->planRules(false));
        if ($validator->fails()) {
            return response()->json([
                'status'  => 0,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $update = ['updated_by' => $context['user_id'], 'updated_at' => now()];

        foreach (['title', 'objective', 'competency_id', 'framework_id', 'career_path_id',
                  'department_id', 'jobrole', 'start_date', 'due_date', 'approver_id'] as $field) {
            if ($request->has($field)) {
                $value = $request->input($field);
                $update[$field] = ($value === '' && $field !== 'title') ? null : $value;
            }
        }
        if ($request->has('focus_areas')) {
            $update['focus_areas'] = $this->joinList($request->input('focus_areas'));
        }
        if ($request->has('user_id_target')) {
            $update['user_id'] = $request->input('user_id_target');
        }
        if ($request->has('progress')) {
            $update['progress'] = (int) $request->input('progress');
        }
        if ($request->has('status')) {
            $update['status'] = $request->input('status');
            if ($request->input('status') === 'completed' && !$plan->completed_at) {
                $update['completed_at'] = now();
                $update['progress'] = 100;
            } elseif ($request->input('status') !== 'completed') {
                $update['completed_at'] = null;
            }
        }
        if ($request->has('approver_id')) {
            $update['approval_status'] = $request->input('approver_id') ? ($plan->approval_status ?: 'pending_approval') : null;
        }
        if ($request->has('approval_status')) {
            $update['approval_status'] = $this->activeFilter($request->input('approval_status'));
        }

        DB::table(self::TABLE)->where('id', $plan->id)->update($update);

        AuditLog::record([
            'module'      => 'competency_management',
            'action'      => 'development_plan.updated',
            'entity_type' => 'development_plan',
            'entity_id'   => $plan->id,
            'new_values'  => $update,
        ]);

        $this->logCompetencyActivity(
            $sid,
            $context['user_id'],
            'updated_development_plan',
            'Updated development plan "' . ($update['title'] ?? $plan->title) . '"',
            'development_plan',
            (int) $plan->id,
            $update['title'] ?? $plan->title,
            $this->diffChanges($plan, $update, self::CHANGE_LABELS)
        );

        return response()->json([
            'status'  => 1,
            'message' => 'Development plan updated successfully',
            'data'    => ['id' => (int) $plan->id],
        ]);
    }

    public function destroy(Request $request, $id)
    {
        if ($response = $this->assertIsAdmin()) { return $response; }

        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $plan = $this->findPlan($id, $context['sub_institute_id']);
        if (!$plan) {
            return response()->json(['status' => 0, 'message' => 'Development plan not found'], 404);
        }

        DB::table(self::TABLE)->where('id', $plan->id)->update([
            'deleted_at' => now(),
            'deleted_by' => $context['user_id'],
        ]);

        AuditLog::record([
            'module'      => 'competency_management',
            'action'      => 'development_plan.deleted',
            'entity_type' => 'development_plan',
            'entity_id'   => $plan->id,
            'new_values'  => ['title' => $plan->title],
        ]);

        $this->logCompetencyActivity(
            $context['sub_institute_id'],
            $context['user_id'],
            'deleted_development_plan',
            'Deleted development plan "' . $plan->title . '"',
            'development_plan',
            (int) $plan->id,
            $plan->title
        );

        return response()->json(['status' => 1, 'message' => 'Development plan deleted successfully']);
    }

    /* ================================================================== *
     * Plan detail tabs
     * ================================================================== */

    /**
     * GET /api/competency/development-plans/{id}/gaps
     *
     * "Competency Gaps" tab. Computed, not stored: the employee's current
     * levels (s_skill_matrix) against the levels their job role requires
     * (s_user_skill_jobrole).
     */
    public function gaps(Request $request, $id)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        $plan = $this->findPlan($id, $sid);
        if (!$plan) {
            return response()->json(['status' => 0, 'message' => 'Development plan not found'], 404);
        }

        if (!$plan->user_id) {
            return response()->json([
                'status'  => 1,
                'message' => 'Competency gaps fetched successfully',
                'data'    => ['jobrole' => $plan->jobrole, 'items' => [], 'summary' => $this->emptyGapSummary()],
            ]);
        }

        $required = collect();
        if ($plan->jobrole) {
            $required = DB::table('s_user_skill_jobrole')
                ->where('sub_institute_id', $sid)
                ->where('jobrole', $plan->jobrole)
                ->whereNull('deleted_at')
                ->get()
                ->keyBy('skill');
        }

        $current = DB::table('s_skill_matrix as m')
            ->join('s_users_skills as s', 'm.skill_id', '=', 's.id')
            ->where('m.user_id', $plan->user_id)
            ->whereNull('m.deleted_at')
            ->whereNull('s.deleted_at')
            ->get(['s.id as skill_id', 's.title', 's.category', 'm.skill_level', 'm.updated_at'])
            ->keyBy('title');

        $items = [];
        foreach ($required as $skillName => $row) {
            $requiredLevel = (int) $row->proficiency_level;
            $held = $current->get($skillName);
            $currentLevel = $held ? (int) $held->skill_level : 0;

            $items[] = [
                'competency_id' => $held ? (int) $held->skill_id : null,
                'name'          => (string) $skillName,
                'category'      => $held->category ?? null,
                'required'      => $requiredLevel,
                'current'       => $currentLevel,
                'gap'           => $currentLevel - $requiredLevel,
                'is_focus'      => $this->isFocusArea($skillName, $plan->focus_areas),
                'last_assessed' => $held && $held->updated_at ? $this->humanDate($held->updated_at) : null,
            ];
        }

        usort($items, function ($a, $b) {
            if ($a['gap'] === $b['gap']) {
                return strcasecmp($a['name'], $b['name']);
            }
            return $a['gap'] <=> $b['gap'];
        });

        $met = count(array_filter($items, fn ($i) => $i['gap'] >= 0));
        $total = count($items);

        return response()->json([
            'status'  => 1,
            'message' => 'Competency gaps fetched successfully',
            'data'    => [
                'jobrole' => $plan->jobrole,
                'items'   => $items,
                'summary' => [
                    'total'        => $total,
                    'met'          => $met,
                    'gaps'         => $total - $met,
                    'met_percent'  => $total > 0 ? (int) round($met / $total * 100) : 0,
                ],
            ],
        ]);
    }

    /** GET /api/competency/development-plans/{id}/actions */
    public function actions(Request $request, $id)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        if (!$this->findPlan($id, $sid)) {
            return response()->json(['status' => 0, 'message' => 'Development plan not found'], 404);
        }

        $rows = DB::table('s_competency_plan_actions as a')
            ->leftJoin('s_users_skills as s', 's.id', '=', 'a.competency_id')
            ->where('a.plan_id', $id)
            ->where('a.sub_institute_id', $sid)
            ->whereNull('a.deleted_at')
            ->orderBy('a.sequence')
            ->orderBy('a.id')
            ->get(['a.*', 's.title as competency_name']);

        $owners = $this->userMap($rows->pluck('owner_id')->filter()->all());

        $data = $rows->map(fn ($r) => [
            'id'              => (int) $r->id,
            'title'           => $r->title,
            'description'     => $r->description,
            'action_type'     => $r->action_type,
            'status'          => $r->status,
            'competency_id'   => $r->competency_id ? (int) $r->competency_id : null,
            'competency_name' => $r->competency_name,
            'owner_id'        => $r->owner_id ? (int) $r->owner_id : null,
            'owner_name'      => $r->owner_id ? ($owners[$r->owner_id]['name'] ?? null) : null,
            'due_date'        => $r->due_date,
            'due_date_label'  => $this->humanDate($r->due_date),
            'completed_at'    => $this->humanDate($r->completed_at),
            'sequence'        => (int) $r->sequence,
        ])->all();

        return response()->json([
            'status'  => 1,
            'message' => 'Plan actions fetched successfully',
            'data'    => $data,
        ]);
    }

    /** POST /api/competency/development-plans/{id}/actions */
    public function storeAction(Request $request, $id)
    {
        if ($response = $this->assertIsAdmin()) { return $response; }

        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        $plan = $this->findPlan($id, $sid);
        if (!$plan) {
            return response()->json(['status' => 0, 'message' => 'Development plan not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'title'         => 'required|string|max:191',
            'description'   => 'nullable|string',
            'action_type'   => 'nullable|in:milestone,training,mentoring,project,reading,other',
            'status'        => 'nullable|in:pending,in_progress,completed,blocked',
            'competency_id' => 'nullable|integer',
            'owner_id'      => 'nullable|integer',
            'due_date'      => 'nullable|date',
            'sequence'      => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 0,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $status = $request->input('status', 'pending');

        $actionId = DB::table('s_competency_plan_actions')->insertGetId([
            'sub_institute_id' => $sid,
            'plan_id'          => (int) $plan->id,
            'title'            => $request->input('title'),
            'description'      => $request->input('description'),
            'action_type'      => $request->input('action_type', 'milestone'),
            'status'           => $status,
            'competency_id'    => $request->input('competency_id'),
            'owner_id'         => $request->input('owner_id') ?: $plan->user_id,
            'due_date'         => $request->input('due_date'),
            'completed_at'     => $status === 'completed' ? now() : null,
            'sequence'         => (int) $request->input('sequence', 0),
            'created_by'       => $context['user_id'],
            'updated_by'       => $context['user_id'],
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $progress = $this->syncPlanProgress((int) $plan->id, $sid, $context['user_id']);

        AuditLog::record([
            'module'      => 'competency_management',
            'action'      => 'development_plan.action_added',
            'entity_type' => 'development_plan',
            'entity_id'   => $plan->id,
            'new_values'  => [
                'action_id' => $actionId,
                'title'     => $request->input('title'),
                'status'    => $status,
            ],
        ]);

        $this->logCompetencyActivity(
            $sid,
            $context['user_id'],
            'added_plan_action',
            'Added action "' . $request->input('title') . '" to plan "' . $plan->title . '"',
            'development_plan',
            (int) $plan->id,
            $plan->title
        );

        return response()->json([
            'status'  => 1,
            'message' => 'Plan action created successfully',
            'data'    => ['id' => $actionId, 'plan_progress' => $progress],
        ], 201);
    }

    /** PUT /api/competency/development-plans/{id}/actions/{actionId} */
    public function updateAction(Request $request, $id, $actionId)
    {
        if ($response = $this->assertIsAdmin()) { return $response; }

        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        $plan = $this->findPlan($id, $sid);
        if (!$plan) {
            return response()->json(['status' => 0, 'message' => 'Development plan not found'], 404);
        }

        $action = DB::table('s_competency_plan_actions')
            ->where('id', $actionId)->where('plan_id', $plan->id)
            ->where('sub_institute_id', $sid)->whereNull('deleted_at')->first();

        if (!$action) {
            return response()->json(['status' => 0, 'message' => 'Plan action not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'title'         => 'sometimes|required|string|max:191',
            'description'   => 'nullable|string',
            'action_type'   => 'nullable|in:milestone,training,mentoring,project,reading,other',
            'status'        => 'nullable|in:pending,in_progress,completed,blocked',
            'competency_id' => 'nullable|integer',
            'owner_id'      => 'nullable|integer',
            'due_date'      => 'nullable|date',
            'sequence'      => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 0,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $update = ['updated_by' => $context['user_id'], 'updated_at' => now()];
        foreach (['title', 'description', 'action_type', 'competency_id', 'owner_id', 'due_date', 'sequence'] as $field) {
            if ($request->has($field)) {
                $update[$field] = $request->input($field);
            }
        }
        if ($request->has('status')) {
            $update['status'] = $request->input('status');
            $update['completed_at'] = $request->input('status') === 'completed'
                ? ($action->completed_at ?: now())
                : null;
        }

        DB::table('s_competency_plan_actions')->where('id', $action->id)->update($update);

        $progress = $this->syncPlanProgress((int) $plan->id, $sid, $context['user_id']);

        AuditLog::record([
            'module'      => 'competency_management',
            'action'      => 'development_plan.action_updated',
            'entity_type' => 'development_plan',
            'entity_id'   => $plan->id,
            'new_values'  => array_merge($update, ['action_id' => $action->id]),
        ]);

        $this->logCompetencyActivity(
            $sid,
            $context['user_id'],
            ($request->input('status') === 'completed' && $action->status !== 'completed')
                ? 'completed_plan_action'
                : 'updated_plan_action',
            ($request->input('status') === 'completed' && $action->status !== 'completed' ? 'Completed' : 'Updated')
                . ' action "' . ($update['title'] ?? $action->title) . '" on plan "' . $plan->title . '"',
            'development_plan',
            (int) $plan->id,
            $plan->title,
            $this->diffChanges($action, $update, [
                'title'         => 'Action Title',
                'description'   => 'Description',
                'action_type'   => 'Action Type',
                'status'        => 'Status',
                'competency_id' => 'Linked Competency',
                'owner_id'      => 'Owner',
                'due_date'      => 'Due Date',
                'sequence'      => 'Order',
            ])
        );

        return response()->json([
            'status'  => 1,
            'message' => 'Plan action updated successfully',
            'data'    => ['id' => (int) $action->id, 'plan_progress' => $progress],
        ]);
    }

    /** DELETE /api/competency/development-plans/{id}/actions/{actionId} */
    public function destroyAction(Request $request, $id, $actionId)
    {
        if ($response = $this->assertIsAdmin()) { return $response; }

        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        $plan = $this->findPlan($id, $sid);
        if (!$plan) {
            return response()->json(['status' => 0, 'message' => 'Development plan not found'], 404);
        }

        $action = DB::table('s_competency_plan_actions')
            ->where('id', $actionId)->where('plan_id', $plan->id)
            ->where('sub_institute_id', $sid)->whereNull('deleted_at')->first();

        if (!$action) {
            return response()->json(['status' => 0, 'message' => 'Plan action not found'], 404);
        }

        DB::table('s_competency_plan_actions')->where('id', $actionId)->update([
            'deleted_at' => now(),
            'deleted_by' => $context['user_id'],
        ]);

        $progress = $this->syncPlanProgress((int) $plan->id, $sid, $context['user_id']);

        AuditLog::record([
            'module'      => 'competency_management',
            'action'      => 'development_plan.action_deleted',
            'entity_type' => 'development_plan',
            'entity_id'   => $plan->id,
            'new_values'  => ['action_id' => (int) $actionId, 'title' => $action->title],
        ]);

        $this->logCompetencyActivity(
            $sid,
            $context['user_id'],
            'deleted_plan_action',
            'Removed action "' . $action->title . '" from plan "' . $plan->title . '"',
            'development_plan',
            (int) $plan->id,
            $plan->title
        );

        return response()->json([
            'status'  => 1,
            'message' => 'Plan action deleted successfully',
            'data'    => ['plan_progress' => $progress],
        ]);
    }

    /**
     * GET /api/competency/development-plans/{id}/history
     *
     * "Notes & History" tab: this plan's slice of the shared competency
     * activity feed (s_competency_activity_log), newest first.
     */
    public function history(Request $request, $id)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        $plan = $this->findPlan($id, $sid);
        if (!$plan) {
            return response()->json(['status' => 0, 'message' => 'Development plan not found'], 404);
        }

        $rows = DB::table('s_competency_activity_log')
            ->where('sub_institute_id', $sid)
            ->where('subject_type', 'development_plan')
            ->where('subject_id', $plan->id)
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $data = $rows->map(fn ($r) => [
            'id'          => (int) $r->id,
            'action'      => $r->action,
            'description' => $r->description,
            'by'          => $r->actor_name ?: 'System',
            'date'        => $r->created_at ? date('d M Y, H:i', strtotime($r->created_at)) : null,
        ])->all();

        return response()->json([
            'status'  => 1,
            'message' => 'Plan history fetched successfully',
            'data'    => $data,
        ]);
    }

    /* ================================================================== *
     * Helpers
     * ================================================================== */

    private function planRules(bool $creating): array
    {
        return [
            'title'          => ($creating ? 'required' : 'sometimes|required') . '|string|max:191',
            'objective'      => 'nullable|string',
            'user_id_target' => 'nullable|integer',
            'competency_id'  => 'nullable|integer',
            'framework_id'   => 'nullable|integer',
            'career_path_id' => 'nullable|integer',
            'department_id'  => 'nullable|integer',
            'jobrole'        => 'nullable|string|max:191',
            'status'         => 'nullable|in:active,completed,overdue,on_hold',
            'progress'       => 'nullable|integer|min:0|max:100',
            'start_date'     => 'nullable|date',
            'due_date'       => 'nullable|date',
            'approver_id'    => 'nullable|integer',
        ];
    }

    private function findPlan($id, int $sid)
    {
        return DB::table(self::TABLE)
            ->where('id', $id)
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at')
            ->first();
    }

    /**
     * Widen the raw plan rows with the names the workspace table renders.
     * Employee and owner are resolved in two batched queries, not per row.
     */
    private function decorateRows($rows, int $sid): array
    {
        if ($rows->isEmpty()) {
            return [];
        }

        $userIds = $rows->pluck('user_id')->merge($rows->pluck('approver_id'))->filter()->unique()->all();
        $users = $this->userMap($userIds);

        $departmentIds = $rows->pluck('department_id')->filter()->unique()->all();
        $departments = [];
        if ($departmentIds) {
            $departments = DB::table('s_user_jobrole')
                ->where('sub_institute_id', $sid)
                ->whereIn('department_id', $departmentIds)
                ->whereNull('deleted_at')
                ->pluck('department', 'department_id')
                ->all();
        }

        return $rows->map(function ($r) use ($users, $departments) {
            $employee = $r->user_id ? ($users[$r->user_id] ?? null) : null;
            $owner = $r->approver_id ? ($users[$r->approver_id] ?? null) : null;

            return [
                'id'               => (int) $r->id,
                'title'            => $r->title,
                'user_id'          => $r->user_id ? (int) $r->user_id : null,
                'employee_name'    => $employee['name'] ?? null,
                'employee_initials'=> $employee['initials'] ?? null,
                'employee_no'      => $employee['employee_no'] ?? null,
                'jobrole'          => $r->jobrole,
                'department_id'    => $r->department_id ? (int) $r->department_id : null,
                'department'       => $r->department_id ? ($departments[$r->department_id] ?? null) : null,
                'status'           => $r->status,
                'status_label'     => self::STATUS_LABELS[$r->status] ?? ucfirst((string) $r->status),
                'progress'         => (int) $r->progress,
                'start_date'       => $r->start_date,
                'due_date'         => $r->due_date,
                'start_date_label' => $this->humanDate($r->start_date),
                'due_date_label'   => $this->humanDate($r->due_date),
                'approver_id'      => $r->approver_id ? (int) $r->approver_id : null,
                'owner_name'       => $owner['name'] ?? null,
                'owner_initials'   => $owner['initials'] ?? null,
                'approval_status'  => $r->approval_status,
                'competency_id'    => $r->competency_id ? (int) $r->competency_id : null,
                'framework_id'     => $r->framework_id ? (int) $r->framework_id : null,
                'career_path_id'   => isset($r->career_path_id) && $r->career_path_id ? (int) $r->career_path_id : null,
                'completed_at'     => $this->humanDate($r->completed_at),
            ];
        })->values()->all();
    }

    /** @return array<int, array{name:string, initials:string, employee_no:?string}> */
    private function userMap(array $ids): array
    {
        $ids = array_values(array_filter(array_unique($ids)));
        if (!$ids) {
            return [];
        }

        return DB::table('tbluser')
            ->whereIn('id', $ids)
            ->get(['id', 'first_name', 'last_name', 'employee_no'])
            ->mapWithKeys(function ($u) {
                $first = trim((string) $u->first_name);
                $last = trim((string) $u->last_name);
                $name = trim($first . ' ' . $last) ?: ('User ' . $u->id);
                $initials = strtoupper(substr($first ?: $name, 0, 1) . substr($last, 0, 1));

                return [(int) $u->id => [
                    'name'        => $name,
                    'initials'    => $initials ?: strtoupper(substr($name, 0, 2)),
                    'employee_no' => $u->employee_no,
                ]];
            })
            ->all();
    }

    /**
     * Recompute plan progress from its action items, so the workspace's
     * progress bars reflect real completion instead of a typed-in number.
     * Plans with no actions keep whatever progress was set manually.
     */
    private function syncPlanProgress(int $planId, int $sid, ?int $actorId): ?int
    {
        $total = DB::table('s_competency_plan_actions')
            ->where('plan_id', $planId)->where('sub_institute_id', $sid)->whereNull('deleted_at')->count();

        if ($total === 0) {
            return null;
        }

        $done = DB::table('s_competency_plan_actions')
            ->where('plan_id', $planId)->where('sub_institute_id', $sid)->whereNull('deleted_at')
            ->where('status', 'completed')->count();

        $progress = (int) round($done / $total * 100);

        $update = ['progress' => $progress, 'updated_by' => $actorId, 'updated_at' => now()];
        if ($progress === 100) {
            $update['status'] = 'completed';
            $update['completed_at'] = now();
        }

        DB::table(self::TABLE)->where('id', $planId)->update($update);

        return $progress;
    }

    /** Comma-separated storage <-> array, mirroring s_user_jobrole.related_jobrole. */
    private function splitList(?string $value): array
    {
        if (!$value) {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(',', $value)), fn ($v) => $v !== ''));
    }

    private function joinList($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_array($value)) {
            $value = implode(',', array_filter(array_map('trim', $value), fn ($v) => $v !== ''));
        }
        return trim((string) $value) ?: null;
    }

    private function isFocusArea(string $name, ?string $focusAreas): bool
    {
        foreach ($this->splitList($focusAreas) as $area) {
            if (strcasecmp($area, $name) === 0) {
                return true;
            }
        }
        return false;
    }

    /** First numeric entry of a CSV column (tbluser.allocated_standards). */
    private function firstNumeric($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        foreach (array_map('trim', explode(',', (string) $value)) as $part) {
            if (is_numeric($part)) {
                return (int) $part;
            }
        }
        return null;
    }

    private function humanDate($value): ?string
    {
        return $value ? date('d M Y', strtotime((string) $value)) : null;
    }

    private function emptyGapSummary(): array
    {
        return ['total' => 0, 'met' => 0, 'gaps' => 0, 'met_percent' => 0];
    }
}
