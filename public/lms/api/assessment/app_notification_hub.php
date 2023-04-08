<?php
error_reporting(1);
require("config.php");

$user_id= $_REQUEST["user_id"];

$sql = "
select ID,QUIZ_ID,QUIZ_NAME,NOTIFICATION_DESCRIPTION,NOTOFICATION_DATE,Status from app_notification where
 user_id = '".$user_id."' order by NOTOFICATION_DATE desc
 
";
mysql_query('SET NAMES \'utf8\'');
	mysql_query ("set character_set_results='utf8'");
	mysql_query("SET character_set_results=utf8");
	mysql_query("SET names=utf8");
	mysql_query("SET character_set_client=utf8");
	mysql_query("SET character_set_connection=utf8");
	mysql_query("SET collation_connection=utf8_general_ci");
$res=mysql_query($sql);
$response["notification_detail"] = array();
if (mysql_num_rows($res)>0)
{
	
	while($row1=mysql_fetch_array($res))
	{
		$stu= array();
		$stu["ID"] = $row1['ID'];
		$stu["Status"] = $row1['Status'];
		$stu["NOTIFICATION_DESCRIPTION"] = $row1['NOTIFICATION_DESCRIPTION'];
		$stu["NOTOFICATION_DATE"] = $row1['NOTOFICATION_DATE'];
		$stu["QUIZ_ID"] = $row1['QUIZ_ID'];
		$stu["QUIZ_NAME"] = $row1['QUIZ_NAME'];
									
		array_push($response["notification_detail"], $stu);
	}
	echo str_replace('\/','/',json_encode($response));
}

?>