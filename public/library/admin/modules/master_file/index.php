<?php
if (!defined('SENAYAN_BASE_DIR')) {
    // main system configuration
    require '../../../sysconfig.inc.php';
    // start the session
    require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
}

require SENAYAN_BASE_DIR.'admin/default/session_check.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/form_maker/simbio_form_table_AJAX.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/simbio_dbop.inc.php';

// privileges checking
$can_read = utility::havePrivilege('master_file', 'r');
$can_write = utility::havePrivilege('master_file', 'w');

if (!$can_read) {
    die('<div class="errorBox">'.__('You don\'t have enough privileges to view this section').'</div>');
}

/* RECORD OPERATION */
if (isset($_POST['saveData']) AND $can_read AND $can_write) {
    $gmdCode = trim(strip_tags($_POST['gmdCode']));
    $gmdName = trim(strip_tags($_POST['gmdName']));
    $activeinactive = trim(strip_tags($_POST['activeinactive']));
    // check form validity
    if (empty($gmdCode) OR empty($gmdName)) {
//added by parth 8/7/2011
        utility::jsAlert(__('Material Type Code And Name can\'t be empty'));
//ended addition by parth 8/7/2011
        exit();
    } else {
        $data['material_resource_id'] = $dbs->escape_string($gmdCode);
        $data['gmd_name'] = $dbs->escape_string($gmdName);
        $data['input_date'] = date('Y-m-d');
        $data['last_update'] = date('Y-m-d');
        $data['active_inactive'] = $dbs->escape_string($activeinactive);

        // create sql op object
        $sql_op = new simbio_dbop($dbs);
        if (isset($_POST['updateRecordID'])) {
            /* UPDATE RECORD MODE */
            // remove input date
if($data['material_resource_id']=="--Select--")
{
utility::jsAlert(__('Please Select Material Resource Type'));
}
else
{
$checkflag1=0;	
            unset($data['input_date']);
            // filter update record ID
            $updateRecordID = $dbs->escape_string(trim($_POST['updateRecordID']));
$qry1 = 'SELECT * from mst_gmd where gmd_id !='.$updateRecordID;
$rec_f1 = $dbs->query('SELECT * from mst_gmd where gmd_id !='.$updateRecordID);

	while($row1 = $rec_f1->fetch_assoc())
		{
$qry2 = 'SELECT * from mst_gmd where gmd_id ='.$row1['gmd_id'];
  $rec_f2 = $dbs->query('SELECT * from mst_gmd where gmd_id = '.$row1['gmd_id']);
			while($row2 = $rec_f2->fetch_assoc())
			{

			if($data['gmd_name']==$row2['gmd_name'])
				{			
				$checkflag1=1;	
				}
			}
		}
if($checkflag1==1)
{
utility::jsAlert(__('Duplicate Record Can not inserted'));
}
else
{
            // update the data
            $update = $sql_op->update('mst_gmd', $data, 'gmd_id='.$updateRecordID);
            if ($update) {
                utility::jsAlert(__('Material Type Successfully Updated'));
                echo '<script type="text/javascript">parent.setContent(\'mainContent\', parent.getPreviousAJAXurl(), \'post\');</script>';
            } else { utility::jsAlert(__('GMD Data FAILED to Updated. Please Contact System Administrator')."\nDEBUG : ".$sql_op->error); }
}
}
            exit();
        } else {
            /* INSERT RECORD MODE */
            // insert the data
if($data['material_resource_id']=="--Select--")
{
utility::jsAlert(__('Please Select Material Resource Type'));
}
else
{
$checkflag=0;	
	
$qry = 'SELECT * from mst_gmd where gmd_name ="'.$data['gmd_name'].'"';
$rec_f = $dbs->query('SELECT * from mst_gmd where gmd_name ="'.$data['gmd_name'].'" AND material_resource_id ='.$data['material_resource_id']);

	while($row = $rec_f->fetch_assoc())
		{
			//utility::jsAlert($data['material_resource_name']);
			$checkflag=1;	
		}
if($checkflag==1)
{
utility::jsAlert(__('Duplicate Record Can not inserted'));
}
else
{
            if ($sql_op->insert('mst_gmd', $data)) {
         
	         utility::jsAlert(__('Material Type Successfully Saved'));
	//$sql_material_resource_name=$dbs->query('select material_resource_name from mst_material_resource_type where material_resource_id='.$data['gmd_code'].'');
	//$row=$sql_material_resource_name->fetch_assoc();
	//if($row['material_resource_name']=='Physical Resources')
	//{
		//$sql=$dbs->query('ALTER TABLE mst_member_type ADD '.$data['gmd_name'].' VARCHAR(20)');
	//}
		
                echo '<script type="text/javascript">parent.setContent(\'mainContent\', \''.$_SERVER['PHP_SELF'].'\', \'post\');</script>';
            } else { utility::jsAlert(__('GMD Data FAILED to Save. Please Contact System Administrator')."\nDEBUG : ".$sql_op->error); }
}
}
            exit();
        }
    }
    exit();
} else if (isset($_POST['itemID']) AND !empty($_POST['itemID']) AND isset($_POST['itemAction'])) {
    if (!($can_read AND $can_write)) {
        die();
    }
    /* DATA DELETION PROCESS */
    $sql_op = new simbio_dbop($dbs);
    $failed_array = array();
    $error_num = 0;
    if (!is_array($_POST['itemID'])) {
        // make an array
        $_POST['itemID'] = array((integer)$_POST['itemID']);
    }
    // loop array
//added by Parth 8/7/2011
    foreach ($_POST['itemID'] as $itemID) {
         $itemID = (integer)$itemID;
	$checkflag = 0;
	$checkflag1 = 0;
	$rec_f = $dbs->query('SELECT gmd_id from biblio where gmd_id ='.$itemID);
		while($row = $rec_f->fetch_assoc())
		{
			$checkflag=1;	
		}
	$rec_f_new = $dbs->query('SELECT gmd_id from mst_material_sub_type where gmd_id ='.$itemID);
		while($row1 = $rec_f_new->fetch_assoc())
		{
			$checkflag1=1;	
		}
	if($checkflag==1 || $checkflag1==1)
	{
		$rec_name = $dbs->query('SELECT gmd_name from mst_gmd where gmd_id ='.$itemID);
		while($rownew = $rec_name->fetch_assoc())
		{
			$gmd_name_set = $rownew['gmd_name'];	
		}
		 utility::jsAlert(__('You can not Delete Material Type '.$gmd_name_set));
	}
	else
	{
		//utility::jsAlert(__('You can Delete it'));
        	if (!$sql_op->delete('mst_gmd', 'gmd_id='.$itemID)) {
       		     $error_num++;
       		 }
	}
    }
//ended addition by Parth 8/7/2011
    // error alerting
    if ($error_num == 0) {
        utility::jsAlert(__('All Selected Data successfully saved'));
        echo '<script type="text/javascript">parent.setContent(\'mainContent\', \''.$_SERVER['PHP_SELF'].'?'.$_POST['lastQueryStr'].'\', \'post\');</script>';
    } else {
        utility::jsAlert(__('Some or All Data NOT deleted successfully!\nPlease contact system administrator'));
        echo '<script type="text/javascript">parent.setContent(\'mainContent\', \''.$_SERVER['PHP_SELF'].'?'.$_POST['lastQueryStr'].'\', \'post\');</script>';
    }
    exit();
}
/* RECORD OPERATION END */

