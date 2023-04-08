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
	isset($data['chapter_id']) && $data['chapter_id'] != '' &&
	isset($data['chapter_name']) && $data['chapter_name'] != '' &&
	isset($data['user_id']) && $data['user_id'] != '' &&
	isset($data['grade']) && $data['grade'] != '' &&
	isset($data['total']) && $data['total'] != '' &&
	isset($data['question_layout']) && $data['question_layout'] != '' &&
	isset($data['given_ans']) && $data['given_ans'] != '' &&
	isset($data['orignal_ans']) && $data['orignal_ans'] != '' &&
	isset($data['time']) && $data['time'] != ''
	) {

		date_default_timezone_set("Asia/Calcutta");

	// $chapterid = $data["chapter_id"];
	// $userid = $data["user_id"];

	$sql = "INSERT INTO `app_practice_grades` 
		( `courseid`,`chapterid`, `chaptername`, `userid`, `grade`, `timemodified`, `QUESTION_LAYOUT`, `GIVEN_ANS`, `ORG_ANS`, `Total_Marks`, `Exam_Time`) 
		VALUES ('".$data['courseid']."','".$data['chapter_id']."', '".$data['chapter_name']."', '".$data['user_id']."', '".$data['grade']."', '0','".$data['question_layout']."', '".$data['given_ans']."', '".$data['orignal_ans']."', '".$data['total']."', '".$data['time']."');
	";


	$conn->set_charset("utf8");
	mysqli_set_charset($con, "utf8");

	$conn->query("SET NAMES 'utf8'");
	$conn->query("SET character_set_results 'utf8'");
	$conn->query("SET character_set_client 'utf8'");
	$conn->query("SET collation_connection 'utf8_general_ci'");

	$res = mysqli_query($conn,$sql);
	$send_data = array(
		"status" => 1,
		"message" => "Sucsess",
	);
}
echo json_encode($send_data);
exit;
