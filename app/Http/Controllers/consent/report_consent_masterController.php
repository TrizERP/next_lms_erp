<?php

namespace App\Http\Controllers\consent;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;
use Illuminate\Support\Facades\DB;
use App\Models\consent\consent_masterModel;

class report_consent_masterController extends Controller
{
    /**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function index(Request $request) {
		$type = $request->input('type');
		$submit = $request->input('submit');
		$sub_institute_id = $request->session()->get('sub_institute_id');
		$res['status_code'] = 1;
		$res['message'] = "Success";

		return is_mobile($type, "consent/report_consent_master", $res, "view");
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
		$from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
		$type = $request->input('type');
		$sub_institute_id = $request->session()->get('sub_institute_id');
		$syear = $request->session()->get('syear');
		$extra_query = '';


		if($from_date != '' && $to_date != '')
        {
            $extra_query .= " AND (DATE_FORMAT(CM.date,'%Y-%m-%d') BETWEEN '".$from_date."' AND '".$to_date."') ";
        }

		// $data = SearchStudent($grade, $standard, $division);
		$sql = "SELECT CM.ID AS CHECKBOX,CM.*,s.enrollment_no, CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) AS FULL_NAME,s.mobile AS SMS_NO, 
		SG.title AS GRADE_ID, CONCAT_WS('/',CS.name,SS.name) AS STANDARD, CONCAT_WS(' ',ta.first_name,ta.last_name) AS created_by, 
		DATE_FORMAT(CM.date,'%d-%m-%Y') AS consent_date, IF(CM.accountable_status = 'Accountable','Acoount','Not Account') AS account_status
		FROM consent_master CM
		INNER JOIN tblstudent s ON s.id = CM.student_id AND s.sub_institute_id = CM.sub_institute_id
		INNER JOIN tblstudent_enrollment SE ON SE.student_id = s.id AND SE.syear = '".$syear."'
		INNER JOIN standard CS ON CS.id = SE.standard_id
		INNER JOIN academic_section SG ON SG.id = CS.grade_id AND SG.sub_institute_id = '".$sub_institute_id."'
		INNER JOIN division SS ON SS.id = SE.section_id
		INNER JOIN tbluser ta ON ta.id = CM.created_by
		WHERE CM.syear = '".$syear."' AND CM.sub_institute_id = '".$sub_institute_id."' ";
		// dd($data);

		if ($standard != '') {
			$sql .= "  AND CM.standard_id = '" . $standard . "'";
		}

		if ($division != '') {
			$sql .= "  AND CM.division_id = '" . $division . "'";
		}

		$result = DB::select($sql.$extra_query);

		$res['status_code'] = 1;
		$res['message'] = "Success";
		$res['student_data'] = $result;
		$res['grade_id'] = $grade;
		$res['standard_id'] = $standard;
		$res['division_id'] = $division;
		$res['from_date'] = $from_date;
        $res['to_date'] = $to_date;

		return is_mobile($type, "consent/report_consent_master", $res, "view");
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @return \Illuminate\Http\Response
	 */
	public function store(Request $request) {
		// 
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
}
