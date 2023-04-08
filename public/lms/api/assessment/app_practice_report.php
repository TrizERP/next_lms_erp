<?php
// require("config.php");
error_reporting(1);

mb_internal_encoding("UTF-8");
include("../auth/auth.php");
require('../app_config.php');

$send_data = array(
	"status" => 0,
	"message" => "Bad Request."
);

if (
	isset($data['id']) && $data['id'] != '' &&
	isset($data['user_id']) && $data['user_id'] != ''
) {

	$id = $data["id"];
	$userid = $data["user_id"];

	$sql = "select QUESTION_LAYOUT,GIVEN_ANS,ORG_ANS 
		from app_practice_grades 
		where id='" . $id . "'";

		
	$conn->set_charset("utf8");
	mysqli_set_charset($con, "utf8");

	$conn->query("SET NAMES 'utf8'");
	$conn->query("SET character_set_results 'utf8'");
	$conn->query("SET character_set_client 'utf8'");
	$conn->query("SET collation_connection 'utf8_general_ci'");
	
	$res = mysqli_query($conn,$sql);
// echo '<pre>'; print_r($_REQUEST); exit;

	if (mysqli_num_rows($res) > 0) {
		while ($row1 = mysqli_fetch_array($res)) {
			$QUESTION_LAYOUT = explode(',', $row1[QUESTION_LAYOUT]);
			// $QUESTION_LAYOUT = explode( $row1[QUESTION_LAYOUT],',');

			$GIVEN_ANS = explode(',', $row1[GIVEN_ANS]);
			$ORG_ANS = explode(',', $row1[ORG_ANS]);
		}
	}
	// echo '<pre>'; print_r($QUESTION_LAYOUT); exit;

	$mi = new MultipleIterator();
	$mi->attachIterator(new ArrayIterator($QUESTION_LAYOUT));
	$mi->attachIterator(new ArrayIterator($GIVEN_ANS));
	$mi->attachIterator(new ArrayIterator($ORG_ANS));
// echo '<pre>'; print_r($mi); exit;

	$respons = array();
	$stu = array();
	foreach ($mi as $value) {
		list($QUESTIONLAYOUT, $GIVENANS, $ORGANS) = $value;
		$sql = "	select q.questiontext, qa.answer as org_ans,qb.answer as given_ans from mdl_question as q 
			inner join mdl_question_answers as qa on qa.id='" . $ORGANS . "'
			inner join mdl_question_answers as qb on qb.id='" . $GIVENANS . "'
			where q.id='" . $QUESTIONLAYOUT . "'
		";
		
		$res = mysqli_query($conn,$sql);

		if (mysqli_num_rows($res) > 0) {
			while ($row1 = mysqli_fetch_array($res)) {
				$stu["questiontext"] = $row1['questiontext'];
				$stu["org_ans"] = $row1['org_ans'];
				$stu["given_ans"] = $row1['given_ans'];

				array_push($respons, $stu);
			}
		}
	}
	$send_data = array(
		"status" => 1,
		"message" => "Success",
		"data" => $respons
	);
}
echo json_encode($send_data);
exit;
echo str_replace('\/', '/', json_encode($respons));
