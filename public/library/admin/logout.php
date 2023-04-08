<?php
// required file
//require '../sysconfig.inc.php';
// start the session
session_start();
unset ($_SESSION['uid']);
unset ($_SESSION['uname']);
unset ($_SESSION['realname']);
unset ($_SESSION['groups']);
unset ($_SESSION['logintime']);

header("location:../../../dashboard.php");


//require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
//// write log
//utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'system', $_SESSION['realname'].' Log Out from application from address '.$_SERVER['REMOTE_ADDR']);
//$id = $_SERVER['REMOTE_ADDR']; 
//$id = $id.".php";
//$myFile = SENAYAN_BASE_DIR.$id;
//unlink($myFile);
////started added by parth 28/7/2011
////ended added by parth 28/7/2011
//// redirecting pages
//$msg = '<script type="text/javascript">';
//if ($sysconf['logout_message']) {
//    $msg .= 'alert(\''.__('You Have Been Logged Out From Library Automation System').'\');';
//}
////comment by iresh on 22-1-2011 $msg .= 'location.href = \''.SENAYAN_WEB_ROOT_DIR.'index.php?p=login\';';
///*added by iresh on 22-1-2011 */
//$msg .= 'location.href = \''.SENAYAN_WEB_ROOT_DIR.'index.php\';';
////$msg .= 'location.href = \''.SENAYAN_WEB_ROOT_DIR.'index.php?p=member&logout=1\';';
//$msg .= '</script>';
//
//// unset admin cookie flag
//setcookie('admin_logged_in', true, time()-86400, SENAYAN_WEB_ROOT_DIR);
//// completely destroy session cookie
//simbio_security::destroySessionCookie($msg, SENAYAN_SESSION_COOKIES_NAME, SENAYAN_WEB_ROOT_DIR.'admin/', true);
////header('Location: index.php');

?>
