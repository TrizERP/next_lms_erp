<?php
$host = $_REQUEST['db_host'];
$username = $_REQUEST['db_user'];
$password = $_REQUEST['db_password'];
$database = $_REQUEST['db_hrms'];

$cn = mysqli_connect($host, $username, $password) or die("adsad");
mysqli_select_db($cn, $database) or die("database");

$first_name = ($_REQUEST['first_name'] != "") ? $_REQUEST['first_name'] : '';
$middle_name = ($_REQUEST['middle_name'] != "") ? $_REQUEST['middle_name'] : '';
$last_name = ($_REQUEST['last_name'] != "") ? $_REQUEST['last_name'] : '';
$user_name = ($_REQUEST['user_name'] != "") ? $_REQUEST['user_name'] : '';

$sql_hrms = "INSERT INTO hs_hr_employee (emp_firstname,emp_middle_name,emp_lastname,employee_id)
			VALUES
			('".$first_name."','".$middle_name."','".$last_name."',
			(SELECT  ifnull(max(emp_number)+1,1) as employee_id from hs_hr_employee as e))
			";
$insSql = mysqli_query($cn, $sql_hrms);
$USER_EMP_NO = mysqli_insert_id($cn);

$sql = "SELECT max(convert(SUBSTRING_INDEX(id,'USR',-1),unsigned)) as LAST_USER_ID
	    FROM hs_hr_users"; 

$USER_NO_RET = mysqli_query($cn,$sql);
$USER_NO_RET = mysqli_fetch_assoc($USER_NO_RET);
$USER_ID = ($USER_NO_RET[LAST_USER_ID] + 1);                                             

if(strlen($USER_ID)==1)
{
	$USER_ID = "000".$USER_ID ;
}
else if(strlen($USER_ID)==2)
{
	$USER_ID = "00".$USER_ID ;
}
else if(strlen($USER_ID)==3)
{
	$USER_ID = "0".$USER_ID ;
}
else
{
	$USER_ID = $USER_ID ;
}
$USER_ID = "USR".$USER_ID;

$emp_password = md5($_REQUEST['password']);
$sql_hrms_users= "INSERT INTO hs_hr_users (id,user_name,user_password,first_name,last_name,
		  emp_number,is_admin,modified_user_id,status,userg_id)
		  VALUES
		  ('".$USER_ID."','".$user_name."','".$emp_password."','".$first_name."','".$last_name."',
		   '".$USER_EMP_NO."','No','".$USER_ID."','Enabled','USG002'
		  )
		";
mysqli_query($cn,$sql_hrms_users);
?>