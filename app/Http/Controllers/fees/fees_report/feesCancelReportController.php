<?php

namespace App\Http\Controllers\fees\fees_report;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;
use App\Models\fees\map_year\map_year;
use Illuminate\Support\Facades\DB;


class feesCancelReportController extends Controller {
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

	public function feesCancelReportIndex(Request $request) {
		$type = $request->input('type');
		$submit = $request->input('submit');
		$sub_institute_id = $request->session()->get('sub_institute_id');
		$grade = $request->input('grade');
        $standard = $request->input('standard');
        $division = $request->input('division');
		$from_date = $request->input('from_date');
        $to_date = $request->input('to_date');

		$feesCancelType = DB::table('fees_cancel_type')->pluck('title','id');

		$res['status_code'] = 1;
		$res['message'] = "Success";
		$res['grade_id'] = $grade;
        $res['standard_id'] = $standard;
        $res['division_id'] = $division;
		$res['from_date'] = $from_date;
        $res['to_date'] = $to_date;
        $res['fees_cancel_type'] = $feesCancelType;

		return is_mobile($type, "fees/fees_report/show_fees_cancel_report", $res, "view");
	}

	public function feesCancelReport(Request $request) {
		$type = $request->input('type');
		$sub_institute_id = $request->session()->get('sub_institute_id');
		$syear = $request->session()->get('syear');
		$grade = $request->input('grade');
		$standard = $request->input('standard');
		$division = $request->input('division');
		$from_date = $request->input('from_date');
		$to_date = $request->input('to_date');
		$cancel_type = $request->input('cancel_type');

		$feesCancelType = DB::table('fees_cancel_type')->pluck('title','id');

		$query = "SELECT fc.id,fc.reciept_id,ts.enrollment_no, CONCAT_WS(' ',ts.first_name,ts.middle_name,ts.last_name) AS student_name,ts.admission_year,
te.student_quota,s.name as std_name,fc.amountpaid,fc.cancel_type,fc.cancel_remark, DATE_FORMAT(fc.cancel_date,'%d-%m-%Y') AS cancel_date, 
CONCAT_WS(' ',u.first_name,u.middle_name,u.last_name) AS cancelled_by,sq.title as student_quota_name
FROM fees_cancel fc
INNER JOIN tblstudent ts ON ts.id = fc.student_id AND ts.sub_institute_id = fc.sub_institute_id
INNER JOIN tblstudent_enrollment te ON te.student_id = ts.id AND te.syear = fc.syear
INNER JOIN student_quota sq ON sq.id = te.student_quota AND ts.sub_institute_id = sq.sub_institute_id
INNER JOIN standard s ON s.id = te.standard_id
INNER JOIN tbluser u ON u.id = fc.cancelled_by
WHERE te.syear = '".$syear."' AND fc.sub_institute_id = '".$sub_institute_id."' AND fc.syear = '".$syear."' AND fc.cancel_type = '".$cancel_type."' AND date_format(fc.cancel_date,'%Y-%m-%d') BETWEEN '".$from_date."' AND '".$to_date."' ";

		if ($standard != '') {
			$query .= " AND fc.standard_id = '" . $standard . "'";
		}

		if ($division != '') {
			$query .= " AND te.section_id = '" . $division . "'";
		}

		if ($grade != '') {
			$query .= " AND te.grade_id = '" . $grade . "'";
		}

		 // dd($query);
		$result = DB::select($query);

		$result = array_map(function ($value) {
			return (array) $value;
		}, $result);

		$res['status_code'] = 1;
		$res['message'] = "Success";
		$res['report_data'] = $result;
		$res['fees_cancel_type'] = $feesCancelType;
		$res['grade_id'] = $grade;
		$res['standard_id'] = $standard;
		$res['division_id'] = $division;
		$res['from_date'] = $from_date;
		$res['to_date'] = $to_date;
		$res['cancel_type'] = $cancel_type;

		return is_mobile($type, "fees/fees_report/show_fees_cancel_report", $res, "view");
	}
}
