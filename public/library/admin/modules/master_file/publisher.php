<?php
session_start();
/* Publisher Management section */

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

if (!$can_read) 
{
    die('<div class="errorBox">'.gettext('You don\'t have enough privileges to access this area!').'</div>');
}


if (isset($_POST['saveData']) AND $can_read AND $can_write) 
{
    $publisherName = trim(strip_tags($_POST['publisherName']));
    // check form validity
    if (number_format($publisherName)) 
    {
        utility::jsAlert(gettext('Publisher Name Can\'t be Numeric!'));
        exit();        
    }
    
    if (empty($publisherName)) 
    {
        utility::jsAlert(gettext('Publisher Name can\'t be empty')); //mfc
        exit();
    }
    else
    {
        $data['publisher_name'] = $dbs->escape_string($publisherName);
        $data['input_date'] = date('Y-m-d');
        $data['last_update'] = date('Y-m-d');
        $data['user_name']=$_SESSION['uname'];

        // create sql op object
        $sql_op = new simbio_dbop($dbs);
        if (isset($_POST['updateRecordID'])) {
            /* UPDATE RECORD MODE */
            // remove input date
            unset($data['input_date']);
            // filter update record ID
            $updateRecordID = (integer)$_POST['updateRecordID'];
            // update the data
            $update = $sql_op->update('mst_publisher', $data, 'publisher_id='.$updateRecordID);
            if ($update) {
                utility::jsAlert(gettext('Publisher Data Successfully Updated'));
                echo '<script type="text/javascript">parent.setContent(\'mainContent\', parent.getPreviousAJAXurl(), \'post\');</script>';
            } else { utility::jsAlert(gettext('PUBLISHER Data FAILED to Updated. Please Contact System Administrator')."\nDEBUG : ".$sql_op->error); }
            exit();
        } else {
            /* INSERT RECORD MODE */
            // insert the data
            $insert = $sql_op->insert('mst_publisher', $data);
            
            if ($insert) 
            {
                utility::jsAlert(gettext('New Publisher Data Successfully Saved'));
                echo '<script type="text/javascript">parent.setContent(\'mainContent\', \''.$_SERVER['PHP_SELF'].'\', \'post\');</script>';
            } 
            else
            {
                //utility::jsAlert(__('Publisher Data FAILED to Save. Please Contact System Administrator')."\nDEBUG : ".$sql_op->error); 
                utility::jsAlert(gettext('Duplicate Entery!Publisher is already Exist')); 
                exit();
          
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
	$rec_f = $dbs->query('SELECT publisher_id from biblio where publisher_id ='.$itemID);
		while($row = $rec_f->fetch_assoc())
		{
			$checkflag=1;	
		}
	if($checkflag==1)
	{
		$rec_name = $dbs->query('SELECT publisher_name from mst_publisher where publisher_id ='.$itemID);
		while($rownew = $rec_name->fetch_assoc())
		{
			$publisher_name_set = $rownew['publisher_name'];	
		}
                $error_num++;
		 utility::jsAlert(gettext('You can not delete the publisher Type: '.$publisher_name_set.'; because it is associate with the book.'));
	}
	else
	{
       	 if (!$sql_op->delete('mst_publisher', 'publisher_id='.$itemID)) {
       	     $error_num++;
       		 }
	}
    }
//ended addition by Parth 8/7/2011
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
/* RECORD OPERATION */

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
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'master_file/publisher.php class="headerText2">Publisher</a>';
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
<a href="<?php echo MODULES_WEB_ROOT_DIR; ?>master_file/publisher.php?action=detail" class="headerText2"><?php echo gettext('Add New Publisher'); ?></a> </li>
<li> 
<a href="<?php echo MODULES_WEB_ROOT_DIR; ?>master_file/publisher.php" class="headerText2"><?php echo gettext('Publisher List'); ?></a> </li>
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
   <!-- <?php echo strtoupper(__('Publisher')); ?> - <a href="<?php echo MODULES_WEB_ROOT_DIR; ?>master_file/publisher.php?action=detail" class="headerText2"><?php echo __('Add New Publisher'); ?></a>
    &nbsp; <a href="<?php echo MODULES_WEB_ROOT_DIR; ?>master_file/publisher.php" class="headerText2"><?php echo __('Publisher List'); ?></a>-->
    <p class="only_border">&nbsp;</p>
    <form name="search" action="<?php echo MODULES_WEB_ROOT_DIR; ?>master_file/publisher.php" id="search" method="get" style="display: inline;"><?php echo gettext('Search'); ?> :
     <!--commnet by iresh on 25-1-2011  <input type="text" name="keywords" id="keywords" size="30" />-->
   <!-- added by iresh on 25-1-2011 --> <input type="text" name="keywords" id="keywords" width=140px/>
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
if (isset($_POST['detail']) OR (isset($_GET['action']) AND $_GET['action'] == 'detail')) {
    if (!($can_read AND $can_write)) {
        die('<div class="errorBox">'.gettext('You don\'t have enough privileges to access this area!').'</div>');
    }
    /* RECORD FORM */
    $itemID = (integer)isset($_POST['itemID'])?$_POST['itemID']:0;
    $rec_q = $dbs->query('SELECT * FROM mst_publisher WHERE publisher_id='.$itemID);
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
        $form->record_title = $rec_d['publisher_name'];
        // submit button attribute
        $form->submit_button_attr = 'name="saveData" value="'.gettext('Update').'" class="button"';
    }

    /* Form Element(s) */
    // publisher name
   //comment by iresh on 25-1-2011 $form->addTextField('text', 'publisherName', __('Publisher Name').'*', $rec_d['publisher_name'], 'style="width: 60%;"');
    $form->addTextField('text', 'publisherName', gettext('Publisher Name').'*', $rec_d['publisher_name'], 'style="width: 140px;" onblur="return charactercheck(this.name);" onkeyup="return checkspecialcharacterdynamic(this.name);"');

    // edit mode messagge
    if ($form->edit_mode) {
        echo '<div class="infoBox">'.gettext('You are going to edit data').' : <b>'.$rec_d['publisher_name'].'</b> <br />'.gettext('Last Update').$rec_d['last_update'] //mfc
            .'</div>'."\n";
    }
    // print out the form object
    echo $form->printOut();
} else {
    /* PUBLISHER LIST */
    // table spec
    $table_spec = 'mst_publisher AS p';

    // create datagrid
    $datagrid = new simbio_datagrid();
    if ($can_read AND $can_write) {
        $datagrid->setSQLColumn('p.publisher_id',
            'p.publisher_name AS \''.gettext('Publisher Name').'\'',
            'DATE_FORMAT(p.last_update,"%d-%m-%Y") AS \''.gettext('Last Update').'\'');
    } else {
    	// TODO: publisher_place was dropped in stable7...?
        $datagrid->setSQLColumn('p.publisher_name AS \''.gettext('Publisher Name').'\'',
            'p.publisher_place AS \''.lang_mod_masterfile_publisher_form_field_place.'\'',
            'DATE_FORMAT(p.last_update,"%d-%m-%Y") AS \''.gettext('Last Update').'\'');
    }
    $datagrid->setSQLorder('publisher_name ASC');

    // is there any search
    if (isset($_GET['keywords']) AND $_GET['keywords']) {
       $keywords = $dbs->escape_string($_GET['keywords']);
       $datagrid->setSQLCriteria("p.publisher_name LIKE '%$keywords%'");
    }

    // set table and table header attributes
    $datagrid->table_attr = 'align="center" id="dataList" cellpadding="5" cellspacing="0"';
    $datagrid->table_header_attr = 'class="dataListHeader" style="font-weight: bold;"';
    // set delete proccess URL
    $datagrid->chbox_form_URL = $_SERVER['PHP_SELF'];

    // put the result into variable
    $datagrid_result = $datagrid->createDataGrid($dbs, $table_spec, 20, ($can_read AND $can_write));
    if (isset($_GET['keywords']) AND $_GET['keywords']) {
        echo '<table cellpadding="3" cellspacing="0" class="infoBox">';
        $msg = str_replace('{result->num_rows}', $datagrid->num_rows, gettext('Found <strong>{result->num_rows}</strong> from your keywords')); //mfc
        echo '<tr><th>'.$msg.' : "'.$_GET['keywords'].'"</th></tr>';
        echo '</table>';
    }

    echo $datagrid_result;
}
/* main content end */

?>
