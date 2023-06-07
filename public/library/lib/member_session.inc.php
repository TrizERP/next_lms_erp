<?php

session_start();

//include '../../setsession.php';
include '../../connection.php';

$table = "";
$field = "";

if ($_SESSION["USER_GROUP_ID"] == 1 && ($_SESSION["HOD"] && in_array("113", explode(",", $_SESSION["HOD"])))) {
    $table = "tblstaff";
    $field = "staff_id";
} else if ($_SESSION['USER_GROUP_ID'] == 1) {
    $table = "tbladmin";
    $field = "admin_id";
}
else if($_SESSION['USER_GROUP_ID'] ==2)
{
    $table="tblstaff";
    $field="staff_id";
}
else if($_SESSION['USER_GROUP_ID'] ==3)
{
    $table="tblstudent";
    $field="student_id";
}
else if($_SESSION['USER_GROUP_ID'] ==4)
{
    $table="tblparent";
    $field="parent_id";
}

if(!$_SESSION['DUSER_ID'])// && !$_SESSION['STUDENT_ID'] && strpos($_SERVER['PHP_SELF'],'index.php')===false)
{
        header('Location: ../../index.php');
        exit;
}
else {
    $ipaddress = $_SERVER["REMOTE_ADDR"];//check session to work on single machine
    $qry = "select * from $table t
    inner join tblinstitute ins on ins.institute_id=t.institute_id
    where current_ip='$ipaddress' and $field='$_SESSION[ID]' and institute_type=$_SESSION[INSTITUTE_TYPE]";

    $res = mysqli_query($qry);

    if (mysql_num_rows($res) > 0) {
        $qry = "select * from $table t
          inner join tblinstitute ins on ins.institute_id=t.institute_id
          where active_time < DATE_SUB(NOW(),INTERVAL 15 MINUTE) and $field='$_SESSION[ID]' and institute_type=$_SESSION[INSTITUTE_TYPE]";

        $res = mysqli_query($qry);

        if (mysql_num_rows($res) > 0)
            header("location:../../logout.php");          //logout.php
    } else {
        header("location:../../dashboard.php");       //index.php
    }

    $sql = "update $table t
    inner join tblinstitute ins on ins.institute_id=t.institute_id
    set active_time=sysdate()
    where $field='$_SESSION[ID]' and institute_type=$_SESSION[INSTITUTE_TYPE]";
    mysqli_query($sql);
}
?>
