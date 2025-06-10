<?php
set_time_limit(0);
include("../../excel_upload/db.php");

$cn = mysqli_connect($host, $username, $password) or die("adsad");

mysqli_select_db($cn, $database) or die("database");
// mmis client id 2
$client_id = $_REQUEST['client_id'];
$sub_institute_id = $_REQUEST['sub_institute_id'];
$syear = $_REQUEST['syear'];
// $leave_date=$_REQUEST['date'];
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

			$getOldStudent = mysqli_query($cnOld, 'SELECT a.employee_id,s.employee_id as user_code,s.emp_mobile AS mobile,s.emp_work_email AS email,l.leave_type_name,a.leave_type_id,a.leave_length_days,a.leave_date,a.HR_Comments,a.leave_comments,a.approved_user_id,a.leave_status
			FROM hs_hr_leave a
			INNER JOIN hs_hr_employee s ON s.emp_number = a.employee_id
			INNER JOIN hs_hr_leavetype l ON l.leave_type_id=a.leave_type_id');// limit 5

			// echo "<pre>";print_r($getOldStudent);
			// for status us 
			$status_arr = [
				"1"=>"pending",
				"-1"=>"approved_lwp",
				"3"=>"approved",
				"0"=>"cancelled",
				"99"=>"rejected"
			];
			$grand_total = $grand_success = $grand_failure = 0;
			while ($fetOldStudent = mysqli_fetch_assoc($getOldStudent)) 
			{
			// echo "<pre>";print_r($fetOldStudent);

				$leave_status = $status_arr[$fetOldStudent['leave_status']] ?? 'approved';
				// get leave type id from new db
				$getLeaveType = mysqli_query($cn, "SELECT id FROM hrms_leave_types WHERE leave_type = '{$fetOldStudent['leave_type_name']}'");
				$leaveTypeId = mysqli_fetch_assoc($getLeaveType);
				// echo "<pre>";print_r($fetOldStudent);

				if(isset($leaveTypeId['id'])){
				
				// get emp id from new db
				$getEmp = mysqli_query($cn,"SELECT id FROM tbluser WHERE employee_no='".$fetOldStudent['user_code']."' and status = 1 ");
				$empId = mysqli_fetch_assoc($getEmp);
				// echo "<pre>";print_r($getEmp);

				if($empId && $empId['id']!=0){
					$currentDate = date("Y-m-d H:i:s");
					$leaveId=$leaveTypeId['id'] ?? 0;
					// insert
					// $insert = "INSERT INTO hrms_emp_leaves (sub_institute_id,department_id,user_id,leave_type_id,day_type,slot,from_date,to_date,comment,hod_comment,hod_comment_date,hr_remarks,hr_remark_date,approved_by,status,created_at)
					// values ('".$sub_institute_id."',1,'".$empId['id']."','".$leaveId."',1,'','".$fetOldStudent['leave_date']."','".$fetOldStudent['leave_date']."','".$fetOldStudent['HR_Comments']."','','','".$fetOldStudent['HR_Comments']."','','admin','".$leave_status."','".$currentDate."')";

					// $insertData = mysqli_query($cn, $insert);
						
					$leaveComments = mysqli_real_escape_string($cn, $fetOldStudent['leave_comments']);
					$hrComments = mysqli_real_escape_string($cn, $fetOldStudent['HR_Comments']);

					// $insert = "INSERT INTO hrms_emp_leaves (sub_institute_id, department_id, user_id, leave_type_id, day_type, slot, from_date, to_date, comment, hod_comment, hod_comment_date, hr_remarks, hr_remark_date, approved_by, status, created_at)
					// 		VALUES ('$sub_institute_id', 1, '{$empId['id']}', '$leaveId', '{$fetOldStudent['leave_length_days']}', '', '{$fetOldStudent['leave_date']}', '{$fetOldStudent['leave_date']}', '$leaveComments', '', '', '$hrComments', '', 'admin', '$leave_status', '$currentDate')";
					
					// $insertData = mysqli_query($cn, $insert);
										
					$final_result[] = array(
						"current_id"=> $empId['id'],
						"email"=>$fetOldStudent['email'],
						"mobile"=>$fetOldStudent['mobile'],
					);
				}
			}

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
echo "<pre>";count($final_result);echo "</pre>";
echo "<pre>";print_r($final_result);echo "</pre>";
?>