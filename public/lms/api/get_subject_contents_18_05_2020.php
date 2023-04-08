<?php
mb_internal_encoding("UTF-8");

include('app_config.php'); 

$functionname = 'core_course_get_contents';
$restformat = 'json';

//$token = "600c496313948a4b7530507ed9872d25";
//$domainname = "http://202.47.117.131/lms";

$courseid = '23';//$_REQUEST['courseid'];


header('Content-Type: text/plain');
$serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token . '&wsfunction=' . $functionname . '&courseid=' . $courseid;
require_once('./curl.php');
$curl = new curl;
//if rest format == 'xml', then we do not add the param for backward compatibility with Moodle < 2.2
$restformat = ($restformat == 'json')?'&moodlewsrestformat=' . $restformat:'';
$resp = $curl->post($serverurl . $restformat, $params);
print_r($resp);
		
?>

