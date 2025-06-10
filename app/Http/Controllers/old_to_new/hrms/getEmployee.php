<?php
set_time_limit(0);
include("../../excel_upload/db.php");

$cn = mysqli_connect($host, $username, $password) or die("adsad");

mysqli_select_db($cn, $database) or die("database");
// mmis client id 2
$client_id = $_REQUEST['client_id'];
$sub_institute_id = $_REQUEST['sub_institute_id'];
$syear = $_REQUEST['syear'];
$message = 'client is - '.$client_id;
if($client_id != "")
{
	$getClients = mysqli_query($cn, "SELECT *,s.Id as sub_institute_id  
					FROM tblclient c 
					INNER JOIN school_setup s on c.id = s.client_id
					WHERE c.id = '" . $client_id . "'"
					);

	$final_result = array();

	while ($fetClients = mysqli_fetch_assoc($getClients)) 
	{

		$host = $fetClients['db_host'];
		$username = $fetClients['db_user'];
		$password = $fetClients['db_password'];
		$database = $fetClients['db_hrms'];
		$sub_institute_id = $fetClients['sub_institute_id'];
		
		if($host != "" && $username != "" && $password != "" && $database != "" && $sub_institute_id != "")
		{

			$cnOld = mysqli_connect($host, $username, $password) or die("adsad");

			mysqli_select_db($cnOld, $database) or die("database");

			$getOldStudent = mysqli_query($cnOld, 'SELECT a.*,d.id AS dep_id,d.title FROM hs_hr_employee a 
            inner JOIN hs_hr_compstructtree d ON d.id=a.work_station
            WHERE a.employee_id IN (148,138,139,125,141,153,152,353,157,140,151,155,146,158,149,69,142,415,143,410)');// limit 5
				// echo "<pre>";print_r($getOldStudent);exit;
			
			while ($fetOldStudent = mysqli_fetch_assoc($getOldStudent)) 
			{
                $first_name=isset($fetOldStudent['emp_firstname']) ? $fetOldStudent['emp_firstname'] : '-';
                $last_name=isset($fetOldStudent['emp_lastname']) ? $fetOldStudent['emp_lastname'] : '-';
                $middle_name=isset($fetOldStudent['emp_middlename']) ? $fetOldStudent['emp_middlename'] : '-';
                $user_name = $first_name.'.'.$last_name;
                $password = "staff";
                $email =isset($fetOldStudent['emp_oth_email']) ? $fetOldStudent['emp_oth_email'] : '';
                $gender = ($fetOldStudent['emp_gender']==1) ? 'M' : 'F';
                $mobile=isset($fetOldStudent['emp_mobile']) ? $fetOldStudent['emp_mobile'] : '';
                $dob=isset($fetOldStudent['emp_birthday']) ? $fetOldStudent['emp_birthday'] : '';
                $address=$fetOldStudent['emp_street1'].','.$fetOldStudent['emp_street2'];
                $city=isset($fetOldStudent['city_code']) ? $fetOldStudent['city_code'] : '';
                $state=isset($fetOldStudent['provin_code']) ? $fetOldStudent['provin_code'] : '';
                $pincode=isset($fetOldStudent['emp_zipcode']) ? $fetOldStudent['emp_zipcode'] : '';
                $user_profile = 25;
                $joined_date = $fetOldStudent['joined_date']; 
                $join_year = date('Y', strtotime($joined_date));
                $terminate_date=$fetOldStudent['terminated_date'];
                $getDepartments = mysqli_query($cn, "SELECT id FROM hrms_departments WHERE department = '" . $fetOldStudent['title'] . "'"
                );
                $depdata = mysqli_fetch_assoc($getDepartments);
                $department_id = $depdata['id'] ?? 0;
                $employee_no = $fetOldStudent['employee_id'];
                $probation_from = $fetOldStudent['probation_period_from'];
                $probation_to = $fetOldStudent['probation_period_to'];
                $notice_fromdate = $fetOldStudent['notice_fromdate'];
                $notice_todate = $fetOldStudent['notice_todate'];
                $openingleave = $fetOldStudent['openingleave'];
                $relieving_date = $fetOldStudent['relieving_date'];
                $relieving_reason = $fetOldStudent['relieving_reason'];
                $CL_opening_leave = $fetOldStudent['CL_opening_leave'];

			// echo $user_name.'-'.$dob.'<br>';
                $created_on = date("Y-m-d H:i:s");

				$insertQuery = "INSERT INTO `tbluser` (`user_name`, `password`, `name_suffix`, `first_name`, `middle_name`, `last_name`, `email`, `mobile`, `gender`, `birthdate`, `address`, `city`, `state`, `pincode`, `otp`, `user_profile_id`, `join_year`, `image`, `plain_password`, `sub_institute_id`, `client_id`, `is_admin`, `portal_user`, `status`, `last_login`, `login_ip`, `landmark`, `address_2`, `created_on`, `expire_date`, `total_lecture`, `subject_ids`, `bank_name`, `branch_name`, `amount`, `transfer_type`, `account_no`, `ifsc_code`, `jobtitle_id`, `department_id`, `employee_no`, `qualification`, `occupation`, `joined_date`, `probation_period_from`, `probation_period_to`, `terminated_date`, `termination_reason`, `notice_fromdate`, `notice_todate`, `noticereason`, `openingleave`, `relieving_date`, `relieving_reason`, `CL_opening_leave`, `supervisor_opt`, `employee_id`, `load`, `reporting_method`, `monday`, `tuesday`, `wednesday`, `thursday`, `friday`, `saturday`, `sunday`, `monday_in_date`, `monday_out_date`, `tuesday_in_date`, `tuesday_out_date`, `wednesday_in_date`, `wednesday_out_date`, `thursday_in_date`, `thursday_out_date`, `friday_in_date`, `friday_out_date`, `saturday_in_date`, `saturday_out_date`, `sunday_in_date`, `sunday_out_date`) VALUES ('$user_name', '$password', '', '$first_name', '$middle_name', '$last_name', '$email', '$mobile', '$gender', '$dob', '$address', '$city', '$state', '$pincode', NULL, 25, '$join_year', '-',NULL, 47, 2, NULL, NULL, 1, NULL, NULL, NULL, NULL,'$created_on',NULL, NULL, NULL, NULL, NULL, NULL,NULL, NULL, NULL, 0, $department_id, $employee_no, NULL,NULL, '$joined_date', '$probation_from', '$probation_to', '$terminate_date', NULL, '$notice_fromdate', '$notice_todate', NULL,'$openingleave', '$relieving_date', '$relieving_reason', '$CL_opening_leave', NULL, 0, NULL, 'Indirect', 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL)";

                $insert = mysqli_query($cn,$insertQuery);

                $final_result[]=["user_name"=>$user_name,
                                "employee_no"=>$employee_no,
                                "email"=>$email,
                                "mobile"=>$mobile];
			}
		}
		else
		{
			echo "<font color='red'>School Setup is missing</font>";
		}
	}
}
else
{
	$message = "No Client Id Found";
}

echo $message.'<br>';
echo "Inserted EMP Data <br>";
echo "<pre>";print_r($final_result);echo "</pre>";
?>