<?php

namespace App\Http\Controllers\fees\fees_report;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;
use App\Models\fees\map_year\map_year;
use Illuminate\Support\Facades\DB;


class feesStructureReportController extends Controller {
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

	public function feesStructureReportIndex(Request $request) {
		$type = $request->input('type');
		$submit = $request->input('submit');
		$sub_institute_id = $request->session()->get('sub_institute_id');

		$res['status_code'] = 1;
		$res['message'] = "Success";

		return is_mobile($type, "fees/fees_report/show_fees_structure_report", $res, "view");
	}

	public function feesStructureReport(Request $request) {
		$type = $request->input('type');
		$sub_institute_id = $request->session()->get('sub_institute_id');
		$syear = $request->session()->get('syear');
		$grade = $request->input('grade');
		$standard = $request->input('standard');		

		$data = map_year::
                where([
                    'sub_institute_id' => session()->get('sub_institute_id'),
                    'syear' => session()->get('syear')
                ])->get()->toArray();
        $start_month = $data[0]['from_month'];
        $end_month = $data[0]['to_month'];
        $months = array(1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec');
        $months_arr = array();
        $syear = session()->get('syear');

        for ($i = 1; $i <= 12; $i++) {
            $months_arr[$start_month . $syear] = $months[$start_month] . '/' . $syear;
            if ($start_month == 12) {
                $start_month = 0;
                $syear = $syear + 1;
            }
            $start_month = $start_month + 1;
        }
		
		
		$extra_query = "";
		if($grade != "")
		{
			$extra_query = " AND s.grade_id = '".$grade."'";
		}
		if($standard != "")
		{
			$extra_query = " AND s.id = '".$standard."'";
		}
		
        $query = "SELECT *,s.name AS standard_name 
		FROM standard s WHERE s.sub_institute_id = '" . session()->get('sub_institute_id') . "' ".$extra_query;        
		$std_result = DB::select($query);   
		
		$query1 = "SELECT *,q.title AS quota_name 
		FROM student_quota q WHERE q.sub_institute_id = '" . session()->get('sub_institute_id') . "'";        
		$quota_result = DB::select($query1);   
		
		$final_data = array();
		foreach($std_result as $key => $val)
		{
			foreach($quota_result as $qkey => $qval)
			{	
				//NEW STUDENT AMOUNT
				$amt_query = "SELECT sum(amount) as new_amount,month_id FROM fees_breackoff 
				WHERE sub_institute_id = '".session()->get('sub_institute_id')."'
				AND standard_id = '".$val->id."' and quota= '".$qval->id."' and admission_year = '".session()->get('syear')."'
				AND syear = '".session()->get('syear')."'
				GROUP BY quota,month_id";        
				$amt_result = DB::select($amt_query); 								
				$amt = json_decode(json_encode($amt_result), true);				
				foreach($amt as $akey => $aval)
				{
					$final_data[$val->standard_name][$qval->quota_name]['NEW'][$aval['month_id']] = $aval['new_amount']; 								
				}
				
				//OLD STUDENT AMOUNT
				$old_year = session()->get('syear') - 1;
				$old_amt_query = "SELECT sum(amount) as new_amount,month_id FROM fees_breackoff 
				WHERE sub_institute_id = '".session()->get('sub_institute_id')."'
				AND standard_id = '".$val->id."' and quota= '".$qval->id."' and admission_year = '".$old_year."'
				AND syear = '".session()->get('syear')."'
				GROUP BY quota,month_id";        
				$old_amt_result = DB::select($old_amt_query); 								
				$old_amt = json_decode(json_encode($old_amt_result), true);												
				foreach($old_amt as $okey => $oval)
				{
					$final_data[$val->standard_name][$qval->quota_name]['OLD'][$oval['month_id']] = $oval['new_amount']; 								
				}
								
			}
		}		
		// echo '<pre>';
		// print_r($months_arr);
		// print_r($final_data);
		// die;		
		$res['status_code'] = 1;
		$res['message'] = "Success";
		$res['months_arr'] = $months_arr;
		$res['grade_id'] = $grade;
		$res['standard_id'] = $standard;		
		$res['report_data'] = $final_data;		

		return is_mobile($type, "fees/fees_report/show_fees_structure_report", $res, "view");
	}
}
