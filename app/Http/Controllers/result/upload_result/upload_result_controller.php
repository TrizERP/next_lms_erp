<?php

namespace App\Http\Controllers\result\upload_result;

use App\Http\Controllers\Controller;
use App\Models\student\tblstudentModel;
use App\Models\result\upload_result\upload_result_model;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use GenTux\Jwt\JwtToken;
use GenTux\Jwt\GetsJwtToken;
use function App\Helpers\aut_token;

class upload_result_controller extends Controller {
	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */

	use GetsJwtToken;

	public function index(Request $request) 
	{
		$type = $request->input('type');
		$submit = $request->input('submit');
		$sub_institute_id = session()->get('sub_institute_id');
		$syear = session()->get('syear');
		$res['status'] = 1;
		$res['message'] = "Success";

		return is_mobile($type, "result/upload_result/show_upload_result", $res, "view");
	}

	/**
	 * Show the form for creating a new resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function create(Request $request) 
	{
        // dd($request);
		$type = $request->input('type');
		$sub_institute_id = session()->get('sub_institute_id');
		$syear = session()->get('syear');
		$grade = $request->input('grade');
		$standard = $request->input('standard');
		$division = $request->input('division');
		$term = $request->input('term');

		$extraSearchArray = array();
        $extraSearchArrayRaw = " 1=1 ";

        if($grade != '')
        {
            $extraSearchArray['tblstudent_enrollment.grade_id'] = $grade;
        }
    
        if($standard != '')
        {
            $extraSearchArray['tblstudent_enrollment.standard_id'] = $standard;
        }
        if($division != '')
        {
            $extraSearchArray['tblstudent_enrollment.section_id'] = $division;
        }

        $extraSearchArrayRaw .= "  AND tblstudent_enrollment.end_date IS NULL ";
        $extraSearchArray['tblstudent_enrollment.syear'] = $syear;
        $extraSearchArray['tblstudent.sub_institute_id'] = $sub_institute_id;
        $extraSearchArray['tblstudent_enrollment.sub_institute_id'] = $sub_institute_id;

        $studentData = tblstudentModel::selectRaw("tblstudent.id AS CHECKBOX,CONCAT_WS(' ',tblstudent.first_name,tblstudent.middle_name,tblstudent.last_name) AS student_name,academic_section.title as grade,standard.name as standard_name,division.name as division_name,tblstudent.enrollment_no,tblstudent.mobile,tblstudent.uniqueid,upload_result.file_name,academic_year.title as term_name")
        ->join('tblstudent_enrollment', 'tblstudent.id', '=', 'tblstudent_enrollment.student_id')
        ->leftjoin('upload_result', function ($join) use ($term) {
            $join->on('upload_result.student_id', '=', 'tblstudent.id')
                ->on('upload_result.grade_id','=','tblstudent_enrollment.grade_id')
                ->on('upload_result.standard_id','=','tblstudent_enrollment.standard_id')
                ->on('upload_result.term_id','=',DB::raw("'".$term."'"));
        })
        ->leftjoin('academic_year', function ($join) {
            $join->on('academic_year.term_id', '=', 'upload_result.term_id')
            ->on('academic_year.sub_institute_id','=','upload_result.sub_institute_id');
        })
        ->join('academic_section', 'academic_section.id', '=', 'tblstudent_enrollment.grade_id')
        ->join('standard', 'standard.id', '=', 'tblstudent_enrollment.standard_id')
        ->join('division', 'division.id', '=', 'tblstudent_enrollment.section_id')
        ->where($extraSearchArray)
        ->whereRaw($extraSearchArrayRaw)
        ->groupby('tblstudent.id')
        ->get()
        ->toArray();

		$res['status_code'] = 1;
		$res['message'] = "Success";
		$res['student_data'] = $studentData;
		$res['grade_id'] = $grade;
		$res['standard_id'] = $standard;
		$res['division_id'] = $division;
		$res['term_id'] = $term;

		return is_mobile($type, "result/upload_result/show_upload_result", $res, "view");
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @return \Illuminate\Http\Response
	 */
	public function store(Request $request) 
	{
		// dd($request);
		$sub_institute_id = $request->session()->get('sub_institute_id');
		$syear = $request->session()->get('syear');
		$type = $request->get('type');
		$students = $request->get('students');
		$grade_id = $request->get('grade_id');
		$standard_id = $request->get('standard_id');
		$division_id = $request->get('division_id');
		$term_id = $request->get('term_id');
		$created_on = now();
        $created_by = session()->get('user_id');
        $created_ip = $_SERVER['REMOTE_ADDR'];

        foreach ($students as $key => $student_id) 
		{
			$check_sql = DB::select("SELECT * FROM upload_result 
									WHERE student_id = '".$student_id."' AND  standard_id = '".$standard_id."' 
									AND grade_id = '".$grade_id."' AND term_id = '".$term_id."' 
									AND sub_institute_id = '".$sub_institute_id."' AND syear = '".$syear."' ");

			$check_data = json_decode(json_encode($check_sql),true);
			// dd($check_data);

			$file_name = "";
			if ($request->hasFile('image')) 
			{
				$random_no = rand(10000, 99999);
				$file = $request->file('image')[$student_id];
				$originalname = $file->getClientOriginalName();
				$name = "upload_result-" .date('YmdHis').'-'.$random_no;
				$ext = \File::extension($originalname);
				$file_name = $name . '.' . $ext;
				$path = $file->storeAs('public/upload_result/', $file_name);
			}

            if(count($check_data) == 0)
            {
				$insert_data = array(
					'syear' => $syear,
					'sub_institute_id' => $sub_institute_id,
					'term_id' => $term_id,
					'grade_id' => $grade_id,
					'standard_id' => $standard_id,
					'student_id' => $student_id,
					'file_name' => $file_name,
					'created_on' => $created_on,
					'created_by' => $created_by,
					'created_ip' => $created_ip
				);
	            upload_result_model::insert($insert_data);
	        }else{
	        	$update_data = array(
					'file_name' => $file_name,
					'created_on' => $created_on,
					'created_by' => $created_by,
					'created_ip' => $created_ip
				);
	            upload_result_model::where(['student_id' => $student_id,'grade_id' => $grade_id,'standard_id' => $standard_id,'term_id' => $term_id,'sub_institute_id' => $sub_institute_id,'syear' => $syear])->update($update_data);
	        }    
		}

		$res['status'] = "1";
        $res['message'] = "Result Uploaded Successfully";

        return is_mobile($type, "upload_result.index", $res, "redirect");
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

	public function uploadResultAPI(Request $request) {

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
			$data = DB::select("SELECT ur.id,ur.syear,ur.sub_institute_id,
								ur.student_id,ay.title as term_name,
								if(ur.file_name = '','',concat('https://".$_SERVER['SERVER_NAME']."/storage/upload_result/',ur.file_name)) as file_name
								FROM upload_result ur
								INNER JOIN academic_year ay ON ay.term_id = ur.term_id AND ay.sub_institute_id = ur.sub_institute_id
								WHERE ur.student_id = '".$student_id."' AND ur.sub_institute_id = '".$sub_institute_id."' AND ur.syear = '".$syear."'
								GROUP BY ur.term_id ");
	
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
