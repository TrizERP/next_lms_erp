<?php
error_reporting(1);
require("config.php");

$id=$_REQUEST["id"];

//error_reporting(0);

  $sql = "	update app_notification SET Status = '1' where ID = '".$id."'
		";
//echo $sql;
$res=mysql_query($sql);
//echo "hi=".$res;
//echo "test".mysql_num_rows($res);
//echo $res;

if (mysql_num_rows($res)>0)
{

	$response["resp"] = array();
	
		$notification_hub= array();
		
		$notification_hub["message"] = "success";
								
		array_push($response["resp"], $notification_hub);
	
	echo str_replace('\/','/',json_encode($response));
}
		
?>