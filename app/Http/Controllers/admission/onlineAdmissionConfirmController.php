<?php

namespace App\Http\Controllers\admission;

use App\Http\Controllers\Controller;
use function App\Helpers\is_mobile;
use App\Models\student\tblstudentEnrollmentModel;
use App\Models\student\tblstudentModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class onlineAdmissionConfirmController extends Controller {
	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function index(Request $request) {
		$data = session()->all();
		// echo '<pre>';
		// print_r($data);
		// die;
		$type = $request->input('type');
		$submit = $request->input('submit');
		$sub_institute_id = $request->session()->get('sub_institute_id');
		$user_profile_name = $request->session()->get('user_profile_name');
		$syear = $request->session()->get('syear');

		if($user_profile_name == 'Principal'){
			$sql = "SELECT n.*,n.id AS CHECKBOX
			FROM new_admission_inquiry_registration n
			WHERE n.admin_status = 'Verified' AND n.sub_institute_id = '".$sub_institute_id."'"; //WHERE n.syear = '".$syear."'
		}else{
			$sql = "SELECT n.*,n.id AS CHECKBOX
			FROM new_admission_inquiry_registration n
			where n.sub_institute_id = '".$sub_institute_id."' "; //WHERE n.syear = '".$syear."'
		}

		 

		$result = DB::select($sql);

		$res['status_code'] = 1;
		$res['student_data'] = $result;
		$res['message'] = "Success";

		return is_mobile($type, "admission/show_online_admission_confirm", $res, "view");
	}

	/**
	 * Show the form for creating a new resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function create(Request $request) {
		$token_no = $request->input('token_no');
		$type = $request->input('type');
		$sub_institute_id = $request->session()->get('sub_institute_id');
		$syear = $request->session()->get('syear');
		$extra_query = '';


		if($token_no != '')
        {
            $extra_query .= " AND n.token = '".$token_no."' ";
        }

		// $sql = "SELECT n.*,n.id AS CHECKBOX,s.admission_token_no
		// 	FROM new_admission_inquiry_registration n
		// 	LEFT JOIN tblstudent s ON s.admission_id = n.id
		// 	WHERE 1=1 AND n.token = '".$token_no."' 
		// 	HAVING s.admission_token_no IS NULL"; //syear = '".$syear."' 

		$sql = "SELECT n.*,n.id AS CHECKBOX
			FROM new_admission_inquiry_registration n
			WHERE 1=1 AND n.sub_institute_id = '".$sub_institute_id."' 
			"; //AND n.syear = '".$syear."'

		$result = DB::select($sql.$extra_query);

		$res['status_code'] = 1;
		$res['message'] = "Success";
		$res['student_data'] = $result;
		$res['token_no'] = $token_no;
		return is_mobile($type, "admission/show_online_admission_confirm", $res, "view");
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @return \Illuminate\Http\Response
	 */
	public function store(Request $request) {
		// dd($request);
		$students = $request->get('students');
		$admin_status = $request->get('admin_status');
		$principal_status = $request->get('principal_status');
		$account_status = $request->get('account_status');
		$type = $request->get('type');
		$token_no = $request->get('token_no');
		$sub_institute_id = $request->session()->get('sub_institute_id');
		$syear = $request->session()->get('syear');
		$created_by = $request->session()->get('user_id');
        $created_on = date('Y-m-d');
        $created_ip = $_SERVER['REMOTE_ADDR'];

		foreach ($students as $key => $student_id) 
		{
			// echo '<pre>';
			// print_r($students);
			// print_r($admin_status);
			// print_r($principal_status);
			// print_r($account_status);
			// die;
			$res = array();
			if(isset($admin_status[$student_id]) && $admin_status[$student_id] != '')
			{
				$sql_update_admin = "UPDATE new_admission_inquiry_registration SET admin_status = '".$admin_status[$student_id]."' WHERE id = '".$student_id."' AND sub_institute_id = '".$sub_institute_id."'";

				$result_admin = DB::update($sql_update_admin);
				$res['status_code'] = "1";
				$res['message'] = "Admission Verified By Admin Successfully";
			}
			
			if(isset($principal_status[$student_id]) && $principal_status[$student_id] != '')
			{
				$sql_update_principal = "UPDATE new_admission_inquiry_registration SET principal_status = '".$principal_status[$student_id]."' WHERE id = '".$student_id."' AND sub_institute_id = '".$sub_institute_id."' ";

				$result_principal = DB::update($sql_update_principal);
				$res['status_code'] = "1";
				$res['message'] = "Admission Approved by Principal Successfully";
			}

			if(isset($account_status[$student_id]) && $account_status[$student_id] != '')
			{
				$sql_update_account = "UPDATE new_admission_inquiry_registration SET account_status = '".$account_status[$student_id]."' WHERE id = '".$student_id."' AND sub_institute_id = '".$sub_institute_id."' ";

				$result_account = DB::update($sql_update_account);

				
				if($account_status[$student_id] == 'Confirm' && $result_account == 1)
				{

					$students_check = tblstudentModel::select('admission_id')->where(['sub_institute_id' => $sub_institute_id,'admission_id' => $student_id,'admission_token_no' => $token_no])->get()->toArray();
	      			

					$enrollment_no_sql = "SELECT *,MAX(enrollment_no) as new_enrollment_no
										FROM tblstudent
										WHERE sub_institute_id = '".$sub_institute_id."' AND enrollment_no NOT LIKE '%SS%'";					

					$enrollment_result = DB::select($enrollment_no_sql);				
					$get_enrollment_no = substr($enrollment_result[0]->new_enrollment_no,2,6);
					$new_enrollment_no = $get_enrollment_no + 1; //$enrollment_result[0]->new_enrollment_no;
	      			
					// $enrollment_no_sql = "SELECT *,(MAX(CAST(enrollment_no AS int)) + 1) as new_enrollemnet_no
					// 					FROM tblstudent
					// 					WHERE sub_institute_id = '".$sub_institute_id."' and enrollment_no NOT LIKE '%PP%' AND enrollment_no NOT LIKE '%SS%'";

					// $enrollment_result = DB::select($enrollment_no_sql);
					// $new_enrollemnet_no = $enrollment_result[0]->new_enrollemnet_no;
					// echo '<pre>';
					// echo 'divya';
					// print_r($enrollment_result[0]->new_enrollemnet_no);
					// print_r($enrollment_result->new_enrollemnet_no);
					// die;					

			        if(count($students_check) == 0)
			        {
			        	$sql = "INSERT INTO tblstudent(admission_id,first_name,father_name,mother_name,gender,dob,mobile,mother_mobile,email,password,admission_year,admission_date,city,state,address,pincode,sub_institute_id,status,created_on,aadhar_document_upload,birth_certificate,place_of_birth,religion,cast,subcast,bloodgroup,father_dob,height,admission_token_no,enrollment_no)
						SELECT n.id,n.child_name,n.father_name,n.mother_name,n.gender,n.date_of_birth,n.mobile,n.mother_mobile_no,n.mail,MD5('student'),n.syear,CURDATE(),n.city,n.state,n.address,n.pin_code,'".$sub_institute_id."',1,Now(),n.student_adharcard,n.birth_certificate,n.birth_place,n.religion,n.cast,n.sub_cast,n.blood_group,n.father_dob,n.height,n.token,'PP".$new_enrollment_no."'
						FROM new_admission_inquiry_registration n 
						WHERE n.id = '".$student_id."' "; // OR token = '".$token_no."'
							
						$result = DB::insert($sql);
						$inserted_student_id = DB::getPdo()->lastInsertId();

						$insert_enrolnment = "INSERT INTO tblstudent_enrollment(syear,student_id,standard_id,sub_institute_id,grade_id,section_id,student_quota)
						SELECT n.syear,s.id,ss.id,s.sub_institute_id,ac.id,dd.id,sq.id 
						FROM new_admission_inquiry_registration n 
						INNER JOIN tblstudent s ON s.admission_id = n.id
						INNER JOIN standard ss ON ss.name = n.admission_std AND ss.sub_institute_id = s.sub_institute_id 
						INNER JOIN academic_section ac ON ac.id = ss.grade_id and ac.sub_institute_id = s.sub_institute_id
						INNER JOIN division dd on dd.sub_institute_id = s.sub_institute_id
						INNER JOIN student_quota sq ON sq.sub_institute_id = s.sub_institute_id
						WHERE n.id = '".$student_id."' and s.admission_id = '".$student_id."' 
						limit 1";	
						// die;
						$result_enrollment = DB::insert($insert_enrolnment);

						$checkQuery = "SELECT s.first_name,s.id AS student_id,n.birth_certificate,n.student_adharcard,
						n.student_cast_certificate,n.father_cast_certificate,n.student_passport_size_photo,n.family_photo,n.vaccination_record,
						n.medical_examination_report,n.father_adharcard,n.mother_adharcard,n.address_proof,
						n.father_signature,n.mother_signature,n.any_other_doc,n.other_doc 
						FROM tblstudent s 
						INNER JOIN new_admission_inquiry_registration n ON n.token = s.admission_token_no
						WHERE s.id = '".$inserted_student_id."' AND s.admission_token_no IS NOT NULL";

						$result = DB::select($checkQuery); 						

						$result = $result[0];   
						// echo $result[0]->first_name;                
						// dd($result);  
						if(isset($result) > 0)
						{		
						  	$return_array	= array();
						    if($result->birth_certificate != "")
						    {
						        $return_array[$inserted_student_id][] = $this->insert_document($inserted_student_id,'Birth Certificate',$result->birth_certificate,'3');
						    }
						    if($result->student_adharcard != "")
						    {
						        $return_array[$inserted_student_id][] = $this->insert_document($inserted_student_id,'Student Adharcard',$result->student_adharcard,'2');
						    }
						    if($result->student_cast_certificate != "")
						    {
						        $return_array[$inserted_student_id][] = $this->insert_document($inserted_student_id,'Student Cast Certificate',$result->student_cast_certificate,'11');
						    }
						    if($result->father_cast_certificate != "")
						    {
						        $return_array[$inserted_student_id][] = $this->insert_document($inserted_student_id,'Father Cast Certificate',$result->father_cast_certificate,'30');
						    }
						    if($result->student_passport_size_photo != "")
						    {
						        $return_array[$inserted_student_id][] = $this->insert_document($inserted_student_id,'Student Passport Size Photo',$result->student_passport_size_photo,'43');
						    }
						    if($result->family_photo != "")
						    {
						        $return_array[$inserted_student_id][] = $this->insert_document($inserted_student_id,'Family Photo',$result->family_photo,'8');
						    }
						    if($result->vaccination_record != "")
						    {
						        $return_array[$inserted_student_id][] = $this->insert_document($inserted_student_id,'Vaccination Record',$result->vaccination_record,'7');
						    }
						    if($result->medical_examination_report != "")
						    {
						        $return_array[$inserted_student_id][] = $this->insert_document($inserted_student_id,'Medical Examination Report',$result->medical_examination_report,'6');
						    }
						    if($result->father_adharcard != "")
						    {
						        $return_array[$inserted_student_id][] = $this->insert_document($inserted_student_id,'Father Adharcard',$result->father_adharcard,'25');
						    }
						    if($result->mother_adharcard != "")
						    {
						        $return_array[$inserted_student_id][] = $this->insert_document($inserted_student_id,'Mother Adharcard',$result->mother_adharcard,'26');
						    }
						    if($result->address_proof != "")
						    {
						        $return_array[$inserted_student_id][] = $this->insert_document($inserted_student_id,'Address Proof',$result->address_proof,'10');
						    }
						    if($result->father_signature != "")
						    {
						        $return_array[$inserted_student_id][] = $this->insert_document($inserted_student_id,'Father Signature',$result->father_signature,'44');
						    }
						    if($result->mother_signature != "")
						    {
						        $return_array[$inserted_student_id][] = $this->insert_document($inserted_student_id,'Mother Signature',$result->mother_signature,'45');
						    }
						    if($result->any_other_doc != "")
						    {
						        $return_array[$inserted_student_id][] = $this->insert_document($inserted_student_id,'Any Other Doc',$result->any_other_doc,'46');
						    }
						    if($result->other_doc != "")
						    {
						        $return_array[$inserted_student_id][] = $this->insert_document($inserted_student_id,'Other Doc',$result->other_doc,'47');
						    }
						}						

						$res['status_code'] = "1";
						$res['message'] = "Admission Confirm by Accounted Successfully";

			        }else{
			        	$res['status_code'] = "0";
						$res['message'] = "Admission Already Confirmed";
			        }
				}
			}
	    }

		return is_mobile($type, "admission/show_online_admission_confirm", $res,"view");
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

	public function onlineAdmissionReport(Request $request) {
		$type = $request->input('type');
		$sub_institute_id = $request->session()->get('sub_institute_id');
		$syear = $request->session()->get('syear');

		$query = "SELECT *,id AS CHECKBOX FROM new_admission_inquiry_registration WHERE syear = '" . $syear . "' AND sub_institute_id = '".$sub_institute_id."' ";

		$result = DB::select($query);

		$result = array_map(function ($value) {
			return (array) $value;
		}, $result);
		// dd($result);
		$res['status_code'] = 1;
		$res['message'] = "Success";
		$res['report_data'] = $result;

		return is_mobile($type, "admission/show_online_admission_report", $res, "view");
	}

	public function ajax_AdmissionConfirmReport(Request $request)
    {          
        $type = $request->get('type');
		$sub_institute_id = $request->session()->get('sub_institute_id');
		$syear = $request->session()->get('syear');
		$students = $request->get('students');

		foreach ($students as $key => $student_id) 
		{
			DB::statement("UPDATE new_admission_inquiry_registration SET eligible_status = 'Yes' WHERE id = '".$student_id."' AND sub_institute_id = '".$sub_institute_id."' ");
		}
		$res['status_code'] = "1";
		$res['message'] = "Student Eligibility Update Successfully";
		
		return is_mobile($type, "admission/show_online_admission_report", $res, "view");
    }

    public function insert_document($student_id,$document_title,$file_name,$document_type_id)
	{

	    $records_uploaded = $records_not_uploaded = 0;
	    $problematic_reocords = "";
	    $return_array = array();

	    $checkQuery = "SELECT * FROM tblstudent_document WHERE student_id = '".$student_id."' AND document_type_id = '".$document_type_id."'";
	    $checkresult = DB::select($checkQuery);

	    if(count($checkresult) == 0)
	    {
	        $insertQuery = "INSERT INTO tblstudent_document(student_id,document_type_id,document_title,file_name,sub_institute_id,created_on) 
	        values('".$student_id."','".$document_type_id."','".$document_title."','".$file_name."',47,now())";
	        
	        $result = DB::insert($insertQuery);
	        if($result)
	        {
	          $records_uploaded++;
	        }
	        else{
	          $records_not_uploaded++;
	          $problematic_reocords .= "Student ID -  ".$studnet_id." Document Title -  ".$document_title."File Name -  ".$file_name."<br><br>"; 
	        }
	        $return_array['PROBLEMATIC_RECORDS'] = $problematic_reocords;
	    }
	    

	    return $return_array;     
	}
}
