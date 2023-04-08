<?php
include('app_config.php'); 

$functionname = 'core_course_get_courses';
$restformat = 'json';

$cursos = '{}';

		$serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token . '&wsfunction='.$functionname.'&moodlewsrestformat=' . $restformat;

		try {
			$cursos = file_get_contents($serverurl);
		} catch (Exception $e) {
			echo $e;	
		}
		
		$rows = json_decode($cursos);

		echo "<pre>";
		print_r($rows);
?>
