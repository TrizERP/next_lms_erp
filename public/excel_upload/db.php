<?php
set_time_limit(0);
$host = '192.168.0.2';
$username = 'dev_db';
$password = 'dev@sql';
$database = 'triz_erp_21';
$syear = "2023";

$cn = mysqli_connect($host, $username, $password) or die("Check DB Connection");

mysqli_select_db($cn, $database) or die("Connection OK, but Database not found");
?>