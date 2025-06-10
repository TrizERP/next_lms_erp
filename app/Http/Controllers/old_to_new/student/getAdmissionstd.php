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
		$database = $fetClients['db_cms'];
		$sub_institute_id = $fetClients['sub_institute_id'];
		
		if($host != "" && $username != "" && $password != "" && $database != "" && $sub_institute_id != "")
		{

			$cnOld = mysqli_connect($host, $username, $password) or die("adsad");

			mysqli_select_db($cnOld, $database) or die("database");

			$getOldStudent = mysqli_query($cnOld, 'SELECT s.student_id, s.ENROLLMENT_NO,s.ADMISSION_YEAR,s.STUDENT_ID,s.FIRST_NAME,s.LAST_NAME,s.MIDDLE_NAME MIDDLE_NAME,s.ADMISSION_DATE,
            s.EMAIL,s.mobile MOBILE_NUMBER,SE.standard_id AS seSatndard,
            (
           SELECT st.title
           FROM STUDENT_ENROLLMENT sse
           INNER JOIN COURSE_SUBJECTS st ON sse.standard_id=st.subject_id
           WHERE sse.student_id=s.student_id
           ORDER BY st.subject_id ASC
           LIMIT 1) AS admission_std,
            (
           SELECT STANDARD_ID
           FROM STUDENT_ENROLLMENT
           WHERE SYEAR='.$syear.' AND STUDENT_ID=s.STUDENT_ID
           ORDER BY START_DATE DESC,END_DATE DESC
           LIMIT 1) AS STANDARD_ID,CS.title AS standard_name
           FROM mmiserp_solution.tblstudent s
           INNER JOIN STUDENT_ENROLLMENT SE ON SE.student_id=s.STUDENT_ID AND SE.syear='.$syear.'
           INNER JOIN COURSE_SUBJECTS CS ON CS.subject_id = SE.standard_id
           WHERE SE.syear='.$syear.' AND SE.end_date IS NULL 
           GROUP BY s.student_id');// limit 5
			// for status us 

			$grand_total = $grand_success = $grand_failure = 0;
			while ($fetOldStudent = mysqli_fetch_assoc($getOldStudent)) 
			{
                $getNewStudent = mysqli_query($cn, 'SELECT id,enrollment_no FROM tblstudent WHERE sub_institute_id='.$sub_institute_id);// limit 5
                while ($fetNewStudent = mysqli_fetch_assoc($getNewStudent)) 
                {
					if($fetNewStudent['enrollment_no']==$fetOldStudent['ENROLLMENT_NO']){
						$currentDate = date("Y-m-d H:i:s");

                        $getNewStandard = mysqli_query($cn, 'SELECT id,name FROM standard WHERE sub_institute_id='.$sub_institute_id.' AND name="'.$fetOldStudent['admission_std'].'"');
                        $Adm_std = mysqli_fetch_assoc($getNewStandard);
                        $admStdId = $Adm_std['id'] ?? null;
                        $admStdName = $Adm_std['name'] ?? null;

                        $fetNewStudent['old_standard'] = $fetOldStudent['admission_std'];
                        $fetNewStudent['new_standard'] = $admStdName;
                        $fetNewStudent['new_standard_id'] = $admStdId;
						// echo "<pre>";print_r($fetNewStudent['id']);  admission_std
						// $update = mysqli_query($cn, "UPDATE tblstudent 
                        //       SET uniqueid = '" . mysqli_real_escape_string($cn, $fetOldStudent['U_ID']) . "', updated_on = '" . $currentDate . "' 
                        //       WHERE sub_institute_id = " . $sub_institute_id . " AND enrollment_no = '" . mysqli_real_escape_string($cn, $fetOldStudent['ENROLLMENT_NO']) . "'");

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
echo 'total updated data = '.count($final_result).'<br>';
echo "Updated Students Id's : <br>";
echo "<pre>";print_r($final_result);echo "</pre>";
?>