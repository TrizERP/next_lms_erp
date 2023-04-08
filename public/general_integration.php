<?php
set_time_limit(0);
$host = '150.129.172.214';
$username = 'triz_erp';
$password = 'Triz@2019$04';
$database = 'triz_erp';
$syear = "2019";

$cn = mysqli_connect($host, $username, $password) or die("adsad");

mysqli_select_db($cn, $database) or die("database");

$getClients = mysqli_query($cn, "SELECT * FROM tblclient  where id = 7");
while ($fetClients = mysqli_fetch_assoc($getClients)) {

	$host = $fetClients['db_host'];
	$username = $fetClients['db_user'];
	$password = $fetClients['db_password'];
	$database = $fetClients['db_cms'];

	$cnOld = mysqli_connect($host, $username, $password) or die("adsad");

	mysqli_select_db($cnOld, $database) or die("database");

	$getSchools = mysqli_query($cn, "select * from school_setup where client_id = '" . $fetClients['id'] . "'");

	while ($fetSchools = mysqli_fetch_assoc($getSchools)) {

		$profiles = array();

		$getProfilesNew = mysqli_query($cn, "SELECT * FROM tbluserprofilemaster where sub_institute_id = '" . $fetSchools['Id'] . "'");

		while ($fetProfilesNew = mysqli_fetch_assoc($getProfilesNew)) {
			$profiles[$fetProfilesNew['name']] = $fetProfilesNew['id'];
		}

		$grades = array();

		$getGrades = mysqli_query($cn, "SELECT * FROM academic_section WHERE sub_institute_id = '" . $fetSchools['Id'] . "' ");

		while ($fetGrades = mysqli_fetch_assoc($getGrades)) {
			$grades[$fetGrades['title']] = $fetGrades['id'];
		}

		$standards = array();

		$getStandards = mysqli_query($cn, "SELECT * FROM standard WHERE sub_institute_id = '" . $fetSchools['Id'] . "' ");

		while ($fetStandards = mysqli_fetch_assoc($getStandards)) {
			$standards[$fetStandards['name']] = $fetStandards['id'];
		}

		$divisions = array();

		$getDivision = mysqli_query($cn, "SELECT * FROM division WHERE sub_institute_id = '" . $fetSchools['Id'] . "' ");

		while ($fetDivision = mysqli_fetch_assoc($getDivision)) {
			$divisions[$fetDivision['name']] = $fetDivision['id'];
		}

		$users = array();

		$getUsers = mysqli_query($cn, "SELECT * FROM tbluser WHERE sub_institute_id = '" . $fetSchools['Id'] . "' ");

		while ($fetUsers = mysqli_fetch_assoc($getUsers)) {
			$users[$fetUsers['user_name']] = $fetUsers['id'];
		}

		$students = array();

		$getStudent = mysqli_query($cn, "SELECT * FROM tblstudent WHERE sub_institute_id = '" . $fetSchools['Id'] . "' ");

		while ($fetStudent = mysqli_fetch_assoc($getStudent)) {
			$students[$fetStudent['enrollment_no']] = $fetStudent['id'];
		}

		$places = array();

		$getPlaces = mysqli_query($cn, "SELECT * FROM place_master WHERE sub_institute_id = '" . $fetSchools['Id'] . "' ");

		while ($fetPlaces = mysqli_fetch_assoc($getPlaces)) {
			$places[$fetPlaces['title']] = $fetPlaces['id'];
		}

		$fileLocations = array();

		$getFileLocations = mysqli_query($cn, "SELECT * FROM physical_file_location WHERE sub_institute_id = '" . $fetSchools['Id'] . "' ");

		while ($fetFileLocations = mysqli_fetch_assoc($getFileLocations)) {
			$fileLocations[$fetFileLocations['title']] = $fetFileLocations['id'];
		}

		$getOldStudentLeave = mysqli_query($cnOld, "SELECT sl.*,s.enrollment_no,sl.leave_attached_file,ac.title AS STATUS,sl.applied_date
FROM STUDENT_LEAVE sl
INNER JOIN " . $fetClients['db_solution'] . ".tblstudent s ON s.student_id = sl.student_id
LEFT JOIN LEAVE_STATUS ac ON ac.id = sl.leave_status_id
WHERE sl.syear = '" . $syear . "'");

		while ($fetOldStudentLeave = mysqli_fetch_assoc($getOldStudentLeave)) {

			$checkStudentLeave = mysqli_query($cn, "SELECT * from leave_applications where syear = '" . $syear . "' and student_id = '" . $students[$fetOldStudentLeave['enrollment_no']] . "' and sub_institute_id = '" . $fetSchools['Id'] . "' and apply_date = '" . $fetOldStudentLeave['applied_date'] . "'");

			if (mysqli_num_rows($checkStudentLeave) == 0) {
				$insertStudentLeave = "INSERT INTO leave_applications (syear,student_id,message,files,apply_date,from_date,to_date,reply,status,sub_institute_id) VALUES ('" . $syear . "','" . $students[$fetOldStudentLeave['enrollment_no']] . "','" . mysqli_real_escape_string($cn, $fetOldStudentLeave['leave_desc']) . "','" . $fetOldStudentLeave['leave_attached_file'] . "','" . $fetOldStudentLeave['applied_date'] . "','" . $fetOldStudentLeave['from_date'] . "','" . $fetOldStudentLeave['to_date'] . "','','" . $fetOldStudentLeave['STATUS'] . "','" . $fetSchools['Id'] . "')";

				mysqli_query($cn, $insertStudentLeave) or die(mysqli_error($cn));
			}

		}

		$getOldPC = mysqli_query($cnOld, "SELECT sp.*,s.enrollment_no,u.user_name
FROM SCHOOL_PARENT_COMMUNICATION sp
INNER JOIN " . $fetClients['db_solution'] . ".tblstudent s ON sp.STUDENT_ID = s.student_id
LEFT JOIN " . $fetClients['db_solution'] . ".tblstaff u ON u.staff_id = sp.REPLY_BY
WHERE syear = '" . $syear . "'");

		while ($fetOldPC = mysqli_fetch_assoc($getOldPC)) {

			$checkPC = mysqli_query($cn, "SELECT * from parent_communication where syear = '" . $syear . "' and student_id = '" . $students[$fetOldPC['enrollment_no']] . "' and sub_institute_id = '" . $fetSchools['Id'] . "' and date_ = '" . date('Y-m-d', strtotime($fetOldPC['COM_DATE'])) . "'");

			if (mysqli_num_rows($checkPC) == 0) {
				$user_id = 0;
				if (isset($users[$fetOldPC['user_name']])) {
					$user_id = $users[$fetOldPC['user_name']];
				}
				$insertPC = "INSERT INTO parent_communication (syear,date_,student_id,message,reply,reply_by,reply_on,sub_institute_id) VALUES ('" . $syear . "','" . $fetOldPC['COM_DATE'] . "','" . $students[$fetOldPC['enrollment_no']] . "','" . mysqli_real_escape_string($cn, $fetOldPC['DESCRIPTION']) . "','" . mysqli_real_escape_string($cn, $fetOldPC['REPLY_COMMENT']) . "','" . $user_id . "','" . $fetOldPC['REPLY_ON'] . "','" . $fetSchools['Id'] . "')";

				mysqli_query($cn, $insertPC) or die(mysqli_error($cn));
			}

		}

		$getOldCircular = mysqli_query($cnOld, "SELECT * FROM SCHOOL_CIRCULAR WHERE SYEAR = '" . $syear . "'");

		while ($fetOldCircular = mysqli_fetch_assoc($getOldCircular)) {
			$checkCircular = mysqli_query($cn, "SELECT * FROM circular WHERE syear = '" . $syear . "' and sub_institute_id = '" . $fetSchools['Id'] . "' and standard_id = '" . $standards[$fetOldCircular['STANDARD_TITLE']] . "'  and date_ = '" . date('Y-m-d', strtotime($fetOldCircular['CIRCULAR_DATE'])) . "'");

			if (mysqli_num_rows($checkCircular) == 0) {
				$insertCircular = "INSERT INTO circular (syear,standard_id,title,message,file_name,date_,sub_institute_id) VALUES ('" . $syear . "','" . $standards[$fetOldCircular['STANDARD_TITLE']] . "','" . mysqli_real_escape_string($cn, $fetOldCircular['HEADER_TEXT']) . "','" . mysqli_real_escape_string($cn, strip_tags($fetOldCircular['CIRCULAR_DESCRIPTION'])) . "','" . $fetOldCircular['ATTACHMENT'] . "','" . $fetOldCircular['CIRCULAR_DATE'] . "','" . $fetSchools['Id'] . "')";

				mysqli_query($cn, $insertCircular) or die(mysqli_error($cn));
			}

		}

		$getOldES = mysqli_query($cnOld, "SELECT es.*,cs.title as STANDARD_TITLE FROM exam_schedule es INNER JOIN COURSE_SUBJECTS cs ON es.standard_id = cs.subject_id WHERE es.syear = '" . $syear . "'");

		while ($fetOldES = mysqli_fetch_assoc($getOldES)) {
			$checkES = mysqli_query($cn, "SELECT * FROM exam_schedule WHERE syear = '" . $syear . "' and sub_institute_id = '" . $fetSchools['Id'] . "' and standard_id = '" . $standards[$fetOldES['STANDARD_TITLE']] . "' ");

			// and date_ = '".date('Y-m-d',strtotime($fetOldES['DATE']))."'

			if (mysqli_num_rows($checkES) == 0) {
				$insertES = "INSERT INTO exam_schedule (syear,standard_id,division_id,title,file_name,date_,sub_institute_id) VALUES ('" . $syear . "','" . $standards[$fetOldES['STANDARD_TITLE']] . "',0,'" . mysqli_real_escape_string($cn, $fetOldES['HEADER_TEXT']) . "','" . $fetOldES['ATTACHMENT'] . "','" . $fetOldES['DATE'] . "','" . $fetSchools['Id'] . "')";

				mysqli_query($cn, $insertES) or die(mysqli_error($cn));
			}

		}

		$getOldPlace = mysqli_query($cnOld, "SELECT * FROM PLACEMASTER ");

		while ($fetOldPlace = mysqli_fetch_assoc($getOldPlace)) {
			$checkPlace = mysqli_query($cn, "SELECT * FROM place_master WHERE sub_institute_id = '" . $fetSchools['Id'] . "' and title = '" . $fetOldPlace['TITLE'] . "' ");

			if (mysqli_num_rows($checkPlace) == 0) {
				$insertPlace = "INSERT INTO place_master (sub_institute_id,title,description) VALUES ('" . $fetSchools['Id'] . "','" . $fetOldPlace['TITLE'] . "','" . $fetOldPlace['DESCRIPTION'] . "')";

				mysqli_query($cn, $insertPlace) or die(mysqli_error($cn));
			}

		}

		$getOldFileLocation = mysqli_query($cnOld, "SELECT * FROM physicalfilelocation ");

		while ($fetOldFileLocation = mysqli_fetch_assoc($getOldFileLocation)) {
			$checkFileLocation = mysqli_query($cn, "SELECT * FROM physical_file_location WHERE sub_institute_id = '" . $fetSchools['Id'] . "' and title = '" . $fetOldFileLocation['title'] . "'");

			if (mysqli_num_rows($checkFileLocation) == 0) {
				$insertFileLocation = "INSERT INTO physical_file_location (sub_institute_id,title,description,file_code,file_location) VALUES ('" . $fetSchools['Id'] . "','" . $fetOldFileLocation['title'] . "','" . $fetOldFileLocation['description'] . "','" . $fetOldFileLocation['file_code'] . "','" . $fetOldFileLocation['file_location'] . "')";

				mysqli_query($cn, $insertFileLocation) or die(mysqli_error($cn));
			}

		}

		$getInward = mysqli_query($cnOld, "SELECT i.*,p.TITLE place,pf.title file FROM INWARD i INNER JOIN PLACEMASTER p ON i.PLACE_ID = p.ID LEFT JOIN physicalfilelocation pf ON i.FILE_LOCATION_ID = pf.id WHERE i.syear = '" . $syear . "' GROUP BY INWARD_NUMBER ");

		while ($fetInward = mysqli_fetch_assoc($getInward)) {
			$checkInward = mysqli_query($cn, "SELECT * FROM inward WHERE sub_institute_id = '" . $fetSchools['Id'] . "' and inward_number = '" . $fetInward['INWARD_NUMBER'] . "' and title = '" . $fetInward['TITLE'] . "'");

			if (mysqli_num_rows($checkInward) == 0) {
				$insertInward = "INSERT INTO inward (sub_institute_id,place_id,file_location_id,inward_number,title,description,attachment,acedemic_year,inward_date) VALUES ('" . $fetSchools['Id'] . "','" . $places[$fetInward['place']] . "','" . $fileLocations[$fetInward['file']] . "','" . $fetInward['INWARD_NUMBER'] . "','" . $fetInward['TITLE'] . "','" . $fetInward['DESCRIPTION'] . "','" . $fetInward['ATTACHMENT'] . "','" . $fetInward['ACEDEMIC_YEAR'] . "','" . $fetInward['INWARD_DATE'] . "')";

				mysqli_query($cn, $insertInward) or die(mysqli_error($cn));
			}

		}

		$getOutward = mysqli_query($cnOld, "SELECT i.*,p.TITLE place,pf.title FILE FROM OUTWARD i INNER JOIN PLACEMASTER p ON i.PLACE_ID = p.ID LEFT JOIN physicalfilelocation pf ON i.FILE_LOCATION_ID = pf.id ");

		while ($fetOutward = mysqli_fetch_assoc($getOutward)) {
			$checkOutward = mysqli_query($cn, "SELECT * FROM outward WHERE sub_institute_id = '" . $fetSchools['Id'] . "' and outward_number = '" . $fetOutward['OUTWARD_NUMBER'] . "' and title = '" . $fetOutward['TITLE'] . "'");

			if (mysqli_num_rows($checkOutward) == 0) {
				$insertOutward = "INSERT INTO outward (sub_institute_id,place_id,file_location_id,outward_number,title,description,attachment,acedemic_year,outward_date) VALUES ('" . $fetSchools['Id'] . "','" . $places[$fetOutward['place']] . "','" . $fileLocations[$fetOutward['file']] . "','" . $fetOutward['OUTWARD_NUMBER'] . "','" . $fetOutward['TITLE'] . "','" . $fetOutward['DESCRIPTION'] . "','" . $fetOutward['ATTACHMENT'] . "','" . $fetOutward['ACEDEMIC_YEAR'] . "','" . $fetOutward['INWARD_DATE'] . "')";

				mysqli_query($cn, $insertOutward) or die(mysqli_error($cn));
			}

		}

		$getOldYears = mysqli_query($cnOld, "SELECT * FROM SCHOOL_SEMESTERS");

		while ($fetOldYears = mysqli_fetch_assoc($getOldYears)) {
			$checkYears = mysqli_query($cn, "SELECT * FROM academic_year WHERE syear = '" . $fetOldYears['syear'] . "' and sub_institute_id = '" . $fetSchools['Id'] . "' and term_id = '" . $fetOldYears['marking_period_id'] . "'");

			if (mysqli_num_rows($checkYears) == 0) {
				$insertYears = "INSERT INTO academic_year (term_id,syear,sub_institute_id,title,short_name,sort_order,start_date,end_date,post_start_date,post_end_date,does_grades,does_exams) VALUES ('" . $fetOldYears['marking_period_id'] . "','" . $fetOldYears['syear'] . "','" . $fetSchools['Id'] . "','" . $fetOldYears['title'] . "','" . $fetOldYears['short_name'] . "','" . $fetOldYears['sort_order'] . "','" . $fetOldYears['start_date'] . "','" . $fetOldYears['end_date'] . "','" . $fetOldYears['post_start_date'] . "','" . $fetOldYears['post_end_date'] . "','" . $fetOldYears['does_grades'] . "','" . $fetOldYears['does_exam'] . "')";

				mysqli_query($cn, $insertYears) or die(mysqli_error($cn));
			}

		}

	}
}
?>