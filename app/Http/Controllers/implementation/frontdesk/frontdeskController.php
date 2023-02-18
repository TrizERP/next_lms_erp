<?php

namespace App\Http\Controllers\implementation\frontdesk;

use App\Http\Controllers\Controller;
use App\Models\frontdesk\frontdeskModel;
use App\Models\user\tbluserModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class frontdeskController extends Controller {
	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function index(Request $request) {
		$type = $request->input("type");
		$sub_institute_id = $request->session()->get("sub_institute_id");
		$user_profile_name = $request->session()->get("user_profile_name");
		$user_id = $request->session()->get("user_id");

		$data = "SELECT fd.*,CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) as student_name,CONCAT_WS(' ',u.first_name,u.middle_name,u.last_name) as user_name FROM front_desk fd inner join tblstudent s on s.id = fd.STUDENT_ID INNER JOIN tbluser u on u.id = fd.TO_WHOM_MEET where fd.SUB_INSTITUTE_ID = '" . $sub_institute_id . "' ";

		if (strtoupper($user_profile_name) != 'ADMIN') {
			$data .= "  AND fd.TO_WHOM_MEET = '" . $user_id . "' ";
		}

		$data .= "  order by fd.ID desc";

		$data = DB::select($data);

		$res['status_code'] = 1;
		$res['message'] = "Success";
		$res['data'] = $data;

		return is_mobile($type, "frontdesk.show_frontdesk", $res, "view");
	}

	/**
	 * Show the form for creating a new resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function create(Request $request) {
		$type = $request->input("type");
		$sub_institute_id = $request->session()->get("sub_institute_id");
		$syear = $request->session()->get("syear");
		$user_id = $request->session()->get("user_id");

		$users = tbluserModel::where(["sub_institute_id" => $sub_institute_id])->whereRaw("id != '" . $user_id . "'")->get()->toArray();

		$res['status_code'] = 1;
		$res['message'] = "Success";
		$res['userList'] = $users;

		return is_mobile($type, "frontdesk.add_frontdesk", $res, "view");
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @return \Illuminate\Http\Response
	 */
	public function store(Request $request) {
		$type = $request->input("type");
		$sub_institute_id = $request->session()->get("sub_institute_id");
		$syear = $request->session()->get("syear");
		$term_id = $request->session()->get("term_id");
		$user_id = $request->session()->get("user_id");
		$data = $request->except(['_method', '_token', 'submit', 'VISITOR_PHOTO']);
		$data['SUB_INSTITUTE_ID'] = $sub_institute_id;
		$data['SYEAR'] = $syear;
		$data['MARKING_PERIOD_ID'] = $term_id;
		$data['CREATED_BY'] = $user_id;
		$data['CREATED_IP'] = $_SERVER['REMOTE_ADDR'];
		$data['CREATED_ON'] = date('Y-m-d H:i:s');
		$data['OUT_DATE'] = $request->input("DATE");

		$STUDENT = $request->input("STUDENT_ID");
		$STUDENT = explode("-", $STUDENT);
		$data['STUDENT_ID'] = trim($STUDENT[1]);

		$TO_WHOM_MEET = $request->input("TO_WHOM_MEET");
		$TO_WHOM_MEET = explode("-", $TO_WHOM_MEET);
		$data['TO_WHOM_MEET'] = trim($TO_WHOM_MEET[1]);

		$file_name = "";
		if ($request->hasFile('VISITOR_PHOTO')) {
			$file = $request->file('VISITOR_PHOTO');
			$originalname = $file->getClientOriginalName();
			$name = $originalname . date('YmdHis');
			$ext = \File::extension($originalname);
			$file_name = $name . '.' . $ext;
			$path = $file->storeAs('public/frontdesk/', $file_name);
		}

		if ($file_name != '') {
			$data['VISITOR_PHOTO'] = $file_name;
		}

		frontdeskModel::insert($data);

		$res['status_code'] = "1";
		$res['message'] = "Added successfully";

		return is_mobile($type, "frontdesk.index", $res);
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
	public function edit(Request $request, $id) {
		$sub_institute_id = $request->session()->get("sub_institute_id");
		$user_id = $request->session()->get("user_id");
		$syear = $request->session()->get("syear");

		$data = "SELECT fd.*,CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) as student_name,CONCAT_WS(' ',u.first_name,u.middle_name,u.last_name) as user_name FROM front_desk fd inner join tblstudent s on s.id = fd.STUDENT_ID INNER JOIN tbluser u on u.id = fd.TO_WHOM_MEET where fd.SUB_INSTITUTE_ID = '" . $sub_institute_id . "' and fd.ID = '" . $id . "' order by fd.ID desc";

		$result = DB::select($data);

		$result = array_map(function ($value) {
			return (array) $value;
		}, $result);

		$editData = $result[0];

		$users = tbluserModel::where(["sub_institute_id" => $sub_institute_id])->whereRaw("id != '" . $user_id . "'")->get()->toArray();

		return view('frontdesk/edit_frontdesk', ['data' => $editData, 'userList' => $users]);

	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public function update(Request $request, $id) {
		$type = $request->input("type");
		$sub_institute_id = $request->session()->get("sub_institute_id");
		$syear = $request->session()->get("syear");
		$term_id = $request->session()->get("term_id");
		$user_id = $request->session()->get("user_id");
		$data = $request->except(['_method', '_token', 'submit', 'VISITOR_PHOTO', 'STUDENT_ID']);

		$data['SUB_INSTITUTE_ID'] = $sub_institute_id;
		$data['SYEAR'] = $syear;
		$data['MARKING_PERIOD_ID'] = $term_id;
		$data['CREATED_BY'] = $user_id;
		$data['CREATED_IP'] = $_SERVER['REMOTE_ADDR'];
		$data['CREATED_ON'] = date('Y-m-d H:i:s');

		$STUDENT = $request->input("STUDENT_ID");
		$STUDENT = explode("-", $STUDENT);
		$data['STUDENT_ID'] = trim($STUDENT[1]);

		$TO_WHOM_MEET = $request->input("TO_WHOM_MEET");
		$TO_WHOM_MEET = explode("-", $TO_WHOM_MEET);
		$data['TO_WHOM_MEET'] = trim($TO_WHOM_MEET[1]);

		$file_name = "";
		if ($request->hasFile('VISITOR_PHOTO')) {
			$file = $request->file('VISITOR_PHOTO');
			$originalname = $file->getClientOriginalName();
			$name = $originalname . date('YmdHis');
			$ext = \File::extension($originalname);
			$file_name = $name . '.' . $ext;
			$path = $file->storeAs('public/frontdesk/', $file_name);
		}

		if ($file_name != '') {
			$data['VISITOR_PHOTO'] = $file_name;
		}

		$data = frontdeskModel::where(['id' => $id])->update($data);

		$res['status_code'] = "1";
		$res['message'] = "Updated successfully";

		return is_mobile($type, "frontdesk.index", $res);
	}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public function destroy(Request $request, $id) {
		$type = $request->input('type');
		frontdeskModel::where(["id" => $id])->delete();
		$res['status_code'] = "1";
		$res['message'] = "Deleted successfully";
		return is_mobile($type, "frontdesk.index", $res);
	}

	public function frontDeskReportIndex(Request $request) {
		$type = $request->input('type');
		$submit = $request->input('submit');
		$sub_institute_id = $request->session()->get('sub_institute_id');

		$res['status_code'] = 1;
		$res['message'] = "Success";

		return is_mobile($type, "frontdesk/show_frontdesk_report", $res, "view");
	}

	public function frontDeskReport(Request $request) {
		$type = $request->input('type');
		$sub_institute_id = $request->session()->get('sub_institute_id');
		$syear = $request->session()->get('syear');
		$from_date = $request->input('from_date');
		$to_date = $request->input('to_date');

		$query = "SELECT f.*, CONCAT_WS(' ',s.first_name,s.last_name) AS student_name, CONCAT_WS(' ',u.first_name,u.last_name) AS staff_name
        FROM front_desk f
        INNER JOIN tblstudent s ON f.STUDENT_ID = s.id AND f.SUB_INSTITUTE_ID = s.sub_institute_id
        INNER JOIN tbluser u ON u.id = f.TO_WHOM_MEET
        WHERE f.SUB_INSTITUTE_ID = '" . $sub_institute_id . "' and f.SYEAR = '" . $syear . "'";

		if ($from_date != '') {
			$query .= "AND f.DATE >= '" . $from_date . "' ";
		}

		if ($to_date != '') {
			$query .= "AND f.DATE <= '" . $to_date . "' ";
		}

		$result = DB::select($query);

		$res['status_code'] = 1;
		$res['message'] = "Success";
		$res['report_data'] = $result;
		$res['from_date'] = $from_date;
		$res['to_date'] = $to_date;

		return is_mobile($type, "frontdesk/show_frontdesk_report", $res, "view");
	}
}
