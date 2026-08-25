<?php

namespace App\Http\Controllers\api\TaskManagement;

use App\Http\Controllers\api\TaskManagement\Concerns\ResolvesTaskManagementContext;
use App\Http\Controllers\Controller;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Ported from hp_erp's `App\Http\Controllers\Api\TaskManagement\ProjectController`.
 *
 * Full CRUD for projects, their team (members), workstreams, and linking
 * legacy tasks into a project.
 */
class ProjectController extends Controller
{
    use ResolvesTaskManagementContext;

    private const STATUSES = ['PLANNING', 'IN PROGRESS', 'AT RISK', 'COMPLETED', 'ARCHIVED'];
    private const PRIORITIES = ['High', 'Medium', 'Low'];
    private const CATEGORIES = ['it', 'healthcare', 'finance', 'real_estate', 'rd', 'marketing'];
    private const MEMBER_ROLES = ['MEMBER', 'LEAD', 'SPONSOR'];

    public function index(Request $request)
    {
        $context = $this->taskManagementContext($request);

        $validator = Validator::make($request->all(), [
            'search' => 'nullable|string|max:191',
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'include_archived' => 'nullable|boolean',
        ]);
        if ($validator->fails()) {
            return $this->taskManagementError($validator->errors()->first(), 422, ['errors' => $validator->errors()]);
        }

        $query = $this->projectQuery($context);
        if (!$request->boolean('include_archived')) {
            $query->whereNull('p.archived_at');
        }
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(fn (Builder $q) => $q
                ->where('p.name', 'like', "%{$search}%")
                ->orWhere('p.code', 'like', "%{$search}%")
                ->orWhereRaw("CONCAT_WS(' ', manager.first_name, manager.middle_name, manager.last_name) like ?", ["%{$search}%"]));
        }
        if ($request->filled('status')) {
            $query->where('p.status', $request->input('status'));
        }

        $projects = $query->orderByDesc('p.updated_at')->paginate((int) $request->input('per_page', 12));
        $projects->getCollection()->transform(fn ($project) => $this->resource($project));

