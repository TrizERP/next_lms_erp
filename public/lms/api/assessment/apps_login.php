<?php
error_reporting(1);
require("config.php");

$user_id= $_REQUEST["user_id"];
$password= $_REQUEST["password"];

$sql = "
select id,username,password,firstname,lastname,phone1,phone2,email,address,city,country AS class,picture from mdl_user where
 username = '$user_id' AND password = md5('$password')
 
";
echo $sql;
mysql_query('SET NAMES \'utf8\'');
	mysql_query ("set character_set_results='utf8'");
	mysql_query("SET character_set_results=utf8");
	mysql_query("SET names=utf8");
	mysql_query("SET character_set_client=utf8");
	mysql_query("SET character_set_connection=utf8");
	mysql_query("SET collation_connection=utf8_general_ci");
$res=mysql_query($sql);
$response["student_detail"] = array();
if (mysql_num_rows($res)>0)
{
	
	while($row1=mysql_fetch_array($res))
	{
		$stu= array();
		$stu["success"] = "Yes";
		$stu["id"] = $row1['id'];
		$stu["username"] = $row1['username'];
		$stu["password"] = $row1['password'];
		$stu["firstname"] = $row1['firstname'];
		$stu["lastname"] = $row1['lastname'];
		$stu["phone1"] = $row1['phone1'];
		$stu["phone2"] = $row1['phone2'];
		$stu["email"] = $row1['email'];
		$stu["address"] = $row1['address'];
		$stu["city"] = $row1['city'];
		$stu["div_class"] = $row1['class'];
		$stu["picture"] = $row1['picture'];
		$stu["VER"] = "1.5";
									
		array_push($response["student_detail"], $stu);
	}
	echo str_replace('\/','/',json_encode($response));
}
else{
		$stu= array();
		$stu["success"] = "No";
		array_push($response["student_detail"], $stu);
		echo str_replace('\/','/',json_encode($response));
}
?>