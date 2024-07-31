<?php

namespace App\Http\Controllers\hrms;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use App\Traits\Helpers;
use GenTux\Jwt\GetsJwtToken;
use DB;

class HrmsLeaveController extends Controller
{
    //
    use GetsJwtToken;

    public function index(Request $request){
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
    
        if($type=="API"){
            $sub_institute_id = $request->sub_institute_id;
            $syear = $request->syear;
        }

        $res['allData']=DB::table('hrms_leave_allocation as hla')
                        ->join('hrms_departments as hd',function($join) use($sub_institute_id){
                            $join->on('hd.id','=','hla.department_id')->where('hd.sub_institute_id',$sub_institute_id)->where('status',1)->whereNull('deleted_at');
                        })
                        ->join('hrms_leave_types as hlt','hlt.id','=','hla.leave_type_id')
                        ->where('hla.sub_institute_id',$sub_institute_id)->where('hla.year',$syear)->get()->toArray();

        return is_mobile($type, "HRMS.hrms_leave.hrms_leave_allocation.show", $res, "view");
    }

    public function create(Request $request){
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
    
        if($type=="API"){
            $sub_institute_id = $request->sub_institute_id;
            $syear = $request->syear;
        }

        $res['departments'] = DB::table('hrms_departments')->where('sub_institute_id',$sub_institute_id)->where('status',1)->whereNull('deleted_at')->pluck('department','id');
        $res['leave_types'] = DB::table('hrms_leave_types')->where('sub_institute_id',$sub_institute_id)->orderBy('sort_order')->pluck('leave_type','id');
        $res['years']= Helpers::getYears();
        return is_mobile($type, "HRMS.hrms_leave.hrms_leave_allocation.add", $res, "view");
    }

    public function store(Request $request){
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
    
        if($type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }
            $sub_institute_id = $request->get('sub_institute_id');
            $syear = $request->get('syear');            
        }

        $res['department_ids'] = $department_ids = $request->department_ids;
        $res['leave_type_ids'] = $leave_type_ids = $request->leave_type_ids;
        $res['year'] = $year = $request->year;
        $res['days'] = $days = $request->days;

        if($department_ids=="All"){
            $departmentAll = DB::table('hrms_departments')->where('sub_institute_id',$sub_institute_id)->where('status',1)->whereNull('deleted_at')->get()->toArray();
            foreach($departmentAll as $key=>$value){
                $res = $this->insertData($value->id,$leave_type_ids,$year,$days,$sub_institute_id);
            }
            // echo "<pre>";print_r($d);exit;
        }else{
            $res = $this->insertData($department_ids,$leave_type_ids,$year,$days,$sub_institute_id);
        }

        return is_mobile($type, "designation_leave.index", $res);
    }

    public function insertData($department_ids,$leave_type_ids,$year,$days,$sub_institute_id){
        $i=0;
        foreach($leave_type_ids as $key=>$value){
            // check alread exists or not 
            $check = DB::table('hrms_leave_allocation')->where('sub_institute_id',$sub_institute_id)->where(['department_id'=>$department_ids,'year'=>$year,'leave_type_id'=>$value])->first();
            if(empty($check)){
                $i++;
                $insert = DB::table('hrms_leave_allocation')->insert([
                    'department_id'=>$department_ids,
                    'leave_type_id'=>$value,
                    'year'=>$year,
                    'value'=>$days,
                    'sub_institute_id'=>$sub_institute_id,
                    'created_at'=>now()
                ]);
            }
        }
        if($i>0){
            $res['status_code'] = 1;
            $res['message'] = "Added SuccessFully";
        }else{
            $res['status_code'] = 0;
            $res['message'] = "Leave Already Alloted";
        }
        return $res;
    }

    public function edit(Request $request,$id){
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
    
        if($type=="API"){
            $sub_institute_id = $request->sub_institute_id;
            $syear = $request->syear;
        }
        $res['editData'] = DB::table('hrms_leave_allocation')->where('id',$id)->first();
        $res['departments'] = DB::table('hrms_departments')->where('sub_institute_id',$sub_institute_id)->where('status',1)->whereNull('deleted_at')->pluck('department','id');
        $res['leave_types'] = DB::table('hrms_leave_types')->where('sub_institute_id',$sub_institute_id)->orderBy('sort_order')->pluck('leave_type','id');
        $res['years']= Helpers::getYears();
        return is_mobile($type, "HRMS.hrms_leave.hrms_leave_allocation.edit", $res, "view");
    }

    public function update(Request $request,$id){
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
    
       if($type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }
            $sub_institute_id = $request->get('sub_institute_id');
            $syear = $request->get('syear');            
        }

        $res['department_ids'] = $department_ids = $request->department_ids;
        $res['leave_type_ids'] = $leave_type_ids = $request->leave_type_ids;
        $res['year'] = $year = $request->year;
        $res['days'] = $days = $request->days;

        $update = DB::table('hrms_leave_allocation')->where('id',$id)->update([
            'department_id'=>$department_ids,
            'leave_type_id'=>$leave_type_ids,
            'year'=>$year,
            'value'=>$days,
            'sub_institute_id'=>$sub_institute_id,
            'updated_at'=>now()
        ]);

        if($update){
            $res['status_code'] = 1;
            $res['message'] = "Updated SuccessFully";
        }else{
            $res['status_code'] = 0;
            $res['message'] = "Failed to Update";
        }
        return is_mobile($type, "designation_leave.index", $res);
    }

    public function destroy(Request $request,$id){
        $type=$request->type;

        if($type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }
            $sub_institute_id = $request->get('sub_institute_id');
            $syear = $request->get('syear');            
        }

        $delete = DB::table('hrms_leave_allocation')->where('id',$id)->delete();
        $res['status_code'] = 1;
        $res['message'] = "Deleted SuccessFully";
        
        return is_mobile($type, "designation_leave.index", $res);
    }
}
