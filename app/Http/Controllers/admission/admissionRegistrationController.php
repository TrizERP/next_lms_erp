<?php

namespace App\Http\Controllers\admission;

use App\Http\Controllers\Controller;
use App\Models\admission\admissionEnquiryModel;
use App\Models\admission\admissionRegistrationModel;
use App\Models\settings\tblcustomfieldsModel;
use App\Models\settings\tblfields_dataModel;
use App\Models\school_setup\bloodgroupModel;
use App\Models\school_setup\standardModel;
use App\Models\student\tblstudentEnrollmentModel;
use App\Models\student\tblstudentModel;
use App\Models\student\studentQuotaModel;
use function App\Helpers\is_mobile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class admissionRegistrationController extends Controller {
	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function index(Request $request) {
		$type = $request->input('type');
		$sub_institute_id = $request->session()->get("sub_institute_id");
		$syear = session()->get("syear");

		$data = DB::select("Select ae.*,COUNT(ts.id) AS total_student_count,ae.remarks AS enquiry_remark,s.name AS std_name 
							FROM admission_enquiry ae 
							INNER JOIN admission_form af ON ae.id = af.enquiry_id
							LEFT JOIN tblstudent ts ON ts.admission_id = ae.id AND ts.admission_year = ae.syear AND ts.sub_institute_id = ae.sub_institute_id 
							LEFT JOIN standard s ON s.id = ae.admission_standard AND s.sub_institute_id = '".$sub_institute_id."'
							WHERE ae.sub_institute_id = '".$sub_institute_id."' AND ae.syear = '".$syear."' 
							GROUP BY ae.id");

		$data = array_map(function ($value) {
			return (array) $value;
		}, $data);

		$res['status_code'] = 1;
		$res['message'] = "Success";
		$res['data'] = $data;

		return is_mobile($type, 'admission/registration/show_admission_registration', $res, 'view');
	}

	/**
	 * Show the form for creating a new resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function create() {
		//
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
	public function edit(Request $request, $id) {
		$type = $request->input('type');
		$sub_institute_id = $request->session()->get('sub_institute_id');
		
		if($sub_institute_id == 198) // For Mahaeshvari school
		{
			$data = DB::select("Select ae.*,ar.*,ae.id as id,ae.enquiry_no as enquiry_no,CONCAT_WS(',',ae.house_no,ae.`building_name_appratment_name_society_name`,ae.district_name,ae.pin_code,ae.state) AS address,
			ae.previous_standard,ae.mother_name,ae.mobile_number_mother ,ae.place_of_birth,ar.enquiry_id as registration_enquiry_id
			from admission_enquiry ae INNER JOIN admission_form af ON ae.id = af.enquiry_id LEFT JOIN admission_registration ar ON ae.id = ar.enquiry_id WHERE ae.id = '" . $id . "'");
		}else{
			$data = DB::select("Select ae.*,ar.*,ae.id as id,ae.enquiry_no as enquiry_no,ar.enquiry_id as registration_enquiry_id
			from admission_enquiry ae 
			INNER JOIN admission_form af ON ae.id = af.enquiry_id 
			LEFT JOIN admission_registration ar ON ae.id = ar.enquiry_id WHERE ae.id = '" . $id . "'");
		}

		$data = array_map(function ($value) {
			return (array) $value;
		}, $data);

		$editData = $data;
		// dd($editData);
		$checkStudent = tblstudentModel::where(['admission_id' => $id])->get()->toArray();

		$dataCustomFields = tblcustomfieldsModel::where(['status' => "1", 'table_name' => "admission_registration"])
			->whereRaw('(sub_institute_id = ' . $sub_institute_id . ' OR common_to_all = 1)')
			->get();

		$fieldsData = tblfields_dataModel::get()->toArray();
		$i = 0;
		$finalfieldsData = array();
		foreach ($fieldsData as $key => $value) {
			$finalfieldsData[$value['field_id']][$i]['display_text'] = $value['display_text'];
			$finalfieldsData[$value['field_id']][$i]['display_value'] = $value['display_value'];
			$i++;
		}


		if(count($checkStudent) > 0)
		{
			$res['display_save_student'] = '0';
		}else{
			$res['display_save_student'] = '1';
		}

		$category = studentQuotaModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

		if(isset($editData[0]['enrollment_no']) && $editData[0]['enrollment_no'] != '' ){
			$res['new_enrollment_no'] = $editData[0]['enrollment_no'];
		}else{
			$res['new_enrollment_no'] = $this->max_enrollment_no($sub_institute_id,$editData[0]['admission_standard']);
		}

		$standard = standardModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

		$bloodgroupData = bloodgroupModel::select()->get();

		$getDiv = DB::select("SELECT d.id,d.name,sdm.standard_id
							FROM std_div_map sdm 
							INNER JOIN standard s ON s.id =sdm.standard_id AND s.sub_institute_id = sdm.sub_institute_id
							INNER JOIN division d ON d.id = sdm.division_id AND d.sub_institute_id = sdm.sub_institute_id 
							WHERE sdm.sub_institute_id = '".$sub_institute_id."' AND sdm.standard_id = '".$editData[0]['admission_standard']."' ");

		$getDiv = array_map(function ($value) {
			return (array) $value;
		}, $getDiv);

		$res['status_code'] = "1";
		$res['message'] = "Successfully";
		$res['editData'] = $editData['0'];
		$res['standard'] = $standard;
		$res['bloodgroup_data'] = $bloodgroupData;
		$res['custom_fields'] = $dataCustomFields;
		if(count($getDiv) > 0)
		{
			$res['division'] = $getDiv;
		}
		if (count($finalfieldsData) > 0) {
			$res['data_fields'] = $finalfieldsData;
		}
		if(count($category) > 0)
		{
			$res['category'] = $category;
		}
		// dd($res);
		return is_mobile($type, 'admission/registration/edit_admission_registration', $res, 'view');
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
		$user_id = $request->session()->get("user_id");

		$editdata['first_name'] = $request->input("first_name");
		$editdata['middle_name'] = $request->input("middle_name");
		$editdata['last_name'] = $request->input("last_name");
		$editdata['mobile'] = $request->input("mobile");
		$editdata['email'] = $request->input("email");
		$editdata['date_of_birth'] = $request->input("date_of_birth");
		$editdata['age'] = $request->input("age");
		$editdata['address'] = $request->input("address");
		$editdata['previous_school_name'] = $request->input("previous_school_name");
		// $editdata['previous_standard'] = $request->input("previous_standard");
		$editdata['source_of_enquiry'] = $request->input("source_of_enquiry");
		// $editdata['remarks'] = $request->input("remarks");
		// $editdata['followup_date'] = $request->input("followup_date");

		admissionEnquiryModel::where(['id' => $id,'sub_institute_id' => $sub_institute_id])->update($editdata);

		$data = $request->except(['_method', '_token', 'submit', 'type', 'first_name', 'middle_name','last_name','mobile','email','date_of_birth','age','address','previous_school_name','previous_standard','source_of_enquiry','admission_standard']); //,'remarks','followup_date'

		$checkForm = admissionRegistrationModel::where(['enquiry_id' => $id])->get()->toArray();
		if (count($checkForm) > 0) {
			$data['enquiry_id'] = $id;
			$data['created_by'] = $user_id;
			$data['created_on'] = date('Y-m-d H:i:s');
			$data['sub_institute_id'] = $sub_institute_id;

			admissionRegistrationModel::where(['enquiry_id' => $id])->update($data);
		} else {
			$data['enquiry_id'] = $id;
			$data['created_by'] = $user_id;
			$data['created_on'] = date('Y-m-d H:i:s');
			$data['sub_institute_id'] = $sub_institute_id;

			admissionRegistrationModel::insert($data);
		}

		$res['status_code'] = "1";
		$res['message'] = "Added successfully";

		return is_mobile($type, "admission_registration.index", $res);
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

	public function saveStudent(Request $request) {
		$type = $request->input("type");
		$sub_institute_id = $request->session()->get("sub_institute_id");
		$term_id = $request->session()->get("term_id");
		$syear = $request->session()->get("syear");
		$id = $request->input("id");

		$user_profile_sql = "select tu.id as id from tbluserprofilemaster tu where tu.name = 'Student' and tu.sub_institute_id = '" . $sub_institute_id . "'";
		$user_profile_result = DB::select($user_profile_sql);
		$user_profile_id = $user_profile_result[0]->id;


		$data = DB::select("Select ae.*,af.*,ae.id as id,ar.* from admission_enquiry ae INNER JOIN admission_form af ON ae.id = af.enquiry_id INNER JOIN admission_registration ar ON ae.id = ar.enquiry_id WHERE ae.id = '" . $id . "'");

		$data = array_map(function ($value) {
			return (array) $value;
		}, $data);

		if (count($data) == 0) {
			$res['status_code'] = 0;
			$res['message'] = "Please complete admission enquiry process";

			return is_mobile($type, "admission_registration.index", $res);
		}

		$data = $data['0'];
		// dd($data);
		$standardDetails = standardModel::where(['id' => $data['admission_standard']])->get()->toArray();

		$grade_id = $standardDetails['0']['grade_id'];

		$studentArray = array();
		$studentEnrollmentArray = array();

		// $studentArray['enrollment_no'] = $data['enrollment_no'];


		$studentArray['admission_id'] = $id;
		$studentArray['first_name'] = $data['first_name'];
		$studentArray['middle_name'] = $data['middle_name'];
		$studentArray['last_name'] = $data['last_name'];
		$studentArray['gender'] = $data['gender'];
		$studentArray['mobile'] = $data['mobile'];
		$studentArray['email'] = $data['email'];
		$studentArray['address'] = $data['address'];
		$studentArray['username'] = $data['enrollment_no'];
		$studentArray['user_profile_id'] = $user_profile_id;
		$studentArray['admission_year'] = $syear;//date('Y');
		$studentArray['since_when'] = $syear;//date('Y');
		$studentArray['admission_date'] = $data['admission_date'];//date('Y-m-d');
		$studentArray['sub_institute_id'] = $sub_institute_id;
		$studentArray['status'] = "1";
		$studentArray['place_of_birth'] = $data['place_of_birth'];
		$studentArray['adharnumber'] = $data['aadhar_number'];
		$studentArray['mother_name'] = $data['mother_name'];
		$studentArray['mother_mobile'] = $data['mother_mobile_number'];
		$studentArray['father_name'] = $data['father_name'];
		$studentArray['dob'] = $data['date_of_birth'];
		$studentArray['anuualincome'] = $data['annual_income'];
		$studentArray['bloodgroup'] = $data['blood_group'];
		$studentArray['admission_docket_no'] = $data['admission_docket_no'];
		$studentArray['registration_no'] = $data['registration_no'];

		if(isset($data['enrollment_no']) && $data['enrollment_no'] != '')
		{
			$enrollment_no_sql_new = $data['enrollment_no'];

			$insert_sql = "insert into `tblstudent` (`admission_id`, `first_name`, `middle_name`, `last_name`, `gender`, `mobile`, `email`, `address`, 
			`username`, `user_profile_id`, `admission_year`, `since_when`, `admission_date`, `sub_institute_id`, `status`, `place_of_birth`,
			 `adharnumber`, `mother_name`, `mother_mobile`, `father_name`, `dob`, `anuualincome`, `bloodgroup`, `admission_docket_no`, `registration_no`, `enrollment_no`) 
				VALUES (
					'".$studentArray['admission_id']."', '".$studentArray['first_name']."', 
					'".$studentArray['middle_name']."', '".$studentArray['last_name']."', 
					'".$studentArray['gender']."', '".$studentArray['mobile']."', 
					'".$studentArray['email']."', '".$studentArray['address']."', 
					'".$studentArray['username']."', '".$studentArray['user_profile_id']."', 
					'".$studentArray['admission_year']."', '".$studentArray['since_when']."', 
					'".$studentArray['admission_date']."', '".$studentArray['sub_institute_id']."', 
					'".$studentArray['status']."', '".$studentArray['place_of_birth']."', 
					'".$studentArray['adharnumber']."', '".$studentArray['mother_name']."', 
					'".$studentArray['mother_mobile']."','".$studentArray['father_name']."', 
					'".$studentArray['dob']."', '".$studentArray['anuualincome']."', 
					'".$studentArray['bloodgroup']."','".$studentArray['admission_docket_no']."', '".$studentArray['registration_no']."', 
					'".$enrollment_no_sql_new."' )";

			DB::insert($insert_sql);		
			$student_id = DB::getPdo()->lastInsertId();		

		}
		else
		{
			$enrollment_no_sql_new = $this->max_enrollment_no_new($sub_institute_id,$data['admission_standard']);

			$insert_sql = "insert into `tblstudent` (`admission_id`, `first_name`, `middle_name`, `last_name`, `gender`, `mobile`, `email`, `address`, 
			`username`, `user_profile_id`, `admission_year`, `since_when`, `admission_date`, `sub_institute_id`, `status`, `place_of_birth`,
			 `adharnumber`, `mother_name`, `mother_mobile`, `father_name`, `dob`, `anuualincome`, `bloodgroup`, `admission_docket_no`, `registration_no`, `enrollment_no`) 
				VALUES (
					'".$studentArray['admission_id']."', '".$studentArray['first_name']."', 
					'".$studentArray['middle_name']."', '".$studentArray['last_name']."', 
					'".$studentArray['gender']."', '".$studentArray['mobile']."', 
					'".$studentArray['email']."', '".$studentArray['address']."', 
					'".$studentArray['username']."', '".$studentArray['user_profile_id']."', 
					'".$studentArray['admission_year']."', '".$studentArray['since_when']."', 
					'".$studentArray['admission_date']."', '".$studentArray['sub_institute_id']."', 
					'".$studentArray['status']."', '".$studentArray['place_of_birth']."', 
					'".$studentArray['adharnumber']."', '".$studentArray['mother_name']."', 
					'".$studentArray['mother_mobile']."','".$studentArray['father_name']."', 
					'".$studentArray['dob']."', '".$studentArray['anuualincome']."', 
					'".$studentArray['bloodgroup']."','".$studentArray['admission_docket_no']."', '".$studentArray['registration_no']."', 
					(".$enrollment_no_sql_new.") )";

			$student_id = DB::transaction(function() use ($insert_sql) {
				DB::insert($insert_sql);		
				$student_id = DB::getPdo()->lastInsertId();
				return $student_id;
			});
		}

		//tblstudentModel::insert($studentArray);

		$studentEnrollmentArray['syear'] = $syear;
		$studentEnrollmentArray['student_id'] = $student_id;
		$studentEnrollmentArray['grade_id'] = $grade_id;
		$studentEnrollmentArray['standard_id'] = $data['admission_standard'];
		$studentEnrollmentArray['section_id'] = $data['admission_division'];
		$studentEnrollmentArray['student_quota'] = $data['student_quota'];
		$studentEnrollmentArray['start_date'] = date('Y-m-d');
		$studentEnrollmentArray['enrollment_code'] = "1";
		$studentEnrollmentArray['term_id'] = $term_id;
		$studentEnrollmentArray['admission_fees'] = $data['amount'];
		$studentEnrollmentArray['sub_institute_id'] = $sub_institute_id;

		tblstudentEnrollmentModel::insert($studentEnrollmentArray);

		$res['status_code'] = 1;
		$res['message'] = "Student added successfully";//with Enrollment Number - ".$studentArray['enrollment_no'];

		return is_mobile($type, "admission_registration.index", $res);
	}
	public function max_enrollment_no($sub_institute_id,$admission_standard_id)
	{
		
		if($sub_institute_id == 47)//Generate Enrollment No for MMISERP 
		{
			$get_prefix = "SELECT * FROM enrollment_prefix_master 
					   WHERE sub_institute_id = '".$sub_institute_id."' 
					   AND FIND_IN_SET ('".$admission_standard_id."',standards) ";

			$get_prefix_result = DB::select($get_prefix);			   
			$prefix = $get_prefix_result[0]->prefix;

			if($prefix != '')
			{
				$enrollment_no_sql = "SELECT *,MAX(enrollment_no) as new_enrollment_no
				FROM tblstudent
				WHERE sub_institute_id = '".$sub_institute_id."' AND enrollment_no LIKE '%".$prefix."%'";

				$enrollment_result = DB::select($enrollment_no_sql);		
				$get_enrollment_no = substr($enrollment_result[0]->new_enrollment_no,2,6);
				$new_enrollment_number = $get_enrollment_no + 1;
				$new_enrollment_no = $prefix.$new_enrollment_number;			
			}
			else
			{
				$get_prefix_null = "SELECT GROUP_CONCAT(prefix) as all_prefix
						   FROM enrollment_prefix_master 
						   WHERE sub_institute_id = '".$sub_institute_id."' ";

				$get_prefix_null_result = DB::select($get_prefix_null);
				$get_prefix_null_result = $get_prefix_null_result[0];
				$prefix_expload = explode(',', $get_prefix_null_result->all_prefix);

				$extra_query = "";
				foreach ($prefix_expload as $key => $value) {
					$extra_query .= " AND enrollment_no NOT LIKE '%".$value."%'";
				}
				
				$enrollment_no_sql = "SELECT *,MAX(enrollment_no) as new_enrollment_no
				FROM tblstudent
				WHERE sub_institute_id = '".$sub_institute_id."' $extra_query ";
				
				$enrollment_result = DB::select($enrollment_no_sql);		
				$get_enrollment_no = $enrollment_result[0]->new_enrollment_no;
				$new_enrollment_no = $get_enrollment_no + 1;					
			}
		}
		else
		{			
			$maxEnrollment = DB::select("SELECT (MAX(CAST(enrollment_no AS INT)) + 1) AS new_enrollment_no FROM tblstudent WHERE sub_institute_id = '".$sub_institute_id."' ORDER BY id DESC LIMIT 1");

			$maxEnrollment = array_map(function ($value) {
				return (array) $value;
			}, $maxEnrollment);

			$new_enrollment_no = $maxEnrollment['0']['new_enrollment_no'];

			// dd($new_enrollment_no);
			// if(count($maxEnrollment) > 0)
			// {
			// 	if(is_numeric($maxEnrollment['0']['enrollment_no']))
			// 	{
			// 		$new_enrollment_no = ($maxEnrollment['0']['enrollment_no']+1);
			// 	}
			// }
		}

		return $new_enrollment_no;	
	}

	// This function return max enrollment query
	public function max_enrollment_no_new($sub_institute_id,$admission_standard_id)
	{
		
		if($sub_institute_id == 47)//Generate Enrollment No for MMISERP 
		{
			$get_prefix = "SELECT * FROM enrollment_prefix_master 
					   WHERE sub_institute_id = '".$sub_institute_id."' 
					   AND FIND_IN_SET ('".$admission_standard_id."',standards) ";

			$get_prefix_result = DB::select($get_prefix);			   
			$prefix = $get_prefix_result[0]->prefix;

			if($prefix != '')
			{
				$enrollment_no_sql = "SELECT concat_Ws('','".$prefix."',substr(MAX(enrollment_no),3) + 1) as new_enrollment_no
				FROM tblstudent as s
				WHERE sub_institute_id = '".$sub_institute_id."' AND enrollment_no LIKE '%".$prefix."%'";

				// $enrollment_result = DB::select($enrollment_no_sql);		
				// $get_enrollment_no = substr($enrollment_result[0]->new_enrollment_no,2,6);
				// $new_enrollment_number = $get_enrollment_no + 1;
				// $new_enrollment_no = $prefix.$new_enrollment_number;			
			}
			else
			{
				$get_prefix_null = "SELECT GROUP_CONCAT(prefix) as all_prefix
						   FROM enrollment_prefix_master 
						   WHERE sub_institute_id = '".$sub_institute_id."' ";

				$get_prefix_null_result = DB::select($get_prefix_null);
				$get_prefix_null_result = $get_prefix_null_result[0];
				$prefix_expload = explode(',', $get_prefix_null_result->all_prefix);

				$extra_query = "";
				foreach ($prefix_expload as $key => $value) {
					$extra_query .= " AND enrollment_no NOT LIKE '%".$value."%'";
				}
				
				$enrollment_no_sql = "SELECT (MAX(enrollment_no) + 1) as new_enrollment_no
				FROM tblstudent as s
				WHERE sub_institute_id = '".$sub_institute_id."' $extra_query ";
				
				// $enrollment_result = DB::select($enrollment_no_sql);		
				// $get_enrollment_no = $enrollment_result[0]->new_enrollment_no;
				// $new_enrollment_no = $get_enrollment_no + 1;					
			}
		}
		else
		{		
			$enrollment_no_sql = "SELECT MAX(cast(enrollment_no as int) + 1) as new_enrollment_no FROM tblstudent as s WHERE sub_institute_id = '".$sub_institute_id."'";	
			// $maxEnrollment = DB::select("SELECT enrollment_no FROM tblstudent WHERE sub_institute_id = '".$sub_institute_id."' ORDER BY id DESC LIMIT 1");

			// $maxEnrollment = array_map(function ($value) {
			// 	return (array) $value;
			// }, $maxEnrollment);
			// // dd($maxEnrollment);
			// if(count($maxEnrollment) > 0)
			// {
			// 	if(is_numeric($maxEnrollment['0']['enrollment_no']))
			// 	{
			// 		$new_enrollment_no = ($maxEnrollment['0']['enrollment_no']+1);
			// 	}
			// }
		}
		return $enrollment_no_sql;	
	}

	public function ajax_getDivision(Request $request)
	{		    
		$standard_id = $request->input("standard_id");        
        $sub_institute_id = session()->get("sub_institute_id");
        
		$data = DB::select("SELECT d.id,d.name,sdm.standard_id
							FROM std_div_map sdm 
							INNER JOIN standard s ON s.id =sdm.standard_id AND s.sub_institute_id = sdm.sub_institute_id
							INNER JOIN division d ON d.id = sdm.division_id AND d.sub_institute_id = sdm.sub_institute_id 
							WHERE sdm.sub_institute_id = '".$sub_institute_id."' 
							AND sdm.standard_id = '".$standard_id."' ");
        return $data; 
	}
}
