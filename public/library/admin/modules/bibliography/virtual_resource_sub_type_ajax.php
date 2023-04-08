<?php
error_reporting(0);
if (!defined('SENAYAN_BASE_DIR')) {
    // main system configuration
    require '../../../sysconfig.inc.php';
    // start the session
    require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
     
}
echo $q=$_GET["q"];
//echo $_SESSION["iresh"]=$q;
//echo "<input type=text value='".$_SESSION['iresh']."'>";


require SENAYAN_BASE_DIR.'admin/default/session_check.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/form_maker/simbio_form_table_AJAX.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/paging/simbio_paging.inc.php';

$form = new simbio_form_table_AJAX('main', $_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'], 'post');
//$form->submit_button_attr = ' value=""';
$form->table_attr = 'align="center" id="dataList"  border=0 cellpadding="5" cellspacing="0"';
$form->table_header_attr = 'class="altermain" style="font-weight: bold;"';
$form->table_content_attr = 'class="altermain2"';
$sql_sub_material = $dbs->query("SELECT gmd_id,material_sub_name FROM mst_material_sub_type WHERE gmd_id='".$q."'");
$sql_sub_material_resource=array('N/A');
while($row = $sql_sub_material->fetch_row())
{
	$sql_sub_material_resource[]=array($row[0],$row[1]);
}  
print_r($sql_sub_material_resource);
$form->addSelectList('materialsubtype', __('Material Sub Type Code').'*',$sql_sub_material_resource, $rec_d['gmd_id'], 'style="width: 140px;" maxlength="30"');

?>
