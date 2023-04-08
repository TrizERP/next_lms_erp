
<?php
 header("Access-Control-Allow-Origin: *");
 header("Content-Type: application/json; charset=UTF-8");
 header("Access-Control-Allow-Methods: POST");
 header("Access-Control-Max-Age: 3600");
 header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
mb_internal_encoding("UTF-8");

include('config.php'); 
$functionname = 'core_user_create_users';
$restformat = 'json';


$username = $_REQUEST['mobile'];
$password = 'student';
$firstname = $_REQUEST['firstname'];
$lastname = $_REQUEST['lastname'];
$email = $_REQUEST['email'];
//////// moodle_user_create_users ////////

if($username != '' && $password != '' && $firstname != '' && $lastname != '' && $email != '')
{


/// PARAMETERS - NEED TO BE CHANGED IF YOU CALL A DIFFERENT FUNCTION
$user1 = new stdClass();
$user1->username = $username;
$user1->password = $password;
$user1->firstname = $firstname;
$user1->lastname = $lastname;
$user1->email = $email;
//$user1->auth = 'manual';
//$user1->idnumber = 'testidnumber1';
//$user1->lang = 'en';
//$user1->theme = 'standard';
//$user1->timezone = '-12.5';
//$user1->mailformat = 0;
//$user1->description = 'Hello World! Shabbir Here';
//$user1->city = 'testcity1';
//$user1->country = 'au';
//$preferencename1 = 'preference1';
//$preferencename2 = 'preference2';
//$user1->preferences = array(
//    array('type' => $preferencename1, 'value' => 'preferencevalue1'),
//    array('type' => $preferencename2, 'value' => 'preferencevalue2'));
/*
$user2 = new stdClass();
$user2->username = 'shabbir';
$user2->password = '123456';
$user2->firstname = 'fakira';
$user2->lastname = 'shabbir';
$user2->email = 'shabbir@moodle.com';
$user2->timezone = 'Pacific/Port_Moresby';
*/
$users = array($user1);//, $user2
$params = array('users' => $users);

/// REST CALL
//header('Content-Type: text/plain');
$serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token . '&wsfunction='.$functionname;
require_once('./curl.php');
$curl = new curl;
//if rest format == 'xml', then we do not add the param for backward compatibility with Moodle < 2.2
$restformat = ($restformat == 'json')?'&moodlewsrestformat=' . $restformat:'';
$resp = $curl->post($serverurl . $restformat, $params);
print_r($resp);
// echo $username;
}else{
	echo "Parameter Missing";
}
?>


