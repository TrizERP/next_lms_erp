<?php

namespace App\Http\Controllers\student;

use App\Http\Controllers\Controller;
use function App\Helpers\getCountDays;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use GenTux\Jwt\JwtToken;
use GenTux\Jwt\GetsJwtToken;
use function App\Helpers\aut_token;
use App\Models\student\tblstudentModel;

class studentAttendanceController extends Controller {
	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	use GetsJwtToken;
	 
	public function index(Request $request) {
		$type = $request->input('type');
		$submit = $request->input('submit');

		if ($type == "API") {
			$sub_institute_id = $request->input('sub_institute_id');
			$syear = $request->input('syear');
			$user_id = $request->input('user_id');

			$standardDiv = "SELECT ct.standard_id,ct.division_id,s.name as standard_name,d.name as division_name
        FROM class_teacher ct
        INNER JOIN standard s ON ct.standard_id = s.id AND ct.sub_institute_id = s.sub_institute_id
        INNER JOIN division d ON d.id = ct.division_id AND d.sub_institute_id = ct.sub_institute_id
        WHERE ct.sub_institute_id = '" . $sub_institute_id . "' AND syear = '" . $syear . "' AND ct.teacher_id = '" . $user_id . "'";

			$result = DB::select($standardDiv);

			$result = array_map(function ($value) {
				return (array) $value;
			}, $result);

			$res['standardDivision'] = $result;
		}

		$res['status_code'] = 1;
		$res['message'] = "Success";

		return is_mobile($type, "student/student_attendance", $res, "view");
	}

	public function showStudent(Request $request)
	{
		$type = $request->input('type');
		$date = $request->input('date');

		if ($type == "API") {
			$term_id = $request->input('term_id');
			$syear = $request->input('syear');
			$sub_institute_id = $request->input('sub_institute_id');
		} else {
			$term_id = $request->session()->get('term_id');
			$syear = $request->session()->get('syear');
			$sub_institute_id = $request->session()->get('sub_institute_id');
		}
		$standard_division_orignal = $request->input('standard_division');
		$standard_division = explode("||", $standard_division_orignal);
		$standard = $standard_division[0];
		$division = $standard_division[1];
		$grade = '';

		// $student_data = SearchStudent($grade, $standard, $division, $sub_institute_id, $syear);

		$extraSearchArray = array();
        $extraSearchArray['tblstudent.sub_institute_id'] = $sub_institute_id;
        $extraSearchArray['tblstudent_enrollment.syear'] = $syear;
        $extraSearchArray['tblstudent.status'] = 1;
        if($standard != ''){
            $extraSearchArray['tblstudent_enrollment.standard_id'] = $standard;
        }
        if($division != ''){
            $extraSearchArray['tblstudent_enrollment.section_id'] = $division;
        }

        $extraRaw = " 1 = 1 AND tblstudent_enrollment.end_date IS NULL ";
        //START Check for class teacher assigned standards
		
		$classTeacherStdArr = session()->get('classTeacherStdArr');
		if (isset($classTeacherStdArr))
		{
			if(count($classTeacherStdArr) > 0)
			{
				$extraRaw = "standard.id IN (".implode(",",$classTeacherStdArr).")";	
			}
			else{
				$extraRaw = "standard.id IN (' ')";	
			}
		}		
		
		$classTeacherDivArr = session()->get('classTeacherDivArr');
		if (isset($classTeacherStdArr))
		{
			if (count($classTeacherDivArr) > 0)
			{
				$extraRaw .= " and division.id IN (".implode(",",$classTeacherDivArr).")";				
			}
		}
		//END Check for class teacher assigned standards		


        $student_data = tblstudentModel::select('tblstudent_enrollment.*','tblstudent.*','standard.name as standard',
        										'division.name as division','academic_section.title as grade')
				        ->join("tblstudent_enrollment",function($join){
				            $join->on("tblstudent_enrollment.student_id","=","tblstudent.id")
				                ->on("tblstudent_enrollment.sub_institute_id","=","tblstudent.sub_institute_id")
				                ->whereNull('tblstudent_enrollment.end_date');
				        })
				        ->join("academic_section",function($join){
				            $join->on("academic_section.id","=","tblstudent_enrollment.grade_id")
				                ->on("academic_section.sub_institute_id","=","tblstudent_enrollment.sub_institute_id");
				        })
				        ->join("standard",function($join){
				            $join->on("standard.id","=","tblstudent_enrollment.standard_id")
				                ->on("standard.sub_institute_id","=","tblstudent_enrollment.sub_institute_id");
				        })
				        ->join("division",function($join){
				            $join->on("division.id","=","tblstudent_enrollment.section_id")
				                ->on("division.sub_institute_id","=","tblstudent_enrollment.sub_institute_id");
				        })
				        ->where($extraSearchArray)
				        ->whereRaw($extraRaw)
				        ->orderby('tblstudent.roll_no')
				        ->get()->toArray();
		// dd($student_data);		        
		if (count($student_data) == 0) {
			$res['status_code'] = 0;
			$res['message'] = "No Student Data Found";
			return is_mobile($type, "student_attendance.index", $res);
		}

		$attendanceArray = array();
		$attendanceArray['syear'] = $syear;
		$attendanceArray['sub_institute_id'] = $sub_institute_id;
		// $attendanceArray['term_id'] = $term_id;
		$attendanceArray['attendance_date'] = $date;
		$attendanceArray['standard_id'] = $standard;
		$attendanceArray['section_id'] = $division;

		$data = DB::table("attendance_student")->where($attendanceArray)->get()->toArray();
		$attendanceData = array();
		if (count($data) > 0) {
			foreach ($data as $key => $value) {
				$attendanceData[$value->student_id] = $value->attendance_code;
			}
		}

		$res['status_code'] = 1;
		$res['message'] = "Success";
		$res['student_data'] = $student_data;
		$res['date'] = $date;
		$res['standard_division'] = $standard_division_orignal;
		$res['attendance_data'] = $attendanceData;
		// dd($res);
		return is_mobile($type, "student/student_attendance", $res, "view");
	}

