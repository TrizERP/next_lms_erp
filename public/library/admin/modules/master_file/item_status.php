<?php
session_start();
/* Item Status Management section */

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
    die('<div class="errorBox">'.__('You don\'t have enough privileges to access this area!').'</div>');
}

// item status rules
$rules_option[] = array(NO_LOAN_TRANSACTION, __('No Loan Transaction'));
//$rules_option[] = array(SKIP_STOCK_TAKE, __('Skipped By Stock Take'));

/* RECORD OPERATION */
if (isset($_POST['saveData']) AND $can_read AND $can_write) 
 {
    
          
    
    //$itemStatusID = strip_tags(trim($_POST['itemStatusID']));
    $itemStatusName = strip_tags(trim($_POST['itemStatus']));
    // check form validity
    /*if (empty($itemStatusID) ) 
    {
        utility::jsAlert(__('Item Status Code can\'t be empty'));
        exit();
    }*/
    if (empty($itemStatusName))
    {
        utility::jsAlert(__('Item Status Name can\'t be empty'));
        exit();
    }
    if (number_format($itemStatusName))
    {
        utility::jsAlert(__('Item Status Name can\'t be Numeric!'));
        exit();
    }
    else
    {
        //$data['item_status_id'] = $dbs->escape_string($itemStatusID);
        $data['item_status_name'] = $dbs->escape_string($itemStatusName);
        // parsing rules
		/*
        $rules = '';
        if (isset($_POST['rules']) AND !empty($_POST['rules'])) {
            $rules = serialize($_POST['rules']);
        } else {
            $rules = 'literal{NULL}';
        }
		*/
        $data['rules'] = 'literal{NULL}';
		if (isset($_POST['rules']) AND !empty($_POST['rules'])) {
			foreach ($_POST['rules'] as $rule) {
				if ((integer)$rule == NO_LOAN_TRANSACTION) {
					$data['no_loan'] = 1;
				} 
                                /*else if ((integer)$rule == SKIP_STOCK_TAKE) 
                                {
					$data['skip_stock_take'] = 1;
				}*/
			}
		}
		if(empty($_POST['rules']))
		{
			$data['no_loan']=0;
			//$data['skip_stock_take']=0;
		}
        $data['input_date'] = date('Y-m-d');
        $data['last_update'] = date('Y-m-d');
        $data['user_name']=$_SESSION['uname'];
        

        // create sql op object
        $sql_op = new simbio_dbop($dbs);
        if (isset($_POST['updateRecordID'])) 
      //  if (isset($_POST['Update'])) 
        {
            
            /* UPDATE RECORD MODE */
            // remove input date
            unset($data['input_date']);
            // filter update record ID
            $updateRecordID = $dbs->escape_string(trim($_POST['updateRecordID']));
            // update the data
            $update = $sql_op->update('mst_item_status', $data, 'item_status_id=\''.$updateRecordID.'\'');
            if ($update) 
            {
                utility::jsAlert(__('Item Status Data Successfully Updated'));
                // update item status ID in item table to keep data integrity
                $sql_op->update('item', array('item_status_id' => $data['item_status_id']), 'item_status_id=\''.$updateRecordID.'\'');
                echo '<script type="text/javascript">parent.setContent(\'mainContent\', parent.getPreviousAJAXurl(), \'post\');</script>';
            }
            else
           {
               utility::jsAlert(__('Item Status Data FAILED to Updated.')."DEBUG : ".$sql_op->error); 
           
            }
            exit();
        }
        else
       {                 
            /* INSERT RECORD MODE */
            // insert the data
            $insert = $sql_op->insert('mst_item_status', $data);
            if ($insert) 
            {
                utility::jsAlert(__('New Item Status Data Successfully Saved'));
                echo '<script type="text/javascript">parent.setContent(\'mainContent\', \''.$_SERVER['PHP_SELF'].'\', \'post\');</script>';
            }
            else
            {
                utility::jsAlert(__('Item Status Data FAILED to Save.')." ".$sql_op->error); 
             
            }
            exit();
        }
    }
    exit();
} 
else if (isset($_POST['itemID']) AND !empty($_POST['itemID']) AND isset($_POST['itemAction'])) 
{
    if (!($can_read AND $can_write)) 
    {
        die();
    }
    /* DATA DELETION PROCESS */
    $sql_op = new simbio_dbop($dbs);
    $failed_array = array();
    $error_num = 0;
    if (!is_array($_POST['itemID'])) 
    {
        // make an array
        $_POST['itemID'] = array($dbs->escape_string(trim($_POST['itemID'])));
    }
    // loop array
    foreach ($_POST['itemID'] as $itemID) 
    {
        $itemID = $dbs->escape_string(trim($itemID));
	$checkflag = 0;
	$rec_f = $dbs->query('SELECT item_status_id from item where item_status_id ='.$itemID);
		while($row = $rec_f->fetch_assoc())
		{
			$checkflag=1;	
		}
	if($checkflag==1)
	{
		$rec_name = $dbs->query('SELECT item_status_name from mst_item_status where item_status_id ='.$itemID);
		while($rownew = $rec_name->fetch_assoc())
		{
			$item_status_name_set = $rownew['item_status_name'];	
		}
                $error_num++;
		 utility::jsAlert(__('You can not Delete Topic '.$item_status_name_set));
	}
	else
	{
            if (!$sql_op->delete('mst_item_status', "item_status_id='$itemID'")) 
            {
                $error_num++;
            }
	}
    }

    // error alerting
    if ($error_num == 0) {
        utility::jsAlert(__('All Data Successfully Deleted'));
        echo '<script type="text/javascript">parent.setContent(\'mainContent\', \''.$_SERVER['PHP_SELF'].'?'.$_POST['lastQueryStr'].'\', \'post\');</script>';
    } else {
        utility::jsAlert(__('Some or All Data NOT deleted successfully! Please contact system administrator'));
        echo '<script type="text/javascript">parent.setContent(\'mainContent\', \''.$_SERVER['PHP_SELF'].'?'.$_POST['lastQueryStr'].'\', \'post\');</script>';
    }
    exit();
}
/* item status update process end */

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
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'master_file/item_status.php class="headerText2">Item Status</a>';
echo $bradecum;
        ?>	
	</td>
