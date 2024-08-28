<?php

namespace App\Http\Controllers\HRMS;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use DB;

class departmentController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');

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
        $sub_institute_id = session()->get('sub_institute_id');
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
            $insert = DB::table('hrms_departments')->insert([
                'department'=>$department_name,
                'parent_id'=>$parent_id,
                'tasks'=>$task,
                'roles_responsibility'=>$roles_responsibility,
                'status'=>1,
                'is_calculated'=>$is_calculated,
                'sub_institute_id'=>$sub_institute_id
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

        $department_name = $request->department_name;
        $roles_responsibility = $request->roles_responsibility;
        $is_calculated = $request->is_calculated;
        $task = $request->tasks;
        $parent_id=0;

        if($request->has('parentDiv') && $request->parentDiv!=''){
            $parent_id = $request->parentDiv;
        }

        $update = DB::table('hrms_departments')->where('id',$id)->Update([
                'department'=>$department_name,
                'parent_id'=>$parent_id,
                'tasks'=>$task,
                'roles_responsibility'=>$roles_responsibility,
                'status'=>1,
                'is_calculated'=>$is_calculated,
                'sub_institute_id'=>$sub_institute_id
            ]);

        if($update){
            $res['status_code']=1;
            $res['message']="Updated Successfully!!";
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
        $sub_institute_id = session()->get('sub_institute_id');
        $depIds = $request->depId;

         return DB::table('hrms_departments')
        ->whereRaw('parent_id in ('.$depIds.')')
        ->where('sub_institute_id',$sub_institute_id)
        ->groupBy('id')
        ->get()
        ->toArray();
    }

    public function departmentEmployeeList(Request $request){
        $sub_institute_id = session()->get('sub_institute_id');
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
}
