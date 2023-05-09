<?php
set_time_limit(0);
$host = '202.47.117.220';
$username = 'dev_db';
$password = 'dev@sql';
$database = 'development_erp';
$syear = "2023";

$cn = mysqli_connect($host, $username, $password) or die("Check DB Connection");

mysqli_select_db($cn, $database) or die("Connection OK, but Database not found");
?>