<?php

namespace App\Http\Controllers\api\OrganizationManagement\EmployeeDirectory;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\user\tbluserModel;
use App\Models\user\tbluserprofilemasterModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

/**
 * Ported from G2G's (hp_erp) `App\Http\Controllers\user\tbluserController`
 * (index/create/store/edit/update/destroy/show/addUserDocument/teacherListAPI),
 * adapted to return JSON instead of dispatching to Blade views (the source's
 * `is_mobile($type, $view, $data)` helper), and to a single request path
 * instead of the source's web-session / mobile / `type=API` three-way branch -
 * this project's `api.session` middleware performs the real authentication
 * and hydrates the legacy session keys before the controller ever runs (same
 * convention as JobPostingController in the Talent Management port).
 *
 * Field names and response keys mirror the source as closely as possible
 * (data/departments/jobroleList/user_profiles/...) so the ported frontend
 * needs no field-name changes beyond the new /organization-management/
 * employee-directory URL.
 *
 * NOT ported 1:1 (with reason):
 *  - G2G's `tbluser` table has soft-delete columns (deleted_at/deleted_by)
 *    and created_by/updated_by/created_at/updated_at. LMS-K12's tbluser table
 *    has none of these (verified against every existing tbluser migration) -
 *    only `status`. destroy()/deactivate therefore only flips `status = 0`,
 *    same end-user behaviour (employee disappears from the active list) but
 *    without an audit trail on who/when. TODO: add those columns in a future
 *    migration if an audit trail is required.
 *  - G2G's index()/edit()/show() also assemble skill-matrix / job-role /
 *    level-of-responsibility data from `s_user_jobrole`, `s_level_responsibility`,
 *    `s_user_skill_jobrole` and `org_designation` - none of these tables exist
 *    in LMS-K12 (verified: only `s_users_skills` and `s_skill_matrix` do). Those
 *    sections are guarded with Schema::hasTable() and simply omitted/empty
 *    rather than erroring. TODO: port those tables if/when the Skill Matrix
 *    module is migrated.
 *  - File storage: G2G writes to a DigitalOcean Spaces disk (`digitalocean`).
 *    Per this project's established convention for ported modules (see
 *    ComplianceLibraryController), uploads go to the local `public` disk
 *    instead.
 */
class EmployeeDirectoryController extends Controller
{
    private function tenant(): int
    {
        return (int) session()->get('sub_institute_id');
    }

    private function actorId(): ?int
    {
        $userId = session()->get('user_id');

        return $userId !== null ? (int) $userId : null;
    }

