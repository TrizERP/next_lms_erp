<?php
session_start();
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
error_reporting(0);
if (!$can_read) {
    die('<div class="errorBox">'.__('You don\'t have enough privileges to access this area!').'</div>');
}

/* RECORD OPERATION */
if (isset($_POST['saveData']) AND $can_read AND $can_write) 
{
    
    
    
     $topic = trim(strip_tags($_POST['subjectType']));
     $level = trim(strip_tags($_POST['sub_level']));                
     $subject = trim(strip_tags($_POST['subject']));
    //  utility::jsAlert(__($level));
    // check form validity
    if (empty($topic) || $topic=="N/A") 
    {
        utility::jsAlert(__('Subject Type can\'t be empty'));
        exit();
    } 
    if (empty($level) || $level=="N/A") 
    {
       
        utility::jsAlert(__('Subject Level can\'t be empty'));
        exit();
    } 
 if (empty($subject)) {
        utility::jsAlert(__('Subject can\'t be empty'));
        exit();
    } 
	
else {
	$data['topic_id'] = $dbs->escape_string($topic);
        $data['subject_type_name'] = $_POST['subject'];
        $data['sub_level'] =intval($_POST['sub_level']);
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
            $update = $sql_op->update('mst_subject_type', $data, 'subject_type_id='.$updateRecordID);
            if ($update) {
                utility::jsAlert(__('Subject Data Successfully Updated'));
                echo '<script type="text/javascript">parent.setContent(\'mainContent\', parent.getPreviousAJAXurl(), \'post\');</script>';
            } else { utility::jsAlert(__('Subject Data FAILED to Updated. Please Contact System Administrator')."\nDEBUG : ".$sql_op->error); }
            exit();
        } else {
		print_r($data);
		
            /* INSERT RECORD MODE */
            // insert the data
            $insert = $sql_op->insert('mst_subject_type', $data);
            if ($insert) {
                utility::jsAlert(__('New Subject Data Successfully Saved'));
		echo '<script type="text/javascript">parent.setContent(\'mainContent\', \''.$_SERVER['PHP_SELF'].'\', \'post\');</script>';
            } else { utility::jsAlert(__('Subject Data FAILED to Save. Please Contact System Administrator')."\nDEBUG : ".$sql_op->error); }
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
    // create sql op object
    $sql_op = new simbio_dbop($dbs);
    $failed_array = array();
    $error_num = 0;
    
    if (!is_array($_POST['itemID'])) 
    {
        
        // make an array
        $_POST['itemID'] = array((integer)$_POST['itemID']);
    }
    // loop array
    foreach ($_POST['itemID'] as $itemID) 
    {
        $itemID = (integer)$itemID;
     
        /*if (!$sql_op->delete('mst_topic', 'topic_id='.$itemID)) 
        {
            $error_num++;
        }*/
        if (!$sql_op->delete('mst_subject_type', 'subject_type_id ='.$itemID)) 
        {
            $error_num++;
        }
    }

    // error alerting
    if ($error_num == 0)
    {
        utility::jsAlert(__('All Data Successfully Deleted'));
        echo '<script type="text/javascript">parent.setContent(\'mainContent\', \''.$_SERVER['PHP_SELF'].'?'.$_POST['lastQueryStr'].'\', \'post\');</script>';
    }
    else 
    {
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
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'master_file/subject_type.php class="headerText2">Subject</a>';
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
<a href="<?php echo MODULES_WEB_ROOT_DIR; ?>master_file/subject_type.php?action=detail" class="headerText2"><?php echo __('Add New Subject'); ?></a></li>
<li> 
<a href="<?php echo MODULES_WEB_ROOT_DIR; ?>master_file/subject_type.php" class="headerText2"><?php echo __('Subject List'); ?></a> </li>
<li>
<a href="<?php echo MODULES_WEB_ROOT_DIR; ?>master_file/topic.php?action=detail" class="headerText2"><?php echo __('Add New Subject Type'); ?></a></li>
<li> 
<a href="<?php echo MODULES_WEB_ROOT_DIR; ?>master_file/topic.php" class="headerText2"><?php echo __('Subject Type List'); ?></a> </li>
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
    <!--<?php echo strtoupper(__('Subject Type')); ?> - <a href="<?php echo MODULES_WEB_ROOT_DIR; ?>master_file/subject_type.php?action=detail" class="headerText2"><?php echo __('Add New Subject'); ?></a>
    &nbsp; <a href="<?php echo MODULES_WEB_ROOT_DIR; ?>master_file/subject_type.php" class="headerText2"><?php echo __('Subject List'); ?></a> &nbsp; <a href="<?php echo MODULES_WEB_ROOT_DIR; ?>master_file/topic.php?action=detail" class="headerText2"><?php echo __('Add New Subject Type'); ?></a>&nbsp; <a href="<?php echo MODULES_WEB_ROOT_DIR; ?>master_file/topic.php" class="headerText2"><?php echo __('Subject Type List'); ?></a>-->
    <p class="only_border">&nbsp;</p>
    <form name="search" action="<?php echo MODULES_WEB_ROOT_DIR; ?>master_file/subject_type.php" id="search" method="get" style="display: inline;"><?php echo __('Search'); ?> :
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
if (isset($_POST['detail']) OR (isset($_GET['action']) AND $_GET['action'] == 'detail')) {
    if (!($can_read AND $can_write)) {
        die('<div class="errorBox">'.__('You don\'t have enough privileges to access this area!').'</div>');
    }
    /* RECORD FORM */
    $itemID = (integer)isset($_POST['itemID'])?$_POST['itemID']:0;
    //echo 'SELECT st.subject_type_id,st.subject_type_name,t.topic_id,t.topic FROM mst_subject_type AS st LEFT JOIN mst_topic AS t ON t.topic_id=st.topic_id 			  WHERE subject_type_id='.$itemID;die;
    
    $rec_q = $dbs->query('SELECT st.sub_level,st.subject_type_id,st.subject_type_name,t.topic_id,t.topic 
			  FROM mst_subject_type AS st
			  LEFT JOIN mst_topic AS t ON t.topic_id=st.topic_id
			  WHERE subject_type_id='.$itemID);
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
        $form->record_title = $rec_d['subject_type_name'];
        // submit button attribute
        $form->submit_button_attr = 'name="saveData" value="'.__('Update').'" class="button"';
    }

    /* Form Element(s) */
   $subject_type=$dbs->query('select topic_id,topic from mst_topic');
   $subj_type_options=array('N/A');
   while($row=$subject_type->fetch_row())
   {
	$subj_type_options[] = array($row[0],$row[1]);	
   }
    $form->addSelectList('subjectType', __('Subject Type'), $subj_type_options, $rec_d['topic_id']);
    
    $sub_level=array('N/A');
    $sub_level[]=array('1','Primary');	
    $sub_level[]=array('2','Additional');	
    
    
    $form->addSelectList('sub_level', __('Level'), $sub_level,$rec_d['sub_level']);

    
     //comment by iresh on 25-1-2011$form->addTextField('text', 'topic', __('Subject').'*', $rec_d['topic'], 'style="width: 60%;"');
  /*added by iresh on 25-1-2011 */$form->addTextField('text', 'subject', __('Subject Name').'*', $rec_d['subject_type_name'], 'style="width: 140;" onblur="return charactercheck(this.name);"');
    // subject type
   
     // edit mode messagge
    if ($form->edit_mode) {
        echo '<div class="infoBox">'.__('You are going to edit data').' : <b>'.$rec_d['topic'].'</b>  <br />'.__('Last Update').$rec_d['last_update'].'</div>'; //mfc
    }
    // print out the form object
    echo $form->printOut();
} else {
    /* TOPIC LIST */
    // table spec
  $table_spec = 'mst_subject_type AS st INNER JOIN mst_topic AS t ON t.topic_id=st.topic_id';

    $subj_type_fld = 1;
    // create datagrid
    $datagrid = new simbio_datagrid();
    if ($can_read AND $can_write) {
        $subj_type_fld = 2;
       $datagrid->setSQLColumn('st.subject_type_id',
	    'st.subject_type_name AS \''.__('Subject').'\'',
            't.topic AS \''.__('Subject Type').'\'',
	    'DATE_FORMAT(st.last_update,"%d-%m-%Y") AS \''.__('Last Update').'\'');
    } else {
        $datagrid->setSQLColumn('st.subject_type_id',
            'st.subject_type_name AS \''.__('Subject').'\'',
            't.topic AS \''.__('Subject Type').'\'',
            'DATE_FORMAT(st.last_update,"%d-%m-%Y") AS \''.__('Last Update').'\'');
    }
   // $datagrid->setSQLorder('subject_type_name ASC');

    // is there any search
    if (isset($_GET['keywords']) AND $_GET['keywords']) {
        $keyword = $dbs->escape_string(trim($_GET['keywords']));
        $words = explode(' ', $keyword);
        if (count($words) > 1) {
            $concat_sql = ' (';
            foreach ($words as $word) {
                $concat_sql .= " st.subject_type_name LIKE '%$word%' OR t.topic LIKE '%$word%' AND";
            }
            // remove the last AND
            $concat_sql = substr_replace($concat_sql, '', -3);
            $concat_sql .= ') ';
            $datagrid->setSQLCriteria($concat_sql);
        } else {
            $datagrid->setSQLCriteria("st.subject_type_name LIKE '%$keyword%' OR t.topic LIKE '%$keyword%'");
        }
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
