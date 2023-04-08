<?php
require("config.php");
//echo "hi=".$_REQUEST["imei_no"]."---".$_REQUEST["regId"];die();
error_reporting(0);
//print_r($_REQUEST);
$json = array();

/**
 * Registering a user device
 * Store reg id in users table
 */
  if (isset($_REQUEST["imei_no"])) 
  {
// if (isset($_POST["name"]) && isset($_POST["email"]) && isset($_POST["regId"])) {
    $imei_no =$_REQUEST["imei_no"];
    $news = $_REQUEST["news"];
	$sports = $_REQUEST["sports"];
	$business = $_REQUEST["business"];
	$entertainment = $_REQUEST["entertainment"];
	$technology = $_REQUEST["technology"];
	$alert = $_REQUEST["alert"];
	$stime = $_REQUEST["stime"];
	$etime = $_REQUEST["etime"];
	
    // Store user details in db
   // include_once 'db_functions.php';
   $sql = " select * from settings where imei_no='".$imei_no."' ";
   $res=mysql_query($sql);
   if (mysql_num_rows($res)>0)
	{
		$sql_update = "update settings set news = '".$news."',sports = '".$sports."',business = '".$business."',entertainment = '".$entertainment."',technology = '".$technology."',
		alerts = '".$alert."',from_time = '".$stime."',to_time = '".$etime."',,created_on=now() where imei_no='".$imei_no."' ";
		$res1 = mysql_query($sql_update);
	}
	else
   {
   	$result = "INSERT INTO settings(imei_no,news,sports,business,entertainment,technology,alerts,from_time,to_time,created_on) 
	VALUES('$imei_no', '$news','$sports','$business','$entertainment','$technology','$alert','$stime','$etime',now())";
		
     //   echo $result;
		 $res1 = mysql_query($result);
	}
	
	$response["setting"] = array();
	if (mysql_num_rows($res1)>0)
	{
		$stu= array();
		$stu["message"] = "success";
		array_push($response["setting"], $stu);
		
		echo str_replace('\/','/',json_encode($response));
	}
	else{
		$stu= array();
		$stu["message"] = "fail";
		array_push($response["setting"], $stu);
		echo str_replace('\/','/',json_encode($response));
}
   	$response["message"] = "success";
	echo json_encode($response);
} else {
    // user details missing
	//echo "Error";
	$response["message"] = "Due to some techincal problem data not saved.";
	echo json_encode($response);
}
?>