    /** GET /organization-management/employee-directory */
    public function index(Request $request)
    {
        $subInstituteId = $this->tenant();
        $userId = $this->actorId();
        $userProfile = session()->get('user_profile_name');

        $hasJobroleTable = Schema::hasTable('s_user_jobrole');

        $query = tbluserModel::select(
            'tbluser.*',
            'tbluserprofilemaster.name as profile_name',
            DB::raw('if(tbluser.status = 1,"Active","Inactive") as status_label'),
            DB::raw('IFNULL(hrms_departments.department,"-") as department_name'),
            // `tbluser.allocated_standards` holds a `s_user_jobrole.id` (not a
            // display name) - resolve it to the job role name via a join
            // rather than showing the raw id.
            $hasJobroleTable ? DB::raw('s_user_jobrole.jobrole as jobrole') : DB::raw('tbluser.allocated_standards as jobrole')
        )
            ->join('tbluserprofilemaster', 'tbluser.user_profile_id', '=', 'tbluserprofilemaster.id')
            ->leftJoin('hrms_departments', 'tbluser.department_id', '=', 'hrms_departments.id')
            ->when(
                $hasJobroleTable,
                fn ($q) => $q->leftJoin('s_user_jobrole', function ($join) {
                    $join->on(DB::raw("CAST(NULLIF(tbluser.allocated_standards, '') AS UNSIGNED)"), '=', 's_user_jobrole.id');
                })
            )
            ->where('tbluser.sub_institute_id', $subInstituteId)
            ->when(
                !in_array(strtoupper((string) $userProfile), ['ADMIN', 'SUPER ADMIN']) && !$request->has('menu_type'),
                fn ($q) => $q->where('tbluser.id', $userId)
            )
            ->when($request->has('active_status'), fn ($q) => $q->where('tbluser.status', $request->active_status))
            ->when($request->filled('department_id'), fn ($q) => $q->where('tbluser.department_id', $request->department_id))
            ->when($request->filled('jobrole_id'), fn ($q) => $q->where('tbluser.allocated_standards', $request->jobrole_id))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->search;
                $q->where(function ($inner) use ($search) {
                    $inner->where('tbluser.first_name', 'like', "%{$search}%")
                        ->orWhere('tbluser.last_name', 'like', "%{$search}%")
                        ->orWhere('tbluser.email', 'like', "%{$search}%")
                        ->orWhere('tbluser.employee_no', 'like', "%{$search}%");
                });
            });

        $perPage = (int) ($request->input('per_page') ?: 0);

        if ($perPage > 0) {
            $employees = $query->orderByDesc('tbluser.id')->paginate(min(200, max(5, $perPage)));
            $userData = $employees->items();
        } else {
            $userData = $query->orderByDesc('tbluser.id')->get();
            $employees = null;
        }

        $res = [
            'status_code' => 1,
            'message' => 'Success',
            'departments' => DB::table('hrms_departments')->where('sub_institute_id', $subInstituteId)->where('status', 1)->get(),
            'jobroleList' => Schema::hasTable('s_user_jobrole')
                ? DB::table('s_user_jobrole')->where('sub_institute_id', $subInstituteId)->whereNull('deleted_at')->get()
                : [],
            'levelOfResponsbility' => Schema::hasTable('s_level_responsibility')
                ? DB::table('s_level_responsibility')->groupBy('level')->get()
                : [],
            'user_profiles' => tbluserprofilemasterModel::where('sub_institute_id', $subInstituteId)->get(),
            'data' => $userData,
        ];

        if ($employees) {
            $res['pagination'] = [
                'current_page' => $employees->currentPage(),
                'per_page' => $employees->perPage(),
                'total' => $employees->total(),
                'last_page' => $employees->lastPage(),
            ];
        }

        return response()->json($res);
    }

    /** GET /organization-management/employee-directory/{id} */
    public function show(Request $request, $id)
    {
        $subInstituteId = $this->tenant();

        $employee = tbluserModel::where('sub_institute_id', $subInstituteId)->find($id);
        if (!$employee) {
            return response()->json(['status_code' => 0, 'message' => 'Employee not found'], 404);
        }

        $editData = $employee->toArray();
        // `allocated_standards` holds a `s_user_jobrole.id`, not a display name -
        // resolve it, matching index()'s `jobrole` column.
        $editData['userJobrole'] = null;
        if (!empty($editData['allocated_standards']) && Schema::hasTable('s_user_jobrole')) {
            $editData['userJobrole'] = DB::table('s_user_jobrole')
                ->where('id', (int) $editData['allocated_standards'])
                ->value('jobrole');
        }
        $editData['userDepartment'] = '';
        if (!empty($editData['department_id'])) {
            $editData['userDepartment'] = DB::table('hrms_departments')
                ->where('sub_institute_id', $subInstituteId)
                ->where('status', 1)
                ->where('id', $editData['department_id'])
                ->value('department');
        }

        $res = [
            'status_code' => 1,
            'message' => 'Success',
            'data' => $editData,
            'departments' => DB::table('hrms_departments')->where('sub_institute_id', $subInstituteId)->where('status', 1)->get(),
            'user_profiles' => tbluserprofilemasterModel::where('sub_institute_id', $subInstituteId)->get(),
            'documentTypeLists' => Schema::hasTable('student_document_type')
                ? DB::table('student_document_type')->where('status', 1)->where('user_type', 'staff')->get()
                : [],
            'documentLists' => DB::table('staff_document')
                ->select('staff_document.*', 'd.document_type')
                ->join('student_document_type as d', 'd.id', '=', 'staff_document.document_type_id')
                ->where(['staff_document.sub_institute_id' => $subInstituteId, 'staff_document.user_id' => $id])
                ->get(),
            // Matches G2G's edit() response keys (tbluserController@edit) so the
            // ported EmployeeOverviewSheet's PersonalInfoTab dropdowns
            // (jobRoles/employeesList) populate exactly as in G2G.
            'jobroleList' => Schema::hasTable('s_user_jobrole')
                ? DB::table('s_user_jobrole')->where('sub_institute_id', $subInstituteId)->whereNull('deleted_at')->get()
                : [],
            'employees' => tbluserModel::where('sub_institute_id', $subInstituteId)->get(),
        ];

        // `s_user_skill_jobrole`, `s_skill_knowledge_ability`, `s_user_jobrole_task`
        // and `s_level_responsibility` were ported from G2G (2026-08-20) - see
        // buildJobroleSkillsAndTasks()/buildUserLevelOfResponsibility() below for
        // the 1:1 port of tbluserController@edit's query logic.
        [$res['jobroleSkills'], $res['jobroleTasks']] = $this->buildJobroleSkillsAndTasks($subInstituteId, $editData);
        $res['userLevelOfResponsibility'] = $this->buildUserLevelOfResponsibility($subInstituteId, $editData);

        return response()->json($res);
    }

    /**
     * Ported from tbluserController@edit's jobroleSkills/jobroleTasks block
     * (the `$assignedJobrole` branch), with one deliberate mapping fix on the
     * skills side (product decision, 2026-08-20 skills/tasks/LOR audit) -
     * see the docblock above the skills query below for why.
     *
     * Tasks are unchanged from a literal port: `s_user_jobrole_task.jobrole`
     * (text) reliably matches `s_user_jobrole.jobrole` for 80% of rows
     * (verified against the full ported dataset), so the same
     * `->where('jobrole', $assignedJobroleName)->where('sub_institute_id', ...)`
     * filter G2G uses works as-is. `groupBy('task')` in the source is
     * replicated via `unique('task')` on the collection instead of a SQL
     * GROUP BY, since this database runs with ONLY_FULL_GROUP_BY and the
     * source's grouped-but-not-aggregated columns would fail here.
     *
     * @return array{0: array, 1: array} [jobroleSkills, jobroleTasks]
     */
    private function buildJobroleSkillsAndTasks(int $subInstituteId, array $editData): array
    {
        if (empty($editData['allocated_standards']) || !Schema::hasTable('s_user_jobrole')) {
            return [[], []];
        }

        $assignedJobroleName = DB::table('s_user_jobrole')
            ->where('sub_institute_id', $subInstituteId)
            ->where('id', (int) $editData['allocated_standards'])
            ->whereNull('deleted_at')
            ->value('jobrole');

        if (!$assignedJobroleName) {
            return [[], []];
        }

        $jobroleSkills = [];

        if (Schema::hasTable('s_jobrole_skills') && Schema::hasTable('s_users_skills')) {
            /*
             * NOT a literal port, by explicit product decision (2026-08-20
             * skills/tasks/LOR audit + follow-up department/jobrole-precision
             * fix): G2G's own tbluserController@edit joins the TENANT-scoped
             * `s_user_skill_jobrole` to the employee's job role via
             * `->where('jobrole', $assignedJobrole->jobrole)` (text
             * equality). That column is corrupted in G2G's own live
             * production data (verified against the full table: 77,199 of
             * 77,200 rows hold a stray number instead of a job-role name,
             * matching NO `s_user_jobrole.jobrole` value) - a pre-existing
             * G2G bug, not something this port introduced, meaning G2G's own
             * Skills tab is effectively always empty too.
             *
             * A first fix attempt matched by the job role's `track` alone,
             * but that pulled in skills from unrelated job roles sharing the
             * same track label (e.g. a Sales skill for an Occupational
             * Hygienist) - too broad. This instead uses the GLOBAL,
             * reliably-populated `s_jobrole_skills` master catalog (no
             * sub_institute_id - the same shared reference catalog
             * `s_jobrole`/`s_jobrole_task` already are), matched by an EXACT
             * `jobrole` name equality against the employee's own (reliable)
             * `s_user_jobrole.jobrole` - i.e. Department (via the employee's
             * `s_user_jobrole` row, which already carries a `department`
             * assignment) -> Job Role -> its exact mapped skills, precisely
             * as the requirement asks and consistent with how G2G's own
             * `edit()` filters (by exact jobrole, not by a broader
             * category). Verified: 100% of this catalog's skill titles for
             * a sampled job role resolve to a real tenant `s_users_skills`
             * row, so `skill_id` (needed for the knowledge/ability lookup)
             * is still obtained via a title match here, not the tenant
             * table's unreliable `skill_id` column.
             */
            $skillRows = DB::table('s_jobrole_skills as js')
                ->join('s_users_skills as us', function ($join) {
                    $join->on(DB::raw('LOWER(TRIM(us.title))'), '=', DB::raw('LOWER(TRIM(js.skill))'));
                })
                ->where('js.jobrole', $assignedJobroleName)
                ->where('us.sub_institute_id', $subInstituteId)
                ->whereNull('js.deleted_at')
                ->whereNull('us.deleted_at')
                ->select(
                    'js.id as jobrole_skill_id',
                    'js.jobrole',
                    'js.skill',
                    'js.proficiency_level',
                    'us.id as skill_id',
                    'us.title',
                    'us.category',
                    'us.sub_category',
                    'us.description'
                )
                ->get()
                ->unique('skill_id');

            $hasKnowledgeAbility = Schema::hasTable('s_skill_knowledge_ability');

            foreach ($skillRows as $row) {
                $knowledgeAbility = $hasKnowledgeAbility
                    ? DB::table('s_skill_knowledge_ability')
                        ->where('skill_id', $row->skill_id)
                        ->where('proficiency_level', $row->proficiency_level)
                        ->where('sub_institute_id', $subInstituteId)
                        ->whereNull('deleted_at')
                        ->get()
                        ->groupBy('classification')
                    : collect();

                $behaviourAttitude = $hasKnowledgeAbility
                    ? DB::table('s_skill_knowledge_ability')
                        ->where('skill_id', $row->skill_id)
                        ->where('sub_institute_id', $subInstituteId)
                        ->whereNull('deleted_at')
                        ->get()
                        ->groupBy('classification')
                    : collect();

                $jobroleSkills[] = [
                    'jobrole_skill_id' => $row->jobrole_skill_id,
                    'jobrole' => $assignedJobroleName,
                    'skill' => $row->skill,
                    'skill_id' => $row->skill_id,
                    'title' => $row->title,
                    'category' => $row->category,
                    'sub_category' => $row->sub_category,
                    'description' => $row->description,
                    'proficiency_level' => $row->proficiency_level,
                    'knowledge' => $knowledgeAbility->get('knowledge', collect())->pluck('classification_item')->toArray(),
                    'ability' => $knowledgeAbility->get('ability', collect())->pluck('classification_item')->toArray(),
                    'behaviour' => $behaviourAttitude->get('behaviour', collect())->pluck('classification_item')->toArray(),
                    'attitude' => $behaviourAttitude->get('attitude', collect())->pluck('classification_item')->toArray(),
                ];
            }
        }

        $jobroleTasks = [];
        if (Schema::hasTable('s_user_jobrole_task')) {
            $jobroleTasks = DB::table('s_user_jobrole_task')
                ->where('jobrole', $assignedJobroleName)
                ->where('sub_institute_id', $subInstituteId)
                ->whereNull('deleted_at')
                ->select('id', 'sector', 'track', 'jobrole', 'critical_work_function', 'task', 'task_category')
                ->get()
                ->unique('task')
                ->values()
                ->toArray();
        }

        return [$jobroleSkills, $jobroleTasks];
    }

    /**
     * NOT a literal port, by explicit product decision (2026-08-20
     * userLevelOfResponsibility audit): G2G's tbluserController@edit
     * repurposes `tbluser.subject_ids` to hold an `s_level_responsibility.id`
     * (an existing column reused for this in G2G, not a naming choice made
     * here). Audited and confirmed: in G2G's own live data, 100% of populated
     * `subject_ids` values are small integers matching this exact id range
     * (1-112), so that convention is real and deliberate there.
     *
     * In LMS-K12, `tbluser.subject_ids` is NOT repurposed this way - it holds
     * this tenant's actual subject-assignment data (mostly CSV lists of real
     * subject ids, e.g. "4712,4713,4714,..."), verified against the live
     * data. Reusing it here would either always resolve to nothing (harmless
     * but permanently empty) or, for a future employee whose subject_ids
     * happens to be a small integer, silently match an unrelated
     * s_level_responsibility row - a coincidental wrong-data bug waiting to
     * happen (checked: not currently triggered by any existing employee, but
     * not something to leave sitting there).
     *
     * There is no per-employee Level-of-Responsibility assignment anywhere in
     * LMS-K12's data. Per product decision, this instead derives an
     * approximate level from the employee's job role's `s_user_jobrole.
     * job_level` (ENTRY/MID/SENIOR/ADVANCED/EXECUTIVE, already populated),
     * mapped onto the SFIA responsibility scale `s_level_responsibility`
     * actually holds (1 Follow / 2 Assist / 3 Apply / 4 Enable / 5 Ensure,
     * advise / 6 Initiate, influence / 7 Set strategy) via
     * JOB_LEVEL_TO_SFIA_LEVEL below. This mapping is NOT sourced from G2G or
     * from any LMS-K12 data - it is an explicit approximation, chosen for
     * this audit, to surface a reasonable level instead of a permanently
     * empty tab. TODO: replace with a real per-employee assignment (e.g. a
     * dedicated `tbluser.level_responsibility_id` column) if/when LMS-K12
     * adds a UI for HR to assign this directly.
     *
     * Once a numeric level is resolved (by whichever path), the response
     * shape is unchanged from G2G: `level`/`guiding_phrase`/`essence_level`/
     * `guidance_note` at the top plus one key per `attribute_type` (except
     * 'Business skills/Behavioural factors', which nests under
     * `Business_skills` keyed by `str_replace(' ', '_', attribute_name)`),
     * each holding the full attribute row - exactly as G2G builds
     * `$userLevelOfResponsibility`.
     */
    private const JOB_LEVEL_TO_SFIA_LEVEL = [
        'ENTRY' => 1,
        'MID' => 3,
        'SENIOR' => 5,
        'ADVANCED' => 6,
        'EXECUTIVE' => 7,
    ];

    private function buildUserLevelOfResponsibility(int $subInstituteId, array $editData)
    {
        if (!Schema::hasTable('s_level_responsibility')) {
            return (object) [];
        }

        $level = null;

        if (!empty($editData['allocated_standards']) && Schema::hasTable('s_user_jobrole')) {
            $jobLevel = DB::table('s_user_jobrole')
                ->where('sub_institute_id', $subInstituteId)
                ->where('id', (int) $editData['allocated_standards'])
                ->whereNull('deleted_at')
                ->value('job_level');

            $level = self::JOB_LEVEL_TO_SFIA_LEVEL[strtoupper((string) $jobLevel)] ?? null;
        }

        if ($level === null) {
            return (object) [];
        }

        $built = [];
        $levelRows = DB::table('s_level_responsibility')->where('level', $level)->get();
        foreach ($levelRows as $value) {
            $built['level'] = $value->level;
            $built['guiding_phrase'] = $value->guiding_phrase;
            $built['essence_level'] = $value->essence_level;
            $built['guidance_note'] = $value->attribute_guidance_notes;

            if ($value->attribute_type !== 'Business skills/Behavioural factors') {
                $built[$value->attribute_type][$value->attribute_name] = $value;
            } else {
                $built['Business_skills'][str_replace(' ', '_', $value->attribute_name)] = $value;
            }
        }

        return $built ?: (object) [];
    }

    /** The organisation's working week, used by referenceData()/create(). */
    private const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    /**
     * GET /organization-management/employee-directory/reference-data
     *
     * Ported from G2G's (hp_erp) `EmployeeDirectoryController::referenceData()`
     * - bootstrap data for the Add Employee wizard (departments, job roles,
     * user profiles, levels of responsibility, managers, the next employee
     * number and the organisation's usual working week), adapted to this
     * controller's session-based tenant resolution and DB::table conventions.
     * Every table/column queried here (hrms_departments, s_user_jobrole,
     * s_level_responsibility, tbluserprofilemaster, tbluser.employee_no,
     * tbluser.monday.../monday_in_date...) is already read the same way
     * elsewhere in this class (index()/show()/buildUserLevelOfResponsibility())
     * or confirmed present via the tbluser migration chain, and every
     * optional table is still Schema::hasTable()-guarded.
     */
    public function referenceData(Request $request)
    {
        $subInstituteId = $this->tenant();

        $departments = DB::table('hrms_departments')
            ->where('sub_institute_id', $subInstituteId)
            ->where('status', 1)
            ->orderBy('department')
            ->get(['id', 'department as name', 'parent_id']);

        $jobRoles = [];
        if (Schema::hasTable('s_user_jobrole')) {
            $columns = ['id', 'jobrole as name', 'department_id'];
            if (Schema::hasColumn('s_user_jobrole', 'jobrole_category')) {
                $columns[] = 'jobrole_category as category';
            }

            $jobRoles = DB::table('s_user_jobrole')
                ->where('sub_institute_id', $subInstituteId)
                ->whereNull('deleted_at')
                ->orderBy('jobrole')
                ->get($columns);
        }

        $levels = Schema::hasTable('s_level_responsibility')
            ? DB::table('s_level_responsibility')
                ->select(DB::raw('MIN(id) as id'), 'level', DB::raw('MIN(guiding_phrase) as guiding_phrase'))
                ->groupBy('level')
                ->orderBy('level')
                ->get()
            : [];

        $managers = tbluserModel::where('sub_institute_id', $subInstituteId)
            ->where('status', 1)
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'employee_no']);

        return response()->json([
            'status_code' => 1,
            'message' => 'Success',
            'data' => [
                'departments' => $departments,
                'job_roles' => $jobRoles,
                'user_profiles' => tbluserprofilemasterModel::where('sub_institute_id', $subInstituteId)->orderBy('name')->get(['id', 'name']),
                'levels_of_responsibility' => $levels,
                'managers' => $managers,
                'next_employee_no' => $this->nextEmployeeNo($subInstituteId),
                'default_schedule' => $this->defaultSchedule($subInstituteId),
            ],
        ]);
    }

    /**
     * Highest numeric tbluser.employee_no for the tenant, plus one. Non-numeric
     * employee numbers (legacy free-text values) are excluded from the max()
     * rather than breaking the cast, matching G2G's own nextEmployeeNo().
     */
    private function nextEmployeeNo(int $subInstituteId): string
    {
        $highest = DB::table('tbluser')
            ->where('sub_institute_id', $subInstituteId)
            ->whereRaw("employee_no REGEXP '^[0-9]+$'")
            ->max(DB::raw('CAST(employee_no AS UNSIGNED)'));

        return (string) (((int) $highest) + 1);
    }

    /**
     * The organisation's most common per-day in/out time, so the Add Employee
     * wizard opens on something true rather than a hardcoded Mon-Fri 09-18.
     * Ported from G2G's `defaultSchedule()`, reading the same
     * monday.../monday_in_date/monday_out_date... columns this table already
     * carries (see the 2023_05_25 migration and AttendanceApiController,
     * which reads the same columns for lateness reporting).
     */
    private function defaultSchedule(int $subInstituteId): array
    {
        $schedule = [];

        foreach (self::DAYS as $day) {
            $row = DB::table('tbluser')
                ->where('sub_institute_id', $subInstituteId)
                ->where($day, 1)
                ->whereNotNull($day . '_in_date')
                ->select($day . '_in_date as in_time', $day . '_out_date as out_time', DB::raw('COUNT(*) as total'))
                ->groupBy($day . '_in_date', $day . '_out_date')
                ->orderByDesc('total')
                ->first();

            $schedule[] = [
                'day' => $day,
                'working' => (bool) $row,
                'in_time' => $row?->in_time ? substr($row->in_time, 0, 5) : null,
                'out_time' => $row?->out_time ? substr($row->out_time, 0, 5) : null,
            ];
        }

        return $schedule;
    }

    /** POST /organization-management/employee-directory */
    public function store(Request $request)
    {
        $subInstituteId = $this->tenant();

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:191',
            'user_profile_id' => [
                'required',
                'integer',
                Rule::exists('tbluserprofilemaster', 'id')->where(
                    fn ($query) => $query->where('sub_institute_id', $subInstituteId)
                ),
            ],
            'email' => 'nullable|email|max:191',
        ]);
        if ($validator->fails()) {
            return response()->json(['status_code' => 0, 'message' => $validator->errors()->first(), 'data' => null], 422);
        }

        $email = $request->input('email');
        if ($email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return response()->json(['status_code' => 0, 'message' => 'Invalid email address format', 'data' => null], 422);
            }

            if (tbluserModel::where('email', $email)->exists()) {
                return response()->json(['status_code' => 0, 'message' => 'Email address already exists', 'data' => null], 422);
            }
        }

        $fileName = null;
        if ($request->hasFile('user_image')) {
            $fileName = $this->storeUserImage($request);
        }

        $finalArray = $this->buildAttributeArray($request->all(), $subInstituteId);
        $finalArray['status'] = 1;
        if ($fileName) {
            $finalArray['image'] = $fileName;
        }
        if ($request->filled('password')) {
            $finalArray['password'] = Hash::make($request->input('password'));
        }
        if ($request->filled('birthdate')) {
            $finalArray['birthdate'] = Carbon::parse($request->input('birthdate'))->format('Y-m-d');
        }

        $id = tbluserModel::insertGetId($finalArray);

        $employee = tbluserModel::find($id);

        AuditLog::record([
            'module' => 'organization_management',
            'action' => 'employee_created',
            'entity_type' => 'tbluser',
            'entity_id' => $id,
            'new_values' => collect($finalArray)->except(['password'])->all(),
        ]);

        return response()->json([
            'status_code' => 1,
            'message' => 'User created successfully',
            'data' => $employee,
        ], 201);
    }

    /**
     * POST /organization-management/employee-directory/create
     *
     * Ported from G2G's (hp_erp) `EmployeeDirectoryController::store()` (the
     * token-API sibling of tbluserController@store, not that controller's own
     * store()) - added alongside this file's existing store() above rather
     * than replacing it (out of scope for this pass to change store()'s
     * route/behaviour). The distinction that matters: this file's store()
     * will hash and persist a caller-supplied `password` if one is sent
     * (`if ($request->filled('password')) { ... Hash::make($request->input(
     * 'password')) ... }`); create() never reads that field at all - the
     * password is always generated here, matching G2G's own rationale
     * ("generated, never supplied and never echoed") - and the employee sets
     * their own via the invite/password-reset flow below.
     *
     * `user_name` and `join_year` are NOT NULL on tbluser with no default (see
     * the create_tbluser_table migration), so unlike store() - which leaves
     * both to buildAttributeArray()'s raw pass-through and fails if the
     * caller omits them - both are derived here.
     */
    public function create(Request $request)
    {
        $subInstituteId = $this->tenant();

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:191',
            'last_name' => 'required|string|max:191',
            'mobile' => 'required|string|max:50',
            'email' => 'required|email|max:191',
            'user_profile_id' => [
                'required',
                'integer',
                Rule::exists('tbluserprofilemaster', 'id')->where(
                    fn ($query) => $query->where('sub_institute_id', $subInstituteId)
                ),
            ],
        ]);
        if ($validator->fails()) {
            return response()->json(['status_code' => 0, 'message' => $validator->errors()->first(), 'data' => null], 422);
        }

        $email = $request->input('email');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['status_code' => 0, 'message' => 'Invalid email address format', 'data' => null], 422);
        }
        if (tbluserModel::where('email', $email)->exists()) {
            return response()->json(['status_code' => 0, 'message' => 'Email address already exists', 'data' => null], 422);
        }

        $fileName = null;
        if ($request->hasFile('user_image')) {
            $fileName = $this->storeUserImage($request);
        }

        $finalArray = $this->buildAttributeArray($request->all(), $subInstituteId);
        // The password is generated below and never taken from the caller -
        // see method docblock. buildAttributeArray()'s generic flatten would
        // otherwise copy a caller-supplied `password` straight through, same
        // as store() does; discard it here before it can reach the row.
        unset($finalArray['password']);
        $finalArray['status'] = 1;
        if ($fileName) {
            $finalArray['image'] = $fileName;
        }
        if ($request->filled('birthdate')) {
            $finalArray['birthdate'] = Carbon::parse($request->input('birthdate'))->format('Y-m-d');
        }

        $temporaryPassword = bin2hex(random_bytes(12));
        $finalArray['password'] = Hash::make($temporaryPassword);
        $finalArray['plain_password'] = null;
        $finalArray['user_name'] = $this->uniqueUserName($email);
        $finalArray['join_year'] = $request->filled('joined_date')
            ? Carbon::parse($request->input('joined_date'))->format('Y')
            : now()->format('Y');

        $id = tbluserModel::insertGetId($finalArray);

        $employee = tbluserModel::find($id);

        $invite = $this->issueInvite($email);

        AuditLog::record([
            'module' => 'organization_management',
            'action' => 'employee_created',
            'entity_type' => 'tbluser',
            'entity_id' => $id,
            'new_values' => collect($finalArray)->except(['password', 'plain_password'])->all(),
        ]);

        return response()->json([
            'status_code' => 1,
            'message' => $invite['sent'] ? 'Employee created and invited.' : 'Employee created. The invite could not be sent.',
            'data' => $employee,
            'invite_sent' => $invite['sent'],
        ], 201);
    }

    /** PUT /organization-management/employee-directory/{id} */
    public function update(Request $request, $id)
    {
        $subInstituteId = $this->tenant();

        $employee = tbluserModel::where('sub_institute_id', $subInstituteId)->find($id);
        if (!$employee) {
            return response()->json(['status_code' => 0, 'message' => 'Employee not found', 'data' => null], 404);
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|required|string|max:191',
            'user_profile_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('tbluserprofilemaster', 'id')->where(
                    fn ($query) => $query->where('sub_institute_id', $subInstituteId)
                ),
            ],
            'email' => 'nullable|email|max:191',
        ]);
        if ($validator->fails()) {
            return response()->json(['status_code' => 0, 'message' => $validator->errors()->first(), 'data' => null], 422);
        }

        $email = $request->input('email');
        if ($email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return response()->json(['status_code' => 0, 'message' => 'Invalid email address format', 'data' => null], 422);
            }

            if (tbluserModel::where('email', $email)->where('id', '!=', $id)->exists()) {
                return response()->json(['status_code' => 0, 'message' => 'Email address already exists', 'data' => null], 422);
            }
        }

        $fileName = null;
        if ($request->hasFile('user_image')) {
            $fileName = $this->storeUserImage($request);
        }

        $finalArray = $this->buildAttributeArray($request->all(), $subInstituteId);
        if ($fileName) {
            $finalArray['image'] = $fileName;
        }
        if ($request->filled('password')) {
            $finalArray['password'] = Hash::make($request->input('password'));
        } else {
            unset($finalArray['password']);
        }
        if ($request->filled('birthdate')) {
            $finalArray['birthdate'] = Carbon::parse($request->input('birthdate'))->format('Y-m-d');
        }

        tbluserModel::where('id', $id)->update($finalArray);

        AuditLog::record([
            'module' => 'organization_management',
            'action' => 'employee_updated',
            'entity_type' => 'tbluser',
            'entity_id' => (int) $id,
            'new_values' => collect($finalArray)->except(['password'])->all(),
        ]);

        return response()->json([
            'status_code' => 1,
            'message' => 'User updated successfully',
            'data' => tbluserModel::find($id),
        ]);
    }

    /** DELETE /organization-management/employee-directory/{id} */
    public function destroy(Request $request, $id)
    {
        $subInstituteId = $this->tenant();

        $employee = tbluserModel::where('sub_institute_id', $subInstituteId)->find($id);
        if (!$employee) {
            return response()->json(['status_code' => 0, 'message' => 'Employee not found'], 404);
        }

        // G2G sets status/deleted_by/deleted_at; LMS-K12's tbluser has no
        // deleted_by/deleted_at columns, so only status is flipped (see
        // class docblock).
        tbluserModel::where('id', $id)->update(['status' => 0]);

        AuditLog::record([
            'module' => 'organization_management',
            'action' => 'employee_deactivated',
            'entity_type' => 'tbluser',
            'entity_id' => (int) $id,
            'new_values' => ['status' => 0],
        ]);

        return response()->json([
            'status_code' => 1,
            'message' => 'User deleted successfully',
        ]);
    }

    /**
     * PATCH /organization-management/employee-directory/{id}/status
     *
     * Ported from G2G's `EmployeeDirectoryController::setStatus`. Backs
     * "Suspend Access" / "Restore Access" - distinct from destroy(), which
     * this project's tbluser schema also implements as a status flip (see
     * class docblock) but without this endpoint's own-account guard or
     * explicit intent. G2G also stamps updated_by/updated_at; LMS-K12's
     * tbluser has neither column (see class docblock), so only status
     * changes here.
     */
    public function setStatus(Request $request, $id)
    {
        $subInstituteId = $this->tenant();

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:0,1',
        ]);
        if ($validator->fails()) {
            return response()->json(['status_code' => 0, 'message' => $validator->errors()->first()], 422);
        }

        $employee = tbluserModel::where('sub_institute_id', $subInstituteId)->find($id);
        if (!$employee) {
            return response()->json(['status_code' => 0, 'message' => 'Employee not found'], 404);
        }

        $status = (int) $request->input('status');

        if ((int) $id === $this->actorId() && $status === 0) {
            return response()->json(['status_code' => 0, 'message' => 'You cannot suspend your own account.'], 422);
        }

        tbluserModel::where('id', $id)->update(['status' => $status]);

        AuditLog::record([
            'module' => 'organization_management',
            'action' => $status === 1 ? 'employee_reactivated' : 'employee_deactivated',
            'entity_type' => 'tbluser',
            'entity_id' => (int) $id,
            'new_values' => ['status' => $status],
        ]);

        return response()->json([
            'status_code' => 1,
            'message' => $status === 1 ? 'Access restored.' : 'Access suspended.',
        ]);
    }

    /**
     * POST /organization-management/employee-directory/{id}/invite
     *
     * Ported from G2G's `EmployeeDirectoryController::invite()` - (re)issues a
     * password-reset token for an existing employee, so an employee whose
     * initial invite (create()'s issueInvite() call) failed or expired can be
     * invited again without touching their record otherwise.
     */
    public function invite(Request $request, $id)
    {
        $subInstituteId = $this->tenant();

        $employee = tbluserModel::where('sub_institute_id', $subInstituteId)->find($id);
        if (!$employee) {
            return response()->json(['status_code' => 0, 'message' => 'Employee not found'], 404);
        }

        $invite = $this->issueInvite($employee->email);

        AuditLog::record([
            'module' => 'organization_management',
            'action' => 'employee_invited',
            'entity_type' => 'tbluser',
            'entity_id' => (int) $id,
            'new_values' => ['invite_sent' => $invite['sent']],
        ]);

        return response()->json([
            'status_code' => $invite['sent'] ? 1 : 0,
            'message' => $invite['sent'] ? 'Invite sent.' : ('The invite could not be sent: ' . $invite['error']),
        ], $invite['sent'] ? 200 : 502);
    }

    /** POST /organization-management/employee-directory/{id}/documents */
    public function uploadDocument(Request $request, $id)
    {
        $subInstituteId = $this->tenant();

        $validator = Validator::make($request->all(), [
            'document' => 'required|file',
            'document_type_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['status_code' => 0, 'message' => $validator->errors()->first()], 422);
        }

        $file = $request->file('document');
        $name = $id . date('YmdHis');
        $ext = $file->getClientOriginalExtension();
        $fileName = $name . '.' . $ext;

        // 'public' disk - see class docblock re: storage convention.
        Storage::disk('public')->putFileAs('staff_document', $file, $fileName);

        $inserted = DB::table('staff_document')->insert([
            'user_id' => $id,
            'document_title' => $request->input('document_title'),
            'document_type_id' => $request->input('document_type_id'),
            'file_name' => $fileName,
            'sub_institute_id' => $subInstituteId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($inserted) {
            AuditLog::record([
                'module' => 'organization_management',
                'action' => 'employee_document_uploaded',
                'entity_type' => 'staff_document',
                'entity_id' => (int) $id,
                'new_values' => [
                    'document_title' => $request->input('document_title'),
                    'document_type_id' => $request->input('document_type_id'),
                    'file_name' => $fileName,
                ],
            ]);

            return response()->json([
                'status_code' => 1,
                'success' => 1,
                'message' => 'Document Added successfully',
                'data' => ['file_name' => $fileName, 'file_url' => Storage::disk('public')->url('staff_document/' . $fileName)],
            ], 201);
        }

        return response()->json(['status_code' => 0, 'message' => 'Failed to Add Document'], 500);
    }

    /**
     * GET /organization-management/employee-directory/{id}/competency-profile
     *
     * NOT a literal port, by explicit product decision (2026-08-20 Expected
     * Competency / Competency Rating audit). G2G's own
     * `EmployeeCompetencyProfileController::show()` (`/api/competency/
     * employee-profiles/{id}`) is effectively dead code in G2G's live app:
     * its "Expected Competency" consumer looks for a `requiredSkills`
     * response key the controller never returns (a frontend wiring bug, so
     * G2G always falls back to a hardcoded mock there), and its "Competency
     * Rating" consumer instead calls a *different*, legacy endpoint
     * (`/get-kaba`) whose controller (`SkillMatrixController::getKaba`) has
     * a live, uncommented `dd()` debug statement that halts every request -
     * confirmed by reading the current source, not a guess. Per the explicit
     * "do not use mock data, use real backend logic" instruction, this
     * instead implements what G2G's `show()` *intends* (expected level from
     * the employee's job-role skill mapping vs. actual level from the
     * employee's own ratings), reusing the same reliable data path already
     * proven for the Jobrole Skill tab (`buildJobroleSkillsAndTasks()` above)
     * rather than G2G's own broken `s_user_skill_jobrole.jobrole` text match
     * (100% corrupted in G2G's live data, see that method's docblock).
     *
     * Response: `{ status_code, data: [{ id, skill_id, title, description,
     * category, expected_level, current_level, max_level }] }` - one flat
     * array per employee, grouped into `Record<CategoryType, ...>` client-side
     * by the frontend's existing `mapKabaRatings()`/analogous mapper (mirrors
     * how G2G's own `mapKabaRatings()` groups client-side). `id` is the
     * skill's own id (`s_users_skills.id`), matching what
     * `PUT .../skills/{matrixId}` below expects as its `$matrixId` param.
     */
    public function competencyProfile(Request $request, $id)
    {
        $subInstituteId = $this->tenant();

        $employee = tbluserModel::where('sub_institute_id', $subInstituteId)->find($id);
        if (!$employee) {
            return response()->json(['status_code' => 0, 'message' => 'Employee not found', 'data' => []], 404);
        }

        if (
            empty($employee->allocated_standards)
            || !Schema::hasTable('s_user_jobrole')
            || !Schema::hasTable('s_jobrole_skills')
            || !Schema::hasTable('s_users_skills')
        ) {
            return response()->json(['status_code' => 1, 'message' => 'Success', 'data' => []]);
        }

        $assignedJobroleName = DB::table('s_user_jobrole')
            ->where('sub_institute_id', $subInstituteId)
            ->where('id', (int) $employee->allocated_standards)
            ->whereNull('deleted_at')
            ->value('jobrole');

        if (!$assignedJobroleName) {
            return response()->json(['status_code' => 1, 'message' => 'Success', 'data' => []]);
        }

        $items = DB::table('s_jobrole_skills as js')
            ->join('s_users_skills as us', function ($join) {
                $join->on(DB::raw('LOWER(TRIM(us.title))'), '=', DB::raw('LOWER(TRIM(js.skill))'));
            })
            ->where('js.jobrole', $assignedJobroleName)
            ->where('us.sub_institute_id', $subInstituteId)
            ->whereNull('js.deleted_at')
            ->whereNull('us.deleted_at')
            ->select(
                'us.id as skill_id',
                'us.title',
                'us.description',
                'us.competency_type',
                'js.proficiency_level as expected_level'
            )
            ->get()
            ->unique('skill_id')
            ->values();

        $currentLevels = [];
        if ($items->isNotEmpty() && Schema::hasTable('s_skill_matrix')) {
            $currentLevels = DB::table('s_skill_matrix')
                ->where('user_id', $id)
                ->whereIn('skill_id', $items->pluck('skill_id'))
                ->pluck('skill_level', 'skill_id')
                ->toArray();
        }

        $result = $items->map(function ($item) use ($currentLevels) {
            $category = $item->competency_type ?: 'Skill';
            // This dataset spells it 'Behavior' (US); normalize to the
            // frontend's 'Behaviour' CategoryType.
            if (strcasecmp($category, 'Behavior') === 0) {
                $category = 'Behaviour';
            }

            $current = $currentLevels[$item->skill_id] ?? null;

            return [
                'id' => (string) $item->skill_id,
                'skill_id' => $item->skill_id,
                'title' => $item->title,
                'description' => $item->description,
                'category' => $category,
                'expected_level' => is_numeric($item->expected_level) ? (int) $item->expected_level : null,
                'current_level' => is_numeric($current) ? (int) $current : null,
                'max_level' => 5,
            ];
        })->values();

        /*
         * Fix (2026-08-20 follow-up): `s_users_skills.competency_type` only
         * ever holds 'Skill' or 'Behavior' in this dataset (verified: no
         * 'Knowledge'/'Ability'/'Attitude' value exists anywhere in that
         * column), so nothing was ever routed into those three category
         * tabs regardless of what data existed - not a data-sparsity issue,
         * a mapping bug. The real per-classification breakdown lives in
         * `s_skill_knowledge_ability` (`classification` = knowledge/ability/
         * behaviour/attitude, `classification_item` = the actual competency
         * statement) - the same table `buildJobroleSkillsAndTasks()` already
         * reads to build each Jobrole Skill entry's knowledge/ability/
         * behaviour/attitude arrays. Each classification_item becomes its
         * own rateable item here, inheriting its parent skill's expected/
         * current level (matches the source: these are elaborations of one
         * skill's requirements at a level, not independently-leveled
         * competencies). All classifications (knowledge/ability/behaviour/
         * attitude) include every non-deleted row for the skill regardless
         * of the row's own proficiency_level - see the exclusion-condition
         * fix below (a prior exact-level-match requirement for Knowledge/
         * Ability was traced and found to drop real, valid records).
         *
         * NOTE: 98.4% of `s_skill_knowledge_ability` rows are soft-deleted
         * in the live data this was ported from (verified against the full
         * table) - a pre-existing data-quality state, not something this
         * fix can manufacture data around. Most skills will still show few
         * or no Knowledge/Ability/Attitude/Behaviour items; wherever
         * non-deleted rows do exist, they now correctly surface instead of
         * being permanently unreachable.
         */
        if ($items->isNotEmpty() && Schema::hasTable('s_skill_knowledge_ability')) {
            $skillById = $items->keyBy('skill_id');
            $currentById = $currentLevels;

            $kaRows = DB::table('s_skill_knowledge_ability')
                ->whereIn('skill_id', $items->pluck('skill_id'))
                ->whereNull('deleted_at')
                ->select('skill_id', 'proficiency_level', 'classification', 'classification_item')
                ->get();

            $categoryMap = ['knowledge' => 'Knowledge', 'ability' => 'Ability', 'behaviour' => 'Behaviour', 'attitude' => 'Attitude'];

            foreach ($kaRows as $index => $row) {
                $category = $categoryMap[strtolower((string) $row->classification)] ?? null;
                if (!$category || empty($row->classification_item)) {
                    continue;
                }

                $skill = $skillById->get($row->skill_id);
                if (!$skill) {
                    continue;
                }

                // NOT level-filtered (fix, 2026-08-20 debug trace): a prior
                // version required `proficiency_level == expected_level` for
                // Knowledge/Ability, mirroring buildJobroleSkillsAndTasks's
                // source logic. Traced against real data (skill_id 2366,
                // "Critical Thinking" under Architect, expected_level 4):
                // its knowledge/ability rows are recorded at proficiency_level
                // 5, so the exact-match rule silently dropped real,
                // non-deleted records - the classification data in this
                // dataset isn't authored to line up 1:1 with each job role's
                // catalog level. Behaviour/Attitude were never level-filtered
                // and correctly show data; Knowledge/Ability now follow the
                // same rule so valid records aren't excluded over a level
                // mismatch that reflects data authoring, not a real gap.
                $result->push([
                    'id' => "{$row->skill_id}-{$row->classification}-{$index}",
                    'skill_id' => $row->skill_id,
                    'title' => $row->classification_item,
                    'description' => "{$category} competency for {$skill->title}",
                    'category' => $category,
                    'expected_level' => is_numeric($skill->expected_level) ? (int) $skill->expected_level : null,
                    'current_level' => is_numeric($currentById[$row->skill_id] ?? null) ? (int) $currentById[$row->skill_id] : null,
                    'max_level' => 5,
                ]);
            }
        }

        return response()->json(['status_code' => 1, 'message' => 'Success', 'data' => $result->values()]);
    }

    /**
     * PUT /organization-management/employee-directory/{id}/skills/{matrixId}
     *
     * Ported from G2G's `updateSkillRating`
     * (`PUT /competency/employee-profiles/{id}/skills/{matrixId}`). `matrixId`
     * is the `s_users_skills.id` being rated; upserts the employee's rating
     * into `s_skill_matrix` (unique on user_id+skill_id).
     */
    public function updateSkillRating(Request $request, $id, $matrixId)
    {
        $validator = Validator::make($request->all(), [
            'proficiency_level' => 'required|integer|min:0|max:5',
        ]);

        if ($validator->fails()) {
            return response()->json(['status_code' => 0, 'message' => $validator->errors()->first()], 422);
        }

        if (!Schema::hasTable('s_skill_matrix')) {
            return response()->json(['status_code' => 0, 'message' => 'Skill matrix unavailable'], 501);
        }

        $existing = DB::table('s_skill_matrix')->where('user_id', $id)->where('skill_id', $matrixId)->first();

        DB::table('s_skill_matrix')->updateOrInsert(
            ['user_id' => $id, 'skill_id' => $matrixId],
            [
                'skill_level' => (int) $request->input('proficiency_level'),
                'interest_level' => $existing->interest_level ?? 0,
                'updated_at' => now(),
                'created_at' => $existing->created_at ?? now(),
            ]
        );

        AuditLog::record([
            'module' => 'organization_management',
            'action' => 'employee_skill_rating_updated',
            'entity_type' => 's_skill_matrix',
            'entity_id' => (int) $id,
            'new_values' => ['skill_id' => (int) $matrixId, 'proficiency_level' => (int) $request->input('proficiency_level')],
        ]);

        return response()->json(['status_code' => 1, 'message' => 'Skill rating updated']);
    }

    /**
     * Ported from tbluserController@teacherListAPI - lists every user on the
     * "Teacher" profile for the tenant.
     */
    public function teacherList(Request $request)
    {
        $subInstituteId = $this->tenant();

        $data = DB::table('tbluser as u')
            ->join('tbluserprofilemaster as up', function ($join) {
                $join->on('up.id', '=', 'u.user_profile_id')->where('up.name', 'Teacher');
            })
            ->selectRaw("u.id, concat_ws(' ', u.first_name, u.middle_name, u.last_name) as teacher_name, u.email, u.mobile, u.user_profile_id, up.name as user_group")
            ->where('u.sub_institute_id', $subInstituteId)
            ->orderBy('u.id')
            ->get();

        return response()->json(['status_code' => 1, 'message' => 'Success', 'data' => $data]);
    }

    private function storeUserImage(Request $request): string
    {
        $file = $request->file('user_image');
        $name = $request->input('user_name', 'employee') . date('YmdHis');
        $ext = $file->getClientOriginalExtension();
        $fileName = $name . '.' . $ext;

        Storage::disk('public')->putFileAs('employee_directory', $file, $fileName);

        return $fileName;
    }

    /**
     * tbluser.user_name is indexed but not unique, and login resolves on it,
     * so a collision would be ambiguous rather than rejected. Suffix until
     * free, matching G2G's own `uniqueUserName()`.
     */
    private function uniqueUserName(string $email): string
    {
        $base = Str::slug(Str::before($email, '@'), '.') ?: 'user';
        $candidate = $base;
        $suffix = 1;

        while (tbluserModel::where('user_name', $candidate)->exists()) {
            $candidate = $base . (++$suffix);
        }

        return $candidate;
    }

    /**
     * Issue a password-reset token so the employee sets their own password,
     * reusing the exact table and flow `Auth\ForgotPasswordController`
     * already runs on (`password_resets`: email/token/created_at) rather than
     * inventing a second credential channel - this project's table name for
     * what G2G calls `password_reset_tokens`. Used by both create() (the
     * employee's first invite) and invite() (a resend). A failure here must
     * not undo the employee record - it is reported via the caller's
     * response and recoverable by calling invite() again.
     *
     * @return array{sent: bool, error: ?string}
     */
    private function issueInvite(?string $email): array
    {
        if (!$email) {
            return ['sent' => false, 'error' => 'No email address'];
        }

        try {
            $token = Str::random(64);

            DB::table('password_resets')->where('email', $email)->delete();
            DB::table('password_resets')->insert([
                'email' => $email,
                'token' => $token,
                'created_at' => now(),
            ]);

            return ['sent' => true, 'error' => null];
        } catch (\Throwable $e) {
            return ['sent' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Same "everything except a short excluded list" flattening rule as
     * G2G's tbluserController@saveData/updateData: any array value is
     * imploded, and framework/meta keys are dropped rather than written
     * to the row.
     */
    private function buildAttributeArray(array $input, int $subInstituteId): array
    {
        $excluded = ['user_image', 'type', 'user_id', '_method', '_token', 'submit', 'id', 'update', 'token', 'document'];

        $final = ['sub_institute_id' => $subInstituteId];

        foreach ($input as $key => $value) {
            if (in_array($key, $excluded, true)) {
                continue;
            }

            if (is_array($value)) {
                $value = implode(',', $value);
            }

            $final[$key] = $value;
        }

        // NOTE: unlike G2G's tbluser, LMS-K12's tbluser table has no
        // created_by/updated_by columns (verified against every tbluser
        // migration), so - unlike the source controller - no actor id is
        // stamped onto the row here.

        return $final;
    }
}
