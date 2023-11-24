<?php

namespace App\Http\Controllers\easy_com\send_notification_parents;

use App\Http\Controllers\Controller;
use App\Models\school_setup\SchoolModel;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;
use function App\Helpers\send_FCM_Notification;
use function App\Helpers\sendNotification;


class send_notification_parents_controller extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return Response
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

        return is_mobile($type, "easy_comm/send_notification_parents/show", $data, "view");
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

        foreach ($student_data as $id => $arr) 
        {
            $responce_arr['stu_data'][$id]['sr.no'] = $id + 1;
            $responce_arr['stu_data'][$id]['enrollment_no'] = $arr['enrollment_no'];
            $responce_arr['stu_data'][$id]['name'] = $arr['first_name'].' '.$arr['middle_name'].' '.$arr['last_name'];
            $responce_arr['stu_data'][$id]['student_id'] = $arr['student_id'];
            $responce_arr['stu_data'][$id]['mobile'] = $arr['mobile'];
            $responce_arr['stu_data'][$id]['standard_name'] = $arr['standard_name'];
            $responce_arr['stu_data'][$id]['division_name'] = $arr['division_name'];
        }

        return is_mobile($type, "easy_comm/send_notification_parents/add", $responce_arr, "view");
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
        $text = $_REQUEST['notificationText'];
        $res = array();
        $student_data = SearchStudent($_REQUEST['grade'], $_REQUEST['standard'], $_REQUEST['division']);

        foreach ($_REQUEST['sendNotification'] as $number => $on) {

            $requestData = $_REQUEST;
            DB::enableQueryLog();
            $student_data_selected = DB::table('tblstudent_enrollment as se')
                ->join('tblstudent as s', function ($join) {
                    $join->whereRaw('s.id = se.student_id AND s.sub_institute_id = se.sub_institute_id');
                })->selectRaw("*,concat_ws(' ',s.first_name,s.middle_name,s.last_name) as student_name")
                ->where('s.mobile', $number)
                ->where('se.syear', $syear)
                ->whereNull('se.end_date')
                ->where('se.sub_institute_id', $sub_institute_id)
                ->where(function ($q) use ($requestData) {
                    if (isset($_REQUEST['grade'])  && $_REQUEST['grade'] != "") {
                        $q->where('se.grade_id', $requestData['grade']);
                    }
                    if (isset($_REQUEST['standard'])  && $_REQUEST['standard'] != "") {
                        $q->where('se.standard_id', $requestData['standard']);
                    }
                    if (isset($_REQUEST['division']) && $_REQUEST['division'] != "") {
                        $q->where('se.section_id', $requestData['division']);
                    }
                })->get()->toArray();
             

            $schoolData = SchoolModel::where(['id' => $sub_institute_id])->get()->toArray();

            $schoolName = $schoolData[0]['SchoolName'];
            $schoolLogo = $_SERVER['APP_URL'].'/admin_dep/images/'.$schoolData[0]['Logo'];

            if (count($student_data_selected) > 0) {
                foreach ($student_data_selected as $key => $values) {
                    $student_id = $values->student_id;

                    $app_notification_content = [
                        'NOTIFICATION_TYPE'        => 'Notification',
                        'NOTIFICATION_DATE'        => now(),
                        'STUDENT_ID'               => $student_id,
                        'NOTIFICATION_DESCRIPTION' => $text,
                        'STATUS'                   => 0,
                        'SUB_INSTITUTE_ID'         => $sub_institute_id,
                        'SYEAR'                    => $syear,
                        'SCREEN_NAME'              => 'general',
                        'CREATED_BY'               => session()->get('user_id'),
                        'CREATED_IP'               => $_SERVER['REMOTE_ADDR'],
                    ];

                    $gcm_data = DB::table('gcm_users')->where('mobile_no', $number)
                        ->where('sub_institute_id', $sub_institute_id)->get()->toArray();

                    $gcmRegIds = [];
                    if (count($gcm_data) > 0) {
                        foreach ($gcm_data as $key1 => $val1) {
                            $gcmRegIds[] = $val1->gcm_regid;
                        }
                    }

                    $pushMessage = $text;

                    $bunch_arr = array_chunk($gcmRegIds, 1000);
                    
                    if (! empty($bunch_arr)) {
                        foreach ($bunch_arr as $val) {
                            if (isset($val, $pushMessage)) {
                                $type1 = 'Notification';
                                $message = [
                                    'body'  => $pushMessage, 'TYPE' => $type1, 'USER_ID' => $student_id,
                                    'title' => $schoolName, 'image' => $schoolLogo,
                                ];
                                $pushStatus = send_FCM_Notification($val, $message, $sub_institute_id);
                                sendNotification($app_notification_content);
                            }
                        }
                      
                    }

                }
            }
        }
          $res = [
                            "status"  => 1,
                            "message" => "Notification Sent successfully.",
                        ];
        $type = $request->input('type');

        return is_mobile($type, "send_notification_parents.index", $res, "redirect");
    }

}
