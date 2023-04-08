<?php
/**
 * Copyright (C) 2007,2008  Arie Nugraha (dicarve@yahoo.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 */

/* Member Type Management section */

// main system configuration
require '../../../sysconfig.inc.php';
// start the session
require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
require SENAYAN_BASE_DIR.'admin/default/session_check.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/form_maker/simbio_form_table_AJAX.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/simbio_dbop.inc.php';

// privileges checking
$can_read = utility::havePrivilege('membership', 'r');
$can_write = utility::havePrivilege('membership', 'w');

if (!$can_read) {
    die('<div class="errorBox">'.__('You don\'t have enough privileges to view this section').'</div>');
}

if (isset($_POST['saveData'])) {
   /* $data['member_type_id'] = $_POST['memberTypeID'];
    $data['coll_type_id'] = $_POST['collTypeID'];
    $data['gmd_id'] = $_POST['gmdID'];
    $data['loan_limit'] = trim($_POST['loanLimit']);
    $data['loan_periode'] = trim($_POST['loanPeriode']);
    $data['reborrow_limit'] = trim($_POST['reborrowLimit']);
    $data['fine_each_day'] = trim($_POST['fineEachDay']);
    $data['grace_periode'] = trim($_POST['gracePeriode']);
    $data['input_date'] = date('Y-m-d');
    $data['last_update'] = date('Y-m-d');*/
    // create sql op object
    $sql_op = new simbio_dbop($dbs);
    if (isset($_POST['updateRecordID'])) {
  $data['member_type_id'] = $_POST['memberTypeID'];
    $data['coll_type_id'] = $_POST['collTypeID'];
    $data['gmd_id'] = $_POST['gmdID'];
    $data['loan_limit'] = trim($_POST['loanLimit']);
    $data['loan_periode'] = trim($_POST['loanPeriode']);
    $data['reborrow_limit'] = trim($_POST['reborrowLimit']);
    $data['fine_each_day'] = trim($_POST['fineEachDay']);
    $data['grace_periode'] = trim($_POST['gracePeriode']);
    $data['input_date'] = date('Y-m-d');
    $data['last_update'] = date('Y-m-d'); 
//added started parth 20/8/2011

        $data2['loan_limit'] = trim($_POST['loanLimit']);
        $data2['loan_periode'] = trim($_POST['loanPeriode']);
        $data2['enable_reserve'] = $_POST['enableReserve'];
        $data2['reserve_limit'] = $_POST['reserveLimit'];
        $data2['member_periode'] = $_POST['memberPeriode'];
        $data2['reborrow_limit'] = $_POST['reborrowLimit'];
        $data2['fine_each_day'] = $_POST['fineEachDay'];
        $data2['grace_periode'] = $_POST['gracePeriode'];
        $data2['input_date'] = date('Y-m-d');
        $data2['last_update'] = date('Y-m-d');
	$data2['issue']= intval($_POST['issue']);
	$data2['checkin']= intval($_POST['checkin']);
       $data2['reserve']= intval($_POST['reserve']);   
 $update = $sql_op->update('mst_member_type', $data2, 'member_type_id='.$_POST['memberTypeID']);
//added ended parth 20/8/2011
        /* UPDATE RECORD MODE */
        // remove input date
        unset($data['input_date']);
        // filter update record ID
        $updateRecordID = (integer)$_POST['updateRecordID'];
        // update the data
        $update = $sql_op->update('mst_loan_rules', $data, 'loan_rules_id='.$updateRecordID);
        if ($update) {
            utility::jsAlert(__('Loan Rules Successfully Updated'));
            echo '<script language="Javascript">parent.setContent(\'mainContent\', parent.getPreviousAJAXurl(), \'post\');</script>';
        } else { utility::jsAlert(__('Loan Rules FAILED to Updated. Please Contact System Administrator')."\nDEBUG : ".$sql_op->error); }
        exit();
    } else {
       $memberTypeName = trim(strip_tags($_POST['memberTypeID']));
       $flag_set = 0;  
    if (empty($memberTypeName)) {
       $memberTypeName = $_POST['memberTypeID1']; 
       $flag_set = 1;
    } 
    //$data2['coll_type_id'] = $_POST['collTypeID'];
    //$data['gmd_id'] = $itemID; 
     $data2['member_type_name'] = $dbs->escape_string($memberTypeName);
        $data2['loan_limit'] = trim($_POST['loanLimit']);
        $data2['loan_periode'] = trim($_POST['loanPeriode']);
        $data2['enable_reserve'] = $_POST['enableReserve'];
        $data2['reserve_limit'] = $_POST['reserveLimit'];
        $data2['member_periode'] = $_POST['memberPeriode'];
        $data2['reborrow_limit'] = $_POST['reborrowLimit'];
        $data2['fine_each_day'] = $_POST['fineEachDay'];
        $data2['grace_periode'] = $_POST['gracePeriode'];
        $data2['input_date'] = date('Y-m-d');
        $data2['last_update'] = date('Y-m-d');
	$data2['issue']= intval($_POST['issue']);
	$data2['checkin']= intval($_POST['checkin']);
       $data2['reserve']= intval($_POST['reserve']);   
if($flag_set==0)
{
     $insert = $sql_op->insert('mst_member_type', $data2); 
     $memberTypeName = $sql_op->insert_id;  
}
        /* INSERT RECORD MODE */
         foreach ($_POST['gmdID'] as $itemID) {
         $data['member_type_id'] = $memberTypeName;
    $data['coll_type_id'] = $_POST['collTypeID'];
    $data['gmd_id'] = $itemID;
    $data['loan_limit'] = trim($_POST['loanLimit']);
    $data['loan_periode'] = trim($_POST['loanPeriode']);
    $data['reborrow_limit'] = trim($_POST['reborrowLimit']);
    $data['fine_each_day'] = trim($_POST['fineEachDay']);
    $data['grace_periode'] = trim($_POST['gracePeriode']);
    $data['input_date'] = date('Y-m-d');
    $data['last_update'] = date('Y-m-d'); 
        $insert = $sql_op->insert('mst_loan_rules', $data);
          }
        if ($insert) {
            utility::jsAlert(__('New Loan Rules Successfully Saved'));
            echo '<script language="Javascript">parent.setContent(\'mainContent\', \''.$_SERVER['PHP_SELF'].'\', \'post\');</script>';
        } else { utility::jsAlert(__('Loan Rules FAILED to Save. Please Contact System Administrator')."\n".$sql_op->error); }
        exit();
    }
    exit();

}



 else if (isset($_POST['itemID']) AND !empty($_POST['itemID']) AND isset($_POST['itemAction'])) {



    if (!($can_read AND $can_write)) {
        die();
    }

    /* DATA DELETION PROCESS */
    // create sql op object
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
        $set_master = $dbs->query('select member_type_id from mst_loan_rules where loan_rules_id ='.$itemID);
        $set_count = $set_master->fetch_assoc();
        if (!$sql_op->delete('mst_loan_rules', 'loan_rules_id='.$itemID)) {
            $error_num++;
        }  
        if($error_num==0)
        {    
        $set_master1 = $dbs->query('select DISTINCT(count(member_type_id)) from mst_loan_rules where member_type_id ='.$set_count['member_type_id']);
        $set_count1 = $set_master1->fetch_assoc(); 
        if(empty($set_count1['(count(member_type_id))'])) 
             {
                  $sql_op->delete('mst_member_type', 'member_type_id='.$set_count['member_type_id']);
             } 
        } 
    }

    // error alerting
    if ($error_num == 0) {
        utility::jsAlert(__('All Data Successfully Deleted'));
        echo '<script language="Javascript">parent.setContent(\'mainContent\', \''.$_SERVER['PHP_SELF'].'?'.$_POST['lastQueryStr'].'\', \'post\');</script>';
    } else {
        utility::jsAlert(__('Some or All Data NOT deleted successfully!\nPlease contact system administrator'));
        echo '<script language="Javascript">parent.setContent(\'mainContent\', \''.$_SERVER['PHP_SELF'].'?'.$_POST['lastQueryStr'].'\', \'post\');</script>';
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
//$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'membership/member_type.php class="headerText2">Assign Member Role</a>';
//echo $bradecum;
        ?>	
	</td>
</tr>
</table>
<table>
<tr>
	<td class="tab_menu_top">
                            <ul class="tabs"> 
				<li>
<a href="<?php echo MODULES_WEB_ROOT_DIR; ?>membership/index.php" class="headerText2"><?php echo __('View Member List'); ?></a> </li><li>
<a href="<?php echo  MODULES_WEB_ROOT_DIR; ?>membership/index.php?action=detail" class="headerText2"><?php echo __('Add New Member'); ?></a> </li><li> <a href="<?php echo  MODULES_WEB_ROOT_DIR; ?>membership/member_type.php" class="headerText2"><?php echo __('Assign member Role'); ?></a> </li>
<li>
<a href="<?php echo  MODULES_WEB_ROOT_DIR; ?>membership/index.php?expire=true" class="headerText2"><?php echo __('View Expired Member'); ?></a> 
</li>
</ul>
	</td>
</tr>
</table>
<fieldset class="menuBox">
<div class="menuBoxInner memberTypeIcon">
    <?php echo strtoupper(__('Member Type')); ?> - <a href="<?php echo MODULES_WEB_ROOT_DIR; ?>membership/member_type.php?action=detail" class="headerText3"><?php echo __('Add New Member Type'); ?></a>
    &nbsp; <a href="<?php echo MODULES_WEB_ROOT_DIR; ?>membership/member_type.php" class="headerText3"><?php echo __('Member Type List'); ?></a>
    <p class="only_border">&nbsp;</p>
    <form name="search" action="<?php echo MODULES_WEB_ROOT_DIR; ?>membership/member_type.php" id="search" method="get" style="display: inline;"><?php echo __('Search'); ?> :
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
    /*if (!($can_read AND $can_write)) {
        die('<div class="errorBox">'.__('You don\'t have enough privileges to view this section').'</div>');
    }*/
    /* RECORD FORM */
   /* $itemID = (integer)isset($_POST['itemID'])?$_POST['itemID']:0;
    $rec_q = $dbs->query('SELECT * FROM mst_member_type WHERE member_type_id='.$itemID);
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
        $form->record_title = $rec_d['member_type_name'];
        // submit button attribute
        $form->submit_button_attr = 'name="saveData" value="'.__('Update').'" class="button"';
    }*/

    /* Form Element(s) */
    // member type name
 /*   $form->addTextField('text', 'memberTypeName', __('Member Type Name').'*', $rec_d['member_type_name'], 'style="width: 100%;"');
    // loan limit
    $form->addTextField('text', 'loanLimit', __('Book Issue Limit'), $rec_d['loan_limit'], 'size="5" onchange="return numericcheck(this.name);"');
    // loan periode
    $form->addTextField('text', 'loanPeriode', __('Book Issue Periode (In Days)'), $rec_d['loan_periode'], 'size="5" onchange="return numericcheck(this.name);"');
    // enable reserve
    $enable_resv_chbox[0] = array('1', __('Enable'));
    $enable_resv_chbox[1] = array('0', __('Disable'));
    $form->addRadio('enableReserve', __('Reserve'), $enable_resv_chbox, !empty($rec_d['enable_reserve'])?$rec_d['enable_reserve']:'1');
    // reserve limit
    $form->addTextField('text', 'reserveLimit', __('Reserve Limit'), $rec_d['reserve_limit'], 'size="5" onchange="return numericcheck(this.name);"');
    // resource issue limit

   // $sql=$dbs->query('select gmd_name from mst_gmd where gmd_code=5');
   // $str_input ='';
   // while($row=$sql->fetch_assoc())
   // {	
    // $str_input .= $row['gmd_name']. simbio_form_element::textField('text', $row['gmd_name'], $rec_d['reserve_limit'], 'style="width:5%;"');
   /// } 
  
  //  $form->addAnything(__('Resource Issue Limit'), $str_input);
  
    // membership periode
    $form->addTextField('text', 'memberPeriode', __('Membership Periode (In Days)'), $rec_d['member_periode'], 'size="5" onchange="return numericcheck(this.name);"');
    // reborrow limit
    $form->addTextField('text', 'reborrowLimit', __('Reborrow Limit'), $rec_d['reborrow_limit'], 'size="5" onchange="return numericcheck(this.name);"');
    // fine each day
    $form->addTextField('text', 'fineEachDay', __('Fine Each Day'), $rec_d['fine_each_day'],'onchange="return numericcheck(this.name);"');
    // overdue grace periode
    $form->addTextField('text', 'gracePeriode', __('Overdue Grace Periode'), $rec_d['grace_periode'],'onchange="return numericcheck(this.name);"');
   //  Member messaging preferences 
    $issue[]=array('1',__('Issue'));
    $checkin[]=array('1',__('Return'));
    $reserve[]=array('1',__('Reserve'));
//$form->addCheckBox('alert', __('Member messaging preferences '), $issue, $rec_d['is_pending']);
$str_input = simbio_form_element::checkbox('issue', $issue, $rec_d['issue'], 'style="width:5%;"');
$str_input .= simbio_form_element::checkbox('checkin', $checkin, $rec_d['checkin'], 'style="width:5%;"');
$str_input .= simbio_form_element::checkbox('reserve', $reserve, $rec_d['reserve'], 'style="width:5%;"');
 $form->addAnything(__('Member messaging preferences '), $str_input);
    // edit mode messagge
    if ($form->edit_mode) {
        echo '<div class="infoBox">'.__('You are going to edit member data').' : <b>'.$rec_d['member_type_name'].'</b> <br />'.__('Last Updated').' '.$rec_d['last_update'].'</div>'."\n"; //mfc
    }
    // print out the form object
    echo $form->printOut();*/
    if (!($can_read AND $can_write)) {
        die('<div class="errorBox">'.__('You don\'t have enough privileges to view this section').'</div>');
    }
    /* RECORD FORM */
    // try query
    $itemID = (integer)isset($_POST['itemID'])?$_POST['itemID']:0;
    $rec_q = $dbs->query('SELECT mst1.*,mst2.issue,mst2.checkin,mst2.reserve,mst2.enable_reserve,mst2.reserve_limit,mst2.member_periode FROM mst_loan_rules mst1 INNER JOIN mst_member_type mst2 ON mst1.member_type_id = mst2.member_type_id WHERE mst1.loan_rules_id='.$itemID);
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
        // form record id
        $form->record_id = $itemID;
        // form record title
        $form->record_title = 'Loan Rules';
        // submit button attribute
        $form->submit_button_attr = 'name="saveData" value="'.__('Update').'" class="button"';
    }

    /* Form Element(s) */
    // member type
        // get mtype data related to this record from database
        $mtype_query = $dbs->query('SELECT member_type_id, member_type_name FROM mst_member_type');
        $mtype_options = array();
        while ($mtype_data = $mtype_query->fetch_row()) {
            $mtype_options[] = array($mtype_data[0], $mtype_data[1]);
        }
     if ($form->edit_mode) {    
    $form->addSelectList('memberTypeID', __('Member Type'), $mtype_options, $rec_d['member_type_id'], 'style="width: 50%;"');
     }
     else
     {
     $form->addTextField('text', 'memberTypeID', __('Member Type Name').'*', $rec_d['member_type_name'], 'style="width: 100%;"');
     $form->addSelectList('memberTypeID1', __('Member Type Available'), $mtype_options, $rec_d['member_type_id'], 'style="width: 50%;"');  
     }
    // collection type
        // get collection type data related to this record from database
        $ctype_query = $dbs->query('SELECT coll_type_id, coll_type_name FROM mst_coll_type');
        $ctype_options = array();
        while ($ctype_data = $ctype_query->fetch_row()) {
            $ctype_options[] = array($ctype_data[0], $ctype_data[1]);
        }
        $ctype_options[] = array('0', __('ALL'));
    $form->addSelectList('collTypeID', __('Collection Type'), $ctype_options, $rec_d['coll_type_id'], 'style="width: 50%;"');
    // gmd
        // get gmd data related to this record from database
        $gmd_query = $dbs->query('SELECT material_sub_id, material_sub_name FROM mst_material_sub_type where gmd_id=36');
        $gmd_options[] = array(0, __('ALL'));
        while ($gmd_data = $gmd_query->fetch_row()) {
            $gmd_options[] = array($gmd_data[0], $gmd_data[1]);
        }
    if(isset($rec_d['gmd_id']))
    {
    $form->addSelectList('gmdID', __('Material Type'), $gmd_options, $rec_d['gmd_id'], 'style="width: 50%;"');
    }
    else
    {
    $form->addSelectList('gmdID[]', __('Material Type'), $gmd_options, $rec_d['gmd_id'], 'style="width: 50%;" multiple=multiple');
    }
    // loan limit
    $form->addTextField('text', 'loanLimit', __('Loan Limit'), $rec_d['loan_limit'], 'size="5"');
    // loan periode
    $form->addTextField('text', 'loanPeriode', __('Loan Period'), $rec_d['loan_periode'], 'size="5"');
    $enable_resv_chbox[0] = array('1', __('Enable'));
    $enable_resv_chbox[1] = array('0', __('Disable'));
    $form->addRadio('enableReserve', __('Reserve'), $enable_resv_chbox, !empty($rec_d['enable_reserve'])?$rec_d['enable_reserve']:'1');
    // reserve limit
    $form->addTextField('text', 'reserveLimit', __('Reserve Limit'), $rec_d['reserve_limit'], 'size="5" onchange="return numericcheck(this.name);"');
    $form->addTextField('text', 'memberPeriode', __('Membership Periode (In Days)'), $rec_d['member_periode'], 'size="5" onchange="return numericcheck(this.name);"');
    // reborrow limit
    $form->addTextField('text', 'reborrowLimit', __('Reborrow Limit'), $rec_d['reborrow_limit'], 'size="5"');
    // fine each day
    $form->addTextField('text', 'fineEachDay', __('Fines Each Day'), $rec_d['fine_each_day']);
    // overdue grace periode
    $form->addTextField('text', 'gracePeriode', __('Overdue Grace Periode'), $rec_d['grace_periode']);
    $issue[]=array('1',__('Issue'));
    $checkin[]=array('1',__('Return'));
    $reserve[]=array('1',__('Reserve'));