	public function saveStudentAttendance(Request $request) {
		$date = $request->input('date');
		$type = $request->input('type');
		$students = $request->input('student');

		if ($type != "API") {
			$syear = $request->session()->get('syear');
			$term_id = $request->session()->get('term_id');
			$user_id = $request->session()->get('user_id');
			$user_profile_id = $request->session()->get('user_profile_id');
			$sub_institute_id = $request->session()->get('sub_institute_id');
		} else {
			try {
				if (!$this->jwtToken()->validate()) {
					$response = array('status' => '2', 'message' => 'Token Auth Failed', 'data' => array());
					return response()->json($response, 401);
				}
			} catch (\Exception $e) {
				$response = array('status' => '2', 'message' => $e->getMessage(), 'data' => array());
				return response()->json($response, 401);
			}
			
			$syear = $request->input('syear');
			//$term_id = $request->input('term_id');
			$user_id = $request->input('teacher_id');
			$user_profile_id = $request->input('user_profile_id');
			$sub_institute_id = $request->input('sub_institute_id');
			if($syear == '' || $date == '' ||  $students == '' ||  $user_id == '' ||  $user_profile_id == '' ||  $sub_institute_id == '')
			{
				$res['status_code'] = 0;
				$res['message'] = "Parameter Missing.";				
				return is_mobile($type, "student_attendance.index", $res);
			}
		}

		$standard_division_orignal = $request->input('standard_division');
		$standard_division = explode("||", $standard_division_orignal);
		$standard = $standard_division[0];
		$division = $standard_division[1];

		foreach ($students as $student_id => $attendance) {
			$attendanceArray = array();
			$attendanceArray['syear'] = $syear;
			$attendanceArray['sub_institute_id'] = $sub_institute_id;
			$attendanceArray['student_id'] = $student_id;
			//$attendanceArray['term_id'] = $term_id;
			$attendanceArray['attendance_date'] = $date;
			$attendanceArray['standard_id'] = $standard;
			$attendanceArray['section_id'] = $division;

			$data = DB::table("attendance_student")->where($attendanceArray)->get()->toArray();

			$attendanceArray['attendance_code'] = $attendance;
			$attendanceArray['teacher_id'] = $user_id;
			$attendanceArray['user_group_id'] = $user_profile_id;
			$attendanceArray['created_by'] = $user_id;

			if (count($data) > 0) {
				DB::table("attendance_student")->where(['id' => $data[0]->id])->update($attendanceArray);
			} else {
				DB::table("attendance_student")->insert($attendanceArray);
			}
		}

		$res['status_code'] = 1;
		$res['message'] = "Attendance successfully taken";
		return is_mobile($type, "student_attendance.index", $res);
	}

