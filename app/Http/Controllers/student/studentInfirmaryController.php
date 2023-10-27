<?php

namespace App\Http\Controllers\student;

use App\Http\Controllers\Controller;
use App\Models\school_setup\SchoolModel;
use App\Models\student\studentInfirmaryModel;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use function App\Helpers\is_mobile;
use function App\Helpers\send_FCM_Notification;
use function App\Helpers\sendNotification;

class studentInfirmaryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    use GetsJwtToken;

    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $result = DB::table('student_infirmary as si')
            ->join('tblstudent as s', function ($join) {
                $join->whereRaw('si.student_id = s.id');
            })->selectRaw("si.*, CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) AS student_name")
            ->where('si.sub_institute_id', $sub_institute_id)
            ->orderBy('si.id', 'DESC')->get()->toArray();

        $result = array_map(function ($value) {
            return (array) $value;
        }, $result);

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['data'] = $result;

        return is_mobile($type, "student/infirmary/show_student_infirmary", $res, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Application|Factory|View
     */
    public function create(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        
        $medical_case_no = DB::table("student_infirmary")
            ->selectRaw('(IFNULL(MAX(CAST(medical_case_no AS INT)),0) + 1) AS medical_case_no')
            ->where("sub_institute_id", "=", $sub_institute_id)
            ->where("syear", "=", $syear)
            ->get()->toArray();

        view()->share('medical_case_no', $medical_case_no[0]->medical_case_no);
        return view('student/infirmary/add_student_infirmary');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $term_id = $request->session()->get('term_id');
        $syear = $request->session()->get('syear');
        $type = $request->input('type');
        $user_id = $request->session()->get('user_id');

        $finalArray = $request->except('_method', '_token', 'submit');

        $STUDENT = $request->input("student_id");
        $STUDENT = explode("-", $STUDENT);
        $student_id = trim($STUDENT[1] ?? '');

        if (empty($student_id)) {
            throw ValidationException::withMessages([
                'student_id' => 'Please select proper student with id',
            ]);
        }

        $finalArray['student_id'] = $student_id;

        $finalArray['created_by'] = $user_id;
        $finalArray['syear'] = $syear;
        $finalArray['marking_period_id'] = $term_id;
        $finalArray['sub_institute_id'] = $sub_institute_id;
        $finalArray['created_on'] = date('Y-m-d H:i:s');

        studentInfirmaryModel::insert($finalArray);

        //START Send Notification Code
        $student_data = DB::table('tblstudent as s')
            ->selectRaw("*,s.id as stu_id,concat_ws(' ',s.first_name,s.middle_name,s.last_name) as student_name")
            ->where('s.sub_institute_id', $sub_institute_id)
            ->where('s.id', $student_id)->get()->toArray();

        $schoolData = SchoolModel::where(['id' => $sub_institute_id])->get()->toArray();
        $schoolName = $schoolData[0]['SchoolName'];
        $schoolLogo = $_SERVER['APP_URL'].'/admin_dep/images/'.$schoolData[0]['Logo'];

        if (count($student_data) > 0) {
            foreach ($student_data as $key => $val) {
                $student_id = $val->stu_id;
                $mobile_no = $val->mobile;
                $student_name = $val->student_name;

                $pushMessage = "Dear ".$student_name.", Infirmary details has been added for date : ".
                    date('d-m-Y',
                        strtotime($_REQUEST['date']))." . Case No.: ".$_REQUEST['medical_case_no']." ,
                    Name : ".$_REQUEST['doctor_name']." , Contact: ".$_REQUEST['doctor_contact'];

                $app_notification_content = [
                    'NOTIFICATION_TYPE'        => 'Infirmary',
                    'NOTIFICATION_DATE'        => $_REQUEST['date'],
                    'STUDENT_ID'               => $student_id,
                    'NOTIFICATION_DESCRIPTION' => $_REQUEST['complaint'].' - '.$pushMessage,
                    'STATUS'                   => 0,
                    'SUB_INSTITUTE_ID'         => $sub_institute_id,
                    'SYEAR'                    => $syear,
                    'SCREEN_NAME'              => 'student_infirmary',
                    'CREATED_BY'               => $user_id,
                    'CREATED_IP'               => $_SERVER['REMOTE_ADDR'],
                ];

                $gcm_data = DB::table('gcm_users')->where('mobile_no', $mobile_no)
                    ->where('sub_institute_id', $sub_institute_id)->get()->toArray();

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
                            $type = 'Infirmary';
                            $message = [
                                'body'  => $pushMessage, 'TYPE' => $type, 'USER_ID' => $student_id,
                                'title' => $schoolName, 'image' => $schoolLogo,
                            ];
                            $pushStatus = send_FCM_Notification($val, $message, $sub_institute_id);
                            sendNotification($app_notification_content);
                        }
                    }
                }

            }
        }
        //END Send Notification Code

        $id = DB::getPdo()->lastInsertId();

        $res['status_code'] = 1;
        $res['message'] = "Student Infirmary successfully created.";

        return is_mobile($type, "student_infirmary.index", $res);
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
     * @return Application|Factory|View
     */
    public function edit(Request $request, $id)
    {
        $sub_institute_id = $request->session()->get("sub_institute_id");

        $result = DB::table('student_infirmary as si')
            ->join('tblstudent as s', function ($join) {
                $join->whereRaw('si.student_id = s.id');
            })->selectRaw("si.*, CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) AS student_name")
            ->where('si.sub_institute_id', $sub_institute_id)
            ->where('si.id', $id)
            ->orderBy('si.id', 'DESC')->get()->toArray();

        $result = array_map(function ($value) {
            return (array) $value;
        }, $result);

        $editData = $result[0];

        return view('student/infirmary/edit_student_infirmary', ['data' => $editData]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $term_id = $request->session()->get('term_id');
        $syear = $request->session()->get('syear');
        $type = $request->input('type');
        $user_id = $request->session()->get('user_id');

        $finalArray = $request->except('_method', '_token', 'submit');

        $STUDENT = $request->input("student_id");
        $STUDENT = explode("-", $STUDENT);
        $finalArray['student_id'] = trim($STUDENT[1]);

        $data = studentInfirmaryModel::where(['id' => $id])->update($finalArray);

        $res['status_code'] = 1;
        $res['message'] = "Student Infirmary successfully updated.";

        return is_mobile($type, "student_infirmary.index", $res);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        studentInfirmaryModel::where(["id" => $id])->delete();
        $res['status_code'] = "1";
        $res['message'] = "Student Infirmary deleted successfully";

        return is_mobile($type, "student_infirmary.index", $res);
    }

    public function studentHealthReport(Request $request)
    {
        return view('student/show_student_health_report');
    }

    public function showStudentHealthReport(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $req = $request->except('_token', '_method', 'submit');
        $marking_period_id = session()->get('term_id');

        $result = DB::table($req['health_type']." as si")
            ->join('tblstudent as s', function ($join) use($marking_period_id){
                $join->whereRaw("si.student_id = s.id")->when(function($query) use($marking_period_id){
                    $query->where('s.marking_period_id',$marking_period_id);
                });
            })->join('tblstudent_enrollment as se', function ($join) use ($syear) {
                $join->whereRaw("se.student_id = s.id and se.syear = '".$syear."'");
            })->selectRaw("si.*, CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) AS student_name")
            ->where('si.sub_institute_id', $sub_institute_id);

        $headers = [];

        if ($req['health_type'] == 'student_infirmary') {
            $headers['student_name'] = "Student Name";
            $headers['doctor_name'] = "Doctor Name";
            $headers['doctor_contact'] = "Doctor Contact";
            $headers['date'] = "Date";
            $headers['complaint'] = "Complaint";
            $headers['symptoms'] = "Symptoms";
            $headers['disease'] = "Disease";
            $headers['treatments'] = "Treatments";
            $headers['medical_close_date'] = "Medical Close Date";
        }
        if ($req['health_type'] == 'student_vaccination') {
            $headers['student_name'] = "Student Name";
            $headers['doctor_name'] = "Doctor Name";
            $headers['vaccination_type'] = "Vaccination Type";
            $headers['note'] = "Note";
            $headers['date'] = "Date";
        }
        if ($req['health_type'] == 'student_height_weight') {
            $headers['student_name'] = "Student Name";
            $headers['doctor_name'] = "Doctor Name";
            $headers['doctor_contact'] = "Doctor Contact";
            $headers['height'] = "Height";
            $headers['weight'] = "Weight";
        }
        if ($req['health_type'] == 'student_health') {
            $headers['student_name'] = "Student Name";
            $headers['doctor_name'] = "Doctor Name";
            $headers['doctor_contact'] = "Doctor Contact";
            $headers['date'] = "Date";
            $headers['file'] = "File";
        }


        if ($req['grade'] != '') {
            $result = $result->where('se.grade_id', $req['grade']);
        }

        if ($req['standard'] != '') {
            $result = $result->where('se.standard_id', $req['standard']);
        }

        if ($req['division'] != '') {
            $result = $result->where('se.section_id', $req['division']);
        }

        if ($req['from_date'] != '') {
            $result = $result->where('si.date', '>=', $req['from_date']);
        }

        if ($req['to_date'] != '') {
            $result = $result->where('si.date', '<=', $req['to_date']);
        }

        $result = $result->orderBy('si.id', 'DESC')->get()->toArray();

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['health_data'] = $result;
        $res['headers'] = $headers;
        $res['grade_id'] = $req['grade'];
        $res['standard_id'] = $req['standard'];
        $res['division_id'] = $req['division'];
        $res['health_type'] = $req['health_type'];
        $res['from_date'] = $req['from_date'];
        $res['to_date'] = $req['to_date'];

        return is_mobile($type, "student/show_student_health_report", $res, "view");
    }

    public function studentInfirmaryAPI(Request $request)
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

        if ($student_id != "" && $sub_institute_id != "" && $syear != "") {
            $data = DB::table('student_infirmary as si')
                ->selectRaw("si.id,si.student_id,si.doctor_name,si.doctor_contact,si.medical_case_no,
                    DATE_FORMAT(si.date,'%d-%m-%Y') AS date,si.complaint,si.symptoms,si.disease,si.treatments,si.medical_case_no,
                    DATE_FORMAT(si.medical_close_date,'%d-%m-%Y') AS medical_close_date,si.health_center")
                ->where('si.sub_institute_id', $sub_institute_id)
                ->where('si.student_id', $student_id)
                ->where('si.syear', $syear)->get()->toArray();

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
