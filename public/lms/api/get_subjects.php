<?php
include('config.php'); 

$functionname = 'core_course_get_courses_by_field';
$restformat = 'json';

$category = $_REQUEST['category'];
$cursos = '{}';

		$serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token . '&wsfunction='.$functionname.'&moodlewsrestformat=' . $restformat.'&field=category&value='.$category;

		try {
			$cursos = file_get_contents($serverurl);
		} catch (Exception $e) {
			echo $e;
		}
		
		$rows = json_decode($cursos);
echo $cursos;
//		echo "<pre>";
		//print_r($rows);
?>
