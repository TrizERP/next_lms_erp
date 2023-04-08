<?php

/*if (!defined('SENAYAN_BASE_DIR')) {
    // main system configuration
    require '../../../sysconfig.inc.php';
    // start the session
    require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
     
}*/
?>
 
<!--<form method='post' action="<?php echo 'index.php?standard='.$_RQUEST['standard'].'&subject='.$_RQUEST['subject'].'&material_sub_type='.$_RQUEST['material_sub_type'].'&material_sub_type='.$_RQUEST['material_sub_type'].'&submit=submit'; ?>"> -->
<form action='index.php'>
<table align=center>
<tr>
<td>
 <select name="standard" style="width: 20%;" onchange="getsubjectofstandard(this.value);">
 <?php

	$standard=$dbs->query('select standard_id,standard_name from mst_standard');
	echo "<OPTION value='0'>-select standard-</OPTION>";
	$selected=$selected;
	while($row=$standard->fetch_assoc())
	{	 $select = $selected==$row['standard_id'] ?' selected' : null;
		if($_REQUEST['standard']==$row['standard_id'])
		{
		echo '<option value='.$row['standard_id'].' selected>'.$row['standard_name'].'</option>';
		}
		else
		{
			echo '<option value='.$row['standard_id'].'>'.$row['standard_name'].'</option>';
		}
	}

 ?>
</select></td>
<?php echo '<div><span id="ajax_output_subject_4standard"></span></div>'; ?>
<?php 
$subject_visible=0;
$subject_visible ='<div id="subject_visible_id"></div>';
if($subject_visible != 1 )
{ echo '<div id="subject4standard">';
?>

<select name="subject"  style="width: 20%;"><?php
$subject_type=$dbs->query('select topic_id,topic from mst_topic');
echo "<OPTION value='0'>-select subject-</OPTION>";
$selected=$selected;
while($row=$subject_type->fetch_assoc())
{	
	 $select = $selected==$row['topic_id'] ?' selected' : null;
	if($_REQUEST['subject']==$row['topic_id'] || $_REQUEST['subjectajax']==$row['topic_id'])
	{
		echo '<option value='.$row['topic_id'].' selected>'.$row['topic'].'</option>';
	}
	else
	{
		echo '<option value='.$row['topic_id'].'>'.$row['topic'].'</option>';
	}
}
  
?></select>
<?php echo "</div>";}?>
<?php echo '<span id="ajax_output_subject_4standard"></span>'; ?>
<input type=hidden name='material_sub_type' value=0>
<!--
<?php 
$sub_subject_visible=0;
$sub_subject_visible ='<div id="sub_subject_visible_id"></div>';
if($sub_subject_visible != 1 )
{ echo '<div id="subsubject4subject">';
?>
 <select  style="width: 20%;"><?php
//$subject_type=$dbs->query('select subject_type_id,subject_type_name from mst_subject_type');
//while($row=$subject_type->fetch_assoc())
//{	
	echo "<OPTION value=''>N/A</OPTION>";
	echo '<option value='.$row['subject_type_id'].'>'.$row['subject_type_name'].'</option>';
	
//}
  
?></select>
<?php echo "</div>";}?>

<?php echo '<span id="ajax_output_sub_subject_4subject"></span>'; ?> -->

<?php if($_REQUEST['gmd_id']){?>
<select name="material_sub_type">
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
</select>
<?php }?>
<?php if($_REQUEST['material_sub_type']){?>
<select name="material_sub_type">
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
</select>
<?php }?>
<input type='submit' name='submit' value='submit'>
</td></tr>
</table>
</form>

<?php
/*

require SENAYAN_BASE_DIR.'admin/default/session_check.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/form_maker/simbio_form_table_AJAX.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/simbio_dbop.inc.php';
require SIMBIO_BASE_DIR.'simbio_FILE/simbio_file_upload.inc.php';

 // create new instance
   $form = new simbio_form_table_AJAX('mainForms', $_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'], 'post');
    $form->submit_button_attr = 'name="saveData" value="'.__('Save').'" class="button"';
    // form table attributes
    $form->table_attr = 'align="center" id="dataList" border=0 cellpadding="5" cellspacing="0"';
  // $form->table_header_attr = 'class="alterCell" style="font-weight: bold;"';
   // $form->table_content_attr = 'class="alterCell2"';


 $gmd_q = $dbs->query('SELECT gmd_id, gmd_name FROM mst_gmd where gmd_code="4"');
	//$gmd_options='';
       $gmd_options = array('N/A');
        while ($gmd_d = $gmd_q->fetch_row()) 
	//while ($gmd_d = $gmd_q->fetch_assoc())		
	{
            $gmd_options[] = array($gmd_d[0], $gmd_d[1]);
		//$gmd_options.=$gmd_d['gmd_id'].',';
        }
$ajax_exp = "ajaxFillSelect('".SENAYAN_WEB_ROOT_DIR."admin/AJAX_lookup_handler.php', 'mst_material_sub_type', 'gmd_id:material_sub_name:material_sub_id', 'materialsubid', $('gmdID').getValue())";

       if ($rec_d['material_sub_name']) {
            $mst_material_sub_type_options[] = array($rec_d['material_sub_id'],$rec_d['material_sub_name']);
        }
	$mst_material_sub_type_options[] = array('0', __('Material Sub Type'));
        // string element
       
       
//$str_input .= simbio_form_element::textField('text', 'material_sub_search_str', $rec_d['publisher_name'], 'style="width: 45%;" onkeyup="'.$ajax_exp.'"');
$str_input = simbio_form_element::selectList('gmdID', $gmd_options, $rec_d['gmd_id'],'onchange="ccc(this.value);'.$ajax_exp.'"');
$str_input .= '&nbsp;';
$str_input .= simbio_form_element::selectList('materialsubid', $mst_material_sub_type_options, '', 'style="width: 20%;"');
$str_input .= '<tr><td id="txtHint" class="alterCell2" colspan="3"></td></tr>';
 // $str_input .= '<tr><td id="txtsubmaterial" class="alterCell2" colspan="3"></td></tr>';  
       // $str_input .= '&nbsp;';
       //$str_input .= simbio_form_element::textField('text', 'plc_search_str', $rec_d['place_name'], 'style="width: 45%;" onkeyup="'.$ajax_exp.'"');
 $form->addAnything(__('Material Sub Type'), $str_input);



  echo $form->printOut();

*/
?>












