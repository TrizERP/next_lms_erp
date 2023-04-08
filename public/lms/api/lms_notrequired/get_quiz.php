<?php
include("auth/auth.php");
mb_internal_encoding("UTF-8");

include('app_config.php');


$send_data = array(
    "status" => 0,
    "message" => "Bad Request."
);

if (
    isset($data['course_id']) && $data['course_id'] != ''
) {

    $functionname = 'mod_quiz_get_quizzes_by_courses';
    $restformat = 'json';

    $courseid = $data['course_id']; //$_REQUEST['courseid'];
    // $courseid = '23'; //$_REQUEST['courseid'];

    $serverurl = $domainname . '/webservice/rest/server.php' . '?wstoken=' . $token . '&wsfunction=' . $functionname . '&courseids[0]=' . $courseid;
    require_once('./curl.php');
    $curl = new curl;
  
    //if rest format == 'xml', then we do not add the param for backward compatibility with Moodle < 2.2
    $restformat = ($restformat == 'json') ? '&moodlewsrestformat=' . $restformat : '';
    $resp = $curl->post($serverurl . $restformat, $params);
    $resp_arr = json_decode($resp);
    // print_r($resp);

    $send_data = array(
        "status" => 1,
        "message" => "Sucsess",
        "data" => $resp_arr
    );

} 

echo json_encode($send_data);
exit;
