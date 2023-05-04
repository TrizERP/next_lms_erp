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
    die('<div class="errorBox">'.gettext('You don\'t have enough privileges to access this area!').'</div>');
}

/* RECORD OPERATION */
if (isset($_POST['saveData']) AND $can_read AND $can_write) {
    // check form validity
    $memberTypeName = trim(strip_tags($_POST['memberTypeName']));
    if (empty($memberTypeName)) {
        utility::jsAlert(gettext('Member Type Name can\'t be empty')); //mfc
        exit();
    } else {
        $data['member_type_name'] = $dbs->escape_string($memberTypeName);
        $data['loan_limit'] = trim($_POST['loanLimit']);
        $data['loan_periode'] = trim($_POST['loanPeriode']);
        $data['enable_reserve'] = $_POST['enableReserve'];
        $data['reserve_limit'] = $_POST['reserveLimit'];
        $data['member_periode'] = $_POST['memberPeriode'];
        $data['reborrow_limit'] = $_POST['reborrowLimit'];
        $data['fine_each_day'] = $_POST['fineEachDay'];
        $data['grace_periode'] = $_POST['gracePeriode'];
        $data['input_date'] = date('Y-m-d');
        $data['last_update'] = date('Y-m-d');
	$data['issue']= intval($_POST['issue']);
	$data['checkin']= intval($_POST['checkin']);
       $data['reserve']= intval($_POST['reserve']);
        // create sql op object
        $sql_op = new simbio_dbop($dbs);
        if (isset($_POST['updateRecordID'])) {
            /* UPDATE RECORD MODE */
            // remove input date
            unset($data['input_date']);
            // filter update record ID
            $updateRecordID = (integer)$_POST['updateRecordID'];
            // update the data
            $update = $sql_op->update('mst_member_type', $data, 'member_type_id='.$updateRecordID);
            if ($update) {
                utility::jsAlert(gettext('Member Type Successfully Updated'));
                // update all member expire date
                @$dbs->query('UPDATE member AS m SET expire_date=DATE_ADD(register_date,INTERVAL '.$data['member_periode'].'  DAY)
                    WHERE member_type_id='.$updateRecordID);
                echo '<script type="text/javascript">parent.setContent(\'mainContent\', \''.$_SERVER['PHP_SELF'].'\', \'post\');</script>';
            } else { utility::jsAlert(gettext('Member Type Data FAILED to Save/Update. Please Contact System Administrator')."\nDEBUG : ".$sql_op->error); }
            exit();
        } else {
            /* INSERT RECORD MODE */
            // insert the data
            if ($sql_op->insert('mst_member_type', $data)) {
                utility::jsAlert(gettext('New Member Type Successfully Saved'));
                echo '<script type="text/javascript">parent.setContent(\'mainContent\', \''.$_SERVER['PHP_SELF'].'\', \'post\');</script>';
            } else { utility::jsAlert(gettext('Member Type Data FAILED to Save/Update. Please Contact System Administrator')."\n".$sql_op->error); }
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
        if (!$sql_op->delete('mst_member_type', 'member_type_id='.$itemID)) {
            $error_num++;
        }
    }

    // error alerting
    if ($error_num == 0) {
        utility::jsAlert(gettext('All Data Successfully Deleted'));
        echo '<script type="text/javascript">parent.setContent(\'mainContent\', \''.$_SERVER['PHP_SELF'].'?'.$_POST['lastQueryStr'].'\', \'post\');</script>';
    } else {
        utility::jsAlert(gettext('Some or All Data NOT deleted successfully!\nPlease contact system administrator'));
        echo '<script type="text/javascript">parent.setContent(\'mainContent\', \''.$_SERVER['PHP_SELF'].'?'.$_POST['lastQueryStr'].'\', \'post\');</script>';
    }
    exit();
}
/* RECORD OPERATION END */

/* search form */
?>
<fieldset class="menuBox">
<div class="menuBoxInner memberTypeIcon">
    <?php echo strtoupper(gettext('Member Type')); ?> - <a href="<?php echo MODULES_WEB_ROOT_DIR; ?>membership/member_type.php?action=detail" class="headerText2"><?php echo gettext('Add New Member Type'); ?></a>
    &nbsp; <a href="<?php echo MODULES_WEB_ROOT_DIR; ?>membership/member_type.php" class="headerText2"><?php echo gettext('Member Type List'); ?></a>
    <p class="only_border">&nbsp;</p>
    <form name="search" action="<?php echo MODULES_WEB_ROOT_DIR; ?>membership/member_type.php" id="search" method="get" style="display: inline;"><?php echo __('Search'); ?> :
  <!--commnet by iresh on 25-1-2011  <input type="text" name="keywords" id="keywords" size="30" />-->
   <!-- added by iresh on 25-1-2011 --> <input type="text" name="keywords" id="keywords" width=140px/>
    <input type="submit" id="doSearch" value="<?php echo gettext('Search'); ?>" class="button" />
    </form>
</div>
</fieldset>
<?php
/* search form end */
/* main content */
if (isset($_POST['detail']) OR (isset($_GET['action']) AND $_GET['action'] == 'detail')) {
    if (!($can_read AND $can_write)) {
        die('<div class="errorBox">'.gettext('You don\'t have enough privileges to view this section').'</div>');
    }
    /* RECORD FORM */
    $itemID = (integer)isset($_POST['itemID'])?$_POST['itemID']:0;
    $rec_q = $dbs->query('SELECT * FROM mst_member_type WHERE member_type_id='.$itemID);
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
        $form->record_title = $rec_d['member_type_name'];
        // submit button attribute
        $form->submit_button_attr = 'name="saveData" value="'.gettext('Update').'" class="button"';
    }

    /* Form Element(s) */
    // member type name
    $form->addTextField('text', 'memberTypeName', gettext('Member Type Name').'*', $rec_d['member_type_name'], 'style="width: 100%;"');
    // loan limit
    $form->addTextField('text', 'loanLimit', gettext('Book Issue Limit'), $rec_d['loan_limit'], 'size="5" onchange="return numericcheck(this.name);"');
    // loan periode
    $form->addTextField('text', 'loanPeriode', gettext('Book Issue Periode (In Days)'), $rec_d['loan_periode'], 'size="5" onchange="return numericcheck(this.name);"');
    // enable reserve
    $enable_resv_chbox[0] = array('1', gettext('Enable'));
    $enable_resv_chbox[1] = array('0', gettext('Disable'));
    $form->addRadio('enableReserve', gettext('Reserve'), $enable_resv_chbox, !empty($rec_d['enable_reserve'])?$rec_d['enable_reserve']:'1');
    // reserve limit
    $form->addTextField('text', 'reserveLimit', gettext('Reserve Limit'), $rec_d['reserve_limit'], 'size="5" onchange="return numericcheck(this.name);"');
    // resource issue limit

   // $sql=$dbs->query('select gmd_name from mst_gmd where gmd_code=5');
   // $str_input ='';
   // while($row=$sql->fetch_assoc())
   // {	
    // $str_input .= $row['gmd_name']. simbio_form_element::textField('text', $row['gmd_name'], $rec_d['reserve_limit'], 'style="width:5%;"');
   /// } 
  
  //  $form->addAnything(__('Resource Issue Limit'), $str_input);
  
    // membership periode
    $form->addTextField('text', 'memberPeriode', gettext('Membership Periode (In Days)'), $rec_d['member_periode'], 'size="5" onchange="return numericcheck(this.name);"');
    // reborrow limit
    $form->addTextField('text', 'reborrowLimit', gettext('Reborrow Limit'), $rec_d['reborrow_limit'], 'size="5" onchange="return numericcheck(this.name);"');
    // fine each day
    $form->addTextField('text', 'fineEachDay', gettext('Fine Each Day'), $rec_d['fine_each_day'],'onchange="return numericcheck(this.name);"');
    // overdue grace periode
    $form->addTextField('text', 'gracePeriode', gettext('Overdue Grace Periode'), $rec_d['grace_periode'],'onchange="return numericcheck(this.name);"');
   //  Member messaging preferences 
    $issue[]=array('1',gettext('Issue'));
    $checkin[]=array('1',gettext('Return'));
    $reserve[]=array('1',gettext('Reserve'));
//$form->addCheckBox('alert', __('Member messaging preferences '), $issue, $rec_d['is_pending']);
$str_input = simbio_form_element::checkbox('issue', $issue, $rec_d['issue'], 'style="width:5%;"');
$str_input .= simbio_form_element::checkbox('checkin', $checkin, $rec_d['checkin'], 'style="width:5%;"');
$str_input .= simbio_form_element::checkbox('reserve', $reserve, $rec_d['reserve'], 'style="width:5%;"');
 $form->addAnything(gettext('Member messaging preferences '), $str_input);
    // edit mode messagge
    if ($form->edit_mode) {
        echo '<div class="infoBox">'.gettext('You are going to edit member data').' : <b>'.$rec_d['member_type_name'].'</b> <br />'.gettext('Last Updated').' '.$rec_d['last_update'].'</div>'."\n"; //mfc
    }
    // print out the form object
    echo $form->printOut();
} else {
    /* MEMBER TYPE NAME LIST */
    // table spec
    $table_spec = 'mst_member_type AS mt';

    // create datagrid
    $datagrid = new simbio_datagrid();
    if ($can_read AND $can_write) {
        $datagrid->setSQLColumn('mt.member_type_id',
            'mt.member_type_name AS \''.gettext('Membership Type').'\'',
            'mt.loan_limit AS \''.gettext('Loan Limit').'\'',
            'mt.member_periode AS \''.gettext('Membership Periode (In Days)').'\'',
            'mt.reborrow_limit AS \''.gettext('Reborrow Limit').'\'',
            'DATE_FORMAT(mt.last_update,"%d-%m-%Y") AS \''.gettext('Last Updated').'\'');
    } else {
        $datagrid->setSQLColumn('mt.member_type_name AS \''.gettext('Membership Type').'\'',
            'mt.loan_limit AS \''.gettext('Loan Limit').'\'',
            'mt.member_periode AS \''.gettext('Membership Periode (In Days)').'\'',
            'mt.reborrow_limit AS \''.gettext('Reborrow Limit').'\'',
            'mt.last_update AS \''.gettext('Last Updated').'\'');
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
        $msg = str_replace('{result->num_rows}', $datagrid->num_rows, gettext('Found <strong>{result->num_rows}</strong> from your keywords')); //mfc
        echo '<div class="infoBox">'.$msg.' : "'.$_GET['keywords'].'"</div>';
    }

    echo $datagrid_result;
}
/* main content end */
?>
