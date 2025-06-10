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
		$database = $fetClients['db_solution'];
		$sub_institute_id = $fetClients['sub_institute_id'];
		
		if($host != "" && $username != "" && $password != "" && $database != "" && $sub_institute_id != "")
		{

			$cnOld = mysqli_connect($host, $username, $password) or die("adsad");

			mysqli_select_db($cnOld, $database) or die("database");

			$getOldStudent = mysqli_query($cnOld, 'SELECT student_id,enrollment_no,U_ID,is_active,mobile,first_name,last_name FROM tblstudent WHERE U_ID !=""');// limit 5
			// for status us 

			$grand_total = $grand_success = $grand_failure = 0;
			while ($fetOldStudent = mysqli_fetch_assoc($getOldStudent)) 
			{
                $getNewStudent = mysqli_query($cn, 'SELECT id,enrollment_no,uniqueid FROM tblstudent WHERE sub_institute_id='.$sub_institute_id);// limit 5
                while ($fetNewStudent = mysqli_fetch_assoc($getNewStudent)) 
                {
					if($fetNewStudent['enrollment_no']==$fetOldStudent['enrollment_no']){
						$currentDate = date("Y-m-d H:i:s");
						// echo "<pre>";print_r($fetNewStudent['id']); 
						$update = mysqli_query($cn, "UPDATE tblstudent 
                              SET uniqueid = '" . mysqli_real_escape_string($cn, $fetOldStudent['U_ID']) . "', updated_on = '" . $currentDate . "' 
                              WHERE sub_institute_id = " . $sub_institute_id . " AND enrollment_no = '" . mysqli_real_escape_string($cn, $fetOldStudent['enrollment_no']) . "'");

						$final_result[] = $fetNewStudent;
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