//$form->addCheckBox('alert', __('Member messaging preferences '), $issue, $rec_d['is_pending']);
$str_input = simbio_form_element::checkbox('issue', $issue, $rec_d['issue'], 'style="width:5%;"');
$str_input .= simbio_form_element::checkbox('checkin', $checkin, $rec_d['checkin'], 'style="width:5%;"');
$str_input .= simbio_form_element::checkbox('reserve', $reserve, $rec_d['reserve'], 'style="width:5%;"');
 $form->addAnything(__('Member messaging preferences '), $str_input); 
    // edit mode messagge
    if ($form->edit_mode) {
        echo '<div class="infoBox">'.__('You are going to edit loan rules').' : <br />'.__('Last Update').$rec_d['last_update'].'</div>'."\n"; //mfc
    }
    // print out the form object
    echo $form->printOut();
} else {
    /* MEMBER TYPE NAME LIST */
    // table spec
   /* $table_spec = 'mst_member_type AS mt';

    // create datagrid
    $datagrid = new simbio_datagrid();
    if ($can_read AND $can_write) {
        $datagrid->setSQLColumn('mt.member_type_id',
            'mt.member_type_name AS \''.__('Membership Type').'\'',
            'mt.loan_limit AS \''.__('Loan Limit').'\'',
            'mt.member_periode AS \''.__('Membership Periode (In Days)').'\'',
            'mt.reborrow_limit AS \''.__('Reborrow Limit').'\'',
            'DATE_FORMAT(mt.last_update,"%d-%m-%Y") AS \''.__('Last Updated').'\'');
    } else {
        $datagrid->setSQLColumn('mt.member_type_name AS \''.__('Membership Type').'\'',
            'mt.loan_limit AS \''.__('Loan Limit').'\'',
            'mt.member_periode AS \''.__('Membership Periode (In Days)').'\'',
            'mt.reborrow_limit AS \''.__('Reborrow Limit').'\'',
            'mt.last_update AS \''.__('Last Updated').'\'');
    }
    $datagrid->setSQLorder('member_type_name ASC');

    // is there any search
    if (isset($_GET['keywords']) AND $_GET['keywords']) {
       $keywords = $dbs->escape_string($_GET['keywords']);
       $datagrid->setSQLCriteria("mt.member_type_name LIKE '%$keywords%'");
    }

    // set table and table header attributes
    $datagrid->icon_edit = SENAYAN_WEB_ROOT_DIR.'admin/'.$sysconf['admin_template']['dir'].'/'.$sysconf['admin_template']['theme'].'/edit.gif';
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

    echo $datagrid_result;*/
$table_spec = 'mst_loan_rules AS lr
        LEFT JOIN mst_member_type AS mt ON lr.member_type_id=mt.member_type_id
        LEFT JOIN mst_coll_type AS ct ON lr.coll_type_id=ct.coll_type_id
        LEFT JOIN mst_material_sub_type AS g ON lr.gmd_id=g.material_sub_id';

    // create datagrid
    $datagrid = new simbio_datagrid();
    if ($can_read AND $can_write) {
        $datagrid->setSQLColumn('lr.loan_rules_id',
            'mt.member_type_name AS \''.__('Member Type').'\'',
            'ct.coll_type_name AS \''.__('Collection Type').'\'',
            'g.material_sub_name AS \''.__('Material Type').'\'',
            'lr.loan_limit AS \''.__('Loan Limit').'\'',
            'lr.loan_periode AS \''.__('Loan Period').'\'',
            'lr.last_update AS \''.__('Last Update').'\'');
    } else {
        $datagrid->setSQLColumn('mt.member_type_name AS \''.__('Member Type').'\'',
            'ct.coll_type_name AS \''.__('Collection Type').'\'',
            'g.material_sub_name AS \''.__('Material Type').'\'',
            'lr.loan_limit AS \''.__('Loan Limit').'\'',
            'lr.loan_periode AS \''.__('Loan Period').'\'',
            'lr.last_update AS \''.__('Last Update').'\'');
    }
    $datagrid->setSQLorder('mt.member_type_name ASC');

    // is there any search
    if (isset($_GET['keywords']) AND $_GET['keywords']) {
       $keywords = $dbs->escape_string($_GET['keywords']);
       $datagrid->setSQLCriteria("mt.member_type_name LIKE '%$keywords%'");
    }

    // set table and table header attributes
    $datagrid->icon_edit = $sysconf['admin_template']['dir'].'/'.$sysconf['admin_template']['theme'].'/edit.gif';
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
