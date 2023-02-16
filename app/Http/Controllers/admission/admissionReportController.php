<?php

namespace App\Http\Controllers\admission;

use App\Http\Controllers\Controller;
use App\Models\admission\admissionRegistrationModel;
use App\Models\admission\admissionEnquiryModel;
use App\Models\school_setup\standardModel;
use App\Models\student\tblstudentEnrollmentModel;
use App\Models\student\tblstudentModel;
use function App\Helpers\is_mobile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class admissionReportController extends Controller {

	public function enquiryReport(Request $request){
		$sub_institute_id = $request->session()->get('sub_institute_id');
		$syear = $request->session()->get('syear');
		$type = $request->input('type');
		$report = $request->input('report');
		$from_date = $request->input('from_date');
		$to_date = $request->input('to_date');
		$standard = $request->input('standard');
		$user = $request->input('user');

		// get All User
		// $users = DB::table('tbluser')->where('status', '!=', '0')->get();

		// get only assign users
		// DB::enableQueryLog();
		// $users = DB::table('tbluser')
		// 	->whereIn( 'id', DB::select("select distinct(created_by) from admission_registration") )
		// 	->get();
		$users = DB::select("select * from tbluser where sub_institute_id = '".$sub_institute_id."' and id IN ( select distinct(created_by) from admission_registration) ");
		// dd( $users );

		if(isset($report))
		{

			$extra = '';
			if($sub_institute_id == 201 || $sub_institute_id == 202 || $sub_institute_id == 203 || $sub_institute_id == 204) 
			// for re-print fees_circular (hillshigh school)
			{
				$extra = ",ai.id,ai.admission_fees,ai.fees_amount,ai.fees_remark,ai.fees_circular_html as fees_circular ";
			}

			
			$getQuery = "SELECT ai.enquiry_no, ai.created_on,ai.followup_date, ai.first_name, ai.middle_name, ai.last_name, ai.gender, ai.mobile, 
			ai.email, ai.address, ai.date_of_birth, ai.age, ai.syear, ai.previous_school_name,ai.previous_standard,s.name as admission_standard, ai.remarks,fu.status as enquiry_status, 
			ai.source_of_enquiry, ai.created_by, ai.counciler_name, ai.father_name,CONCAT_WS(' ',ts.first_name,ts.last_name) AS created_by, cs.caste_name $extra
			FROM admission_enquiry ai 
			INNER JOIN tbluser ts ON ts.id = ai.created_by AND ts.sub_institute_id = ai.sub_institute_id
			LEFT JOIN caste cs ON cs.id = ai.category
			LEFT JOIN follow_up fu ON fu.enquiry_id = ai.id AND fu.sub_institute_id = ai.sub_institute_id
			INNER JOIN standard s ON s.id = ai.admission_standard AND s.sub_institute_id = ai.sub_institute_id
			WHERE (DATE_FORMAT(ai.created_on, '%Y-%m-%d') BETWEEN '".$from_date."' AND '".$to_date."') AND ai.sub_institute_id = '".$sub_institute_id."' AND ai.syear = '".$syear."'";
			

			if($standard != '')
			{
				$getQuery .= " AND admission_standard = '".$standard."'";
			}

			if ( $user ) {
				$getQuery .= " AND ai.created_by = '".$user."'";
			}

			$data = DB::select($getQuery);

			$data = array_map(function ($value) {
				return (array) $value;
			}, $data);

/*echo "<pre>";
print_r($data);
die();*/

			if(count($data) > 0)
			{
				$headers = array_keys($data['0']);
				$res['headers'] = $headers;
				$res['data'] = $data;
				$res['from_date'] = $from_date;
				$res['to_date'] = $to_date;
				$res['user'] = $user;
			}else{
				$res['status_code'] = 0;
				$res['message'] = "Please revise your search. No data found.";
				// dd($res);	

				return is_mobile($type, "admission_enquiry_report", $res);
			}
			
		}

		$res['status_code'] = 1;	
		$res['message'] = "Success";
		$res['users'] = $users;
		$res['ser_user'] = $user;
		//dd($res);	

		return is_mobile($type, "admission.report.show_enquiry_report", $res, 'view');
	}

	public function formReport(Request $request){
		$sub_institute_id = $request->session()->get('sub_institute_id');
		$syear = $request->session()->get('syear');
		$type = $request->input('type');
		$report = $request->input('report');
		$from_date = $request->input('from_date');
		$to_date = $request->input('to_date');
		$standard = $request->input('standard');
		$status = $request->input('status');
		$dynamicFields = $request->input('dynamicFields');

		$formFields = DB::select("DESC admission_form");

		$formFields = array_map(function ($value) {
				return (array) $value;
			}, $formFields);

		$reportFields = array();

		$dFields[0] = "first_name";
		$dFields[1] = "middle_name";
		$dFields[2] = "last_name";
		$dFields[3] = "mobile";
		$dFields[4] = "email";

		if($dynamicFields == '')
		{
			$dynamicFields = $dFields;
		}else{
			$dynamicFields = array_merge($dFields, $dynamicFields);
		}

		foreach($formFields as $key => $value)
		{
			$reportFields[$value['Field']] = ucfirst(str_replace("_", " ", $value['Field']));
		}

		if(isset($report))
		{

			if($dynamicFields == '')
			{
				$res['status_code'] = 0;
				$res['message'] = "Please select fields to view report";
				

				return is_mobile($type, "admission_form_report", $res);
			}

			$getQuery = "SELECT ar.*, ae.admission_standard, ae.first_name, ae.middle_name, ae.last_name, ae.mobile, ae.email 
			FROM admission_form ar
			INNER JOIN admission_enquiry ae ON ar.enquiry_id = ae.id 
			WHERE DATE_FORMAT(ar.created_on, '%Y-%m-%d') BETWEEN '".$from_date."' AND '".$to_date."' AND ae.sub_institute_id = '".$sub_institute_id."' AND ae.syear = '".$syear."'";

			

			if($standard != '')
			{
				$getQuery .= " AND ae.admission_standard = '".$standard."'";
			}

			if($status != '')
			{
				$getQuery .= " AND ar.status = '".$status."'";	
			}

			$data = DB::select($getQuery);

			$data = array_map(function ($value) {
				return (array) $value;
			}, $data);

			if(count($data) > 0)
			{

				$headers = $dynamicFields;
				$res['headers'] = $headers;
				$res['data'] = $data;
				$res['from_date'] = $from_date;
				$res['to_date'] = $to_date;
				if($status != '')
				{
					$res['status'] = $status;
				}
				if($standard != '')
				{
					$res['standard'] = $standard;
				}
			}else{
				$res['status_code'] = 0;
				$res['message'] = "Please revise your search. No data found.";
				

				return is_mobile($type, "admission_form_report", $res);
			}
			
		}

		$res['status_code'] = 1;	
		$res['message'] = "Success";
		$res['fields'] = $reportFields;
		

		return is_mobile($type, "admission.report.show_form_report", $res, 'view');
	}

	public function regReport(Request $request){
		$sub_institute_id = $request->session()->get('sub_institute_id');
		$syear = $request->session()->get('syear');
		$type = $request->input('type');
		$report = $request->input('report');
		$from_date = $request->input('from_date');
		$to_date = $request->input('to_date');
		$standard = $request->input('standard');
		$status = $request->input('status');
		$dynamicFields = $request->input('dynamicFields');

		$formFields = DB::select("DESC admission_registration");

		$formFields = array_map(function ($value) {
				return (array) $value;
			}, $formFields);

		$reportFields = array();

		$dFields[0] = "first_name";
		$dFields[1] = "middle_name";
		$dFields[2] = "last_name";
		$dFields[3] = "mobile";
		$dFields[4] = "email";

		if($dynamicFields == '')
		{
			$dynamicFields = $dFields;
		}else{
			$dynamicFields = array_merge($dFields, $dynamicFields);
		}

		foreach($formFields as $key => $value)
		{
			$reportFields[$value['Field']] = ucfirst(str_replace("_", " ", $value['Field']));
		}

		if(isset($report))
		{
			$getQuery = "SELECT ar.*, ae.admission_standard, ae.first_name, ae.middle_name, ae.last_name, ae.mobile, ae.email
			 FROM admission_registration ar
			  INNER JOIN admission_enquiry ae ON ar.enquiry_id = ae.id 
			WHERE DATE_FORMAT(ar.created_on, '%Y-%m-%d') BETWEEN '".$from_date."' AND '".$to_date."' AND ae.sub_institute_id = '".$sub_institute_id."'
			AND ae.syear = '".$syear."'";

			if($standard != '')
			{
				$getQuery .= " AND ae.admission_standard = '".$standard."'";
			}

			if($status != '')
			{
				$getQuery .= " AND ar.status = '".$status."'";	
			}

			$data = DB::select($getQuery);

			$data = array_map(function ($value) {
				return (array) $value;
			}, $data);

			if(count($data) > 0)
			{
				// $headers = array_keys($data['0']);
				$headers = $dynamicFields;
				$res['headers'] = $headers;
				$res['data'] = $data;
				$res['from_date'] = $from_date;
				$res['to_date'] = $to_date;
				if($status != '')
				{
					$res['status'] = $status;
				}
				if($standard != '')
				{
					$res['standard'] = $standard;
				}
			}else{
				$res['status_code'] = 0;
				$res['message'] = "Please revise your search. No data found.";
				// dd($res);	

				return is_mobile($type, "admission_reg_report", $res);
			}
			
		}

		$res['status_code'] = 1;	
		$res['message'] = "Success";
		$res['fields'] = $reportFields;
		// dd($res);	

		return is_mobile($type, "admission.report.show_reg_report", $res, 'view');
	}

	public function conReport(Request $request){
		$sub_institute_id = $request->session()->get('sub_institute_id');
		$syear = $request->session()->get('syear');
		$type = $request->input('type');
		$report = $request->input('report');
		$from_date = $request->input('from_date');
		$to_date = $request->input('to_date');
		$standard = $request->input('standard');
		$status = $request->input('status');
		$dynamicFields = $request->input('dynamicFields');

		$formFields = DB::select("DESC admission_registration");

		$formFields = array_map(function ($value) {
				return (array) $value;
			}, $formFields);

		$reportFields = array();

		$dFields[0] = "first_name";
		$dFields[1] = "middle_name";
		$dFields[2] = "last_name";
		$dFields[3] = "mobile";
		$dFields[4] = "email";

		if($dynamicFields == '')
		{
			$dynamicFields = $dFields;
		}else{
			$dynamicFields = array_merge($dFields, $dynamicFields);
		}

		foreach($formFields as $key => $value)
		{
			$reportFields[$value['Field']] = ucfirst(str_replace("_", " ", $value['Field']));
		}

		if(isset($report))
		{	

			$getQuery = "SELECT  ai.enquiry_no,ai.first_name, ai.middle_name, ai.last_name, ai.gender, 
						ai.mobile, ai.email,s.name AS admission_standard,d.name AS div_name,sq.title AS stu_quota,
						ar.place_of_birth,ar.enrollment_no,ar.payment_mode,ar.bank_name,ar.bank_branch,ar.cheque_no,
						ar.cheque_date,bg.bloodgroup,ar.aadhar_number,ar.mother_name,ar.mother_mobile_number,
						ar.admission_date,ar.admission_division,ar.remarks,ar.followup_date,ar.`status`,
						ar.admission_status,ar.date_of_payment,
						ai.created_on,ai.address, ai.date_of_birth, ai.age, ai.syear, ai.previous_school_name,
						ai.previous_standard,ai.source_of_enquiry,ai.father_name, CONCAT_WS(' ',ts.first_name,ts.last_name) AS created_by
						FROM admission_registration ar
						INNER JOIN admission_enquiry ai ON ar.enquiry_id = ai.id
						INNER JOIN tbluser ts ON ts.id = ar.created_by AND ts.sub_institute_id = ai.sub_institute_id
						INNER JOIN standard s ON s.id = ai.admission_standard AND s.sub_institute_id = ai.sub_institute_id
						INNER JOIN std_div_map sd ON sd.standard_id = ai.admission_standard AND sd.sub_institute_id = ai.sub_institute_id
						INNER JOIN division d ON d.id = sd.division_id AND d.sub_institute_id = sd.sub_institute_id
						LEFT JOIN student_quota sq ON sq.id = ar.student_quota AND sq.sub_institute_id = ar.sub_institute_id
						LEFT JOIN blood_group bg ON bg.id = ar.blood_group
						WHERE ai.sub_institute_id = '".$sub_institute_id."' AND ai.syear = '".$syear."' ";

			if($from_date != '' && $to_date != '')
			{
				$getQuery .= " AND DATE_FORMAT(ar.created_on, '%Y-%m-%d') BETWEEN '".$from_date."' AND '".$to_date."' ";
			} 
			if($standard != '')
			{
				$getQuery .= " AND ai.admission_standard = '".$standard."'";
			}
			if($status != '')
			{
				$getQuery .= " AND ar.admission_status = '".$status."'";	
			}
			$getQuery .= " GROUP BY ar.id";	
			$data = DB::select($getQuery);

			$data = array_map(function ($value) {
				return (array) $value;
			}, $data);

			if(count($data) > 0)
			{

				$headers = array_keys($data['0']);
				$res['headers'] = $headers;
				// $headers = $dynamicFields;
				// $res['headers'] = $headers;
				$res['data'] = $data;
				$res['from_date'] = $from_date;
				$res['to_date'] = $to_date;
				if($status != '')
				{
					$res['status'] = $status;
				}
				if($standard != '')
				{
					$res['standard'] = $standard;
				}
			}else{
				$res['status_code'] = 0;
				$res['message'] = "Please revise your search. No data found.";
				// dd($res);	

				return is_mobile($type, "admission_confirmation_report", $res);
			}
			
		}

		$res['status_code'] = 1;	
		$res['message'] = "Success";
		$res['fields'] = $reportFields;
		// dd($res);	

		return is_mobile($type, "admission.report.show_con_report", $res, 'view');
	}

	public function followUpReport(Request $request){
		$sub_institute_id = $request->session()->get('sub_institute_id');
		$syear = $request->session()->get('syear');
		$type = $request->input('type');
		$report = $request->input('report');
		$from_date = $request->input('from_date');
		$to_date = $request->input('to_date');
		$follow_up_status = $request->input('follow_up_status');

		if(isset($report))
		{
			$having_condition = '';
			if($follow_up_status == 'Followed')
			{
				$having_condition = "HAVING fu.remarks != '' ";
			}

			if($follow_up_status == 'Unfollowed')
			{
				$having_condition = "HAVING fu.remarks = '' ";
			}

			$getQuery = "SELECT ae.id AS enquiry_id,ae.enquiry_no,DATE_FORMAT(ae.created_on,'%d-%m-%Y') AS enquiry_date,
						CONCAT_WS(' ',ae.first_name,ae.middle_name,ae.last_name) AS student_name,
						IFNULL(ae.middle_name,ae.father_name) AS father_name,
						ae.previous_school_name,st.name AS admission_std,ae.address,ae.mobile,ae.source_of_enquiry,
						DATE_FORMAT(fu.follow_up_date,'%d-%m-%Y') as follow_up_date,
						fu.remarks AS followup_remark,ae.email
						FROM admission_enquiry ae
						INNER JOIN follow_up fu ON fu.enquiry_id = ae.id AND fu.sub_institute_id = ae.sub_institute_id AND fu.module_type = 'enquiry'
						INNER JOIN standard st ON st.id = ae.admission_standard AND st.sub_institute_id = ae.sub_institute_id
						WHERE fu.sub_institute_id = '".$sub_institute_id."' AND ae.syear = '".$syear."' AND DATE_FORMAT(fu.created_on,'%Y-%m-%d') 
						BETWEEN '".$from_date."' AND '".$to_date."'
						GROUP BY fu.id,fu.remarks
						$having_condition
						ORDER BY ae.id";

			$data = DB::select($getQuery);

			$data = array_map(function ($value) {
				return (array) $value;
			}, $data);      

			if(count($data) > 0)
			{
				// $headers = array_keys($data['0']);
				// $res['headers'] = $headers;
				$res['data'] = $data;
				$res['from_date'] = $from_date;
				$res['to_date'] = $to_date;
			}else{
				$res['status_code'] = 0;
				$res['message'] = "Please revise your search. No data found.";
				// dd($res);	

				return is_mobile($type, "admission_enquiry_followup_report", $res);
			}
			
		}

		$res['status_code'] = 1;	
		$res['message'] = "Success";
		// dd($res);	

		return is_mobile($type, "admission.report.show_enquiry_followup_report", $res, 'view');
	}
}
