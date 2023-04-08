<?php
require("config.php");

error_reporting(0);

$email = $_REQUEST["email"];
$imei = $_REQUEST["imei"];

$sql = "
select is_active from demo_users where email='".$email."' and imei_no = '".$imei."' 
";
//echo $sql;
$res=mysql_query($sql);

if (mysql_num_rows($res)>0){
	
	while($row1=mysql_fetch_array($res))
	{
		$cnt = $row1['is_active'];
	}
	$response["message"] = $cnt;
	echo json_encode($response);
}else{
	$response["message"] = "first";
	echo json_encode($response);
}
?>