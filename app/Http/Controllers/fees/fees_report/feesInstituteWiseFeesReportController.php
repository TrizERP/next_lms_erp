<?php

namespace App\Http\Controllers\fees\fees_report;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;
use App\Models\fees\map_year\map_year;
use Illuminate\Support\Facades\DB;


class feesInstituteWiseFeesReportController extends Controller {
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

	public function instituteWiseFeesPaidReportIndex(Request $request) {
		$type = $request->input('type');
		$submit = $request->input('submit');
		$sub_institute_id = $request->session()->get('sub_institute_id');
		$from_date = $request->input('from_date');
        $to_date = $request->input('to_date');

		$res['status_code'] = 1;
		$res['message'] = "Success";
		$res['from_date'] = $from_date;
        $res['to_date'] = $to_date;

		return is_mobile($type, "fees/fees_report/show_institute_wise_fees_paid_report", $res, "view");
	}

	public function instituteWiseFeesPaidReport(Request $request) {
		$type = $request->input('type');
		$sub_institute_id = $request->session()->get('sub_institute_id');
		$syear = $request->session()->get('syear');
		$from_date = $request->input('from_date');
		$to_date = $request->input('to_date');

		$total_stu_query = "SELECT ss.Id as sub_institute_id,ss.SchoolName,ss.ShortCode,ss.Mobile,ss.Email,COUNT(DISTINCT ts.id) AS TOTAL_STUDENT
FROM tblclient c
INNER JOIN school_setup ss ON ss.client_id = c.id
INNER JOIN tblstudent ts on ts.sub_institute_id = ss.Id
INNER JOIN tblstudent_enrollment te on te.student_id = ts.id
where ss.Id = '".$sub_institute_id."' AND te.syear = '".$syear."'";

		$result = DB::select($total_stu_query);

		$result = json_decode(json_encode($result),true);

		$total_fees_query = "SELECT COUNT(DISTINCT fc.student_id) AS TOOTAL_PAID,SUM(fc.amount) as Total_Fees_Collected 
FROM fees_collect fc 
where fc.sub_institute_id = '".$sub_institute_id."' AND fc.syear = '".$syear."' AND date_format(fc.created_date,'%Y-%m-%d') between '".$from_date."' AND '".$to_date."'";

		$fees_result = DB::select($total_fees_query);

		$fees_result = json_decode(json_encode($fees_result),true);

		$all_result[0] = array_merge($result[0],$fees_result[0]);

		// dd($all_result);

		$res['status_code'] = 1;
		$res['message'] = "Success";
		$res['report_data'] = $all_result;
		$res['from_date'] = $from_date;
		$res['to_date'] = $to_date;

		return is_mobile($type, "fees/fees_report/show_institute_wise_fees_paid_report", $res, "view");
	}
}
