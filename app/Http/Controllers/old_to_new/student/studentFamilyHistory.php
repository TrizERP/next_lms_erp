<?php
// created date 17-04-2025

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
		$database = $fetClients['db_solution'];
		$sub_institute_id = $fetClients['sub_institute_id'];
		
		if($host != "" && $username != "" && $password != "" && $database != "" && $sub_institute_id != "")
		{

			$cnOld = mysqli_connect($host, $username, $password) or die("adsad");

			mysqli_select_db($cnOld, $database) or die("database");

			$getOldStudent = mysqli_query($cnOld, 'SELECT b.student_id,b.enrollment_no,a.* 
                FROM mmiserp_cms.STUDENT_FAMILY_HISTORY AS a
                INNER JOIN mmiserp_solution.tblstudent AS b ON b.student_id=a.student_id
                GROUP BY a.id');// limit 5
			// for status us 

			$grand_total = $grand_success = $grand_failure = 0;
			while ($fetOldStudent = mysqli_fetch_assoc($getOldStudent)) 
			{
                $getNewStudent = mysqli_query($cn, 'SELECT id,enrollment_no,uniqueid FROM tblstudent WHERE sub_institute_id='.$sub_institute_id);// limit 5
                // echo mysqli_error($cn);
                while ($fetNewStudent = mysqli_fetch_assoc($getNewStudent)) 
                {
					if($fetNewStudent['enrollment_no']==$fetOldStudent['enrollment_no']){
						$currentDate = date("Y-m-d H:i:s");
                        $currentStudentID = $fetNewStudent['id'];
						$currentEnrollmentNo = $fetNewStudent['enrollment_no'];
						// INSERT INTO `STUDENT_FAMILY_HISTORY` (`student_id`, `enrollment_no`, `id`, `student_id`, `syear`, `marking_period_id`, `staff_id`, `entry_date`, `name`, `institute_name`, `course`, `year_of_passing`, `percentage`, `relation_with_student`, `father_DOB`, `mother_DOB`, `father_blood_group`, `mother_blood_group`, `father_occupation`, `mother_occupation`, `father_company_name`, `mother_company_name`, `father_office_address`, `mother_office_address`, `father_annual_income`, `mother_annual_income`, `local_guardian_address`, `contact_no`

						$family_member_name = mysqli_real_escape_string($cn, $fetOldStudent['name']);
						$institute_name = mysqli_real_escape_string($cn, $fetOldStudent['institute_name']);
						$course = mysqli_real_escape_string($cn, $fetOldStudent['course']);
						$year = mysqli_real_escape_string($cn, $fetOldStudent['year_of_passing']);
						$percentage = mysqli_real_escape_string($cn, $fetOldStudent['percentage']);
						$relation_with_student = mysqli_real_escape_string($cn, $fetOldStudent['relation_with_student']);
						$annual_income = isset($fetOldStudent['father_annual_income']) && !empty($fetOldStudent['father_annual_income']) 
							? mysqli_real_escape_string($cn, $fetOldStudent['father_annual_income']) 
							: mysqli_real_escape_string($cn, $fetOldStudent['mother_annual_income']);
						$birthdate =  isset($fetOldStudent['father_DOB']) && !empty($fetOldStudent['father_DOB']) 
							? mysqli_real_escape_string($cn, $fetOldStudent['father_DOB']) 
							: mysqli_real_escape_string($cn, $fetOldStudent['mother_DOB']);
						$blood_group = isset($fetOldStudent['father_blood_group']) && !empty($fetOldStudent['father_blood_group']) 
							? mysqli_real_escape_string($cn, $fetOldStudent['father_blood_group']) 
							: mysqli_real_escape_string($cn, $fetOldStudent['mother_blood_group']);
						$designation = isset($fetOldStudent['father_occupation']) && !empty($fetOldStudent['father_occupation']) 
							? mysqli_real_escape_string($cn, $fetOldStudent['father_occupation']) 
							: mysqli_real_escape_string($cn, $fetOldStudent['mother_occupation']);
						$company_name = isset($fetOldStudent['father_company_name']) && !empty($fetOldStudent['father_company_name']) 
							? mysqli_real_escape_string($cn, $fetOldStudent['father_company_name']) 
							: mysqli_real_escape_string($cn, $fetOldStudent['mother_company_name']);
						$office_address = isset($fetOldStudent['father_office_address']) && !empty($fetOldStudent['father_office_address']) 
							? mysqli_real_escape_string($cn, $fetOldStudent['father_office_address']) 
							: mysqli_real_escape_string($cn, $fetOldStudent['mother_office_address']);
						$mobile_no = mysqli_real_escape_string($cn, $fetOldStudent['contact_no']);
						$guardian_address = mysqli_real_escape_string($cn, $fetOldStudent['local_guardian_address']);
						$created_on = date("Y-m-d H:i:s");

						// check data already Exists or not 

						$checkData = mysqli_query($cn, "SELECT * FROM `tblstudent_family_history` 
							WHERE student_id = '" . $currentStudentID . "' 
							AND name = '" . $family_member_name . "' 
							AND institute_name = '" . $institute_name . "' 
							AND course = '" . $course . "' 
							AND year = '" . $year . "' 
							AND percentage = '" . $percentage . "' 
							AND relation_with_student = '" . $relation_with_student . "' 
							AND annual_income = '" . $annual_income . "' 
							AND birthdate = '" . $birthdate . "' 
							AND blood_group = '" . $blood_group . "' 
							AND designation = '" . $designation . "' 
							AND company_name = '" . $company_name . "' 
							AND office_address = '" . $office_address . "' 
							AND mobile_no = '" . $mobile_no . "' 
							AND guardian_address = '" . $guardian_address . "'
							AND sub_institute_id = '".$sub_institute_id."'");

							if(mysqli_num_rows($checkData) == 0) {
								$insert = mysqli_query($cn, "INSERT INTO `tblstudent_family_history` 
								(`student_id`, `name`, `institute_name`, `course`, `year`, `percentage`, `relation_with_student`, `annual_income`, `birthdate`, `blood_group`, `designation`, `company_name`, `office_address`, `mobile_no`, `guardian_address`, `sub_institute_id`, `created_on`) 
								VALUES 
								('$currentStudentID', '$family_member_name', '$institute_name', '$course', '$year', '$percentage', '$relation_with_student', '$annual_income', '$birthdate', '$blood_group', '$designation', '$company_name', '$office_address', '$mobile_no', '$guardian_address', '$sub_institute_id', '$created_on')");

								$final_result[] = [
									$currentStudentID, $family_member_name, $institute_name, $course, $year, $percentage, $relation_with_student, $annual_income, $birthdate, $blood_group, $designation, $company_name, $office_address, $mobile_no, $guardian_address, $sub_institute_id, $created_on
								];
							}

					}
                }
			}
		}
		else
		{
			echo "<font color='red'>School Setup is missing</font>";
		}
	}
    // exit;
}
else
{
	$message = "No Client Id Found";
}

echo $message.'<br>';
echo 'total updated data'.count($final_result).'<br>';
echo "Updated Students Id's : <br>";
echo "<pre>";print_r($final_result);echo "</pre>";
?>