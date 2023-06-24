<?php

namespace App\Http\Controllers\front_desk\leave_application;

use App\Http\Controllers\Controller;
use App\Models\school_setup\SchoolModel;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function App\Helpers\is_mobile;
use function App\Helpers\send_FCM_Notification;
use function App\Helpers\sendNotification;

class leaveApplicationController extends Controller
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
                $school_data['message'] = $data_arr['message'];
            }
        }

        $school_data['data'] = [];

        $type = $request->input('type');

        return is_mobile($type, "front_desk/leaveApplication/show", $school_data, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Request $request)
    {
        $grade_id = "";
        $standard_id = "";
        $division_id = "";
        $extra_where = "";
        $classteacher_data = [];
        $grades_ids = '';
        $standards_ids = '';
        $divisions_ids = '';
        if (session()->get("user_profile_name") == "Teacher") {
            $where_arr = [
                "teacher_id" => session()->get("user_id"),
                "sub_institute_id" => session()->get("sub_institute_id"),
                "syear" => session()->get("syear"),
            ];

            $classteacher_data = DB::table('class_teacher')
                ->where($where_arr)
                ->get();
            $classteacher_data = json_decode(json_encode($classteacher_data), true);


            $grade_ids = $standard_ids = $division_ids = '';
            foreach ($classteacher_data as $key => $classteacher) {
                $grade_ids .= $classteacher['grade_id'].',';
                $standard_ids .= $classteacher['standard_id'].',';
                $division_ids .= $classteacher['division_id'].',';
            }
            $grades_ids = rtrim($grade_ids, ',');
            $standards_ids = rtrim($standard_ids, ',');
            $divisions_ids = rtrim($division_ids, ',');

        }

        $requestData = $_REQUEST;
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
            ->join('leave_applications as pc', function ($join) {
                $join->whereRaw("pc.student_id = s.id AND se.syear = pc.syear");
            })
            ->leftJoin('tbluser as u', function ($join) {
                $join->whereRaw("u.id = pc.reply_by");
            })
            ->selectRaw("s.*,se.syear,se.student_id,se.grade_id,se.standard_id,se.section_id,se.student_quota,
                    se.start_date,se.end_date,se.enrollment_code,se.drop_code,se.drop_remarks,se.drop_remarks,se.term_id,se.remarks,
                    se.admission_fees,se.house_id,se.lc_number,st.name standard_name,d.name as division_name,pc.id AS leave_app_id,
                    pc.syear,pc.student_id,pc.message,pc.reply,pc.apply_date,ifnull(pc.status,'') status,pc.from_date,
                    pc.to_date,pc.files,CONCAT_WS(' ',u.first_name,u.middle_name,u.last_name) as reply_by")
            ->where("s.sub_institute_id", "=", session()->get('sub_institute_id'))
            ->where("se.syear", "=", session()->get('syear'))
            ->where(function ($q) use ($classteacher_data, $grades_ids, $standards_ids, $divisions_ids, $requestData) {
                if (count($classteacher_data) > 0) {
                    $q->whereRaw("se.grade_id IN  (" . $grades_ids . ") AND se.standard_id IN (" . $standards_ids . ")
                                AND se.section_id IN (" . $divisions_ids . ")");
                } else {
                    if (isset($requestData['grade']) && $requestData['grade'] != '') {
                        $q->where('se.grade_id', $requestData['grade']);
                    }
                    if (isset($requestData['standard']) && $requestData['standard'] != '') {
                        $q->where('se.standard_id', $requestData['standard']);
                    }
                    if (isset($requestData['division']) && $requestData['division'] != '') {
                        $q->where('se.section_id', $requestData['division']);
                    }
                }

                if (isset($_REQUEST['from_date']) && $_REQUEST['from_date'] != '') {
                    $q->where('pc.apply_date', '>=', $requestData['from_date']);
                }
                if (isset($_REQUEST['to_date']) && $_REQUEST['to_date'] != '') {
                    $q->where('pc.apply_date', '<=', $requestData['to_date']);
                }
            })
            ->get()->toarray();

        $responce_arr = [];
        foreach ($result as $id => $arr) {
            $responce_arr['stu_data'][$id]['sr.no'] = $id + 1;
            $responce_arr['stu_data'][$id]['name'] = $arr->first_name.' '.$arr->middle_name.' '.$arr->last_name;
            $responce_arr['stu_data'][$id]['student_id'] = $arr->student_id;
            $responce_arr['stu_data'][$id]['leave_app_id'] = $arr->leave_app_id;
            $responce_arr['stu_data'][$id]['stddiv'] = $arr->standard_name."/".$arr->division_name;
            $responce_arr['stu_data'][$id]['mobile'] = $arr->mobile;
            $responce_arr['stu_data'][$id]['message'] = $arr->message;
            $responce_arr['stu_data'][$id]['files'] = $arr->files;
            $responce_arr['stu_data'][$id]['status'] = $arr->status;
            $responce_arr['stu_data'][$id]['apply_date'] = date("d-m-Y", strtotime($arr->apply_date));
            $responce_arr['stu_data'][$id]['from_date'] = date("d-m-Y", strtotime($arr->from_date));
            $responce_arr['stu_data'][$id]['to_date'] = date("d-m-Y", strtotime($arr->to_date));
            $responce_arr['stu_data'][$id]['reply'] = $arr->reply;
            $responce_arr['stu_data'][$id]['reply_by'] = $arr->reply_by;
        }
        $type = $request->input('type');

        return is_mobile($type, "front_desk/leaveApplication/add", $responce_arr, "view");
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
        foreach ($_REQUEST['reply'] as $leave_app_id => $reply) {
            DB::table('leave_applications')
                ->where('id', $leave_app_id)
                ->where('sub_institute_id', session()->get('sub_institute_id'))
                ->where('syear', session()->get('syear'))
                ->update([
                    'reply'    => $reply,
                    'reply_on' => date('Y-m-d H:i:s'),
                    'reply_by' => session()->get('user_id'),
                ]);
        }
        foreach ($_REQUEST['status'] as $leave_app_id => $status_data) {
            DB::table('leave_applications')
                ->where('id', $leave_app_id)
                ->where('sub_institute_id', session()->get('sub_institute_id'))
                ->where('syear', session()->get('syear'))
                ->update([
                    'status' => $status_data, 'reply_on' => date('Y-m-d H:i:s'),
                ]);

            //START Send Notification Code


            $get_student = DB::table("leave_applications")
                ->where("id", "=", $leave_app_id)
                ->where("syear", "=", session()->get('syear'))
                ->where("sub_institute_id", "=", session()->get('sub_institute_id'))
                ->get()->toarray();


            $student_id = $get_student[0]->student_id;
            $apply_date = date('d-m-Y', strtotime($get_student[0]->apply_date));
            $reply_on_date = date('d-m-Y', strtotime($get_student[0]->reply_on));

            $student_data = DB::table("tblstudent_enrollment as se")
                ->join('tblstudent as s', function ($join) {
                    $join->whereRaw("s.id = se.student_id AND s.sub_institute_id = se.sub_institute_id");
                })
                ->selectRaw("*,concat_ws(' ',s.first_name,s.middle_name,s.last_name) as student_name")
                ->where("s.id", "=", $student_id)
                ->where("se.syear", "=", session()->get('syear'))
                ->whereNull("se.end_date")
                ->where("se.sub_institute_id", "=", session()->get('sub_institute_id'))
                ->get()->toArray();

            $schoolData = SchoolModel::where(['id' => session()->get('sub_institute_id')])->get()->toArray();
            $schoolName = $schoolData[0]['SchoolName'];
            $schoolLogo = $_SERVER['APP_URL'].'/admin_dep/images/'.$schoolData[0]['Logo'];

            if (count($student_data) > 0) {
                $mobile_no = $student_data[0]->mobile;
                $student_name = $student_data[0]->student_name;

                $pushMessage = "Dear Parents, Your message : ".$get_student[0]->message." on date : ".$apply_date." <br>"." Reply : ".$get_student[0]->reply." on date : ".$reply_on_date." & status of Leave Application is : ".$get_student[0]->status;

                $app_notification_content = [
                    'NOTIFICATION_TYPE'        => 'Leave Application',
                    'NOTIFICATION_DATE'        => $get_student[0]->reply_on,
                    'STUDENT_ID'               => $student_id,
                    'NOTIFICATION_DESCRIPTION' => $pushMessage,
                    'STATUS'                   => 0,
                    'SUB_INSTITUTE_ID'         => session()->get('sub_institute_id'),
                    'SYEAR'                    => session()->get('syear'),
                    'SCREEN_NAME'              => 'leave_application',
                    'CREATED_BY'               => session()->get('user_id'),
                    'CREATED_IP'               => $_SERVER['REMOTE_ADDR'],
                ];

                $gcm_data = DB::table("gcm_users")
                    ->where("mobile_no", "=", $mobile_no)
                    ->where("sub_institute_id", "=", session()->get('sub_institute_id'))
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
                        if (isset($val)) {
                            $type = 'Parent Communication';
                            $message = array(
                                'body'  => $pushMessage, 'TYPE' => $type, 'USER_ID' => $student_id,
                                'title' => $schoolName, 'image' => $schoolLogo,
                            );
                            $pushStatus = send_FCM_Notification($val, $message);
                            sendNotification($app_notification_content);
                        }
                    }
                }
            }
            //END Send Notification Code

        }
        $res = [
            "status_code" => 1,
            "message"     => "Leave Application Update Successufully.",
        ];

        $type = $request->input('type');

        return is_mobile($type, "leave_application.index", $res, "redirect");
    }

    public function add_leave(Request $request)
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

        $response = [];//array('response' => '', 'success' => false);
        $validator = Validator::make($request->all(), [
            'student_id'       => 'required|numeric',
            'syear'            => 'required|numeric',
            'sub_institute_id' => 'required|numeric',
            'message'          => 'required',
            'title'            => 'required',
            'apply_date'       => 'required|date_format:Y-m-d',
            'from_date'        => 'required|date_format:Y-m-d',
            'to_date'          => 'required|date_format:Y-m-d',
        ]);
        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {

            $file_name = $file_size = $ext = "";
            if ($request->hasFile('attechment')) {
                $file = $request->file('attechment');
                $originalname = $file->getClientOriginalName();
                $file_size = $file->getSize();
                $name = $request->get('attechment').date('YmdHis');
                $ext = File::extension($originalname);
                $file_name = "attechment_".$name.'.'.$ext;
                $path = $file->storeAs('public/leave_application/', $file_name);
            }

            $data = [
                'syear'            => $_REQUEST['syear'],
                'student_id'       => $_REQUEST['student_id'],
                'title'            => $_REQUEST['title'],
                'message'          => $_REQUEST['message'],
                'apply_date'       => $_REQUEST['apply_date'],
                'from_date'        => $_REQUEST['from_date'],
                'to_date'          => $_REQUEST['to_date'],
                'sub_institute_id' => $_REQUEST['sub_institute_id'],
                'files'            => $file_name,
                'file_size'        => $file_size,
                'file_type'        => $ext,
                'created_at'       => now(),
                'updated_at'       => now(),
            ];

            DB::table('leave_applications')
                ->insert($data);
            $response['status'] = 1;
            $response['message'] = "Record Added";
        }

        return json_encode($response);
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

    public function studentLeaveApplicationAPI(Request $request)
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

        $student_id = $request->input("student_id");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");

        if ($student_id != "" && $sub_institute_id != "" && $syear != "") {

            $data = DB::table("leave_applications as l")
                ->selectRaw("l.title,l.message,if(l.files = '','',concat('https://".$_SERVER['SERVER_NAME']."/storage/leave_application/',
                    l.files)) as files,l.apply_date,l.from_date,l.to_date,l.status,l.reply,l.reply_on")
                ->where("l.syear", "=", $syear)
                ->where("l.sub_institute_id", "=", $sub_institute_id)
                ->where("l.student_id", "=", $student_id)
                ->get()->toArray();

            $res['status'] = 1;
            $res['message'] = "Success";
            $res['data'] = $data;
        } else {
            $res['status'] = 0;
            $res['message'] = "Parameter Missing";
        }

        return json_encode($res);
    }

    public function teacherLeaveApplicationListAPI(Request $request)
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

        $teacher_id = $request->input("teacher_id");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");

        if ($teacher_id != "" && $sub_institute_id != "" && $syear != "") {
            $data = DB::table("leave_applications as la")
                ->join('tblstudent_enrollment as se', function ($join) {
                    $join->whereRaw("se.student_id = la.student_id AND la.sub_institute_id = se.sub_institute_id AND se.syear = la.syear");
                })
                ->join('tblstudent as s', function ($join) {
                    $join->whereRaw("s.id = se.student_id and s.sub_institute_id = se.sub_institute_id");
                })
                ->join('standard as st', function ($join) {
                    $join->whereRaw("st.id = se.standard_id");
                })
                ->join('division as di', function ($join) {
                    $join->whereRaw("di.id = se.section_id");
                })
                ->join('class_teacher as ct', function ($join) {
                    $join->whereRaw("ct.standard_id = se.standard_id and ct.division_id = se.section_id and se.sub_institute_id = ct.sub_institute_id");
                })
                ->leftJoin('tbluser as u', function ($join) {
                    $join->whereRaw("u.id=la.reply_by AND u.sub_institute_id = la.sub_institute_id");
                })
                ->selectRaw("la.id as leave_app_id,concat_ws(' ',s.first_name,s.middle_name,s.last_name) as student_name,
                    if(s.image = '','',concat('https://".$_SERVER['SERVER_NAME']."/storage/student/',s.image)) as student_image,
                    st.name as std_name,la.title,la.message,if(files = '','',concat('https://".$_SERVER['SERVER_NAME']."/storage/leave_application/',files))
                    as file_name,la.apply_date,la.reply,la.reply_on,concat_ws(' ',u.first_name,u.middle_name,u.last_name) as reply_by,la.`status`")
                ->where("la.syear", "=", $syear)
                ->where("la.sub_institute_id", "=", $sub_institute_id)
                ->where("ct.teacher_id", "=", $teacher_id)
                ->get()->toarray();

            $res['status'] = 1;
            $res['message'] = "Success";
            $res['data'] = $data;

        } else {
            $res['status'] = 0;
            $res['message'] = "Parameter Missing";
        }

        return json_encode($res);
    }

    public function teacherLeaveApplicationSaveAPI(Request $request)
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
        $leave_app_id = $request->input("leave_app_id");
        $reply = $request->input("reply");
        $status = $request->input("status");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");

        if ($teacher_id != "" && $sub_institute_id != "" && $syear != "" && $leave_app_id != "" && $reply != "" && $status != "") {
            DB::table('leave_applications')
                ->where('id', $leave_app_id)
                ->where('sub_institute_id', $sub_institute_id)
                ->where('syear', $syear)
                ->update([
                    'reply'    => $reply,
                    'status'   => $status,
                    'reply_on' => date('Y-m-d H:i:s'),
                    'reply_by' => $teacher_id,

                ]);

            $res['status'] = 1;
            $res['message'] = "Success";
        } else {
            $res['status'] = 0;
            $res['message'] = "Parameter Missing";
        }

        return json_encode($res);
    }
}
