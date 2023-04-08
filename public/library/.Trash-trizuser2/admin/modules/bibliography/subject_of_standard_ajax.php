<?php
error_reporting(0);
if (!defined('SENAYAN_BASE_DIR')) {
    // main system configuration
    require '../../../sysconfig.inc.php';
    // start the session
    require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
     
}
$q=$_GET["a"];

$subject=$dbs->query('SELECT DISTINCT(bt.topic_id),mt.topic FROM biblio_standard AS bs 
		      LEFT JOIN biblio_topic AS bt ON bt.biblio_id=bs.biblio_id 
		      LEFT JOIN mst_topic AS mt ON mt.topic_id=bt.topic_id
		     WHERE bs.standard_id='.$q.'');
$topic='';
?>
<!-- <select name='subject' onchange='getsubsubjectofsubject(this.value);'> -->
<select name='subjectajax' onchange="getsubsubjectofsubject(this.value);">
<?php
echo "<OPTION value='0'>-select subject-</OPTION>";
$selected=$selected;
while($row=$subject->fetch_assoc())
{	

	$select = $selected==$row['topic_id'] ?' selected' : null;
	if($_REQUEST['subjectajax']==$row['topic_id'])
	{
	echo '<option value='.$row['topic_id'].' selected>'.$row['topic'].'</option>';
	}
	else
	{echo "hhh";
	echo '<option value='.$row['topic_id'].'>'.$row['topic'].'</option>';
	}
}


?>
</select>
<?php echo '<span id="ajax_output_sub_subject_4subject"></span>'; ?>
