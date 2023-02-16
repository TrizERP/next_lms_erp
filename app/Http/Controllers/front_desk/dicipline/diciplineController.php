<?php

namespace App\Http\Controllers\front_desk\dicipline;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;
use GenTux\Jwt\JwtToken;
use GenTux\Jwt\GetsJwtToken;
use function App\Helpers\aut_token;
use function App\Helpers\sendNotification;


class diciplineController extends Controller {

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
	use GetsJwtToken;
	 
    public function index(Request $request) {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $data['message'] = $data_arr['message'];
            }
        }

//        $data['data'] = $this->getData();


        $data['data'] = array();
        //dd($data);
//        $data['data']['dd'] = $dd;
        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "front_desk/dicipline/show", $data, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request) {
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
            $responce_arr['stu_data'][$id]['standard_name'] = $arr['standard_name'];
            $responce_arr['stu_data'][$id]['division_name'] = $arr['division_name'];
        }
        $dd = DB::table('dicipline_dd')->pluck('message', 'id');
        $responce_arr['dd'] = $dd;
       // dd($responce_arr);

        return \App\Helpers\is_mobile($type, "front_desk/dicipline/add", $responce_arr, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {
//        echo "<pre>";
//        print_r($_REQUEST);
//        exit;
        $stu_arr = array();
        foreach ($_REQUEST['values']['stud_id'] as $student_id => $on) {
            $stu_arr[] = $student_id;
        }

        $sql = "select concat(first_name,' ',last_name) name 
                from tbluser 
                where id = " . $request->session()->get('user_id');
        $sql = preg_replace('/\n+/', '', $sql);
        $result = DB::select($sql);
        $name = $result[0]->name;

//        echo "<pre>";
//        print_r($stu_arr);
//        exit;
        foreach ($stu_arr as $id => $stu_id) {
            DB::table('dicipline')->insert(
                    array(
                        'syear' => session()->get('syear'),
                        'student_id' => $stu_id,
                        'name' => $name,
                        'dicipline' => $_REQUEST['values']['dd'][$stu_id],
                        'message' => $_REQUEST['values']['text'][$stu_id],
                        'date_' => date('Y-m-d'),
                        'sub_institute_id' => session()->get('sub_institute_id'),
                        'created_by' => session()->get('user_id'),
                        'created_at' => now(),
                        'updated_at' => now()
                    )
            );

            //START Send Notification Code
            $app_notification_content = array(
                'NOTIFICATION_TYPE' => 'Student Remarks',
                'NOTIFICATION_DATE' => date('Y-m-d'),
                'STUDENT_ID' => $stu_id,
                'NOTIFICATION_DESCRIPTION' => $_REQUEST['values']['text'][$stu_id],
                'STATUS' => 0,
                'SUB_INSTITUTE_ID' => session()->get('sub_institute_id'),                  
                'SYEAR' => session()->get('syear'),
                'CREATED_BY' => session()->get('user_id'),        
                'CREATED_IP' => $_SERVER['REMOTE_ADDR']          
            );
            sendNotification($app_notification_content);  
            //END Send Notification Code
        }
        $res = array(
            "status_code" => 1,
            "message" => "Dicipline Added",
        );

        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "dicipline.index", $res, "redirect");

        //        echo "<pre>";
        //        print_r($responce);
        //        exit;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id) {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id) {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id) {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id) {
        //
    }
	
	public function studentDisciplineAPI(Request $request) {
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
		$student_id = $request->input("student_id");
		$sub_institute_id = $request->input("sub_institute_id");
		$syear = $request->input("syear");

		if($student_id != "" && $sub_institute_id != "" && $syear != "")
		{				
			$data = DB::select("SELECT dicipline as discipline,message,date_ AS discipline_date
			FROM dicipline
			WHERE syear = '".$syear."' AND sub_institute_id = '".$sub_institute_id."' 
			AND student_id = '".$student_id."'				
			");
			
			$res['status'] = 1;
			$res['message'] = "Success";
			$res['data'] = $data;	
		}else{
			$res['status'] = 0;
			$res['message'] = "Parameter Missing";
		}
		//return  \App\Helpers\is_mobile($type, "implementation", $res);
		return json_encode($res);		
	}

}
