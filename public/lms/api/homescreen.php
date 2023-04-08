<?php
 header("Access-Control-Allow-Origin: *");
 header("Content-Type: application/json; charset=UTF-8");
 header("Access-Control-Allow-Methods: POST");
 header("Access-Control-Max-Age: 3600");
 header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

mb_internal_encoding("UTF-8");
include("auth/auth.php");
include('app_config.php'); 

$functionname = 'core_enrol_get_users_courses';
$restformat = 'json';

 $data = json_decode(file_get_contents("php://input"));
 $data = (array) $data;

// $userid = $data['userid'];
$send_data = array(
    "status" => 0,
    "message" => "Bad Request."
);
//echo $userid."RAJESh";
//if (isset($data['userid']) && $data['userid'] != '') {

	$userid = '7';//$data['userid'];//'8'
//	header('Content-Type: text/plain');
	$serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token . '&wsfunction=' . $functionname . '&userid=' . $userid;
	require_once('./curl.php');
	$curl = new curl;
	//if rest format == 'xml', then we do not add the param for backward compatibility with Moodle < 2.2
	$restformat = ($restformat == 'json')?'&moodlewsrestformat=' . $restformat:'';
	$resp = $curl->post($serverurl . $restformat, $params);

	$final_arr = array();
	$live_lecture = array(
					'status'=> '1',
					'image' => $domainname.'/api/assets/live_lecture.jpg',
					'image_action' => $domainname.'/lms',
					'screenid' => '',
					'idnumber' => '',
					);
	$question_paper = array(
					'status'=> '1',
					'image' => $domainname.'/api/assets/que_paper.jpg',
					'image_action' => 'http://appweb.triz.co.in/dailylms',
					'screenid' => '',
					'idnumber' => '',
					);

	$resp = json_decode($resp);
	
	$title_arr = array("My Subjects","Live Lecture","Question Paper");//
	$data_arr = array($resp,$live_lecture,$question_paper);//
	$key_arr = array("chapter","slider","slider");//
	
	foreach($title_arr as $key => $val)
	{
		if(count($data_arr[$key]) > 0)
		{
			$final_arr[] = array("title" => $val,'key'=>$key_arr[$key],'data'=>$data_arr[$key]);
		}
	}
	// $final_arr = array(
		// "live_lecture" => $live_lecture,
		// "my_courses" => $resp,
		// "question_paper" => $question_paper
	// );
	$send_data = array(
					"status" => 1,
					"message" => "Success",
					"data" => $final_arr
				);

//}

echo json_encode($send_data);
exit;

?>

