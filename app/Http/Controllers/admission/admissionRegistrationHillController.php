<?php

namespace App\Http\Controllers\admission;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;
use GenTux\Jwt\GetsJwtToken;
use App\Http\Controllers\easy_com\send_sms_parents\send_sms_parents_controller;
use App\Http\Controllers\easy_com\send_email_parents\send_email_parents_controller;
use App\Services\EmailTemplateService;
use Carbon\Carbon;
use PHPMailer\PHPMailer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class admissionRegistrationHillController extends Controller
{
    use GetsJwtToken;
    
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
        $res['pIntArr'] = ["I","NO","W/L"];
        $res['confArr'] = ["C","C/A","NO","W/L"]; // ,"C/A","NO","W/L"
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
                $skipOtherEmails = ($data["paid"] ?? "No") === "Yes";
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
              // If paid = Yes → send payment confirmation email
            if (isset($data["paid"]) && $data["paid"] == "Yes" && isset($data["email"])) {

                $nextYear = ((int) substr($syear, 2, 2) + 1);

                $this->sendTemplateEmail('admission_payment_confirmation', [
                    'page_type'    => 'paid',
                    'paid_status'  => $data["paid"],
                    'aca_year'     => $syear . '-' . $nextYear,
                    'enquiry_no'   => $data["enquiry_no"] ?? '',
                    'student_name' => $this->studentName($data),
                ], $data['email'], $created_by, $sub_institute_id, $syear);
            }

              // Check if transport is "Yes" and send the specific welcome email
              if(isset($data["transport"]) && $data["transport"] == "Yes" && isset($data['email'])) {
                  $this->sendTransportWelcomeEmail($data['email'], $created_by, $sub_institute_id, $syear, $data);
              }

              // send sms 
              $text = 'Your Admission has been confirmed';
              $sendSmsController = new send_sms_parents_controller;
              $sendSms = $sendSmsController->sendSMS($data['mobile'], $text, $sub_institute_id);
              
              //send email;
              // Layouts are resolved from Settings > Email Templates when the
              // institute has configured one; EmailTemplateService otherwise
              // falls back to the legacy blade for the same event.
              $nextYear = ((int) substr($syear, 2, 2) + 1);
              $getStandard = isset($data['admission_standard'])
                  ? DB::table('standard')->where(['id' => $data['admission_standard'], 'sub_institute_id' => $sub_institute_id])->first()
                  : null;
              $standard_id = $getStandard->id ?? null;

              $commonVars = [
                  'aca_year'      => $syear . '-' . $nextYear,
                  'admission_std' => $getStandard->name ?? '-',
                  'medium'        => $getStandard->medium ?? '-',
                  'student_name'  => $this->studentName($data),
                  'enquiry_no'    => $data['enquiry_no'] ?? '',
                  'mobile'        => $data['mobile'] ?? '',
                  'email'         => $data['email'] ?? '',
              ];

              if (!$skipOtherEmails && in_array($data['conf'], ["C", "C/A"]) && isset($condate) && isset($data['admission_standard']) && ($data["paid"] ?? "No") !== "Yes") {
                  $this->sendTemplateEmail('admission_confirmed', $commonVars + [
                      'page_type'   => 'confirm',
                      'conf_date'   => $condate,
                      'conf'        => $data["conf"] ?? '',
                      'parent_time' => '9:00 a.m. to 11:00 am OR 2:30 p.m. to 4:30 p.m.',
                  ], $data['email'], $created_by, $sub_institute_id, $syear, $standard_id, $data["conf"] ?? null);
              }
              elseif (!$skipOtherEmails && isset($data["pint_time"]) && isset($pindate) && isset($data['admission_standard']) && in_array($data["pint"], ["I"])) {
                  $this->sendTemplateEmail('parent_interaction', $commonVars + [
                      'page_type'   => 'parent',
                      'parent_date' => $pindate,
                      'pint'        => $data["pint"] ?? '',
                      'parent_time' => $data["pint_time"] ?? '',
                  ], $data['email'], $created_by, $sub_institute_id, $syear, $standard_id, $data["pint"] ?? null);
              }
              elseif (!$skipOtherEmails && isset($data['admission_standard']) && in_array($data["pint"], ["NO", "W/L"])) {
                  $this->sendTemplateEmail('parent_interaction', $commonVars + [
                      'page_type'   => 'parent',
                      'parent_date' => $pindate,
                      'pint'        => $data["pint"] ?? '',
                      'parent_time' => $data["pint_time"] ?? '',
                  ], $data['email'], $created_by, $sub_institute_id, $syear, $standard_id, $data["pint"] ?? null);
              }
              elseif (isset($data['admission_standard']) && in_array($data["conf"], ["NO", "W/L"])) {
                  $this->sendTemplateEmail('admission_result', $commonVars + [
                      'page_type'   => 'parent',
                      'parent_date' => $pindate,
                      'conf'        => $data["conf"] ?? '',
                      'conf_date'   => $condate,
                      // The legacy layout branches on $pint, so the confirmation
                      // status is passed through under both names.
                      'pint'        => $data["conf"] ?? '',
                      'parent_time' => $condate ?? '',
                  ], $data['email'], $created_by, $sub_institute_id, $syear, $standard_id, $data["conf"] ?? null);
              }
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

    /**
     * Send transport welcome email when transport is set to "Yes"
     */
    private function sendTransportWelcomeEmail($email, $created_by, $sub_institute_id, $syear, $studentData)
    {
        $nextYear = ((int) substr($syear, 2, 2)+1);

        return $this->sendTemplateEmail('transport_welcome', [
            'student_data' => $studentData,
            'aca_year'     => $syear.'-'.$nextYear,
            'student_name' => $this->studentName($studentData),
            'enquiry_no'   => $studentData['enquiry_no'] ?? '',
            'mobile'       => $studentData['mobile'] ?? '',
            'email'        => $email,
        ], $email, $created_by, $sub_institute_id, $syear);
    }

    /**
     * Render an email event through EmailTemplateService and hand it to SMTP.
     *
     * The body comes from the template the institute manages in
     * Settings > Email Templates; when none is saved the legacy blade declared
     * in config/email_templates.php is used, so existing mails keep working.
     */
    private function sendTemplateEmail($eventKey, array $vars, $email, $created_by, $sub_institute_id, $syear, $standardId = null, $statusCode = null)
    {
        $rendered = EmailTemplateService::render((int) $sub_institute_id, $eventKey, $vars, $standardId, $statusCode);

        if (empty($rendered) || empty($rendered['body'])) {
            Log::warning('No email template available, mail skipped', [
                'event'            => $eventKey,
                'sub_institute_id' => $sub_institute_id,
                'standard_id'      => $standardId,
                'status_code'      => $statusCode,
            ]);

            return null;
        }

        $emailRequest = new Request([
            'type'             => 'webForm',
            'teacher_id'       => $created_by,
            'sub_institute_id' => $sub_institute_id,
            'token'            => $_REQUEST['_token'] ?? '',
            'all_email'        => $email,
            'subject'          => $rendered['subject'],
            'syear'            => $syear,
            'example_subject'  => $rendered['subject'],
            'content'          => $rendered['body'],
            // Set when the template sends its letter as a PDF instead of inline.
            'attachment_path'  => $rendered['attachment'] ?? null,
            'attachment_name'  => $rendered['attachment_name'] ?? null,
        ]);

        try {
            return $this->sendEmail($emailRequest);
        } finally {
            if (!empty($rendered['attachment']) && is_file($rendered['attachment'])) {
                EmailTemplateService::cleanupAttachment($rendered['attachment']);
            }
        }
    }

    private function studentName(array $data): string
    {
        $name = trim(implode(' ', array_filter([
            $data['first_name'] ?? null,
            $data['middle_name'] ?? null,
            $data['last_name'] ?? null,
        ])));

        return $name !== '' ? $name : ($data['full_name'] ?? '');
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
        $res['pIntArr'] = ["I","NO","W/L"];
        $res['confArr'] = ["C","C/A","NO","W/L"]; // ,"C/A","NO","W/L"
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

        // A template that sends its letter as a PDF passes an absolute path.
        if ($path == "" && $request->filled('attachment_path') && is_file($request->get('attachment_path'))) {
            $path = $request->get('attachment_path');
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
            // Without this PHPMailer labels the body iso-8859-1, so UTF-8
            // characters such as the rupee sign arrive as mojibake.
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            $mail->SMTPDebug = 0;
            $mail->SMTPAuth = true;
            $mail->SMTPSecure = "tls";
            $mail->Host = $smtp_details[0]->server_address;
            $mail->Port = $smtp_details[0]->port;

            foreach ($to_arr as $id => $val) {
                $mail->AddAddress($val);
            }

            $mail->Username = $from;
            $mail->Password = $from_pass;
            $mail->SetFrom($from, $from);
            $mail->AddReplyTo($from, $from);
            if ($attechment != "") {
                // Name the attachment explicitly: PHPMailer otherwise falls back
                // to the file's basename, which carries our temp-file prefix.
                $mail->addAttachment($attechment, $request->get('attachment_name') ?: '');
            }
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