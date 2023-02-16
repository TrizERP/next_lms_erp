<?php

namespace App\Http\Controllers\transportation\send_late_sms;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;
use App\Models\easy_com\manage_sms_api\manage_sms_api;
use App\Models\transportation\send_late_sms\send_late_sms;

class send_late_sms_controller extends Controller {

    //
    public function index(Request $request) {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $data['message'] = $data_arr['message'];
            }
        }
        $data['data'] = array();
        $data['data']['ddShift'] = $this->ddShift();
        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "transportation/send_late_sms/show", $data, "view");
    }

    public function ddShift() {
        $std_div_map = DB::table('transport_school_shift')
                ->select('transport_school_shift.shift_title', 'transport_school_shift.id')
                ->where("transport_school_shift.sub_institute_id", session()->get('sub_institute_id'))
                ->pluck('shift_title', 'id');

        return $std_div_map;
    }

    public function create(Request $request) {
        // dd($_REQUEST);
        $stop_con = '';
        if(isset($_REQUEST['stop']) &&  $_REQUEST['stop'] != ''){
            $stop_con = " and tm.to_stop in (" . implode(',', $_REQUEST['stop']) . ")";
        }
        $student_data = DB::select(DB::raw("
                    select concat_ws(' ',ts.first_name,ts.middle_name,ts.last_name) name, ts.mobile, ts.id as student_id
                    from tblstudent ts
                    inner join transport_map_student tm on tm.student_id = ts.id
                    where tm.sub_institute_id = '" . session()->get('sub_institute_id') . "' 
                        and tm.syear = '" . session()->get('syear') . "' 
                        and (tm.from_bus_id = '" . $_REQUEST['bus'] . "' or tm.to_bus_id = '" . $_REQUEST['bus'] . "')
                        and (tm.from_shift_id = '" . $_REQUEST['shift'] . "' or tm.to_shift_id = '" . $_REQUEST['shift'] . "')
                        $stop_con
                "));

        $responce_arr = array();
        foreach ($student_data as $id => $arr) {

            $responce_arr['stu_data'][$id]['sr.no'] = $id + 1;
            $responce_arr['stu_data'][$id]['name'] = $arr->name;
            $responce_arr['stu_data'][$id]['student_id'] = $arr->student_id;
            $responce_arr['stu_data'][$id]['mobile'] = $arr->mobile;
        }


        $type = $request->input('type');

        return \App\Helpers\is_mobile($type, "transportation/send_late_sms/add", $responce_arr, "view");
    }

    public function store(Request $request) {
        $text = $_REQUEST['smsText'];
        $responce = array();

        foreach ($_REQUEST['sendsms'] as $student_id => $arr) {
            foreach ($arr as $number => $on) {
                $responce = $this->sendSMS($number, $text);
                if ($responce['error'] == 1) {
                    break;
                } else {
                    $this->saveParentLog($student_id, $text, $number);
                }
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
        return \App\Helpers\is_mobile($type, "send_late_sms.index", $res, "redirect");
    }

    public function sendSMS($mobile, $text) {
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

    public function saveParentLog($student_id, $msg, $number) {
        DB::table('sms_sent_parents')->insert(
                array(
                    'SYEAR' => session()->get('syear'),
                    'STUDENT_ID' => $student_id,
                    'SMS_TEXT' => $msg,
                    'SMS_NO' => $number,
                    'MODULE_NAME' => 'TANCEPOTATION LATE SMS',
                    'sub_institute_id' => session()->get('sub_institute_id')
                )
        );
    }

}
