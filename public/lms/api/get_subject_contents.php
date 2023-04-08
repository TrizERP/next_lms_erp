<?php
 header("Access-Control-Allow-Origin: *");
 header("Content-Type: application/json; charset=UTF-8");
 header("Access-Control-Allow-Methods: POST");
 header("Access-Control-Max-Age: 3600");
 header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
mb_internal_encoding("UTF-8");
include("auth/auth.php");
include('app_config.php'); 

$functionname = 'core_course_get_contents';
$restformat = 'json';

 $data = json_decode(file_get_contents("php://input"));
 $data = (array) $data;


$send_data = array(
    "status" => 0,
    "message" => "Bad Request."
);


if (isset($data['courseid']) && $data['courseid'] != '') 
{
	$courseid = $data['courseid'];

//	header('Content-Type: text/plain');
	$serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token . '&wsfunction=' . $functionname . '&courseid=' . $courseid;
	require_once('./curl.php');
	$curl = new curl;
	//if rest format == 'xml', then we do not add the param for backward compatibility with Moodle < 2.2
	$restformat = ($restformat == 'json')?'&moodlewsrestformat=' . $restformat:'';
	$resp = $curl->post($serverurl . $restformat, $params);
	$resp = json_decode($resp);
	
	// print_r($resp);	
	// die;
	$sem = "";	
	foreach($resp as $key => $val)
	{	
	if($val->summary != ''){	
		if($sem == "")
		{
			$sem = $val->summary;
		}
		if($sem == $val->summary)
		{
			$chapter[$sem][] = $val;
		}
		else
		{
			$sem = $val->summary;
			$chapter[$sem][] = $val;
		}
	}
	}
	
	foreach($chapter as $key1 => $val1)
	{
		$final_arr[] = array("title" => $key1,"chapters" => $chapter[$key1]);
	}
		
	$send_data = array(
					"status" => 1,
					"message" => "Success",
					"token" => $token,
					"data" => $final_arr
				);
}


echo json_encode($send_data);
exit;
