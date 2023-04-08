<?php
set_time_limit(0);
$host = '192.168.0.2';
$username = 'root';
$password = 'Triz@R@jesh';
$database = 'triz_erp_2';
$syear = "2021";

$cn = mysqli_connect($host, $username, $password) or die("adsad");

mysqli_select_db($cn, $database) or die("database");

$getClients = mysqli_query($cn, "SELECT * FROM tblclient where id = 64");
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

		$getOldSubjects = mysqli_query($cnOld, "SELECT *, UPPER(title) AS subject FROM COURSES WHERE syear = '" . $syear . "' GROUP BY title");

		while ($fetOldSubjects = mysqli_fetch_assoc($getOldSubjects)) {

			$checkSubjects = mysqli_query($cn, "SELECT * FROM subject where subject_name = '" . $fetOldSubjects['subject'] . "' and sub_institute_id = '" . $fetSchools['Id'] . "'");

			if (mysqli_num_rows($checkSubjects) == 0) {

				$insertNewSubjects = "INSERT INTO subject (subject_name,subject_code,subject_type,short_name,sub_institute_id,status,created_at,updated_at) VALUES ('" . trim($fetOldSubjects['subject']) . "','" . $fetOldSubjects['subject_code'] . "','" . $fetOldSubjects['subject_type'] . "','" . $fetOldSubjects['short_name'] . "','" . $fetSchools['Id'] . "','1',now(),now())";

				$iqs = mysqli_query($cn, $insertNewSubjects);

			}
		}

		$subjects = array();

		$getSubject = mysqli_query($cn, "SELECT * FROM subject WHERE sub_institute_id = '" . $fetSchools['Id'] . "' ");

		while ($fetSubject = mysqli_fetch_assoc($getSubject)) {
			$subjects[$fetSubject['subject_name']] = $fetSubject['id'];
		}

		$getOldSubjects = mysqli_query($cnOld, "SELECT c.*, UPPER(c.title) AS subject,cs.title AS standard FROM COURSES c INNER JOIN COURSE_SUBJECTS cs ON c.subject_id = cs.subject_id AND c.syear =cs.syear WHERE c.syear = '" . $syear . "'");

		while ($fetOldSubjects = mysqli_fetch_assoc($getOldSubjects)) {

			$checkSubjects = mysqli_query($cn, "SELECT * FROM sub_std_map where subject_id = '" . $subjects[trim($fetOldSubjects['subject'])] . "' and standard_id = '" . $standards[$fetOldSubjects['standard']] . "' and sub_institute_id = '" . $fetSchools['Id'] . "'");
			if (mysqli_num_rows($checkSubjects) == 0) {
				$allow_grades = ($fetOldSubjects['allow_grades'] == 1) ? 'Yes' : 'No';
				$insertSubStdMap = "INSERT INTO sub_std_map (subject_id,standard_id,allow_grades,elective_subject,display_name,sub_institute_id,status,created_at,updated_at) VALUES ('" . $subjects[trim($fetOldSubjects['subject'])] . "','" . $standards[$fetOldSubjects['standard']] . "','" . $allow_grades . "','" . $fetOldSubjects['elective_subject'] . "','" . trim($fetOldSubjects['subject']) . "','" . $fetSchools['Id'] . "','1',now(),now())";

				$iqssm = mysqli_query($cn, $insertSubStdMap);
			}
		}

		$getOldPeriods = mysqli_query($cnOld, "SELECT * FROM SCHOOL_PERIODS ");

		while ($fetOldPeriods = mysqli_fetch_assoc($getOldPeriods)) {
			$used_for_attendance = ($fetOldSubjects['attendance'] == 'Y') ? 'Yes' : 'No';
			foreach ($grades as $key => $value) {

				$checkPeriod = mysqli_query($cn, "SELECT * FROM period where title = '" . $fetOldPeriods['title'] . "' and academic_section_id = '" . $value . "' and sub_institute_id = '" . $fetSchools['Id'] . "'");

				if (mysqli_num_rows($checkPeriod) == 0) {

					$insertNewPeriod = "INSERT INTO period (title,short_name,sort_order,used_for_attendance,start_time,end_time,length,academic_section_id,status,sub_institute_id,created_at,updated_at) VALUES ('" . $fetOldPeriods['title'] . "','" . $fetOldPeriods['short_name'] . "','" . $fetOldPeriods['sort_order'] . "','" . $used_for_attendance . "','" . $fetOldPeriods['start_time'] . "','" . $fetOldPeriods['end_time'] . "','" . $fetOldPeriods['length'] . "','" . $value . "','1','" . $fetSchools['Id'] . "',now(),now())";

					$iqp = mysqli_query($cn, $insertNewPeriod);

				}
			}
		}

		$periods = array();

		$getPeriods = mysqli_query($cn, "SELECT * FROM period WHERE sub_institute_id = '" . $fetSchools['Id'] . "' ");

		while ($fetPeriods = mysqli_fetch_assoc($getPeriods)) {
			$periods[$fetPeriods['academic_section_id']][$fetPeriods['title']] = $fetPeriods['id'];
		}

		$getOldBatch = mysqli_query($cnOld, "SELECT sb.*,cs.title AS standard,ss.section_name FROM STUDENT_BATCH sb INNER JOIN COURSE_SUBJECTS cs ON sb.syear = cs.syear AND sb.standard_id=cs.subject_id INNER JOIN SCHOOL_SECTIONS ss ON sb.section_id = ss.section_id AND ss.syear = sb.syear WHERE sb.syear = '" . $syear . "'");

		while ($fetOldBatch = mysqli_fetch_assoc($getOldBatch)) {

			$checkBatch = mysqli_query($cn, "SELECT * FROM batch where title = '" . $fetOldBatch['title'] . "' AND standard_id = '" . $standards[$fetOldBatch['standard']] . "' AND division_id = '" . $divisions[$fetOldBatch['section_name']] . "' AND sub_institute_id = '" . $fetSchools['Id'] . "'");

			if (mysqli_num_rows($checkBatch) == 0) {
				$insertBatch = "INSERT INTO batch (title,standard_id,division_id,sub_institute_id,created_at,updated_at) VALUES ('" . $fetOldBatch['title'] . "','" . $standards[$fetOldBatch['standard']] . "','" . $divisions[$fetOldBatch['section_name']] . "','" . $fetSchools['Id'] . "',now(),now())";

				$iqp = mysqli_query($cn, $insertBatch);
			}
		}

		$batchs = array();

		$getBatchs = mysqli_query($cn, "SELECT * FROM batch WHERE sub_institute_id = '" . $fetSchools['Id'] . "' ");

		while ($fetBatchs = mysqli_fetch_assoc($getBatchs)) {
			$batchs[$fetBatchs['title']] = $fetBatchs['id'];
		}

		$getOldTimetable = mysqli_query($cnOld, "SELECT cp.*,sg.short_name AS grade,ss.section_name AS division, cs.title AS standard,s.user_name,sb.title AS batch,UPPER(c.title) AS subject,sp.title AS period FROM COURSE_PERIODS cp INNER JOIN SCHOOL_GRADELEVELS sg ON cp.grade_id = sg.id INNER JOIN COURSE_SUBJECTS cs ON cp.standard_id = cs.subject_id AND cp.syear = cs.syear INNER JOIN SCHOOL_SECTIONS ss ON cp.section_id = ss.section_id INNER JOIN " . $fetClients['db_solution'] . ".tblstaff s ON s.staff_id = cp.teacher_id INNER JOIN COURSES c ON c.course_id = cp.course_id INNER JOIN SCHOOL_PERIODS sp ON sp.period_id = cp.period_id LEFT JOIN STUDENT_BATCH sb ON (sb.id = cp.batch_id) WHERE cp.syear = '" . $syear . "'");

		while ($fetOldTimetable = mysqli_fetch_assoc($getOldTimetable)) {

			$checkTimetable = mysqli_query($cn, "SELECT * FROM timetable where academic_section_id = '" . $grades[$fetOldTimetable['grade']] . "' AND standard_id = '" . $standards[$fetOldTimetable['standard']] . "' AND division_id = '" . $divisions[$fetOldTimetable['division']] . "' AND period_id = '" . $periods[$grades[$fetOldTimetable['grade']]][$fetOldTimetable['period']] . "' AND subject_id = '" . $subjects[trim($fetOldTimetable['subject'])] . "' AND teacher_id = '" . $users[$fetOldTimetable['user_name']] . "' AND week_day = '" . $fetOldTimetable['days'] . "' AND syear = '" . $syear . "' AND sub_institute_id = '" . $fetSchools['Id'] . "'") or die(mysqli_error($cn));

			$batch = 0;

			if (isset($batchs[$fetOldTimetable['batch']])) {
				$batch = $batchs[$fetOldTimetable['batch']];
			}

			if (mysqli_num_rows($checkTimetable) == 0) {
				$insertTimetable = "INSERT INTO timetable (sub_institute_id,syear,academic_section_id,standard_id,division_id,batch_id,period_id,subject_id,teacher_id,week_day,created_at,updated_at) VALUES ('" . $fetSchools['Id'] . "','" . $syear . "','" . $grades[$fetOldTimetable['grade']] . "','" . $standards[$fetOldTimetable['standard']] . "','" . $divisions[$fetOldTimetable['division']] . "','" . $batch . "','" . $periods[$grades[$fetOldTimetable['grade']]][$fetOldTimetable['period']] . "','" . $subjects[trim($fetOldTimetable['subject'])] . "','" . $users[$fetOldTimetable['user_name']] . "','" . $fetOldTimetable['days'] . "',now(),now())";

				$iqt = mysqli_query($cn, $insertTimetable) or die(mysqli_error($cn));
			}
		}

		$getOldClassTeachers = mysqli_query($cnOld, "SELECT st.*,cs.title AS standard,sg.short_name AS grade,ss.section_name AS division,s.user_name
FROM SCHOOL_ASSIGN_CLASS_TEACHER st
INNER JOIN SCHOOL_GRADELEVELS sg ON st.grade_id = sg.id
INNER JOIN COURSE_SUBJECTS cs ON cs.subject_id = st.standard_id
INNER JOIN SCHOOL_SECTIONS ss ON ss.section_id = st.section_id
INNER JOIN " . $fetClients['db_solution'] . ".tblstaff s ON s.staff_id = st.teacher_id
WHERE st.syear = '" . $syear . "'");

		while ($fetOldClassTeachers = mysqli_fetch_assoc($getOldClassTeachers)) {
			$checkClassTeachers = mysqli_query($cn, "SELECT * FROM class_teacher where grade_id = '" . $grades[$fetOldClassTeachers['grade']] . "' AND standard_id = '" . $standards[$fetOldClassTeachers['standard']] . "' AND division_id = '" . $divisions[$fetOldClassTeachers['division']] . "' AND teacher_id = '" . $users[$fetOldClassTeachers['user_name']] . "' AND syear = '" . $syear . "' AND sub_institute_id = '" . $fetSchools['Id'] . "'") or die(mysqli_error($cn));

			if (mysqli_num_rows($checkClassTeachers) == 0) {
				$insertClassTeachers = "INSERT INTO class_teacher (sub_institute_id,syear,grade_id,standard_id,division_id,teacher_id,created_at,updated_at) VALUES ('" . $fetSchools['Id'] . "','" . $syear . "','" . $grades[$fetOldClassTeachers['grade']] . "','" . $standards[$fetOldClassTeachers['standard']] . "','" . $divisions[$fetOldClassTeachers['division']] . "','" . $users[$fetOldClassTeachers['user_name']] . "',now(),now())";

				$iqt = mysqli_query($cn, $insertClassTeachers) or die(mysqli_error($cn));
			}
		}

	}
}
?>