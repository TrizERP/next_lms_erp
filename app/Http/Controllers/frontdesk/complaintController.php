<?php

namespace App\Http\Controllers\frontdesk;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\frontdesk\complaintModel;
use App\Models\student\tblstudentModel;
use App\Models\user\tbluserModel;

use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;
use function App\Helpers\is_mobile;
use Illuminate\Http\Response;

class complaintController extends Controller {
	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function index(Request $request) {
		$type = $request->input("type");
        $from_date = $request->input("from_date");
		$to_date = $request->input("to_date");
		$sub_institute_id = $request->session()->get("sub_institute_id");
		$syear = $request->session()->get("syear");
		
		$data = array();

		$data = "SELECT c.*, CONCAT_WS(' ',u.first_name,u.middle_name,u.last_name) AS COMPLAINT_BY, CONCAT_WS(' ',u2.first_name,u2.middle_name,u2.last_name) AS COMPLAINT_SOLUTION_BY
		FROM complaint c
		INNER JOIN tbluser u ON c.COMPLAINT_BY = u.id AND u.sub_institute_id = '" . $sub_institute_id . "'
		LEFT JOIN tbluser u2 ON c.COMPLAINT_SOLUTION_BY = u2.id AND u2.sub_institute_id = '" . $sub_institute_id . "'
		WHERE c.SYEAR = '" . $syear . "' and c.SUB_INSTITUTE_ID = '".$sub_institute_id."'";

		if(isset($from_date))
		{
			$data .= "  AND c.DATE >= '".$from_date."' ";
			$res['from_date'] = $from_date;
		}

		if(isset($to_date))
		{
			$data .= "  AND c.DATE <= '".$to_date."' ";
			$res['to_date'] = $to_date;
		}
	
		$data = DB::select($data);

		$res['status_code'] = 1;
		$res['message'] = "Success";
		$res['data'] = $data;

		return is_mobile($type, "frontdesk.show_complaint", $res, "view");
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

		return is_mobile($type, "frontdesk.add_complaint", $res, "view");
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
		$data = $request->except(['_method', '_token', 'submit', 'ATTACHEMENT']);

		$data['SYEAR'] = $syear;
		$data['MARKING_PERIOD_ID'] = $term_id;
	
		$data['COMPLAINT_BY'] = $user_id;
		$data['SUB_INSTITUTE_ID'] = $sub_institute_id;
		$data['COMPLAINT_SOLUTION'] = "PENDING";
		$data['CREATED_IP'] = $_SERVER['REMOTE_ADDR'];
		$data['CREATED_DATE'] = date('Y-m-d H:i:s');

		$file_name = $ext = $file_size = "";
		if ($request->hasFile('ATTACHEMENT')) {
			$file = $request->file('ATTACHEMENT');
			$originalname = $file->getClientOriginalName();
			$file_size = $file->getSize();			
			$name = "complaint_" . date('YmdHis');
			$ext = \File::extension($originalname);
			$file_name = $name . '.' . $ext;
			$path = $file->storeAs('public/frontdesk/', $file_name);
		}

		if ($file_name != '') {
			$data['ATTACHEMENT'] = $file_name;
			$data['FILE_SIZE'] = $file_size;
			$data['FILE_TYPE'] = $ext;
		}

		complaintModel::insert($data);

		$res['status_code'] = "1";
		$res['message'] = "Added successfully";

		return is_mobile($type, "complaint.index", $res);
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

		$data = "SELECT c.*, CONCAT_WS(' ',u.first_name,u.middle_name,u.last_name) AS COMPLAINT_BY, CONCAT_WS(' ',u2.first_name,u2.middle_name,u2.last_name) AS COMPLAINT_SOLUTION_BY
		FROM complaint c
		INNER JOIN tbluser u ON c.COMPLAINT_BY = u.id AND u.sub_institute_id = '" . $sub_institute_id . "'
		LEFT JOIN tbluser u2 ON c.COMPLAINT_SOLUTION_BY = u2.id AND u2.sub_institute_id = '" . $sub_institute_id . "'
		WHERE c.SYEAR = '" . $syear . "' and c.SUB_INSTITUTE_ID = '".$sub_institute_id."' AND  c.ID = '" . $id . "'";

		$result = DB::select($data);

		$result = array_map(function ($value) {
			return (array) $value;
		}, $result);

		$editData = $result[0];


		$dataResult = "SELECT * from complaint_status WHERE TYPE = 'COMPLAIN'";

		$dataResult = DB::select($dataResult);

		$dataResult = array_map(function ($value) {
			return (array) $value;
		}, $dataResult);

		$complaintStatus = $dataResult;

		$users = tbluserModel::where(["sub_institute_id" => $sub_institute_id])->get()->toArray();

		return view('frontdesk/edit_complaint', ['data' => $editData,'complaint_status' => $complaintStatus, 'userList' => $users]);
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
		$data = $request->except(['_method', '_token', 'submit', 'ATTACHEMENT']);

		$data['SYEAR'] = $syear;
		$data['MARKING_PERIOD_ID'] = $term_id;
	
		$data['COMPLAINT_BY'] = $user_id;
		$data['SUB_INSTITUTE_ID'] = $sub_institute_id;
		$data['CREATED_IP'] = $_SERVER['REMOTE_ADDR'];
		$data['CREATED_DATE'] = date('Y-m-d H:i:s');

		$file_name = $ext = $file_size = "";
		if ($request->hasFile('ATTACHEMENT')) {
			$file = $request->file('ATTACHEMENT');
			$originalname = $file->getClientOriginalName();
			$file_size = $file->getSize();
			$name = "complaint_" . date('YmdHis');
			$ext = \File::extension($originalname);
			$file_name = $name . '.' . $ext;
			$path = $file->storeAs('public/frontdesk/', $file_name);
		}

		if ($file_name != '') {
			$data['ATTACHEMENT'] = $file_name;
			$data['FILE_SIZE'] = $file_size;
			$data['FILE_TYPE'] = $ext;
		}
		// dd($data);
		$data = complaintModel::where(['id' => $id])->update($data);

		$res['status_code'] = "1";
		$res['message'] = "Updated successfully";

		return is_mobile($type, "complaint.index", $res);
	}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public function destroy(Request $request, $id) {
		$type = $request->input('type');
		taskModel::where(["id" => $id])->delete();
		$res['status_code'] = "1";
		$res['message'] = "Deleted successfully";
		return is_mobile($type, "complaint.index", $res);
	}


	public function complaintReportIndex(Request $request)
    {
        $type = $request->input('type');

        $res['status_code'] = 1;
        $res['message'] = "Success";

        return is_mobile($type, "frontdesk.complaint_report", $res , "view");
    }
}
