<?php

/*if (!defined('SENAYAN_BASE_DIR')) {
    // main system configuration
    require '../../../sysconfig.inc.php';
    // start the session
    require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
     
}*/
?>
 
<!--<form method='post' action="<?php echo 'index.php?standard='.$_RQUEST['standard'].'&subject='.$_RQUEST['subject'].'&material_sub_type='.$_RQUEST['material_sub_type'].'&material_sub_type='.$_RQUEST['material_sub_type'].'&submit=submit'; ?>"> -->

<form>
<table>
<tr>
<td>
<!-- <select name="standard" style="width: 100%;" onchange="getsubjectofstandard(this.value);">-->
 <?php
//
//	$standard=$dbs->query('select standard_id,standard_name from mst_standard');
//	echo "<OPTION value='0'>-select standard-</OPTION>";
//	$selected=$selected;
//	while($row=$standard->fetch_assoc())
//	{	 $select = $selected==$row['standard_id'] ?' selected' : null;
//		if($_REQUEST['standard']==$row['standard_id'])
//		{
//		echo '<option value='.$row['standard_id'].' selected>'.$row['standard_name'].'</option>';
//		}
//		else
//		{
//			echo '<option value='.$row['standard_id'].'>'.$row['standard_name'].'</option>';
//		}
//	}
//
// ?>
<!--</select>--></td>
<?php echo '<td><span id="ajax_output_subject_4standard"></span></td>'; ?>
<?php 
$subject_visible=0;
$subject_visible ='<div id="subject_visible_id"></div>';
if($subject_visible != 1 )
{ echo '<td id="subject4standard">';
?>

<!--<select name="subject"  style="width: 100%;" onchange="getsubsubjectofsubject(this.value);"><?php
//$subject_type=$dbs->query('select topic_id,topic from mst_topic order by topic_id');
//echo "<OPTION value='0'>-select subject-</OPTION>";
//$selected=$selected;
//while($row=$subject_type->fetch_assoc())
//{	
//	 $select = $selected==$row['topic_id'] ?' selected' : null;
//	if($_REQUEST['subject']==$row['topic_id'] || $_REQUEST['subjectajax']==$row['topic_id'])
//	{
//		echo '<option value='.$row['topic_id'].' selected>'.$row['topic'].'</option>';
//	}
//	else
//	{
//		echo '<option value='.$row['topic_id'].'>'.$row['topic'].'</option>';
//	}
//}
  
?></select>-->
<?php echo "</td>";}?>
<?php echo '<td><span id="ajax_output_subject_4standard"></span></td>'; ?>
<input type=hidden name='material_sub_type' value=0>

<?php 
$sub_subject_visible=0;
$sub_subject_visible ='<div id="sub_subject_visible_id"></div>';
if($sub_subject_visible != 1 )
{ echo '<td id="subsubject4subject">';
?>
<!-- <select name='subjecttype' style="width: 100%;" ><?php
//$subject_type=$dbs->query('select subject_type_id,subject_type_name from mst_subject_type order by subject_type_id');
//echo "<OPTION value='0'>-select sub subject-</OPTION>";
//$selected=$selected;
//while($row=$subject_type->fetch_assoc())
//{	
//	$select = $selected==$row['material_sub_id'] ?' selected' : null;
//	if($_REQUEST['subjecttype']==$row['subject_type_id'])
//	{
//	echo '<option value='.$row['subject_type_id'].' selected>'.$row['subject_type_name'].'</option>';
//	}
//	else
//	{
//		echo '<option value='.$row['subject_type_id'].'>'.$row['subject_type_name'].'</option>';
//	}
//}
  
?></select>-->
<?php echo "</td>";}?>

<?php echo '<td><span id="ajax_output_sub_subject_4subject"></span></td>'; ?>

<?php if($_REQUEST['gmd_id']){?>
<td><select name="material_sub_type">
<?php  
$material_subtype=$dbs->query('SELECT material_sub_id,material_sub_name FROM mst_material_sub_type WHERE gmd_id='.$_REQUEST['gmd_id'].'');
echo "<OPTION value='0'>-select material sub type-</OPTION>";
$selected=$selected;
while($row=$material_subtype->fetch_assoc())
{
	$select = $selected==$row['material_sub_id'] ?' selected' : null;
	if($_REQUEST['material_sub_type']==$row['material_sub_id'])
	{
	echo '<option value='.$row['material_sub_id'].' selected>'.$row['material_sub_name'].'</option>';
	}
	else
	{
	echo '<option value='.$row['material_sub_id'].'>'.$row['material_sub_name'].'</option>';
	}
}	
?>
</select></td>
<?php }?>
<?php if($_REQUEST['material_sub_type']){?>
<td><select name="material_sub_type">
<?php  
$gmdid=$dbs->query('SELECT gmd_id FROM mst_material_sub_type WHERE material_sub_id='.$_REQUEST['material_sub_type'].'');
while($row=$gmdid->fetch_assoc())
{
$material_subtype=$dbs->query('SELECT material_sub_id,material_sub_name FROM mst_material_sub_type WHERE gmd_id='.$row['gmd_id'].'');
}
echo "<OPTION value='0'>-select material sub type-</OPTION>";
$selected=$selected;
while($row=$material_subtype->fetch_assoc())
{
	$select = $selected==$row['material_sub_id'] ?' selected' : null;
	if($_REQUEST['material_sub_type']==$row['material_sub_id'])
	{
	echo '<option value='.$row['material_sub_id'].' selected>'.$row['material_sub_name'].'</option>';
	}
	else
	{
	echo '<option value='.$row['material_sub_id'].'>'.$row['material_sub_name'].'</option>';
	}
}
?>
</select></td>
<?php }?>
<!--<td><input type='submit' name='submit' value='submit'></td>-->
</tr>
</table>
</form>





