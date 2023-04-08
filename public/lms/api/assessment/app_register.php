<?php
require("config.php");
//echo "hi=".$_REQUEST["imei_no"]."---".$_REQUEST["regId"];die();
error_reporting(0);
//print_r($_REQUEST);
$json = array();

$cur_version = $_REQUEST["cur_version"];
$new_version = $_REQUEST["new_version"];
/**
 * Registering a user device
 * Store reg id in users table
 */
  if (isset($_REQUEST["imei_no"]) && isset($_REQUEST["regId"])) 
  {
// if (isset($_POST["name"]) && isset($_POST["email"]) && isset($_POST["regId"])) {
    $name =$_REQUEST["imei_no"];
    $gcm_regid =$_REQUEST["regId"];// GCM Registration ID
	$user_id =$_REQUEST["user_id"];	
    // Store user details in db
   // include_once 'db_functions.php';
   $sql = " select * from gcm_users where imei_no='".$name."' ";
   $res=mysql_query($sql);
   if (mysql_num_rows($res)>0)
	{
		$sql_update = "update gcm_users set gcm_regid = '".$gcm_regid."',current_version = '".$cur_version."',new_version = '".$new_version."',created_on=now() where imei_no='".$name."' ";
		mysql_query($sql_update);
	}
	else
   {
   	$result = "INSERT INTO gcm_users(imei_no,gcm_regid,user_id,created_on,current_version,new_version) VALUES('$name', '$gcm_regid','$user_id',now(),'$cur_version','$new_version')";
		
     //   echo $result;
		 mysql_query($result);
	}
  //  include_once 'GCM.php';

   // $db = new DB_Functions();
   // $gcm = new GCM();

    //$res = $db->storeUser($name, $email, $gcm_regid);

  //  $registatoin_ids = array($gcm_regid);
  //  $message = array("product" => "shirt");

  //  $result = $gcm->send_notification($registatoin_ids, $message);

   // echo $result;
   	$response["message"] = "success";
	echo json_encode($response);
} else {
    // user details missing
	//echo "Error";
	$response["message"] = "Due to some techincal problem data not saved.";
	echo json_encode($response);
}
?>