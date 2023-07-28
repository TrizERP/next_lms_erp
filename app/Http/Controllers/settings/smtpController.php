<?php

namespace App\Http\Controllers\settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\easy_com\manage_sms_api\manage_sms_api;
use PHPMailer\PHPMailer;

use function App\Helpers\is_mobile;

class smtpController extends Controller
{
    public function index(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $res['data'] = $this->get_data();
        $type = $request->input('type');

        return is_mobile($type, "settings/smtp_setting/show", $res, "view");
    }

    public function create(Request $request)
    {
        return view('settings/smtp_setting/add');
    }

    public function get_data()
    {
        return DB::table('smtp_details')->where(['sub_institute_id' => session()->get('sub_institute_id')])->get();
    }

    public function store(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $data = [
            'gmail'            => $request['email'],
            'password'         => $request['password'],
            'server_address'   => $request['server_address'],
            'port'             => $request['port'],
            'sub_institute_id' => $sub_institute_id,
        ];

        DB::table('smtp_details')->insert($data);

        $res['status_code'] = "1";
        $res['message'] = "SMTP added successfully";

        $type = $request->input('type');

        return is_mobile($type, "smtp_setting.index", $res);
    }

    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $data = DB::table('smtp_details')->find($id);


        return is_mobile($type, "settings/smtp_setting/edit", $data, "view");
    }

    public function update(Request $request, $id)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data = [
            'gmail'            => $request['email'],
            'password'         => $request['password'],
            'server_address'   => $request['server_address'],
            'port'             => $request['port'],
            'sub_institute_id' => $sub_institute_id,
        ];

        DB::table('smtp_details')->where(["id" => $id])->update($data);

        $res = [
            "status_code" => 1,
            "message"     => "Data Saved",
        ];

        $type = $request->input('type');

        return is_mobile($type, "smtp_setting.index", $res, "redirect");
    }

    public function destroy(Request $request, $id)
    {
        DB::table('smtp_details')->where('id', $id)->delete();
        $res['status_code'] = "1";
        $res['message'] = "SMTP Setting deleted successfully";
        $type = "";

        return is_mobile($type, "smtp_setting.index", $res);
    }

    public function CheckEmail(Request $request)
    {
        $path = "";
        $type = $request->input('type');
        if ($type == "API") {
            $sub_institute_id = $_REQUEST['sub_institute_id'];
            //$syear = $_REQUEST['syear'];
            $user_id = $_REQUEST['teacher_id'];
            try {
                if (! $this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                    return response()->json($response, 401);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

                return response()->json($response, 401);
            }
        } else {
            $sub_institute_id = session()->get('sub_institute_id');
            $syear = session()->get('syear');
            $user_id = session()->get('user_id');
        }

     
        $where_arr = [
            "sub_institute_id" => $sub_institute_id
        ];//            "syear"=>$syear,
        $smtp_details = DB::table('smtp_details')
            ->where($where_arr)
            ->get();
        if (count($smtp_details) > 0) {
            $to_arr =$_REQUEST['to_email'];
            $subject = "Check SMTP Email";
            $message = "Test For SMTP Email is OK";

            $from = $smtp_details[0]->gmail;
            $from_pass = $smtp_details[0]->password;
       
            $mail = new PHPMailer\PHPMailer();
            $mail->IsSMTP();
            $mail->isHTML(true);
            $mail->SMTPDebug = 0;
            $mail->SMTPAuth = true;
            $mail->SMTPSecure = "tls";
            $mail->Host = $smtp_details[0]->server_address;
            $mail->Port = $smtp_details[0]->port;
            // $mail->SMTPDebug = 2;
            $mail->AddAddress($to_arr);
            $mail->Username = $from;
            $mail->Password = $from_pass;
            $mail->SetFrom($from, $from);
            $mail->AddReplyTo($from, $from);
            // $mail->addAttachment($attechment);
            $mail->Subject = $subject;            
            $mail->Body = $message;
            $mail->AltBody = $message;
            // echo "<pre>";print_r($mail);exit;
            if (! $mail->Send()) {
                $res = [
                    "status_code" => 0,
                    "message"     => "There is some error , while sending mail",
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
        $res['data'] = $this->get_data();
        $type = $request->input('type');
        return is_mobile($type, "settings/smtp_setting/show", $res, "view");
    }
}
