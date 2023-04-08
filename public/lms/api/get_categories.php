<?php

include('config.php'); 

$functionname = 'core_course_get_categories';
$restformat = 'json';

$categories = '{}';
$serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token . '&wsfunction='.$functionname.'&moodlewsrestformat=' . $restformat;

try {
	$categories = file_get_contents($serverurl);
	
} catch (Exception $e) {
	echo $e;
}

$rows = json_decode($categories);

echo $categories;
//echo "<pre>";
//print_r($rows);
?>
