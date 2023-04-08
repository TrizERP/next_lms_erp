
<?php
mb_internal_encoding("UTF-8");

include('app_config.php'); 

$functionname = 'core_course_create_categories';
$restformat = 'json';

$category = new stdClass();
$category->name = 'CBSE-12 Sci.';
$category->parent = 0;
$category->description = '<p>CBSE-12 Sci.</p>';
$category->descriptionformat = 1;
$categories = array( $category);
$params = array('categories' => $categories);

/// REST CALL
header('Content-Type: text/plain');
$serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token . '&wsfunction='.$functionname;
require_once('./curl.php');
$curl = new curl;
//if rest format == 'xml', then we do not add the param for backward compatibility with Moodle < 2.2
$restformat = ($restformat == 'json')?'&moodlewsrestformat=' . $restformat:'';
$resp = $curl->post($serverurl . $restformat, $params);
print_r($resp);

?>


