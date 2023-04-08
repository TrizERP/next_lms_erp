<?php 
mb_internal_encoding("UTF-8");

//include('app_config.php');
$token = 'c1224fee07508baf8ba74cd8e196421b';
$domainname = 'https://lms.merapnaschool.com';

$functionname = 'core_user_get_users_by_field';
$restformat = 'json';

$idnumber = 'nw1';//$_REQUEST['idnumber'];

header('Content-Type: text/plain');
$serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token . '&wsfunction=' . $functionname . '&field=username&values[0]=' . $idnumber;
require_once('./curl.php');
$curl = new curl;
//if rest format == 'xml', then we do not add the param for backward compatibility with Moodle < 2.2
$restformat = ($restformat == 'json')?'&moodlewsrestformat=' . $restformat:'';
$resp = $curl->post($serverurl . $restformat, $params);
print_r($resp);

?>
