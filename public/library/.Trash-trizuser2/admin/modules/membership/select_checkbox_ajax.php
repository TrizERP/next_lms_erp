<?php
if (!defined('SENAYAN_BASE_DIR')) {
    // main system configuration
    require '../../../sysconfig.inc.php';
    // start the session
    require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
     
}
require SIMBIO_BASE_DIR.'simbio_GUI/form_maker/simbio_form_table_AJAX.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
 $chechboxselect = $_GET["check"];
$select=$dbs->query('select issue,checkin,reserve from mst_member_type where member_type_id='.$chechboxselect.'');
$rec_d='';
$rec_d1='';
$rec_d2='';
while($row=$select->fetch_assoc())
{
	 $rec_d.=$row['issue'];
	$rec_d1.=$row['checkin'];
	$rec_d2.=$row['reserve'];
}
 $issue[]=array('1',__('Issue'));
    $checkin[]=array('1',__('Return'));
    $reserve[]=array('1',__('Reserve'));
$form = new simbio_form_table_AJAX('main', $_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'], 'post');
//$form->submit_button_attr = ' value=""';
$form->table_attr = 'align="center" id="dataList"  border=0 cellpadding="5" cellspacing="0"';
$form->table_header_attr = 'class="altermain" style="font-weight: bold;"';
$form->table_content_attr = 'class="altermain2"';
$str_input = simbio_form_element::checkbox('issue', $issue, $rec_d['issue'], 'style="width:5%;"');
$str_input .= simbio_form_element::checkbox('checkin', $checkin, $rec_d1['checkin'], 'style="width:5%;"');
$str_input .= simbio_form_element::checkbox('reserve', $reserve, $rec_d2['reserve'], 'style="width:5%;"');
$form->addAnything(__('Member messaging preferences '), $str_input);

echo $form->printOut();





?>
