<?php
set_time_limit(0);
include("excel_upload/db.php");

$cn = mysqli_connect($host, $username, $password) or die("adsad");

mysqli_select_db($cn, $database) or die("database");

$client_id = $_REQUEST['client_id'];

if($client_id != "")
{
	$getClients = mysqli_query($cn, "SELECT *,s.Id as sub_institute_id  
					FROM tblclient c 
					INNER JOIN school_setup s on c.id = s.client_id
					WHERE c.id = '" . $client_id . "'"
					);
	while ($fetClients = mysqli_fetch_assoc($getClients)) 
	{
		$host = $fetClients['db_host'];
		$username = $fetClients['db_user'];
		$password = $fetClients['db_password'];
		$database = $fetClients['db_cms'];
		$sub_institute_id = $fetClients['sub_institute_id'];
		$old_url = $fetClients['old_url'];

		if($host != "" && $username != "" && $password != "" && $database != "" && $sub_institute_id != "" && $old_url != "" )
		{

			$cnOld = mysqli_connect($host, $username, $password) or die("adsad");

			mysqli_select_db($cnOld, $database) or die("database");

			$getOldStudent = mysqli_query($cnOld, "SELECT *,CONCAT_WS(' ',first_name,middle_name,last_name) as student_name 
							FROM " . $fetClients['db_solution'] . ".tblstudent 
							WHERE rollover_id IS NOT NULL AND image_file IS NOT NULL");
			
			$final_result = array();
			$total_records = $success = $failed = 0;
			while ($fetOldStudent = mysqli_fetch_assoc($getOldStudent)) 
			{				
				$success_arr = $failed_arr = array();
				$OLD_student_id = $fetOldStudent['student_id'];				
				$NEW_student_id = $fetOldStudent['rollover_id'];				
				$OLD_stud_photo = $fetOldStudent['image_file'];				
																						
				$src = $old_url."/Products/admin/assets/upload_user/".$OLD_stud_photo;						
				$src_arr = explode(".",basename($src));				
				$NEW_stud_photo = $fetOldStudent['rollover_id'].".".$src_arr[1];

				$dest = "storage/student/" . $NEW_stud_photo;
				file_put_contents($dest, file_get_contents($src));

				$updStudPhoto = "UPDATE tblstudent set image = '".$NEW_stud_photo."' WHERE id = '" . $NEW_student_id . "' 
				AND sub_institute_id = '".$sub_institute_id."'";
				$result = mysqli_query($cn,$updStudPhoto);

				if ($result)
				{ 										
					$success++;
					$status = "Successfully Uplodaded File / File -> ".$src;
				}
				else
				{
					$failed++;
					$status = "Failed Uploading File / File -> ".$src;
					
				}
				$total_records++;								
				
				$final_result[$OLD_student_id]['STUDENT_NAME'] = $fetOldStudent['student_name'];
				$final_result[$OLD_student_id]['RESULT'] = $status;								
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
	echo "<font color='red'>Client ID parameter is missing</font>";
}

echo '<pre>';
echo "<h2 style='color:red;'>This Script will take time so please wait till it finish.</h2>";
echo "=======================================================================================";
echo "<h2>Final Result</h2>";
echo "=======================================================================================<br><br>";
echo "<h2>Total Records : ".$total_records."</h2><br>";
echo "<h2>Total Success : ".$success."</h2><br>";
echo "<h2>Total Failure : ".$failed."</h2><br>";
echo "=======================================================================================<br><br>";
print_r($final_result);
?>