<?php

namespace App\Http\Controllers\easy_com\send_sms_staff;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\easy_com\manage_sms_api\manage_sms_api;
use DB;
use GenTux\Jwt\JwtToken;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Support\Facades\Validator;

class send_sms_staff_controller extends Controller
{
    use GetsJwtToken;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $data['message'] = $data_arr['message'];
            }
        }

        //        $data['data'] = $this->getData();
        $alldata = DB::table("tbluserprofilemaster")
            ->where(['sub_institute_id' => session()->get('sub_institute_id')])
            ->get();
        foreach ($alldata as $object) {
            $arrays[] = (array) $object;
        }
        $data['data'] = $arrays;
        //        echo "<pre>";
        //        print_r($arrays);
        //        exit;
        //        $data['data'] = array();
        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "easy_comm/send_sms_staff/show", $data, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        //        echo "<pre>";
        //        print_r($_REQUEST);
        //        exit;

        $type = $request->input('type');

        $alldata = DB::table("tbluser")
            ->where([
                'sub_institute_id' => session()->get('sub_institute_id'),
                'user_profile_id' => $_REQUEST['staff']
            ])
            ->get();
        $data = array();
        foreach ($alldata as $object) {
            $data[] = (array) $object;
        }
        //        echo "<pre>";
        //        print_r($data);
        //        exit;


        //        $student_data = \App\Helpers\SearchStudent($_REQUEST['grade'], $_REQUEST['standard'], $_REQUEST['division']);
        $responce_arr['group_id'] = $_REQUEST['staff'];
        foreach ($data as $id => $arr) {
            $responce_arr['stu_data'][$id]['sr.no'] = $id + 1;
            $responce_arr['stu_data'][$id]['name'] = $arr['first_name'] . ' ' . $arr['middle_name'] . ' ' . $arr['last_name'];
            $responce_arr['stu_data'][$id]['student_id'] = $arr['id'];
            $responce_arr['stu_data'][$id]['mobile'] = $arr['mobile'];
        }

        return \App\Helpers\is_mobile($type, "easy_comm/send_sms_staff/add", $responce_arr, "view");
        //         echo "<pre>";
        //         print_r($student_data);
        //         exit;
    }

    public function GetStudentAnnouncement(Request $request)
    {
        try {
            if (!$this->jwtToken()->validate()) {
                $response = array('status' => '2', 'message' => 'Token Auth Failed', 'data' => array());
                return response()->json($response, 200);
            }
        } catch (\Exception $e) {
            $response = array('status' => '2', 'message' => $e->getMessage(), 'data' => array());
            return response()->json($response, 200);
        }
        $response = array('status' => '0', 'message' => '', 'data' => array());
        $validator =  Validator::make($request->all(), [
            'student_id' => 'required|numeric',
            'syear' => 'required|numeric',
            'sub_institute_id' => 'required|numeric',
            'type' => ["in:SMS,Notification"]
        ]);

        if ($validator->fails()) {
            $response['message'] = $validator->messages();
        } else {
            //process the request

            $sub_institute_id = $_REQUEST['sub_institute_id'];
            $syear = $_REQUEST['syear'];
            $student_id = $_REQUEST['student_id'];

            // $standard_id = $result[0]->standard_id;
            // $extra_condition = "";
            $type = $_REQUEST["type"];
            // if (isset($_REQUEST["type"]) && $_REQUEST["type"] != "") {
            //     $extra_condition = " AND type = '" . $_REQUEST["type"] . "'";
            // }

            $data_sql = "";
            if ($type == 'SMS') {
                $data_sql = "SELECT sms.*
            FROM sms_sent_parents sms
            WHERE sms.student_id = $student_id
            AND sms.syear = $syear
            AND sms.sub_institute_id = $sub_institute_id
            ";
            } else {
                $data_sql = "SELECT an.*
            FROM app_notification an
            WHERE an.STUDENT_ID = $student_id
            AND an.SYEAR = $syear
            AND an.SUB_INSTITUTE_ID = $sub_institute_id
            ";
            }
            $data_sql = preg_replace('/\n+/', '', $data_sql);
            $result_data = DB::select($data_sql);
            $response['data'] = $result_data;
            $response['status'] = '1';
            $response['message'] = 'Sucsess';
        }

        return json_encode($response);

        exit;
    }
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //        echo "<pre>";
        //        print_r($_REQUEST);
        //        exit;
        $text = $_REQUEST['smsText'];
        $responce = array();

        $alldata = DB::table("tbluser")
            ->where([
                'sub_institute_id' => session()->get('sub_institute_id'),
                'user_profile_id' => $_REQUEST['group_id']
            ])
            ->get();
        $data = array();
        foreach ($alldata as $object) {
            $data[] = (array) $object;
        }

        //        $student_data = \App\Helpers\SearchStudent($_REQUEST['grade'], $_REQUEST['standard'], $_REQUEST['division']);
        foreach ($_REQUEST['sendsms'] as $number => $on) {
            $responce = $this->sendSMS($number, $text);
            if ($responce['error'] == 1) {
                break;
            } else {
                $id = 0;
                foreach ($data as $id => $arr) {
                    if ($arr['mobile'] == $number) {
                        $id = $arr['id'];
                    }
                }
                $this->saveStaffLog($id, $text, $number);
            }
        }

        if ($responce['error'] == 1) {
            $res = array(
                "status_code" => 1,
                "message" => $responce['message'],
            );
        } else {
            $res = array(
                "status_code" => 1,
                "message" => "SMS Sent",
            );
        }

        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "send_sms_staff.index", $res, "redirect");

        //        echo "<pre>";
        //        print_r($responce);
        //        exit;
    }

    public function sendSMS($mobile, $text)
    {
        $sub_institute_id = session()->get('sub_institute_id');
        $data = manage_sms_api::where(['sub_institute_id' => $sub_institute_id])
            ->get()->first();
        // ->toArray();

        $isError = 0;
        $errorMessage = true;

        if ($data) {
            $data = $data->toArray();
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
        } else {
            $isError = 1;
            $errorMessage = "Please add api details first.";
        }



        $responce = array();
        if ($isError) {
            $responce = array('error' => 1, 'message' => $errorMessage);
        } else {
            $responce = array('error' => 0);
        }
        return $responce;
    }

    public function saveStaffLog($student_id, $msg, $number)
    {
        //        $sql = "INSERT INTO `SMS_SENT_PARENTS`
        //            (`SYEAR`, `STUDENT_ID`,
        //            `SMS_TEXT`, `SMS_NO`, `MODULE_NAME`) VALUES
        //            (" . UserSyear() . ", '$student_id', '$msg',
        //             '$number', 'SENT SMS PARENT')";
        DB::table('sms_sent_staff')->insert(
            array(
                'syear' => session()->get('syear'),
                'sub_institute_id' => session()->get('sub_institute_id'),
                'staff_id' => $student_id,
                'sms_text' => $msg,
                'sms_no' => $number,
                'module_name' => 'SENT SMS STAFF'
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
