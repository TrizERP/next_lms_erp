<?php
require("config.php");

error_reporting(0);

$name = $_REQUEST["name"];
$mobile = $_REQUEST["mobile"];
$email = $_REQUEST["email"];
$imei = $_REQUEST["imei"];

$sql = "
select count(*) as cnt from demo_users where email='".$email."' and imei_no = '".$imei."' 
";
$res=mysql_query($sql);

if (mysql_num_rows($res)>0){
	
	while($row1=mysql_fetch_array($res))
	{
		$cnt = $row1['cnt'];
	}
	if($cnt>0){
		
	$sql_update = "
	update demo_users SET name = '".$name."',mobile = '".$mobile."',email = '".$email."',imei_no = '".$imei."',
	created_on = now() where email = '".$email."' and imei_no = '".$imei."'
	";
	$res_update=mysql_query($sql_update);
	
	if($res_update){
		
		$response["message"] = "success";
		echo json_encode($response);
	
	}else{
		
		$response["message"] = "fail";
		echo json_encode($response);
	}
	
	}else{
		
	$sql_insert = "
	insert into demo_users(name,mobile,email,imei_no,created_on)
	values('".$name."','".$mobile."','".$email."','".$imei."',now())
	";
	$res_insert=mysql_query($sql_insert);
	//echo $sql_insert;
	
	if($res_insert){
		
		$response["message"] = "success";
		echo json_encode($response);
	
	}else{
		
		$response["message"] = "fail";
		echo json_encode($response);
	}
}
}
?>