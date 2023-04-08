<?php

//include_once("/var/www/html/db.config.inc.php");
$host="202.47.117.131";
$db_user="dev_db";
$db_password="2021@Triz";


 $servername=$host; 
 $username=$db_user;
 $password=$db_password;

$dbname = "triz_lms";

$token = '600c496313948a4b7530507ed9872d25';
$domainname = 'http://202.47.117.131/ssalms';
// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
// Check connection

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
mysqli_connect($servername,$username,$password);
mysqli_select_db($dbname); 

$servername_erp="150.129.172.214";
$username_erp="triz_rajkot";
$password_erp="Triz@Raj$2017";
$dbname_erp="murlidhar_cms";
$prod_schema="murlidhar_solution";
$sub_institute_id=1;
$syear=2020;

$conn_erp = new mysqli($servername_erp, $username_erp, $password_erp, $dbname_erp);
if ($conn_erp->connect_error) 
{
    die("Connection failed: " . $conn->connect_error);
}
else
{
	mysqli_connect($servername_erp,$username_erp,$password_erp);
	mysqli_select_db($dbname_erp); 
	//echo "ERP Connection Done";
}

$prod_erp = new mysqli($servername_erp, $username_erp, $password_erp, $prod_schema);
if ($prod_erp->connect_error) 
{
    die("Connection failed: " . $conn->connect_error);
}
else
{
	mysqli_connect($servername_erp,$username_erp,$password_erp);
	mysqli_select_db($prod_schema); 
	//echo "ERP Connection Done";
}
$is_login_otp="Y";

?>