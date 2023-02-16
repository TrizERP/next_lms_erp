<?php
namespace App\Http\Controllers\easy_com\send_email_parents;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\easy_com\manage_sms_api\manage_sms_api;
use DB;
use PHPMailer\PHPMailer;
use GenTux\Jwt\JwtToken;
use GenTux\Jwt\GetsJwtToken;
use function App\Helpers\aut_token;


class send_email_parents_controller extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
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

//        $data['data'] = $this->getData();


        $data['data'] = array();
        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "easy_comm/send_email_parents/show", $data, "view");
    }

    //13.46
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        //    echo "<pre>";
        //    print_r($_REQUEST);
        //    exit;

        $type = $request->input('type');
        $student_data = \App\Helpers\SearchStudent($_REQUEST['grade'], $_REQUEST['standard'], $_REQUEST['division']);
        $responce_arr['grade'] = $_REQUEST['grade'];
        $responce_arr['standard'] = $_REQUEST['standard'];
        $responce_arr['division'] = $_REQUEST['division'];
        foreach ($student_data as $id => $arr) {
            $responce_arr['stu_data'][$id]['sr.no'] = $id + 1;
            $responce_arr['stu_data'][$id]['name'] = $arr['first_name'] . ' ' . $arr['middle_name'] . ' ' . $arr['last_name'];
            $responce_arr['stu_data'][$id]['student_id'] = $arr['student_id'];
            $responce_arr['stu_data'][$id]['mobile'] = $arr['mobile'];
            $responce_arr['stu_data'][$id]['email'] = $arr['email'];
        }

        return \App\Helpers\is_mobile($type, "easy_comm/send_email_parents/add", $responce_arr, "view");
