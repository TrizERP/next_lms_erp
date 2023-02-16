<?php

namespace App\Http\Controllers\student;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\student\studentInfirmaryModel;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;
use GenTux\Jwt\JwtToken;
use GenTux\Jwt\GetsJwtToken;
use function App\Helpers\aut_token;
use App\Models\school_setup\SchoolModel;
use function App\Helpers\sendNotification;
use function App\Helpers\send_FCM_Notification;

class studentInfirmaryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    use GetsJwtToken;
    
    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        
        $data = "SELECT si.*, CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) AS student_name
        FROM student_infirmary si
        INNER JOIN tblstudent s ON si.student_id = s.id
        WHERE si.sub_institute_id = '".$sub_institute_id."' order by si.id desc ";

        $result = DB::select($data);

        $result = array_map(function ($value) {
            return (array)$value;
        }, $result);

        // dd($result);

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['data'] = $result;
        
        return is_mobile($type, "student/infirmary/show_student_infirmary", $res, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('student/infirmary/add_student_infirmary');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $term_id = $request->session()->get('term_id');
        $syear = $request->session()->get('syear');
        $type = $request->input('type');
        $user_id = $request->session()->get('user_id');

        $finalArray = $request->except('_method','_token','submit');

        $STUDENT = $request->input("student_id");
        $STUDENT = explode("-",$STUDENT);
        $student_id = trim($STUDENT[1]);
        $finalArray['student_id'] = $student_id;

        $finalArray['created_by'] = $user_id;
        $finalArray['syear'] = $syear;
        $finalArray['marking_period_id'] = $term_id;
        $finalArray['sub_institute_id'] = $sub_institute_id;
        $finalArray['created_on'] = date('Y-m-d H:i:s');
        // dd($finalArray);
        studentInfirmaryModel::insert($finalArray);

        //START Send Notification Code
        $student_sql = "SELECT *,s.id as stu_id,concat_ws(' ',s.first_name,s.middle_name,s.last_name) as student_name 
                FROM tblstudent s
                WHERE s.sub_institute_id = '".$sub_institute_id."' AND s.id = '".$student_id."' ";
        $student_data = DB::select($student_sql);

        $schoolData = SchoolModel::where(['id' => $sub_institute_id])->get()->toArray(); 
        $schoolName = $schoolData[0]['SchoolName'];
        $schoolLogo = $_SERVER['APP_URL'].'/admin_dep/images/'.$schoolData[0]['Logo'];

        if(count($student_data) > 0)
        {
            foreach($student_data as $key => $val)
            {
                $student_id = $val->stu_id;
                $mobile_no = $val->mobile;
                $student_name = $val->student_name;

                $pushMessage = "Dear Parents, ".$student_name." Infirmary details has been added for date : ".date('d-m-Y', strtotime($_REQUEST['date']))." . Details are Medical Case No.: ".$_REQUEST['medical_case_no']." ,Doctore Name : ".$_REQUEST['doctor_name']." ,Doctore Concat No.: ".$_REQUEST['doctor_contact'];

                $app_notification_content = array(
                    'NOTIFICATION_TYPE' => 'Infirmary',
                    'NOTIFICATION_DATE' => $_REQUEST['date'],                 
                    'STUDENT_ID' => $student_id,                   
                    'NOTIFICATION_DESCRIPTION' => $_REQUEST['complaint'].' - '.$pushMessage,
                    'STATUS' => 0,
                    'SUB_INSTITUTE_ID' => $sub_institute_id,                  
                    'SYEAR' =>  $syear,
                    'SCREEN_NAME' => 'student_infirmary',
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
                            $type = 'Infirmary';
                            $message = array('body' => $pushMessage, 'TYPE' => $type, 'USER_ID' => $student_id, 'title' => $schoolName, 'image' => $schoolLogo);
                            $pushStatus = send_FCM_Notification($val, $message);
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
    public function edit(Request $request, $id)
    {
        $sub_institute_id = $request->session()->get("sub_institute_id") ;

        $data = "SELECT si.*, CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) AS student_name
        FROM student_infirmary si
        INNER JOIN tblstudent s ON si.student_id = s.id
        WHERE si.sub_institute_id = '".$sub_institute_id."' and si.id = '".$id."' order by si.id desc";

        $result = DB::select($data);

        $result = array_map(function ($value) {
            return (array)$value;
        }, $result);

        $editData = $result[0];

        return view('student/infirmary/edit_student_infirmary',['data' => $editData]);
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
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $term_id = $request->session()->get('term_id');
        $syear = $request->session()->get('syear');
        $type = $request->input('type');
        $user_id = $request->session()->get('user_id');

        $finalArray = $request->except('_method','_token','submit');

        $STUDENT = $request->input("student_id");
        $STUDENT = explode("-",$STUDENT);
        $finalArray['student_id'] = trim($STUDENT[1]);

        $data = studentInfirmaryModel::where(['id'=>$id])->update($finalArray);
        
        $res['status_code'] = 1;
        $res['message'] = "Student Infirmary successfully updated.";
        
        return is_mobile($type, "student_infirmary.index", $res);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request,$id)
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
        $req = $request->except('_token','_method','submit');
        
        $data = "SELECT si.*, CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) AS student_name
        FROM ".$req['health_type']." si
        INNER JOIN tblstudent s ON si.student_id = s.id
        INNER JOIN tblstudent_enrollment se ON se.student_id = s.id and se.syear = '".$syear."'
        WHERE si.sub_institute_id = '".$sub_institute_id."' ";

        $headers =array();

        if($req['health_type'] == 'student_infirmary')
        {
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
        if($req['health_type'] == 'student_vaccination')
        {
            $headers['student_name'] = "Student Name";
            $headers['doctor_name'] = "Doctor Name";
            $headers['vaccination_type'] = "Vaccination Type";
            $headers['note'] = "Note";
            $headers['date'] = "Date";
        }
        if($req['health_type'] == 'student_height_weight')
        {
            $headers['student_name'] = "Student Name";
            $headers['doctor_name'] = "Doctor Name";
            $headers['doctor_contact'] = "Doctor Contact";
            $headers['height'] = "Height";
            $headers['weight'] = "Weight";
        }
        if($req['health_type'] == 'student_health')
        {
            $headers['student_name'] = "Student Name";
            $headers['doctor_name'] = "Doctor Name";
            $headers['doctor_contact'] = "Doctor Contact";
            $headers['date'] = "Date";
            $headers['file'] = "File";
        }
        

        if($req['grade'] != '')
        {
            $data .= "  AND se.grade_id = '".$req['grade']."'  ";
        }

        if($req['standard'] != '')
        {
            $data .= "  AND se.standard_id = '".$req['standard']."'  ";
        }

        if($req['division'] != '')
        {
            $data .= "  AND se.section_id = '".$req['division']."'  ";
        }

        if($req['from_date'] != '')
        {
            $data .= "  AND si.date >= '".$req['from_date']."'  ";
        }

        if($req['to_date'] != '')
        {
            $data .= "  AND si.date <= '".$req['to_date']."'  ";
        }

        $data .= "  order by si.id desc";
        
        $result = DB::select($data);
        
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

    public function studentInfirmaryAPI(Request $request) {

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
            $data = DB::select("SELECT si.id,si.student_id,si.doctor_name,si.doctor_contact,si.medical_case_no,DATE_FORMAT(si.date,'%d-%m-%Y') AS date,si.complaint,si.symptoms,si.disease,si.treatments,si.medical_case_no,DATE_FORMAT(si.medical_close_date,'%d-%m-%Y') AS medical_close_date,si.health_center FROM student_infirmary si
                WHERE si.sub_institute_id = '".$sub_institute_id."' AND si.student_id = '".$student_id."' AND si.syear = '".$syear."'
                ORDER BY si.date");
            
            $res['status_code'] = 1;
            $res['message'] = "Success";
            $res['data'] = $data;   
        
        }else{
            $res['status_code'] = 0;
            $res['message'] = "Parameter Missing";
        }
        return json_encode($res);
        // return is_mobile($type, "implementation", $res);    
    }
}
