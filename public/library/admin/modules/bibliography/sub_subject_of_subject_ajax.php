<?php
error_reporting(0);
if (!defined('SENAYAN_BASE_DIR')) {
    // main system configuration
    require '../../../sysconfig.inc.php';
    // start the session
    require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
     
}
$q=$_GET["b"];

$sub_subject=$dbs->query('SELECT subject_type_id,subject_type_name FROM mst_subject_type WHERE topic_id='.$q.'');

?>
<select name='subjecttype'>
<?php
echo "<OPTION value='0'>-select sub subject-</OPTION>";
while($row=$sub_subject->fetch_assoc())
{	
	echo '<option value='.$row['subject_type_id'].'>'.$row['subject_type_name'].'</option>';
}


?>
 </select>
