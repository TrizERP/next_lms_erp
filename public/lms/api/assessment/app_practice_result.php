<?php
error_reporting(1);

mb_internal_encoding("UTF-8");
include("../auth/auth.php");
require('../app_config.php');

$send_data = array(
	"status" => 0,
	"message" => "Bad Request."
);

// require("config.php");

if (
	isset($data['courseid']) && $data['courseid'] != '' &&
	isset($data['user_id']) && $data['user_id'] != ''
) {


	$courseid = $data["courseid"];
	$userid = $data["user_id"];

	$sql = "select * 
	from app_practice_grades 
	where courseid='" . $courseid . "' and 
	userid='" . $userid . "' 
	order by MODIFIED_DATE DESC
	";

	$conn->set_charset("utf8");
	mysqli_set_charset($con, "utf8");

	$conn->query("SET NAMES 'utf8'");
	$conn->query("SET character_set_results 'utf8'");
	$conn->query("SET character_set_client 'utf8'");
	$conn->query("SET collation_connection 'utf8_general_ci'");

	$res = mysqli_query($conn,$sql);
	// $response["report"] = array();
	$rea_data = array();
	$i = 0;
	if (mysqli_num_rows($res) > 0) {

		while ($row1 = mysqli_fetch_array($res)) {
			$rea_data[$i]["id"] = $row1['id'];
			$rea_data[$i]["chapterid"] = $row1['chapterid'];
			$rea_data[$i]["chaptername"] = $row1['chaptername'];
			$rea_data[$i]["total"] = $row1['Total_Marks'];
			$rea_data[$i]["grade"] = $row1['grade'];
			$rea_data[$i]["modify_date"] = $row1['MODIFIED_DATE'];
			$rea_data[$i]["exam_time"] = $row1['Exam_Time'];
			$i++;
			// array_push($response["report"], $stu);
		}
		// echo str_replace('\/', '/', json_encode($response));
	}
	$send_data = array(
		"status" => 1,
		"message" => "Success",
		"data" => $rea_data
	);
}
echo json_encode($send_data);
exit;
