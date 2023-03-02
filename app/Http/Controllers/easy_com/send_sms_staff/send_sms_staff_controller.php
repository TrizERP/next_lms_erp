<?php

namespace App\Http\Controllers\easy_com\send_sms_staff;

use App\Http\Controllers\Controller;
use App\Models\easy_com\manage_sms_api\manage_sms_api;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function App\Helpers\is_mobile;

class send_sms_staff_controller extends Controller
{
    use GetsJwtToken;

    /**
     * Display a listing of the resource.
     *
     * @param  Request  $request
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
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

        $alldata = DB::table("tbluserprofilemaster")
            ->where(['sub_institute_id' => session()->get('sub_institute_id')])
            ->get();
        foreach ($alldata as $object) {
            $arrays[] = (array) $object;
        }
        $data['data'] = $arrays;

        $type = $request->input('type');

        return is_mobile($type, "easy_comm/send_sms_staff/show", $data, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param  Request  $request
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @return Response
     */
    public function create(Request $request)
    {
        $type = $request->input('type');

        $alldata = DB::table("tbluser")
            ->where([
                'sub_institute_id' => session()->get('sub_institute_id'),
                'user_profile_id'  => $_REQUEST['staff'],
            ])
            ->get();

        $data = [];
        foreach ($alldata as $object) {
            $data[] = (array) $object;
        }

        $responce_arr['group_id'] = $_REQUEST['staff'];
        foreach ($data as $id => $arr) {
            $responce_arr['stu_data'][$id]['sr.no'] = $id + 1;
            $responce_arr['stu_data'][$id]['name'] = $arr['first_name'].' '.$arr['middle_name'].' '.$arr['last_name'];
            $responce_arr['stu_data'][$id]['student_id'] = $arr['id'];
            $responce_arr['stu_data'][$id]['mobile'] = $arr['mobile'];
        }

        return is_mobile($type, "easy_comm/send_sms_staff/add", $responce_arr, "view");
    }

    public function GetStudentAnnouncement(Request $request)
    {
        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 200);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 200);
        }
        $response = ['status' => '0', 'message' => '', 'data' => []];

        $validator = Validator::make($request->all(), [
            'student_id'       => 'required|numeric',
            'syear'            => 'required|numeric',
            'sub_institute_id' => 'required|numeric',
            'type'             => ["in:SMS,Notification"],
        ]);

        if ($validator->fails()) {
            $response['message'] = $validator->messages();
        } else {
            //process the request

            $sub_institute_id = $_REQUEST['sub_institute_id'];
            $syear = $_REQUEST['syear'];
            $student_id = $_REQUEST['student_id'];

            $type = $_REQUEST["type"];

            $data_sql = "";
            if ($type == 'SMS') {
                $result_data = DB::table('sms_sent_parents')
                    ->where('student_id', $student_id)
                    ->where('syear', $syear)
                    ->where('sub_institute_id', $sub_institute_id)->get()->toArray();
            } else {
                $result_data = DB::table('app_notification')
                    ->where('STUDENT_ID', $student_id)
                    ->where('SYEAR', $syear)
                    ->where('SUB_INSTITUTE_ID', $sub_institute_id)->get()->toArray();
            }

            $response['data'] = $result_data;
            $response['status'] = '1';
            $response['message'] = 'Sucsess';
        }

        return json_encode($response);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @return Response
     */
    public function store(Request $request)
    {
        $text = $_REQUEST['smsText'];
        $responce = [];

        $alldata = DB::table("tbluser")
            ->where([
                'sub_institute_id' => session()->get('sub_institute_id'),
                'user_profile_id'  => $_REQUEST['group_id'],
            ])->get();
        $data = [];

        foreach ($alldata as $object) {
            $data[] = (array) $object;
        }

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

        return is_mobile($type, "send_sms_staff.index", $res, "redirect");
    }

    public function sendSMS($mobile, $text)
    {
        $sub_institute_id = session()->get('sub_institute_id');
        $data = manage_sms_api::where(['sub_institute_id' => $sub_institute_id])
            ->get()->first();

        $isError = 0;
        $errorMessage = true;

        if ($data) {
            $data = $data->toArray();
            $url = $data['url'].$data['pram'].$data['mobile_var'].$mobile.$data['text_var'].$text.$data['last_var'];

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


        $responce = [];
        if ($isError) {
            $responce = ['error' => 1, 'message' => $errorMessage];
        } else {
            $responce = ['error' => 0];
        }

        return $responce;
    }

    public function saveStaffLog($student_id, $msg, $number)
    {
        DB::table('sms_sent_staff')->insert([
            'syear'            => session()->get('syear'),
            'sub_institute_id' => session()->get('sub_institute_id'),
            'staff_id'         => $student_id,
            'sms_text'         => $msg,
            'sms_no'           => $number,
            'module_name'      => 'SENT SMS STAFF',
        ]);
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
}
