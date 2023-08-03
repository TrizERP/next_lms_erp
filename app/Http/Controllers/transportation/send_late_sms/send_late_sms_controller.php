<?php

namespace App\Http\Controllers\transportation\send_late_sms;

use App\Http\Controllers\Controller;
use App\Models\easy_com\manage_sms_api\manage_sms_api;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class send_late_sms_controller extends Controller
{

    //
    public function index(Request $request)
    {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $data['message'] = $data_arr['message'];
            }
        }
        $data['data'] = [];
        $data['data']['ddShift'] = $this->ddShift();
        $type = $request->input('type');

        return is_mobile($type, "transportation/send_late_sms/show", $data, "view");
    }

    public function ddShift()
    {
        return DB::table('transport_school_shift')
            ->select('transport_school_shift.shift_title', 'transport_school_shift.id')
            ->where("transport_school_shift.sub_institute_id", session()->get('sub_institute_id'))
            ->pluck('shift_title', 'id');
    }

    public function create(Request $request)
    {
        $marking_period_id = session()->get('term_id');
        $student_data = DB::table("tblstudent as ts")
            ->join('transport_map_student as tm', function ($join) {
                $join->whereRaw("tm.student_id = ts.id");
            })
            ->selectRaw("concat_ws(' ',ts.first_name,ts.middle_name,ts.last_name) name, ts.mobile, ts.id as student_id")
            ->where("tm.sub_institute_id", "=", session()->get('sub_institute_id'))
            ->where("tm.syear", "=", session()->get('syear'))
            ->where(function ($q) {
                $q->where('tm.from_bus_id', $_REQUEST['bus'])->orWhere('tm.to_bus_id', $_REQUEST['bus']);
            })->where(function ($q) {
                $q->where('tm.from_shift_id', $_REQUEST['shift'])->orWhere('tm.to_shift_id', $_REQUEST['shift']);
            });
            // ->when($marking_period_id,function($query) use ($marking_period_id){
            //     $query->where('ts.marking_period_id',$marking_period_id);
            // });

        if (isset($_REQUEST['stop']) && $_REQUEST['stop'] != '') {
            $student_data = $student_data->whereIn('tm.to_stop', $_REQUEST['stop']);
        }

        $student_data = $student_data->get()->toArray();

        $responce_arr = [];
        foreach ($student_data as $id => $arr) {

            $responce_arr['stu_data'][$id]['sr.no'] = $id + 1;
            $responce_arr['stu_data'][$id]['name'] = $arr->name;
            $responce_arr['stu_data'][$id]['student_id'] = $arr->student_id;
            $responce_arr['stu_data'][$id]['mobile'] = $arr->mobile;
        }


        $type = $request->input('type');

        return is_mobile($type, "transportation/send_late_sms/add", $responce_arr, "view");
    }

    public function store(Request $request)
    {
        $text = $_REQUEST['smsText'];
        $responce = [];

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

        return is_mobile($type, "send_late_sms.index", $res, "redirect");
    }

    public function sendSMS($mobile, $text)
    {
        $sub_institute_id = session()->get('sub_institute_id');
        $data = manage_sms_api::where(['sub_institute_id' => $sub_institute_id])
            ->get()->first()->toArray();

        $isError = 0;
        $errorMessage = true;

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

        $responce = [];
        if ($isError) {
            $responce = ['error' => 1, 'message' => $errorMessage];
        } else {
            $responce = ['error' => 0];
        }

        return $responce;
    }

    public function saveParentLog($student_id, $msg, $number)
    {
        DB::table('sms_sent_parents')->insert([
            'SYEAR'            => session()->get('syear'),
            'STUDENT_ID'       => $student_id,
            'SMS_TEXT'         => $msg,
            'SMS_NO'           => $number,
            'MODULE_NAME'      => 'TANCEPOTATION LATE SMS',
            'sub_institute_id' => session()->get('sub_institute_id'),
        ]);
    }

}
