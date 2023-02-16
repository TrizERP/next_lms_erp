<?php

namespace App\Http\Controllers\student;

use App\Http\Controllers\Controller;
use App\Models\school_setup\subjectModel;
use App\Models\student\studentHomeworkModel;
use function App\Helpers\is_mobile;
use function App\Helpers\getStudents;
use function App\Helpers\SearchStudent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use GenTux\Jwt\JwtToken;
use GenTux\Jwt\GetsJwtToken;
use function App\Helpers\aut_token;
use function App\Helpers\sendNotification;

class studentHomeworkController extends Controller {
	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */

	use GetsJwtToken;
	
	public function index(Request $request) {
		$type = $request->input('type');
		$submit = $request->input('submit');
		$sub_institute_id = $request->session()->get('sub_institute_id');
		$res['status_code'] = 1;
		$res['message'] = "Success";

		$subjects = subjectModel::select('id', 'subject_name')->where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

		$res['subjects'] = $subjects;

		return is_mobile($type, "student/homework/show_student_homework", $res, "view");
	}

	/**
	 * Show the form for creating a new resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function create(Request $request) {
        // echo '<pre>'; print_r($_REQUEST); exit;
		$grade = $request->input('grade');
		$standard = $request->input('standard');
		$division = $request->input('division');
		$subject = $request->input('subject');
		$type = $request->input('type');
		if($type == "API")
		{
			$sub_institute_id = $request->input('sub_institute_id');
			$syear = $request->input('syear');
		}else{
			$sub_institute_id = $request->session()->get('sub_institute_id');
			$syear = session()->get('syear');
		}

		$data = SearchStudent($grade, $standard, $division, $sub_institute_id, $syear);

		$subjects = subjectModel::select('id', 'subject_name')->where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

		$res['status_code'] = 1;
		$res['message'] = "Success";
		$res['student_data'] = $data;
		$res['subjects'] = $subjects;
		$res['grade_id'] = $grade;
		$res['standard_id'] = $standard;
		$res['division_id'] = $division;

		$res['subject'] = $subject;

		return is_mobile($type, "student/homework/show_student_homework", $res, "view");
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @return \Illuminate\Http\Response
	 */
    public function fetchData(Request $request)
    {
        $response = array('response' => '', 'success' => false);
        $validator =  Validator::make($request->all(), [
            'student_id' => 'required|numeric',
            'syear' => 'required|numeric',
            'sub_institute_id' => 'required|numeric'
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
                // $standard_id = $result[0]->standard_id;
                // $division_id = $result[0]->section_id;
                // echo ('<pre>');print_r($_SERVER);exit;
                $server = "http://".$_SERVER['HTTP_HOST'];
                
                $data_sql = "SELECT hm.id,hm.student_id,hm.sub_institute_id,hm.title,
                hm.description,hm.date,if(hm.image IS NULL OR hm.image='','-',concat('$server/storage/student/',hm.image)) file,s.subject_name
                FROM homework hm
                INNER JOIN subject s ON s.id = hm.subject_id
                WHERE hm.student_id = $student_id
                AND hm.syear = $syear
                AND hm.sub_institute_id = $sub_institute_id
                AND hm.type='Homework'
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

	public function store(Request $request) {
        // echo '<pre>'; print_r($_REQUEST); exit;

		$type = $request->get('type');
		if($type == "API")
		{
			$sub_institute_id = $request->input('sub_institute_id');
			$syear = $request->input('syear');
		}else{
			$sub_institute_id = $request->session()->get('sub_institute_id');
			$syear = session()->get('syear');
		}

        $students = $request->get('students');
        $student_details = getStudents($students,$sub_institute_id,$syear);
        // echo '<pre>'; print_r($student_details); exit;
		$title = $request->get('title');
		$description = $request->get('description');
		$submission_date = $request->get('submission_date');
		$division_id = $request->get('division_id');
		$standard_id = $request->get('standard_id');
		$subject_id = $request->get('subject_id');
		$created_by = ($request->session()->get('user_id') ? $request->session()->get('user_id') : $request->get('teacher_id'));

		$file_name = $file_size = $ext = "";
		if ($request->hasFile('image')) {
			$file = $request->file('image');
			$originalname = $file->getClientOriginalName();
			$file_size = $file->getSize();
			$name = "homework-" . $request->get('user_name') . date('YmdHis');
			$ext = \File::extension($originalname);
			$file_name = $name . '.' . $ext;
			$path = $file->storeAs('public/student/', $file_name);
		}

		foreach ($student_details as $id => $arr) {
            $student_id = $arr['id'];
            $standard_id = $arr['standard_id'];
            $division_id = $arr['section_id'];
			$addhomeworkArray = array();
			$addhomeworkArray['student_id'] = $student_id;
			$addhomeworkArray['sub_institute_id'] = $sub_institute_id;
			$addhomeworkArray['title'] = $title;
			$addhomeworkArray['description'] = $description;
			$addhomeworkArray['standard_id'] = $standard_id;
			$addhomeworkArray['division_id'] = $division_id;
			$addhomeworkArray['subject_id'] = $subject_id;
			$addhomeworkArray['date'] = date('Y-m-d');
			$addhomeworkArray['submission_date'] = $submission_date;
			$addhomeworkArray['syear'] = $syear;
			$addhomeworkArray['type'] = "Homework";
			$addhomeworkArray['image'] = $file_name;
			$addhomeworkArray['image_size'] = $file_size;
			$addhomeworkArray['image_type'] = $ext;
			$addhomeworkArray['created_ip'] = $_SERVER['REMOTE_ADDR'];
			$addhomeworkArray['created_by'] = $created_by;
			studentHomeworkModel::insert($addhomeworkArray);

			//START Send Notification Code
			$app_notification_content = array(
	            'NOTIFICATION_TYPE' => 'Homework',
	            'NOTIFICATION_DATE' => date('Y-m-d'),
	            'STUDENT_ID' => $student_id,
	            'NOTIFICATION_DESCRIPTION' => $title,
	            'STATUS' => 0,
	            'SUB_INSTITUTE_ID' => $sub_institute_id,                  
	            'SYEAR' => $syear,
	            'SCREEN_NAME' => 'home_work',
	            'CREATED_BY' => $created_by,        
	            'CREATED_IP' => $_SERVER['REMOTE_ADDR']          
	        );
	        sendNotification($app_notification_content);  
	        //END Send Notification Code
		}

		$res['status_code'] = "1";
		$res['message'] = "Homework Added successfully";

		return is_mobile($type, "student_homework.index", $res);
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

	public function studentHomeworkReportIndex(Request $request) {
		$type = $request->input('type');
		$submit = $request->input('submit');
		$sub_institute_id = $request->session()->get('sub_institute_id');

		$subjects = subjectModel::select('id', 'subject_name')->where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

		$res['status_code'] = 1;
		$res['message'] = "Success";
		$res['subjects'] = $subjects;

		return is_mobile($type, "student/homework/show_student_homework_report", $res, "view");
	}

	public function studentHomeworkReport(Request $request) {
		$type = $request->input('type');
		$sub_institute_id = $request->session()->get('sub_institute_id');
		$syear = $request->session()->get('syear');
		$subject = $request->input('subject');
		$grade = $request->input('grade');
		$standard = $request->input('standard');
		$division = $request->input('division');
		$from_date = $request->input('from_date');
		$to_date = $request->input('to_date');

		$subjects = subjectModel::select('id', 'subject_name')->where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

		$query = "SELECT h.*,s.name as standard_name,d.name as division_name,ss.subject_name,CONCAT_WS(' ',ts.first_name,ts.last_name) as student_name
        FROM homework h
        INNER JOIN tblstudent ts ON ts.id = h.student_id AND ts.sub_institute_id = h.sub_institute_id
        INNER JOIN standard s ON h.standard_id = s.id AND h.sub_institute_id = s.sub_institute_id
        INNER JOIN division d ON d.id = h.division_id AND h.sub_institute_id= d.sub_institute_id
        INNER JOIN subject ss ON ss.id = h.subject_id AND ss.sub_institute_id = h.sub_institute_id
        WHERE h.sub_institute_id = '" . $sub_institute_id . "' AND h.syear = '" . $syear . "'";

		if ($standard != '') {
			$query .= "  AND h.standard_id = '" . $standard . "'";
		}

		if ($subject != '') {
			$query .= "  AND h.subject_id = '" . $subject . "'";
		}

		if ($division != '') {
			$query .= "  AND h.division_id = '" . $division . "'";
		}

		if ($grade != '') {
			$query .= "  AND s.grade_id = '" . $grade . "'";
		}

		if ($from_date != '') {
			$query .= "AND h.date >= '" . $from_date . "' ";
		}

		if ($to_date != '') {
			$query .= "AND h.date <= '" . $to_date . "' ";
		}

		$result = DB::select($query);

		$result = array_map(function ($value) {
			return (array) $value;
		}, $result);

		$res['status_code'] = 1;
		$res['message'] = "Success";
		$res['report_data'] = $result;
		$res['subjects'] = $subjects;
		$res['grade_id'] = $grade;
		$res['standard_id'] = $standard;
		$res['division_id'] = $division;
		$res['subject'] = $subject;
		$res['from_date'] = $from_date;
		$res['to_date'] = $to_date;

		return is_mobile($type, "student/homework/show_student_homework_report", $res, "view");
	}

	public function teacherHomeworkAssignmentAPI(Request $request) {
       try {
            if (!$this->jwtToken()->validate()) {
                $response = array('status' => '2', 'message' => 'Token Auth Failed', 'data' => array());
                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = array('status' => '2', 'message' => $e->getMessage(), 'data' => array());
            return response()->json($response, 401);
        }
                
        $teacher_id = $request->input("teacher_id");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");
        $action = $request->input("action");

        if($teacher_id != "" && $sub_institute_id != "" && $syear != "" && $action != "")
        {               

            $data = DB::select("SELECT h.id,h.title,h.description,h.date,if(h.image = '','',concat('https://".$_SERVER['SERVER_NAME']."/storage/student/',h.image)) as file_name,s.name AS standard_name,d.name AS division_name,ss.subject_name, 
CONCAT_WS(' ',ts.first_name,ts.middle_name,ts.last_name) AS student_name,ts.enrollment_no,ts.mobile,h.type
FROM homework h
INNER JOIN tblstudent ts ON ts.id = h.student_id AND ts.sub_institute_id = h.sub_institute_id
INNER JOIN standard s ON h.standard_id = s.id AND h.sub_institute_id = s.sub_institute_id
INNER JOIN division d ON d.id = h.division_id AND h.sub_institute_id= d.sub_institute_id
INNER JOIN subject ss ON ss.id = h.subject_id AND ss.sub_institute_id = h.sub_institute_id
INNER JOIN class_teacher ct ON ct.standard_id = h.standard_id AND ct.division_id = h.division_id
WHERE h.sub_institute_id = '".$sub_institute_id."' AND h.syear = '".$syear."' AND ct.teacher_id = '".$teacher_id."' AND h.type = '".$action."'             
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

    public function studentHomeworkAssignmentAPI(Request $request) {
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

        if($student_id != "" && $sub_institute_id != "" && $syear != "" && $action != "")
        {         

            $data = DB::select("SELECT h.id,h.title,h.description,DATE_FORMAT(h.date,'%d-%m-%Y') AS date, if(h.image = '','',concat('https://".$_SERVER['SERVER_NAME']."/storage/student/',h.image)) as file_name,s.name AS standard_name,d.name AS division_name,ss.subject_name, 
CONCAT_WS(' ',ts.first_name,ts.middle_name,ts.last_name) AS student_name,ts.enrollment_no,ts.mobile,h.type,if(tu.image != NULL,concat('https://".$_SERVER['SERVER_NAME']."/storage/student/',tu.image),'https://".$_SERVER['SERVER_NAME']."/storage/student/noimages.png') as user_image
FROM homework h
INNER JOIN tblstudent ts ON ts.id = h.student_id AND ts.sub_institute_id = h.sub_institute_id
INNER JOIN standard s ON h.standard_id = s.id AND h.sub_institute_id = s.sub_institute_id
INNER JOIN division d ON d.id = h.division_id AND h.sub_institute_id= d.sub_institute_id
INNER JOIN subject ss ON ss.id = h.subject_id AND ss.sub_institute_id = h.sub_institute_id
LEFT JOIN tbluser tu ON tu.id = h.created_by
WHERE h.sub_institute_id = '".$sub_institute_id."' 
AND h.syear = '".$syear."' AND h.type = '".$action."' AND h.student_id = '".$student_id."'
  ORDER BY h.date DESC ");
            
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
    public function studentSubjectAPI(Request $request) 
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
		
		$type = $request->input("type");
		$student_id = $request->input("student_id");
		$sub_institute_id = $request->input("sub_institute_id");
		$syear = $request->input("syear");

		if($student_id != "" && $sub_institute_id != "" && $syear != "")
		{
			// echo "SELECT * FROM tblstudent_enrollment 
			// WHERE student_id = '".$student_id."' AND syear = '".$syear."' AND sub_institute_id = '".$sub_institute_id."'";
			// exit;
			$stud_data = DB::select("SELECT * FROM tblstudent_enrollment 
			WHERE student_id = '".$student_id."' AND syear = '".$syear."' AND sub_institute_id = '".$sub_institute_id."'");
			if(count($stud_data) > 0)
			{			
				$standard_id = $stud_data[0]->standard_id;
				$section_id = $stud_data[0]->section_id;

				$data = DB::select("SELECT display_name AS subject_name,elective_subject,allow_grades,t.teacher_id,concat_ws(' ',tu.first_name,tu.middle_name,tu.last_name) as teacher_name
				FROM timetable t
				INNER JOIN sub_std_map s ON s.subject_id = t.subject_id
				INNER JOIN tbluser tu on tu.id = t.teacher_id
				WHERE t.syear = '".$syear."' AND t.sub_institute_id = '".$sub_institute_id."' 
				AND t.standard_id = '".$standard_id."' AND t.division_id = '".$section_id."'				
				GROUP BY t.subject_id
				ORDER BY display_name");
				
				$res['status'] = 1;
				$res['message'] = "Success";
				$res['data'] = $data;	
			}
			else{
				$res['status'] = 0;
				$res['message'] = "Wrong Parameters";
			}
		}else{
			$res['status'] = 0;
			$res['message'] = "Parameter Missing";
		}
		//return is_mobile($type, "implementation", $res);
		return json_encode($res);					
	}

	public function ajax_getHomeworkSubjects(Request $request)
    {
    	// dd(session()->all());
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $user_profile_name = session()->get('user_profile_name');
        $teacher_id = $request->session()->get('user_id');
    	$standard_id = $request->input('standard_id');

    	if($user_profile_name == 'Admin')
    	{
    		$subject_teacher_sql = "SELECT s.subject_id,s.display_name,s.standard_id,
    							'' as academic_section_id,'' as division_id,'' as teacher_id
								FROM sub_std_map s
								WHERE s.sub_institute_id = '".$sub_institute_id."' AND s.standard_id = '".$standard_id."' 
								GROUP BY s.subject_id,s.standard_id
								ORDER BY s.display_name";// AND t.syear = '".$syear."'
    	}
    	else
    	{
    		$subject_teacher_sql = "SELECT s.subject_id,s.display_name,t.academic_section_id,t.standard_id,t.division_id,t.teacher_id
								FROM sub_std_map s
								INNER JOIN timetable t ON t.standard_id = s.standard_id AND t.sub_institute_id = s.sub_institute_id AND t.subject_id = s.subject_id
								WHERE s.sub_institute_id = '".$sub_institute_id."' AND s.standard_id = '".$standard_id."' 
								AND t.teacher_id = '".$teacher_id."'  
								GROUP BY s.subject_id,s.standard_id
								ORDER BY s.display_name";// AND t.syear = '".$syear."'
    	}
    	
		$subject_teacher_subjects_data = DB::select($subject_teacher_sql);	
		$subject_teacher_subjects_data = json_decode(json_encode($subject_teacher_subjects_data),true);
		
		// $class_teacher_sql = "SELECT s.subject_id,s.display_name,ct.grade_id,ct.standard_id,ct.division_id,ct.teacher_id
		// 					FROM sub_std_map s
		// 					INNER JOIN class_teacher ct ON ct.standard_id = s.standard_id AND ct.sub_institute_id = s.sub_institute_id
		// 					WHERE s.sub_institute_id = '".$sub_institute_id."' AND s.standard_id = '".$standard_id."' AND ct.syear = '".$syear."' AND ct.teacher_id = '".$teacher_id."'
		// 					GROUP BY s.subject_id
		// 					ORDER BY s.display_name";					
		// $class_teacher_subjects_data = DB::select($class_teacher_sql);
		// $class_teacher_subjects_data = json_decode(json_encode($class_teacher_subjects_data),true);

		// $all_subjects = array_merge($subject_teacher_subjects_data,$class_teacher_subjects_data);

		return $subject_teacher_subjects_data;

    }

}