	/*Old Save Student Attendance function
	public function saveStudentAttendance(Request $request) {
		$date = $request->input('date');
		$type = $request->input('type');
		$students = $request->input('student');

		if ($type != "API") {
			$syear = $request->session()->get('syear');
			$term_id = $request->session()->get('term_id');
			$user_id = $request->session()->get('user_id');
			$user_profile_id = $request->session()->get('user_profile_id');
			$sub_institute_id = $request->session()->get('sub_institute_id');
		} else {
			$syear = $request->input('syear');
			$term_id = $request->input('term_id');
			$user_id = $request->input('user_id');
			$user_profile_id = $request->input('user_profile_id');
			$sub_institute_id = $request->input('sub_institute_id');
		}

		$standard_division_orignal = $request->input('standard_division');
		$standard_division = explode("||", $standard_division_orignal);
		$standard = $standard_division[0];
		$division = $standard_division[1];

		foreach ($students as $student_id => $attendance) {
			$attendanceArray = array();
			$attendanceArray['syear'] = $syear;
			$attendanceArray['sub_institute_id'] = $sub_institute_id;
			$attendanceArray['student_id'] = $student_id;
			$attendanceArray['term_id'] = $term_id;
			$attendanceArray['attendance_date'] = $date;
			$attendanceArray['standard_id'] = $standard;
			$attendanceArray['section_id'] = $division;

			$data = DB::table("attendance_student")->where($attendanceArray)->get()->toArray();

			$attendanceArray['attendance_code'] = $attendance;
			$attendanceArray['teacher_id'] = $user_id;
			$attendanceArray['user_group_id'] = $user_profile_id;
			$attendanceArray['created_by'] = $user_id;

			if (count($data) > 0) {
				DB::table("attendance_student")->where(['id' => $data[0]->id])->update($attendanceArray);
			} else {
				DB::table("attendance_student")->insert($attendanceArray);
			}

		}

		$res['status_code'] = 1;
		$res['message'] = "Attendance successfully taken";
		return is_mobile($type, "student_attendance.index", $res);
	}*/
	
	public function daywiseStudentAttendance(Request $request) {
		$type = $request->input('type');
		$submit = $request->input('submit');

		$res['status_code'] = 1;
		$res['message'] = "Success";

		return is_mobile($type, "student/daywise_attendance_report", $res, "view");
	}

	public function showDaywiseStudentAttendance(Request $request) {
		$type = $request->input('type');
		$date = $request->input('date');
		$taken = $request->input('taken');
		$syear = $request->session()->get('syear');
		$term_id = $request->session()->get('term_id');
		$sub_institute_id = $request->session()->get('sub_institute_id');

		$query = "SELECT CONCAT_WS('/',sm.name,dm.name) AS standard_name, dm.name AS division_name,se.standard_id,se.section_id,se.student_id,a.attendance_code,
		s.gender, SUM(CASE WHEN s.gender = 'M' THEN 1 ELSE 0 END) AS BOY, SUM(CASE WHEN s.gender = 'F' THEN 1 ELSE 0 END) AS GIRL, SUM(CASE WHEN s.gender = 'M' AND a.attendance_code = 'P' THEN 1 ELSE 0 END) TBP, SUM(CASE WHEN s.gender = 'F' AND a.attendance_code = 'P' THEN 1 ELSE 0 END) TGP, SUM(CASE WHEN s.gender = 'M' AND a.attendance_code = 'A' THEN 1 ELSE 0 END) TBA, SUM(CASE WHEN s.gender = 'F' AND a.attendance_code = 'A' THEN 1 ELSE 0 END) TGA
		FROM tblstudent s
		INNER JOIN tblstudent_enrollment se ON s.id = se.student_id AND se.syear = '" . $syear . "' AND s.sub_institute_id = se.sub_institute_id AND se.end_date IS NULL
		INNER JOIN standard sm ON se.standard_id = sm.id
		INNER JOIN division dm ON se.section_id = dm.id
		LEFT JOIN attendance_student a ON s.id = a.student_id AND sm.id = a.standard_id AND dm.id = a.section_id AND s.sub_institute_id = a.sub_institute_id AND a.attendance_date = '" . $date . "' and a.syear = '" . $syear . "'
		WHERE s.sub_institute_id = '" . $sub_institute_id . "'
		GROUP BY se.standard_id,se.section_id";

		if ($taken == 'no') {
			$query .= "  HAVING attendance_code IS NULL";
		} else {
			$query .= "  HAVING attendance_code IS NOT NULL";
		}
		$query .= " ORDER BY sm.sort_order ASC";

		$data = DB::select($query);

		$res['status_code'] = 1;
		$res['message'] = "Success";
		$res['date'] = $date;
		$res['taken'] = $taken;
		$res['attendance_data'] = $data;

		return is_mobile($type, "student/daywise_attendance_report", $res, "view");
	}

