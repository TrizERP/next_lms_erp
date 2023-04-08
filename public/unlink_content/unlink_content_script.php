<?php
$host= "192.168.0.2";
$db_user="root";
$db_password="Triz@R@jesh";

$database='triz_erp_2';
$syear = '2021';
$sub_institute_id = '1';

$cn = mysqli_connect($host, $db_user, $db_password) or die("asd");
mysqli_set_charset($cn,"utf8");
mysqli_select_db($cn, $database) or die("Database not found");

$where =" sub_institute_id = '1' AND syear = '2021' AND standard_id = 39";
//$sql = "SELECT * FROM lms_teacher_resource WHERE sub_institute_id = '".$sub_institute_id."' AND syear = '".$syear."' AND standard_id = 40";

$sql = "SELECT * FROM content_master WHERE".$where;

//echo $sql;die();

$query = mysqli_query($cn, $sql) or die(mysqli_error($cn));

while ($data = mysqli_fetch_assoc($query)) {

    $filepath = "../storage". $data['file_folder'] ."/". $data['filename'];

    if(!empty($data['filename']) && isset($data['filename']) && $data['filename'] != '') {
        unlink($filepath);

        //$sql = "DELETE FROM content_master WHERE id= '".$data['id']."' ".$where;
        //mysqli_query($cn, $sql) or die(mysqli_error($cn));
        
        echo "<br/>deleted file ------> ".$filepath;
    }
    else
        echo "<br/>NOT FOUND IN FODLER ------> ".$filepath;   
}
?>