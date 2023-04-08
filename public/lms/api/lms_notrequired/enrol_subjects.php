<?php
mb_internal_encoding("UTF-8");

include('app_config.php'); 

$functionname = 'core_enrol_get_users_courses';
$restformat = 'json';

// $userid = '7';//$_REQUEST['userid'];
// get posted data
$data = json_decode(file_get_contents("php://input"));
$data = (array) $data;

$userid = $data["userid"];

header('Content-Type: text/plain');
$serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token . '&wsfunction=' . $functionname . '&userid=' . $userid;
require_once('./curl.php');
$curl = new curl;
//if rest format == 'xml', then we do not add the param for backward compatibility with Moodle < 2.2
$restformat = ($restformat == 'json')?'&moodlewsrestformat=' . $restformat:'';
$resp = $curl->post($serverurl . $restformat, $params);

$final_arr = array();
$live_lecture = array(
				'status'=> '1',
				'image' => $domainname.'/api/assets/splash_logo.png',
				'image_action' => '',							
				);
$question_paper = array(
				'status'=> '1',
				'image' => $domainname.'/api/assets/splash_logo.png',
				'image_action' => '',							
				);

$resp = json_decode($resp);
$final_arr = array(
	"live_lecture" => $live_lecture,
	"my_courses" => $resp,
	"question_paper" => $question_paper
); 

print_r(json_encode($final_arr));	
?>

