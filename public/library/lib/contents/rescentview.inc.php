<?php
require LIB_DIR.'member_logon.inc.php';
require LIB_DIR.'biblio_list.inc.php';
$biblio_list = new biblio_list($dbs);
error_reporting(0);
echo '<table border=0 align=center width=100% align=center>';
echo '<tr><td  style="font-size:19px;" align="center" bgcolor=lightgray>Recently-Viewed</td></tr><tr><td width=100%>';
$biblio_list->getDocumentList($sysconf['opac_result_num']);
echo '</td></tr></table>';

?>



        