//         echo "<pre>";
//         print_r($student_data);
//         exit;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //    echo "<pre>";
        //    print_r($_REQUEST);
        //    exit;
        // $text = $_REQUEST['sendsms'];
        $responce = array();
        // foreach($_REQUEST["sendsms"] as $email=>$value){
        foreach ($request->get("sendsms") as $email=>$value) {
            $responce[] = $email;
        }
        $type = $request->input('type');
        // return \App\Helpers\is_mobile($type, "easy_comm/send_email_parents/send_email", $responce, "redirect");
        return \App\Helpers\is_mobile($type, "easy_comm/send_email_parents/send_email", $responce, "view");
        // return \App\Helpers\is_mobile($type, "send_mail", $responce, "redirect");

        //        echo "<pre>";
        //        print_r($responce);
        //        exit;
    }

    public function sendEmail(Request $request)
    {
        // dd($request);
         //echo ('<pre>');print_r($_REQUEST);exit;
        // require_once(public_path().'/mailer/class.phpmailer.php');
        $path = "";
		$type = $request->input('type');
		if($type == "API")
		{
			$sub_institute_id = $_REQUEST['sub_institute_id'];
			$syear = $_REQUEST['syear'];
			$user_id = $_REQUEST['teacher_id'];
			try {
				if (!$this->jwtToken()->validate()) {
					$response = array('status' => '2', 'message' => 'Token Auth Failed', 'data' => array());
					return response()->json($response, 401);
				}
			} 
			catch (\Exception $e) {
				$response = array('status' => '2', 'message' => $e->getMessage(), 'data' => array());
				return response()->json($response, 401);
			}
		}else
		{
			$sub_institute_id = session()->get('sub_institute_id');
			$syear = session()->get('syear');
			$user_id = session()->get('user_id');
		}
		
        if ($request->hasFile('fileToUpload')) {
            $file = $request->file('fileToUpload');
            $originalname = $file->getClientOriginalName();
            $name = $request->get('fileToUpload').date('YmdHis');
            $ext = \File::extension($originalname);
            $file_name = "email_".$name . '.' . $ext;
            $path = $file->storeAs('public/email', $file_name);
        }

        if ($path != "") {
            $filePath = storage_path()."/app/".$path;
            $path = $filePath;
        }
        
        $where_arr = array(
            "sub_institute_id" => $sub_institute_id
        );
        $smtp_details = DB::table('smtp_details')
                        ->where($where_arr)
                        ->get();                        

        if (count($smtp_details) > 0) {
            $emails = $_REQUEST['all_email'];
            $to_arr = explode(',', $emails);

            $subject = $_REQUEST['example-subject'];
            $message = $_REQUEST['content'];
            $attechment = $path;

            $ip =  \Request::ip();
            $this->saveParentLog($emails, $message, $subject, $attechment,$ip,$syear,$user_id,$sub_institute_id);
            // echo ('<pre>');print_r($_REQUEST);
            // echo ('<pre>');print_r($smtp_details);exit;

            // $to_arr = array("keyurmodi143@gmail.com");

            // $emails = $_REQUEST['all_email'];
            // $to_arr = explode(',', $emails);

            // $to_arr = array("keyurmodi143@gmail.com");
            // $subject = $_REQUEST['example-subject'];
            // $message = $_REQUEST['content'];
            // $attechment = $path;
            // $from = 'trizinnovation2018@gmail.com';
            // $from_pass = '2018@Info$123';
            $from = $smtp_details[0]->gmail;
            $from_pass = $smtp_details[0]->password;

            $mail = new PHPMailer\PHPMailer();
            $mail->IsSMTP();
            $mail->isHTML(true);
            $mail->SMTPDebug = 0;
            $mail->SMTPAuth = true;
            $mail->SMTPSecure = "ssl";
            // $mail->Host = "smtp.gmail.com";
            $mail->Host = $smtp_details[0]->server_address;
            // $mail->Port = 465;
            $mail->Port = $smtp_details[0]->port;
            foreach ($to_arr as $id => $val) {
                $mail->AddAddress($val);
            }
            // $mail->AddAddress("keyurmodi143@gmail.com");
            $mail->Username = $from;
            $mail->Password = $from_pass;
            $mail->SetFrom($from, $from);
            $mail->AddReplyTo($from, $from);
            $mail->addAttachment($attechment);
            $mail->Subject = $subject;
            $mail->Body = $message;
            $mail->AltBody = $message;
            //$mail->Send();

            if(!$mail->Send())
            {
                $res = array(
                        "status_code" => 0,
                        "message" => "There is some error , while sending mail",
                    );
            }
            else{
                $res = array(
                        "status_code" => 1,
                        "message" => "Email Sent",
                    );
            }
        } else {
            $res = array(
                    "status_code" => 1,
                    "message" => "You did not setup mail client.",
                );
        }
                        
        return \App\Helpers\is_mobile($type, "send_email_parents.index", $res, "redirect");
    }

    public function sendSMS($mobile, $text)
    {
        $sub_institute_id = session()->get('sub_institute_id');
        $data = manage_sms_api::
                        where(['sub_institute_id' => $sub_institute_id])
                        ->get()->first()->toArray();

        $isError = 0;
        $errorMessage = true;

        $url = $data['url'] . $data['pram'] . $data['mobile_var'] . $mobile . $data['text_var'] . $text . $data['last_var'];

        $ch = curl_init();

        //Ignore SSL certificate verification
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $url);

        //get response
        $output = curl_exec($ch);

        //Print error if any
        if (curl_errno($ch)) {
            $isError = true;
            $errorMessage = curl_error($ch);
        }
        curl_close($ch);

        $responce = array();
        if ($isError) {
            $responce = array('error' => 1, 'message' => $errorMessage);
        } else {
            $responce = array('error' => 0);
        }
        return $responce;
    }

    public function saveParentLog($email, $msg, $subject, $attachment,$ip,$syear,$user_id,$sub_institute_id)
    {
        DB::table('email_sent_parents')->insert(
            array(
				'SYEAR' => $syear,
				'EMAIL' => $email,
				'SUBJECT' => $subject,
				'EMAIL_TEXT' => $msg,
				'ATTECHMENT' => $attachment,
				'USER_ID' => $user_id,
				'IP' => $ip,
				'sub_institute_id' => $sub_institute_id
				)
        );
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
		
}
