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
	isset($data['course_id']) && $data['course_id'] != '' &&
	isset($data['chapter_id']) && $data['chapter_id'] != '' &&
	isset($data['user_id']) && $data['user_id'] != ''
) {

	$course_id = $data["course_id"];
	$chapter_id = $data["chapter_id"];
	$user_id = $data["user_id"];
	

	$conn->set_charset("utf8");
	mysqli_set_charset($con, "utf8");

	$conn->query("SET NAMES 'utf8'");
	$conn->query("SET character_set_results 'utf8'");
	$conn->query("SET character_set_client 'utf8'");
	$conn->query("SET collation_connection 'utf8_general_ci'");


	$sql_attempt = "select (ifnull(max(attempt),0) + 1) as attempt,id,uniqueid from app_practice_attempts where quiz='" . $course_id . "' and userid='" . $user_id . "'";
	$res = mysqli_query($conn, $sql_attempt);

	if (mysqli_num_rows($res) > 0) {
		while ($row1 = mysqli_fetch_array($res)) {
			$attempt = $row1['attempt'];
			$uniqueid = $row1['id'];
		}
		$sql_insert = "insert into app_practice_attempts(uniqueid,quiz,userid,attempt,timestart)VALUES('" . $uniqueid . "','" . $course_id . "','" . $user_id . "','" . $attempt . "',UNIX_TIMESTAMP (NOW()))";
		$res2 = mysqli_query($conn, $sql_insert);
	}
	// echo '<pre>'; print_r($_REQUEST); exit;

	if(isset($data["limit"]) && $data["limit"] != ''){
		$limit = $data["limit"];
		$extra_query = " limit ".$limit;
	}
	
	$sql = "
select eq.id AS questionid, eq.category, eq.name AS quetionname, eq.questiontext, eq.defaultmark,  eq.qtype,eq.idnumber,eqa.id AS answerid,eqa.answer AS answertext,if(eqa.fraction=1,'true','false') AS correct
from mdl_question_answers eqa 
inner join mdl_question eq on eq.id=eqa.question 
inner join mdl_question_categories eqc on eqc.id=eq.category
where eqc.id='" . $chapter_id . "'
";


	$res = mysqli_query($conn, $sql);
	// echo '<pre>'; print_r($_REQUEST); exit;
	$response["questionanswer"] = array();

	// shuffle($res);

	$res_data = array();
	$i = 0;
	if (mysqli_num_rows($res) > 0) {

		while ($row1 = mysqli_fetch_array($res)) {
			$res_data[$i]["questionid"] = $row1['questionid'];
			$res_data[$i]["category"] = $row1['category'];
			$res_data[$i]["quetionname"] = $row1['quetionname'];
			$res_data[$i]["questiontext"] = $row1['questiontext'];
			$res_data[$i]["defaultmark"] = $row1['defaultmark'];
			$res_data[$i]["qtype"] = $row1['qtype'];
			$res_data[$i]["idnumber"] = $row1['idnumber'];
			$res_data[$i]["answerid"] = $row1['answerid'];
			$res_data[$i]["answertext"] = $row1['answertext'];
			$res_data[$i]["correct"] = $row1['correct'];
			$i++;
		}

		$res_arr = array();
		foreach ($res_data as $id => $arr) {
			if (!isset($res_arr[$arr['questionid']])) {
				$res_arr[$arr['questionid']]['questionid'] = $arr['questionid'];
				$res_arr[$arr['questionid']]['quetionname'] = $arr['quetionname'];
				$res_arr[$arr['questionid']]['questiontext'] = $arr['questiontext'];
				$res_arr[$arr['questionid']]['defaultmark'] = $arr['defaultmark'];
				$res_arr[$arr['questionid']]['qtype'] = $arr['qtype'];
				$res_arr[$arr['questionid']]['option'][] = array(
					"answerid" => $arr['answerid'],
					"answertext" => $arr['answertext'],
					"correct" => $arr['correct']
				);
			} else {
				$res_arr[$arr['questionid']]['option'][] = array(
					"answerid" => $arr['answerid'],
					"answertext" => $arr['answertext'],
					"correct" => $arr['correct']
				);
				shuffle($res_arr[$arr['questionid']]['option']);
			}
		}
		shuffle($res_arr);

		$send_data = array(
			"status" => 1,
			"message" => "Success",
			"data" => $res_arr
		);
		// echo '<pre>';
		// print_r($res_arr);
		// exit;
		// echo str_replace('\/', '/', json_encode($response));
	}
}
echo json_encode($send_data);
exit;

