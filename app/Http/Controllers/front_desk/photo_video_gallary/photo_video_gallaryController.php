<?php

namespace App\Http\Controllers\front_desk\photo_video_gallary;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use File;
use DB;
use GenTux\Jwt\JwtToken;
use GenTux\Jwt\GetsJwtToken;
use function App\Helpers\aut_token;
use function App\Helpers\sendNotification;
use function App\Helpers\send_FCM_Notification;
use App\Models\school_setup\SchoolModel;

class photo_video_gallaryController extends Controller
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
                $school_data['message'] = $data_arr['message'];
            }
        }

        $school_data['data'] = $this->getData();
        // echo "<pre>";
        // print_r($school_data['data']);
        // exit;
//        $school_data['data'] = array();
        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "front_desk/photo_video_gallary/show", $school_data, "view");
    }

    public function getData()
    {
        $sql = "SELECT c.*,s.name std_name,d.name div_name 
                FROM photo_video_gallary c
                INNER JOIN standard s on s.id = c.standard_id AND s.sub_institute_id = c.sub_institute_id
                LEFT JOIN division d on d.id = c.division_id AND d.sub_institute_id = c.sub_institute_id
                WHERE c.syear = '" . session()->get('syear') . "' 
                AND c.sub_institute_id = '" . session()->get('sub_institute_id') . "' 
                ORDER BY id DESC 
                LIMIT 1000";

        $sql = preg_replace('/\n+/', '', $sql);

        $result = DB::select($sql);
        return $result;
    }
    public function fetchData(Request $request)
    {
        $response = array('response' => '', 'success' => false);
        $validator =  Validator::make($request->all(), [
            'student_id' => 'required|numeric',
            'syear' => 'required|numeric',
            'sub_institute_id' => 'required|numeric',
            'type' => ["in:Photo,Video,"]
        ]);
        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {
            //process the request

            $sub_institute_id = $_REQUEST['sub_institute_id'];
            $syear = $_REQUEST['syear'];
            $student_id = $_REQUEST['student_id'];

            $sql = "SELECT se.standard_id,se.section_id,se.grade_id
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
            if ($result) {
                $standard_id = $result[0]->standard_id;
                $extra_condition = "";
                if (isset($_REQUEST["type"]) && $_REQUEST["type"] != "") {
                    $extra_condition = " AND type = '" . $_REQUEST["type"] . "'";
                }
                $server = "https://".$_SERVER['HTTP_HOST'];
                $data_sql = "SELECT pvg.id,pvg.syear,pvg.standard_id,pvg.title,pvg.`type`,pvg.ai,
                if(pvg.file_name IS NULL OR pvg.file_name = '','-',if(pvg.`type` = 'Video', pvg.file_name, CONCAT('$server/storage/photo_video_gallary/',pvg.file_name))) file_name,
                pvg.date_,pvg.sub_institute_id,pvg.created_at,pvg.updated_at
                FROM photo_video_gallary pvg
                WHERE pvg.standard_id = $standard_id
                AND pvg.syear = $syear
                AND pvg.sub_institute_id = $sub_institute_id
                $extra_condition
                ";
                $data_sql = preg_replace('/\n+/', '', $data_sql);
                $result_data = DB::select($data_sql);
                $response['response'] = $result_data;
                $response['success'] = true;
            // echo '<pre>'; print_r($result); exit;
            } else {
                $response['response'] = array("student_id" => array("No student found."));
            }
        }

        return json_encode($response);

        exit;
    }
    public function TeacherFetchData(Request $request)
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
            'standard_id' => 'required|numeric',
            'syear' => 'required|numeric',
            'sub_institute_id' => 'required|numeric',
            'type' => ["in:Photo,Video,"]
        ]);
        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {
            //process the request

            $sub_institute_id = $_REQUEST['sub_institute_id'];
            $syear = $_REQUEST['syear'];
            $standard_id = $_REQUEST['standard_id'];

            
            $extra_condition = "";
            if (isset($_REQUEST["type"]) && $_REQUEST["type"] != "") {
                $extra_condition = " AND type = '" . $_REQUEST["type"] . "'";
            }
            $server = "https://".$_SERVER['HTTP_HOST'];
            $data_sql = "SELECT pvg.id,pvg.syear,pvg.standard_id,pvg.album_title,pvg.title,pvg.`type`,pvg.ai,
                if(pvg.file_name IS NULL OR pvg.file_name = '','-',if(pvg.`type` = 'Video', pvg.file_name, CONCAT('$server/storage/photo_video_gallary/',pvg.file_name))) file_name,
                pvg.date_,pvg.sub_institute_id,pvg.created_at,pvg.updated_at
                FROM photo_video_gallary pvg
                WHERE pvg.standard_id = $standard_id
                AND pvg.syear = $syear
                AND pvg.sub_institute_id = $sub_institute_id
                $extra_condition
                ";
            $data_sql = preg_replace('/\n+/', '', $data_sql);
            $result_data = DB::select($data_sql);

            foreach($result_data as $key => $val)
            {
                $new_data[$val->album_title][] = $val;
            }
            
            $response['data'] = $new_data;
            $response['status'] = '1';
            $response['message'] = 'Sucsses';            
        }

        return json_encode($response);

        exit;
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
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

        if ($_REQUEST['type'] == 'Photo') 
        {
            if ($request->hasFile('attachment')) 
            {
                foreach ($request->file('attachment') as $key => $file_data) 
                {
                    $file_name = $file_size = $ext = "";
                    $originalname = $file_data->getClientOriginalName();
                    $file_size = $file_data->getSize();
                    $name = 'attachment_'.$key.'_'.date('YmdHis');
                    $ext = \File::extension($originalname);
                    $file_name = $name.'.'.$ext;
                    $path = $file_data->storeAs('public/photo_video_gallary/', $file_name);
                
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
                                    'album_title' => $_REQUEST['album_title'],
                                    'type' => $_REQUEST['type'],
                                    'file_name' => $file_name,
                                    'file_size' => $file_size,
                                    'file_type' => $ext,
                                    'date_' => $_REQUEST['date_'],
                                    'sub_institute_id' => $sub_institute_id,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                );
                                DB::table('photo_video_gallary')->insert($values);

                                //START Send Notification Code
                                $student_sql = "SELECT *,concat_ws(' ',s.first_name,s.middle_name,s.last_name) as student_name 
                                        FROM tblstudent_enrollment se
                                        INNER JOIN tblstudent s ON s.id = se.student_id AND s.sub_institute_id = se.sub_institute_id
                                        WHERE se.standard_id = '".$std."' AND se.section_id = '".$div_id."' AND se.syear = '".$syear."' AND se.end_date IS NULL 
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
                                        
                                        if($_REQUEST['type'] == 'Photo')
                                        {
                                            $screen_name = 'photos_gallery';
                                            $noti_type = 'Photo Gallery';
                                        }else{
                                            $screen_name = 'video_gallery';
                                            $noti_type = 'Video Gallery';
                                        }


                                        $pushMessage = "Dear Parents, ".$_REQUEST['title']." has been added in Photo Video Gallary for date : " . date('d-m-Y', strtotime($_REQUEST['date_']));

                                        $app_notification_content = array(
                                            'NOTIFICATION_TYPE' => $noti_type,
                                            'NOTIFICATION_DATE' => $_REQUEST['date_'],                 
                                            'STUDENT_ID' => $student_id,                   
                                            'NOTIFICATION_DESCRIPTION' => $_REQUEST['title'].' - '.$pushMessage,
                                            'STATUS' => 0,
                                            'SUB_INSTITUTE_ID' => $sub_institute_id,                  
                                            'SYEAR' => $syear,
                                            'SCREEN_NAME' => $screen_name,
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
                                                    $type = $noti_type;
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
        } 
        else 
        {
            if (isset($_REQUEST['standard'])) 
            {
                foreach ($_REQUEST['standard'] as $id => $std) 
                {
                    foreach ($_REQUEST['division'] as $ids => $div_id) 
                    {
                        $file_name = $_REQUEST['attachment'];
                        $values = array(
                            'syear' => $syear,
                            'standard_id' => $std,
                            'division_id' => $div_id,
                            'title' => $_REQUEST['title'],
                            'album_title' => $_REQUEST['album_title'],
                            'type' => $_REQUEST['type'],
                            'file_name' => $file_name,
                            'date_' => $_REQUEST['date_'],
                            'sub_institute_id' => $sub_institute_id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        );
                        DB::table('photo_video_gallary')->insert($values);

                        //START Send Notification Code
                        $student_sql = "SELECT *,concat_ws(' ',s.first_name,s.middle_name,s.last_name) as student_name 
                                FROM tblstudent_enrollment se
                                INNER JOIN tblstudent s ON s.id = se.student_id AND s.sub_institute_id = se.sub_institute_id
                                WHERE se.standard_id = '".$std."' AND se.section_id = '".$div_id."' AND se.syear = '".$syear."' AND se.end_date IS NULL 
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
                                
                                if($_REQUEST['type'] == 'Photo')
                                {
                                    $screen_name = 'photos_gallery';
                                    $noti_type = 'Photo Gallery';
                                }else{
                                    $screen_name = 'video_gallery';
                                    $noti_type = 'Video Gallery';
                                }


                                $pushMessage = "Dear Parents, ".$_REQUEST['title']." has been added in Photo Video Gallary for date : " . date('d-m-Y', strtotime($_REQUEST['date_']));

                                $app_notification_content = array(
                                    'NOTIFICATION_TYPE' => $noti_type,
                                    'NOTIFICATION_DATE' => $_REQUEST['date_'],                 
                                    'STUDENT_ID' => $student_id,                   
                                    'NOTIFICATION_DESCRIPTION' => $_REQUEST['title'].' - '.$pushMessage,
                                    'STATUS' => 0,
                                    'SUB_INSTITUTE_ID' => $sub_institute_id,                  
                                    'SYEAR' => $syear,
                                    'SCREEN_NAME' => $screen_name,
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
                                            $type = $noti_type;
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
                "message" => "Photo Video Gallery Added Successfully.",
            );

            $type = $request->input('type');
            return \App\Helpers\is_mobile($type, "photo_video_gallary.index", $res, "redirect");
        }

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
        $sql = "select ai from photo_video_gallary where id = " . $id;
        $sql = preg_replace('/\n+/', '', $sql);
        $result = DB::select($sql);
        $active_status = $result[0]->ai;

        $change_status = "InActive";
        if ($active_status == 'InActive') {
            $change_status = "Active";
        }
        $sql = "update photo_video_gallary set ai = '$change_status' where id = " . $id;
        $sql = preg_replace('/\n+/', '', $sql);
        $result = DB::statement($sql);
//        $active_status = $result[0]->ai;
        $res = array(
            "status" => 1,
            "message" => "Status Changed",
        );
        $type = "web";
        return \App\Helpers\is_mobile($type, "photo_video_gallary.index", $res, "redirect");

//        update photo_video_gallary set ai = 'status' where id = 2
//        echo "<pre>";
//        print_r($_REQUEST);
//        exit;
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
    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        DB::table('photo_video_gallary')->where(["id" => $id])->delete();
//        ExamMaster::where(["Id" => $id])->delete();
        $res = array(
            "status" => 1,
            "message" => "Data Deleted",
        );

        return \App\Helpers\is_mobile($type, "photo_video_gallary.index", $res, "redirect");
    }
    
    public function studentPhotoVideoGalleryAPI(Request $request)
    {
        try {
            if (!$this->jwtToken()->validate()) {
                $response = array('status' => '2', 'message' => 'Token Auth Failed', 'data' => array());
                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = array('status' => '2', 'message' => $e->getMessage(), 'data' => array());
            return response()->json($response, 401);
        }
                
        $student_id = $request->input("student_id");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");
        $action = $request->input("action");

        if ($student_id != "" && $sub_institute_id != "" && $syear != "" && $action != "") 
        {
            $data = DB::select("SELECT p.album_title,p.title,if(p.type = 'Video',p.file_name,concat('https://".$_SERVER['SERVER_NAME']."/storage/photo_video_gallary/',p.file_name)) as file_name,p.date_ ,ai,`type`
            FROM tblstudent_enrollment s
            LEFT JOIN photo_video_gallary p ON p.standard_id = s.standard_id AND p.division_id = s.section_id AND ai = 'Active'          
            WHERE s.student_id = '".$student_id."' AND s.syear = '".$syear."' AND s.sub_institute_id = '".$sub_institute_id."'    
            AND type = '".$action."'            
            ORDER BY p.date_ DESC");

            $new_data = array();
            foreach($data as $key => $val)
            {
                $new_data[$val->album_title][] = $val;
            }      
            
            $res['status'] = 1;
            $res['message'] = "Success";
            $res['data'] = $new_data;
        } else {
            $res['status'] = 0;
            $res['message'] = "Parameter Missing";
        }
        
        //return is_mobile($type, "implementation", $res);
        return json_encode($res);
    }
}
