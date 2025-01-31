<?php

namespace App\Http\Controllers\settings;

use App\Http\Controllers\Controller;
use App\Models\school_setupModel;
use App\Models\settings\instituteDetailModel;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use function App\Helpers\employeeDetails;
use App\Http\Controllers\HRMS\departmentController;
use App\Http\Controllers\frontdesk\taskController;
use Illuminate\Support\Facades\Storage;
use GenTux\Jwt\GetsJwtToken;
use GenTux\Jwt\JwtToken;
use Validator;
use DB;

class instituteDetailController extends Controller
{
    use GetsJwtToken;

    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $formName = $request->get('formName');

        if(in_array($type,["API","JSON"])){
            $validator = Validator::make($request->all(), [
                'sub_institute_id' => 'required|numeric',
                'syear'    => 'required|numeric',
                'user_id'   => 'required|numeric',
                'formName' => 'required',
            ]);

            $sub_institute_id = $request->get('sub_institute_id');
            $syear = $request->get('syear');
            $user_id = $request->get('user_id');

            if ($validator->fails()) {
                $response['status'] = '0';
                $response['message'] = $validator->messages();
                return response()->json($response);
            } 
        }
        
        $res['complainceData'] = DB::table('master_compliance as mc')
                                ->select('mc.*',DB::Raw('(SELECT CONCAT_WS(" ",COALESCE(first_name,"-"),COALESCE(middle_name,"-"),COALESCE(last_name,"-")) FROM tbluser WHERE id=mc.assigned_to) as assigned_user'))
                                ->where('mc.sub_institute_id',$sub_institute_id)
                                ->whereNull('mc.deleted_at')->get()->toArray();
        $res['userDetails'] = employeeDetails($sub_institute_id,"",1);

        if($request->has('formName') && $formName=="complaince_library" && in_array($type,["API","JSON"])){
            $response['complainceData'] = $res['complainceData'];
            $response['userDetails'] = $res['userDetails'];
            return is_mobile($type, "settings/add_institute_detail", $response, "view");
        }

        $res['data'] = $this->getData($sub_institute_id);
        // to get datats drom another controllers add type API
        $request->merge(['type'=>'API','sub_institute_id'=>$sub_institute_id,'syear'=>$syear]);
        // get data from department controller
        $departmentController = new departmentController;
        $departmentData = $departmentController->create($request);
        $res['departmentData'] =  json_decode($departmentData,true);
        $res['taskManagerLists'] = DB::table('tbluser') ->selectRaw('id,CONCAT_WS(" ",COALESCE(first_name,"-"),COALESCE(middle_name,"-"),COALESCE(last_name,"-")) as name,mobile')->where('sub_institute_id',$sub_institute_id)->where('status',1)->get()->toArray();
        $res['skillLists'] = DB::table('o_net_occupation_detail_skill_summeries')->groupBy('name')->get()->toArray();

