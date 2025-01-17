<?php

namespace App\Http\Controllers\lms\curriculum;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use Illuminate\Support\Facades\Validator;
use GenTux\Jwt\GetsJwtToken;
use GenTux\Jwt\JwtToken;
use DB;

class curriculumLessonplanController extends Controller
{
    use GetsJwtToken;
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $user_id = session()->get('user_id');
        $user_profile = session()->get('user_profile_name');
        
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
            $user_id = $request->get('user_id'); 
            $user_profile = $request->get('user_profile_name'); 
            $syear = $request->get('syear');   
            $checkValidation = $this->checkValidator($request);
            if($checkValidation['status']==0){
                return response()->json($checkValidation);
            }         
        }

        $res['teachersList'] = DB::table('tbluser as u')
                    ->join('tbluserprofilemaster as up','up.id','=','u.user_profile_id')
                    ->selectRaw('u.id,concat_ws(" ",COALESCE(u.first_name),COALESCE(u.middle_name),COALESCE(u.last_name)) as full_name')
                    ->where(['u.sub_institute_id'=>$sub_institute_id,'u.status'=>1])
                    ->whereIn('up.name',['Teacher','LMS Teacher'])
                    ->when(in_array($user_profile,['Teacher','LMS Teacher']), function($q) use($user_id){
                        $q->where('u.id',$user_id);
                    })
                    ->get()->toArray();
                    
        $res['compeletion_status'] = ["Yes","No"]; 
        
        if($request->has('search_data') && $request->search_data==1){
            $res['searched_data'] = $this->getDetails($request,$sub_institute_id,$syear);
            $res['teacher_id'] = $request->teacher_id;
            $res['from_date'] = $request->from_date;
            $res['to_date'] = $request->to_date;
            $res['completion_status'] = $request->completion_status;
        }
        // echo "<pre>";print_r($res);exit;

        return is_mobile($type, 'lms/lms_curriculum/lessonPlan', $res, "view");
    }

    
     /**
     * store the resource.
     *
     * @return Response
     */
    public function store(Request $request)
    {
        
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $user_id = session()->get('user_id');
        $user_profile = session()->get('user_profile_name');
        
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
            $user_id = $request->get('user_id'); 
            $user_profile = $request->get('user_profile_name'); 
            $syear = $request->get('syear');      
            $checkValidation = $this->checkValidator($request);
            if($checkValidation['status']==0){
                return response()->json($checkValidation);
            }      
        }
        // echo "<pre>";print_r($request->all());exit; 
        $i=0; 
        foreach ($request->checkedValue as $key => $id) {
            $status = isset($request->completion_status[$key]) ? $request->completion_status[$key] : 'No';
            $reasons = isset($request->reasons[$key]) ? $request->reasons[$key] : null;
            $date = isset($request->completeion_date[$key]) ? $request->completeion_date[$key] : null;

            $update = DB::table('lessonplan')->where('id',$id)->update([
                'completion_status'=>$status,
                'completion_date'=>$date,
                'reasons'=>$reasons,
                'updated_by'=>$user_id,
                'updated_at'=>now()
            ]);

            if($update){
                $i++;
            }
        }      

        if($i==0){
            $res['status'] = "0";
            $res['message'] = "Failed to Add Data!";
        }else{
            $res['status'] = "1";
            $res['message'] = "Data Added Successfully !";
        }

        return is_mobile($type, 'curriculum_lessonplan.index', $res);
    }

    public function checkValidator($request){
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|numeric',
            'syear' => 'required|numeric',
            'user_id' => 'required|numeric',
            'user_profile_name' => 'required',
        ]);

        if ($validator->fails()) {
            $response['status'] = '0';
            $response['message'] = $validator->messages();
        }else{
            $response['status'] = '1';
            $response['message'] = 'success';
        }
        return $response;
    }

    public function getDetails($request,$sub_institute_id,$syear){

        $teacher_id = $request->teacher_id;
        $from_date = $request->from_date;
        $to_date = $request->to_date;
        $completion_status = $request->completion_status;

        $searched_data = DB::table('lessonplan as lp')
        ->join('standard as std',function($join){
            $join->on('std.id','=','lp.standard_id')->on('std.sub_institute_id','=','lp.sub_institute_id');
        })
        ->join('sub_std_map as ssm',function($join){
            $join->on('ssm.subject_id','=','lp.subject_id')->on('ssm.sub_institute_id','=','lp.sub_institute_id');
        })
        ->selectRaw('lp.*,ssm.display_name as subject_name,std.name as standard_name,(SELECT concat_ws(" ",COALESCE(first_name),COALESCE(middle_name),COALESCE(last_name)) FROM tbluser WHERE id=lp.teacher_id) as teacher_name')
        ->where(['lp.sub_institute_id'=>$sub_institute_id,'lp.syear'=>$syear])
        ->when($teacher_id!='',function($q) use($teacher_id){
            $q->where('lp.teacher_id',$teacher_id);
        })
        ->when($completion_status!="",function($q) use($completion_status){
            $q->where('lp.completion_status',$completion_status);
        })
        ->when($from_date!='' && $to_date!='',function($q) use($from_date,$to_date){
            $q->whereBetween('lp.school_date',[$from_date,$to_date]);
        })
        ->groupBy('lp.id')
        ->get()->toArray();

       return $searched_data;
    }
    
}
