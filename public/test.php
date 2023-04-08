<?php
set_time_limit(0);
$host = '150.129.172.214';
$username = 'triz_erp';
$password = 'Triz@2019$04';
$database = 'triz_erp_2';
$syear = "2019";

$cn = mysqli_connect($host, $username, $password) or die("asd");

mysqli_select_db($cn, $database) or die("database");

// echo '<pre>';
// print_r($_REQUEST);

$get_info = mysqli_query($cn, "SELECT * FROM tblclient WHERE id = '".$_REQUEST['college_id']."'");

$RET = mysqli_fetch_assoc($get_info);

$lib_db_name = $RET['db_library'];
// $create_db_sql = "CREATE DATABASE $db_name";
// DBQuery($create_db_sql);
duplicate('millennium_library',$lib_db_name,$cn);

function duplicate($originalDB, $newDB,$cn) 
{
    // echo $originalDB;
    // echo $newDB;
    $db_check = mysqli_select_db ($cn,$originalDB);
    $getTables =  mysqli_query($cn,"SHOW TABLES") or die("No Tables Available");   
    $originalDBs = array();
    while($row = mysqli_fetch_row( $getTables )) {
        $originalDBs[] = $row[0];
    }
    mysqli_query($cn,"CREATE DATABASE  IF NOT EXISTS `$newDB`") or die("NO RIGHTS");  
    foreach($originalDBs as $tab ) {
        mysqli_select_db($cn,$newDB) or die("CAN't select database");
        mysqli_query($cn,"CREATE TABLE $tab LIKE ".$originalDB.".".$tab) or die("Can't select table");
    }
}

