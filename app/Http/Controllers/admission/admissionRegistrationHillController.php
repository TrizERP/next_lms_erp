<?php

namespace App\Http\Controllers\admission;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;
use GenTux\Jwt\GetsJwtToken;
use App\Http\Controllers\easy_com\send_sms_parents\send_sms_parents_controller;
use App\Http\Controllers\easy_com\send_email_parents\send_email_parents_controller;
use Carbon\Carbon;

class admissionRegistrationHillController extends Controller
{
    //
    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get("sub_institute_id");
        $syear = session()->get("syear");
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

        $data = DB::table('admission_enquiry as ae')
            ->leftJoin('admission_registration_v1 as ar', function ($join) {
                $join->whereRaw('ae.id = ar.enquiry_id');
            })->leftJoin('tblstudent as ts', function ($join) {
                $join->whereRaw('ts.admission_id = ae.id AND ts.admission_year = ae.syear AND ts.sub_institute_id = ae.sub_institute_id');
            })->leftJoin('standard as s', function ($join) use ($sub_institute_id) {
                $join->whereRaw("s.id = ae.admission_standard AND s.sub_institute_id = '".$sub_institute_id."'");
            })
            ->selectRaw('ae.*,ar.*,ae.enquiry_no AS enquiry_no,COUNT(ts.id) AS total_student_count,ae.remarks AS enquiry_remark,s.name AS std_name,CONCAT_WS(" ",COALESCE(ae.first_name,"-"),COALESCE(ae.middle_name,"-"),COALESCE(ae.last_name,"-")) as full_name,ae.id as id')
            ->where('ae.sub_institute_id', $sub_institute_id)
            ->where('ae.syear', $syear)->groupBy('ae.id')->get()->toArray();
        $data = array_map(function ($value) {
            return (array) $value;
        }, $data);
        // echo "<pre>";print_r($data);exit;

       $res['hnArr'] = [1,2,3];
       $res['pIntArr'] = ["C","I","C/A","NO","W/L"];
       $res['confArr'] = ["C","C/A","NO","W/L"];
       $res['yesNo'] = ["Yes","No"];

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['data'] = $data;

        return is_mobile($type, 'admission/registrationHills/show', $res, 'view');
    }

    public function store(Request $request)
    {
        // return $request;exit;

        $type = $request->input('type');
        $students = $request->students;
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $created_by = session()->get('user_id');

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
        $pindate = isset($data["pint_date"]) ? Carbon::createFromFormat('d-m-Y',$data["pint_date"])->format('Y-m-d') : null;
        $condate = isset($data["conf_date"]) ? Carbon::createFromFormat('d-m-Y',$data["conf_date"])->format('Y-m-d') : null;
        $i=0;
        if(!empty($students)){
            foreach($students as $enquiry_id=>$data){
               $dataArr = [
                "enquiry_id"=> $enquiry_id,
                "enquiry_no"=> $data["enquiry_no"],
                "h_n"=> $data["hn"],
                "h_n_remarks"=> $data["hn_remarks"],
                "activity"=> $data["activity"],
                "p_int"=> $data["pint"],
                "p_int_date"=>$pindate,
                // "p_int_time"=> $data["pint_time"],
                "confi"=> $data["conf"],
                "confi_date"=> $condate,
                // "confi_time"=> $data["conf_time"],
                "paid"=> $data["paid"],
                "transport_fees"=> $data["transport"],
                "sub_institute_id"=>$sub_institute_id,
                "created_by"=>$created_by
               ];
        // echo "<pre>";print_r($dataArr);

              $checkExists = DB::table('admission_registration_v1')->where(["enquiry_id"=>$enquiry_id,"enquiry_no"=>$data['enquiry_no'],'sub_institute_id'=>$sub_institute_id])->first();

              if(empty($checkExists)){
                $dataArr['created_at'] = now();
                $insert = DB::table('admission_registration_v1')->insert($dataArr);
                $i=1;
              }else{
                $dataArr['updated_at'] = now();
                $update = DB::table('admission_registration_v1')->where('id',$checkExists->id)->update($dataArr);
                $i=1;
              }

              // check in admission form 
            //   $checkFormExists = DB::table('admission_form')->where(["enquiry_id"=>$enquiry_id,"enquiry_no"=>$data['enquiry_no'],'sub_institute_id'=>$sub_institute_id])->first();

            //   $formArr =[
            //     "enquiry_id"=> $enquiry_id,
            //     "enquiry_no"=> $data["enquiry_no"],
            //     "status"=>'OPEN',
            //     "followup_date"=>now(),
            //     'admission_standard'=>$data['admission_standard'],
            //     'created_by'=>session()->get('user_id'),
            //     "created_on"=>now(),
            //   ];
            //   if(empty($checkFormExists)){
            //     $formArr['created_on'] = now();
            //     $forminsert = DB::table('admission_form')->insert($formArr);
            //     $i=1;
            //   }else{
            //     $formupdate = DB::table('admission_form')->where('id',$checkFormExists->id)->update($formArr);
            //     $i=1;
            //   }
              $text = 'Your Admission has been confirmed';
              // send sms 
              $sendSmsController = new send_sms_parents_controller;
              $sendSms = $sendSmsController->sendSMS($data['mobile'], $text, $sub_institute_id);
              //send email;
          
              $emailRequest = Request::create('/', 'POST', [
                'type' => 'API',
                'teacher_id' => $created_by,
                'sub_institute_id' =>$sub_institute_id,
                'token' => $_REQUEST['_token'],
                'all_email' => $data['email'],
                'syear' => $syear,
                'example-subject' => 'admission confirmation',
                'content' => $text
            ]);
            
            //   echo "<pre>";print_r($emailRequest);
              $sendEmailController = new send_email_parents_controller;
              $sendEmail = $sendEmailController->sendEmail($emailRequest);
            }
        }
        // exit;
        if($i=1){
            $res["status_code"]=1;
            $res["message"]="Added Data Successfully";
          }
          else{
            $res["status_code"]=0;
            $res["message"]="Failed To Add Data";
          }

        return is_mobile($type, 'admission_registration_v1.index', $res);
    }
}
