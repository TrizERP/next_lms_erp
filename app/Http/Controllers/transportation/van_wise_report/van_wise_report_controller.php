<?php

namespace App\Http\Controllers\transportation\van_wise_report;

use App\Http\Controllers\Controller;
use DB;
use Illuminate\Http\Request;

class van_wise_report_controller extends Controller {

	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function index(Request $request) {
		if (session()->has('data')) {
			// check if it exists
			$data_arr = session('data'); // to retrieve value
			if (isset($data_arr['message'])) {
				$data['message'] = $data_arr['message'];
			}
		}
		$data['data'] = array();
		$data['data']['ddShift'] = $this->ddShift();
		$data['data']['ddVan'] = $this->ddVan();
		$data['data']['ddRoute'] = $this->ddRoute();
		$data['data']['ddStop'] = $this->ddStop();
		$type = $request->input('type');
		return \App\Helpers\is_mobile($type, "transportation/van_wise_report/show", $data, "view");
	}

	public function ddShift() {
		$std_div_map = DB::table('transport_school_shift')
			->select('transport_school_shift.shift_title', 'transport_school_shift.id')
			->where("transport_school_shift.sub_institute_id", session()->get('sub_institute_id'))
			->pluck('shift_title', 'id');

		return $std_div_map;
	}

	public function ddVan() {
		$std_div_map = DB::table('transport_vehicle')
			->select('transport_vehicle.title', 'transport_vehicle.id')
			->where("transport_vehicle.sub_institute_id", session()->get('sub_institute_id'))
			->pluck('title', 'id');

		return $std_div_map;
	}

	public function ddRoute() {
		$where = array(
			"transport_route.sub_institute_id" => session()->get('sub_institute_id'),
			"transport_route.syear" => session()->get('syear'),
		);
		$std_div_map = DB::table('transport_route')
			->select('transport_route.route_name', 'transport_route.id')
			->where($where)
			->pluck('route_name', 'id');

		return $std_div_map;
	}

	public function ddStop() {
		$where = array(
			"transport_stop.sub_institute_id" => session()->get('sub_institute_id'),
			"transport_stop.syear" => session()->get('syear'),
		);
		$std_div_map = DB::table('transport_stop')
			->select('transport_stop.stop_name', 'transport_stop.id')
			->where($where)
			->pluck('stop_name', 'id');

		return $std_div_map;
	}

	/**
	 * Show the form for creating a new resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function create(Request $request) {

		$search = "";

		if ($_REQUEST['pickup'] == 'drop') {
			$search = "to";
		} else {
			$search = "from";
		}

		$where = "tm.sub_institute_id = '" . session()->get('sub_institute_id') . "'
                and tm.syear = '" . session()->get('syear') . "' ";

		if (isset($_REQUEST['van']) && $_REQUEST['van'] != '') {
			$where .= " and tm." . $search . "_bus_id = '" . $_REQUEST['van'] . "' ";
		}
		if (isset($_REQUEST['shift']) && $_REQUEST['shift'] != '') {
			// tm." . $search . "_bus_id
			$where .= " and tm." . $search . "_shift_id = '" . $_REQUEST['shift'] . "' ";
		}
		if (isset($_REQUEST['grno']) && $_REQUEST['grno'] != '') {
			$where .= " and ts.enrollment_no = '" . $_REQUEST['grno'] . "' ";
		}
		if (isset($_REQUEST['route']) && $_REQUEST['route'] != '') {
			$where .= " and tr.id ='" . $_REQUEST['route'] . "' ";
		}
		if (isset($_REQUEST['stop']) && $_REQUEST['stop'] != '') {
			$where .= " and st.id ='" . $_REQUEST['stop'] . "'  ";
		}
		$str = "
            SELECT ts.id AS student_id,CONCAT_WS(' ',ts.first_name,ts.middle_name,ts.last_name) name, concat(s.name,'/',d.name) as stddiv,
            ts.mobile,ts.address, tr.route_name, tv.title as bus_name, st.stop_name
            ,dd.first_name driver, cd.first_name conductor
            FROM tblstudent ts
            INNER JOIN tblstudent_enrollment se ON se.student_id = ts.id AND se.syear = '" . session()->get('syear') . "' AND se.end_date IS NULL
            INNER JOIN standard s ON s.id = se.standard_id
            INNER JOIN division d ON d.id = se.section_id
            INNER JOIN transport_map_student tm ON tm.student_id = ts.id
            INNER JOIN transport_vehicle tv ON tv.id = tm." . $search . "_bus_id
            INNER JOIN transport_school_shift ss on ss.id = tv.school_shift
            INNER JOIN transport_stop st ON st.id = tm." . $search . "_stop
            INNER JOIN transport_route_bus rb ON rb.bus_id = tv.id
            INNER JOIN transport_route tr ON tr.id = rb.route_id
            INNER JOIN transport_driver_detail dd ON dd.id = tv.driver
            LEFT JOIN transport_driver_detail cd ON cd.id = tv.conductor
            where $where group by student_id ";
		// echo "<pre>";
		// print_r($_REQUEST);
		// echo $str;
		// exit;

		$student_data = DB::select(DB::raw("$str"));
		// echo $str;
		//        echo "<pre>";
		//        print_r($student_data);
		//        exit;
		$data['data'] = $student_data;

		//        foreach ($student_data as $id => $arr) {
		//
		//            $responce_arr['stu_data'][$id]['sr.no'] = $id + 1;
		//            $responce_arr['stu_data'][$id]['name'] = $arr->name;
		//            $responce_arr['stu_data'][$id]['student_id'] = $arr->student_id;
		//            $responce_arr['stu_data'][$id]['mobile'] = $arr->mobile;
		//            $responce_arr['stu_data'][$id]['std'] = $arr->stand;
		//            $responce_arr['stu_data'][$id]['division'] = $arr->divisio;
		//        }

		$type = $request->input('type');

		return \App\Helpers\is_mobile($type, "transportation/van_wise_report/add", $data, "view");
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
