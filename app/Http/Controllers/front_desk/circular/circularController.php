<?php

namespace App\Http\Controllers\front_desk\circular;

use App\Http\Controllers\Controller;
use DB;
use File;
use GenTux\Jwt\GetsJwtToken;
use GenTux\Jwt\JwtToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use function App\Helpers\sendNotification;
use function App\Helpers\send_FCM_Notification;
use App\Models\school_setup\SchoolModel;
use App\Models\front_desk\circular\circular;

// namespace GenTux\Jwt\Exceptions;

class circularController extends Controller {
	use GetsJwtToken;

	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function index(Request $request) {
		if (session()->has('data')) {
			// check if it exists
			$data_arr = session('data'); // to retrieve value
			if (isset($data_arr['message'])) {
				$school_data['message'] = $data_arr['message'];
			}
		}

		$data = $this->getData();
		$school_data['data'] = $data['data'];
		$school_data['circular_type'] = $data['circular_type'];
		
		// echo "<pre>";
		// print_r($school_data);
		// exit;
		//        $school_data['data'] = array();
		$type = $request->input('type');
		return \App\Helpers\is_mobile($type, "front_desk/circular/show", $school_data, "view");
	}

	function getData() {
		$sql = "SELECT c.*,s.name std_name,t.type as circular_type,d.name div_name 
            FROM circular c
            INNER JOIN standard s on s.id = c.standard_id
			INNER JOIN circular_type t on t.id = c.type
            LEFT JOIN division d on d.id = c.division_id AND d.sub_institute_id = c.sub_institute_id			
            WHERE c.syear = '" . session()->get('syear') . "'
                and c.sub_institute_id = '" . session()->get('sub_institute_id') . "'
                order by  c.id DESC limit 100";

		$sql = preg_replace('/\n+/', '', $sql);
		$result['data'] = DB::select($sql);
		
		$sql1 = "SELECT * FROM circular_type ";		
		$result['circular_type'] = DB::select($sql1);
		
		return $result;
	}
	public function fetchData(Request $request) {
		try {
			if (!$this->jwtToken()->validate()) {
				$response = array('status' => '2', 'message' => 'Token Auth Failed', 'data' => array());
				return response()->json($response, 401);
			}
		} catch (\Exception $e) {
			$response = array('status' => '2', 'message' => $e->getMessage(), 'data' => array());
			return response()->json($response, 401);
		}
		$payload = $this->jwtPayload();
		//payload array
		//Array
		// (
		//     [exp] => 1591011631
		//     [student_id] => 16822
		//     [sub_institute_id] => 59
		//     [mobile] => 9909906512
		// )
		// echo '<pre>';
		// print_r($payload);die;

		// $response = array('response' => '', 'success' => false);
		$response = array('status' => '0', 'message' => '', 'data' => array());
		$validator = Validator::make($request->all(), [
			'student_id' => 'required|numeric',
			'syear' => 'required|numeric',
			'sub_institute_id' => 'required|numeric',
			'action' => 'required',
		]);
		if ($validator->fails()) {
			$response['message'] = $validator->messages();
		} else {
			//process the request
			// echo 'dd';die;
			$sub_institute_id = $_REQUEST['sub_institute_id'];
			$syear = $_REQUEST['syear'];
			$student_id = $_REQUEST['student_id'];
			$action = $_REQUEST['action'];

			if ($student_id == $payload['student_id'] && $sub_institute_id == $payload['sub_institute_id']) 
			{
				$sql = "SELECT se.standard_id,se.section_id,se.grade_id,d.id as division_id
                FROM tblstudent s
                INNER JOIN tblstudent_enrollment se ON se.student_id = s.id
                INNER JOIN academic_section g ON g.id = se.grade_id
                INNER JOIN standard st ON st.id = se.standard_id
                INNER JOIN division d ON  d.id = se.section_id
                INNER JOIN school_setup ss on s.sub_institute_id = ss.Id
                WHERE s.sub_institute_id = '" . $sub_institute_id . "'
                AND se.syear = '" . $syear . "'
                AND se.student_id = '" . $student_id . "'
                GROUP BY s.id
                ";
				$sql = preg_replace('/\n+/', '', $sql);
				$result = DB::select($sql);

				$standard_id = $result[0]->standard_id;
				$division_id = $result[0]->division_id;
				$extra_condition = "";
				if (isset($_REQUEST["type"]) && $_REQUEST["type"] != "") {
					$extra_condition = " AND event_type = '" . $_REQUEST["type"] . "'";
				}
				$data_sql = "SELECT c.*,if(c.file_name = '','',concat('https://".$_SERVER['SERVER_NAME']."/storage/circular/',c.file_name)) as file_name,t.type as circular_type,DATE_FORMAT(c.date_,'%d-%m-%Y') AS date_
                FROM circular c
				INNER JOIN circular_type t on t.id = c.type
                WHERE standard_id = $standard_id AND division_id = $division_id
                AND syear = $syear
                AND sub_institute_id = $sub_institute_id
				AND t.type =  '".$action."'
				ORDER BY c.date_ DESC";
				
				$data_sql = preg_replace('/\n+/', '', $data_sql);
				$result_data = DB::select($data_sql);
				$response['data'] = $result_data;
				$response['message'] = "Success";
				$response['status'] = 1;
				// echo '<pre>'; print_r($result); exit;
			} else {
				$response['message'] = array("Token Error" => "You are not authorized to view this data.");
			}
		}

		return json_encode($response);

		exit;
	}
	public function TeacherFetchData(Request $request) {
		try {
			if (!$this->jwtToken()->validate()) {
				$response = array('status' => '2', 'message' => 'Token Auth Failed', 'data' => array());
				return response()->json($response, 401);
			}
		} catch (\Exception $e) {
			$response = array('status' => '2', 'message' => $e->getMessage(), 'data' => array());
			return response()->json($response, 401);
		}
		$payload = $this->jwtPayload();
		
		$response = array('status' => '0', 'message' => '', 'data' => array());
		$validator = Validator::make($request->all(), [
			'sub_institute_id' => 'required|numeric',
			'syear' => 'required|numeric',
			'standard_id' => 'required|numeric',
		]);
		if ($validator->fails()) {
			$response['message'] = $validator->messages();
		} else {
			//process the request
			// echo 'dd';die;
			$sub_institute_id = $_REQUEST['sub_institute_id'];
			$syear = $_REQUEST['syear'];
			$standard_id = $_REQUEST['standard_id'];

				$data_sql = "SELECT *,if(c.file_name = '','',concat('https://".$_SERVER['SERVER_NAME']."/storage/student/',c.file_name)) as file_name,s.name as std_name
                FROM circular c
                INNER JOIN standard s on s.id = c.standard_id AND s.sub_institute_id = c.sub_institute_id 
                WHERE c.standard_id = $standard_id
                AND c.syear = $syear
                AND c.sub_institute_id = $sub_institute_id
                ";
				$data_sql = preg_replace('/\n+/', '', $data_sql);
				$result_data = DB::select($data_sql);
				$response['data'] = $result_data;
				$response['message'] = "Success";
				$response['status'] = "1";
				// echo '<pre>'; print_r($result); exit;
			
		}

		return json_encode($response);

		exit;
	}

