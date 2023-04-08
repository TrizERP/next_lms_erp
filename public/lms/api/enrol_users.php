<?php
include('config.php'); 

$functionname = 'enrol_manual_enrol_users';
$restformat = 'json';

//////// enrol_manual_enrol_users ////////

/// Paramètres
$enrolment = new stdClass();
$enrolment->roleid = 5; //estudante(student) -> 5; moderador(teacher) -> 4; professor(editingteacher) -> 3;
$enrolment->userid = $_REQUEST['userid'];
$enrolment->courseid = $_REQUEST['courseid']; 
$enrolments = array( $enrolment);
$params = array('enrolments' => $enrolments);

// print_r($params);

header('Content-Type: text/plain');

$serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token . '&wsfunction='.$functionname;
require_once('./curl.php');
$curl = new curl;
//if rest format == 'xml', then we do not add the param for backward compatibility with Moodle < 2.2
$restformat = ($restformat == 'json')?'&moodlewsrestformat=' . $restformat:'';
$resp = $curl->post($serverurl . $restformat, $params);
if(empty($resp))
	echo "Success";
else
	echo "Failed";
//print_r($resp);
?>

