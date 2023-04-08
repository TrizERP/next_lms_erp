<?php
error_reporting(1);

mb_internal_encoding("UTF-8");
include("../auth/auth.php");
// require_once('../app_config.php');
require('../app_config.php');

$send_data = array(
	"status" => 0,
	"message" => "Bad Request."
);


if (isset($data['course_id']) && $data['course_id'] != '') {

	$course_id = $data["course_id"];

	$sql = "SELECT eqc.id as chapterid,eqc.name as chaptername,eqc.sortorder,eqc.idnumber, count(eq.category) as cnt
		from mdl_question eq
		inner join mdl_context ec on ec.instanceid='" . $course_id . "'
		inner join mdl_question_categories eqc on eqc.contextid in (ec.id)
		where eq.category=eqc.id
		group by eqc.id";

	$conn -> set_charset("utf8");
	mysqli_set_charset($con,"utf8");

	$conn->query("SET NAMES 'utf8'");
	$conn->query("SET character_set_results 'utf8'");
	$conn->query("SET character_set_client 'utf8'");
	$conn->query("SET collation_connection 'utf8_general_ci'");

	$res = mysqli_query($conn, $sql);
	
	$data = array();
	$i = 0;

	if (mysqli_num_rows($res) > 0) {

		while ($row1 = mysqli_fetch_array($res)) {

			$data[$i]["chapterid"] = $row1['chapterid'];
			$data[$i]["chaptername"] = $row1['chaptername'];
			$data[$i]["sortorder"] = $row1['sortorder'];
			$data[$i]["idnumber"] = $row1['idnumber'];
			$data[$i]["cnt"] = $row1['cnt'];
			$i++;
		}
	}
	$send_data = array(
		"status" => 1,
		"message" => "Success",
		"data" => $data
	);
}


echo json_encode($send_data);
exit;
