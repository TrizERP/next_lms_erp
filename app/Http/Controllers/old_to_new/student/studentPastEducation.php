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
FROM mmiserp_cms.STUDENT_PAST_EDUCATION AS a
INNER JOIN mmiserp_solution.tblstudent AS b ON b.student_id=a.student_id 
ORDER BY b.student_id desc
LIMIT 10');// limit 5
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
						// INSERT INTO `STUDENT_PAST_EDUCATION` (`id`, `student_id`, `syear`, `marking_period_id`, `staff_id`, `course`, `medium`, `name_of_board`, `year_of_passing`, `percentage`, `school_name`, `place`, `trail`, `entry_date`, `reason_of_leaving`) 

                        $course = mysqli_real_escape_string($cn, $fetOldStudent['course']);
                        $medium = mysqli_real_escape_string($cn, $fetOldStudent['medium']);
                        $name_of_board = mysqli_real_escape_string($cn, $fetOldStudent['name_of_board']);
                        $year_of_passing = mysqli_real_escape_string($cn, $fetOldStudent['year_of_passing']);
                        $percentage = mysqli_real_escape_string($cn, $fetOldStudent['percentage']);
                        $school_name = mysqli_real_escape_string($cn, $fetOldStudent['school_name']);
                        $place = mysqli_real_escape_string($cn, $fetOldStudent['place']);
                        $trial = mysqli_real_escape_string($cn, $fetOldStudent['trail']);
                        $reason_of_leaving = mysqli_real_escape_string($cn, $fetOldStudent['reason_of_leaving']);
                        $created_on = date("Y-m-d H:i:s");

                        // Check if data already exists
                        $checkData = mysqli_query($cn, "SELECT * FROM `tblstudent_past_education` 
                            WHERE student_id = '" . $currentStudentID . "' 
                            AND course = '" . $course . "' 
                            AND medium = '" . $medium . "' 
                            AND name_of_board = '" . $name_of_board . "' 
                            AND year_of_passing = '" . $year_of_passing . "' 
                            AND percentage = '" . $percentage . "' 
                            AND school_name = '" . $school_name . "' 
                            AND place = '" . $place . "' 
                            AND trial = '" . $trial . "' 
                            AND reason_of_leaving = '" . $reason_of_leaving . "' 
                            AND sub_institute_id = '" . $sub_institute_id . "'");

                        if (mysqli_num_rows($checkData) == 0) {
                            $insert = mysqli_query($cn, "INSERT INTO `tblstudent_past_education` 
                                (`student_id`, `course`, `medium`, `name_of_board`, `year_of_passing`, `percentage`, `school_name`, `place`, `trial`, `reason_of_leaving`, `sub_institute_id`, `created_on`) 
                                VALUES 
                                ('$currentStudentID', '$course', '$medium', '$name_of_board', '$year_of_passing', '$percentage', '$school_name', '$place', '$trial', '$reason_of_leaving', '$sub_institute_id', '$created_on')");

                            $final_result[] = [
                                $currentStudentID, $course, $medium, $name_of_board, $year_of_passing, $percentage, $school_name, $place, $trial, $reason_of_leaving, $sub_institute_id, $created_on
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