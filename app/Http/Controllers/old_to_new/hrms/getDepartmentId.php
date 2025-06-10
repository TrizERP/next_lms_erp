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

			$getOldStudent = mysqli_query($cnOld, 'SELECT a.emp_number,a.employee_id AS user_code,a.emp_firstname AS first_name,a.emp_lastname AS last_name,
            a.emp_mobile AS mobile,a.work_station,d.id AS dep_id,d.title
            FROM hs_hr_employee a
            INNER JOIN hs_hr_compstructtree d ON d.id=a.work_station');// limit 5
			
			while ($fetOldStudent = mysqli_fetch_assoc($getOldStudent)) 
			{
				// echo "<pre>";print_r($fetOldStudent['emp_code']);
				// get emp id from new db
				$getEmp = mysqli_query($cn,"SELECT id FROM tbluser WHERE employee_no='".$fetOldStudent['user_code']."' and first_name='".$fetOldStudent['first_name']."' and status = 1 ");
				$empId = mysqli_fetch_assoc($getEmp);
				// echo "<pre>";print_r($empId['id']);exit;
                $getDepartments = mysqli_query($cn, "SELECT id FROM hrms_departments WHERE department = '" . $fetOldStudent['title'] . "'");
                $depdata = mysqli_fetch_assoc($getDepartments);
                $department_id = $depdata['id'] ?? null;

				if($empId && $empId['id']!=0){
					$currentDate = date("Y-m-d H:i:s");
					
					$update = "update tbluser set department_id = '".$department_id."' where employee_no=".$fetOldStudent['user_code'];
					
					$insertData = mysqli_query($cn, $update);
										
					$final_result[] = array(
						"Current_id"=> $empId['id'],
						"emp_name"=>$fetOldStudent['first_name'],
						"mobile"=>$fetOldStudent['mobile'],
                        "emp_code" => $fetOldStudent['user_code'],
					);
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
echo "<pre>";print_r($final_result);echo "</pre>";
?>