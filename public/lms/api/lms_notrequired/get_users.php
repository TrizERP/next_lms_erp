<?php

include('app_config.php'); 

$functionname = 'core_user_get_users';
$restformat = 'json';

$users = '{}';
/*
		$serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token . '&wsfunction='.$functionname;
		try {
			$users = file_get_contents($serverurl);
		} catch (Exception $e) {
			echo $e;
		}
		$rows = json_decode($users);
		echo "<pre>";
		print_r($rows);
*/
header('Content-Type: text/plain');
$serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token . '&wsfunction='.$functionname;
require_once('./curl.php');
$curl = new curl;
//if rest format == 'xml', then we do not add the param for backward compatibility with Moodle < 2.2
$restformat = ($restformat == 'json')?'&moodlewsrestformat=' . $restformat:'';
$resp = $curl->post($serverurl . $restformat, $params);
print_r($resp);
?>
