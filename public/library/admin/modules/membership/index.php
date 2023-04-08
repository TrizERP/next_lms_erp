<?php
session_start();
if (!defined('SENAYAN_BASE_DIR')) 
{
    // main system configuration
    require '../../../sysconfig.inc.php';
    // start the session
    require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
}

require SENAYAN_BASE_DIR.'admin/default/session_check.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/form_maker/simbio_form_table_AJAX (copy).inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/simbio_dbop.inc.php';
require SIMBIO_BASE_DIR.'simbio_UTILS/simbio_date.inc.php';
require SIMBIO_BASE_DIR.'simbio_FILE/simbio_file_upload.inc.php';

// privileges ing
$can_read = utility::havePrivilege('membership', 'r');
$can_write = utility::havePrivilege('membership', 'w');

if (!$can_read) {
    die('<div class="errorBox">You dont have enough privileges to view this section</div>');
}
/* member update process */

if (isset($_POST['saveData']) AND $can_read AND $can_write)
{

    $_SESSION['membermail']= trim($dbs->escape_string(strip_tags($_POST['memberEmail'])));
            
    
    // check form validity
    $memberID = trim($_POST['memberID']);
    $memberName = trim($_POST['memberName']);
    $membermiddleName = trim($_POST['membermiddleName']);
    $memberlastName = trim($_POST['memberlastName']);
    $mpasswd1 = trim($_POST['memberPasswd']);
    $mpasswd2 = trim($_POST['memberPasswd2']);
    $data['mpasswd'] = MD5($memberID);
   
    if (empty($memberID) OR empty($memberName))
    {
        utility::jsAlert(__('Member ID and Name can\'t be empty')); //mfc
        exit();
    }
    
    if (empty($_POST['memberEmail']))
    {
        utility::jsAlert(__('Member Email Id can\'t be empty')); //mfc
        exit();
    }
    
    else
    {
        // include custom fields file
        if (file_exists(MODULES_BASE_DIR.'membership/member_custom_fields.inc.php'))
        {
            include MODULES_BASE_DIR.'membership/member_custom_fields.inc.php';
        }

        /**
         * Custom fields
         */
        if (isset($member_custom_fields)) {
            if (is_array($member_custom_fields) && $member_custom_fields) {
                foreach ($member_custom_fields as $fid => $cfield) {
                    // custom field
                    $cf_dbfield = $cfield['dbfield'];
                    if (isset($_POST[$cf_dbfield]) AND trim($_POST[$cf_dbfield]) != '') {
                        $data[$cf_dbfield] = trim($dbs->escape_string(strip_tags($_POST[$cf_dbfield], $sysconf['content']['allowable_tags'])));
                    }
                }
            }
        }
        
        $data['member_id'] = $dbs->escape_string($memberID);
        $data['member_name'] = $dbs->escape_string($memberName);
        $data['member_mid_name'] = $dbs->escape_string($membermiddleName);
        $data['member_last_name'] = $dbs->escape_string($memberlastName);
        $data['member_type_id'] = (integer)$_POST['memberTypeID'];
        $data['inst_name'] = trim($dbs->escape_string(strip_tags($_POST['instName'])));
        $data['gender'] = trim($dbs->escape_string(strip_tags($_POST['gender'])));
         $data['birth_date'] = date('Y-m-d',strtotime($_POST['birthDate']));
        $data['register_date'] = date('Y-m-d',strtotime($_POST['regDate']));
        
        // member since date
        $member_since = trim($dbs->escape_string(strip_tags($_POST['sinceDate'])));
       
        if (isset($_POST['updateRecordID'])) 
        {
            $data['member_since_date'] = $member_since;
        }
        else 
        {
            if ($member_since) 
            {
                $data['member_since_date'] = $member_since;
            }
            else 
            {
                $data['member_since_date'] = $data['register_date'];
            }
        }
        
         $data['expire_date'] = date('Y-m-d',strtotime($_POST['expDate']));
        if (isset($_POST['extend']) AND !empty($_POST['extend'])) 
       {
            // get membership periode from database
            $mtype_query = $dbs->query("SELECT member_periode FROM mst_member_type WHERE member_type_id=".$data['member_type_id']);
            $mtype_data = $mtype_query->fetch_row();
            $data['register_date'] = date('Y-m-d');
            $data['expire_date'] = simbio_date::getNextDate($mtype_data[0], $data['register_date']);
        }
        $data['pin'] = trim($dbs->escape_string(strip_tags($_POST['memberPIN'])));
        $data['member_address'] = trim($dbs->escape_string(strip_tags($_POST['memberAddress'])));
        $data['member_phone'] = trim($dbs->escape_string(strip_tags($_POST['memberPhone'])));
        $data['member_fax'] = trim($dbs->escape_string(strip_tags($_POST['memberFax'])));
        $data['postal_code'] = trim($dbs->escape_string(strip_tags($_POST['memberPostal'])));
        $data['member_notes'] = trim($dbs->escape_string(strip_tags($_POST['memberNotes'])));
        $data['member_email'] = trim($dbs->escape_string(strip_tags($_POST['memberEmail'])));
//        $data['is_pending'] = intval($_POST['isPending']);
        $data['input_date'] = date('Y-m-d');
        $data['last_update'] = date('Y-m-d');
        $data['issue']= intval($_POST['issue']);
	$data['checkin']= intval($_POST['checkin']);
       $data['reserve']= intval($_POST['reserve']);
       
	if (!empty($_FILES['image']) AND $_FILES['image']['size']) {
            // create upload object
            $upload = new simbio_file_upload();
            $upload->setAllowableFormat($sysconf['allowed_images']);
            $upload->setMaxSize($sysconf['max_image_upload']*1024); // approx. 100 kb
            $upload->setUploadDir(IMAGES_BASE_DIR.'persons');
            // give new name for upload file
            $new_filename = 'member_'.$data['member_id'];
            $upload_status = $upload->doUpload('image', $new_filename);
            if ($upload_status == UPLOAD_SUCCESS) {
                $data['member_image'] = $dbs->escape_string($upload->new_filename);
            }
        }
        // password confirmation
        if (($mpasswd1 AND $mpasswd2) AND ($mpasswd1 === $mpasswd2)) 
        {
            $data['mpasswd'] = 'literal{MD5(\''.$mpasswd2.'\')}';
	}

        // create sql op object
        $sql_op = new simbio_dbop($dbs);
        if (isset($_POST['updateRecordID'])) 
        {
            unset($data['input_date']);
            // filter update record ID
            $updateRecordID = $dbs->escape_string(trim($_POST['updateRecordID']));
            $old_member_ID = $updateRecordID;
            // update the data
            $update = $sql_op->update('tblstudent', $data, "enrollment_no='$updateRecordID'");
            if ($update) {
                // update other tables contain this member ID
                @$dbs->query('UPDATE loan SET member_id=\''.$data['member_id'].'\' WHERE member_id=\''.$old_member_ID.'\'');
                @$dbs->query('UPDATE fines SET member_id=\''.$data['member_id'].'\' WHERE member_id=\''.$old_member_ID.'\'');
                utility::jsAlert(__('Member Data Successfully Updated'));

                if (isset($upload_status)) {
                    if ($upload_status == UPLOAD_SUCCESS) {
                        // write log
                        utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'membership', $_SESSION['realname'].' upload image file '.$upload->new_filename);
                        utility::jsAlert(__('Image Uploaded Successfully'));
                    } else {
                        // write log
                        utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'membership', 'ERROR : '.$_SESSION['realname'].' FAILED TO upload image file '.$upload->new_filename.', with error ('.$upload->error.')');
                        utility::jsAlert(__('Image FAILED to upload'));
                    }
                }
                // write log
                utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'membership', $_SESSION['realname'].' update member data ('.$memberName.') with ID ('.$memberID.')');
                echo '<script type="text/javascript">parent.setContent(\'mainContent\', parent.getPreviousAJAXurl(), \'post\');</script>';
            } else { utility::jsAlert(__('Member Data FAILED to Save/Update. Please Contact System Administrator')."\nDEBUG : ".$sql_op->error); }
            exit();
        } 
        else 
         {
             
            /* INSERT RECORD MODE */
            if (!$mpasswd1 AND !$mpasswd2) 
            {
                $data['mpasswd'] = 'literal{NULL}'; 
             
            }
            // insert the data
            
            $insert = $sql_op->insert($inte_schema.'.tblstudent', $data);
            if ($insert) {
                utility::jsAlert(__('New Member Data Successfully Saved'));
		if (isset($upload_status)) {
                    if ($upload_status == UPLOAD_SUCCESS) {
                        // write log
                        utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'membership', $_SESSION['realname'].' upload image file '.$upload->new_filename);
                        utility::jsAlert(__('Image Uploaded Successfully'));
                    } else {
                        // write log
                        utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'membership', 'ERROR : '.$_SESSION['realname'].' FAILED TO upload image file '.$upload->new_filename.', with error ('.$upload->error.')');
                        utility::jsAlert(__('Image FAILED to upload'));
                    }
                }
                // write log
                utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'membership', $_SESSION['realname'].' add new member ('.$memberName.') with ID ('.$memberID.')');
                echo '<script type="text/javascript">parent.setContent(\'mainContent\', \''.$_SERVER['PHP_SELF'].'\', \'get\');</script>';
            } else { utility::jsAlert(__('Member Data FAILED to Save/Update. Please Contact System Administrator')."\nDEBUG : ".$sql_op->error); }
            exit();
        }
    }
    exit();
} 
else if (isset($_POST['batchExtend']) && $can_read && $can_write) 
{
    /* BATCH extend membership proccessing */
    $curr_date = date('Y-m-d');
    $num_extended = 0;
    foreach ($_POST['itemID'] as $itemID) {
        $memberID = $dbs->escape_string(trim($itemID));
        // get membership periode from database
        $mtype_q = $dbs->query('SELECT member_periode, m.first_name FROM '.$inte_schema.'.tblstudent AS m
            LEFT JOIN category_user AS mt ON m.user_group_id=mt.user_id
            WHERE m.SUB_INSTITUTE_ID=\''.$_SESSION['SUB_INSTITUTE_ID'].'\' AND m.enrollment_no=\''.$memberID.'\'');
        $mtype_d = $mtype_q->fetch_row();
        $expire_date = simbio_date::getNextDate($mtype_d[0], $curr_date);
        @$dbs->query('UPDATE '.$inte_schema.'.tblstudent SET expire_date=\''.$expire_date.'\' WHERE SUB_INSTITUTE_ID=\''.$_SESSION['SUB_INSTITUTE_ID'].'\' AND enrollment_no=\''.$memberID.'\'');
        // write log
        utility::writeLogs($dbs, $inte_schema.'.tblstudent', $_SESSION['mid'], 'membership', $_SESSION['realname'].' extends membership for member ('.$mtype_d[1].') with ID ('.$memberID.')');
        $num_extended++;
    }
    header('Location: '.MODULES_WEB_ROOT_DIR.'membership/index.php?expire=true&numExtended='.$num_extended);
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
    $still_have_loan = array();
    if (!is_array($_POST['itemID'])) 
    {
        // make an array
        $_POST['itemID'] = array($dbs->escape_string(trim($_POST['itemID'])));
    }
    
    // loop array
    foreach ($_POST['itemID'] as $itemID) 
    {
//utility::jsAlert('notify');
       $itemID = $dbs->escape_string(trim($itemID));
        // check if the member still have loan
       
       
        $loan_q = $dbs->query('SELECT DISTINCT m.enrollment_no, m.first_name, COUNT(l.loan_id), m.roll_no as Roll_no FROM tblstudent AS m
            LEFT JOIN loan AS l ON (m.enrollment_no=l.member_id AND l.loan_date is not null  AND l.return_date is null)
            WHERE m.SUB_INSTITUTE_ID=\''.$_SESSION['SUB_INSTITUTE_ID'].'\' AND m.enrollment_no=\''.$itemID.'\' GROUP BY m.enrollment_no');
        $loan_d = $loan_q->fetch_row();
       //send mail of deletion by Parth 23/7/2011	
       require SENAYAN_BASE_DIR.'admin/modules/membership/member_notification_mail.php';
	//send end mail of deletion by Parth 23/7/2011
        if ($loan_d[2] < 1) {
            if (!$sql_op->delete('tblstudent', "enrollment_no='$itemID'")) {
                $error_num++;
            } else {
                // write log
                utility::writeLogs($dbs, 'tblstudent', $_SESSION['mid'], 'membership', $_SESSION['realname'].' DELETE member data ('.$loan_d[1].') with ID ('.$loan_d[0].')');

            }
        } else {
            $still_have_loan[] = $loan_d[0].' - '.$loan_d[1];
            $error_num++;
        }
    }

    if ($still_have_loan)
    {
        $members = '';
        foreach ($still_have_loan as $mbr) 
        {
            $members .= $mbr."\n";
        }
        utility::jsAlert(__('Below member data can\'t be deleted because still have unreturned item(s)').' : '."\n".$mbr);
        exit();
    }
    // error alerting
    if ($error_num == 0) 
    {
        utility::jsAlert(__('All Data Successfully Deleted'));
        echo '<script type="text/javascript">parent.setContent(\'mainContent\', \''.$_SERVER['PHP_SELF'].'?'.$_POST['lastQueryStr'].'\', \'post\');</script>';
	
    }
    else 
    {
        utility::jsAlert(__('Some or All Data NOT deleted successfully! Please contact system administrator'));
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
if(isset($_REQUEST['action']))
{
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'membership/index.php?action=detail class="headerText2">Add New Member</a>';
}
else if(isset($_REQUEST['expire']))
{
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'membership/index.php?expire=true class="headerText2">View Expired Member</a>';
}
else
{
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'membership/index.php class="headerText2">View Member List</a>';
}

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
<a href="<?php echo MODULES_WEB_ROOT_DIR; ?>membership/index.php" class="headerText2"><?php echo __('View Member List'); ?></a> </li>
<!--<li><a href="<?php //echo  MODULES_WEB_ROOT_DIR; ?>membership/index.php?action=detail" class="headerText2"><?php //echo __('Add New Member'); ?></a> </li>-->
<!--<li> <a href="<?php //echo  MODULES_WEB_ROOT_DIR; ?>membership/member_type.php" class="headerText2"><?php // echo __('Assign member Role'); ?></a> </li>-->
<li>
<a href="<?php echo  MODULES_WEB_ROOT_DIR; ?>membership/index.php?expire=true" class="headerText2"><?php echo __('View Expired Member'); ?></a> 
</li>
</ul>
	</td>
</tr>
</table>

<? 
 if((isset($_POST['detail']) OR (!isset($_GET['action']) AND $_GET['action'] != 'detail')) ){
?>
<fieldset class="menuBox">
<div class="menuBoxInner memberIcon">
    <?php echo strtoupper(__('Member Area')); ?> <!-- <a href="<?php echo MODULES_WEB_ROOT_DIR; ?>membership/index.php?action=detail" class="headerText2"><?php //echo __('Add New Member'); ?></a>
    &nbsp; <a href="//<?php //echo MODULES_WEB_ROOT_DIR; ?>membership/index.php" class="headerText2"><?php //echo __('Member List'); ?></a>
    &nbsp; <a href="<?php //echo MODULES_WEB_ROOT_DIR; ?>membership/index.php?expire=true" class="headerText2" style="color: #990000;"><?php //echo __('View Expired Member'); ?></a>-->
    <p class="only_border">&nbsp;</p>
    <form name="search" action="<?php echo MODULES_WEB_ROOT_DIR; ?>membership/index.php" id="search" method="get" style="display: inline;"><?php echo __('Member Search'); ?> :
   <!--comment by iresh on 25-1-2011--> <input type="text" name="keywords"  /><?php if (isset($_GET['expire'])) { echo '<input type="hidden" name="expire" value="true" />'; } ?>

 <!--added by iresh on 25-1-2011 <input type="text" name="keywords" width=140px/><?php if (isset($_GET['expire'])) { echo '<input type="hidden" name="expire" value="true" />'; } ?>-->
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
    
   if (!($can_read AND $can_write)) {
        die('<div class="errorBox">'.__('You don\'t have enough privileges to view this section').'</div>');
    }
    /* RECORD FORM */
    $itemID = $dbs->escape_string(trim(isset($_POST['itemID'])?$_POST['itemID']:''));
    $rec_q = $dbs->query("SELECT * FROM ".$inte_schema.".tblstudent WHERE SUB_INSTITUTE_ID='$_SESSION[SUB_INSTITUTE_ID]' AND enrollment_no='$itemID'");
    $rec_d = $rec_q->fetch_assoc();

    // create new instance
    $form = new simbio_form_table_AJAX('mainForm', $_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'], 'post','s(this.value)');
    
   // $form->button = 'name = "Reset"';
  
   // $form->submit_button_attr = 'name="saveData" value="'.__('Save').'" class="button" ';
    //$form->submit_button_attr = 'name="resetData" value="'.__('Reset').'" class="button" ';
    
    // form table attributes
    $form->table_attr = 'align="center" id="dataList" cellpadding="5" cellspacing="0"';
    $form->table_header_attr = 'class="alterCell" style="font-weight: bold;"';
    $form->table_content_attr = 'class="alterCell2"';

    // edit mode flag set
    if ($rec_q->num_rows > 0) {
        $form->edit_mode = TRUE;
        // record ID for delete process
        $form->record_id = $itemID;
        // form record title
        $form->record_title = $rec_d['first_name'];
        // submit button attribute
//       $form->submit_button_attr = 'name="saveData" value="'.__('Update').'" class="button"';
    }

    /* Form Element(s) */
    if ($form->edit_mode) {
        // check if member expired
        $curr_date = date('Y-m-d');
        $compared_date = simbio_date::compareDates($rec_d['expire_date'], $curr_date);
        $is_expired = ($compared_date == $curr_date);
        $expired_message = '';
        if ($is_expired) {
            // extend membership
            $chbox_array[] = array('1', __('Extend'));
            $form->addCheckBox('extend', __('Extend Membership'), $chbox_array);
            $expired_message = '<b style="color: #990000;">('.__('Membership Already Expired').')</b>';
        }
    }

    // include custom fields file
    if (file_exists(MODULES_BASE_DIR.'membership/member_custom_fields.inc.php')) {
        include MODULES_BASE_DIR.'membership/member_custom_fields.inc.php';
    }

     $mtype_query = $dbs->query("SELECT user_id, user_name FROM category_user order by user_name");
     $mtype_options = array();
     while ($mtype_data = $mtype_query->fetch_row()) 
     {
            $mtype_options[] = array($mtype_data[0], $mtype_data[1]);
     }

//     $element = new Zend_Form_Element_Reset('Reset');
//     $form->addElement($element,'Reset');
    
//$str_input = simbio_form_element::selectlist('memberTypeID', $mtype_options, $rec_d['member_type_id'],'onchange=selectcheckbox(this.value);');
$str_input = simbio_form_element::selectlist('memberTypeID', $mtype_options, $rec_d['user_group_id']);
$form->addAnything(__('Membership Type').'*', $str_input);
    // member code
    $str_input = simbio_form_element::textField('text', 'memberID', $rec_d['enrollment_no'], 'id="memberID" onblur="ajaxCheckID(\''.SENAYAN_WEB_ROOT_DIR.'admin/AJAX_check_id.php\', \'tblstudent\', \'student_id\', \'msgBox\', \'memberID\')" style="width: 30%;"onkeyup="return checkspecialcharacterdynamic(this.name);"onchange="maxlength(this.name,12);"');
    $str_input .= ' &nbsp; <span id="msgBox">&nbsp;</span>';
     //$form->addButton('button', 'reset');
    
    $form->addAnything(__('Member ID').'*', $str_input);
    
    
    
    //$form->addTextField('text', 'memberName', __('Member Name').'*', $rec_d['member_name'], 'style="width:100%;" onchange="return checkspecialcharacterdynamic(this.name);"');
     
    $table = new simbio_table();
    
   
    $text= simbio_form_element::textField('text', 'memberName', $rec_d['first_name'], 'style="width:30%;" onkeyup="return checkspecialcharacterdynamic(this.name);"onblur="charactercheck(this.name);"onchange="maxlength(this.name,20);"');
    $text.= simbio_form_element::textField('text', 'membermiddleName', $rec_d['father_name'], 'style="width:30%;" onkeyup="return checkspecialcharacterdynamic(this.name);"onblur="charactercheck(this.name);"onchange="maxlength(this.name,20);"');
    $text.= simbio_form_element::textField('text', 'memberlastName', $rec_d['last_name'], 'style="width:30%;" onkeyup="return checkspecialcharacterdynamic(this.name);"onblur="charactercheck(this.name);"onchange="maxlength(this.name,20);"');
    $form->addAnything("Member name", $text);
  
    
    $form->addTextField('text', 'memberEmail', __('E-mail*'), $rec_d['email'], 'style="width: 40%;" onchange="return emailcheck(this.name);"');
    ?>

<?php
        
    // member birth date
    $form->addDateField('birthDate', __('Birth Date*'),$form->edit_mode?date('d-m-Y',strtotime($rec_d['birthdate'])):date('d-m-Y'));
    // member since date
    
    $form->addDateField('sinceDate', __('Member Since').'*', $form->edit_mode?$rec_d['created_on']:date('d-m-Y'));
    // member register date
    //:date('d-m-Y')
    $form->addDateField('regDate', __('Register Date').'*', $form->edit_mode?date('d-m-Y',strtotime($rec_d['created_on'])):date('d-m-Y'));
    // member expire date
    if ($form->edit_mode) 
    {
        
      //  $form->addDateField('expDate', __('Expiry Date').'*', $rec_d['expire_date']);
        $form->addDateField('expDate', __('Expiry Date').'*',$rec_d['expire_date']?date('d-m-Y',strtotime($rec_d['expire_date'])):date('d-m-Y'));
    }
    else
   {
        
        $form->addDateField('expDate', __('Expiry Date').'*', $form->edit_mode?date('d-m-Y',strtotime($rec_d['expire_date'])):date('d-m-Y'));
    }
    // member gender
    $gender_chbox[0] = array('m', __('Male'));
    $gender_chbox[1] = array('f', __('Female'));
    $form->addRadio('gender', __('Gender*'), $gender_chbox, !empty($rec_d['gender'])?$rec_d['gender']:'0');
    // member address
    $form->addTextField('textarea', 'memberAddress', __('Address'), $rec_d['address'], 'rows="2" style="width: 50%;"onkeyup="return checkspecialcharacterdynamic(this.name);"onchange="maxlength(this.name,150);"');
    // member postal
    $form->addTextField('text', 'memberPostal', __('Postal Code'), $rec_d['zipcode'], 'style="width: 30%;" "maxlength=20" onchange="return numericcheck(this.name);"');
    // member phone
    $form->addTextField('text', 'memberPhone', __('Mobile Number'), $rec_d['mobile'], 'style="width: 30%;" "maxlength=20" onchange="return numericcheck(this.name);"');
    // member fax
    $form->addTextField('text', 'memberFax', __('Fax Number'), $rec_d['member_fax'], 'style="width: 30%;"  "maxlength=20" onchange="return numericcheck(this.name);"');
    // member pin
    /**
     * Custom fields
     */
    if (isset($member_custom_fields)) {
        if (is_array($member_custom_fields) && $member_custom_fields) {
            foreach ($member_custom_fields as $fid => $cfield) {

                // custom field properties
                $cf_dbfield = $cfield['dbfield'];
                $cf_label = $cfield['label'];
                $cf_default = $cfield['default'];
                $cf_data = (isset($cfield['data']) && $cfield['data'])?$cfield['data']:array();

                // custom field processing
                if (in_array($cfield['type'], array('text', 'longtext', 'numeric'))) {
                    $cf_max = isset($cfield['max'])?$cfield['max']:'200';
                    $cf_width = isset($cfield['width'])?$cfield['width']:'50';
                    $form->addTextField( ($cfield['type'] == 'longtext')?'textarea':'text', $cf_dbfield, $cf_label, isset($rec_d[$cf_dbfield])?$rec_d[$cf_dbfield]:$cf_default, 'style="width: '.$cf_width.'%;" maxlength="'.$cf_max.'"');
                } else if ($cfield['type'] == 'dropdown') {
                    $form->addSelectList($cf_dbfield, $cf_label, $cf_data, isset($rec_d[$cf_dbfield])?$rec_d[$cf_dbfield]:$cf_default);
                } else if ($cfield['type'] == 'checklist') {
                    $form->addCheckBox($cf_dbfield, $cf_label, $cf_data, isset($rec_d[$cf_dbfield])?$rec_d[$cf_dbfield]:$cf_default);
                } else if ($cfield['type'] == 'choice') {
                    $form->addRadio($cf_dbfield, $cf_label, $cf_data, isset($rec_d[$cf_dbfield])?$rec_d[$cf_dbfield]:$cf_default);
                } else if ($cfield['type'] == 'date') {
                    $form->addDateField($cf_dbfield, $cf_label, isset($rec_d[$cf_dbfield])?$rec_d[$cf_dbfield]:$cf_default);
                }
            }
        }
    }
    // member photo
    if ($rec_d['image_file']) {
        $str_input = '<a href="'.SENAYAN_WEB_ROOT_DIR.'images/persons/'.$rec_d['member_image'].'" target="_blank"><strong>'.$rec_d['member_image'].'</strong></a><br />';
        $str_input .= simbio_form_element::textField('file', 'image');
        $str_input .= ' '.__('Maximum').' '.$sysconf['max_image_upload'].' KB'; //mfc
        $form->addAnything(__('Photo'), $str_input);
    } else {
        $str_input = simbio_form_element::textField('file', 'image');
        $str_input .= ' '.__('Maximum').' '.$sysconf['max_image_upload'].' KB'; //mfc
        $form->addAnything(__('Photo'), $str_input);
    }
    // edit mode messagge
    if ($form->edit_mode) {
        echo '<div class="infoBox" style="overflow: auto;">'
            .'<div style="float: left; width: 80%;">'.__('You are going to edit data').' : <b>'.$rec_d['first_name'].'</b> <br />'.__('Last Updated').' '.$rec_d['expire_date'].' '.$expired_message
            .'<div>'.__('Leave Password field blank if you don\'t want to change the password').'</div>'
            .'</div>';
            if ($rec_d['image_file']) {
                if (file_exists(IMAGES_BASE_DIR.'persons/'.$rec_d['image_file'])) {
                    echo '<div style="float: right;"><img src="../lib/phpthumb/phpThumb.php?src=../../images/persons/'.urlencode($rec_d['image_file']).'&w=53" style="border: 1px solid #999999" /></div>';
                }
            }
        echo '</div>'."\n";
    }
    // print out the form object
    echo $form->printOut();
} 
else
{    
    
   
   $table_spec ="        
    (
        SELECT 
        m.enrollment_no, 
        m.enrollment_no AS 'Enrollment_No1', 
		m.roll_no as 'Roll_no',
		m.mobile as 'Mobile',
		concat_ws(' ',cs.name,ss.name) as 'Std_Div',
        concat_ws(' ',m.first_name,m.middle_name,m.last_name) AS 'Member_Name',
        m.email AS 'E_mail', 
        up.name AS 'Membership_Type'
        FROM ".$inte_schema.".tblstudent AS m 
        INNER JOIN ".$inte_schema.".tbluserprofilemaster up ON up.id = m.user_profile_id
        INNER JOIN ".$inte_schema.".tblstudent_enrollment se ON se.student_id=m.id AND se.SYEAR='".$dyear."' AND se.end_date IS NULL AND m.sub_institute_id = se.sub_institute_id AND m.sub_institute_id = '".$SUB_INSTITUTE_ID."'
        INNER JOIN ".$inte_schema.".standard cs ON cs.id=se.standard_id AND cs.sub_institute_id = se.sub_institute_id
        INNER JOIN ".$inte_schema.".division ss ON ss.id=se.section_id AND ss.sub_institute_id = se.sub_institute_id
		
        UNION
		
        SELECT 
        m.user_name, 
        m.user_name AS 'Enrollment_No1', 
		' ' as 'Roll_no',
		m.mobile as 'Mobile',
		' ' as 'Std-Div',
        concat_ws(' ',m.first_name,m.middle_name,m.last_name) AS 'Member_Name', 
        m.email AS 'E_mail', 
        up.name AS 'Membership_Type'
        FROM ".$inte_schema.".tbluser m
        INNER JOIN ".$inte_schema.".tbluserprofilemaster up ON up.id = m.user_profile_id AND m.sub_institute_id = '".$SUB_INSTITUTE_ID."'
    )AS X WHERE X.enrollment_no IS NOT NULL";
// echo     $table_spec;
    $custom_query=$table_spec;
    $datagrid = new simbio_datagrid();
    $datagrid->select_flag='';
    if ($can_read AND $can_write) 
    {
        $datagrid->setSQLColumn('X.enrollment_no',
          'X.Enrollment_No1 AS \''.__('Member ID').'\'',   
  		  'X.Roll_no AS \''.__('Roll_no').'\'',           
            'X.Member_Name AS \''.__('Member Name').'\'',
            //'X.E_mail AS \''.__('E-mail').'\'',
			'X.Mobile AS \''.__('Mobile').'\'',
			'X.Std_Div AS \''.__('Std-Div').'\'',
            'X.Membership_Type AS \''.__('Membership Type').'\''
            );
    } 
    else 
    {
        $datagrid->setSQLColumn('X.Enrollment_No AS \''.__('Member ID').'\'');
        /*    'X.first_name AS \''.__('Member Name').'\'',
            'X.user_group_name AS \''.__('Membership Type').'\'',
            'X.email AS \''.__('E-mail').'\'',
            'DATE_FORMAT(X.updated_on,"%d-%m-%Y") AS \''.__('Last Updated').'\'');*/
    }
     //$datagrid->setSQLorder('X.Enrollment_No ASC');
	//$datagrid->setSQLorder('X.Member_Name ASC');
	$datagrid->setSQLorder('CAST(X.Enrollment_No AS SIGNED) ASC');
    $datagrid->$custom_query=$custom_query;

    
    $criteria = 'm.enrollment_no IS NOT NULL ';
    if (isset($_GET['keywords']) AND $_GET['keywords']) 
    {
       $keywords = $dbs->escape_string($_GET['keywords']);
       $table_spec .= " AND (X.Member_Name LIKE '%$keywords%'   OR    X.Enrollment_No1 LIKE '%$keywords%') ";
    }
    if (isset($_GET['expire'])) 
    {
        $criteria .= " AND TO_DAYS('".date('Y-m-d')."')>TO_DAYS(m.expire_date)";
    }
    
    //$datagrid->setSQLCriteria($criteria);
    
    $datagrid->icon_edit = SENAYAN_WEB_ROOT_DIR.'admin/'.$sysconf['admin_template']['dir'].'/'.$sysconf['admin_template']['theme'].'/edit.gif';
    $datagrid->table_name = '';
    $datagrid->table_attr = 'align="center" id="dataList" cellpadding="5" cellspacing="0"';
    $datagrid->table_header_attr = 'class="dataListHeader" style="font-weight: bold;"';    
    $datagrid->chbox_form_URL = $_SERVER['PHP_SELF'];
    
    $datagrid_result = $datagrid->createDataGrid_temp($dbs, $table_spec, 20, ($can_read AND $can_write));
    if ((isset($_GET['keywords']) AND $_GET['keywords']) OR isset($_GET['expire']))
    {
        echo '<div class="infoBox">';
        if (isset($_GET['expire'])) 
        {
            echo '<b style="color: #FF0000;">'.__('Expired Member List').'</b><hr size="1" />';
            //echo '<div><input type="button" value="'.__('Extend Selected Member(s)').'" onclick="javascript: if (confirm(\''.__('Are you sure to EXTEND membership for selected members?').'\')) { setContent(\'mainContent\', \''.MODULES_WEB_ROOT_DIR.'membership/index.php?expire=1\', \'post\', $H($(\'memberList\').serialize(true)).update({ batchExtend: \'true\' }) ); }" class="button" /></div>';
            if (isset($_GET['numExtended']) AND $_GET['numExtended'] > 0) 
            { echo '<div><strong>'.$_GET['numExtended'].'</strong> '.__('members extended!').'</div>'; }
        }
        if (isset($_GET['keywords']) AND $_GET['keywords']) 
        {
            echo __('Found').' '.$datagrid->num_rows.' '.__('from your search with keyword').' : "'.$_GET['keywords'].'"'; //mfc
        }
        echo '</div>';
    }

    echo $datagrid_result;
}

?>
