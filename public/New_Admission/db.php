<?php
set_time_limit(0);
// $host = '192.168.5.191';
// $username = 'root';
// $password = 'Triz$2o2o';
// $database = 'triz_erp_2';
$host = '192.168.0.2';
$username = 'root';
$password = 'Triz@R@jesh';
$database = 'triz_erp_2';

$cn = mysqli_connect($host, $username, $password) or die("asd");

mysqli_select_db($cn, $database) or die("database");
?>