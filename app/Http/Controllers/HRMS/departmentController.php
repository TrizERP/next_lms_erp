<?php

namespace App\Http\Controllers\HRMS;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use DB;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Support\Facades\Validator;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Schema;

class departmentController extends Controller
{
    use GetsJwtToken;

    /**
     * Every table in this schema carrying a `department_id` column, per a
     * direct `information_schema` query against this database (verified
     * 2026-08-25) - not copied from hp_erp's own dependent-table list, which
     * names tables that do not exist here and misses several that do (see
     * the Organizational Management audit's conflict note). Backup tables
     * (`tbluser_backup_20260819`) are deliberately excluded: reassigning or
     * counting against a backup is never the intent of an impact check or a
     * merge. Every table is still guarded with Schema::hasTable()/hasColumn()
     * before use, so a table dropped after this list was written is skipped
     * rather than erroring.
     */
    private const DEPARTMENT_ID_TABLES = [
        'ai_sops', 'hrms_emp_leaves', 'hrms_leave_allocation',
        'inventory_requisition_details', 'org_disciplinary_library',
        's_competency_career_paths', 's_competency_certifications',
        's_competency_certification_requirements',
        's_competency_development_plans', 's_competency_frameworks',
        's_competency_mapping_reviews', 's_mobility_jobs',
        's_performance_appraisals', 's_performance_bonus_awards',
        's_performance_calibration_sessions',
        's_performance_compensation_revisions', 's_performance_goals',
        's_performance_reviews', 's_users_skills', 's_user_jobrole',
        'talent_job_postings', 'talent_offboarding_cases',
        'talent_onboarding_journeys', 'task_management_projects',
        'tbluser', 'tbluser_shift_records',
    ];

    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->input('sub_institute_id', session()->get('sub_institute_id'));

        $departmentData = DB::table('hrms_departments as hdm')
        ->LeftJoin('tbluser as u',function($query) use($sub_institute_id){
            $query->on('u.department_id','=','hdm.id')->where('u.sub_institute_id',$sub_institute_id);
        })
        ->select('hdm.*',DB::raw('(CASE WHEN hdm.parent_id=0 THEN "parent" ELSE "child" END) as depType'),
        DB::raw('COUNT(u.id) as total_emp'))
        ->where('hdm.status',1)
        ->where('hdm.sub_institute_id',$sub_institute_id)
        ->orderBy('hdm.sub_institute_id','DESC')
        ->orderBy('hdm.id','DESC')
        ->groupBy('hdm.id')
        ->get()->toArray();

