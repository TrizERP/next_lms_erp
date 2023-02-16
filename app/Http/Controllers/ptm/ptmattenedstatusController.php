<?php

namespace App\Http\Controllers\ptm;

use App\Http\Controllers\Controller;
use App\Models\ptm\ptmtimeslotmasterModel;
use App\Models\ptm\ptmattenedstatusModel;
use App\Models\school_setup\standardModel;
use App\Models\user\tbluserModel;
use function App\Helpers\is_mobile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use GenTux\Jwt\JwtToken;
use GenTux\Jwt\GetsJwtToken;
use function App\Helpers\aut_token;


class ptmattenedstatusController extends Controller {
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

		$standard = standardModel::select('id', 'name')->where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

		$user_data = tbluserModel::select('tbluser.*',
        DB::raw('concat(tbluser.first_name," ",tbluser.middle_name," ",tbluser.last_name) as teacher_name'))
        ->join('tbluserprofilemaster','tbluserprofilemaster.id' ,"=", 'tbluser.user_profile_id')        
        ->where(['tbluser.sub_institute_id'=>$sub_institute_id,'tbluserprofilemaster.name' => 'Teacher'])
        ->get();                     

		$res['standards'] = $standard;
		$res['users'] = $user_data;

		return is_mobile($type, "ptm/show_ptm_attened_status", $res, "view");
	}

	/**
	 * Show the form for creating a new resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function create(Request $request) {
		$standard = $request->input('standard_id');
		$date = $request->input('date');
		$teacher_id = $request->get('teacher_id');
		$type = $request->input('type');
		$sub_institute_id = $request->session()->get('sub_institute_id');
		$syear = $request->session()->get('syear');

		$sql = "SELECT PBM.ID AS CHECKBOX,PBM.ID,PBM.STUDENT_ID,CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) AS STUDENT,
CONCAT_WS(' - ',cs.name,ss.name) AS std_div,s.mobile,CONCAT_WS(' ',ts.first_name,ts.middle_name,ts.last_name) AS TEACHER,
PTS.title, DATE_FORMAT(PTS.ptm_date,'%d-%m-%Y') AS PTM_DATE, 
CONCAT_WS('-',PTS.from_time,PTS.to_time) AS TIME_SLOT,PBM.PTM_ATTENDED_STATUS,PBM.PTM_ATTENDED_REMARKS
FROM ptm_booking_master PBM
INNER JOIN ptm_time_slots_master PTS ON PTS.id = PBM.TIME_SLOT_ID
INNER JOIN tblstudent s ON (s.id = PBM.STUDENT_ID AND s.sub_institute_id = PBM.SUB_INSTITUTE_ID)
INNER JOIN tblstudent_enrollment se ON se.student_id = s.id AND se.syear = '".$syear."'
INNER JOIN standard cs ON cs.id = se.standard_id
INNER JOIN division ss ON ss.id = se.section_id
INNER JOIN tbluser ts ON ts.id = PBM.TEACHER_ID
INNER JOIN tbluserprofilemaster tup ON tup.id = ts.user_profile_id AND tup.name = 'Teacher' AND ts.sub_institute_id = '".$sub_institute_id."'
WHERE 1=1 AND DATE_FORMAT(PTS.ptm_date,'%Y-%m-%d') = '".$date."' AND PBM.sub_institute_id = '".$sub_institute_id."' ";

		if ($teacher_id != '') {
			$sql .= "  AND PBM.TEACHER_ID = '" . $teacher_id . "' ";
		}

		if ($standard != '') {
			$sql .= "  AND PTS.standard_id = '" . $standard . "'";
		}
// dd($sql);
		$result = DB::select($sql);

		$standards = standardModel::select('id', 'name')->where(['sub_institute_id' => $sub_institute_id])->get()->toArray();


		$res['status_code'] = 1;
		$res['message'] = "Success";
		$res['student_data'] = $result;
		$res['standards'] = $standards;
		$res['standard_id'] = $standard;
		$res['date'] = $date;

		return is_mobile($type, "ptm/show_ptm_attened_status", $res, "view");
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @return \Illuminate\Http\Response
	 */
	public function store(Request $request) {
		// dd($request);
		$students = $request->get('students');
		$type = $request->get('type');
		$date = $request->get('date');
		$standard_id = $request->get('standard_id');
        $attened_remarks = $request->input('attened_remarks');
        $attened_status = $request->input('attened_status');

		$sub_institute_id = $request->session()->get('sub_institute_id');
		$syear = $request->session()->get('syear');
		$created_by = $request->session()->get('user_id');
		$created_ip = $_SERVER['REMOTE_ADDR'];

		foreach ($students as $key => $student_id) 
		{
			$PTMArray = array();

			$PTMArray['PTM_ATTENDED_REMARKS'] = $attened_remarks[$student_id];
			$PTMArray['PTM_ATTENDED_STATUS'] = $attened_status[$student_id];
			$PTMArray['PTM_ATTENDED_BY'] = $created_by;
			$PTMArray['PTM_ATTENDED_ENTRY_DATE'] = date('Y-m-d');
			$PTMArray['PTM_ATTENDED_CREATED_IP'] = $created_ip;

            ptmattenedstatusModel::where(["ID" => $student_id,'SUB_INSTITUTE_ID' => $sub_institute_id])->update($PTMArray);
		}

		$res['status_code'] = "1";
		$res['message'] = "Record Updated Successfully";

		return is_mobile($type, "add_ptm_attened_status.index", $res);
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

	public function ptmBookAPI(Request $request) {

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
		$booking_id = $request->input("booking_id");
		$booking_status = $request->input("booking_status");
		$teacher_id = $request->input("teacher_id");
		$date = $request->input("date");

		if($student_id != "" && $sub_institute_id != "" && $syear != "" && $booking_id != "" && $teacher_id != "")
		{
			$data[] = array(
                'CONFIRM_STATUS' => 'Confirm',
                'TEACHER_ID' => $teacher_id,
                'STUDENT_ID' => $student_id,
                'TIME_SLOT_ID' => $booking_id,
                'DATE' => $date,
                'SUB_INSTITUTE_ID' => $sub_institute_id,            
                'CREATED_ON' => date('Y-m-d')
            );
			ptmattenedstatusModel::insert($data);
	
			$res['status_code'] = 1;
			$res['message'] = "Success"; 
			// $res['data'] = $data;	
		}else{
			$res['status_code'] = 0;
			$res['message'] = "Parameter Missing";
		}
		return json_encode($res);
		// return is_mobile($type, "implementation", $res);	
	}

	public function ptmBookingStatusAPI(Request $request) {

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
		$time_slot_id = $request->input("time_slot_id");
		

		if($student_id != "" && $sub_institute_id != "" && $syear != "" && $time_slot_id != "")
		{
			$data = DB::select("SELECT pb.ID,pb.DATE,pb.TEACHER_ID,pb.TIME_SLOT_ID,pb.CONFIRM_STATUS,pb.STUDENT_ID,pb.CREATED_ON,
					pb.SUB_INSTITUTE_ID,pb.PTM_ATTENDED_STATUS,pb.PTM_ATTENDED_REMARKS,pb.PTM_ATTENDED_ENTRY_DATE,
					ps.from_time as FROM_TIME,ps.to_time as TO_TIME,ps.ptm_date as PTM_DATE
			FROM ptm_booking_master pb
			INNER JOIN ptm_time_slots_master ps ON ps.id= pb.TIME_SLOT_ID
			INNER JOIN standard cs ON cs.id = ps.standard_id
			INNER JOIN tblstudent_enrollment se ON se.standard_id = cs.id
			INNER JOIN tblstudent s ON s.id = se.student_id AND se.syear='".$syear."'
			WHERE s.id='".$student_id."' AND ps.id='".$time_slot_id."' AND ps.sub_institute_id = '".$sub_institute_id."'
			GROUP BY pb.ID");
	
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

	public function ptmBookingTimeAPI(Request $request) {

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
		$date = $request->input("date");
		

		if($student_id != "" && $sub_institute_id != "" && $syear != "" && $date != "")
		{
			$data = DB::select("SELECT ps.id,ps.syear,ps.sub_institute_id,ps.ptm_date,ps.standard_id,ps.title, DATE_FORMAT(ps.from_time,'%h:%i') AS from_time, DATE_FORMAT(ps.to_time,'%h:%i') AS to_time,
						ps.created_by,ps.created_on, IFNULL(pt.CONFIRM_STATUS,'Pending') AS CONFIRM_STATUS,
						pt.ID AS booking_id,pt.STUDENT_ID
				FROM ptm_time_slots_master ps
				INNER JOIN standard cs ON cs.id = ps.standard_id
				INNER JOIN tblstudent_enrollment se ON se.standard_id = cs.id
				INNER JOIN tblstudent s ON s.id = se.student_id AND se.syear='".$syear."'
				LEFT JOIN ptm_booking_master pt ON pt.TIME_SLOT_ID= ps.id AND s.id=pt.STUDENT_ID
				WHERE s.id='".$student_id."' and ps.ptm_date = '".$date."' 
				order by ps.id");
	
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

	public function ptmTeacherListAPI(Request $request) {

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
			$data = DB::select("SELECT s.id, CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) AS teacher, s.sub_institute_id
					FROM tblstudent_enrollment se
					INNER JOIN timetable cp ON cp.standard_id = se.standard_id
					INNER JOIN tbluser s ON s.id= cp.teacher_id AND s.sub_institute_id = '".$sub_institute_id."'
					INNER JOIN tbluserprofilemaster tup ON tup.id = s.user_profile_id AND tup.name = 'Teacher'
					WHERE se.student_id = '".$student_id."' AND se.syear='".$syear."'
					GROUP BY cp.teacher_id");
	
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
