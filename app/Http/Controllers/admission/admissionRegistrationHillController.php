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
use PHPMailer\PHPMailer;

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
            ->leftJoin('admission_registration_v1 as ar', function ($join) use ($sub_institute_id)  {
                $join->on('ae.id','=','ar.enquiry_id')->where('ar.sub_institute_id',$sub_institute_id);
            })->leftJoin('tblstudent as ts', function ($join) {
                $join->whereRaw('ts.admission_id = ae.id AND ts.admission_year = ae.syear AND ts.sub_institute_id = ae.sub_institute_id');
            })->leftJoin('standard as s', function ($join) use ($sub_institute_id) {
                $join->whereRaw("s.id = ae.admission_standard AND s.sub_institute_id = '".$sub_institute_id."'");
            })
            ->selectRaw('ae.*,ar.*,ae.enquiry_no AS enquiry_no,COUNT(ts.id) AS total_student_count,ae.remarks AS enquiry_remark,s.name AS std_name,CONCAT_WS(" ",COALESCE(ae.first_name,"-"),COALESCE(ae.middle_name,"-"),COALESCE(ae.last_name,"-")) as full_name,ae.id as id')
            ->where('ae.sub_institute_id', $sub_institute_id)
            ->where('ae.syear', $syear)
            ->orderBy('ae.id','DESC')
            ->groupBy('ae.id')->get()->toArray();

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
       
        $i=0;
        if(!empty($students)){
            foreach($students as $enquiry_id=>$data){
                $pindate = isset($data["pint_date"]) ? Carbon::createFromFormat('d-m-Y',$data["pint_date"])->format('Y-m-d') : null;
                $condate = isset($data["conf_date"]) ? Carbon::createFromFormat('d-m-Y',$data["conf_date"])->format('Y-m-d') : null;

               $dataArr = [
                "enquiry_id"=> $enquiry_id,
                "enquiry_no"=> $data["enquiry_no"],
                "h_n"=> $data["hn"],
                "h_n_remarks"=> $data["hn_remarks"],
                "activity"=> $data["activity"],
                "p_int"=> $data["pint"],
                "p_int_date"=>$pindate,
                "p_int_time"=> $data["pint_time"],
                "p_int_remark"=> $data["p_int_remark"],
                "p_int_attandance"=> $data["p_int_attandance"],
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
              $nextYear = ((int) substr($syear, 2, 2)+1);
              $getStandard = DB::table('standard')->where(['id'=>$data['admission_standard'],'sub_institute_id'=>$sub_institute_id])->first();

              $htmlContent = view('admission.registrationHills.sendConfirmEmail', [
                'parent_date' => $pindate,
                'parent_time' => $data["pint_time"] ?? '',
                'aca_year'    => $syear.'-'.$nextYear,
                'admission_std'    => $getStandard->name ?? '-',
            ])->render();

              $emailRequest = new Request([
                'type' => 'webForm',
                'teacher_id' => $created_by,
                'sub_institute_id' =>$sub_institute_id,
                'token' => $_REQUEST['_token'],
                'all_email' => $data['email'],
                'subject' => 'Admission Confirmation',
                'syear' => $syear,
                'example_subject' => 'admission confirmation',
                'content' => $htmlContent
            ]);
            //   echo "<pre>";print_r($emailRequest);
              $sendEmailController = new send_email_parents_controller;
            //   $sendEmail = $sendEmailController->sendEmail($emailRequest);
              $sendEmail = $this->sendEmail($emailRequest);

            //   echo "Email Sent to >";print_r($sendEmail);
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

    public function registrationV1Reoprt(Request $request){
        $type = $request->type;
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
        ->leftJoin('admission_registration_v1 as ar', function ($join) use ($sub_institute_id)  {
            $join->on('ae.id','=','ar.enquiry_id')->where('ar.sub_institute_id',$sub_institute_id);
        })->leftJoin('tblstudent as ts', function ($join) {
            $join->whereRaw('ts.admission_id = ae.id AND ts.admission_year = ae.syear AND ts.sub_institute_id = ae.sub_institute_id');
        })->leftJoin('standard as s', function ($join) use ($sub_institute_id) {
            $join->whereRaw("s.id = ae.admission_standard AND s.sub_institute_id = '".$sub_institute_id."'");
        })
        ->selectRaw('ae.*,ar.*,ae.enquiry_no AS enquiry_no,COUNT(ts.id) AS total_student_count,ae.remarks AS enquiry_remark,s.name AS std_name,CONCAT_WS(" ",COALESCE(ae.first_name,"-"),COALESCE(ae.middle_name,"-"),COALESCE(ae.last_name,"-")) as full_name,ae.id as id')
        ->where('ae.sub_institute_id', $sub_institute_id)
        ->where('ae.syear', $syear)
        ->when($request->enq_no,function($q) use($request){
            $q->where('ar.enquiry_no',$request->enq_no);
        })
        ->when($request->mobile,function($q) use($request){
            $q->where('ae.mobile',$request->mobile);
        })
        ->when($request->hn,function($q) use($request){
            $q->where('ar.h_n',$request->hn);
        })
        ->when($request->pint,function($q) use($request){
            $q->where('ar.p_int',$request->pint);
        })
        ->when($request->pintdate,function($q) use($request){
            $q->where('ar.p_int_date',date('Y-m-d',strtotime($request->pintdate)));
        })
        ->when($request->conf,function($q) use($request){
            $q->where('ar.confi',$request->conf);
        })
        ->when($request->confidate,function($q) use($request){
            $q->where('ar.confi_date',date('Y-m-d',strtotime($request->confidate)));
        })
        ->when($request->fees_paid,function($q) use($request){
            $q->where('ar.paid',$request->fees_paid);
        })
        ->when($request->transport_fees,function($q) use($request){
            $q->where('ar.transport_fees',$request->transport_fees);
        })
        ->orderBy('ae.id','DESC')
        ->groupBy('ae.id')
        ->get()->toArray();

        $data = array_map(function ($value) {
            return (array) $value;
        }, $data);

        $res['mobile']=$request->mobile;
        $res['enq_no']=$request->enq_no;
        $res['hn']=$request->hn;
        $res['pint']=$request->pint;
        $res['pintdate']=$request->pintdate;
        $res['conf']=$request->conf;
        $res['confidate']=$request->confidate;
        $res['paid_fees']=$request->fees_paid;
        $res['transport_fees']=$request->transport_fees;
        // echo "<pre>";print_r($request->all());exit;
        $res['hnArr'] = [1,2,3];
        $res['pIntArr'] = ["C","I","C/A","NO","W/L"];
        $res['confArr'] = ["C","C/A","NO","W/L"];
        $res['yesNo'] = ["Yes","No"];
        $res['data'] = $data;
        
        return is_mobile($type, 'admission/registrationHills/report', $res, 'view');
    }

    public function sendEmail(Request $request)
    {
        // echo "<pre>";print_r($request->all_email);exit;
        $path = "";
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $user_id = session()->get('user_id');

        if ($request->hasFile('fileToUpload')) {
            $file = $request->file('fileToUpload');
            $originalname = $file->getClientOriginalName();
            $name = $request->get('fileToUpload').date('YmdHis');
            $ext = File::extension($originalname);
            $file_name = "email_".$name.'.'.$ext;
            $path = $file->storeAs('public/email', $file_name);
        }

        if ($path != "") {
            $filePath = storage_path()."/app/".$path;
            $path = $filePath;
        }

        $where_arr = [
            "sub_institute_id" => $sub_institute_id,
        ];
        $smtp_details = DB::table('smtp_details')
            ->where($where_arr)
            ->get();
            // return $smtp_details;

        if (count($smtp_details) > 0) {
            $emails = $request->all_email;
            $to_arr = explode(',', $emails);

            $subject = $request->example_subject;
            $message = $request->content;
            $attechment = $path;

            //$ip = Request::ip();
            $ip = $request->ip();
            $this->saveParentLog($emails, $message, $subject, $attechment, $ip, $syear, $user_id, $sub_institute_id);

            $from = $smtp_details[0]->gmail;
            $from_pass = $smtp_details[0]->password;

            $mail = new PHPMailer\PHPMailer();
            $mail->IsSMTP();
            $mail->isHTML(true);
            $mail->SMTPDebug = 0;
            $mail->SMTPAuth = true;
            $mail->SMTPSecure = "ssl";
            $mail->Host = $smtp_details[0]->server_address;
            $mail->Port = $smtp_details[0]->port;

            foreach ($to_arr as $id => $val) {
                $mail->AddAddress($val);
            }

            $mail->Username = $from;
            $mail->Password = $from_pass;
            $mail->SetFrom($from, $from);
            $mail->AddReplyTo($from, $from);
            $mail->addAttachment($attechment);
            $mail->Subject = $subject;
            $mail->Body = $message;
            $mail->AltBody = $message;

            if (! $mail->Send()) {
                $res = [
                    "status_code" => 0,
                    "message"     => "There is some error , while sending mail" . $mail->ErrorInfo,
                ];
            } else {
                $res = [
                    "status_code" => 1,
                    "message"     => "Email Sent",
                ];
            }
        } else {
            $res = [
                "status_code" => 1,
                "message"     => "You did not setup mail client.",
            ];
        }

        return response()->json($res);
    }

    public function saveParentLog($email, $msg, $subject, $attachment, $ip, $syear, $user_id, $sub_institute_id)
    {
        DB::table('email_sent_parents')->insert([
            'SYEAR'            => $syear,
            'EMAIL'            => $email,
            'SUBJECT'          => $subject,
            'EMAIL_TEXT'       => $msg,
            'ATTECHMENT'       => $attachment,
            'USER_ID'          => $user_id,
            'IP'               => $ip,
            'sub_institute_id' => $sub_institute_id,
        ]);
    }

}
