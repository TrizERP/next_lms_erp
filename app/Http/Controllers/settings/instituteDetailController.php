<?php

namespace App\Http\Controllers\settings;

use App\Http\Controllers\Controller;
use App\Models\school_setupModel;
use App\Models\settings\instituteDetailModel;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use App\Http\Controllers\HRMS\departmentController;
use App\Http\Controllers\frontdesk\taskController;
use DB;

class instituteDetailController extends Controller
{

    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');

        $res['data'] = $this->getData();
        // to get datats drom another controllers add type API
        $request->merge(['type'=>'API','sub_institute_id'=>$sub_institute_id,'syear'=>$syear]);
        // get data from department controller
        $departmentController = new departmentController;
        $departmentData = $departmentController->create($request);
        $res['departmentData'] =  json_decode($departmentData,true);
        $res['taskManagerLists'] = DB::table('tbluser') ->selectRaw('id,CONCAT_WS(" ",COALESCE(first_name,"-"),COALESCE(middle_name,"-"),COALESCE(last_name,"-")) as name,mobile')->where('sub_institute_id',$sub_institute_id)->where('status',1)->get()->toArray();
        $res['skillLists'] = DB::table('tblemp_skills')->whereIn('sub_institute_id',[0,$sub_institute_id])->get()->toArray();
        // echo "<pre>";print_r($departmentData);exit;
        return is_mobile($type, "settings/add_institute_detail", $res, "view");
    }

    public function getData()
    {
        $sub_institute_id = session()->get('sub_institute_id');
        $data = school_setupModel::select("*")
            ->leftjoin("institute_detail as i", 'school_setup.Id', 'i.sub_institute_id')
            ->where(['school_setup.Id' => $sub_institute_id])
            ->get()->toArray();

        return $data[0];
    }


    public function store(Request $request)
    {
        $type = $request->input('type');

        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        if($request->has('formName')){

             // get data from department controller
             $request1 = $request->merge(['type'=>'API','sub_institute_id'=>$sub_institute_id,'syear'=>$syear]);
             $departmentController = new departmentController;
             $taskController = new taskController;
             if($request->formName=="addDepartment"){
                $departmentData = $departmentController->store($request1);
                $add = json_decode($departmentData,true);
                $res['status_code'] = 1;
                $res['message'] = "Added Successfully!!";
             }else if($request->formName=="addTask"){
                $i=0;
                foreach($request->arr as $k => $val){
                    $attchment = $val['TASK_ATTACHMENT'] ?? '';
                    
                    $user_id = session()->get("user_id");
                    // make new request to send in taskcontroller
                    $newReq = new Request(['TASK_ALLOCATED_TO'=>$val['TASK_ALLOCATED_TO'] ?? [0],'TASK_TITLE'=>$val['TASK_TITLE'],'TASK_DESCRIPTION'=>$val['TASK_DESCRIPTION'],'KRA'=>$val['KRA'],'KPA'=>$val['KPA'],'selType'=>$val['selType'],'TASK_ATTACHMENT'=>$attchment,'manageby'=>$val['manageby'],'skills'=>$val['skills'],'TASK_DATE'=>now(),'type'=>'API','sub_institute_id'=>$sub_institute_id,'syear'=>$syear,'user_id'=>$user_id]);

                    if ($attchment) {
                        $newReq->files->set('TASK_ATTACHMENT', $attchment);
                    }
                    // add task
                    if(isset($val['TASK_ALLOCATED_TO'])){
                        $taskData = $taskController->store($newReq);
                        $add = json_decode($taskData,true);
                        $i++;
                    }
                } 
               if($i > 0 ){
                 // exit;
                 $res['status_code'] = 1;
                 $res['message'] = "Added Successfully!!";
               }else{
                 // exit;
                 $res['status_code'] = 0;
                 $res['message'] = "Please Select Atleast one Employees";
               }
             }else{
                $res['status_code'] = 0;
                $res['message'] = "Failed To Add Data";
             }
            //  echo "<pre>";print_r($request->all());exit;
            
        }else{
            $newRequest = $request->post();
            $finalArray['sub_institute_id'] = $sub_institute_id;
            foreach ($newRequest as $key => $value) {
                if ($key != '_method' && $key != '_token' && $key != 'submit' && $key != 'college_name') {
                    if (is_array($value)) {
                        $value = implode(",", $value);
                    }
                    $finalArray[$key] = $value;
                }
            }
    
            instituteDetailModel::updateOrCreate([
                'sub_institute_id' => $sub_institute_id,
            ], $finalArray);
    
            $res['status_code'] = 1;
            $res['message'] = "Institute Detail Added Successfully";
        }
        
        $res['data'] = $this->getData();

        return is_mobile($type, "institute_detail.index", $res);
    }

    public function edit(Request $request, $id)
    {
        //
    }

    public function update(Request $request, $id)
    {
        //
        $type = $request->input('type');

        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $i=0;
        if($request->has('formName')){
             // get data from department controller
             if($request->formName=="addDepartment"){
                $request1 = $request->merge(['type'=>'API','sub_institute_id'=>$sub_institute_id,'syear'=>$syear]);
                $departmentController = new departmentController;
                $departmentData = $departmentController->update($request1,$id);
                $res = json_decode($departmentData,true);
                $i=1;
             }
        }

        if($i==0){
            $res['status_code']=0;
            $res['message']="Failed to Update";
        }else{
            $res['status_code']=1;
            $res['message'] = "Updated SuccessFully !!";
        }
        return is_mobile($type, "institute_detail.index", $res);

    }

    public function destroy(Request $request, $id)
    {
        //
        $type = $request->input('type');

        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear'); 
        $i=0;

        if($request->has('formName')){
             // get data from department controller
             if($request->formName=="addDepartment"){
                $request1 = $request->merge(['type'=>'API','sub_institute_id'=>$sub_institute_id,'syear'=>$syear]);
                $departmentController = new departmentController;
                $departmentData = $departmentController->destroy($request1,$id);
                $res = json_decode($departmentData,true);
                $i=1;
             }
            
        }
        if($i==0){
            $res['status_code']=0;
            $res['message']="Failed to Delete";
        }else{
            $res['status_code']=1;
            $res['message'] = "Deleted SuccessFully !!";
        }
        return is_mobile($type, "institute_detail.index", $res);

    }


}
