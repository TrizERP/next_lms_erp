<?php
error_reporting(0);
if (!defined('SENAYAN_BASE_DIR')) {
    // main system configuration
    require '../../../sysconfig.inc.php';
    // start the session
    require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
     
}
$q=$_GET["b"];
echo 'select mt.topic from mst_subject_type AS mst Left join mst_topic AS mt on mt.topic_id=mst.topic_id where mst.subject_type_name='.$q.'';

$subject=$dbs->query('select mt.topic_id as topic_id,mt.topic as topic from mst_subject_type AS mst Left join mst_topic AS mt on mt.topic_id=mst.topic_id where mst.subject_type_name='.$q.'');
$topic='';
?>
<!-- <select name='subject' onchange='getsubsubjectofsubject(this.value);'> -->
<select name='type'>
<?php
echo "<OPTION value='0'>-select subject-</OPTION>";
$selected=$selected;
while($row=$subject->fetch_assoc())
{	

	$select = $selected==$row['topic_id'] ?' selected' : null;
	
	echo '<option value='.$row['topic_id'].' selected>'.$row['topic'].'</option>';

	
}


?>
</select>

