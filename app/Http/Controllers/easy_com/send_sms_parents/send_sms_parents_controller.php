<?php

namespace App\Http\Controllers\easy_com\send_sms_parents;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\easy_com\manage_sms_api\manage_sms_api;
use DB;
use GenTux\Jwt\JwtToken;
use GenTux\Jwt\GetsJwtToken;
use function App\Helpers\aut_token;


class send_sms_parents_controller extends Controller
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
        return \App\Helpers\is_mobile($type, "easy_comm/send_sms_parents/show", $data, "view");
    }

    //13.46
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
        $student_data = \App\Helpers\SearchStudent($_REQUEST['grade'], $_REQUEST['standard'], $_REQUEST['division']);
        $responce_arr['grade'] = $_REQUEST['grade'];
        $responce_arr['standard'] = $_REQUEST['standard'];
        $responce_arr['division'] = $_REQUEST['division'];
        foreach ($student_data as $id => $arr) {

            $responce_arr['stu_data'][$id]['sr.no'] = $id + 1;
            $responce_arr['stu_data'][$id]['name'] = $arr['first_name'] . ' ' . $arr['middle_name'] . ' ' . $arr['last_name'];
            $responce_arr['stu_data'][$id]['student_id'] = $arr['student_id'];
            $responce_arr['stu_data'][$id]['mobile'] = $arr['mobile'];
        }

        return \App\Helpers\is_mobile($type, "easy_comm/send_sms_parents/add", $responce_arr, "view");
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
               // echo "<pre>";
               // print_r($_REQUEST);
               // exit;
		$sub_institute_id = session()->get('sub_institute_id');	   
		$syear = session()->get('syear');	   
        $text = $_REQUEST['smsText'];
        $responce = array();
        $student_data = \App\Helpers\SearchStudent($_REQUEST['grade'], $_REQUEST['standard'], $_REQUEST['division']);
        foreach ($_REQUEST['sendsms'] as $number => $on) {
            $responce = $this->sendSMS($number, $text,$sub_institute_id);
            if ($responce['error'] == 1) {
                break;
            } else {
                $student_id = 0;
                foreach ($student_data as $id => $arr) {
                    if ($arr['mobile'] == $number) {
                        $student_id = $arr['student_id'];
                    }
                }
                $this->saveParentLog($student_id, $text, $number,$sub_institute_id,$syear);
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
        return \App\Helpers\is_mobile($type, "send_sms_parents.index", $res, "redirect");

        //        echo "<pre>";
        //        print_r($responce);
        //        exit;
    }

    public function sendSMS($mobile, $text,$sub_institute_id)
    {
        //$sub_institute_id = session()->get('sub_institute_id');
        $data = manage_sms_api::where(['sub_institute_id' => $sub_institute_id])
            ->get()->first();
        // ->toArray();
        $isError = 0;
        // if($data){
            
        //     echo '<pre>'; print_r($data); exit;
        // }
        if ($data) {
            $data = $data->toArray();
            $isError = 0;
            $errorMessage = true;

            $text = urlencode($text);
            $data['last_var'] = urlencode($data['last_var']);

            $url = $data['url'] . $data['pram'] . $data['mobile_var'] . $mobile . $data['text_var'] . $text . $data['last_var'];

            $ch = curl_init();
            // curl_setopt($ch, CURLOPT_URL, $url);
            // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            // $output = curl_exec($ch);

            // Ignore SSL certificate verification
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_URL, $url);
            $output = curl_exec($ch);

         // print "<pre>";
         // print_r(curl_getinfo($ch));
         // echo '<pre>';
         // print_r($_REQUEST);
         // echo 'out put '.$output ;
         // exit;

            //Print error if any
            if (curl_errno($ch)) {
                $isError = true;
                $errorMessage = curl_error($ch);
            }
            curl_close($ch);
        }else{
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

    public function saveParentLog($student_id, $msg, $number,$sub_institute_id,$syear)
    {
        //        $sql = "INSERT INTO `SMS_SENT_PARENTS` 
        //            (`SYEAR`, `STUDENT_ID`, 
        //            `SMS_TEXT`, `SMS_NO`, `MODULE_NAME`) VALUES 
        //            (" . UserSyear() . ", '$student_id', '$msg', 
        //             '$number', 'SENT SMS PARENT')";
        DB::table('sms_sent_parents')->insert(
            array(
                'SYEAR' => $syear,
                'STUDENT_ID' => $student_id,
                'SMS_TEXT' => $msg,
                'SMS_NO' => $number,
                'MODULE_NAME' => 'SENT SMS PARENT',
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
	
	public function teacherSendSmsParentsAPI(Request $request) {
		try {
            if (!$this->jwtToken()->validate()) {
                $response = array('status' => '2', 'message' => 'Token Auth Failed', 'data' => array());
                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = array('status' => '2', 'message' => $e->getMessage(), 'data' => array());
            return response()->json($response, 401);
        }
		
		$type = $request->input("type");
		$teacher_id = $request->input("teacher_id");
		$sub_institute_id = $request->input("sub_institute_id");
		$mobile_number = $request->input("mobile_number");
		$sms_text = $request->input("sms_text");
		$syear = $request->input("syear");
		
		if($teacher_id != "" && $sub_institute_id != "" && $sms_text != "" && count($mobile_number) > 0)
		{
			foreach ($mobile_number as $student_id => $number) {
				$response1 = $this->sendSMS($number, $sms_text,$sub_institute_id);
				if ($response1['error'] == 1) {
					break;
				} else {					
					$this->saveParentLog($student_id, $sms_text, $number,$sub_institute_id,$syear);
				}
			}			
			$res['status_code'] = 1;
			$res['message'] = "Successfully sent SMS";			
		}else{
			$res['status_code'] = 0;
			$res['message'] = "Parameter Missing";
		}
		
		return json_encode($res);
	}
}