</tr>
</table>
</table>
<table>
<tr>
	<td class="tab_menu_top">
                            <ul class="tabs"> 
				<li>
<a href="<?php echo MODULES_WEB_ROOT_DIR; ?>master_file/item_status.php?action=detail" class="headerText2"><?php echo __('Add New Item Status'); ?></a>
</li>
<li> 
<a href="<?php echo MODULES_WEB_ROOT_DIR; ?>master_file/item_status.php" class="headerText2"><?php echo __('Item Status'); ?></a> </li>
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
   <!-- <?php echo strtoupper(__('Item Status')); ?> - <a href="<?php echo MODULES_WEB_ROOT_DIR; ?>master_file/item_status.php?action=detail" class="headerText2"><?php echo __('Add New Item Status'); ?></a>
    &nbsp; <a href="<?php echo MODULES_WEB_ROOT_DIR; ?>master_file/item_status.php" class="headerText2"><?php echo __('Item Status'); ?></a>-->
    <p class="only_border">&nbsp;</p>
    <form name="search" action="<?php echo MODULES_WEB_ROOT_DIR; ?>master_file/item_status.php" id="search" method="get" style="display: inline;"><?php echo __('Search'); ?> :
    <input type="text" name="keywords" size="30" />
    <input type="submit" id="doSearch" value="<?php echo __('Search'); ?>" class="button" />
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
        die('<div class="errorBox">'.__('You don\'t have enough privileges to access this area!').'</div>');
    }
    /* RECORD FORM */
    $itemID = trim($dbs->escape_string(isset($_POST['itemID'])?$_POST['itemID']:''));
    
    $rec_q = $dbs->query("SELECT * FROM mst_item_status WHERE item_status_id='$itemID'");
    $rec_d = $rec_q->fetch_assoc();

    // create new instance
    $form = new simbio_form_table_AJAX('mainForm', $_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'], 'post');
    $form->submit_button_attr = 'name="saveData" value="'.__('Save').'" class="button"';

    // form table attributes
    $form->table_attr = 'align="center" id="dataList" cellpadding="5" cellspacing="0"';
    $form->table_header_attr = 'class="alterCell" style="font-weight: bold;"';
    $form->table_content_attr = 'class="alterCell2"';

    // edit mode flag set
    if ($rec_q->num_rows > 0) 
    {
        $form->edit_mode = true;        
        $form->record_id = $itemID;       
        $form->record_title = $rec_d['item_status_name'];    
        $form->submit_button_attr = 'name="saveData" value="'.__('Update').'" class="button"';
    }

    /* Form Element(s) */
    // item status code
  //comment by iresh on 25-1-2011  $form->addTextField('text', 'itemStatusID', __('Item Status Code').'*', $rec_d['item_status_id'], 'style="width: 20%;" maxlength="3"');
  /*added by iresh on 25-1-2011*/  //$form->addTextField('text', 'itemStatusID', __('Item Status Code').'*', $rec_d['item_status_id'], 'style="width: 140px;" maxlength="3" onkeyup="return checkspecialcharacterdynamic(this.name);"');
    // item status name
    //comment by iresh on 25-1-2011 $form->addTextField('text', 'itemStatus', __('Item Status Name').'*', $rec_d['item_status_name'], 'style="width: 60%;"');
    $form->addTextField('text', 'itemStatus', __('Item Status Name').'*', $rec_d['item_status_name'], 'style="width: 140px;" onkeyup="return checkspecialcharacterdynamic(this.name);""onblur="return charactercheck(this.name);"');
   
    // item status rules
	$rules = array();
	if ($rec_d['no_loan']) {
		$rules[] = NO_LOAN_TRANSACTION;
	}
	/*if ($rec_d['skip_stock_take']) {
		$rules[] = SKIP_STOCK_TAKE;
	}*/
    $form->addCheckbox('rules', __('Rules'), $rules_option, $rules);

    // edit mode messagge
    if ($form->edit_mode) {
        echo '<div class="infoBox">'.__('You are going to edit data').' : <b>'.$rec_d['item_status_name'].'</b>  <br />'.__('Last Update').$rec_d['last_update'].'</div>'; //mfc
    }
    // print out the form object
    echo $form->printOut();
} else {
    /* ITEM STATUS LIST */
    // table spec
    $table_spec = 'mst_item_status AS ist';

    // create datagrid
    $datagrid = new simbio_datagrid();
    if ($can_read AND $can_write) {
        $datagrid->setSQLColumn('ist.item_status_id',
            //'ist.item_status_id AS \''.__('Item Status Code').'\'',
            'ist.item_status_name AS \''.__('Item Status Name').'\'',
            'DATE_FORMAT(ist.last_update,"%d-%m-%Y") AS \''.__('Last Update').'\'');
    } else {
        $datagrid->setSQLColumn(
//          'ist.item_status_id AS \''.__('Item Status Code').'\'',
            'ist.item_status_name AS \''.__('Item Status Name').'\'',
            'DATE_FORMAT(ist.last_update,"%d-%m-%Y") AS \''.__('Last Update').'\'');
    }
    $datagrid->setSQLorder('item_status_name ASC');

    // change the record order
    if (isset($_GET['fld']) AND isset($_GET['dir'])) {
        $datagrid->setSQLorder("'".urldecode($_GET['fld'])."' ".$dbs->escape_string($_GET['dir']));
    }

    // is there any search
    if (isset($_GET['keywords']) AND $_GET['keywords']) {
       $keywords = $dbs->escape_string($_GET['keywords']);
       $datagrid->setSQLCriteria("ist.item_status_name LIKE '%$keywords%'");
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
