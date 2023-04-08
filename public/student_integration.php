<?php
set_time_limit(0);
$host = '128.199.17.97';
$username = 'root';
$password = 'Triz@R@jesh';
$database = 'triz_erp_2';
$syear = "2023";

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$cn = mysqli_connect($host,$username,$password,$database);

// Check connection
if (mysqli_connect_errno()) {
  echo "Failed to connect to MySQL: " . mysqli_connect_error();
  exit();
}


// echo $host."======".$username."=======".$password."======";
// die;

// $cn = mysqli_connect($host, $username, $password) or die(mysqli_error());

 //echo 'divyaaa';
 //print_r($cn);
 //die;
// mysqli_select_db($cn, $database) or die("database");

$getClients = mysqli_query($cn, "SELECT * FROM tblclient where id = 81 ");
while ($fetClients = mysqli_fetch_assoc($getClients)) 
{

	// echo '<pre>';
	// print_r($fetClients);
	// die;
	$host = $fetClients['db_host'];
	$username = $fetClients['db_user'];
	$password = $fetClients['db_password'];
	$database = $fetClients['db_cms'];

	$cnOld = mysqli_connect($host, $username, $password) or die("adsad");

	mysqli_select_db($cnOld, $database) or die("database");

	$getSchools = mysqli_query($cn, "select * from school_setup where client_id = '" . $fetClients['id'] . "'");

	while ($fetSchools = mysqli_fetch_assoc($getSchools)) {

		$getOldGrades = mysqli_query($cnOld, "SELECT * FROM SCHOOL_GRADELEVELS");
		while ($fetOldGrades = mysqli_fetch_assoc($getOldGrades)) {

			$checkGrades = mysqli_query($cn, "SELECT * FROM academic_section where title = '" . $fetOldGrades['short_name'] . "' and sub_institute_id = '" . $fetSchools['Id'] . "'");

			if (mysqli_num_rows($checkGrades) == 0) {

				$insertNewGrades = "INSERT INTO academic_section (sub_institute_id,title,short_name,sort_order,shift,medium,created_at,updated_at) VALUES ('" . $fetSchools['Id'] . "','" . $fetOldGrades['short_name'] . "','" . $fetOldGrades['title'] . "','" . $fetOldGrades['sort_order'] . "','1','" . $fetOldGrades['MEDIUM'] . "',now(),now())";

			$iqg = mysqli_query($cn, $insertNewGrades);

			}
		}

		$grades = array();
		$getGrades = mysqli_query($cn, "SELECT * FROM academic_section WHERE sub_institute_id = '" . $fetSchools['Id'] . "' ");
		while ($fetGrades = mysqli_fetch_assoc($getGrades)) {
			$grades[$fetGrades['title']] = $fetGrades['id'];
		}

		$getOldStandard = mysqli_query($cnOld, "SELECT c.*,sg.short_name AS grade FROM COURSE_SUBJECTS c INNER JOIN SCHOOL_GRADELEVELS sg ON c.grade_id = sg.id WHERE c.syear = '" . $syear . "'");
		while ($fetOldStandard = mysqli_fetch_assoc($getOldStandard)) {

			$checkStandard = mysqli_query($cn, "SELECT * FROM standard where name = '" . $fetOldStandard['title'] . "' AND sub_institute_id = '" . $fetSchools['Id'] . "'");

			if (mysqli_num_rows($checkStandard) == 0) {

				$insertNewStandard = "INSERT INTO standard (grade_id,name,short_name,sort_order,sub_institute_id,course_duration,created_at,updated_at) VALUES ('" . $grades[$fetOldStandard['grade']] . "','" . $fetOldStandard['title'] . "','" . $fetOldStandard['short_name'] . "','" . trim($fetOldStandard['sort_order']) . "','" . $fetSchools['Id'] . "','',now(),now())";

			$iqs = mysqli_query($cn, $insertNewStandard);
			}
		}

		$standards = array();
		$getStandards = mysqli_query($cn, "SELECT * FROM standard WHERE sub_institute_id = '" . $fetSchools['Id'] . "' ");
		while ($fetStandards = mysqli_fetch_assoc($getStandards)) {
			$standards[$fetStandards['name']] = $fetStandards['id'];
		}
		$oldDivisions = array();
		$getOldDivision = mysqli_query($cnOld, "SELECT * FROM SCHOOL_SECTIONS WHERE syear = '" . $syear . "'");
		while ($fetOldDivision = mysqli_fetch_assoc($getOldDivision)) {

			$oldDivisions[$fetOldDivision['section_id']] = $fetOldDivision['section_name'];
			$checkDivision = mysqli_query($cn, "SELECT * FROM division where name = '" . $fetOldDivision['section_name'] . "' AND sub_institute_id = '" . $fetSchools['Id'] . "'");

			if (mysqli_num_rows($checkDivision) == 0) {

				$insertNewDivision = "INSERT INTO division (name,sub_institute_id,created_at,updated_at) VALUES ('" . $fetOldDivision['section_name'] . "','" . $fetSchools['Id'] . "',now(),now())";

				$iqd = mysqli_query($cn, $insertNewDivision);
			}
		}

		$divisions = array();
		$getDivision = mysqli_query($cn, "SELECT * FROM division WHERE sub_institute_id = '" . $fetSchools['Id'] . "' ");
		while ($fetDivision = mysqli_fetch_assoc($getDivision)) {
			$divisions[$fetDivision['name']] = $fetDivision['id'];
		}

		$query = "SELECT s.MOD_OF_TRANSPOTATION_ID,s.alternativeemail,s.hostelphone_no,s.subcast,s.merit_no,s.merit_marks,s.phone_no,s.blood_group, s.ADMISSION_ROUND,s.ECO_BACKWARD, s.DISTANCE_FROM_INSTITUTE, s.ENROLLMENT_NO,s.MERIT_NO,s.MERIT_MARKS,s.ADMISSION_YEAR,s.STUDENT_ID,s.LAST_NAME,s.FIRST_NAME,s.MIDDLE_NAME, s.NAME_SUFFIX,s.enrollment_no USERNAME,s.PASSWORD,s.LAST_LOGIN,s.GENDER,s.ETHNICITY,s.CAST,s.RELIGION,s.BIRTHDATE,s.roll_no ALT_ID, s.EMAIL,s.phone_no PHONE,s.mobile MOBILE_NUMBER, s.PHYSICALLY_HANDICAPED,s.RESERVE_CATEGORY, s.FATHER_NAME,s.PLACE_OF_BIRTH,s.MOTHERNAME,s.EMAIL,s.ALTERNATIVEEMAIL,s.AADHAR_NUMBER,s.MOTHERMOBILE, (
SELECT SCHOOL_ID
FROM STUDENT_ENROLLMENT
WHERE SYEAR='" . $syear . "'
ORDER BY START_DATE DESC,END_DATE DESC
LIMIT 1) AS SCHOOL_ID,
(
SELECT GRADE_ID
FROM STUDENT_ENROLLMENT
WHERE SYEAR='" . $syear . "' AND STUDENT_ID=s.STUDENT_ID
ORDER BY START_DATE DESC,END_DATE DESC
LIMIT 1) AS GRADE_ID,
(
SELECT STANDARD_ID
FROM STUDENT_ENROLLMENT
WHERE SYEAR='" . $syear . "' AND STUDENT_ID=s.STUDENT_ID
ORDER BY START_DATE DESC,END_DATE DESC
LIMIT 1) AS STANDARD_ID,
(
SELECT SECTION_ID
FROM STUDENT_ENROLLMENT
WHERE SYEAR='" . $syear . "' AND STUDENT_ID=s.STUDENT_ID
ORDER BY START_DATE DESC,END_DATE DESC
LIMIT 1) AS SECTION_ID,
(
SELECT STUDENT_QUOTA
FROM STUDENT_ENROLLMENT
WHERE SYEAR='" . $syear . "' AND STUDENT_ID=s.STUDENT_ID
ORDER BY START_DATE DESC,END_DATE DESC
LIMIT 1) AS STUDENT_QUOTA,
(
SELECT BATCH_ID
FROM STUDENT_ENROLLMENT
WHERE SYEAR='" . $syear . "' AND STUDENT_ID=s.STUDENT_ID
ORDER BY START_DATE DESC,END_DATE DESC
LIMIT 1) AS BATCH_ID,
(
SELECT title
FROM STUDENT_ENROLLMENT_CODES
WHERE id = SE.drop_code
) AS DROP_CODE,
SG.title AS academic_year,CS.title AS standard_name,SG.id AS STUD_GRADE_ID,SG.short_name as grade,SS.section_name as division,SE.start_date,SE.end_date,s.address,s.zipcode
FROM " . $fetClients['db_solution'] . ".tblstudent s
INNER JOIN STUDENT_ENROLLMENT SE ON SE.student_id=s.STUDENT_ID AND SE.syear='" . $syear . "' AND SE.END_DATE IS NULL
INNER JOIN SCHOOL_GRADELEVELS SG ON SG.id=SE.grade_id
INNER JOIN COURSE_SUBJECTS CS ON CS.subject_id = SE.standard_id
INNER JOIN SCHOOL_SECTIONS SS ON SE.section_id = SS.section_id GROUP BY s.STUDENT_ID";

		$getQuery = mysqli_query($cnOld, $query) or die(mysqli_error($cnOld));

		while ($fetData = mysqli_fetch_assoc($getQuery)) {

			$checkStudent = mysqli_query($cn, "SELECT * FROM tblstudent where enrollment_no = '" . $fetData['ENROLLMENT_NO'] . "' AND first_name = '" . $fetData['FIRST_NAME'] . "' AND last_name = '" . $fetData['LAST_NAME'] . "' and sub_institute_id = '" . $fetSchools['Id'] . "'");

			if (mysqli_num_rows($checkStudent) == 0) {

				$iq = "INSERT INTO tblstudent (`enrollment_no`,`first_name`,`middle_name`,`last_name`,`father_name`,`mother_name`,`gender`,`dob`,`mobile`,`mother_mobile`,`email`,`username`,`password`,`admission_year`,`admission_date`,`city`,`state`,`address`,`pincode`,`sub_institute_id`,`place_of_birth`,`status`) VALUES ('" . $fetData['ENROLLMENT_NO'] . "','" . $fetData['FIRST_NAME'] . "','" . $fetData['MIDDLE_NAME'] . "','" . $fetData['LAST_NAME'] . "','" . $fetData['FATHER_NAME'] . "','" . $fetData['MOTHER_NAME'] . "','" . $fetData['GENDER'] . "','" . $fetData['BIRTHDATE'] . "','" . $fetData['MOBILE_NUMBER'] . "','" . $fetData['MOTHERMOBILE'] . "','" . $fetData['EMAIL'] . "','" . $fetData['ENROLLMENT_NO'] . "','" . md5('student') . "','" . $fetData['ADMISSION_YEAR'] . "','" . $fetData['ADMISSION_DATE'] . "','" . $fetData['ETHNICITY'] . "','','" . $fetData['address'] . "','" . $fetData['zipcode'] . "','" . $fetSchools['Id'] . "','" . $fetData['PLACE_OF_BIRTH'] . "','1')";
				$insertQuery = mysqli_query($cn, $iq) or die("Error tblstudent-".$iq);

				$student_id = mysqli_insert_id($cn);

				$iqse = "INSERT INTO tblstudent_enrollment (syear,student_id,grade_id,standard_id,section_id,student_quota,start_date,end_date,enrollment_code,drop_code,drop_remarks,term_id,remarks,admission_fees,house_id,lc_number,sub_institute_id) VALUES ('" . $syear . "','" . $student_id . "','" . $grades[$fetData['grade']] . "','" . $standards[$fetData['standard_name']] . "','" . $divisions[$fetData['division']] . "','" . $fetData['STUDENT_QUOTA'] . "','" . $fetData['start_date'] . "','" . $fetData['end_date'] . "','1','" . $fetData['DROP_CODE'] . "','" . $fetData['DROP_CODE'] . "','1','" . $fetData['REMARKS'] . "','" . $fetData['ADMISSION_FEES'] . "','','" . $fetData['LC_NUMBER'] . "','" . $fetSchools['Id'] . "')";

				$insertQSeuery = mysqli_query($cn, $iqse) or die("Error Student Enrollment-".$iqse);

				$updateStudentEnrollment = "UPDATE tblstudent_enrollment set end_date = NULL where end_date = '0000-00-00' and sub_institute_id = '" . $fetSchools['Id'] . "'";

				$updateQuery = mysqli_query($cn, $updateStudentEnrollment);
			}

		}

		$getOldStandardSectionMap = mysqli_query($cnOld, "SELECT * FROM COURSE_SUBJECTS WHERE syear = '" . $syear . "'");

		while ($fetOldStandardSectionMap = mysqli_fetch_assoc($getOldStandardSectionMap)) {

			$division = rtrim($fetOldStandardSectionMap['school_section'], ',');
			$division = ltrim($division, ',');
			$dAry = explode(",", $division);
			foreach ($dAry as $k => $v) {
				$finalDiv = $oldDivisions[$v];
				$checkMap = mysqli_query($cn, "SELECT * FROM std_div_map where standard_id = '" . $standards[$fetOldStandardSectionMap['title']] . "' and division_id = '" . $divisions[$finalDiv] . "' and sub_institute_id = '" . $fetSchools['Id'] . "'");

				if (mysqli_num_rows($checkMap) == 0) {

					$insertMap = "INSERT INTO std_div_map (`standard_id`,`division_id`,`sub_institute_id`,`created_at`,`updated_at`) VALUES ('" . $standards[$fetOldStandardSectionMap['title']] . "','" . $divisions[$finalDiv] . "','" . $fetSchools['Id'] . "',now(),now())";

					$updateQuery = mysqli_query($cn, $insertMap);

				}

			}

		}

	}
}
?>