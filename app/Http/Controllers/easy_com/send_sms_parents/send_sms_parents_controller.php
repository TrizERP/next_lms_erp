<?php

namespace App\Http\Controllers\easy_com\send_sms_parents;

use App\Http\Controllers\Controller;
use App\Models\easy_com\manage_sms_api\manage_sms_api;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;


class send_sms_parents_controller extends Controller
{
    use GetsJwtToken;

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
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

        return is_mobile($type, "easy_comm/send_sms_parents/show", $data, "view");
    }

    //13.46

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Request $request)
    {
        $type = $request->input('type');
        $student_data = SearchStudent($_REQUEST['grade'], $_REQUEST['standard'], $_REQUEST['division']);
        $responce_arr['grade'] = $_REQUEST['grade'];
        $responce_arr['standard'] = $_REQUEST['standard'];
        $responce_arr['division'] = $_REQUEST['division'];

        foreach ($student_data as $id => $arr) {
            $responce_arr['stu_data'][$id]['sr.no'] = $id + 1;
            $responce_arr['stu_data'][$id]['name'] = $arr['first_name'].' '.$arr['middle_name'].' '.$arr['last_name'];
            $responce_arr['stu_data'][$id]['student_id'] = $arr['student_id'];
            $responce_arr['stu_data'][$id]['mobile'] = $arr['mobile'];
            $responce_arr['stu_data'][$id]['standard_name'] = $arr['standard_name'];
            $responce_arr['stu_data'][$id]['division_name'] = $arr['division_name'];
        }

        return is_mobile($type, "easy_comm/send_sms_parents/add", $responce_arr, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $text = $_REQUEST['smsText'];
        $responce = [];
        $student_data = SearchStudent($_REQUEST['grade'], $_REQUEST['standard'], $_REQUEST['division']);

        foreach ($_REQUEST['sendsms'] as $number => $on) {
            $responce = $this->sendSMS($number, $text, $sub_institute_id);
            if ($responce['error'] == 1) {
                break;
            } else {
                $student_id = 0;
                foreach ($student_data as $id => $arr) {
                    if ($arr['mobile'] == $number) {
                        $student_id = $arr['student_id'];
                    }
                }
                $this->saveParentLog($student_id, $text, $number, $sub_institute_id, $syear);
            }
        }

        if ($responce['error'] == 1) {
            $res = [
                "status_code" => 1,
                "message"     => $responce['message'],
            ];
        } else {
            $res = [
                "status_code" => 1,
                "message"     => "SMS Sent",
            ];
        }

        $type = $request->input('type');

        return is_mobile($type, "send_sms_parents.index", $res, "redirect");
    }

    public function sendSMS($mobile, $text, $sub_institute_id,$template_id='')
    {
        $data = manage_sms_api::where(['sub_institute_id' => $sub_institute_id])
            ->get()->first();

        if ($data) {
            $data = $data->toArray();
            $isError = 0;
            $errorMessage = true;

            $text = urlencode($text);
            //$data['last_var'] = urlencode($data['last_var']);
            /*
            if($template_id !=''){
                $data['last_var'] = $template_id;
            }
            */

            $url = $data['url'].$data['pram'].$template_id.$data['mobile_var'].$mobile.$data['text_var'].$text.$data['last_var'];
            //echo $url;
            //die();
            $ch = curl_init();

            // Ignore SSL certificate verification
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_URL, $url);
            $output = curl_exec($ch);

            //Print error if any
            if (curl_errno($ch)) {
                $isError = true;
                $errorMessage = curl_error($ch).'-'.curl_errno($ch);
            }
            curl_close($ch);
        } else {
            $isError = 1;
            $errorMessage = "Please add api details first.";
        }
        $responce = [];
        if ($isError) {
            $responce = ['error' => 1, 'message' => $errorMessage];
        } else {
            $responce = ['error' => 0];
        }

        return $responce;
    }

    /**
     * The same send as sendSMS() — same gateway row, same URL assembly — that also reports what the
     * provider answered.
     *
     * sendSMS() is left exactly as it was, because Communication → Send SMS to Parents depends on it
     * and it only ever reports a request that could not be made. A caller that has to tell the
     * person whether the provider took the message (the chatbot's fee reminders) needs the reply, and
     * a redirect or an error body is a refusal that sendSMS() calls a success.
     *
     * Two differences from sendSMS(), both about the URL and nothing else:
     *   - raw spaces in the saved parts (a signature such as "- School Name" kept in `last_var`) are
     *     written as %20, because libcurl refuses a URL containing them ("Malformed input to a URL
     *     function", curl error 3) and the request never leaves the server;
     *   - a redirect is not followed. Providers retire an old address by redirecting it to a web page
     *     that answers for a different service, and following it turns a refusal into noise.
     *
     * @return array{error:int, message?:string, http?:int, body?:string, redirect_host?:?string}
     */
    public function sendSMSDetailed($mobile, $text, $sub_institute_id, $template_id = '')
    {
        $data = manage_sms_api::where(['sub_institute_id' => $sub_institute_id])->get()->first();

        if (! $data) {
            return ['error' => 1, 'message' => 'Please add api details first.'];
        }

        $data = $data->toArray();
        $url = $data['url'].$data['pram'].$template_id.$data['mobile_var'].$mobile.$data['text_var'].urlencode($text).$data['last_var'];
        $url = str_replace(' ', '%20', $url);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_URL, $url);
        $body = curl_exec($ch);

        if (curl_errno($ch)) {
            $error = ['error' => 1, 'message' => curl_error($ch).'-'.curl_errno($ch)];
            curl_close($ch);

            return $error;
        }

        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $redirect = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        curl_close($ch);

        return [
            'error' => 0,
            'http' => $http,
            'body' => is_string($body) ? $body : '',
            // The host only: the full address carries the account's key.
            'redirect_host' => $redirect !== '' ? (parse_url($redirect, PHP_URL_HOST) ?: null) : null,
        ];
    }

    public function saveParentLog($student_id, $msg, $number, $sub_institute_id, $syear)
    {
        DB::table('sms_sent_parents')->insert([
            'SYEAR'            => $syear,
            'STUDENT_ID'       => $student_id,
            'SMS_TEXT'         => $msg,
            'SMS_NO'           => $number,
            'MODULE_NAME'      => 'SENT SMS PARENT',
            'sub_institute_id' => $sub_institute_id,
        ]);
    }


    public function teacherSendSmsParentsAPI(Request $request)
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
        $teacher_id = $request->input("teacher_id");
        $sub_institute_id = $request->input("sub_institute_id");
        $mobile_number = $request->input("mobile_number");
        $sms_text = $request->input("sms_text");
        $syear = $request->input("syear");

        if ($teacher_id != "" && $sub_institute_id != "" && $sms_text != "" && count($mobile_number) > 0) {
            foreach ($mobile_number as $student_id => $number) {
                $response1 = $this->sendSMS($number, $sms_text, $sub_institute_id);
                if ($response1['error'] == 1) {
                    break;
                } else {
                    $this->saveParentLog($student_id, $sms_text, $number, $sub_institute_id, $syear);
                }
            }

            $res['status_code'] = 1;
            $res['message'] = "Successfully sent SMS";
        } else {
            $res['status_code'] = 0;
            $res['message'] = "Parameter Missing";
        }

        return json_encode($res);
    }
}