        // echo "<pre>";print_r($res['complainceData']);exit;
        return is_mobile($type, "settings/add_institute_detail", $res, "view");
    }

    public function getData($sub_institute_id)
    {
        $data = school_setupModel::select("*")
            ->leftjoin("institute_detail as i", 'school_setup.Id', 'i.sub_institute_id')
            ->where(['school_setup.Id' => $sub_institute_id])
            ->get()->toArray();

        return isset($data[0]) ? $data[0] : [];
    }


    public function store(Request $request)
    {
        $type = $request->input('type');

        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $user_id = session()->get('user_id');

        $res['status_code'] = 0;
        $res['message'] = "Something went wrong!!";

        if(in_array($type,["API","JSON"])){
            try {
                if (! $this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
                
                $sub_institute_id = $request->get('sub_institute_id');
                $syear = $request->get('syear');
                $user_id = $request->get('user_id');

                $validator = Validator::make($request->all(), [
                    'sub_institute_id' => 'required|numeric',
                    'syear'    => 'required|numeric',
                    'user_id'   => 'required|numeric',
                    'formName' => 'required',
                ]);
        
                if ($validator->fails()) {
                    $response['status'] = '0';
                    $response['message'] = $validator->messages();
                    return response()->json($response);
                } 
    
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }    
        }
    
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
             }
             else if($request->formName=="addTask"){
                // echo "<pre>";print_r($request->all());exit;
                $i=0;
                foreach($request->arr as $k => $val){
                    $attchment = $val['TASK_ATTACHMENT'] ?? '';
                    
                    $user_id = session()->get("user_id");
                    // make new request to send in taskcontroller
                    $newReq = new Request(['TASK_ALLOCATED_TO'=>$val['TASK_ALLOCATED_TO'] ?? [0],'TASK_TITLE'=>$val['TASK_TITLE'],'TASK_DESCRIPTION'=>$val['TASK_DESCRIPTION'],'KRA'=>$val['KRA'],'KPA'=>$val['KPA'],'selType'=>$val['selType'],'TASK_ATTACHMENT'=>$attchment,'manageby'=>$val['manageby'],'skills'=>$val['skills'],'TASK_DATE'=>now(),'observation_point'=>$val['observation_point'],'type'=>'API','sub_institute_id'=>$sub_institute_id,'syear'=>$syear,'user_id'=>$user_id]);

                    if ($attchment) {
                        $newReq->files->set('TASK_ATTACHMENT', $attchment);
                    }
                    // add task
                    if(isset($val['TASK_ALLOCATED_TO'])){
                        $taskData = $taskController->store($newReq);
                        // echo "<pre>";print_r($taskData);
                        $add = json_decode($taskData,true);
                        $i++;
                    }
                } 
                
                // exit;
               if($i > 0 ){
                //  exit;
                 $res['status_code'] = 1;
                 $res['message'] = "Added Successfully!!";
               }else{
                 // exit;
                 $res['status_code'] = 0;
                 $res['message'] = "Please Select Atleast one Employees";
               }
               if($type!='API'){
                return redirect('/settings/institute_detail?module=add_task')->with(['data'=>$res]);
               }

             }
             //compliance library start
             elseif($request->formName=="complaince_library"){
                $name = $request->name;
                $description = $request->description;
                $standard_name = $request->standard_name;
                $assigned_to = $request->assigned_to;
                $duedate = $request->duedate;
                $attachment= null;

                if($request->hasFile('attachment')){
                    $img = $request->file('attachment');
                    $filename = $img->getClientOriginalName();
                    $attachment = time().'_'.$filename;
                    Storage::disk('digitalocean')->putFileAs('public/compliance_library/', $img, $attachment, 'public');
                }

                $complainceData = [
                    'name'=>$name,
                    'description'=>$description,
                    'standard_name'=>$standard_name,
                    'assigned_to'=>$assigned_to,
                    'duedate'=> date('Y-m-d',strtotime($duedate)),
                    'attachment'=>$attachment,
                    'sub_institute_id'=>$sub_institute_id,
                    'created_by'=>$user_id,
                    'created_at'=>now()
                ];
                // echo "<pre>";print_r($complainceData);exit; 

                $insert = DB::table('master_compliance')->insert($complainceData);

                $res['status_code'] = 0;
                $res['message'] = "Failed to Add Details";

                if($insert){
                    $res['status_code'] = 1;
                    $res['message'] = "Details Added Successfully";
                }
                
             }
             //compliance library end
             else{
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
            $res['data'] = $this->getData($sub_institute_id);
        }
        
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
        $user_id = session()->get('user_id');

        if(in_array($type,["API","JSON"])){
            try {
                if (! $this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
                
                $sub_institute_id = $request->get('sub_institute_id');
                $syear = $request->get('syear');
                $user_id = $request->get('user_id');

                $validator = Validator::make($request->all(), [
                    'sub_institute_id' => 'required|numeric',
                    'syear'    => 'required|numeric',
                    'user_id'   => 'required|numeric',
                    'formName' => 'required',
                ]);
        
                if ($validator->fails()) {
                    $response['status'] = '0';
                    $response['message'] = $validator->messages();
                    return response()->json($response);
                } 
    
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }    
        }

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
             elseif($request->formName=="complaince_library"){
                    $name = $request->name;
                    $description = $request->description;
                    $standard_name = $request->standard_name;
                    $assigned_to = $request->assigned_to;
                    $duedate = $request->duedate;
                    $attachment= ($request->oldAttachment) ? $request->oldAttachment : null;

                    if($request->hasFile('attachment')){
                        // delete old file
                        $oldAttachment=$request->oldAttachment;
                        $file_path = 'public/compliance_library/' . $oldAttachment;
                        if (isset($request->oldAttachment) && Storage::disk('digitalocean')->exists($file_path)) {
                            Storage::disk('digitalocean')->delete($file_path);
                        } 

                        $img = $request->file('attachment');
                        $filename = $img->getClientOriginalName();
                        $attachment = time().'_'.$filename;
                        Storage::disk('digitalocean')->putFileAs('public/compliance_library/', $img, $attachment, 'public');
                    }

                    $complainceData = [
                        'name'=>$name,
                        'description'=>$description,
                        'standard_name'=>$standard_name,
                        'assigned_to'=>$assigned_to,
                        'duedate'=> date('Y-m-d',strtotime($duedate)),
                        'attachment'=>$attachment,
                        'sub_institute_id'=>$sub_institute_id,
                        'created_by'=>$user_id,
                        'updated_at'=>now()
                    ];
                    // echo "<pre>";print_r($complainceData);exit; 

                    $i = DB::table('master_compliance')->where('id',$id)->update($complainceData);
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
             if($request->formName=="complaince_library"){
                $i = DB::table('master_compliance')->where('id',$id)->update(['deleted_at'=>now()]);
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
