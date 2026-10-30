<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; } // one-off maintenance script, not web-reachable
set_time_limit(0);
require_once __DIR__ . '/../bootstrap/legacy_env.php';
$host = legacy_env('LEGACY_DB_HOST');
$username = legacy_env('LEGACY_DB_USER');
$password = legacy_env('LEGACY_DB_PASSWORD');
$database = legacy_env('LEGACY_DB_NAME');
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

