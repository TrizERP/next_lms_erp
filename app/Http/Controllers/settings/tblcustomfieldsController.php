<?php

namespace App\Http\Controllers\settings;

use App\Http\Controllers\Controller;
use App\Models\settings\tblcustomfieldsModel;
use App\Models\settings\tblfields_dataModel;
use function App\Helpers\is_mobile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\DB;
use Session;

class tblcustomfieldsController extends Controller {
	public function index(Request $request) {
		$sub_institute_id = $request->session()->get('sub_institute_id');
		$field_data = tblcustomfieldsModel::where(['status' => "1"])
			->whereRaw('(sub_institute_id = ' . $sub_institute_id . ' OR common_to_all = 1)')
			->get();
		$res['status_code'] = 1;
		$res['message'] = "Success";
		$res['data'] = $field_data;
		$type = $request->input('type');
		return is_mobile($type, "settings/show_fields", $res, "view");
	}

	public function create(Request $request) {
		return view('settings/add_fields');
	}

	public function store(Request $request) {
		$newRequest = $request->all();
		$field_type = $request->get('field_type');
		$sub_institute_id = $request->session()->get('sub_institute_id');
		$required_fields['table_name'] = 'required';
		$required_fields['field_name'] = 'required';
		$required_fields['field_label'] = 'required';
		$required_fields['field_type'] = 'required';

		if ($field_type == 'checkbox' || $field_type == 'dropdown') {
			$required_fields['display_name'] = 'required';
			$required_fields['f_value'] = 'required';
		}

		$newRequest['display_name'] = array_filter($newRequest['display_name']);
		$newRequest['f_value'] = array_filter($newRequest['f_value']);

		$validator = Validator::make($newRequest, $required_fields);

		if ($validator->fails()) {
			$failedRules = $validator->failed();
			$res['status_code'] = "0";
			$res['message'] = "Please input all required parameters.";
			$type = $request->input('type');
			return is_mobile($type, "add_fields.create", $res);
		}
		$field_message = '';
		$field_messages = array_filter($request->get('field_message'));
		foreach ($field_messages as $key => $value) {
			$field_message = $value;
		}

		$field_name = strtolower(str_replace(" ", "_", $request->get('field_name')));
		$table_name = $request->get('table_name');
		// echo $field_name;
		// exit;
		$required = $request->get('required') != '' ? $request->get('required') : '0';
		$common_to_all = $request->get('common_to_all') != '' ? $request->get('common_to_all') : '0';

		$fields = new tblcustomfieldsModel([
			'table_name' => $table_name,
			'field_name' => $field_name,
			'field_label' => $request->get('field_label'),
			'field_type' => $request->get('field_type'),
			'display_name' => $request->get('display_name'),
			'f_value' => $request->get('f_value'),
			'field_message' => $field_message,
			'status' => "1",
			'sort_order' => "1",
			'file_size_max' => $request->get('file_size_max'),
			'sub_institute_id' => $sub_institute_id,
			'required' => $required,
			'common_to_all' => $common_to_all,
		]);

		$fields->save();
		$fieldsId = $fields->id;
		foreach ($newRequest['display_name'] as $key => $value) {
			$fieldsData = new tblfields_dataModel([
				'field_id' => $fieldsId,
				'display_text' => $value,
				'display_value' => $newRequest['f_value'][$key],
				'created_on' => date('Y-m-d h:i:s'),
			]);
			$fieldsData->save();
		}
		if (Schema::hasColumn($table_name, $field_name)) {

		} else {
			Schema::table($table_name, function ($table) use ($field_name) {
				$table->string($field_name)->nullable();
			});
		}

		$res['status_code'] = "1";
		$res['message'] = "Field added successfully";

		$type = $request->input('type');
		return is_mobile($type, "add_fields.index", $res);
	}

	public function update(Request $request, $id) {
		// UpDTAe FuncTiON.
	}

	public function destroy(Request $request, $id) {
		$fields = array(
			'status' => "0",
		);
		$type = $request->input('type');
		tblcustomfieldsModel::where(["id" => $id])->update($fields);
		// tbluserModel::where(["id" => $id])->delete();
		$res['status_code'] = "1";
		$res['message'] = "Cutsom Field deleted successfully";
		return is_mobile($type, "add_fields.index", $res);
	}

	public function setsession(Request $request) {
		$type = $request->input('type');
		$syear = $request->input('syear');
		$term_id = $request->input('term_id');

		if ($syear != '') {
			$request->session()->put('syear', $syear);
		}

		if ($term_id != '') {
			$request->session()->put('term_id', $term_id);
		}
		
		//START set class teacher standard , grade , division
		//dd(session()->all());
		$user_group_id = DB::select("SELECT * FROM tbluserprofilemaster WHERE NAME = 'Teacher' AND 
		sub_institute_id = '".session()->get('sub_institute_id')."'");
		$user_group_id = $user_group_id[0]->id;		
		if($user_group_id == session()->get('user_profile_id'))
		{
			$class_teacher   = DB::select("SELECT * FROM class_teacher WHERE teacher_id = '".session()->get('user_id')."' 
			AND syear = '".session()->get('syear')."' AND sub_institute_id = '".session()->get('sub_institute_id')."'");						
			$classTeacherGrdArr = $classTeacherStdArr = $classTeacherDivArr = array(); 
			if(count($class_teacher) > 0)
			{
				foreach($class_teacher as $k => $v)
				{
					$classTeacherGrdArr[] = $v->grade_id;
					$classTeacherStdArr[] = $v->standard_id;
					$classTeacherDivArr[] = $v->division_id;					
				}								
			}
			Session::put('classTeacherGrdArr', $classTeacherGrdArr);
			Session::put('classTeacherStdArr', $classTeacherStdArr);
			Session::put('classTeacherDivArr', $classTeacherDivArr);
		}
		//END set class teacher standard , grade , division

	}

	public function setinstitute(Request $request)
	{
		$type = $request->input('type');
		$sub_institute_id = $request->input('institute');
		$syear = session()->get('syear');
		// dd($sub_institute_id);
		if ($sub_institute_id != '')
		{
			$request->session()->put('sub_institute_id', $sub_institute_id);
			$request->session()->put('new_sub_institute_id', '1');

			$getAcademicTerms = DB::select("SELECT * FROM academic_year
										WHERE sub_institute_id = '".$sub_institute_id."' AND 
										syear = '".$syear."'");

			$getAcademicYear = DB::select("SELECT * FROM academic_year 
									   WHERE sub_institute_id = '".$sub_institute_id."' 
									   GROUP BY syear");

			$request->session()->put('academicTerms', $getAcademicTerms);
			$request->session()->put('academicYears', $getAcademicYear);

		}

	}

}
