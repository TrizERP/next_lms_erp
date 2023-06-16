<?php

namespace App\Http\Controllers\front_desk\parentCommunication;

use App\Http\Controllers\Controller;
use App\Models\school_setup\SchoolModel;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function App\Helpers\is_mobile;
use function App\Helpers\send_FCM_Notification;
use function App\Helpers\sendNotification;


class parentCommunicationController extends Controller
{
    use GetsJwtToken;

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $school_data['message'] = $data_arr['message'];
            }
        }

        $school_data['data'] = array();
        $type = $request->input('type');

        return is_mobile($type, "front_desk/parentCommunication/show", $school_data, "view");
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

        if ($type == "API") {
            $sub_institute_id = $request->input('sub_institute_id');
            $syear = $request->input('syear');
        } else {
            $sub_institute_id = $request->session()->get('sub_institute_id');
            $syear = session()->get('syear');
        }

        //START Check for class teacher assigned standards

        //END Check for class teacher assigned standards


        $result = DB::table("tblstudent as s")
            ->join('tblstudent_enrollment as se', function ($join) {
                $join->whereRaw("se.student_id = s.id");
            })
            ->join('academic_section as g', function ($join) {
                $join->whereRaw("g.id = se.grade_id");
            })
            ->join('standard as st', function ($join) {
                $join->whereRaw("st.id = se.standard_id");
            })
            ->join('division as d', function ($join) {
                $join->whereRaw("d.id = se.section_id");
            })
            ->join('parent_communication as pc', function ($join) {
                $join->whereRaw("pc.student_id = s.id");
            })
            ->leftJoin('tbluser as u', function ($join) {
                $join->whereRaw("u.id = pc.reply_by");
            })
            ->selectRaw("s.*,se.syear,se.student_id,se.grade_id,se.standard_id,se.section_id,se.student_quota,se.start_date,
        se.end_date,se.enrollment_code,se.drop_code,se.drop_remarks,se.drop_remarks,se.term_id,se.remarks,se.admission_fees,
        se.house_id,se.lc_number,st.name standard_name,d.name as division_name,pc.id as parent_communication_id,pc.syear,
        pc.student_id,pc.message,pc.title,pc.reply,pc.date_,pc.created_at,
        CONCAT_WS(' ',u.first_name,u.middle_name,u.last_name) as reply_by_teacher,reply_on")
            ->where("s.sub_institute_id", "=", $sub_institute_id)
            ->where("se.syear", "=", $syear)
            ->where("pc.syear", "=", $syear)
            ->where(function ($q) {
                if (isset($_REQUEST['from_date'])) {
                    $q->where("pc.date_", ">=", $_REQUEST['from_date']);
                }
                if (isset($_REQUEST['to_date'])) {
                    $q->where("pc.date_", "<=", $_REQUEST['to_date']);
                }
            })
            ->where(function ($q) {
                $classTeacherStdArr = session()->get('classTeacherStdArr');
                if (isset($classTeacherStdArr)) {
                    if (count($classTeacherStdArr) > 0) {
                        $q->whereRaw("se.standard_id IN (".implode(",", $classTeacherStdArr).")");
                    } else {
                        $q->whereRaw("se.standard_id IN (' ')");
                    }
                }

                $classTeacherDivArr = session()->get('classTeacherDivArr');
                if (isset($classTeacherStdArr) && count($classTeacherDivArr) > 0) {
                    $q->whereRaw("se.section_id IN (".implode(",", $classTeacherDivArr).")");
                }
            })
            ->get()->toarray();

        $responce_arr = [];
        foreach ($result as $id => $arr) {
            $responce_arr['stu_data'][$id]['sr.no'] = $id + 1;
            $responce_arr['stu_data'][$id]['name'] = $arr->first_name.' '.$arr->middle_name.' '.$arr->last_name;
            $responce_arr['stu_data'][$id]['student_id'] = $arr->student_id;
            $responce_arr['stu_data'][$id]['parent_communication_id'] = $arr->parent_communication_id;
            $responce_arr['stu_data'][$id]['stddiv'] = $arr->standard_name."/".$arr->division_name;
            $responce_arr['stu_data'][$id]['title'] = $arr->title;
            $responce_arr['stu_data'][$id]['mobile'] = $arr->mobile;
            $responce_arr['stu_data'][$id]['message'] = $arr->message;
            $responce_arr['stu_data'][$id]['date_'] = $arr->created_at;//date("d-m-Y", strtotime($arr->date_));
            $responce_arr['stu_data'][$id]['reply'] = $arr->reply;
            $responce_arr['stu_data'][$id]['reply_by'] = $arr->reply_by_teacher;
            $responce_arr['stu_data'][$id]['reply_on'] = $arr->reply_on;
        }

        return is_mobile($type, "front_desk/parentCommunication/add", $responce_arr, "view");
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
        $type = $request->input('type');

        if ($type == "API") {
            $sub_institute_id = $request->input('sub_institute_id');
            $syear = $request->input('syear');
        } else {
            $sub_institute_id = $request->session()->get('sub_institute_id');
            $syear = session()->get('syear');
        }

        foreach ($_REQUEST['reply'] as $parent_communication_id => $message) {
            if (! empty($message)) {
                DB::table('parent_communication')
                    ->where('id', $parent_communication_id)
                    ->where('sub_institute_id', $sub_institute_id)
                    ->where('syear', $syear)
                    ->update([
                        'reply'    => $message, 'reply_on' => date('Y-m-d H:i:s'),
                        'reply_by' => session()->get('user_id'),
                    ]);

                //START Send Notification Code

                $get_student = DB::table("parent_communication")
                    ->where("id", "=", $parent_communication_id)
                    ->where("syear", "=", $syear)
                    ->where("sub_institute_id", "=", $sub_institute_id)
                    ->get()->toArray();

                $student_id = $get_student[0]->student_id;
                $message_date = date('d-m-Y', strtotime($get_student[0]->date_));
                $reply_on_date = date('d-m-Y', strtotime($get_student[0]->reply_on));

                $student_data = DB::table("tblstudent_enrollment as se")
                    ->join('tblstudent as s', function ($join) {
                        $join->whereRaw("s.id = se.student_id AND s.sub_institute_id = se.sub_institute_id");
                    })
                    ->selectRaw("*,concat_ws(' ',s.first_name,s.middle_name,s.last_name) as student_name ")
                    ->where("s.id", "=", $student_id)
                    ->where("se.syear", "=", $syear)
                    ->whereNull("se.end_date")
                    ->where("se.sub_institute_id", "=", $sub_institute_id)
                    ->get()->toArray();

                $schoolData = SchoolModel::where(['id' => $sub_institute_id])->get()->toArray();
                $schoolName = $schoolData[0]['SchoolName'];
                $schoolLogo = $_SERVER['APP_URL'].'/admin_dep/images/'.$schoolData[0]['Logo'];

                if (count($student_data) > 0) {
                    $mobile_no = $student_data[0]->mobile;
                    $student_name = $student_data[0]->student_name;

                    $pushMessage = "Dear Parents, Your message : ".$get_student[0]->message." on date : ".$message_date." <br>"." Reply : ".$get_student[0]->reply." on date : ".$reply_on_date;

                    $app_notification_content = [
                        'NOTIFICATION_TYPE'        => 'Parent Communication',
                        'NOTIFICATION_DATE'        => $get_student[0]->reply_on,
                        'STUDENT_ID'               => $student_id,
                        'NOTIFICATION_DESCRIPTION' => $pushMessage,
                        'STATUS'                   => 0,
                        'SUB_INSTITUTE_ID'         => $sub_institute_id,
                        'SYEAR'                    => $syear,
                        'SCREEN_NAME'              => 'parent_communication',
                        'CREATED_BY'               => session()->get('user_id'),
                        'CREATED_IP'               => $_SERVER['REMOTE_ADDR'],
                    ];

                    $gcm_data = DB::table("gcm_users")
                        ->where("mobile_no", "=", $mobile_no)
                        ->where("sub_institute_id", "=", $sub_institute_id)
                        ->get()->toArray();

                    $gcmRegIds = [];
                    if (count($gcm_data) > 0) {
                        foreach ($gcm_data as $key1 => $val1) {
                            $gcmRegIds[] = $val1->gcm_regid;
                        }
                    }

                    $bunch_arr = array_chunk($gcmRegIds, 1000);
                    if (! empty($bunch_arr)) {
                        foreach ($bunch_arr as $val) {
                            if (isset($val) && isset($pushMessage)) {
                                $type = 'Parent Communication';
                                $message = [
                                    'body'    => $pushMessage,
                                    'TYPE'    => $type,
                                    'USER_ID' => $student_id,
                                    'title'   => $schoolName,
                                    'image'   => $schoolLogo,
                                ];

                                //$pushStatus = send_FCM_Notification($val, $message);
                                //sendNotification($app_notification_content);
                            }
                        }
                    }
                }
                //END Send Notification Code
            }
        }
        $res = [
            "status"  => 1,
            "message" => "Parent Communication Update Successfully.",
        ];

        $type = $request->input('type');

        return is_mobile($type, "parent_communication.index", $res, "redirect");
    }

    public function add_communicationAPI(Request $request)
    {
        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $type = $request->input("type");
        $student_id = $request->input("student_id");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");
        $title = $request->input("title");
        $message = $request->input("message");

        if ($student_id != "" && $sub_institute_id != "" && $syear != "" && $title != "" && $message != "" && $type != "") {
            $data = [
                'syear'            => $syear,
                'student_id'       => $student_id,
                'title'            => $title,
                'message'          => $message,
                'date_'            => now(),
                'sub_institute_id' => $sub_institute_id,
                'created_at'       => now(),
            ];
            DB::table('parent_communication')
                ->insert($data);
            $res['status'] = 1;
            $res['message'] = "Record Added Successfully";
        } else {
            $res['status'] = 0;
            $res['message'] = "Parameter Missing";
        }

        return json_encode($res);
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

    public function teacherParentcommunicationListAPI(Request $request)
    {

        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $type = $request->input("type");
        $teacher_id = $request->input("teacher_id");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");

        if ($teacher_id != "" && $sub_institute_id != "" && $syear != "") {
            $data = DB::table("class_teacher as ct")
                ->join('standard as s', function ($join) {
                    $join->whereRaw("ct.standard_id = s.id AND ct.sub_institute_id = s.sub_institute_id");
                })
                ->join('division as d', function ($join) {
                    $join->whereRaw("d.id = ct.division_id AND d.sub_institute_id = ct.sub_institute_id");
                })
                ->join('tblstudent_enrollment as se', function ($join) {
                    $join->whereRaw("se.standard_id = ct.standard_id AND se.section_id = ct.division_id AND se.sub_institute_id = ct.sub_institute_id");
                })
                ->join('tblstudent as ts', function ($join) {
                    $join->whereRaw("ts.id = se.student_id AND ts.sub_institute_id = ct.sub_institute_id");
                })
                ->join('parent_communication as pc', function ($join) {
                    $join->whereRaw("pc.student_id = ts.id AND pc.sub_institute_id = ct.sub_institute_id");
                })
                ->leftJoin('tbluser as tu', function ($join) {
                    $join->whereRaw("tu.id = pc.reply_by AND tu.sub_institute_id = pc.sub_institute_id");
                })
                ->selectRaw("pc.id as parent_comm_id,concat_ws(' ',ts.first_name,ts.middle_name,ts.last_name) as student_name,
                if(ts.image = '','',concat('https://".$_SERVER['SERVER_NAME']."/storage/student/',ts.image)) as student_image,
                ts.enrollment_no,ts.mobile,ct.standard_id,ct.division_id,ts.email,s.name AS standard_name,d.name AS division_name,
                pc.message,date_format(pc.date_,'%d-%m-%Y') as parent_comm_date,pc.reply,
                concat_ws(' ',tu.first_name,tu.middle_name,tu.last_name) as reply_by,pc.reply_on")
                ->where("ct.sub_institute_id", "=", $sub_institute_id)
                ->where("ct.syear", "=", $syear)
                ->where("se.syear", "=", $syear)
                ->where("pc.syear", "=", $syear)
                ->where("ct.teacher_id", "=", $teacher_id)
                ->orderBy('student_name')
                ->get()->toarray();

            if (count($data) > 0) {
                $res['status'] = 1;
                $res['message'] = "Success";
                $res['data'] = $data;
            } else {
                $res['status'] = 0;
                $res['message'] = "You are not a class teacher.";
            }
        } else {
            $res['status_code'] = 0;
            $res['message'] = "Parameter Missing";
        }

        return json_encode($res);
    }

    public function teacherParentcommunicationSaveAPI(Request $request)
    {

        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $type = $request->input("type");
        $teacher_id = $request->input("teacher_id");
        $parent_comm_id = $request->input("parent_comm_id");
        $reply = $request->input("reply");
        $reply_on = $request->input("reply_on");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");

        if ($teacher_id != "" && $sub_institute_id != "" && $syear != "" && $parent_comm_id != "" && $reply != "" && $reply_on != "") {
            DB::table('parent_communication')
                ->where('id', $parent_comm_id)
                ->where('sub_institute_id', $sub_institute_id)
                ->where('syear', $syear)
                ->update(['reply' => $reply, 'reply_by' => $teacher_id, 'reply_on' => $reply_on]);

            $res['status_code'] = 1;
            $res['message'] = "Success";
        } else {
            $res['status_code'] = 0;
            $res['message'] = "Parameter Missing";
        }

        return json_encode($res);
    }

    public function studentParentcommunicationListAPI(Request $request)
    {

        $type = $request->input("type");
        if ($type == 'API') {
            try {
                if (! $this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                    return response()->json($response, 401);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

                return response()->json($response, 401);
            }
        }

        $student_id = $request->input("student_id");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");

        if ($student_id != "" && $sub_institute_id != "" && $syear != "") {
            $data = DB::table("parent_communication as pc")
                ->leftJoin('tbluser as u', function ($join) {
                    $join->whereRaw("u.id = pc.reply_by");
                })
                ->selectRaw("pc.title,pc.message,pc.created_at,pc.reply,DATE_FORMAT(pc.reply_on,'%d-%m-%Y') AS reply_on,
                        CONCAT_WS(' ',u.first_name,u.middle_name,u.last_name) as reply_by")
                ->where("pc.student_id", "=", $student_id)
                ->where("pc.sub_institute_id", "=", $sub_institute_id)
                ->where("pc.syear", "=", $syear)
                ->orderBy('pc.date_', 'DESC')
                ->get()->toArray();

            $res['status_code'] = 1;
            $res['message'] = "Success";
            $res['data'] = $data;
        } else {
            $res['status_code'] = 0;
            $res['message'] = "Parameter Missing";
        }

        return json_encode($res);
    }
}
