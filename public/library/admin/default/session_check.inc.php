<?php
session_start();
// be sure that this file not accessed directly
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    die();
}

// check session


$unauthorized = !isset($_SESSION['uid']) && !isset($_SESSION['uname']) && !isset($_SESSION['realname']);

if ($unauthorized)
    {
    $msg = '<script type="text/javascript">'."\n";
    //coment by iresh on 7-2-2011 $msg .= 'alert(\''.__('You are not authorized to view this section').'\');'."\n";
   //coment by iresh on 7-2-2011 
    //$msg .= 'top.location.href = \''.SENAYAN_WEB_ROOT_DIR.'index.php?p=login\';'."\n";
  /*added by iresh on 7-2-2011*/  
    $msg .= 'top.location.href = \''.SENAYAN_WEB_ROOT_DIR.'index.php\';'."\n";
   
    $msg .= '</script>'."\n";
    // unset cookie admin flag
    setcookie('admin_logged_in', false, time()-86400, SENAYAN_WEB_ROOT_DIR);
    //simbio
    //_security::destroySessionCookie($msg, SENAYAN_SESSION_COOKIES_NAME, SENAYAN_WEB_ROOT_DIR.'admin', true);
}

// checking session checksum
$unauthorized = $_SESSION['checksum'] != md5($_SERVER['SERVER_ADDR'].SENAYAN_BASE_DIR.'admin');
if ($unauthorized) {
    $msg = '<div style="padding: 5px; border: 1px dotted #FF0000; color: #FF0000;">';
    $msg .= __('You are not authorized to view this section');
    $msg .= '</div>'."\n";
    // unset cookie admin flag
    setcookie('admin_logged_in', true, time()-86400, SENAYAN_WEB_ROOT_DIR);
   // simbio_security::destroySessionCookie($msg, SENAYAN_SESSION_COOKIES_NAME, SENAYAN_WEB_ROOT_DIR.'admin', true);
}


//mysql_close();
include '../../../connection.php';

//$res=$dbs->query($sql);
$table="";$field=""; 

if($_SESSION['USER_GROUP_ID'] == 1)
{ 
    $table="tbladmin";
    $field="admin_id";

    
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

        header('Location: ../../../index.php');
        exit;
}
else
{       
//    $ipaddress=$_SERVER["REMOTE_ADDR"];//check session to work on single machine  
//    $sql="select * from triz_solution.$table t 
//    inner join triz_solution.tblinstitute ins on ins.institute_id=t.institute_id
//    where current_ip='$ipaddress' and $field='$_SESSION[ID]' and institute_type=$_SESSION[INSTITUTE_TYPE]"; 
//
//    $res=mysql_query($sql);   
//        
//    if(mysql_num_rows($res)>0)
//    {
//          $sql="select * from $table t
//          inner join triz_solution.tblinstitute ins on ins.institute_id=t.institute_id
//          where active_time < DATE_SUB(NOW(),INTERVAL 15 MINUTE) and $field='$_SESSION[ID]' and institute_type=$_SESSION[INSTITUTE_TYPE]";  
//          $res=mysql_query($sql);
//          
//          if(mysql_num_rows($res)>0)          
//               header("location:../../../logout.php");          //logout.php
//    }
//    else              
//        header("location:../../../dashboard.php");       //index.php    
    
    // $sql="update ".$inte_schema.".".$table." t 
    // inner join ".$inte_schema.".tblinstitute ins on ins.institute_id=t.institute_id
    // set active_time=sysdate()     
    // where $field='$_SESSION[ID]' and institute_type=$_SESSION[INSTITUTE_TYPE]";
 
    // $res= mysql_query($sql);
    // mysql_close();
}

?>
