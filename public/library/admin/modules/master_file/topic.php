<?php
session_start();
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
    die('<div class="errorBox">'.gettext('You don\'t have enough privileges to access this area!').'</div>');
}



/* RECORD OPERATION */
if (isset($_POST['saveData']) AND $can_read AND $can_write) 
 {
       $topic = trim(strip_tags($_POST['topic']));
    
        if (empty($topic))
       {
                 utility::jsAlert(gettext('Subject can\'t be empty'));
                 exit();
        }
        else 
        {
                    if (number_format($topic))
                    {
                        utility::jsAlert(gettext('Subject can\'t be Numeric'));
                        exit();
                    }
                    
            
                $data['topic'] = $dbs->escape_string($topic);
                $data['topic_type'] = trim($dbs->escape_string($_POST['subjectType']));
                $data['auth_list'] = trim($dbs->escape_string(strip_tags($_POST['authList'])));
                $data['input_date'] = date('Y-m-d');
                $data['last_update'] = date('Y-m-d');
                        $sql_op = new simbio_dbop($dbs);
                if (isset($_POST['updateRecordID'])) 
                {
            
                    unset($data['input_date']);
                    // filter update record ID
                    $updateRecordID = (integer)$_POST['updateRecordID'];
                    // update the data
                    $update = $sql_op->update('mst_topic', $data, 'topic_id='.$updateRecordID);
                    if ($update) 
                    {
                        utility::jsAlert(gettext('Subject Data Successfully Updated'));
                        echo '<script type="text/javascript">parent.setContent(\'mainContent\', parent.getPreviousAJAXurl(), \'post\');</script>';
                    }
                    else
                    {
                        utility::jsAlert(gettext('Subject Data FAILED to Updated. Please Contact System Administrator')."\nDEBUG : ".$sql_op->error); 
                       
                     }
                        exit();
                 }
                 else 
                 {
                        /* INSERT RECORD MODE */
                        // insert the data
                         $insert = $sql_op->insert('mst_topic', $data);
                        if ($insert) 
                         {
                                utility::jsAlert(gettext('New Subject Data Successfully Saved'));
                                echo '<script type="text/javascript">parent.setContent(\'mainContent\', \''.$_SERVER['PHP_SELF'].'\', \'post\');</script>';
                         }
                         else
                         {
                                utility::jsAlert(gettext('Subject Data FAILED to Save. Please Contact System Administrator')."\nDEBUG : ".$sql_op->error); 
                  
                         }
                                exit();
                   }
           }
                exit();
}
elseif (isset($_POST['itemID']) AND !empty($_POST['itemID']) AND isset($_POST['itemAction'])) 
{
    
     
     
       if (!($can_read AND $can_write)) 
        {
                die();
        }
            
            $sql_op = new simbio_dbop($dbs);
            $failed_array = array();
            $error_num = 0;
        if (!is_array($_POST['itemID'])) 
        {
            // make an array
            $_POST['itemID'] = array((integer)$_POST['itemID']);
        }
            
        
        foreach ($_POST['itemID'] as $itemID) 
        {
            $itemID = (integer)$itemID;
            $checkflag = 0;            
                        
                $rec_f = $dbs->query('SELECT topic_id from biblio_topic where topic_id ='.$itemID);
		while($row = $rec_f->fetch_assoc())
		{
                       
			$checkflag=1;	
		}
                
                if($checkflag==1)
                {


                     
                        $rec_name = $dbs->query('SELECT topic from mst_topic where topic_id ='.$itemID);
                        while($rownew = $rec_name->fetch_assoc())
                        {
                                $topic_name_set = $rownew['topic'];	
                        }
                         utility::jsAlert(gettext('You can not Delete Topic '.$topic_name_set));
                         exit();
                }
                else
                {
                        utility::jsAlert(gettext('DATA as been Deleted'));
                        if (!$sql_op->delete('mst_topic', 'topic_id='.$itemID)) 
                        {
                            $error_num++;
                	}
                }
        }
                   
                if ($error_num == 0) 
                {
                        utility::jsAlert(gettext('All Data Successfully Deleted'));
                        echo '<script type="text/javascript">parent.setContent(\'mainContent\', \''.$_SERVER['PHP_SELF'].'?'.$_POST['lastQueryStr'].'\', \'post\');</script>';
                }
                else
                {
                        utility::jsAlert(gettext('Some or All Data NOT deleted successfully!\nPlease contact system administrator'));
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
<a href="<?php echo MODULES_WEB_ROOT_DIR; ?>master_file/subject_type.php?action=detail" class="headerText2"><?php echo gettext('Add New Subject'); ?></a></li>
<li> 
<a href="<?php echo MODULES_WEB_ROOT_DIR; ?>master_file/subject_type.php" class="headerText2"><?php echo gettext('Subject List'); ?></a> </li>
<li>
<a href="<?php echo MODULES_WEB_ROOT_DIR; ?>master_file/topic.php?action=detail" class="headerText2"><?php echo gettext('Add New Subject Type'); ?></a></li>
<li> 
<a href="<?php echo MODULES_WEB_ROOT_DIR; ?>master_file/topic.php" class="headerText2"><?php echo gettext('Subject Type List'); ?></a> </li>
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
   <!-- <?php echo strtoupper(__('Subject Type')); ?> - <a href="<?php echo MODULES_WEB_ROOT_DIR; ?>master_file/subject_type.php?action=detail" class="headerText2"><?php echo __('Add New Subject'); ?></a>
    &nbsp; <a href="<?php echo MODULES_WEB_ROOT_DIR; ?>master_file/subject_type.php" class="headerText2"><?php echo __('Subject List'); ?></a> &nbsp; <a href="<?php echo MODULES_WEB_ROOT_DIR; ?>master_file/topic.php?action=detail" class="headerText2"><?php echo __('Add New Subject Type'); ?></a>
    &nbsp; <a href="<?php echo MODULES_WEB_ROOT_DIR; ?>master_file/topic.php" class="headerText2"><?php echo __('Subject Type List'); ?></a>-->
    <p class="only_border">&nbsp;</p>
    <form name="search" action="<?php echo MODULES_WEB_ROOT_DIR; ?>master_file/topic.php" id="search" method="get" style="display: inline;"><?php echo gettext('Search'); ?> :
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
 
    if (!($can_read AND $can_write)) {
        die('<div class="errorBox">'.gettext('You don\'t have enough privileges to access this area!').'</div>');
    }
    /* RECORD FORM */
    $itemID = (integer)isset($_POST['itemID'])?$_POST['itemID']:0;
    $rec_q = $dbs->query('SELECT * FROM mst_topic WHERE topic_id='.$itemID);
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
        $form->record_title = $rec_d['topic'];
        // submit button attribute
        $form->submit_button_attr = 'name="saveData" value="'.gettext('Update').'" class="button"';
    }

    /* Form Element(s) */
    // subject
     //comment by iresh on 25-1-2011$form->addTextField('text', 'topic', __('Subject').'*', $rec_d['topic'], 'style="width: 60%;"');
  /*added by iresh on 25-1-2011 */$form->addTextField('text', 'topic', gettext('Subject').'*', $rec_d['topic'], 'style="width: 140;" onkeyup="return checkspecialcharacterdynamic(this.name);"onblur="return charactercheck(this.name);"');
    // subject type
    foreach ($sysconf['subject_type'] as $subj_type_id => $subj_type) {
        $subj_type_options[] = array($subj_type_id, $subj_type);
    }
  //  $form->addSelectList('subjectType', __('Subject Type'), $subj_type_options, $rec_d['topic_type']);
    // authority list
   //comment by iresh on 25-1-2011 $form->addTextField('text', 'authList', __('Authority Files'), $rec_d['auth_list'], 'style="width: 30%;"');
    //$form->addTextField('text', 'authList', __('Authority Files'), $rec_d['auth_list'], 'style="width: 140px;"');

    // edit mode messagge
    if ($form->edit_mode) {
        echo '<div class="infoBox">'.gettext('You are going to edit Subject data').' : <b>'.$rec_d['topic'].'</b>  <br />'.gettext('Last Update').$rec_d['last_update'].'</div>'; //mfc
    }
    // print out the form object
    echo $form->printOut();
} 
else
{

                $table_spec = 'mst_topic AS t';
                $subj_type_fld = 1;

                $datagrid = new simbio_datagrid();
                if ($can_read AND $can_write) 
                 {
                    
                    $subj_type_fld = 2;
                    $datagrid->setSQLColumn('t.topic_id','t.topic AS \''.gettext('Subject').'\'', 
                          // 't.topic_type AS \''.__('Subject Type').'\'', 
                          //      't.auth_list AS \''.__('Authority Files').'\'',
                        'DATE_FORMAT(t.last_update,"%d-%m-%Y") AS \''.gettext('Last Update').'\'');
                 } 
                 else 
                 {
                     
                    $datagrid->setSQLColumn('t.topic AS \''.gettext('Subject').'\'',
                       // 't.topic_type AS \''.__('Subject Type').'\'',
                        't.auth_list AS \''.gettext('Authority Files').'\'',
                        'DATE_FORMAT(t.last_update,"%d-%m-%Y") AS \''.gettext('Last Update').'\'');
                 }
                $datagrid->setSQLorder('topic ASC');

                // is there any search
                if (isset($_GET['keywords']) AND $_GET['keywords']) 
                {
                        $keyword = $dbs->escape_string(trim($_GET['keywords']));
                        $words = explode(' ', $keyword);
                        if (count($words) > 1) 
                        {
                            $concat_sql = ' (';
                            foreach ($words as $word) 
                            {
                                $concat_sql .= " t.topic LIKE '%$word%' AND";
                            }   
                            // remove the last AND
                                $concat_sql = substr_replace($concat_sql, '', -3);
                                $concat_sql .= ') ';
                                $datagrid->setSQLCriteria($concat_sql);
                        }
                        else
                        {
                                $datagrid->setSQLCriteria("t.topic LIKE '%$keyword%'");
                        }
                }

                // set table and table header attributes
                $datagrid->table_attr = 'align="center" id="dataList" cellpadding="5" cellspacing="0"';
                $datagrid->table_header_attr = 'class="dataListHeader" style="font-weight: bold;"';
                // set delete proccess URL
                $datagrid->chbox_form_URL = $_SERVER['PHP_SELF'];
            /*
                // callback function to change value of subject type
                function callbackSubjectType($obj_db, $rec_d)
                {
                    global $sysconf, $subj_type_fld;
                    return $sysconf['subject_type'][$rec_d[$subj_type_fld]];
                }
                // modify column content
                $datagrid->modifyColumnContent($subj_type_fld, 'callback{callbackSubjectType}');

            */
                
                // put the result into variables
                $datagrid_result = $datagrid->createDataGrid($dbs,$table_spec, 20,($can_read AND $can_write)  );                                
                //$datagrid_result = $datagrid->createDataGrid($dbs, $table_spec, 20, ($can_read AND $can_write));                                
                if (isset($_GET['keywords']) AND $_GET['keywords']) 
                {
                    
                    $msg = str_replace('{result->num_rows}', $datagrid->num_rows, gettext('Found <strong>{result->num_rows}</strong> from your keywords')); //mfc
                    echo '<div class="infoBox">'.$msg.' : "'.$_GET['keywords'].'"</div>';
                }

                echo $datagrid_result;
                
}

?>
