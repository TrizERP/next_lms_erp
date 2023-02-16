<?php

namespace App\Http\Controllers\consent;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;
use Illuminate\Support\Facades\DB;
use App\Models\consent\consent_masterModel;
use GenTux\Jwt\JwtToken;
use GenTux\Jwt\GetsJwtToken;
use function App\Helpers\aut_token;

class consent_masterController extends Controller
{
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

		return is_mobile($type, "consent/show_consent_master", $res, "view");
	}

	/**
	 * Show the form for creating a new resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function create(Request $request) {
		$grade = $request->input('grade');
		$standard = $request->input('standard');
		$division = $request->input('division');
		$type = $request->input('type');
		$sub_institute_id = $request->session()->get('sub_institute_id');

		$data = SearchStudent($grade, $standard, $division);

		// dd($data);

		$res['status_code'] = 1;
		$res['message'] = "Success";
		$res['student_data'] = $data;
		$res['grade_id'] = $grade;
		$res['standard_id'] = $standard;
		$res['division_id'] = $division;

		return is_mobile($type, "consent/show_consent_master", $res, "view");
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @return \Illuminate\Http\Response
	 */
	public function store(Request $request) {
		$students = $request->get('students');
		$type = $request->get('type');
		$title = $request->get('title');
		$date = $request->get('date');
		$accountable_status = $request->get('accountable_status');
		$division_id = $request->get('division_id');
		$standard_id = $request->get('standard_id');

		$sub_institute_id = $request->session()->get('sub_institute_id');
		// $user_group_id = $request->session()->get('user_profile_id');
		$syear = $request->session()->get('syear');
		$created_by = $request->session()->get('user_id');
		$created_on = date('Y-m-d');

		foreach ($students as $key => $student_id) {
			$addconsentArray = array();
			$addconsentArray['student_id'] = $student_id;
			$addconsentArray['sub_institute_id'] = $sub_institute_id;
			$addconsentArray['title'] = $title;
			$addconsentArray['standard_id'] = $standard_id;
			$addconsentArray['division_id'] = $division_id;
			$addconsentArray['date'] = $date;
			$addconsentArray['accountable_status'] = $accountable_status;
			$addconsentArray['syear'] = $syear;
			$addconsentArray['created_by'] = $created_by;
			$addconsentArray['created_on'] = $created_on;
			$addconsentArray['created_ip'] = $_SERVER['REMOTE_ADDR'];
			// $addconsentArray['user_group_id'] = $user_profile_id;
			// dd($addconsentArray);
			consent_masterModel::insert($addconsentArray);
		}

		$res['status_code'] = "1";
		$res['message'] = "Consent Added successfully";

		return is_mobile($type, "add_consent_master.index", $res);
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

	public function consentListAPI(Request $request) {

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
			$data = DB::select("select ID,title,date_format(date,'%d-%m-%Y') as consent_date,accountable_status,if(status = NULL,'Pending',status) as consent_status,amount,imprest_head_id,created_by 
				from consent_master
				where syear='".$syear."' AND student_id = '".$student_id."' AND sub_institute_id='".$sub_institute_id."'
				order by date desc");
	
			$res['status_code'] = 1;
			$res['message'] = "Success"; 
			$res['data'] = $data;	
		}else{
			$res['status_code'] = 0;
			$res['message'] = "Parameter Missing";
		}
		return json_encode($res);
	}	
}