/* search form */
?>
<table  align=center>
<tr>
	<td valign=top>
	<?php
	$bradecum = '';       
        $basedir = basename(dirname(__FILE__));
        $bradecum = "<a href=javascript:void(0); onclick=javascript:new_set_home(); >Home</a><a class='' href=javascript:void(0); onclick=javascript:new_set('".$basedir."');>"; 
	$query = "select module_name from mst_module where module_path = '".$basedir."'";
	$set_query = $dbs->query($query);
	while($row=$set_query->fetch_assoc())
	{
                $_formated_module_name = ucwords(str_replace('_', ' ', $row['module_name']));
		$bradecum .= $_formated_module_name;
	}
$bradecum .= '</a>';
/*if(isset($_REQUEST['action']))
{
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'membership/index.php?action=detail class="headerText2">Add New Member</a>';
}
else
{
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'membership/index.php class="headerText2">View Member List</a>';
}*/
//$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'master_file/index.php class="headerText2">Resource Type List</a>';
echo $bradecum;
        ?>	
	</td>
</tr>
</table>
<table>
<tr>
	<td class="tab_menu_top">
                            <ul class="tabs"> 
<!--				<li>-->
<!--<a href="<?php //echo MODULES_WEB_ROOT_DIR; ?>master_file/material_resource_type.php" class="headerText2"><?php //echo __('Library Resource Type'); ?></a> </li>--><li>
<!--<a href="<?php //echo MODULES_WEB_ROOT_DIR; ?>master_file/index.php" class="headerText2"><?php //echo __('Resource Type List'); ?></a> </li>-->
<li>
<a href="<?php echo MODULES_WEB_ROOT_DIR; ?>master_file/material_sub_type.php" class="headerText2"><?php echo __('Resource Material Sub Type List'); ?></a></li></ul>
	</td>