	public function monthwiseStudentAttendance(Request $request) {
		$type = $request->input('type');

		$res['status_code'] = 1;
		$res['message'] = "Success";
		return is_mobile($type, "student/monthwise_attendance_report", $res, "view");
	}

	public function showMonthwiseStudentAttendance(Request $request) {
		$type = $request->input('type');
		$month = $request->input('month');
		$grade_id = $request->input("grade");
		$standard_id = $request->input("standard");
		$division_id = $request->input("division");
		$syear = $request->session()->get('syear');
		$term_id = $request->session()->get('term_id');
		$sub_institute_id = $request->session()->get('sub_institute_id');

		$student_data = SearchStudent($grade_id, $standard_id, $division_id);
		$from_date = $syear . "-" . $month . "-01";
		$to_date = date('Y-m-t', strtotime($syear . "-" . $month));

		$sundays = getCountDays($from_date, $to_date);

		$whereAtt['syear'] = $syear;
		$whereAtt['sub_institute_id'] = $sub_institute_id;

		$holidays = DB::table("calendar_events")
			->selectRaw("DATE_FORMAT(school_date,'%d') AS DATE")
			->where($whereAtt)
			->whereRaw("month(school_date) = " . $month)
			->pluck('DATE')
			->toArray();

		// $whereAtt['term_id'] = $term_id;
		$whereAtt['standard_id'] = $standard_id;
		$whereAtt['section_id'] = $division_id;

		$attendanceData = DB::table("attendance_student")
			->where($whereAtt)
			->whereRaw("month(attendance_date) = " . $month)
			->get()
			->toArray();

		if (count($attendanceData) == 0) {
			$res['status_code'] = 0;
			$res['message'] = "No attendance taken in this month";
			return is_mobile($type, "monthwise_student_attendance_report", $res);
		}

		$finalAttendanceArray = array();
		foreach ($attendanceData as $key => $value) {
			$finalAttendanceArray[$value->student_id][(int) date('d', strtotime($value->attendance_date))] = $value->attendance_code;
		}

		foreach ($sundays['S'] as $key => $value) {
			$sundays[$key] = (int) date('d', strtotime($value));
		}

		unset($sundays['S']);

		$res['status_code'] = 1;
		$res['message'] = "Success";
		$res['month'] = $month;
		$res['grade_id'] = $grade_id;
		$res['standard_id'] = $standard_id;
		$res['division_id'] = $division_id;
		$res['student_data'] = $student_data;
		$res['attendance_data'] = $finalAttendanceArray;
		$res['sundays'] = $sundays;
		$res['holidays'] = $holidays;
		$res['to_date'] = date('d', strtotime($to_date));

		return is_mobile($type, "student/monthwise_attendance_report", $res, "view");
	}
	