	/**
	 * Show the form for creating a new resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function create() {
		//        //
		//        echo "<pre>";
		//        print_r($_REQUEST);
		//        exit;
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @return \Illuminate\Http\Response
	 */
	public function store(Request $request) 
	{

		if(isset($_REQUEST['action']) && $_REQUEST['action'] == "API")
        {
            $syear = $_REQUEST['syear'];
            $sub_institute_id = $_REQUEST['sub_institute_id'];
            $user_id = $_REQUEST['user_id'];
        }
        else
        {
            $syear = session()->get('syear');
            $sub_institute_id = session()->get('sub_institute_id');
            $user_id = session()->get('user_id');
        } 

        if ($request->hasFile('attachment')) 
        {
            foreach ($request->file('attachment') as $key => $file_data) 
            {
                $file_name = "";
                $originalname = $file_data->getClientOriginalName();
                $name = 'circular_'.$key.'_'.date('YmdHis');
                $ext = \File::extension($originalname);
                $file_name = $name.'.'.$ext;
                $path = $file_data->storeAs('public/circular/', $file_name);

                if (isset($_REQUEST['standard'])) 
        		{
            		foreach ($_REQUEST['standard'] as $id => $std) 
            		{
		                foreach ($_REQUEST['division'] as $ids => $div_id) 
		                {

                            $values = array(
                                'syear' => $syear,
                                'standard_id' => $std,
                                'division_id' => $div_id,
                                'title' => $_REQUEST['title'],
                                'type' => $_REQUEST['type'],
                                'message' => $_REQUEST['message'],
                                'file_name' => $file_name,
                                'date_' => $_REQUEST['date_'],
                                'sub_institute_id' => $sub_institute_id,
                                'created_at' => now(),
                                'updated_at' => now(),
                            );
                            DB::table('circular')->insert($values);

                            //START Send Notification Code
		                    $student_sql = "SELECT *,concat_ws(' ',s.first_name,s.middle_name,s.last_name) as student_name 
		                            FROM tblstudent_enrollment se
		                            INNER JOIN tblstudent s ON s.id = se.student_id AND s.sub_institute_id = se.sub_institute_id
		                            WHERE se.standard_id = '".$std."' AND se.section_id = '".$div_id."' AND se.syear = '". $syear."' AND se.end_date IS NULL 
		                            AND se.sub_institute_id = '".$sub_institute_id."'";
		                    $student_data = DB::select($student_sql);

		                    $schoolData = SchoolModel::where(['id' => $sub_institute_id])->get()->toArray(); 
		                    $schoolName = $schoolData[0]['SchoolName'];
		                    $schoolLogo = $_SERVER['APP_URL'].'/admin_dep/images/'.$schoolData[0]['Logo'];

		                    if(count($student_data) > 0)
		                    {
		                        foreach($student_data as $key => $val)
		                        {
		                            $student_id = $val->student_id;
		                            $mobile_no = $val->mobile;
		                            $student_name = $val->student_name;
		                            
		                            // if($_REQUEST['type'] == 'Circular'){
		                            //     $screen_name = 'circular';
		                            //     $noti_type = 'Circular';
		                            // }else{
		                            //     $screen_name = 'event';
		                            //     $noti_type = 'Events';
		                            // }

		                            $pushMessage = "Dear Parents, ".$_REQUEST['title']." has been added in Circular for date : " . date('d-m-Y', strtotime($_REQUEST['date_']));

		                            $app_notification_content = array(
		                                'NOTIFICATION_TYPE' => 'Circular',
		                                'NOTIFICATION_DATE' => $_REQUEST['date_'],                 
		                                'STUDENT_ID' => $student_id,                   
		                                'NOTIFICATION_DESCRIPTION' => $_REQUEST['title'].' - '.$pushMessage,
		                                'STATUS' => 0,
		                                'SUB_INSTITUTE_ID' => $sub_institute_id,                  
		                                'SYEAR' =>  $syear,
		                                'SCREEN_NAME' => 'circular_events',
		                                'CREATED_BY' => $user_id,        
		                                'CREATED_IP' => $_SERVER['REMOTE_ADDR']          
		                            );

		                            $gcm_query = "SELECT * 
		                                      FROM gcm_users 
		                                      WHERE mobile_no ='".$mobile_no."' 
		                                      AND sub_institute_id = '".$sub_institute_id."' ";
		                            $gcm_data = DB::select($gcm_query);
		                            // dd($gcm_data);
		                            $gcmRegIds = array();
		                            if(count($gcm_data) > 0)
		                            {
		                                foreach($gcm_data as $key1 => $val1)
		                                {
		                                    array_push($gcmRegIds, $val1->gcm_regid);
		                                }
		                            }

		                            $bunch_arr = array_chunk($gcmRegIds, 1000);
		                            if (!empty($bunch_arr)) 
		                            {
		                                foreach ($bunch_arr as $val) 
		                                {
		                                    if (isset($val) && isset($pushMessage)) 
		                                    {
		                                        $type = 'Circular';
		                                        $message = array('body' => $pushMessage, 'TYPE' => $type, 'USER_ID' => $student_id, 'title' => $schoolName, 'image' => $schoolLogo);
		                                        $pushStatus = send_FCM_Notification($val, $message);
		                                        sendNotification($app_notification_content);                                      
		                                    }
		                                }
		                            }
		                           
		                        }
		                    }
		                    //END Send Notification Code                            
                        }
                    }
                }
            }            
        }
        else
        {
    		if (isset($_REQUEST['standard'])) 
    		{
        		foreach ($_REQUEST['standard'] as $id => $std) 
        		{
	                foreach ($_REQUEST['division'] as $ids => $div_id) 
	                {
			        	$values = array(
			                'syear' =>  $syear,
			                'standard_id' => $std,
			                'division_id' => $div_id,
			                'title' => $_REQUEST['title'],
			                'type' => $_REQUEST['type'],
			                'message' => $_REQUEST['message'],
			                'date_' => $_REQUEST['date_'],
			                'sub_institute_id' => $sub_institute_id,
			                'created_at' => now(),
			                'updated_at' => now(),
			            );
			            DB::table('circular')->insert($values);

			            //START Send Notification Code
	                    $student_sql = "SELECT *,concat_ws(' ',s.first_name,s.middle_name,s.last_name) as student_name 
	                            FROM tblstudent_enrollment se
	                            INNER JOIN tblstudent s ON s.id = se.student_id AND s.sub_institute_id = se.sub_institute_id
	                            WHERE se.standard_id = '".$std."' AND se.section_id = '".$div_id."' AND se.syear = '". $syear."' AND se.end_date IS NULL 
	                            AND se.sub_institute_id = '".$sub_institute_id."'";
	                    $student_data = DB::select($student_sql);

	                    $schoolData = SchoolModel::where(['id' => $sub_institute_id])->get()->toArray(); 
	                    $schoolName = $schoolData[0]['SchoolName'];
	                    $schoolLogo = $_SERVER['APP_URL'].'/admin_dep/images/'.$schoolData[0]['Logo'];

	                    if(count($student_data) > 0)
	                    {
	                        foreach($student_data as $key => $val)
	                        {
	                            $student_id = $val->student_id;
	                            $mobile_no = $val->mobile;
	                            $student_name = $val->student_name;
	                            
	                            // if($_REQUEST['type'] == 'Circular'){
	                            //     $screen_name = 'circular';
	                            //     $noti_type = 'Circular';
	                            // }else{
	                            //     $screen_name = 'event';
	                            //     $noti_type = 'Events';
	                            // }

	                            $pushMessage = "Dear Parents, ".$_REQUEST['title']." has been added in Circular for date : " . date('d-m-Y', strtotime($_REQUEST['date_']));

	                            $app_notification_content = array(
	                                'NOTIFICATION_TYPE' => 'Circular',
	                                'NOTIFICATION_DATE' => $_REQUEST['date_'],                 
	                                'STUDENT_ID' => $student_id,                   
	                                'NOTIFICATION_DESCRIPTION' => $_REQUEST['title'].' - '.$pushMessage,
	                                'STATUS' => 0,
	                                'SUB_INSTITUTE_ID' => $sub_institute_id,                  
	                                'SYEAR' =>  $syear,
	                                'SCREEN_NAME' => 'circular_events',
	                                'CREATED_BY' => $user_id,        
	                                'CREATED_IP' => $_SERVER['REMOTE_ADDR']          
	                            );

	                            $gcm_query = "SELECT * 
	                                      FROM gcm_users 
	                                      WHERE mobile_no ='".$mobile_no."' 
	                                      AND sub_institute_id = '".$sub_institute_id."' ";
	                            $gcm_data = DB::select($gcm_query);
	                            // dd($gcm_data);
	                            $gcmRegIds = array();
	                            if(count($gcm_data) > 0)
	                            {
	                                foreach($gcm_data as $key1 => $val1)
	                                {
	                                    array_push($gcmRegIds, $val1->gcm_regid);
	                                }
	                            }

	                            $bunch_arr = array_chunk($gcmRegIds, 1000);
	                            if (!empty($bunch_arr)) 
	                            {
	                                foreach ($bunch_arr as $val) 
	                                {
	                                    if (isset($val) && isset($pushMessage)) 
	                                    {
	                                        $type = 'Circular';
	                                        $message = array('body' => $pushMessage, 'TYPE' => $type, 'USER_ID' => $student_id, 'title' => $schoolName, 'image' => $schoolLogo);
	                                        $pushStatus = send_FCM_Notification($val, $message);
	                                        sendNotification($app_notification_content);                                      
	                                    }
	                                }
	                            }
	                           
	                        }
	                    }
	                    //END Send Notification Code
			        }
			    }
			}            
        }        

        if(isset($_REQUEST['action']) && $_REQUEST['action'] == "API")
        {
            return 1;
        }
        else
        {
            $res = array(
			"status" => 1,
			"message" => "Circular Added Successfully.",
			);
			$type = $request->input('type');
			return \App\Helpers\is_mobile($type, "circular.index", $res, "redirect");
        }
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
	//    public function destroy($id) {
	//        //
	//    }

	public function destroy(Request $request, $id) {
		$type = $request->input('type');
		DB::table('circular')->where(["Id" => $id])->delete();
		//        ExamMaster::where(["Id" => $id])->delete();
		$res = array(
			"status_code" => 1,
			"message" => "Data Deleted",
		);

		return \App\Helpers\is_mobile($type, "circular.index", $res, "redirect");
	}

	public function searchCircularTitle(Request $request)
    {
        $searchValue = $request->input('value');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $extraSearchArray = array();
        $extraSearchArray['circular.sub_institute_id'] = $sub_institute_id;

        $circular_data = circular::selectRaw('title')
        ->whereRaw('circular.title LIKE "%'.$searchValue.'%"')
        ->where($extraSearchArray)
        ->groupby('circular.title')
        ->get()
        ->toArray();

        return $circular_data; 
    }
}