        return $this->taskManagementResponse([
            'projects' => $projects->items(),
            'pagination' => [
                'current_page' => $projects->currentPage(), 'last_page' => $projects->lastPage(),
                'per_page' => $projects->perPage(), 'total' => $projects->total(),
            ],
            'filters' => ['statuses' => self::STATUSES, 'priorities' => self::PRIORITIES],
        ], 'Projects retrieved successfully.');
    }

    public function store(Request $request)
    {
        $context = $this->taskManagementContext($request);

        $validator = $this->projectValidator($request, $context);
        if ($validator->fails()) {
            return $this->taskManagementError($validator->errors()->first(), 422, ['errors' => $validator->errors()]);
        }

        $id = DB::transaction(function () use ($request, $context) {
            $id = DB::table('task_management_projects')->insertGetId($this->projectPayload($request, $context) + [
                'code' => $this->nextCode($context),
                'created_by' => $context['user_id'],
                'created_at' => now(),
            ]);
            $members = array_values(array_unique(array_filter(array_merge(
                $request->input('member_ids', []),
                [$request->input('manager_id'), $request->input('sponsor_id')]
            ))));
            $this->syncMembers($id, $members, $context['user_id']);
            $this->syncDepartments($id, $request, (int) $context['user_id']);
            return $id;
        });

        return $this->taskManagementResponse($this->findProject($context, $id), 'Project created successfully.', 201);
    }

    public function show(Request $request, int $id)
    {
        $context = $this->taskManagementContext($request);

        $project = $this->findProject($context, $id, true);
        if (!$project) {
            return $this->taskManagementError('Project not found.', 404);
        }

        return $this->taskManagementResponse($project, 'Project retrieved successfully.');
    }

    public function update(Request $request, int $id)
    {
        $context = $this->taskManagementContext($request);

        if (!$this->canManage($context, $id)) {
            return $this->taskManagementError('You cannot manage this project.', 403);
        }

        $validator = $this->projectValidator($request, $context);
        if ($validator->fails()) {
            return $this->taskManagementError($validator->errors()->first(), 422, ['errors' => $validator->errors()]);
        }

        DB::transaction(function () use ($request, $context, $id) {
            DB::table('task_management_projects')->where('id', $id)->update(
                $this->projectPayload($request, $context) + ['updated_by' => $context['user_id'], 'updated_at' => now()]
            );
            $members = array_values(array_unique(array_filter(array_merge(
                $request->input('member_ids', []),
                [$request->input('manager_id'), $request->input('sponsor_id')]
            ))));
            $this->syncMembers($id, $members, $context['user_id']);
            $this->syncDepartments($id, $request, (int) $context['user_id']);
        });

        return $this->taskManagementResponse($this->findProject($context, $id, true), 'Project updated successfully.');
    }

    public function archive(Request $request, int $id)
    {
        $context = $this->taskManagementContext($request);

        if (!$this->canManage($context, $id)) {
            return $this->taskManagementError('You cannot archive this project.', 403);
        }

        DB::table('task_management_projects')->where('id', $id)->update([
            'status' => 'ARCHIVED', 'archived_at' => now(), 'archived_by' => $context['user_id'],
            'updated_by' => $context['user_id'], 'updated_at' => now(),
        ]);

        return $this->taskManagementResponse(null, 'Project archived successfully.');
    }

    public function options(Request $request)
    {
        $context = $this->taskManagementContext($request);

        $users = DB::table('tbluser')->where('sub_institute_id', $context['sub_institute_id'])
            ->where('status', 1)->orderBy('first_name')
            ->select('id', DB::raw("TRIM(CONCAT_WS(' ', first_name, middle_name, last_name)) as name"))->get();
        $departments = DB::table('hrms_departments')->where('sub_institute_id', $context['sub_institute_id'])
            ->orderBy('department')->select('id', 'department as name')->get();
        $tasks = DB::table('task as t')
            ->leftJoin('tbluser as assignee', 'assignee.id', '=', 't.TASK_ALLOCATED_TO')
            ->where('t.sub_institute_id', $context['sub_institute_id'])
            ->where('t.SYEAR', $context['syear'])->whereNull('t.deleted_at')
            ->orderByDesc('t.ID')->limit(200)
            ->select('t.ID as id', 't.TASK_TITLE as title', 't.STATUS', 'assignee.department_id')->get();

        return $this->taskManagementResponse([
            'users' => $users, 'departments' => $departments, 'tasks' => $tasks,
            'categories' => self::CATEGORIES, 'statuses' => self::STATUSES, 'priorities' => self::PRIORITIES,
        ], 'Project options retrieved successfully.');
    }

    public function syncProjectMembers(Request $request, int $id)
    {
        $context = $this->taskManagementContext($request);

        if (!$this->canManage($context, $id)) {
            return $this->taskManagementError('You cannot manage this team.', 403);
        }

        $validator = Validator::make($request->all(), ['member_ids' => 'required|array', 'member_ids.*' => 'integer']);
        if ($validator->fails()) {
            return $this->taskManagementError($validator->errors()->first(), 422, ['errors' => $validator->errors()]);
        }

        try {
            $this->validateTenantUsers($request->input('member_ids'), $context);
        } catch (\InvalidArgumentException $e) {
            return $this->taskManagementError($e->getMessage(), 422);
        }

        $this->syncMembers($id, $request->input('member_ids'), $context['user_id']);
        $memberIds = array_values(array_unique(array_map('intval', $request->input('member_ids'))));

        DB::table('task_management_workstreams')->where('project_id', $id)
            ->when($memberIds, fn ($query) => $query->whereNotIn('owner_id', $memberIds))
            ->when(!$memberIds, fn ($query) => $query->whereNotNull('owner_id'))
            ->update(['owner_id' => null, 'updated_by' => $context['user_id'], 'updated_at' => now()]);

        return $this->taskManagementResponse($this->members($id), 'Project team updated successfully.');
    }

    public function storeWorkstream(Request $request, int $id)
    {
        $context = $this->taskManagementContext($request);

        if (!$this->canManage($context, $id)) {
            return $this->taskManagementError('You cannot manage workstreams.', 403);
        }

        $validator = $this->workstreamValidator($request);
        if ($validator->fails()) {
            return $this->taskManagementError($validator->errors()->first(), 422, ['errors' => $validator->errors()]);
        }
        if ($request->filled('owner_id') && !$this->isProjectMember($id, (int) $request->input('owner_id'))) {
            return $this->taskManagementError('The selected owner must be a project team member.', 422);
        }

        $workstreamId = DB::table('task_management_workstreams')->insertGetId($this->workstreamPayload($request) + [
            'project_id' => $id, 'sub_institute_id' => $context['sub_institute_id'], 'syear' => $context['syear'],
            'created_by' => $context['user_id'], 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $this->taskManagementResponse($this->workstream($id, $workstreamId), 'Workstream created successfully.', 201);
    }

    public function updateWorkstream(Request $request, int $projectId, int $workstreamId)
    {
        $context = $this->taskManagementContext($request);

        if (!$this->canManage($context, $projectId)) {
            return $this->taskManagementError('You cannot manage workstreams.', 403);
        }

        $validator = $this->workstreamValidator($request);
        if ($validator->fails()) {
            return $this->taskManagementError($validator->errors()->first(), 422, ['errors' => $validator->errors()]);
        }
        if ($request->filled('owner_id') && !$this->isProjectMember($projectId, (int) $request->input('owner_id'))) {
            return $this->taskManagementError('The selected owner must be a project team member.', 422);
        }

        DB::table('task_management_workstreams')->where(['id' => $workstreamId, 'project_id' => $projectId])
            ->update($this->workstreamPayload($request) + ['updated_by' => $context['user_id'], 'updated_at' => now()]);

        return $this->taskManagementResponse($this->workstream($projectId, $workstreamId), 'Workstream updated successfully.');
    }

    public function destroyWorkstream(Request $request, int $projectId, int $workstreamId)
    {
        $context = $this->taskManagementContext($request);

        if (!$this->canManage($context, $projectId)) {
            return $this->taskManagementError('You cannot manage workstreams.', 403);
        }

        DB::table('task_management_workstreams')->where(['id' => $workstreamId, 'project_id' => $projectId])->delete();

        return $this->taskManagementResponse(null, 'Workstream deleted successfully.');
    }

    public function syncTasks(Request $request, int $id)
    {
        $context = $this->taskManagementContext($request);

        if (!$this->canManage($context, $id)) {
            return $this->taskManagementError('You cannot link tasks.', 403);
        }

        $validator = Validator::make($request->all(), ['task_ids' => 'required|array', 'task_ids.*' => 'integer']);
        if ($validator->fails()) {
            return $this->taskManagementError($validator->errors()->first(), 422, ['errors' => $validator->errors()]);
        }

        $valid = DB::table('task')->where('sub_institute_id', $context['sub_institute_id'])->where('SYEAR', $context['syear'])
            ->whereIn('ID', $request->input('task_ids'))->pluck('ID')->map(fn ($id) => (int) $id)->all();

        DB::transaction(function () use ($id, $valid, $context) {
            $existingWorkstreams = DB::table('task_management_project_tasks')->where('project_id', $id)->pluck('workstream_id', 'task_id');
            DB::table('task_management_project_tasks')->where('project_id', $id)->delete();
            foreach ($valid as $taskId) {
                DB::table('task_management_project_tasks')->insert([
                    'project_id' => $id, 'task_id' => $taskId,
                    'workstream_id' => $existingWorkstreams[$taskId] ?? null,
                    'sub_institute_id' => $context['sub_institute_id'], 'syear' => $context['syear'],
                    'created_by' => $context['user_id'], 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });

        return $this->taskManagementResponse(null, 'Project tasks updated successfully.');
    }

    /**
     * Attach one task to this project, optionally inside a workstream.
     * syncTasks replaces the project's entire task list; this adds one task
     * and leaves the rest alone.
     */
    public function attachTask(Request $request, int $id)
    {
        $context = $this->taskManagementContext($request);

        if (!$this->canManage($context, $id)) {
            return $this->taskManagementError('You cannot link tasks.', 403);
        }

        $validator = Validator::make($request->all(), [
            'task_id' => 'required|integer',
            'workstream_id' => 'nullable|integer',
        ]);
        if ($validator->fails()) {
            return $this->taskManagementError($validator->errors()->first(), 422, ['errors' => $validator->errors()]);
        }

        $taskId = $request->integer('task_id');
        $taskExists = DB::table('task')->where('ID', $taskId)
            ->where('sub_institute_id', $context['sub_institute_id'])->where('SYEAR', $context['syear'])
            ->whereNull('deleted_at')->exists();
        if (!$taskExists) {
            return $this->taskManagementError('Task not found.', 404);
        }

        $workstreamId = $request->filled('workstream_id') ? $request->integer('workstream_id') : null;
        if ($workstreamId !== null && !DB::table('task_management_workstreams')->where('id', $workstreamId)->where('project_id', $id)->exists()) {
            return $this->taskManagementError('That workstream does not belong to this project.', 422);
        }

        $existing = DB::table('task_management_project_tasks')->where('project_id', $id)->where('task_id', $taskId)->first();

        if ($existing) {
            DB::table('task_management_project_tasks')->where('id', $existing->id)->update(['workstream_id' => $workstreamId, 'updated_at' => now()]);
        } else {
            DB::table('task_management_project_tasks')->insert([
                'project_id' => $id, 'task_id' => $taskId, 'workstream_id' => $workstreamId,
                'sub_institute_id' => $context['sub_institute_id'], 'syear' => $context['syear'],
                'created_by' => $context['user_id'], 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $this->taskManagementResponse([
            'project_id' => (string) $id,
            'task_id' => (string) $taskId,
            'workstream_id' => $workstreamId !== null ? (string) $workstreamId : null,
        ], 'Task linked to the project.', $existing ? 200 : 201);
    }

    private function projectValidator(Request $request, array $context)
    {
        return Validator::make($request->all(), [
            'name' => 'required|string|max:191', 'category' => ['nullable', Rule::in(self::CATEGORIES)],
            'description' => 'required|string', 'department_id' => 'nullable|integer',
            // department_id is THE PRIMARY department; department_ids is the
            // full set. Both are tenant-checked in the after() hook below -
            // without it any integer was accepted and stored, including
            // another organisation's department.
            'department_ids' => 'nullable|array', 'department_ids.*' => 'integer',
            'sponsor_id' => 'nullable|integer', 'manager_id' => 'required|integer',
            'team_size' => 'nullable|string|max:20', 'member_ids' => 'nullable|array',
            'member_ids.*' => 'integer', 'priority' => ['required', Rule::in(self::PRIORITIES)],
            'status' => ['nullable', Rule::in(self::STATUSES)], 'start_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:start_date', 'budget_estimate' => 'nullable|numeric|min:0',
            'client_name' => 'nullable|string|max:191', 'regulatory_flags' => 'nullable|array',
            'regulatory_flags.*' => 'string|max:30',
        ])->after(function ($validator) use ($request, $context) {
            // Every department named must belong to THIS tenant. Without this
            // a caller could attach a project to another organisation's
            // department and it would render under their name.
            $departments = array_values(array_unique(array_filter(array_merge(
                $request->input('department_ids', []),
                [$request->input('department_id')]
            ))));

            if ($departments) {
                $valid = DB::table('hrms_departments')
                    ->whereIn('id', $departments)
                    ->where('sub_institute_id', $context['sub_institute_id'])
                    ->pluck('id')->all();

                if (count($valid) !== count($departments)) {
                    $validator->errors()->add('department_ids', 'One or more selected departments do not belong to this organisation.');
                }
            }

            try {
                $this->validateTenantUsers(array_filter(array_merge($request->input('member_ids', []), [$request->input('manager_id'), $request->input('sponsor_id')])), $context);
            } catch (\InvalidArgumentException $e) {
                $validator->errors()->add('member_ids', $e->getMessage());
            }
        });
    }

    private function projectPayload(Request $request, array $context): array
    {
        return [
            'name' => trim($request->input('name')), 'category' => $request->input('category'),
            'description' => trim($request->input('description')), 'department_id' => $request->input('department_id'),
            'sponsor_id' => $request->input('sponsor_id'), 'manager_id' => $request->input('manager_id'),
            'team_size' => $request->input('team_size'), 'priority' => $request->input('priority'),
            'status' => $request->input('status', 'PLANNING'), 'start_date' => $this->dateOnly($request->input('start_date')),
            'due_date' => $this->dateOnly($request->input('due_date')), 'budget_estimate' => $request->input('budget_estimate'),
            'client_name' => $request->input('client_name'), 'regulatory_flags' => json_encode($request->input('regulatory_flags', [])),
            'sub_institute_id' => $context['sub_institute_id'], 'syear' => $context['syear'],
        ];
    }

    private function projectQuery(array $context): Builder
    {
        return DB::table('task_management_projects as p')
            ->leftJoin('tbluser as manager', 'manager.id', '=', 'p.manager_id')
            ->leftJoin('tbluser as sponsor', 'sponsor.id', '=', 'p.sponsor_id')
            ->leftJoin('hrms_departments as department', 'department.id', '=', 'p.department_id')
            ->where('p.sub_institute_id', $context['sub_institute_id'])->where('p.syear', $context['syear'])
            ->select('p.*', 'department.department as department_name',
                DB::raw("TRIM(CONCAT_WS(' ', manager.first_name, manager.middle_name, manager.last_name)) as manager_name"),
                DB::raw("TRIM(CONCAT_WS(' ', sponsor.first_name, sponsor.middle_name, sponsor.last_name)) as sponsor_name"),
                DB::raw('(SELECT COUNT(*) FROM task_management_project_members pm WHERE pm.project_id = p.id) as members_count'),
                DB::raw('(SELECT COUNT(*) FROM task_management_project_tasks pt WHERE pt.project_id = p.id) as tasks_total'),
                DB::raw("(SELECT COUNT(*) FROM task_management_project_tasks pt JOIN task t ON t.ID = pt.task_id WHERE pt.project_id = p.id AND UPPER(t.STATUS) = 'COMPLETED') as tasks_completed"));
    }

    private function findProject(array $context, int $id, bool $details = false)
    {
        $row = $this->projectQuery($context)->where('p.id', $id)->first();
        if (!$row) {
            return null;
        }

        $resource = $this->resource($row);
        if ($details) {
            $resource['members'] = $this->members($id);
            $resource['workstreams'] = DB::table('task_management_workstreams as w')->leftJoin('tbluser as owner', 'owner.id', '=', 'w.owner_id')
                ->where('w.project_id', $id)->orderBy('w.sort_order')->orderBy('w.id')
                ->select('w.*', DB::raw("TRIM(CONCAT_WS(' ', owner.first_name, owner.middle_name, owner.last_name)) as owner_name"))->get();
            $resource['task_ids'] = DB::table('task_management_project_tasks')->where('project_id', $id)->pluck('task_id')->map(fn ($id) => (string) $id)->all();
            $resource['tasks'] = $this->linkedTasks($context, $id);
            $resource['departments'] = $this->departments($id);
        }
        return $resource;
    }

    /**
     * The tasks actually linked to this project, hydrated.
     *
     * The "Linked tasks" tab used to be built by filtering the global
     * options()-style task list by the project's department - and that list
     * carries the ASSIGNEE's department, not the task's, so the tab was
     * empty whenever the project had no department, or when no assignee
     * among the sampled tasks happened to match, even though tasks were
     * actually linked. The link has always existed in
     * task_management_project_tasks; this returns the whole row (including
     * `workstream_id`, so workstream placement is visible too) instead of
     * re-deriving membership from an unrelated field.
     *
     * TENANT SCOPING: task_management_project_tasks has no sub_institute_id.
     * The `task` join is filtered on tenant + syear so a link row pointing
     * at another tenant's task cannot surface it here.
     */
    private function linkedTasks(array $context, int $projectId)
    {
        return DB::table('task_management_project_tasks as pt')
            ->join('task as t', 't.ID', '=', 'pt.task_id')
            ->leftJoin('tbluser as assignee', 'assignee.id', '=', 't.TASK_ALLOCATED_TO')
            ->leftJoin('task_management_workstreams as w', 'w.id', '=', 'pt.workstream_id')
            ->where('pt.project_id', $projectId)
            ->where('t.sub_institute_id', $context['sub_institute_id'])
            ->where('t.SYEAR', $context['syear'])
            ->whereNull('t.deleted_at')
            ->orderBy('w.sort_order')->orderByDesc('t.ID')
            ->select(
                't.ID', 't.TASK_TITLE as title', 't.STATUS', 't.TASK_DATE as due_date',
                'pt.workstream_id', 'w.name as workstream_name',
                DB::raw("TRIM(CONCAT_WS(' ', assignee.first_name, assignee.middle_name, assignee.last_name)) as assignee")
            )
            ->get()
            ->map(fn ($row) => [
                'id' => (string) $row->ID,
                'title' => $row->title,
                'status' => $row->STATUS,
                'due_date' => $row->due_date,
                'assignee' => $row->assignee ?: null,
                'workstream_id' => $row->workstream_id ? (string) $row->workstream_id : null,
                'workstream_name' => $row->workstream_name,
            ])
            ->all();
    }

    /**
     * Replace this project's department set.
     *
     * Mirrors syncMembers(): delete-and-reinsert, so the request describes
     * the whole set rather than a diff. `department_id` on the project row
     * is rewritten from whichever department is primary, which keeps the
     * single column and the join table from ever disagreeing - the project
     * list, the Home dashboard and every existing report still read that
     * column.
     *
     * The primary is the explicit `department_id` when given, otherwise the
     * first of `department_ids`. A project with no departments clears both.
     */
    private function syncDepartments(int $projectId, Request $request, int $userId): void
    {
        $all = array_values(array_unique(array_filter(array_merge(
            $request->input('department_ids', []),
            [$request->input('department_id')]
        ))));

        $primary = $request->input('department_id') ?: ($all[0] ?? null);

        DB::table('task_management_project_departments')->where('project_id', $projectId)->delete();

        if ($all) {
            DB::table('task_management_project_departments')->insert(
                array_map(fn ($departmentId) => [
                    'project_id' => $projectId,
                    'department_id' => (int) $departmentId,
                    'is_primary' => (int) $departmentId === (int) $primary,
                    'created_by' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], $all)
            );
        }

        // Keep the scalar column in step with the primary row.
        DB::table('task_management_projects')->where('id', $projectId)
            ->update(['department_id' => $primary ? (int) $primary : null]);
    }

    /** Every department on a project, primary first. */
    private function departments(int $projectId): array
    {
        return DB::table('task_management_project_departments as pd')
            ->leftJoin('hrms_departments as d', 'd.id', '=', 'pd.department_id')
            ->where('pd.project_id', $projectId)
            ->orderByDesc('pd.is_primary')->orderBy('d.department')
            ->get(['pd.department_id', 'd.department as name', 'pd.is_primary'])
            ->map(fn ($row) => [
                'id' => (string) $row->department_id,
                'name' => $row->name,
                'is_primary' => (bool) $row->is_primary,
            ])->all();
    }

    private function resource(object $row): array
    {
        $total = (int) $row->tasks_total;
        $completed = (int) $row->tasks_completed;

        return [
            'id' => (string) $row->id, 'code' => $row->code, 'name' => $row->name,
            'category' => $row->category, 'description' => $row->description,
            'department_id' => $row->department_id ? (string) $row->department_id : null,
            'department' => $row->department_name, 'sponsor_id' => $row->sponsor_id ? (string) $row->sponsor_id : null,
            'sponsor' => $row->sponsor_name, 'manager_id' => $row->manager_id ? (string) $row->manager_id : null,
            'manager' => $row->manager_name, 'team_size' => $row->team_size, 'priority' => $row->priority,
            'status' => $row->status, 'start_date' => $row->start_date, 'due_date' => $row->due_date,
            'budget_estimate' => $row->budget_estimate, 'client_name' => $row->client_name,
            'regulatory_flags' => json_decode($row->regulatory_flags ?: '[]', true) ?: [],
            'members_count' => (int) $row->members_count, 'tasks_total' => $total,
            'tasks_completed' => $completed, 'progress' => $total ? (int) round($completed * 100 / $total) : 0,
            'archived_at' => $row->archived_at,
        ];
    }

    private function members(int $id)
    {
        return DB::table('task_management_project_members as pm')->join('tbluser as u', 'u.id', '=', 'pm.user_id')
            ->where('pm.project_id', $id)->orderBy('u.first_name')
            ->select('pm.user_id as id', 'pm.role', DB::raw("TRIM(CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name)) as name"))->get();
    }

    private function syncMembers(int $projectId, array $memberIds, int $actor): void
    {
        $project = DB::table('task_management_projects')->where('id', $projectId)->first(['sub_institute_id', 'syear']);

        DB::table('task_management_project_members')->where('project_id', $projectId)->delete();
        foreach (array_unique(array_map('intval', $memberIds)) as $userId) {
            DB::table('task_management_project_members')->insert([
                'project_id' => $projectId, 'user_id' => $userId, 'role' => 'MEMBER',
                'sub_institute_id' => $project->sub_institute_id ?? 0, 'syear' => $project->syear ?? 0,
                'created_by' => $actor, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function validateTenantUsers(array $ids, array $context): void
    {
        $ids = array_values(array_unique(array_map('intval', array_filter($ids))));
        if (!$ids) {
            return;
        }

        $count = DB::table('tbluser')->where('sub_institute_id', $context['sub_institute_id'])->where('status', 1)->whereIn('id', $ids)->count();
        if ($count !== count($ids)) {
            throw new \InvalidArgumentException('One or more selected users do not belong to this organization.');
        }
    }

    private function canManage(array $context, int $id): bool
    {
        return DB::table('task_management_projects')->where('id', $id)
            ->where('sub_institute_id', $context['sub_institute_id'])->where('syear', $context['syear'])
            ->where(fn ($q) => $q->where('created_by', $context['user_id'])->orWhere('manager_id', $context['user_id'])->orWhere('sponsor_id', $context['user_id']))->exists();
    }

    private function nextCode(array $context): string
    {
        $next = (int) DB::table('task_management_projects')->where('sub_institute_id', $context['sub_institute_id'])->max('id') + 1;
        return 'PRJ-' . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    private function workstreamValidator(Request $request)
    {
        return Validator::make($request->all(), [
            'name' => 'required|string|max:191', 'description' => 'nullable|string', 'owner_id' => 'nullable|integer',
            'status' => ['required', Rule::in(self::STATUSES)], 'start_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:start_date', 'sort_order' => 'nullable|integer|min:0',
        ]);
    }

    private function isProjectMember(int $projectId, int $userId): bool
    {
        return DB::table('task_management_project_members')->where('project_id', $projectId)->where('user_id', $userId)->exists();
    }

    private function workstreamPayload(Request $request): array
    {
        $payload = $request->only(['name', 'description', 'owner_id', 'status', 'start_date', 'due_date', 'sort_order']);

        // Same normalisation as projects. `only()` omits absent keys so a
        // partial update still leaves untouched fields alone - but any date
        // that IS present must be pinned to a calendar day before it reaches
        // the `date` column.
        foreach (['start_date', 'due_date'] as $field) {
            if (array_key_exists($field, $payload)) {
                $payload[$field] = $this->dateOnly($payload[$field]);
            }
        }

        return $payload;
    }

    private function workstream(int $projectId, int $id)
    {
        return DB::table('task_management_workstreams')->where(['project_id' => $projectId, 'id' => $id])->first();
    }
}
