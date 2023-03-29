<?php

namespace App\Http\Controllers\school_setup;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use function App\Helpers\is_mobile;
use Illuminate\Support\Facades\DB;
use function App\Helpers\ValidateInsertData;
use GenTux\Jwt\JwtToken;
use GenTux\Jwt\GetsJwtToken;
use function App\Helpers\aut_token;


class subjectController extends Controller
{
	use GetsJwtToken;
	    
	public function hrmsAttendanceAPI(Request $request) {
	
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
		$syear = $request->input("syear");
		
		$client_data = DB::select("select *,if(db_hrms is null,0,1) as rights,
					if(db_library is null,0,1) as library_rights
					from school_setup s
					inner join tblclient c on c.id = s.client_id
					where s.Id = ".$sub_institute_id);

		$db_host = $client_data[0]->db_host;
		$db_user = $client_data[0]->db_user;
		$db_password = $client_data[0]->db_password;
		$hrms_db_hrms = $client_data[0]->db_hrms;
		$hrms_rights = $client_data[0]->rights;

		

		if($student_id != "" && $sub_institute_id != "" && $syear != "")
		{
			$stud_data = DB::select("SELECT * FROM tblstudent_enrollment 
			WHERE student_id = '".$student_id."' AND syear = '".$syear."' AND sub_institute_id = '".$sub_institute_id."'");
			if(count($stud_data) > 0)
			{			
				$standard_id = $stud_data[0]->standard_id;
				$section_id = $stud_data[0]->section_id;
				
				$data = DB::select("SELECT display_name AS subject_name,elective_subject,allow_grades
				FROM timetable t
				INNER JOIN sub_std_map s ON s.subject_id = t.subject_id
				WHERE t.syear = '".$syear."' AND t.sub_institute_id = '".$sub_institute_id."' 
				AND t.standard_id = '".$standard_id."' AND t.division_id = '".$section_id."'				
				GROUP BY t.subject_id
				ORDER BY display_name");
				
				$res['status_code'] = 1;
				$res['message'] = "Success";
				$res['data'] = $data;	
			}
			else{
				$res['status_code'] = 0;
				$res['message'] = "Wrong Parameters";
			}
		}else{
			$res['status_code'] = 0;
			$res['message'] = "Parameter Missing";
		}
		//return is_mobile($type, "implementation", $res);
		return json_encode($res);					
	}

}
