<?php

//include_once("/var/www/html/db.config.inc.php");
$host="localhost";
$db_user="dev_db";
$db_password="Triz@2020";


 $servername=$host; 
 $username=$db_user;
 $password=$db_password;

$dbname = "triz_lms";

$token = '600c496313948a4b7530507ed9872d25';
$domainname = 'http://202.47.117.131/lms';
// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
// Check connection

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
mysqli_connect($servername,$username,$password);
mysqli_select_db($dbname); 

$servername_erp="150.129.172.214";
$username_erp="triz_erp";
$password_erp="Triz@2019$04";
$dbname_erp="triz_erp_2";
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
$is_login_otp="Y";

?>