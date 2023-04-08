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

if (!$can_read)
{
    die('<div class="errorBox">'.__('You don\'t have enough privileges to access this area!').'</div>');
}

/* RECORD OPERATION */
if (isset($_POST['saveData']) AND $can_read AND $can_write) 
{
    $authorName = trim(strip_tags($_POST['authorName']));
    // check form validity
    
    if (empty($authorName)) 
    {
        utility::jsAlert(__('Author name can\'t be empty!'));
        exit();
        
    }
    else 
    {        
            if (number_format($authorName)) 
            {
                utility::jsAlert(__('Author name can\'t be Numeric!'));
                exit();     
            }                              
                $data['author_name'] = $dbs->escape_string($authorName);
                $data['authority_type'] = trim($dbs->escape_string(strip_tags($_POST['authorityType'])));
                $data['auth_list'] = trim($dbs->escape_string(strip_tags($_POST['authList'])));
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
            $update = $sql_op->update('mst_author', $data, 'author_id='.$updateRecordID);
            if ($update) 
            {
                utility::jsAlert(__('Author Data Successfully Updated'));
                echo '<script type="text/javascript">parent.setContent(\'mainContent\', parent.getPreviousAJAXurl(), \'post\');</script>';
            } 
            else
            { 
                utility::jsAlert(__('Author Data FAILED to Updated. Please Contact System Administrator')."\nDEBUG : ".$sql_op->error);             
            }
            exit();
        }
        else
        {
            //$title = trim(strip_tags($_POST['title']));
            //$gmid=$_POST['gmdID'];                        
            //SELECT *  FROM `mst_author` WHERE `author_name` LIKE 'amit' AND `authority_type` = 'p'
            $sql = "select * from mst_author where author_name LIKE '$authorName' and authority_type='$data[authority_type]'";                               
            $data_available = $dbs->query($sql);
            if ($data_available->num_rows>0) 
            {
                utility::jsAlert(__('Same Author Name and Type is already Exists!'));
                exit();
            } 
            /* INSERT RECORD MODE */
            // insert the data
            $insert = $sql_op->insert('mst_author', $data);
            if ($insert)
            {
                utility::jsAlert(__('New Author Data Successfully Saved'));
                echo '<script type="text/javascript">parent.setContent(\'mainContent\', \''.$_SERVER['PHP_SELF'].'\', \'post\');</script>';
            }
            else
            {
                utility::jsAlert(__('Same Author and Type is Entered!')); 
              
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
	$rec_f = $dbs->query('SELECT author_id from biblio_author where author_id ='.$itemID);
		while($row = $rec_f->fetch_assoc())
		{
			$checkflag=1;	
		}
	if($checkflag==1)
	{
		$rec_name = $dbs->query('SELECT author_name from mst_author where author_id ='.$itemID);
		while($rownew = $rec_name->fetch_assoc())
		{
			$author_name_set = $rownew['author_name'];	
		}
                $error_num++;
		 utility::jsAlert(__('You can not Delete Author :'.$author_name_set.'; because it is associate with the book.'));
	}
	else
	{
        	if (!$sql_op->delete('mst_author', 'author_id='.$itemID)) {
            		$error_num++;
        		}
	}
    }
//ended addition by Parth 8/7/2011
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
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'master_file/author.php class="headerText2">Author</a>';
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
<a href="<?php echo MODULES_WEB_ROOT_DIR; ?>master_file/author.php?action=detail" class="headerText2"><?php echo __('Add New Author'); ?></a></li>
<li> 
<a href="<?php echo MODULES_WEB_ROOT_DIR; ?>master_file/author.php" class="headerText2"><?php echo __('Author List'); ?></a> </li>
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
   <!-- <?php echo strtoupper(__('Author')); ?> - <a href="<?php echo MODULES_WEB_ROOT_DIR; ?>master_file/author.php?action=detail" class="headerText2"><?php echo __('Add New Author'); ?></a>
    &nbsp; <a href="<?php echo MODULES_WEB_ROOT_DIR; ?>master_file/author.php" class="headerText2"><?php echo __('Author List'); ?></a>-->
    <p class="only_border">&nbsp;</p>
    <form name="search" action="<?php echo MODULES_WEB_ROOT_DIR; ?>master_file/author.php" id="search" method="get" style="display: inline;"><?php echo __('Search'); ?> :
     <!--commnet by iresh on 25-1-2011  <input type="text" name="keywords" id="keywords" size="30" />-->
   <!-- added by iresh on 25-1-2011 --> <input type="text" name="keywords" id="keywords" width=140px/>
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
$visibility = 'makeVisible';
    /* RECORD FORM */
    $itemID = (integer)isset($_POST['itemID'])?$_POST['itemID']:0;
    $rec_q = $dbs->query('SELECT * FROM mst_author WHERE author_id='.$itemID);
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
        // record ID for delete process
        $form->record_id = $itemID;
        // form record title
        $form->record_title = $rec_d['author_name'];
        // submit button attribute
        $form->submit_button_attr = 'name="saveData" value="'.__('Update').'" class="button"';
    }

    /* Form Element(s) */
    // author name
    //commnet by iresh on 25-1-2011 $form->addTextField('text', 'authorName', __('Author Name').'*', $rec_d['author_name'], 'style="width: 60%;"');
    
    $form->addTextField('text', 'authorName', __('Author Name').'*', $rec_d['author_name'], 'style="width: 140px;" onkeyup="return checkspecialcharacterdynamic(this.name);"onblur="charactercheck(this.name);"');
    //return charactercheck(this.name);
    //$form->addTextField('text', 'authorName', __('Author Name').'*', $rec_d['author_name'], 'style="width: 140px;" onkeyup="return charactercheck(this.name);"');
    
    // authority type
    foreach ($sysconf['authority_type'] as $auth_type_id => $auth_type) {
//echo $auth_type;
        $auth_type_options[] = array($auth_type_id, $auth_type);
    }
    $form->addSelectList('authorityType', __('Authority Type'), $auth_type_options, $rec_d['authority_type']);
    // authority list
   //commnet by iresh on 25-1-2011  $form->addTextField('text', 'authList', __('Authority Files'), $rec_d['auth_list'], 'style="width: 30%;"');
   /* added by iresh on 25-1-2011*/ 
   // $form->addTextField('text', 'authList', __('Authority Files'), $rec_d['auth_list'], 'style="width: 140px;" onkeyup="return checkspecialcharacterdynamic(this.name);"');
  /* $str_input = '<div class="'.$visibility.'"><a class="notAJAX" href="javascript: openHTMLpop(\''.MODULES_WEB_ROOT_DIR.'bibliography/pop_attach.php?biblioID='.$rec_d['biblio_id'].'\', 600, 300, \''.__('File Attachments').'\')">'.__('Add Attachment').'</a></div>';
    $str_input .= '<iframe name="attachIframe" id="attachIframe" class="borderAll" style="width: 100%; height: 70px;" src="'.MODULES_WEB_ROOT_DIR.'bibliography/iframe_attach.php?biblioID='.$rec_d['biblio_id'].'&block=1"></iframe>';
    $form->addAnything(__('File Attachment'), $str_input);*/

    // edit mode messagge
    if ($form->edit_mode) {
        echo '<div class="infoBox">'.__('You are going to edit data').' : <b>'.$rec_d['author_name'].'</b> <br />'.__('Last Update').$rec_d['last_update'].'</div>'; //mfc
    }
    // print out the form object
    echo $form->printOut();
} else {
    /* AUTHOR LIST */
    // table spec
    $table_spec = 'mst_author AS a';

    // authority field num
    $auth_type_fld = 1;
    // create datagrid
    $datagrid = new simbio_datagrid();
    if ($can_read AND $can_write) {
        $auth_type_fld = 2;
        $datagrid->setSQLColumn('a.author_id', 'a.author_name AS \''.__('Author Name').'\'',
            'a.authority_type AS \''.__('Authority Type').'\'',
      //      'a.auth_list AS \''.__('Authority Files').'\'',
            'DATE_FORMAT(a.last_update,"%d-%m-%Y") AS \''.__('Last Update').'\'');
    } else {
        $datagrid->setSQLColumn('a.author_name AS \''.__('Author Name').'\'',
            'a.authority_type AS \''.__('Authority Type').'\'',
           // 'a.auth_list AS \''.__('Authority Files').'\'',
            'DATE_FORMAT(a.last_update,"%d-%m-%Y") AS \''.__('Last Update').'\'');
    }
    $datagrid->setSQLorder('author_name ASC');

    // change the record order
    if (isset($_GET['fld']) AND isset($_GET['dir'])) {
        $datagrid->setSQLorder("'".urldecode($_GET['fld'])."' ".$dbs->escape_string($_GET['dir']));
    }

    // is there any search
    if (isset($_GET['keywords']) AND $_GET['keywords']) {
       $keywords = $dbs->escape_string($_GET['keywords']);
       $datagrid->setSQLCriteria("a.author_name LIKE '%$keywords%'");
    }

    // set table and table header attributes
    $datagrid->table_attr = 'align="center" id="dataList" cellpadding="5" cellspacing="0"';
    $datagrid->table_header_attr = 'class="dataListHeader" style="font-weight: bold;"';
    // set delete proccess URL
    $datagrid->chbox_form_URL = $_SERVER['PHP_SELF'];

    // callback function to change value of authority type
    function callbackAuthorType($obj_db, $rec_d)
    {
        global $sysconf, $auth_type_fld;
        return $sysconf['authority_type'][$rec_d[$auth_type_fld]];
    }
    // modify column content
    $datagrid->modifyColumnContent($auth_type_fld, 'callback{callbackAuthorType}');
    // put the result into variable
    $datagrid_result = $datagrid->createDataGrid($dbs, $table_spec, 20, ($can_read AND $can_write));
    if (isset($_GET['keywords']) AND $_GET['keywords']) {
        $msg = str_replace('{result->num_rows}', $datagrid->num_rows, __('Found <strong>{result->num_rows}</strong> from your keywords')); //mfc
        echo '<div class="infoBox">'.$msg.' : "'.$_GET['keywords'].'"</div>';
    }

    echo $datagrid_result;
}
/* main content end */
?>
