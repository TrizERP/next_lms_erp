<?php 
require("config.php");
error_reporting(0);

function sendPushNotification($registration_ids, $message) 
{

//print_r($message);
    $url = 'https://android.googleapis.com/gcm/send';
    $fields = array(
        'registration_ids' => $registration_ids,
        'data' => $message,
		
		);

    //define('GOOGLE_API_KEY', 'AIzaSyBFR8_OAsJswQMBuY783M1RP2sFLf9eh-A');
	define('GOOGLE_API_KEY', 'AIzaSyCRl8yVdyGDlw6fsDIbT1nM_k_f8XJwg0w');

    $headers = array(
        'Authorization:key=' . GOOGLE_API_KEY,
        'Content-Type: application/json'
    );
    //echo json_encode($fields);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
	
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true );

    $result = curl_exec($ch);
    if($result === false)
        die('Curl failed ' . curl_error());

    curl_close($ch);
    return $result;

}

$pushStatus = '';

$sql="select q.id as q_id,q.name as quiz_name,q.course,c.fullname,cc.name,u.id as user_id,u.firstname,sg.gcm_regid 
from mdl_quiz as q 
inner join mdl_course as c on c.id=q.course 
inner join mdl_course_categories as cc on cc.id=c.category 
inner join mdl_user as u on cc.name=u.country 
inner join gcm_users as sg on sg.user_id=u.id 
where q.id not in ( select QUIZ_ID from app_notification where QUIZ_ID=q.id ) LIMIT 5
";	
mysql_query('SET NAMES \'utf8\'');
	mysql_query ("set character_set_results='utf8'");
	mysql_query("SET character_set_results=utf8");
	mysql_query("SET names=utf8");
	mysql_query("SET character_set_client=utf8");
	mysql_query("SET character_set_connection=utf8");
	mysql_query("SET collation_connection=utf8_general_ci");	
	//echo $sql;
	/*mysql_query('SET NAMES \'utf8\'');
	mysql_query ("set character_set_results='utf8'");
	mysql_query("SET character_set_results=utf8");
	mysql_query("SET names=utf8");
	mysql_query("SET character_set_client=utf8");
	mysql_query("SET character_set_connection=utf8");
	mysql_query("SET collation_connection=utf8_general_ci");*/
		
	$res=mysql_query($sql);
	if (mysql_num_rows($res)>0)
	{
		while($row1=mysql_fetch_array($res))
		{
				$gcmRegIds = array();
				array_push($gcmRegIds, $row1['gcm_regid']);
				$qid = $row1['q_id'];
				$sub_name=$row1['fullname'];
				$uid = $row1['user_id'];
				$quiz_name = $row1['quiz_name'];
			
			$pushMessage = $sub_name."-".$quiz_name." mdl is set.";
			if(isset($gcmRegIds) && isset($pushMessage)) 
			{
				$message = array('price' =>$pushMessage,
				'quizid' =>$qid,
				'quiz_name' =>$quiz_name);
				//$message1 = array('quizid' =>$qid);
				
				$pushStatus =sendPushNotification($gcmRegIds, $message);
			}   		
			$sql_insert = " insert into app_notification (NOTIFICATION_TYPE,NOTOFICATION_DATE,user_id,created_on,NOTIFICATION_DESCRIPTION,QUIZ_ID,QUIZ_NAME,Status)
							values
							('mdl',now(),'".$uid."',now(),'".addslashes($pushMessage)."','".$qid."','".$quiz_name."','0')
							 ";
			mysql_query($sql_insert);	
						 
		}
	}

?>