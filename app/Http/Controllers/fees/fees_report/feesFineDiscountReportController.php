<?php

namespace App\Http\Controllers\fees\fees_report;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;
use App\Models\fees\map_year\map_year;
use Illuminate\Support\Facades\DB;


class feesFineDiscountReportController extends Controller {
	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function index(Request $request) {
		//
	}

	/**
	 * Show the form for creating a new resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function create(Request $request) {
		//
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @return \Illuminate\Http\Response
	 */
	public function store(Request $request) {
		// dd($request);
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

	public function feesFineDiscountReportIndex(Request $request) {
		$type = $request->input('type');
		$submit = $request->input('submit');
		$sub_institute_id = $request->session()->get('sub_institute_id');
		$grade = $request->input('grade');
        $standard = $request->input('standard');
        $division = $request->input('division');
		$from_date = $request->input('from_date');
        $to_date = $request->input('to_date');

		$res['status_code'] = 1;
		$res['message'] = "Success";
		$res['grade_id'] = $grade;
        $res['standard_id'] = $standard;
        $res['division_id'] = $division;
		$res['from_date'] = $from_date;
        $res['to_date'] = $to_date;

		return is_mobile($type, "fees/fees_report/show_fees_fine_discount_report", $res, "view");
	}

	public function feesFineDiscountReport(Request $request) {
		$type = $request->input('type');
		$sub_institute_id = $request->session()->get('sub_institute_id');
		$syear = $request->session()->get('syear');
		$grade = $request->input('grade');
		$standard = $request->input('standard');
		$division = $request->input('division');
		$from_date = $request->input('from_date');
		$to_date = $request->input('to_date');

		$query = "SELECT '' AS SR_NO, CONCAT_WS(' ',S.first_name,S.middle_name,S.last_name) AS STUDENT_NAME, 
SUM(FP.fees_discount) AS FEES_MAFI,SUM(FP.fine) AS FINE, CS.name as std, S.enrollment_no, SS.name as div_name, 
CASE WHEN S.gender = 'M' THEN 'MALE' WHEN S.gender = 'F' THEN 'FEMALE' END AS GENDER, 
IFNULL(FP.remarks,'-') AS COMMENT, FP.receipt_no, 
DATE_FORMAT(FP.receiptdate,'%d-%m-%Y') AS RECEIVED_DATE
FROM tblstudent S
INNER JOIN tblstudent_enrollment SE ON S.id = SE.student_id AND SE.SYEAR='".$syear."' 
INNER JOIN standard CS ON SE.standard_id = CS.id
INNER JOIN division SS ON SE.section_id = SS.id
INNER JOIN fees_collect FP ON SE.student_id = FP.student_id
WHERE SE.SYEAR='".$syear."' AND FP.SYEAR='".$syear."' AND FP.IS_DELETED='N' and SE.sub_institute_id = '".$sub_institute_id."'
AND FP.receiptdate BETWEEN '".$from_date."' AND '".$to_date."' AND (FP.fees_discount > 0 OR FP.fine > 0)
GROUP BY SE.STUDENT_ID,FP.receiptdate,FP.receipt_no
ORDER BY FP.receiptdate";

		if ($standard != '') {
			$query .= " AND SE.standard_id = '" . $standard . "'";
		}

		if ($division != '') {
			$query .= " AND SE.section_id = '" . $division . "'";
		}

		if ($grade != '') {
			$query .= " AND SE.grade_id = '" . $grade . "'";
		}

		 // dd($query);
		$result = DB::select($query);

		$result = array_map(function ($value) {
			return (array) $value;
		}, $result);

		$res['status_code'] = 1;
		$res['message'] = "Success";
		$res['report_data'] = $result;
		$res['grade_id'] = $grade;
		$res['standard_id'] = $standard;
		$res['division_id'] = $division;
		$res['from_date'] = $from_date;
		$res['to_date'] = $to_date;

		return is_mobile($type, "fees/fees_report/show_fees_fine_discount_report", $res, "view");
	}
}