	public function studentAttendanceAPI(Request $request) {
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

		$attendace_data = $event_data = $holiday_data = $vacation_data = array();
		if($student_id != "" && $sub_institute_id != "" && $syear != "")
		{
			$attendance_data = DB::select("SELECT attendance_date,attendance_code  
			FROM attendance_student 
			WHERE sub_institute_id = '".$sub_institute_id."'
			AND student_id = '".$student_id."' AND syear = '".$syear."'");
			
			$holiday_data = DB::select("SELECT c.school_date,c.title,c.description FROM tblstudent_enrollment s
			LEFT JOIN calendar_events c ON find_in_set (s.standard_id,c.standard)
			WHERE s.student_id = '".$student_id."' AND s.syear = '".$syear."' AND s.sub_institute_id = '".$sub_institute_id."' 	
			AND event_type = 'holiday'
			ORDER BY school_date");			
			
			$event_data = DB::select("SELECT c.school_date,c.title,c.description FROM tblstudent_enrollment s
			LEFT JOIN calendar_events c ON find_in_set (s.standard_id,c.standard)
			WHERE s.student_id = '".$student_id."' AND s.syear = '".$syear."' AND s.sub_institute_id = '".$sub_institute_id."' 	
			AND event_type = 'event'
			ORDER BY school_date");			
			
			$vacation_data = DB::select("SELECT c.school_date,c.title,c.description FROM tblstudent_enrollment s
			LEFT JOIN calendar_events c ON find_in_set (s.standard_id,c.standard)
			WHERE s.student_id = '".$student_id."' AND s.syear = '".$syear."' AND s.sub_institute_id = '".$sub_institute_id."' 	
			AND event_type = 'vaction'
			ORDER BY school_date");			
			
			$res['status'] = 1;
			$res['message'] = "Success";
			$res['data']['attendance_data'] = $attendance_data;	
			$res['data']['calendar_data']['holiday'] = $holiday_data;	
			$res['data']['calendar_data']['event'] = $event_data;	
			$res['data']['calendar_data']['vacation'] = $vacation_data;	
		}else{
			$res['status'] = 0;
			$res['message'] = "Parameter Missing";
		}
		//return is_mobile($type, "implementation", $res);	
		return json_encode($res);
	}
	
	public function studentTeacherListAPI(Request $request) {
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
			$stud_data = DB::select("SELECT * FROM tblstudent_enrollment 
			WHERE student_id = '".$student_id."' AND syear = '".$syear."' AND sub_institute_id = '".$sub_institute_id."'");
			if(count($stud_data) > 0)
			{			
				$standard_id = $stud_data[0]->standard_id;
				$section_id = $stud_data[0]->section_id;
				
				$data = DB::select("SELECT CONCAT_WS(' ',u.first_name,u.middle_name,u.last_name) AS teacher_name,if(u.image = '','',concat('https://".$_SERVER['SERVER_NAME']."/storage/user/',u.image)) as image,u.mobile,
				s.display_name as subject_name
				FROM timetable t
				INNER JOIN tbluser u ON u.id = t.teacher_id
				INNER JOIN sub_std_map s ON s.subject_id = t.subject_id
				WHERE t.syear = '".$syear."' AND t.sub_institute_id = '".$sub_institute_id."' 
				AND t.standard_id = '".$standard_id."' AND t.division_id = '".$section_id."'
				GROUP BY t.teacher_id
				ORDER BY teacher_name");
				
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
	
	public function studentAbsentListAPI(Request $request) {
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
		$teacher_id = $request->input("teacher_id");
		$sub_institute_id = $request->input("sub_institute_id");
		$standard_division = $request->input("standard_division");
		$start_date = $request->input("start_date");
		$end_date = $request->input("end_date");
		$syear = $request->input("syear");
		
		if($teacher_id != "" && $sub_institute_id != "" && $syear != "" && $standard_division != "" && $start_date != "" && $end_date != "")
		{
			$standard_division_orignal = $request->input('standard_division');
			$standard_division = explode("||", $standard_division_orignal);
			$standard = $standard_division[0];
			$division = $standard_division[1];

			$data = DB::select("SELECT attendance_code,attendance_date,
			CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) as student_name
			FROM attendance_student a
			INNER JOIN tblstudent s on s.id = a.student_id and s.sub_institute_id = a.sub_institute_id
			WHERE standard_id =  '".$standard."' and section_id = '".$division."'
			AND attendance_code = 'A' and teacher_id = '".$teacher_id."' and a.sub_institute_id = '".$sub_institute_id."'
			AND attendance_date BETWEEN '".$start_date."' and '".$end_date."'			
			");			
			$res['status'] = 1;
			$res['message'] = "Success";
			$res['data'] = $data;	
		}else{
			$res['status'] = 0;
			$res['message'] = "Parameter Missing";
		}
		
		return json_encode($res);
	}
}
