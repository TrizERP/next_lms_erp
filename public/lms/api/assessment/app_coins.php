<?php
error_reporting(1);
require("config.php");

$user_id= $_REQUEST["user_id"];

$sql = "
SELECT IFNULL(SUM(coins),0) as coins FROM app_coins_entry where user_id='".$user_id."'
 
";
mysql_query('SET NAMES \'utf8\'');
	mysql_query ("set character_set_results='utf8'");
	mysql_query("SET character_set_results=utf8");
	mysql_query("SET names=utf8");
	mysql_query("SET character_set_client=utf8");
	mysql_query("SET character_set_connection=utf8");
	mysql_query("SET collation_connection=utf8_general_ci");
	
$res=mysql_query($sql);
$response["coins_detail"] = array();
if (mysql_num_rows($res)>0)
{
	
	while($row1=mysql_fetch_array($res))
	{
		$stu= array();
		$stu["coins"] = $row1['coins'];
		
		array_push($response["coins_detail"], $stu);
	}
	echo str_replace('\/','/',json_encode($response));
}

?>