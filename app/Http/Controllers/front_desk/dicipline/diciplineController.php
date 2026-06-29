<?php

namespace App\Http\Controllers\front_desk\dicipline;

use App\Http\Controllers\Controller;
use App\Vendor\Jwt\GetsJwtToken;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;
use function App\Helpers\sendNotification;
use Illuminate\Support\Facades\Session;
use PHPMailer\PHPMailer;

class diciplineController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    use GetsJwtToken;

    public function index(Request $request)
    {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $data['message'] = $data_arr['message'];
            }
        }

        $data['data'] = array();
        $type = $request->input('type');
        // added on 07-03-2025 for standalone modules start
        // http://127.0.0.1:8000/dicipline_alone?type=webForm&sub_institute_id=254
        if($type=='webForm'){
            $sub_institute_id = $request->get('sub_institute_id');
            $data['syears'] = DB::table('academic_year')->where('sub_institute_id',$sub_institute_id)->orderBy('sort_order')->groupBy('syear')->get()->toArray();
            $data['gradeList'] = DB::table('academic_section')->where('sub_institute_id',$sub_institute_id)->orderBy('sort_order')->get()->toArray();
            $data['syear'] = Date('Y');
            $data['profiles'] = DB::table("tbluser as tu")
            ->join('tbluserprofilemaster as tup','tup.id','=','tu.user_profile_id')
            ->selectRaw("concat(tu.first_name,' ',tu.last_name) name,tu.id")
            ->where("tu.sub_institute_id", "=", $sub_institute_id)
            ->where('tu.status',1)  
            ->where('tup.name','Watchman')
            ->get()->toArray();
            // echo "<pre>";print_r($data);exit;

            return is_mobile($type, "front_desk/dicipline/standalone", $data, "view");
        }
        // added on 07-03-2025 for standalone modules end
        return is_mobile($type, "front_desk/dicipline/show", $data, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param Request $request
     * @return false|Application|Factory|View|RedirectResponse|string
     */
    public function create(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear'); 

        if($type=='webForm'){
            $sub_institute_id = $request->get('sub_institute_id');
            $syear = $request->get('syear'); 
        }
        $grade_id = isset($_REQUEST['grade']) ? $_REQUEST['grade'] : '';
        $standard = isset($_REQUEST['standard']) ? $_REQUEST['standard'] : '';
        $division = isset($_REQUEST['division']) ? $_REQUEST['division'] : '';

        $student_data = SearchStudent($grade_id, $standard, $division,$sub_institute_id,$syear, "", "", "", "", $request->grNo);

        $responce_arr['grade'] = $grade_id;
        $responce_arr['standard'] = $standard;
        $responce_arr['division'] = $division;
        foreach ($student_data as $id => $arr) {

            $responce_arr['stu_data'][$id]['sr.no'] = $id + 1;
            $responce_arr['stu_data'][$id]['name'] = $arr['first_name'].' '.$arr['middle_name'].' '.$arr['last_name'];
            $responce_arr['stu_data'][$id]['student_id'] = $arr['student_id'];
            $responce_arr['stu_data'][$id]['mobile'] = $arr['mobile'];
            $responce_arr['stu_data'][$id]['standard_name'] = $arr['standard_name'];
            $responce_arr['stu_data'][$id]['division_name'] = $arr['division_name'];
        }
        $dd = DB::table('dicipline_dd')->where('sub_institute_id',$sub_institute_id)->whereNull('deleted_at')->pluck('message', 'id');
        // echo "<pre>";print_r($dd);exit;
        if(count($dd)==0){
            $dd = DB::table('dicipline_dd')->where('sub_institute_id',0)->whereNull('deleted_at')->pluck('message', 'id');
        }
        $responce_arr['dd'] = $dd;
        if($type=='webForm'){
            $responce_arr['syears'] = DB::table('academic_year')->where('sub_institute_id',$sub_institute_id)->orderBy('sort_order')->groupBy('syear')->get()->toArray();
            $responce_arr['gradeList'] = DB::table('academic_section')->where('sub_institute_id',$sub_institute_id)->orderBy('sort_order')->get()->toArray();
            $responce_arr['profiles'] = DB::table("tbluser as tu")
            ->join('tbluserprofilemaster as tup','tup.id','=','tu.user_profile_id')
            ->selectRaw("concat(tu.first_name,' ',tu.last_name) name,tu.id")
            ->where("tu.sub_institute_id", "=", $sub_institute_id)
            ->where('tu.status',1)  
            ->where('tup.name','Watchman')
            ->get()->toArray();
            $responce_arr['syear'] = $request->syear;
            $responce_arr['grade'] = $request->grade;
            $responce_arr['standard'] = $request->standard;
            $responce_arr['division'] = $request->division;
            $responce_arr['user_id'] = $request->user_id;
            $responce_arr['grno'] = $request->grNo;

            // echo "<pre>";print_r($responce_arr);exit;

            return is_mobile($type, "front_desk/dicipline/standalone", $responce_arr, "view");
        }
        return is_mobile($type, "front_desk/dicipline/add", $responce_arr, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
            // echo "<pre>";print_r($request->all());exit;
        $type = $request->type;
        $user_id = session()->get('user_id');
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        if($type=='webForm'){
            $user_id = 0 ;
            $sub_institute_id = $request->get('sub_institute_id');
            $syear = $request->get('syear');
            $user_id = $request->get('user_id');
            // echo "<pre>";print_r($request->all());exit;
        }

        $stu_arr = [];
        $student_ids = $_REQUEST['values']['stud_id'] ?? [];
        
        foreach ($student_ids as $student_id => $on) {
            $stu_arr[] = $student_id;
        }

        $result = DB::table("tbluser")
            ->selectRaw("concat(first_name,' ',last_name) name")
            ->where("id", "=", $user_id)
            ->where('status',1)  // 23-04-24 by uma
            ->get()->toArray();
            
        $name = $result[0]->name;
        // echo("<pre>");print_r($name);exit;
        $i=0;
        foreach ($stu_arr as $id => $stu_id) {
            $insert = DB::table('dicipline')->insert([
                'syear'            => $syear,
                'student_id'       => $stu_id,
                'name'             => $name,
                'dicipline'        => $_REQUEST['values']['dd'][$stu_id],
                'message'          => $_REQUEST['values']['text'][$stu_id],
                'flag'             => $_REQUEST['values']['flag'][$stu_id] ?? 0,
                'date_'            => date('Y-m-d'),
                'sub_institute_id' => $sub_institute_id,
                'created_by'       => $user_id,
                'created_at'       => now(),
            ]);

            if($insert){
                $i++;
            }

            //START Send Notification Code
            $flag_text = '';
            $flag_value = $_REQUEST['values']['flag'][$stu_id] ?? 0;
            if($flag_value == 1) $flag_text = 'Positive';
            elseif($flag_value == -1) $flag_text = 'Negative';

            $notification_desc = $_REQUEST['values']['text'][$stu_id];
            if(!empty($flag_text)) {
                $notification_desc .= " (FLAG: {$flag_text})";
            }

            $app_notification_content = [
                'NOTIFICATION_TYPE'        => 'Student Remarks',
                'NOTIFICATION_DATE'        => date('Y-m-d'),
                'STUDENT_ID'               => $stu_id,
                'NOTIFICATION_DESCRIPTION' => $notification_desc,
                'STATUS'                   => 0,
                'SUB_INSTITUTE_ID'         => $sub_institute_id,
                'SYEAR'                    => $syear,
                'CREATED_BY'               => $user_id,
                'CREATED_IP'               => $_SERVER['REMOTE_ADDR'],
            ];
            sendNotification($app_notification_content);
            //END Send Notification Code

            //START Send Parent Email Notification
            // 01-05-2026 Stop Mail call from Abhi
            //$this->sendParentEmailNotification($stu_id, $flag_value, $_REQUEST['values']['text'][$stu_id], $sub_institute_id, $syear);
            //END Send Parent Email Notification
        }
        $res = [
            "status_code" => 1,
            "message"     => "Dicipline Added",
        ];

        if($type=='webForm'){
            if($i!=0){
                Session::flash('status', 1); 
                Session::flash('message', 'Dicipline Added!'); 
            }else{
                Session::flash('status', 0); 
                Session::flash('message', 'Dicipline Added!'); 
            }

            return redirect('dicipline_alone?type=webForm&sub_institute_id='.$sub_institute_id);
        }
        return is_mobile($type, "dicipline.index", $res, "redirect");
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return void
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return void
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return void
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return void
     */
    public function destroy($id)
    {
        //
    }

    public function studentDisciplineAPI(Request $request)
    {
        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $type = $request->input("type");
        $student_id = $request->input("student_id");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");

        if ($student_id != "" && $sub_institute_id != "" && $syear != "") {
            
            $data = DB::table("dicipline")
                ->selectRaw('dicipline as discipline,message,date_ AS discipline_date,flag')
                ->where("syear", "=", $syear)
                ->where("sub_institute_id", "=", $sub_institute_id)
                ->where("student_id", "=", $student_id)
                ->get()->toArray();
                
            $res['status'] = 1;
            $res['message'] = "Success";
            $res['data'] = $data;
        } else {
            $res['status'] = 0;
            $res['message'] = "Parameter Missing";
        }

        return json_encode($res);
    }

    private function sendParentEmailNotification($student_id, $flag, $message, $sub_institute_id, $syear)
    {
        // Get student details
        $student = DB::table('tblstudent')->where('id', $student_id)->first();
        if (!$student || empty($student->email)) {
            return; // No email to send
        }

        // Get SMTP details
        $smtp_details = DB::table('smtp_details')
            ->where('sub_institute_id', $sub_institute_id)
            ->first();

        if (!$smtp_details) {
            return; // No SMTP config
        }

        $flag_text = '';
        if ($flag == 1) $flag_text = 'Positive';
        elseif ($flag == -1) $flag_text = 'Negative';

        $subject = "Discipline Notification for {$student->first_name} {$student->last_name}";
        $body = "
        <p>Dear Parent,</p>
        <p>This is to inform you about a discipline entry for your child:</p>
        <ul>
            <li><strong>Student Name:</strong> {$student->first_name} {$student->middle_name} {$student->last_name}</li>
            <li><strong>Date:</strong> " . date('d-m-Y') . "</li>
            <li><strong>Message:</strong> {$message}</li>
            <li><strong>FLAG:</strong> {$flag_text} ({$flag})</li>
        </ul>
        <p>Regards,<br>School Administration</p>
        ";

        try {
            $mail = new PHPMailer\PHPMailer();
            $mail->IsSMTP();
            $mail->isHTML(true);
            $mail->SMTPDebug = 0;
            $mail->SMTPAuth = true;
            $mail->SMTPSecure = "tls";
            $mail->Host = $smtp_details->server_address;
            $mail->Port = $smtp_details->port;

            $mail->Username = $smtp_details->gmail;
            $mail->Password = $smtp_details->password;
            $mail->SetFrom($smtp_details->gmail, 'School Administration');
            $mail->AddReplyTo($smtp_details->gmail, 'School Administration');
            $mail->AddAddress($student->email);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = strip_tags($body);

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

            // Log the email sent
            DB::table('email_sent_parents')->insert([
                'SYEAR' => $syear,
                'EMAIL' => $student->email,
                'SUBJECT' => $subject,
                'EMAIL_TEXT' => $body,
                'ATTECHMENT' => '',
                'USER_ID' => session()->get('user_id', 0),
                'IP' => $_SERVER['REMOTE_ADDR'] ?? '',
                'sub_institute_id' => $sub_institute_id,
                'CREATED_AT' => now(),
            ]);
        } catch (\Exception $e) {
            // Log error if needed
        }
        return response()->json($res);
    }
}