        $parentData=$childData=[];
        foreach ($departmentData as $key => $value) {
            if($value->parent_id !=0){
                $childData[$value->parent_id][] = $value;
            }else{
                $parentData[] = $value;
            }
        }
        // echo "<pre>";print_r($childData);exit;
        $res['departmentData'] = $parentData;
        $res['subDepartmentData'] = $childData;
        return is_mobile($type, "HRMS.department.index", $res, "view");
    }

    public function create(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->input('sub_institute_id', session()->get('sub_institute_id'));
        $res = session()->get('data');

        $res['departmentList'] = DB::table('hrms_departments')->where('status',1)->where('parent_id',0)->where('sub_institute_id',$sub_institute_id)->get()->toArray();

        $res['userDepartmentList'] = DB::table('hrms_departments as sub')
                ->select(
                    'sub.*',
                    DB::Raw('IFNULL((select count(DISTINCT id) from hrms_departments where parent_id = sub.id),"-") as sub_dep'),
                    DB::Raw('IFNULL((select count(DISTINCT id) from tbluser where department_id = sub.id and sub_institute_id='.$sub_institute_id.' and status=1),"-") as total_emp'),
                    DB::Raw('IFNULL((select group_concat(DISTINCT id) from tbluser where department_id = sub.id and sub_institute_id='.$sub_institute_id.' and status=1),"-") as emp_ids')
                )
                ->where('sub.status', 1)
                ->where('sub.parent_id', '=', 0)
                ->where('sub.sub_institute_id', $sub_institute_id)
                ->groupBy('sub.id')
                ->get()
                ->toArray();
        // echo "<pre>";print_r($res['userDepartmentList']);exit;
        $res['SubDepartmentList'] = DB::table('hrms_departments as sub')
        ->select(
            'sub.*',
            DB::raw('(CASE WHEN sub.parent_id!=0 THEN (SELECT department FROM hrms_departments WHERE id = sub.parent_id) ELSE "-" END) as mainDepartment'),
            DB::raw('(CASE WHEN sub.parent_id=0 THEN (SELECT count(id) FROM hrms_departments WHERE parent_id = sub.id group by parent_id) ELSE "0" END) total_subDep'),
            DB::Raw('IFNULL((select count(DISTINCT id) from tbluser where department_id = sub.id and sub_institute_id='.$sub_institute_id.' and status=1),"-") as total_emp'),
            DB::Raw('IFNULL((select group_concat(DISTINCT id) from tbluser where department_id = sub.id and sub_institute_id='.$sub_institute_id.' and status=1),"-") as emp_ids')
        )
        ->where('sub.status', 1)
        // ->where('sub.parent_id', '!=', 0)
        ->where('sub.sub_institute_id', $sub_institute_id)
        ->groupBy('sub.id')
        ->get()
        ->toArray();

        $res['employeesList'] =DB::table('tbluser as u')
        ->join('tbluserprofilemaster as upm','upm.id','=','u.user_profile_id')
        ->leftJoin('hrms_departments as dep','u.department_id', '=', 'dep.id')
        ->select(
            'u.id as emp_id','u.employee_no','u.gender','u.image',DB::Raw('CONCAT_WS(" ",COALESCE(u.first_name),COALESCE(u.middle_name),COALESCE(u.last_name)) as emp_name'),
            'upm.name as user_role',DB::Raw('IFNULL(dep.department,"-") as emp_department')
        )
        ->where('u.status', 1)
        ->where('u.sub_institute_id', $sub_institute_id)
        ->groupBy('u.id')
        ->get()
        ->toArray();
    
        // echo "<pre>";print_r($res['SubDepartmentList']);exit;
        return is_mobile($type, "HRMS.department.add", $res, "view");
    }

    public function store(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');

        $validator = Validator::make($request->all(), [
            'department_name' => 'required|string',
        ]);

        if ($validator->fails()) {
            $res['status_code'] = 0;
            $res['message'] = $validator->messages()->first();
            return is_mobile($type, "add_department.create", $res);
        }

        $department_name = $request->department_name;
        $roles_responsibility = $request->roles_responsibility;
        $is_calculated = $request->is_calculated;
        $task = $request->tasks;
        $i=$parent_id=0;

        if($request->has('parentDiv') && $request->parentDiv!=''){
            $parent_id = $request->parentDiv;
            $check = DB::table('hrms_departments')->where(['department'=>$department_name,'parent_id'=>$parent_id])->get()->toArray();
        }else{
            $check = DB::table('hrms_departments')->where(['department'=>$department_name,'parent_id'=>$parent_id])->get()->toArray();
        }

        if(empty($check)){
            $i=1;
            $insertData = [
                'department'=>$department_name,
                'parent_id'=>$parent_id,
                'tasks'=>$task,
                'roles_responsibility'=>$roles_responsibility,
                'status'=>1,
                'is_calculated'=>$is_calculated,
                'sub_institute_id'=>$sub_institute_id
            ];
            $insertId = DB::table('hrms_departments')->insertGetId($insertData);

            AuditLog::record([
                'module' => 'hrms',
                'action' => 'department_added',
                'entity_type' => 'department',
                'entity_id' => $insertId,
                'new_values' => $insertData,
            ]);
        }
        if($i!=0){
            $res['status_code']=1;
            $res['message']="Add Successfully!!";
        }else{
            $res['status_code']=0;
            $res['message']="Failed to Add!!";
        }
        return is_mobile($type, "add_department.create", $res);
    }

    public function Update(Request $request,$id)
    {
        // echo "<pre>";print_r($request->all());exit;
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');

        $validator = Validator::make($request->all(), [
            'department_name' => 'required|string',
        ]);

        if ($validator->fails()) {
            $res['status_code'] = 0;
            $res['message'] = $validator->messages()->first();
            return is_mobile($type, "add_department.create", $res);
        }

        $department_name = $request->department_name;
        $roles_responsibility = $request->roles_responsibility;
        $is_calculated = $request->is_calculated;
        $task = $request->tasks;
        $parent_id=0;

        if($request->has('parentDiv') && $request->parentDiv!=''){
            $parent_id = $request->parentDiv;
        }

        $updateData = [
                'department'=>$department_name,
                'parent_id'=>$parent_id,
                'tasks'=>$task,
                'roles_responsibility'=>$roles_responsibility,
                'status'=>1,
                'is_calculated'=>$is_calculated,
                'sub_institute_id'=>$sub_institute_id
            ];
        $update = DB::table('hrms_departments')->where('id',$id)->Update($updateData);

        if($update){
            $res['status_code']=1;
            $res['message']="Updated Successfully!!";

            AuditLog::record([
                'module' => 'hrms',
                'action' => 'department_updated',
                'entity_type' => 'department',
                'entity_id' => $id,
                'new_values' => $updateData,
            ]);
        }else{
            $res['status_code']=0;
            $res['message']="Failed to Update!!";
        }
        return is_mobile($type, "add_department.create", $res);
    }

    public function destroy(Request $request,$id){
        
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');

        $delete = DB::table('hrms_departments')->where('id',$id)->delete();

        if($delete){
            $res['status_code']=1;
            $res['message']="Deleted Successfully!!";

            AuditLog::record([
                'module' => 'hrms',
                'action' => 'department_deleted',
                'entity_type' => 'department',
                'entity_id' => $id,
            ]);
        }else{
            $res['status_code']=0;
            $res['message']="Failed to Delete!!";
        }
    }

    public function departmentEmpLists(Request $request){
        $sub_institute_id = session()->get('sub_institute_id');
        $emp_ids = explode(',',$request->emp_ids);
         return DB::table('tbluser')
         ->selectRaw('CONCAT_WS(" ",COALESCE(first_name,"-"),COALESCE(middle_name,"-"),COALESCE(last_name,"-")) as name,mobile')
        ->whereIn('id',$emp_ids)
        ->get()
        ->toArray();
    }

    public function subDepartmentList(Request $request){
        $sub_institute_id = $request->input('sub_institute_id', session()->get('sub_institute_id'));
        $depIds = $request->depId;

         return DB::table('hrms_departments')
        ->whereRaw('parent_id in ('.$depIds.')')
        ->where('sub_institute_id',$sub_institute_id)
        ->groupBy('id')
        ->get()
        ->toArray();
    }

    public function departmentEmployeeList(Request $request){
        $sub_institute_id = $request->input('sub_institute_id', session()->get('sub_institute_id'));
        $depIds = $request->depId;
        $where = "(department_id in ($depIds)";
        
        if($request->has('subDepId')){
            $subDepIds = $request->subDepId;
            $where .= " OR department_id in ($subDepIds))";
        }else{
            $where .= " AND 1=1)";
        }
         return DB::table('tbluser')
         ->selectRaw('id,CONCAT_WS(" ",COALESCE(first_name,"-"),COALESCE(middle_name,"-"),COALESCE(last_name,"-")) as name,mobile')
        ->whereRaw($where)
        ->where('sub_institute_id',$sub_institute_id)
        ->where('status',1)
        ->groupBy('id')
        ->get()
        ->toArray();
    }

    public function hierarchy(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->input('sub_institute_id', session()->get('sub_institute_id'));

        $departments = DB::table('hrms_departments')
            ->where('status', 1)
            ->where('sub_institute_id', $sub_institute_id)
            ->orderBy('parent_id', 'ASC')
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->get()
            ->toArray();

        $employees = DB::table('tbluser as u')
            ->leftJoin('hrms_departments as dep', 'u.department_id', '=', 'dep.id')
            ->select(
                'u.id',
                'u.employee_no',
                'u.gender',
                'u.image',
                DB::raw('CONCAT_WS(" ", COALESCE(u.first_name,"-"), COALESCE(u.middle_name,"-"), COALESCE(u.last_name,"-")) as name'),
                'u.mobile',
                'u.department_id'
            )
            ->where('u.status', 1)
            ->where('u.sub_institute_id', $sub_institute_id)
            ->get()
            ->toArray();

        $employeesByDept = [];
        foreach ($employees as $emp) {
            $deptId = $emp->department_id ?: 0;
            $employeesByDept[$deptId][] = $emp;
        }

        $parentDepts = [];
        $childDepts = [];

        foreach ($departments as $dept) {
            if ($dept->parent_id == 0) {
                $parentDepts[] = $dept;
            } else {
                $childDepts[$dept->parent_id][] = $dept;
            }
        }

        $result = [];
        foreach ($parentDepts as $parent) {
            $deptData = [
                'id' => $parent->id,
                'name' => $parent->department,
                'code' => $parent->code ?? null,
                'description' => $parent->description ?? null,
                'sort_order' => (int) ($parent->sort_order ?? 0),
                'parent_id' => (int) $parent->parent_id,
                'head_user_id' => $parent->head_user_id ?? null,
                'total_employees' => count($employeesByDept[$parent->id] ?? []),
                'employees' => $employeesByDept[$parent->id] ?? [],
                'sub_departments' => []
            ];

            if (isset($childDepts[$parent->id])) {
                foreach ($childDepts[$parent->id] as $child) {
                    $deptData['sub_departments'][] = [
                        'id' => $child->id,
                        'name' => $child->department,
                        'code' => $child->code ?? null,
                        'description' => $child->description ?? null,
                        'sort_order' => (int) ($child->sort_order ?? 0),
                        'parent_id' => (int) $child->parent_id,
                        'head_user_id' => $child->head_user_id ?? null,
                        'total_employees' => count($employeesByDept[$child->id] ?? []),
                        'employees' => $employeesByDept[$child->id] ?? []
                    ];
                }
            }

            $result[] = $deptData;
        }

        $res['departments'] = $result;
        return is_mobile($type, "hierarchy", $res, "view");
    }

    /**
     * GET /api/departments-management
     * -> { main_departments: [...], sub_departments: { [parent_id]: [...] } }
     *
     * The payroll module's department filter/picker (app/hrit/_lib/
     * payroll-api.ts's getDepartmentsManagement, used by Salary Structure,
     * Payroll Deduction, Form 16, Salary Certificate) calls this exact
     * path/shape, matching hp_erp's DepartmentManagementController@index.
     * Only POST/PUT/DELETE were ported for this route previously - the GET
     * was missed, so every payroll department dropdown 404'd and stayed
     * empty. Reuses hrms_departments the same way hierarchy() above does,
     * just reshaped to the flat main/sub contract this consumer expects
     * instead of hierarchy()'s nested tree-with-employees shape.
     */
    public function indexManagement(Request $request)
    {
        $auth = $this->managementTokenContext($request);
        if ($auth instanceof \Illuminate\Http\JsonResponse) {
            return $auth;
        }

        $sub_institute_id = $auth;

        $departments = DB::table('hrms_departments')
            ->where('status', 1)
            ->where('sub_institute_id', $sub_institute_id)
            ->orderBy('department')
            ->get();

        $mainDepartments = [];
        $subDepartments = [];

        foreach ($departments as $department) {
            if (empty($department->parent_id)) {
                $mainDepartments[] = $department;
            } else {
                $subDepartments[$department->parent_id][] = $department;
            }
        }

        return response()->json([
            'main_departments' => $mainDepartments,
            'sub_departments'  => $subDepartments,
        ]);
    }

    /**
     * Shared auth/context resolution for the Department Management API
     * (POST/PUT/DELETE /api/departments-management/*). Ported from hp_erp's
     * DepartmentManagementController, which does a manual inline Sanctum
     * PersonalAccessToken check - this codebase's real tokens are JWTs (see
     * App\Http\Controllers\api\Attendance\Concerns\ResolvesAttendanceContext
     * for the established precedent), so we validate with GetsJwtToken
     * instead, mirroring hierarchy() above which also resolves
     * sub_institute_id from the request/session rather than Sanctum.
     *
     * @return int|\Illuminate\Http\JsonResponse sub_institute_id on success
     */
    private function managementTokenContext(Request $request)
    {
        $token = $request->input('token');

        if (!$token) {
            return response()->json(['status' => 0, 'message' => 'Token not provided'], 401);
        }

        try {
            if (!$this->jwtToken($request)->validate()) {
                return response()->json(['status' => 0, 'message' => 'Invalid token'], 401);
            }
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => 'Invalid token'], 401);
        }

        // Tenant identity must come from the verified token payload, not the
        // client-supplied request body - these routes carry no session
        // middleware (see routes/api.php), so a validated token from School A
        // could otherwise still write into School B's data by putting a
        // different sub_institute_id in the request.
        $sub_institute_id = $this->jwtPayload('sub_institute_id', $request);

        if (empty($sub_institute_id)) {
            return response()->json(['status' => 0, 'message' => 'Invalid token payload'], 401);
        }

        return $sub_institute_id;
    }

    /**
     * POST /api/departments-management
     * Body: { sub_institute_id, user_id, department, parent_id }
     * -> { status: 1|0, message, data: { id } }
     *
     * Ported from hp_erp's DepartmentManagementController@store. Distinct
     * from the store() method above (already routed under
     * hrms/add_department for the session-based web UI, different
     * request/response contract) - this is the new API contract only.
     */
    public function storeManagement(Request $request)
    {
        $auth = $this->managementTokenContext($request);
        if ($auth instanceof \Illuminate\Http\JsonResponse) {
            return $auth;
        }

        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|numeric',
            'department' => 'required|string',
            'parent_id' => 'nullable|numeric',
            'user_id' => 'required|numeric',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'message' => $validator->messages()->first()
            ], 400);
        }

        $sub_institute_id = $auth;
        $department = $request->department;
        $parent_id = $request->parent_id ?? 0;
        $user_id = $request->user_id;

        $check = DB::table('hrms_departments')
            ->where('department', $department)
            ->where('sub_institute_id', $sub_institute_id)
            ->where('parent_id', $parent_id)
            ->first();

        if ($check) {
            return response()->json([
                'status' => 0,
                'message' => 'Department already exists'
            ], 400);
        }

        // Note: unlike hp_erp's hrms_departments, this app's table has no
        // created_by/updated_by columns (see SHOW CREATE TABLE) - user_id is
        // still required/validated above to match hp_erp's contract, but is
        // not persisted since there's no column for it here.
        $insertData = [
            'department' => $department,
            'parent_id' => $parent_id,
            'tasks' => null,
            'roles_responsibility' => $department,
            'status' => 1,
            'sub_institute_id' => $sub_institute_id,
            'created_at' => now(),
        ];

        // code/description back the department creation wizard's Basics
        // step (see the 2026_08_25_160000 migration). Guarded with
        // hasColumn() the same way export() above does, so this still works
        // against an environment where that migration has not run yet.
        if ($request->has('code') && Schema::hasColumn('hrms_departments', 'code')) {
            $insertData['code'] = $request->input('code');
        }
        if ($request->has('description') && Schema::hasColumn('hrms_departments', 'description')) {
            $insertData['description'] = $request->input('description');
        }

        $departmentId = DB::table('hrms_departments')->insertGetId($insertData);

        AuditLog::record([
            'module' => 'hrms',
            'action' => 'department_management_added',
            'entity_type' => 'department',
            'entity_id' => $departmentId,
            'new_values' => $insertData,
        ]);

        return response()->json([
            'status' => 1,
            'message' => 'Department added successfully',
            'data' => ['id' => $departmentId]
        ]);
    }

    /**
     * PUT/PATCH /api/departments-management/{id}
     * Body: { sub_institute_id, user_id, department }
     * -> { status: 1|0, message }
     *
     * Ported from hp_erp's DepartmentManagementController@update (rename
     * only). Distinct from Update() above (session-based web UI contract).
     */
    public function updateManagement(Request $request, $id)
    {
        $auth = $this->managementTokenContext($request);
        if ($auth instanceof \Illuminate\Http\JsonResponse) {
            return $auth;
        }

        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|numeric',
            // "sometimes|required" - a caller that sends `department` (the
            // only field the pre-existing "Edit department" dialog ever
            // sends) still must send a non-empty string, exactly as before.
            // A caller that omits it entirely (e.g. the creation wizard's
            // Finish step, which sends only `status`) is no longer forced
            // to also resend the name.
            'department' => 'sometimes|required|string',
            'user_id' => 'required|numeric',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'status' => 'nullable|in:0,1',
            'parent_id' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'message' => $validator->messages()->first()
            ], 400);
        }

        $sub_institute_id = $auth;
        $user_id = $request->user_id;

        // See storeManagement() note above: no updated_by column on this table.
        $updateData = [];

        if ($request->has('department')) {
            $updateData['department'] = $request->input('department');
        }
        if ($request->has('code') && Schema::hasColumn('hrms_departments', 'code')) {
            $updateData['code'] = $request->input('code');
        }
        if ($request->has('description') && Schema::hasColumn('hrms_departments', 'description')) {
            $updateData['description'] = $request->input('description');
        }
        if ($request->has('status')) {
            // status toggle: 0 marks a wizard-created department a resumable
            // draft, 1 (Finish) activates it. Validated to 0/1 above.
            $updateData['status'] = (int) $request->input('status');
        }
        if ($request->has('parent_id')) {
            // 0/empty means "make top-level", same convention storeManagement()
            // uses. Cycle-prevention: a department cannot become its own
            // parent, nor be moved beneath one of its own descendants - the
            // frontend already excludes those options from the picker, but
            // this is the authoritative check since the frontend's list can
            // be stale.
            $newParentId = (int) ($request->input('parent_id') ?: 0);

            if ($newParentId !== 0) {
                $parentExists = DB::table('hrms_departments')
                    ->where('id', $newParentId)
                    ->where('sub_institute_id', $sub_institute_id)
                    ->exists();

                if (!$parentExists) {
                    return response()->json([
                        'status' => 0,
                        'message' => 'Selected parent department was not found'
                    ], 422);
                }

                if ($newParentId === (int) $id || $this->isDescendant($id, $newParentId, $sub_institute_id)) {
                    return response()->json([
                        'status' => 0,
                        'message' => 'Cannot move a department beneath itself or one of its own sub-departments'
                    ], 422);
                }
            }

            $updateData['parent_id'] = $newParentId;
        }

        if (empty($updateData)) {
            return response()->json([
                'status' => 0,
                'message' => 'Nothing to update'
            ], 400);
        }

        $updateData['updated_at'] = now();

        $update = DB::table('hrms_departments')
            ->where('id', $id)
            ->where('sub_institute_id', $sub_institute_id)
            ->update($updateData);

        if ($update) {
            AuditLog::record([
                'module' => 'hrms',
                'action' => 'department_management_updated',
                'entity_type' => 'department',
                'entity_id' => $id,
                'new_values' => $updateData,
            ]);

            return response()->json([
                'status' => 1,
                'message' => 'Department updated successfully'
            ]);
        } else {
            return response()->json([
                'status' => 0,
                'message' => 'Department not found or update failed'
            ], 404);
        }
    }

    /**
     * Whether $candidateParentId is $departmentId itself or lies anywhere
     * beneath it in the hierarchy (i.e. moving $departmentId under
     * $candidateParentId would create a cycle). Walks the tree iteratively
     * (breadth-first over parent_id) rather than recursively, scoped to a
     * single sub_institute_id so it never crosses tenants.
     *
     * Used only by updateManagement()'s parent_id cycle-check - no equivalent
     * helper existed elsewhere in this file to reuse.
     */
    private function isDescendant($departmentId, $candidateParentId, $sub_institute_id): bool
    {
        $queue = [(int) $departmentId];
        $visited = [];

        while (!empty($queue)) {
            $currentId = array_shift($queue);
            if (isset($visited[$currentId])) {
                continue;
            }
            $visited[$currentId] = true;

            $childIds = DB::table('hrms_departments')
                ->where('parent_id', $currentId)
                ->where('sub_institute_id', $sub_institute_id)
                ->pluck('id')
                ->all();

            foreach ($childIds as $childId) {
                $childId = (int) $childId;
                if ($childId === (int) $candidateParentId) {
                    return true;
                }
                $queue[] = $childId;
            }
        }

        return false;
    }

    /**
     * DELETE /api/departments-management/{id}
     * -> { status: 1|0, message }
     * Soft-deletes the target department and its direct children
     * (parent_id = $id) within the same sub_institute_id.
     *
     * Ported from hp_erp's DepartmentManagementController@destroy. Distinct
     * from destroy() above (session-based web UI contract, hard delete).
     * Does not touch hrms_departments_mapping or any HRIT attendance code.
     */
    public function destroyManagement(Request $request, $id)
    {
        $auth = $this->managementTokenContext($request);
        if ($auth instanceof \Illuminate\Http\JsonResponse) {
            return $auth;
        }

        $sub_institute_id = $auth;

        $department = DB::table('hrms_departments')
            ->where('id', $id)
            ->where('sub_institute_id', $sub_institute_id)
            ->first();

        if (!$department) {
            return response()->json([
                'status' => 0,
                'message' => 'Department not found or soft delete failed'
            ], 404);
        }

        $subDepartmentIds = DB::table('hrms_departments')
            ->where('parent_id', $id)
            ->where('sub_institute_id', $sub_institute_id)
            ->pluck('id')
            ->all();

        // Every row that pointed at this department (or one of its direct
        // subdepartments, since those are soft-deleted alongside it below)
        // is released before the department itself goes, so nothing is left
        // referencing a deleted row - the same DEPARTMENT_ID_TABLES list
        // merge() reassigns against, just nulled out here since destroy has
        // no target department to move them onto.
        $departmentIds = array_merge([(int) $id], $subDepartmentIds);
        $released = [];

        DB::transaction(function () use ($departmentIds, $sub_institute_id, $id, &$released) {
            foreach (self::DEPARTMENT_ID_TABLES as $table) {
                if ($table === 'hrms_departments' || !Schema::hasTable($table) || !Schema::hasColumn($table, 'department_id')) {
                    continue;
                }

                $query = DB::table($table)->whereIn('department_id', $departmentIds);

                if (Schema::hasColumn($table, 'sub_institute_id')) {
                    $query->where('sub_institute_id', $sub_institute_id);
                }

                $count = $query->update(['department_id' => null]);

                if ($count > 0) {
                    $released[$table] = $count;
                }
            }

            // This table's status column is enum('1','') - not a 0/1 tinyint -
            // so "inactive" is the empty-string member of the enum.
            DB::table('hrms_departments')
                ->where('id', $id)
                ->where('sub_institute_id', $sub_institute_id)
                ->update(['status' => '', 'deleted_at' => now()]);

            // Soft delete direct subdepartments only.
            DB::table('hrms_departments')
                ->where('parent_id', $id)
                ->where('sub_institute_id', $sub_institute_id)
                ->update(['status' => '', 'deleted_at' => now()]);
        });

        AuditLog::record([
            'module' => 'hrms',
            'action' => 'department_management_deleted',
            'entity_type' => 'department',
            'entity_id' => $id,
            'new_values' => [
                'status' => '',
                'sub_departments_updated' => count($subDepartmentIds),
                'released' => $released,
            ],
        ]);

        return response()->json([
            'status' => 1,
            'message' => 'Department and its subdepartments deleted successfully'
        ]);
    }

    /**
     * PATCH /api/departments-management/{id}/head
     * Body: { head_user_id: int|null }
     * -> { status: 1|0, message }
     *
     * Ported from hp_erp's DepartmentManagementController@setHead.
     * head_user_id already exists on hrms_departments (already read by
     * hierarchy() above and by the 2026_08_25_160000 migration's docblock)
     * but had no writer reachable from any screen - this is that writer.
     */
    public function setHead(Request $request, $id)
    {
        $auth = $this->managementTokenContext($request);
        if ($auth instanceof \Illuminate\Http\JsonResponse) {
            return $auth;
        }
        $sub_institute_id = $auth;

        $department = DB::table('hrms_departments')
            ->where('id', $id)
            ->where('sub_institute_id', $sub_institute_id)
            ->first();

        if (!$department) {
            return response()->json(['status' => 0, 'message' => 'Department not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'head_user_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 0, 'message' => $validator->messages()->first()], 400);
        }

        $headUserId = $request->input('head_user_id');
        $headUserId = ($headUserId === null || $headUserId === '' || (int) $headUserId === 0)
            ? null
            : (int) $headUserId;

        // Naming a user from another organisation would put their name on
        // this tenant's screen, which is a disclosure even though it writes
        // only an id.
        if ($headUserId !== null) {
            $belongs = DB::table('tbluser')
                ->where('id', $headUserId)
                ->where('sub_institute_id', $sub_institute_id)
                ->exists();

            if (!$belongs) {
                return response()->json(['status' => 0, 'message' => 'Employee not found'], 422);
            }
        }

        DB::table('hrms_departments')
            ->where('id', $id)
            ->where('sub_institute_id', $sub_institute_id)
            ->update([
                'head_user_id' => $headUserId,
                'updated_at' => now(),
            ]);

        AuditLog::record([
            'module' => 'hrms',
            'action' => 'department_management_head_set',
            'entity_type' => 'department',
            'entity_id' => $id,
            'new_values' => ['head_user_id' => $headUserId],
        ]);

        return response()->json([
            'status' => 1,
            'message' => $headUserId === null ? 'Department head cleared' : 'Department head updated',
        ]);
    }

    /**
     * GET /api/departments-management/export
     * -> CSV download of the department list
     *
     * Ported from hp_erp's DepartmentManagementController@export - the
     * Export button had no click handler at all; this gives it one, over the
     * same rows/columns indexManagement()/hierarchy() above already read.
     */
    public function export(Request $request)
    {
        $auth = $this->managementTokenContext($request);
        if ($auth instanceof \Illuminate\Http\JsonResponse) {
            return $auth;
        }
        $sub_institute_id = $auth;

        $hasCode = Schema::hasColumn('hrms_departments', 'code');
        $hasDescription = Schema::hasColumn('hrms_departments', 'description');
        $hasSortOrder = Schema::hasColumn('hrms_departments', 'sort_order');

        // Correlated count rather than a LEFT JOIN + GROUP BY onto tbluser,
        // which would multiply department rows by their employees. tbluser
        // has no deleted_at/soft-delete column here (see EmployeeDirectory-
        // Controller's class docblock), so status + terminated_date is the
        // full "active employee" filter on this schema.
        $employeeCount = DB::table('tbluser')
            ->selectRaw('COUNT(*)')
            ->whereColumn('tbluser.department_id', 'd.id')
            ->where('tbluser.sub_institute_id', $sub_institute_id)
            ->where('tbluser.status', 1)
            ->whereNull('tbluser.terminated_date');

        $select = ['d.id', 'd.department', 'd.parent_id', 'd.status', 'd.created_at'];
        if ($hasCode) {
            $select[] = 'd.code';
        }
        if ($hasDescription) {
            $select[] = 'd.description';
        }
        $select[] = DB::raw("TRIM(CONCAT_WS(' ', h.first_name, h.middle_name, h.last_name)) as head_name");

        $query = DB::table('hrms_departments as d')
            ->leftJoin('tbluser as h', 'h.id', '=', 'd.head_user_id')
            ->where('d.sub_institute_id', $sub_institute_id)
            ->whereNull('d.deleted_at')
            ->select($select)
            ->selectSub($employeeCount, 'employee_count');

        $query = $hasSortOrder ? $query->orderBy('d.sort_order') : $query;
        $departments = $query->orderBy('d.department')->get();

        $parentNames = $departments->pluck('department', 'id');

        $filename = 'departments-' . date('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($departments, $parentNames, $hasCode, $hasDescription) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Code', 'Department', 'Parent Department', 'Head of Department',
                'Employees', 'Status', 'Description', 'Created On',
            ]);

            foreach ($departments as $row) {
                fputcsv($handle, [
                    $hasCode ? $row->code : '',
                    $row->department,
                    (int) $row->parent_id === 0 ? '' : ($parentNames[$row->parent_id] ?? ''),
                    $row->head_name ?: '',
                    (int) $row->employee_count,
                    (string) $row->status === '1' ? 'Active' : 'Inactive',
                    $hasDescription ? $row->description : '',
                    $row->created_at,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * GET /api/departments-management/employees
     * -> { status: 1, data: [...] }
     *
     * Serves the head-of-department picker (no filter), the "transfer from
     * another department" list (?department_id=), and the "employees with no
     * department" pool (?unassigned=1) - the last of which had no way to be
     * answered anywhere in the application. Ported from hp_erp's
     * DepartmentManagementController@employees.
     */
    public function employees(Request $request)
    {
        $auth = $this->managementTokenContext($request);
        if ($auth instanceof \Illuminate\Http\JsonResponse) {
            return $auth;
        }
        $sub_institute_id = $auth;

        $query = DB::table('tbluser as u')
            ->leftJoin('hrms_departments as d', function ($join) use ($sub_institute_id) {
                $join->on('d.id', '=', 'u.department_id')
                    ->where('d.sub_institute_id', '=', $sub_institute_id);
            })
            ->where('u.sub_institute_id', $sub_institute_id)
            ->whereNull('u.terminated_date')
            ->select([
                'u.id',
                'u.employee_no',
                'u.department_id',
                'd.department as department_name',
                DB::raw("TRIM(CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name)) as name"),
            ]);

        if ($request->boolean('unassigned')) {
            $query->where(function ($q) {
                $q->whereNull('u.department_id')->orWhere('u.department_id', 0);
            });
        } elseif ($departmentId = (int) $request->query('department_id')) {
            $query->where('u.department_id', $departmentId);
        }

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('u.first_name', 'like', '%' . $search . '%')
                    ->orWhere('u.last_name', 'like', '%' . $search . '%')
                    ->orWhere('u.user_name', 'like', '%' . $search . '%')
                    ->orWhere('u.employee_no', 'like', '%' . $search . '%');
            });
        }

        // Caller-supplied cap, clamped to [1, 500] - lets the wizard's Head
        // step request a small default batch (fast: fewer rows to sort by
        // the computed name column) instead of always paying for the full
        // 500-row fetch when it only wants something to show immediately.
        $limit = (int) $request->query('limit', 500);
        $limit = max(1, min(500, $limit ?: 500));

        return response()->json([
            'status' => 1,
            'data' => $query->orderBy('u.first_name')->orderBy('u.last_name')->limit($limit)->get(),
        ]);
    }

    /**
     * POST /api/departments-management/{id}/employees
     * Body: { user_ids: number[], jobrole_id?: number, remarks?: string }
     * -> { status: 1, message, data: { applied, refused } }
     *
     * Moves a batch of employees into this department - the "transfer from
     * another department" and "assign from the unassigned pool" tool behind
     * DepartmentEmployeesPanel. Ported from G2G's
     * organizationService.assignDepartmentEmployees, whose rationale carries
     * over unchanged: a job role (`s_user_jobrole`) belongs to exactly one
     * department, so moving someone here without also giving them one of
     * THIS department's roles must clear whatever role they held before -
     * otherwise they would keep pointing at a role that belongs to the
     * department they just left. `jobrole_id` is therefore validated against
     * `$id` (not just "does this jobrole exist"), and refused (the employee
     * is still moved, just without a role) rather than silently assigning a
     * role from a different department.
     *
     * Always HTTP 200 with a verdict per employee, same as G2G: one bad id in
     * the batch never aborts the rest.
     */
    public function assignEmployees(Request $request, $id)
    {
        $auth = $this->managementTokenContext($request);
        if ($auth instanceof \Illuminate\Http\JsonResponse) {
            return $auth;
        }
        $sub_institute_id = $auth;

        $department = DB::table('hrms_departments')
            ->where('id', $id)
            ->where('sub_institute_id', $sub_institute_id)
            ->first();

        if (!$department) {
            return response()->json(['status' => 0, 'message' => 'Department not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer',
            'jobrole_id' => 'nullable|integer',
            'remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 0, 'message' => $validator->messages()->first()], 400);
        }

        $userIds = array_values(array_unique(array_map('intval', $request->input('user_ids'))));

        // A jobrole only counts if it exists, is not soft-deleted, and
        // belongs to THIS department - otherwise it is treated exactly like
        // "no role chosen" (cleared, not refused): the employee still moves,
        // they just do not walk in holding a role from somewhere else.
        $jobroleId = null;
        if ($request->filled('jobrole_id')) {
            $requestedJobroleId = (int) $request->input('jobrole_id');
            $ownRole = DB::table('s_user_jobrole')
                ->where('id', $requestedJobroleId)
                ->where('department_id', $id)
                ->where('sub_institute_id', $sub_institute_id)
                ->whereNull('deleted_at')
                ->exists();
            $jobroleId = $ownRole ? $requestedJobroleId : null;
        }

        $applied = 0;
        $refused = 0;

        DB::transaction(function () use ($userIds, $id, $sub_institute_id, $jobroleId, &$applied, &$refused) {
            foreach ($userIds as $userId) {
                $exists = DB::table('tbluser')
                    ->where('id', $userId)
                    ->where('sub_institute_id', $sub_institute_id)
                    ->exists();

                if (!$exists) {
                    $refused++;
                    continue;
                }

                DB::table('tbluser')
                    ->where('id', $userId)
                    ->where('sub_institute_id', $sub_institute_id)
                    ->update([
                        'department_id' => $id,
                        'allocated_standards' => $jobroleId,
                    ]);

                $applied++;
            }
        });

        AuditLog::record([
            'module' => 'hrms',
            'action' => 'department_management_employees_assigned',
            'entity_type' => 'department',
            'entity_id' => $id,
            'new_values' => [
                'user_ids' => $userIds,
                'jobrole_id' => $jobroleId,
                'remarks' => $request->input('remarks'),
                'applied' => $applied,
                'refused' => $refused,
            ],
        ]);

        return response()->json([
            'status' => 1,
            'message' => $refused > 0 ? "{$applied} moved, {$refused} skipped." : "{$applied} employee(s) moved.",
            'data' => ['applied' => $applied, 'refused' => $refused],
        ]);
    }

    /**
     * DELETE /api/departments-management/{id}/employees
     * Body: { user_ids: number[] }
     * -> { status: 1, message, data: { applied, refused } }
     *
     * Removes a batch of employees FROM this department (their
     * `department_id`/`allocated_standards` are cleared, not set to another
     * department) - the "Remove" action on DepartmentEmployeesPanel's current
     * roster. Only rows actually in this department are touched; anyone else
     * in the batch is refused. Same per-employee verdict shape as
     * assignEmployees() above.
     */
    public function unassignEmployees(Request $request, $id)
    {
        $auth = $this->managementTokenContext($request);
        if ($auth instanceof \Illuminate\Http\JsonResponse) {
            return $auth;
        }
        $sub_institute_id = $auth;

        $department = DB::table('hrms_departments')
            ->where('id', $id)
            ->where('sub_institute_id', $sub_institute_id)
            ->first();

        if (!$department) {
            return response()->json(['status' => 0, 'message' => 'Department not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 0, 'message' => $validator->messages()->first()], 400);
        }

        $userIds = array_values(array_unique(array_map('intval', $request->input('user_ids'))));

        $applied = 0;
        $refused = 0;

        DB::transaction(function () use ($userIds, $id, $sub_institute_id, &$applied, &$refused) {
            foreach ($userIds as $userId) {
                $updated = DB::table('tbluser')
                    ->where('id', $userId)
                    ->where('sub_institute_id', $sub_institute_id)
                    ->where('department_id', $id)
                    ->update([
                        'department_id' => null,
                        'allocated_standards' => null,
                    ]);

                if ($updated) {
                    $applied++;
                } else {
                    $refused++;
                }
            }
        });

        AuditLog::record([
            'module' => 'hrms',
            'action' => 'department_management_employees_unassigned',
            'entity_type' => 'department',
            'entity_id' => $id,
            'new_values' => [
                'user_ids' => $userIds,
                'applied' => $applied,
                'refused' => $refused,
            ],
        ]);

        return response()->json([
            'status' => 1,
            'message' => $refused > 0 ? "{$applied} removed, {$refused} skipped." : "{$applied} employee(s) removed.",
            'data' => ['applied' => $applied, 'refused' => $refused],
        ]);
    }

    /**
     * GET /api/departments-management/{id}/impact
     * -> { status: 1|0, message, data: { department, sub_departments, records: {table: count}, total_records } }
     *
     * Read-only preview of what a delete or merge would touch, so the
     * frontend can show "deleting this affects N records" instead of a
     * silent orphan admission. New capability - hp_erp's equivalent
     * (DepartmentManagementController@impact) has no LMS-K12 predecessor to
     * diverge from.
     */
    public function impact(Request $request, $id)
    {
        $auth = $this->managementTokenContext($request);
        if ($auth instanceof \Illuminate\Http\JsonResponse) {
            return $auth;
        }
        $sub_institute_id = $auth;

        $department = DB::table('hrms_departments')
            ->where('id', $id)
            ->where('sub_institute_id', $sub_institute_id)
            ->first();

        if (!$department) {
            return response()->json(['status' => 0, 'message' => 'Department not found'], 404);
        }

        $subDepartments = DB::table('hrms_departments')
            ->where('parent_id', $id)
            ->where('sub_institute_id', $sub_institute_id)
            ->count();

        $records = [];
        $total = 0;

        foreach (self::DEPARTMENT_ID_TABLES as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'department_id')) {
                continue;
            }

            $query = DB::table($table)->where('department_id', $id);

            if (Schema::hasColumn($table, 'sub_institute_id')) {
                $query->where('sub_institute_id', $sub_institute_id);
            }

            $count = $query->count();

            if ($count > 0) {
                $records[$table] = $count;
                $total += $count;
            }
        }

        return response()->json([
            'status' => 1,
            'message' => 'Impact calculated',
            'data' => [
                'department' => $department,
                'sub_departments' => $subDepartments,
                'records' => $records,
                'total_records' => $total,
            ],
        ]);
    }

    /**
     * POST /api/departments-management/merge
     * Body: { sub_institute_id, source_id, target_id }
     * -> { status: 1|0, message, data: { moved: {table: count} } }
     *
     * Reassigns every dependent row (see DEPARTMENT_ID_TABLES) from
     * source_id to target_id, re-parents any sub-departments of source_id
     * onto target_id, then soft-deletes source_id - all inside one
     * transaction, so a failure partway through leaves nothing half-moved.
     * New capability, same reasoning as impact() above.
     */
    public function merge(Request $request)
    {
        $auth = $this->managementTokenContext($request);
        if ($auth instanceof \Illuminate\Http\JsonResponse) {
            return $auth;
        }
        $sub_institute_id = $auth;

        $validator = Validator::make($request->all(), [
            'source_id' => 'required|integer|different:target_id',
            'target_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 0, 'message' => $validator->messages()->first()], 400);
        }

        $sourceId = (int) $request->input('source_id');
        $targetId = (int) $request->input('target_id');

        $source = DB::table('hrms_departments')->where('id', $sourceId)->where('sub_institute_id', $sub_institute_id)->first();
        $target = DB::table('hrms_departments')->where('id', $targetId)->where('sub_institute_id', $sub_institute_id)->first();

        if (!$source || !$target) {
            return response()->json(['status' => 0, 'message' => 'Department not found'], 404);
        }

        $moved = [];

        DB::transaction(function () use ($sourceId, $targetId, $sub_institute_id, &$moved) {
            foreach (self::DEPARTMENT_ID_TABLES as $table) {
                if ($table === 'hrms_departments' || !Schema::hasTable($table) || !Schema::hasColumn($table, 'department_id')) {
                    continue;
                }

                $query = DB::table($table)->where('department_id', $sourceId);

                if (Schema::hasColumn($table, 'sub_institute_id')) {
                    $query->where('sub_institute_id', $sub_institute_id);
                }

                $count = $query->update(['department_id' => $targetId]);

                if ($count > 0) {
                    $moved[$table] = $count;
                }
            }

            // Re-parent any sub-departments of the source onto the target.
            DB::table('hrms_departments')
                ->where('parent_id', $sourceId)
                ->where('sub_institute_id', $sub_institute_id)
                ->update(['parent_id' => $targetId]);

            // Same soft-delete shape as destroyManagement() above.
            DB::table('hrms_departments')
                ->where('id', $sourceId)
                ->where('sub_institute_id', $sub_institute_id)
                ->update(['status' => '', 'deleted_at' => now()]);
        });

        AuditLog::record([
            'module' => 'hrms',
            'action' => 'department_management_merged',
            'entity_type' => 'department',
            'entity_id' => $sourceId,
            'new_values' => ['merged_into' => $targetId, 'moved' => $moved],
        ]);

        return response()->json([
            'status' => 1,
            'message' => "Merged into \"{$target->department}\" successfully",
            'data' => ['moved' => $moved],
        ]);
    }

    /**
     * POST /api/departments-management/reorder
     * Body: { sub_institute_id, order: [{ id, sort_order }, ...] }
     * -> { status: 1|0, message }
     *
     * Backs the "Move up / Move down" controls, which had no ordering
     * column to move anything within until the sort_order migration. New
     * capability, same reasoning as impact()/merge() above.
     */
    public function reorder(Request $request)
    {
        $auth = $this->managementTokenContext($request);
        if ($auth instanceof \Illuminate\Http\JsonResponse) {
            return $auth;
        }
        $sub_institute_id = $auth;

        $validator = Validator::make($request->all(), [
            'order' => 'required|array|min:1',
            'order.*.id' => 'required|integer',
            'order.*.sort_order' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 0, 'message' => $validator->messages()->first()], 400);
        }

        DB::transaction(function () use ($request, $sub_institute_id) {
            foreach ($request->input('order') as $row) {
                DB::table('hrms_departments')
                    ->where('id', $row['id'])
                    ->where('sub_institute_id', $sub_institute_id)
                    ->update(['sort_order' => $row['sort_order']]);
            }
        });

        return response()->json(['status' => 1, 'message' => 'Order updated successfully']);
    }
}
