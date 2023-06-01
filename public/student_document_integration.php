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
							WHERE rollover_id IS NOT NULL ");// limit 5
			
			$final_result = array();
			$grand_total = $grand_success = $grand_failure = 0;
			while ($fetOldStudent = mysqli_fetch_assoc($getOldStudent)) 
			{
				$success = $failed = 0;
				$success_arr = $failed_arr = array();
				$OLD_student_id = $fetOldStudent['student_id'];				
				$NEW_student_id = $fetOldStudent['rollover_id'];				
				
				$getOldStudDoc = mysqli_query($cnOld, "SELECT * FROM ".$database.".STUDENT_DOCUMENTS sd
								INNER JOIN ".$database.".STUDENT_DOCUMENT_TYPE dt ON sd.DOC_TYPE_ID = dt.id
								WHERE sd.student_id = ".$OLD_student_id ."");
			

				if (mysqli_num_rows($getOldStudDoc) > 0) 
				{

					while ($fetOldStudDoc = mysqli_fetch_assoc($getOldStudDoc)) 
					{						
						$checkStudDoc = "SELECT * FROM tblstudent_document WHERE student_id = '" . $NEW_student_id . "' 
						AND document_type_id = '".$fetOldStudDoc['DOC_TYPE_ID']."' AND sub_institute_id = '".$sub_institute_id."'";
						$getStudDoc = mysqli_query($cn,$checkStudDoc);

						if (mysqli_num_rows($getStudDoc) == 0)
						{ 
							$insertStudDoc = "INSERT INTO tblstudent_document (student_id,document_type_id,document_title,file_name,sub_institute_id,
								created_on) 
								VALUES ('" . $NEW_student_id . "','" . $fetOldStudDoc['DOC_TYPE_ID'] . "','" . $fetOldStudDoc['doc_type_name'] . "',
								'" . $fetOldStudDoc['FILE_NAME'] . "','" . $sub_institute_id . "',now())";
							mysqli_query($cn, $insertStudDoc) or die(mysqli_error($cn));

							$src = $old_url."/Products/cms/assets/student_document/".$fetOldStudDoc['FILE_NAME'];						
							$dest = "storage/student_document/" . basename($src);
							file_put_contents($dest, file_get_contents($src));

							$success++;
							$success_arr[$OLD_student_id][] = $fetOldStudDoc['doc_type_name'] ." / FileName -> ".$fetOldStudDoc['FILE_NAME'];
						}
						else
						{
							$failed++;
							$failed_arr[$OLD_student_id][] = $fetOldStudDoc['doc_type_name']." / FileName -> ".$fetOldStudDoc['FILE_NAME'];
							
						}
					}			
				}
				$final_result[$OLD_student_id]['STUDENT_NAME'] = $fetOldStudent['student_name'];
				$final_result[$OLD_student_id]['TOTAL_DOCUMENT'] = mysqli_num_rows($getOldStudDoc);
				$final_result[$OLD_student_id]['TOTAL SUCCESS'] = $success;
				$final_result[$OLD_student_id]['SUCCESS_ARR'] = $success_arr;
				$final_result[$OLD_student_id]['TOTAL FAILURE'] = $failed;
				$final_result[$OLD_student_id]['FAILURE_ARR'] = $failed_arr;
				$grand_total = $grand_total + mysqli_num_rows($getOldStudDoc);
				$grand_success = $grand_success + $success;
				$grand_failure = $grand_failure + $failed;			

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
echo "<h2>Total Records : ".$grand_total."</h2><br>";
echo "<h2>Total Success : ".$grand_success."</h2><br>";
echo "<h2>Total Failure : ".$grand_failure."</h2><br>";
echo "=======================================================================================<br><br>";
print_r($final_result);
?>