<?php

namespace App\Http\Controllers\easy_com\send_notification_parents;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use function App\Helpers\sendNotification;
use function App\Helpers\send_FCM_Notification;
use App\Models\school_setup\SchoolModel;
use DB;
use GenTux\Jwt\JwtToken;
use GenTux\Jwt\GetsJwtToken;
use function App\Helpers\aut_token;


class send_notification_parents_controller extends Controller
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

        $data['data'] = array();
        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "easy_comm/send_notification_parents/show", $data, "view");
    }

    //13.46
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {

        $type = $request->input('type');
        $student_data = \App\Helpers\SearchStudent($_REQUEST['grade'], $_REQUEST['standard'], $_REQUEST['division']);
        $responce_arr['grade'] = $_REQUEST['grade'];
        $responce_arr['standard'] = $_REQUEST['standard'];
        $responce_arr['division'] = $_REQUEST['division'];
        // dd($student_data);
        foreach ($student_data as $id => $arr) {

            $responce_arr['stu_data'][$id]['sr.no'] = $id + 1;
            $responce_arr['stu_data'][$id]['enrollment_no'] = $arr['enrollment_no'];
            $responce_arr['stu_data'][$id]['name'] = $arr['first_name'] . ' ' . $arr['middle_name'] . ' ' . $arr['last_name'];
            $responce_arr['stu_data'][$id]['student_id'] = $arr['student_id'];
            $responce_arr['stu_data'][$id]['mobile'] = $arr['mobile'];
        }

        return \App\Helpers\is_mobile($type, "easy_comm/send_notification_parents/add", $responce_arr, "view");
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
        $sub_institute_id = session()->get('sub_institute_id');    
        $syear = session()->get('syear');      
        $text = $_REQUEST['notificationText'];
        $res = array();
        $student_data = \App\Helpers\SearchStudent($_REQUEST['grade'], $_REQUEST['standard'], $_REQUEST['division']);



        foreach ($_REQUEST['sendNotification'] as $number => $on) 
        {

            $student_sql = "SELECT *,concat_ws(' ',s.first_name,s.middle_name,s.last_name) as student_name 
                    FROM tblstudent_enrollment se
                    INNER JOIN tblstudent s ON s.id = se.student_id AND s.sub_institute_id = se.sub_institute_id
                    WHERE s.mobile = '".$number."' AND se.syear = '".$syear."' AND se.end_date IS NULL AND se.sub_institute_id = '".$sub_institute_id."'";
            if(isset($_REQUEST['grade']))
                $student_sql .= " AND se.grade_id = '".$_REQUEST['grade']."'" ; 
            if(isset($_REQUEST['standard']))
                $student_sql .= " AND se.standard_id = '".$_REQUEST['standard']."'" ; 
            if(isset($_REQUEST['division']))
                $student_sql .= " AND se.section_id = '".$_REQUEST['division']."'" ;

            $student_data_selected = DB::select($student_sql);   

            $schoolData = SchoolModel::where(['id' => $sub_institute_id])->get()->toArray(); 
            $schoolName = $schoolData[0]['SchoolName'];
            $schoolLogo = $_SERVER['APP_URL'].'/admin_dep/images/'.$schoolData[0]['Logo'];

            if(count($student_data_selected) > 0)
            {
                foreach($student_data_selected as $key => $values)
                {
                    $student_id = $values->student_id;
                    
                    $app_notification_content = array(
                        'NOTIFICATION_TYPE' => 'Notification',
                        'NOTIFICATION_DATE' => now(),                 
                        'STUDENT_ID' => $student_id,                   
                        'NOTIFICATION_DESCRIPTION' => $text,
                        'STATUS' => 0,
                        'SUB_INSTITUTE_ID' => $sub_institute_id,                  
                        'SYEAR' => $syear,
                        'SCREEN_NAME' => 'general',
                        'CREATED_BY' => session()->get('user_id'),        
                        'CREATED_IP' => $_SERVER['REMOTE_ADDR']          
                    );

                    $gcm_query = "SELECT * 
                              FROM gcm_users 
                              WHERE mobile_no ='".$number."' 
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

                    $pushMessage = $text;

                    $bunch_arr = array_chunk($gcmRegIds, 1000);
                    if (!empty($bunch_arr)) 
                    {
                        foreach ($bunch_arr as $val) 
                        {
                            if (isset($val) && isset($pushMessage)) 
                            {
                                $type = 'Notification';
                                $message = array('body' => $pushMessage, 'TYPE' => $type, 'USER_ID' => $student_id, 'title' => $schoolName, 'image' => $schoolLogo);
                                $pushStatus = send_FCM_Notification($val, $message);
                                sendNotification($app_notification_content);                                      
                            }
                        }
                        $res = array(
                            "status" => 1,
                            "message" => "Notification Sent successfully.",
                        );
                    }else{
                        $res = array(
                            "status" => 0,
                            "message" => "Notification Not Sent.",
                        ); 
                    }
                   
                }   
            }
        }

       

        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "send_notification_parents.index", $res, "redirect");

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
