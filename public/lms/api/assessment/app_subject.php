<?php
require_once("../auth/auth.php");

error_reporting(1);
require("config.php");

// $user_id = $_REQUEST["user_id"];


$send_data = array(
	"status" => 0,
	"message" => "Bad Request."
);


if (
	isset($data['user_id']) && $data['user_id'] != ''
) {
	if ($session["lms_user_id"] == $data['user_id']) {

		$user_id = $data['user_id'];

		$sql = "SELECT c.id AS Id,cc.name AS Class,c.fullname AS Subject, u.username, u.firstname, u.lastname,u.phone1, u.email,u.address,u.city,u.picture
			from mdl_user u
			INNER JOIN mdl_user_enrolments ue ON ue.userid=u.id
			INNER JOIN mdl_enrol e ON e.id=ue.enrolid AND e.enrol='manual'
			INNER JOIN mdl_course c ON c.id=e.courseid
			INNER JOIN mdl_course_categories cc ON cc.id=c.category
			where u.id='" . $user_id . "'";
			// echo $sql;

			mysqli_query('SET NAMES \'utf8\'');
			mysqli_query("set character_set_results='utf8'");
			mysqli_query("SET character_set_results=utf8");
			mysqli_query("SET names=utf8");
			mysqli_query("SET character_set_client=utf8");
			mysqli_query("SET character_set_connection=utf8");
			mysqli_query("SET collation_connection=utf8_general_ci");
			$res = mysqli_query($conn, $sql);

			$data = array();
			$i = 0;
			if (mysqli_num_rows($res) > 0) {
				while ($row1 = mysqli_fetch_array($res)) {
					$data[$i]["Class"] = $row1['Class'];
					$data[$i]["Subject"] = $row1['Subject'];
					$data[$i]["Id"] = $row1['Id'];
					$i++;
				}
			}

			$send_data = array(
				"status" => 1,
				"message" => "Sucsess",
				"data" => $data
			);
	

	 } else {

		$send_data = array(
			"status" => 0,
			"message" => "Access denied."
		);
	}
}

echo json_encode($send_data);
exit;

/* OLD API VERSION 1.9

$sql = "
select c.id AS Id,cc.name AS Class,c.fullname AS Subject, u.username, u.firstname, u.lastname,u.phone1, u.email,u.address,u.city,u.picture
from mdl_user u
JOIN mdl_role_assignments ra ON ra.userid=u.id
JOIN mdl_context cxt ON cxt.id=ra.contextid
JOIN mdl_course c ON c.category=cxt.instanceid
JOIN mdl_course_categories cc ON cc.id=c.category
where u.id='".$user_id."'
 
";
*/

//NEW API 3.8
$sql = "SELECT c.id AS Id,cc.name AS Class,c.fullname AS Subject, u.username, u.firstname, u.lastname,u.phone1, u.email,u.address,u.city,u.picture
from mdl_user u
INNER JOIN mdl_user_enrolments ue ON ue.userid=u.id
INNER JOIN mdl_enrol e ON e.id=ue.enrolid AND e.enrol='manual'
INNER JOIN mdl_course c ON c.id=e.courseid
INNER JOIN mdl_course_categories cc ON cc.id=c.category
where u.id='" . $user_id . "'";
//echo $sql;

mysqli_query('SET NAMES \'utf8\'');
mysqli_query("set character_set_results='utf8'");
mysqli_query("SET character_set_results=utf8");
mysqli_query("SET names=utf8");
mysqli_query("SET character_set_client=utf8");
mysqli_query("SET character_set_connection=utf8");
mysqli_query("SET collation_connection=utf8_general_ci");
$res = mysqli_query($conn, $sql);
$response["subject_detail"] = array();
if (mysqli_num_rows($res) > 0) {

	while ($row1 = mysqli_fetch_array($res)) {
		$stu = array();
		$stu["Class"] = $row1['Class'];
		$stu["Subject"] = $row1['Subject'];
		$stu["Id"] = $row1['Id'];

		array_push($response["subject_detail"], $stu);
	}
	echo str_replace('\/', '/', json_encode($response));
}
