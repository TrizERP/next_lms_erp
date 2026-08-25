<?php

namespace App\Http\Controllers\HRMS;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use DB;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Support\Facades\Validator;
use App\Models\AuditLog;

class departmentController extends Controller
{
    use GetsJwtToken;

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
                'total_employees' => count($employeesByDept[$parent->id] ?? []),
                'employees' => $employeesByDept[$parent->id] ?? [],
                'sub_departments' => []
            ];

            if (isset($childDepts[$parent->id])) {
                foreach ($childDepts[$parent->id] as $child) {
                    $deptData['sub_departments'][] = [
                        'id' => $child->id,
                        'name' => $child->department,
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
            'user_id' => 'required|numeric'
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
            'department' => 'required|string',
            'user_id' => 'required|numeric'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'message' => $validator->messages()->first()
            ], 400);
        }

        $sub_institute_id = $auth;
        $department = $request->department;
        $user_id = $request->user_id;

        // See storeManagement() note above: no updated_by column on this table.
        $updateData = [
                'department' => $department,
                'updated_at' => now(),
            ];
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

        $update = DB::table('hrms_departments')
            ->where('id', $id)
            ->where('sub_institute_id', $sub_institute_id)
            // This table's status column is enum('1','') - not a 0/1 tinyint -
            // so "inactive" is the empty-string member of the enum.
            ->update(['status' => '', 'deleted_at' => now()]);

        // Soft delete direct subdepartments only.
        $subUpdated = DB::table('hrms_departments')
            ->where('parent_id', $id)
            ->where('sub_institute_id', $sub_institute_id)
            ->update(['status' => '', 'deleted_at' => now()]);

        if ($update) {
            AuditLog::record([
                'module' => 'hrms',
                'action' => 'department_management_deleted',
                'entity_type' => 'department',
                'entity_id' => $id,
                'new_values' => ['status' => '', 'sub_departments_updated' => $subUpdated],
            ]);

            return response()->json([
                'status' => 1,
                'message' => 'Department and its subdepartments deleted successfully'
            ]);
        } else {
            return response()->json([
                'status' => 0,
                'message' => 'Department not found or soft delete failed'
            ], 404);
        }
    }
}