</tr>
</table>
<fieldset class="menuBox">
    
<div class="menuBoxInner masterFileIcon">
    <?php echo strtoupper(__('Resource Type')); ?> - <a href="<?php echo MODULES_WEB_ROOT_DIR; ?>master_file/index.php?action=detail" class="headerText3"><?php echo __('Add Resource Type'); ?></a>
    &nbsp; <!--<a href="<?php //echo MODULES_WEB_ROOT_DIR; ?>master_file/index.php" class="headerText3"><?php// echo __('Resource Type List'); ?></a>-->
   <p class="only_border">&nbsp;</p>
    <form name="search" action="<?php echo MODULES_WEB_ROOT_DIR; ?>master_file/index.php" id="search" method="get" style="display: inline;"><?php echo __('Search'); ?> :
     <!--commnet by iresh on 25-1-2011  <input type="text" name="keywords" id="keywords" size="30" />-->
   <!-- added by iresh on 25-1-2011 --> <input type="text" name="keywords" id="keywords" width=140px/>
    <input type="submit" id="doSearch" value="<?php echo __('Search'); ?>" class="button" />
    </form>
</div>
</fieldset>
<?php
/* search form end */
/* main content */
if (isset($_POST['detail']) OR (isset($_GET['action']) AND $_GET['action'] == 'detail')) {
    if (!($can_read AND $can_write)) {
        die('<div class="errorBox">'.__('You don\'t have enough privileges to view this section').'</div>');
    }
    /* RECORD FORM */
   $itemID = (integer)isset($_POST['itemID'])?$_POST['itemID']:0;
   // $rec_q = $dbs->query('SELECT * FROM mst_gmd WHERE gmd_id='.$itemID);
    $rec_q = $dbs->query('SELECT g.gmd_id,g.material_resource_id,g.gmd_name AS gmd_name,g.last_update AS last_update,mrt.material_resource_id,mrt.material_resource_name AS material_resource_name FROM mst_gmd g 
   			  LEFT JOIN mst_material_resource_type mrt ON mrt.material_resource_id=g.material_resource_id 	                        WHERE g.gmd_id='.$itemID);
    $rec_d = $rec_q->fetch_assoc();

    // create new instance
    $form = new simbio_form_table_AJAX('mainForm', $_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'], 'post');
    $form->submit_button_attr = 'name="saveData" value="'.__('Save').'" class="button"';

    // form table attributes
    $form->table_attr = 'align="center" id="dataList" cellpadding="5" cellspacing="0"';
    $form->table_header_attr = 'class="alterCell" style="font-weight: bold;"';
    $form->table_content_attr = 'class="alterCell2"';

    // edit mode flag set
    if ($rec_q->num_rows > 0) {
        $form->edit_mode = true;
        // record ID for delete process
        $form->record_id = $itemID;
        // form record title
        $form->record_title = $rec_d['gmd_name'];
        // submit button attribute
        $form->submit_button_attr = 'name="saveData" value="'.__('Update').'" class="button"';
    }

    /* Form Element(s) */
    // gmd code
    //comment by iresh on 25-1-2011 $form->addTextField('text', 'gmdCode', __('GMD Code').'*', $rec_d['gmd_code'], 'style="width: 20%;" maxlength="3"');
    /*added by iresh on 25-1-2011 $form->addTextField('text', 'gmdCode', __('Material Type Code').'*', $rec_d['gmd_code'], 'style="width: 140px;" maxlength="30"');*/

//$sql_material_resource_type ="SELECT material_resource_name FROM mst_material_resource_type order by material_resource_id";
$sql_material = $dbs->query('SELECT material_resource_id,material_resource_name FROM mst_material_resource_type order by material_resource_id');
$sql_material_resource=array('--Select--');
while($row = $sql_material->fetch_row())
{
	$sql_material_resource[]=array($row[0],$row[1]);
}  

$form->addSelectList('gmdCode', __('Material Resource Type').'*',$sql_material_resource, $rec_d['material_resource_id'], 'style="width: 140px;" maxlength="30"');
    // gmd name
    //comment by iresh on 25-1-2011$form->addTextField('text', 'gmdName', __('GMD Name').'*', $rec_d['gmd_name'], 'style="width: 60%;"');
    $form->addTextField('text', 'gmdName', __('Material Type').'*', $rec_d['gmd_name'], 'style="width: 140px;" onkeydown="javascript:return checkspecialcharacter();"onblur="return charactercheck(this.name);"');
?>
<?php
 // member active/inactive iresh_date_01-06-2011
    $active_chbox[0] = array('1', __('Active'));
    $active_chbox[1] = array('0', __('Inactive'));
    $form->addRadio('activeinactive', __('Status'), $active_chbox, !empty($rec_d['active_inactive'])?$rec_d['active_inactive']:'1');
    // edit mode messagge
   
    if ($form->edit_mode) {
        echo '<div class="infoBox">'.__('You are going to edit data').' : <b>'.$rec_d['gmd_name'].'</b>  <br />'.__('Last Update').$rec_d['last_update'].'</div>'; //mfc
    }
    // print out the form object
    echo $form->printOut();
} else {
    /* GMD LIST */
    // table spec
    $table_spec = 'mst_gmd AS g LEFT JOIN mst_material_resource_type AS mrt ON mrt.material_resource_id=g.material_resource_id';

    // create datagrid
    $datagrid = new simbio_datagrid();
    if ($can_read AND $can_write) {
      /*  $datagrid->setSQLColumn('g.gmd_id',
            'g.gmd_code AS \''.__('Material Type Code').'\'',
            'g.gmd_name AS \''.__('Material Type Name').'\'',
            'DATE_FORMAT(g.last_update,"%d-%m-%Y") AS \''.__('Last Update').'\'');*/

	   $datagrid->setSQLColumn('g.gmd_id',
            'mrt.material_resource_name AS \''.__('Material Resource Type').'\'',
            'g.gmd_name AS \''.__('Material Type').'\'',
            'DATE_FORMAT(g.last_update,"%d-%m-%Y") AS \''.__('Last Update').'\'');
    } else {
        $datagrid->setSQLColumn('mrt.material_resource_name AS \''.__('Material Resource Type').'\'',
            'g.gmd_name AS \''.__('Material Type').'\'',
            'DATE_FORMAT(g.last_update,"%d-%m-%Y") AS \''.__('Last Update').'\'');
    }
    $datagrid->setSQLorder('gmd_name ASC');

    // is there any search
    if (isset($_GET['keywords']) AND $_GET['keywords']) {
       $keywords = $dbs->escape_string($_GET['keywords']);
       $datagrid->setSQLCriteria("g.gmd_name LIKE '%$keywords%' OR mrt.material_resource_name LIKE '%$keywords%'");
    }

    // set table and table header attributes
    $datagrid->table_attr = 'align="center" id="dataList" cellpadding="5" cellspacing="0"';
    $datagrid->table_header_attr = 'class="dataListHeader" style="font-weight: bold;"';
    // set delete proccess URL
    $datagrid->chbox_form_URL = $_SERVER['PHP_SELF'];

    // put the result into variables
    $datagrid_result = $datagrid->createDataGrid($dbs, $table_spec, 20, ($can_read AND $can_write));
    if (isset($_GET['keywords']) AND $_GET['keywords']) {
        $msg = str_replace('{result->num_rows}', $datagrid->num_rows, __('Found <strong>{result->num_rows}</strong> from your keywords')); //mfc
        echo '<div class="infoBox">'.$msg.' : "'.$_GET['keywords'].'"</div>';
    }

    echo $datagrid_result;
}
/* main content end */
?>
