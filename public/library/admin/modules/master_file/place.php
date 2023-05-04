<?php
session_start();
/* Place Management section */

// main system configuration
require '../../../sysconfig.inc.php';
// start the session
require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
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
    die('<div class="errorBox">'.gettext('You don\'t have enough privileges to access this area!').'</div>');
}

/* RECORD OPERATION */
if (isset($_POST['saveData']) AND $can_read AND $can_write) 
{
    $placeName = trim(strip_tags($_POST['placeName']));
    // check form validity
    if (empty($placeName))
    {
        utility::jsAlert(gettext('Place Name can\'t be empty')); //mfc
        exit();
    } 
    else
    {
        if (number_format($placeName))
        {
            utility::jsAlert(gettext('Place Name can\'t be Numeric')); //mfc
            exit();
        }
        $data['place_name'] = $dbs->escape_string($placeName);
        $data['input_date'] = date('Y-m-d');
        $data['last_update'] = date('Y-m-d');
        $data['user_name']=$_SESSION['uname'];

        // create sql op object
        $sql_op = new simbio_dbop($dbs);
        if (isset($_POST['updateRecordID'])) 
        {
            /* UPDATE RECORD MODE */
            // remove input date
            unset($data['input_date']);
            // filter update record ID
            $updateRecordID = (integer)$_POST['updateRecordID'];
            // update the data
            $update = $sql_op->update('mst_place', $data, 'place_id='.$updateRecordID);
            if ($update) {
                utility::jsAlert(gettext('Place Data Successfully Updated'));
                echo '<script type="text/javascript">parent.setContent(\'mainContent\', parent.getPreviousAJAXurl(), \'post\');</script>';
            } else { utility::jsAlert(gettext('Place Data FAILED to Updated. Please Contact System Administrator')."\nDEBUG : ".$sql_op->error); }
            exit();
        } 
        else
        {
            
            $sql = "select * from mst_place where place_name LIKE '$placeName'";                               
            $data_available = $dbs->query($sql);
            if ($data_available->num_rows>0) 
            {
                utility::jsAlert(gettext('Same Place Name is already Exists!'));
                exit();
            } 
            /* INSERT RECORD MODE */
            // insert the data
            $insert = $sql_op->insert('mst_place', $data);
            if ($insert) 
            {
                utility::jsAlert(gettext('New Place Data Successfully Saved'));
                echo '<script type="text/javascript">parent.setContent(\'mainContent\', \''.$_SERVER['PHP_SELF'].'\', \'post\');</script>';
            }
            else
            {
              utility::jsAlert(gettext('Place Data FAILED to Save. Please Contact System Administrator')."\nDEBUG : ".$sql_op->error);            
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
    foreach ($_POST['itemID'] as $itemID) {
        $itemID = (integer)$itemID;
	$checkflag = 0;
	$rec_f = $dbs->query('SELECT place_id from mst_place where place_id ='.$itemID);
		while($row = $rec_f->fetch_assoc())
		{
			$checkflag=1;	
		}
	if($checkflag==1)
	{
		$rec_name = $dbs->query('SELECT place_name from mst_place where place_id ='.$itemID);
		while($rownew = $rec_name->fetch_assoc())
		{
			$publisher_name_set = $rownew['place_name'];	
		}
                $error_num++;
		 utility::jsAlert(gettext('You can not delete the place: '.$publisher_name_set.'; because it is associate with the book.'));
	}
	else
	{
          if (!$sql_op->delete('mst_place', 'place_id='.$itemID)) {
            $error_num++;
          }
        }
    }

    // error alerting
    if ($error_num == 0) {
        utility::jsAlert(gettext('All Data Successfully Deleted'));
        echo '<script type="text/javascript">parent.setContent(\'mainContent\', \''.$_SERVER['PHP_SELF'].'?'.$_POST['lastQueryStr'].'\', \'post\');</script>';
    } else {
        utility::jsAlert(gettext('Some or All Data NOT deleted successfully! Please contact system administrator'));
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
        $bradecum = "<a href=javascript:void(0); onclick=javascript:new_set_home(); >Home</a>-><a class='' href=javascript:void(0); onclick=javascript:new_set('".$basedir."');>"; 
	$query = "select module_name from mst_module where module_path = '".$basedir."'";
	$set_query = $dbs->query($query);
	while($row=$set_query->fetch_assoc())
	{
                $_formated_module_name = ucwords(str_replace('_', ' ', $row['module_name']));
		$bradecum .= $_formated_module_name;
	}
$bradecum .= '</a>->';
/*if(isset($_REQUEST['action']))
{
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'membership/index.php?action=detail class="headerText2">Add New Member</a>';
}
else
{
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'membership/index.php class="headerText2">View Member List</a>';
}*/
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'master_file/place.php class="headerText2">Publishing Place</a>';
echo $bradecum;
        ?>	
	</td>
</tr>
</table>
<table>
<tr>
	<td class="tab_menu_top">
                            <ul class="tabs"> 
				<li>
<a href="<?php echo MODULES_WEB_ROOT_DIR; ?>master_file/place.php?action=detail" class="headerText2"><?php echo gettext('Add New Place'); ?></a>
</li>
<li> 
<a href="<?php echo MODULES_WEB_ROOT_DIR; ?>master_file/place.php" class="headerText2"><?php echo gettext('Place List'); ?></a> </li>
</ul>
	</td>
</tr>
</table>

<?
if (isset($_POST['detail']) OR (!isset($_GET['action']) AND $_GET['action'] != 'detail')) 
{
?>

<fieldset class="menuBox">
<div class="menuBoxInner masterFileIcon">
    <!--<?php echo strtoupper(__('Place')); ?> - <a href="<?php echo MODULES_WEB_ROOT_DIR; ?>master_file/place.php?action=detail" class="headerText2"><?php echo __('Add New Place'); ?></a>
    &nbsp; <a href="<?php echo MODULES_WEB_ROOT_DIR; ?>master_file/place.php" class="headerText2"><?php echo __('Place List'); ?></a>-->
    <p class="only_border">&nbsp;</p>
    <form name="search" action="<?php echo MODULES_WEB_ROOT_DIR; ?>master_file/place.php" id="search" method="get" style="display: inline;"><?php echo gettext('Search'); ?> :
    <input type="text" name="keywords" size="30" />
    <input type="submit" id="doSearch" value="<?php echo gettext('Search'); ?>" class="button" />
    </form>
</div>
</fieldset>

<?
}
?>

<?php
/* search form end */
/* main content */
if (isset($_POST['detail']) OR (isset($_GET['action']) AND $_GET['action'] == 'detail')) 
{
    if (!($can_read AND $can_write)) 
    {
        die('<div class="errorBox">'.gettext('You don\'t have enough privileges to access this area!').'</div>');
    }
    /* RECORD FORM */
    $itemID = (integer)isset($_POST['itemID'])?$_POST['itemID']:0;
    $rec_q = $dbs->query('SELECT * FROM mst_place WHERE place_id='.$itemID);
    $rec_d = $rec_q->fetch_assoc();

    // create new instance
    $form = new simbio_form_table_AJAX('mainForm', $_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'], 'post');
    $form->submit_button_attr = 'name="saveData" value="'.gettext('Save').'" class="button"';

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
        $form->record_title = $rec_d['place_name'];
        // submit button attribute
        $form->submit_button_attr = 'name="saveData" value="'.gettext('Update').'" class="button"';
    }

    /* Form Element(s) */
    // place name
   //comment by iresh on 25-1-2011 $form->addTextField('text', 'placeName', __('Place Name').'*', $rec_d['place_name'], 'style="width: 60%;"');
   $form->addTextField('text', 'placeName', gettext('Place Name').'*', $rec_d['place_name'], 'style="width: 140px;"onkeyup="return checkspecialcharacterdynamic(this.name);"onblur="return charactercheck(this.name);"');

    // edit mode messagge
    if ($form->edit_mode) {
        echo '<div class="infoBox">'.gettext('You are going to edit data').' : <b>'.$rec_d['place_name'].'</b>  <br />'.gettext('Last Update').$rec_d['last_update'].'</div>'; //mfc
    }
    // print out the form object
    echo $form->printOut();
} else {
    /* PLACE LIST */
    // table spec
    $table_spec = 'mst_place AS pl';

    // create datagrid
    $datagrid = new simbio_datagrid();
    if ($can_read AND $can_write) {
        $datagrid->setSQLColumn('pl.place_id',
            'pl.place_name AS \''.gettext('Place Name').'\'',
            'DATE_FORMAT(pl.last_update,"%d-%m-%Y") AS \''.gettext('Last Update').'\'');
    } else {
        $datagrid->setSQLColumn('pl.place_name AS \''.gettext('Place Name').'\'',
            'DATE_FORMAT(pl.last_update,"%d-%m-%Y") AS \''.gettext('Last Update').'\'');
    }
    $datagrid->setSQLorder('place_name ASC');

    // is there any search
    if (isset($_GET['keywords']) AND $_GET['keywords']) {
       $keywords = $dbs->escape_string($_GET['keywords']);
       $datagrid->setSQLCriteria("pl.place_name LIKE '%$keywords%'");
    }

    // set table and table header attributes
    $datagrid->table_attr = 'align="center" id="dataList" cellpadding="5" cellspacing="0"';
    $datagrid->table_header_attr = 'class="dataListHeader" style="font-weight: bold;"';
    // set delete proccess URL
    $datagrid->chbox_form_URL = $_SERVER['PHP_SELF'];

    // put the result into variable
    $datagrid_result = $datagrid->createDataGrid($dbs, $table_spec, 20, ($can_read AND $can_write));
    if (isset($_GET['keywords']) AND $_GET['keywords']) {
        $msg = str_replace('{result->num_rows}', $datagrid->num_rows, gettext('Found <strong>{result->num_rows}</strong> from your keywords')); //mfc
        echo '<div class="infoBox">'.$msg.' : "'.$_GET['keywords'].'"</div>';
    }

    echo $datagrid_result;
}
/* main content end */
?>
