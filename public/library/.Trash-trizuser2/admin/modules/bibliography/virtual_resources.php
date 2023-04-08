<?php
error_reporting(0);
/**
 * Copyright (C) 2007,2008,2009,2010  Arie Nugraha (dicarve@yahoo.com)
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
/* Bibliography Management section */

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
require SIMBIO_BASE_DIR.'simbio_FILE/simbio_file_upload.inc.php';
// privileges checking
$can_read = utility::havePrivilege('bibliography', 'r');
$can_write = utility::havePrivilege('bibliography', 'w');

if (!$can_read) {
    die('<div class="errorBox">'.__('You are not authorized to view this section').'</div>');
}

$in_pop_up = false;
// check if we are inside pop-up window
if (isset($_GET['inPopUp'])) {
    $in_pop_up = true;
}

/* RECORD OPERATION */
if (isset($_POST['saveData']) AND $can_read AND $can_write) {

    $title = trim(strip_tags($_POST['title']));
    // check form validity
    if (empty($title)) {
        utility::jsAlert(__('Title can not be empty'));
        exit();
    } if($_POST['materialresourceid']=="N/A")
	{
	utility::jsAlert(__('Material Resource Type can not be empty'));
        exit();
	}if($_POST['gmdID']==0)
	{
	utility::jsAlert(__('Material Type can not be empty'));
        exit();
	}if($_POST['materialsubid']==0)
	{
	utility::jsAlert(__('Material Sub Type can not be empty'));
        exit();
	}else {
        // include custom fields file
        if (file_exists(MODULES_BASE_DIR.'bibliography/custom_fields.inc.php')) {
            include MODULES_BASE_DIR.'bibliography/custom_fields.inc.php';
        }

        /**
         * Custom fields
         */
        if (isset($biblio_custom_fields)) {
            if (is_array($biblio_custom_fields) && $biblio_custom_fields) {
                foreach ($biblio_custom_fields as $fid => $cfield) {
                    // custom field data
                    $cf_dbfield = $cfield['dbfield'];
                    if (isset($_POST[$cf_dbfield]) AND trim($_POST[$cf_dbfield]) != '') {
                        $custom_data[$cf_dbfield] = trim($dbs->escape_string(strip_tags($_POST[$cf_dbfield], $sysconf['content']['allowable_tags'])));
                    }
                }
            }
        }

        $data['title'] = $dbs->escape_string($title);
	$data['sub_title'] = trim($dbs->escape_string(strip_tags($_POST['subTitle'])));
	$data['publication'] = trim($dbs->escape_string(strip_tags($_POST['publication'])));
	$data['spec_detail_info'] = trim($dbs->escape_string(strip_tags($_POST['specDetailInfo'])));
        $data['tags'] = trim($dbs->escape_string(strip_tags($_POST['tags'])));
        $data['edition'] = trim($dbs->escape_string(strip_tags($_POST['edition'])));
        $data['gmd_id'] = $_POST['gmdID'];
	$data['material_resource_id'] = $_POST['materialresourceid'];
	$data['material_sub_id'] = $_POST['materialsubid'];
        $data['isbn_issn'] = trim($dbs->escape_string(strip_tags($_POST['isbn_issn'])));
        $data['classification'] = trim($dbs->escape_string(strip_tags($_POST['class'])));
	$data['review'] = trim($dbs->escape_string(strip_tags($_POST['review'])));
        // check publisher
        if ($_POST['publisherID'] != '0') {
            $data['publisher_id'] = intval($_POST['publisherID']);
        } else {
            if (!empty($_POST['publ_search_str'])) {
                $new_publisher = trim(strip_tags($_POST['publ_search_str']));
                $new_id = utility::getID($dbs, 'mst_publisher', 'publisher_id', 'publisher_name', $new_publisher);
                if ($new_id) {
                    $data['publisher_id'] = $new_id;
                } else {
                    $data['publisher_id'] = 'literal{NULL}';
                }
            } else {
                $data['publisher_id'] = 'literal{NULL}';
            }
        }
        $data['publish_year'] = trim($dbs->escape_string(strip_tags($_POST['year'])));
	$data['academic_year'] = trim($dbs->escape_string(strip_tags($_POST['academic_year'])));
        $data['collation'] = trim($dbs->escape_string(strip_tags($_POST['collation'])));
        $data['series_title'] = trim($dbs->escape_string(strip_tags($_POST['seriesTitle'])));
	$data['serial_no'] = trim($dbs->escape_string(strip_tags($_POST['serial_no'])));
        $data['call_number'] = trim($dbs->escape_string(strip_tags($_POST['callNumber'])));
	//if(count($_POST['languageID'])>1)
       // {
      //  $data['language_id'] = trim($dbs->escape_string(strip_tags($_POST['languageID'])));
      foreach ($_POST['languageID'] as $languageID) {
            if (trim($languageID) != '') {
              //  $arr_language[] = array($languageID, isset($_POST['languageID'][$languageID])?$_POST['languageID'][$languageID]:null );
 $arr_language.=$languageID.',';
            }
        }
$data['language_id'] =$arr_language;
	//}
	//else
	//{
		// $data['language_id'] = trim($dbs->escape_string(strip_tags($_POST['languageID'])));
	//}
        // check place
        if ($_POST['placeID'] != '0') {
            $data['publish_place_id'] = intval($_POST['placeID']);
        } else {
            if (!empty($_POST['plc_search_str'])) {
                $new_place = trim(strip_tags($_POST['plc_search_str']));
                $new_id = utility::getID($dbs, 'mst_place', 'place_id', 'place_name', $new_place);
                if ($new_id) {
                    $data['publish_place_id'] = $new_id;
                } else {
                    $data['publish_place_id'] = 'literal{NULL}';
                }
            } else {
                $data['publish_place_id'] = 'literal{NULL}';
            }
        }
        $data['notes'] = trim($dbs->escape_string(strip_tags($_POST['notes'], '<br><p><div><span><i><em><strong><b><code>s')));
	$data['abstract'] = trim($dbs->escape_string(strip_tags($_POST['abstract'], '<br><p><div><span><i><em><strong><b><code>s')));
        $data['opac_hide'] = ($_POST['opacHide'] == '0')?'literal{0}':'1';
        $data['promoted'] = ($_POST['promote'] == '0')?'literal{0}':'1';
        // labels
        $arr_label = array();
        foreach ($_POST['labels'] as $label) {
            if (trim($label) != '') {
                $arr_label[] = array($label, isset($_POST['label_urls'][$label])?$_POST['label_urls'][$label]:null );
            }
        }
        $data['labels'] = $arr_label?serialize($arr_label):'literal{NULL}';
        $data['frequency_id'] = ($_POST['frequencyID'] == '0')?'literal{0}':(integer)$_POST['frequencyID'];
        $data['spec_detail_info'] = trim($dbs->escape_string(strip_tags($_POST['specDetailInfo'])));
         $data['price_currency'] = trim($dbs->escape_string(strip_tags($_POST['priceCurrency'])));
        if (!$data['price_currency']) { $data['price_currency'] = 'literal{NULL}'; }
        $data['price'] = preg_replace('@[.,\-a-z ]@i', '', strip_tags($_POST['price']));
        $data['country'] = trim($dbs->escape_string(strip_tags($_POST['country'])));
        $data['state'] = trim($dbs->escape_string(strip_tags($_POST['state'])));
        $data['city'] = trim($dbs->escape_string(strip_tags($_POST['city'])));
 $data['vol_no'] = trim($dbs->escape_string(strip_tags($_POST['vol_no'])));
$data['issue_no'] = trim($dbs->escape_string(strip_tags($_POST['issue_no'])));
$data['publication_date'] = $_POST['pubdate'];
$data['editorial'] = trim($dbs->escape_string(strip_tags($_POST['editorial'])));
$data['subject'] = trim($dbs->escape_string(strip_tags($_POST['subject'])));
$data['standard'] = trim($dbs->escape_string(strip_tags($_POST['standard'])));
$data['review'] = trim($dbs->escape_string(strip_tags($_POST['review'])));

$data['duration'] = trim($dbs->escape_string(strip_tags($_POST['duration'])));
$data['company'] = trim($dbs->escape_string(strip_tags($_POST['company'])));
$data['actors'] = trim($dbs->escape_string(strip_tags($_POST['actors'])));
$data['age_group'] = trim($dbs->escape_string(strip_tags($_POST['age_group'])));
$data['awards'] = trim($dbs->escape_string(strip_tags($_POST['awards'])));

$data['qualification'] = trim($dbs->escape_string(strip_tags($_POST['qualification'])));
$data['college_inst_dept'] = trim($dbs->escape_string(strip_tags($_POST['college_inst_dept'])));
$data['university'] = trim($dbs->escape_string(strip_tags($_POST['university'])));

$data['index_no'] = trim($dbs->escape_string(strip_tags($_POST['index_no'])));
$data['doc_type'] = trim($dbs->escape_string(strip_tags($_POST['doc_type'])));

        $data['input_date'] = date('Y-m-d H:i:s');
        $data['last_update'] = date('Y-m-d H:i:s');
        $data['input_date'] = date('Y-m-d H:i:s');
        $data['last_update'] = date('Y-m-d H:i:s');

        // image uploading
        if (!empty($_FILES['image']) AND $_FILES['image']['size']) {
            // create upload object
            $image_upload = new simbio_file_upload();
            $image_upload->setAllowableFormat($sysconf['allowed_images']);
            $image_upload->setMaxSize($sysconf['max_image_upload']*1024);
            $image_upload->setUploadDir(IMAGES_BASE_DIR.'docs');
            // upload the file and change all space characters to underscore
            $img_upload_status = $image_upload->doUpload('image', preg_replace('@\s+@i', '_', $_FILES['image']['name']));
            if ($img_upload_status == UPLOAD_SUCCESS) {
                $data['image'] = $dbs->escape_string($image_upload->new_filename);
                // write log
                utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'bibliography', $_SESSION['realname'].' upload image file '.$image_upload->new_filename);
                utility::jsAlert(__('Image Uploaded Successfully'));
            } else {
                // write log
                utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'bibliography', 'ERROR : '.$_SESSION['realname'].' FAILED TO upload image file '.$image_upload->new_filename.', with error ('.$image_upload->error.')');
                utility::jsAlert(__('Image Uploaded Successfully'));
            }
        }

        // create sql op object
        $sql_op = new simbio_dbop($dbs);
        if (isset($_POST['updateRecordID'])) {
	     /* UPDATE RECORD MODE */
            // remove input date
            unset($data['input_date']);
            // filter update record ID
            $updateRecordID = (integer)$_POST['updateRecordID'];
            // update data
            $update = $sql_op->update('biblio', $data, 'biblio_id='.$updateRecordID);
            // send an alert
            if ($update) {
                // update custom data
                if (isset($custom_data)) {
                    // check if custom data for this record exists
                    $_sql_check_custom_q = sprintf('SELECT biblio_id FROM biblio_custom WHERE biblio_id=%d', $updateRecordID);
                    $check_custom_q = $dbs->query($_sql_check_custom_q);
                    if ($check_custom_q->num_rows) {
                        $update2 = @$sql_op->update('biblio_custom', $custom_data, 'biblio_id='.$updateRecordID);
                    } else {
                        $custom_data['biblio_id'] = $updateRecordID;
                        @$sql_op->insert('biblio_custom', $custom_data);
                    }
                }
            	if ($sysconf['bibliography_update_notification']) {
                    utility::jsAlert(__('Bibliography Data Successfully Updated'));
		  //  require_once SENAYAN_BASE_DIR.'admin/modules/bibliography/biblio_notification_mail.php';		
			    }
                // auto insert catalog to UCS if enabled
                if ($sysconf['ucs']['enable']) {
                    echo '<script type="text/javascript">parent.ucsUpload(\''.MODULES_WEB_ROOT_DIR.'bibliography/ucs_upload.php\', \'itemID[]='.$updateRecordID.'\', false);</script>';
                }
                // write log
                utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'bibliography', $_SESSION['realname'].' update bibliographic data ('.$data['title'].') with biblio_id ('.$_POST['itemID'].')');
                // close window OR redirect main page
                if ($in_pop_up) {
                    $itemCollID = (integer)$_POST['itemCollID'];
                    echo '<script type="text/javascript">top.setContent(\'mainContent\', top.getLatestAJAXurl(), \'post\', \''.( $itemCollID?'itemID='.$itemCollID.'&detail=true':'' ).'\');</script>';
                    echo '<script type="text/javascript">top.closeHTMLpop();</script>';
                } else {
                    echo '<script type="text/javascript">top.setContent(\'mainContent\', parent.getPreviousAJAXurl(), \'get\');</script>';
                }
            } else { utility::jsAlert(__('Bibliography Data FAILED to Updated. Please Contact System Administrator')."\n".$sql_op->error); }
            exit();
        } else {
            /* INSERT RECORD MODE */
	
            // insert the data
            $insert = $sql_op->insert('biblio', $data);
            if ($insert) {
                // get auto id of this record
                $last_biblio_id = $sql_op->insert_id;
                // add authors
                if ($_SESSION['biblioAuthor']) {
                    foreach ($_SESSION['biblioAuthor'] as $author) {
                        $sql_op->insert('biblio_author', array('biblio_id' => $last_biblio_id, 'author_id' => $author[0], 'level' => $author[1]));
                    }
                }
                // add topics
                if ($_SESSION['biblioTopic']) {
                    foreach ($_SESSION['biblioTopic'] as $topic) {
				
                        $sql_op->insert('biblio_topic', array('biblio_id' => $last_biblio_id,'subject_type_id' => $topic[0],'topic_id'=>$topic[1]));
			 
                    }
                }
               // add standard
                if ($_SESSION['biblioStandard']) {
		   
                    foreach ($_SESSION['biblioStandard'] as $standard) {
				
                        $sql_op->insert('biblio_standard', array('biblio_id' => $last_biblio_id, 'standard_id' => $standard[0]));
                    }
                }
                // add attachment
                if ($_SESSION['biblioAttach']) {
                    foreach ($_SESSION['biblioAttach'] as $attachment) {
                        $sql_op->insert('biblio_attachment', array('biblio_id' => $last_biblio_id, 'file_id' => $attachment['file_id'], 'access_type' => $attachment['access_type']));
                    }
                }
                // insert custom data
                if ($custom_data) {
                    $custom_data['biblio_id'] = $last_biblio_id;
                    @$sql_op->insert('biblio_custom', $custom_data);
                }

                utility::jsAlert(__('New Bibliography Data Successfully Saved'));
		//require_once SENAYAN_BASE_DIR.'admin/modules/bibliography/biblio_notification_mail.php';	
                // write log
                utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'bibliography', $_SESSION['realname'].' insert bibliographic data ('.$data['title'].') with biblio_id ('.$last_biblio_id.')');
                // clear related sessions
                $_SESSION['biblioAuthor'] = array();
                $_SESSION['biblioTopic'] = array();
                $_SESSION['biblioAttach'] = array();
                $_SESSION['biblioStandard'] = array();
                // auto insert catalog to UCS if enabled
                if ($sysconf['ucs']['enable'] && $sysconf['ucs']['auto_insert']) {
                    echo '<script type="text/javascript">parent.ucsUpload(\''.MODULES_WEB_ROOT_DIR.'bibliography/ucs_upload.php\', \'itemID[]='.$last_biblio_id.'\');</script>';
                }
                echo '<script type="text/javascript">parent.setContent(\'mainContent\', \''.MODULES_WEB_ROOT_DIR.'bibliography/index.php\', \'post\', \'itemID='.$last_biblio_id.'&detail=true\');</script>';
            } else { utility::jsAlert(__('Bibliography Data FAILED to Save. Please Contact System Administrator')."\n".$sql_op->error); }
            exit();
        }
    }
    exit();
} else if (isset($_POST['itemID']) AND !empty($_POST['itemID']) AND isset($_POST['itemAction'])) {
    if (!($can_read AND $can_write)) {
        die();
    }
    /* DATA DELETION PROCESS */
    // create sql op object
    $sql_op = new simbio_dbop($dbs);
    $failed_array = array();
    $error_num = 0;
    $still_have_item = array();
    if (!is_array($_POST['itemID'])) {
        // make an array
        $_POST['itemID'] = array((integer)$_POST['itemID']);
    }
    // loop array
    $http_query = '';
    foreach ($_POST['itemID'] as $itemID) {
        $itemID = (integer)$itemID;
        // check if this biblio data still have an item
        $_sql_biblio_item_q = sprintf('SELECT b.title, COUNT(item_id) FROM biblio AS b
            LEFT JOIN item AS i ON b.biblio_id=i.biblio_id
            WHERE b.biblio_id=%d GROUP BY title', $itemID);
        $biblio_item_q = $dbs->query($_sql_biblio_item_q);
        $biblio_item_d = $biblio_item_q->fetch_row();
        if ($biblio_item_d[1] < 1) {
            if (!$sql_op->delete('biblio', "biblio_id=$itemID")) {
                $error_num++;
            } else {
                // write log
                utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'bibliography', $_SESSION['realname'].' DELETE bibliographic data ('.$biblio_item_d[0].') with biblio_id ('.$itemID.')');
                // delete related data
                $sql_op->delete('biblio_topic', "biblio_id=$itemID");
                $sql_op->delete('biblio_author', "biblio_id=$itemID");
                $sql_op->delete('biblio_attachment', "biblio_id=$itemID");
                // add to http query for UCS delete
                $http_query .= "itemID[]=$itemID&";
            }
        } else {
            $still_have_item[] = substr($biblio_item_d[0], 0, 45).'... still have '.$biblio_item_d[1].' copies';
            $error_num++;
        }
    }

    if ($still_have_item) {
        $titles = '';
        foreach ($still_have_item as $title) {
            $titles .= $title."\n";
        }
        utility::jsAlert(__('Below data can not be deleted:')."\n".$titles);
        echo '<script type="text/javascript">parent.setContent(\'mainContent\', \''.$_SERVER['PHP_SELF'].'?'.$_POST['lastQueryStr'].'\', \'post\');</script>';
        exit();
    }
    // auto delete data on UCS if enabled
    if ($http_query && $sysconf['ucs']['enable'] && $sysconf['ucs']['auto_delete']) {
        echo '<script type="text/javascript">parent.ucsUpdate(\''.MODULES_WEB_ROOT_DIR.'bibliography/ucs_update.php\', \'nodeOperation=delete&'.$http_query.'\');</script>';
    }
    // error alerting
    if ($error_num == 0) {
        utility::jsAlert(__('All Data Successfully Deleted'));
        echo '<script type="text/javascript">parent.setContent(\'mainContent\', \''.$_SERVER['PHP_SELF'].'?'.$_POST['lastQueryStr'].'\', \'post\');</script>';
    } else {
        utility::jsAlert(__('Some or All Data NOT deleted successfully!\nPlease contact system administrator'));
        echo '<script type="text/javascript">parent.setContent(\'mainContent\', \''.$_SERVER['PHP_SELF'].'?'.$_POST['lastQueryStr'].'\', \'post\');</script>';
    }
    exit();
}
/* RECORD OPERATION END */

if (!$in_pop_up) {
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
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'bibliography/index.php?action=detail&physical=physical class="headerText2">Add Hardcopy Book</a>';
}
else
{
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'bibliography/index.php class="headerText2">Book List</a>';
}*/
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'bibliography/virtual_resources.php?action=detail class="headerText2">Virtual Resources</a>';
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
<a href="<?php echo MODULES_WEB_ROOT_DIR; ?>bibliography/index.php" class="headerText2"><?php echo __('Library Resource List'); ?></a> </li><li>
<a href="<?php echo  MODULES_WEB_ROOT_DIR; ?>bibliography/index.php?action=detail&physical=physical" class="headerText2"><?php echo __('Physical Resources'); ?></a> </li><li>  
<a href="<?php echo MODULES_WEB_ROOT_DIR; ?>bibliography/virtual_resources.php?action=detail" class="headerText2"><?php echo __('Virtual Resources'); ?></a></li></ul>
</td></tr></table>
<fieldset class="menuBox">
<div class="menuBoxInner biblioIcon">
    <?php echo strtoupper(__('Virtual Resources List')); ?> - <a href="<?php echo MODULES_WEB_ROOT_DIR; ?>bibliography/virtual_resources.php?action=detail" class="headerText3"><?php echo __('Add New Virtual Resources'); ?></a>
    &nbsp; <a href="<?php echo MODULES_WEB_ROOT_DIR; ?>bibliography/virtual_resources.php?virtual" class="headerText3"><?php echo __('Virtual Resources List'); ?></a>
    <?php
    // enable UCS?
    if ($sysconf['ucs']['enable']) {
    ?>
    <div class="marginTop"><a href="#" onclick="ucsUpload('<?php echo MODULES_WEB_ROOT_DIR; ?>bibliography/ucs_upload.php', serializeChbox('dataList'))" class="notAJAX ucsUpload"><?php echo __('Upload Selected Bibliographic data to Union Catalog Server*'); ?></a></div>
    <?php
    }
    ?>
    <p class="only_border">&nbsp;</p>

    <form name="search" action="<?php echo MODULES_WEB_ROOT_DIR; ?>bibliography/virtual_resources.php" id="search" method="get" style="display: inline;"><?php echo __('Search'); ?> :
    <!-- comment by iresh on 25-1-2011 <input type="text" name="keywords" id="keywords" size="30" />-->
   <!-- added by iresh on 25-1-2011 --> <input type="text" name="keywords" id="keywords" width=140px/>
    <select name="field"><option value="0"><?php echo __('All Fields'); ?></option><option value="title"><?php echo __('Title/Series Title'); ?> </option><option value="subject"><?php echo __('Topics'); ?></option><option value="author"><?php echo __('Authors'); ?></select>
    <input type="submit" id="doSearch" value="<?php echo __('Search'); ?>" class="button" />
    </form>
</div>
</fieldset>
<?php
/* search form end */
}
/* main content */
if (isset($_POST['detail']) OR (isset($_GET['action']) AND $_GET['action'] == 'detail')) {

    if (!($can_read AND $can_write)) {
        die('<div class="errorBox">'.__('You are not authorized to view this section').'</div>');
    }
    /* RECORD FORM */
    // try query
    $itemID = (integer)isset($_POST['itemID'])?$_POST['itemID']:0;
	 $_sql_rec_q = sprintf('SELECT b.*, p.publisher_name, pl.place_name,mst.material_sub_name,g.gmd_name,mr.material_resource_name FROM biblio AS b
        LEFT JOIN mst_publisher AS p ON b.publisher_id=p.publisher_id
        LEFT JOIN mst_place AS pl ON b.publish_place_id=pl.place_id
	LEFT JOIN mst_material_sub_type AS mst ON mst.material_sub_id=b.material_sub_id
	LEFT JOIN mst_gmd AS g ON g.gmd_id=b.gmd_id
	LEFT JOIN mst_material_resource_type AS mr ON mr.material_resource_id=b.material_resource_id
        WHERE biblio_id=%d', $itemID);

    $rec_q = $dbs->query($_sql_rec_q);
    $rec_d = $rec_q->fetch_assoc();
    // create new instance
   $form = new simbio_form_table_AJAX('mainForms', $_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'], 'post');
    $form->submit_button_attr = 'name="saveData" value="'.__('Save').'" class="button"';
    // form table attributes
    $form->table_attr = 'align="center" id="dataList" border=0 cellpadding="5" cellspacing="0"';
   $form->table_header_attr = 'class="alterCell" style="font-weight: bold;"';
    $form->table_content_attr = 'class="alterCell2"';
    
    $visibility = 'makeVisible';
    // edit mode flag set
    if ($rec_q->num_rows > 0) {
        $form->edit_mode = true;
        // record ID for delete process
        if (!$in_pop_up) {
            // form record id
            $form->record_id = $itemID;
        } else {
            $form->addHidden('updateRecordID', $itemID);
            $form->addHidden('itemCollID', $_POST['itemCollID']);
            $form->back_button = false;
        }
        // form record title
        $form->record_title = $rec_d['title'];
        // submit button attribute

        $form->submit_button_attr = 'name="saveData" value="'.__('Update').'" class="button"';
        // element visibility class toogle
        $visibility = 'makeHidden';
	
        // custom field data query
        $_sql_rec_cust_q = sprintf('SELECT * FROM biblio_custom WHERE biblio_id=%d', $itemID);
        $rec_cust_q = $dbs->query($_sql_rec_cust_q);
        $rec_cust_d = $rec_cust_q->fetch_assoc();
    }

    // include custom fields file
    if (file_exists(MODULES_BASE_DIR.'bibliography/custom_fields.inc.php')) {

        include MODULES_BASE_DIR.'bibliography/custom_fields.inc.php';
    }

    /* Form Element(s) */
    // biblio title
    $form->addTextField('textarea', 'title', __('Title').'*', $rec_d['title'], 'rows="1" style="width: 100%; overflow: auto;"');
 // biblio gmd
        // get gmd data related to this record from database
     
//commented start by Parth 28/7/2011
 /* $gmd_q = $dbs->query('SELECT gmd_id, gmd_name FROM mst_gmd where material_resource_id!="5"');
	//$gmd_options='';
       $gmd_options = array('N/A');
        while ($gmd_d = $gmd_q->fetch_row()) 
	//while ($gmd_d = $gmd_q->fetch_assoc())		
	{
            $gmd_options[] = array($gmd_d[0], $gmd_d[1]);
		//$gmd_options.=$gmd_d['gmd_id'].',';
        }*/
//commented ended by Parth 28/7/2011		
 $material_q = $dbs->query('SELECT material_resource_id, material_resource_name FROM mst_material_resource_type where active_inactive="1"  AND material_resource_name="Virtual Library"');
	//$gmd_options='';
       $material_options = array('N/A');
        while ($material_d = $material_q->fetch_row()) 
	//while ($gmd_d = $gmd_q->fetch_assoc())		
	{
            $material_options[] = array($material_d[0], $material_d[1]);
		//$gmd_options.=$gmd_d['gmd_id'].',';
        }

$ajax = "ajaxFillSelect('".SENAYAN_WEB_ROOT_DIR."admin/AJAX_material_sub_type_handler.php', 'mst_gmd', 'gmd_id:gmd_name:material_resource_id', 'gmdID', $('materialresourceid').getValue())";

       if ($rec_d['gmd_name']) {
            $mst_options[] = array($rec_d['gmd_id'],$rec_d['gmd_name']);
        }
	$mst_options[] = array('0', __('Material Type'));


 $ajax_exp = "ajaxFillSelect('".SENAYAN_WEB_ROOT_DIR."admin/AJAX_material_sub_type_handler.php', 'mst_material_sub_type', 'material_sub_id:material_sub_name:gmd_id', 'materialsubid', $('gmdID').getValue())";

       if ($rec_d['material_sub_name']) {
            $mst_material_sub_type_options[] = array($rec_d['material_sub_id'],$rec_d['material_sub_name']);
        }
	$mst_material_sub_type_options[] = array('0', __('Material Sub Type'));
        // string element
$ajax_exp1 = "ajaxFillSelectnew('".SENAYAN_WEB_ROOT_DIR."admin/getuser.php','materialnewid1', $('materialsubid').getValue())";       
$str_input='';
if(isset($_POST['virtual']))
{
$str_input .= simbio_form_element::selectList('materialresourceid',$material_options, $rec_d['material_resource_id'],'');
}
else
{       
$str_input .= simbio_form_element::selectList('materialresourceid',$material_options, $rec_d['material_resource_id'],'onchange="'.$ajax.'"');
}
if(isset($_POST['virtual']))
{
$str_input .= simbio_form_element::selectList('gmdID', $mst_options, $rec_d['gmd_id'],'');
}
else
{
$str_input .= simbio_form_element::selectList('gmdID', $mst_options, $rec_d['gmd_id'],'onchange="'.$ajax_exp.'"');
}
//$str_input .= simbio_form_element::selectList('gmdID', $gmd_options, $rec_d['gmd_id'],'onchange="ccc(this.value);'.$ajax_exp.'"');
$str_input .= '&nbsp;';
if(isset($_POST['virtual']))
{
$str_input .= simbio_form_element::selectList('materialsubid', $mst_material_sub_type_options, $rec_d['material_sub_id'], '');

}
else
{
$str_input .= simbio_form_element::selectList('materialsubid', $mst_material_sub_type_options, $rec_d['material_sub_id'], 'onchange="'.$ajax_exp1.'"');
}
/*$str_input .= simbio_form_element::selectList('materialnewid', $material_options, $rec_d['material_resource_id'],'onchange="'.$ajax_exp1.'"');*/

$str_input .= '<tr><td id="materialnewid1" class="alterCell2" colspan="3"></td></tr>';
 // $str_input .= '<tr><td id="txtsubmaterial" class="alterCell2" colspan="3"></td></tr>';  
       // $str_input .= '&nbsp;';
       //$str_input .= simbio_form_element::textField('text', 'plc_search_str', $rec_d['place_name'], 'style="width: 45%;" onkeyup="'.$ajax_exp.'"');
 $form->addAnything(__('Material Sub Type'), $str_input);

if(isset($_POST['virtual']))
{
//biblio publication
if($rec_d['material_sub_id']==118)
{ 
 $form->addTextField('text', 'publication', __('Publication'), $rec_d['publication'] , 'rows="1" style="width: 100%;"');
}
//biblio sub title
if($rec_d['material_sub_id']==78 || $rec_d['material_sub_id']==112 || $rec_d['material_sub_id']==113 || $rec_d['material_sub_id']==116 || $rec_d['material_sub_id']==117 || $rec_d['material_sub_id']==88 || $rec_d['material_sub_id']==84 || $rec_d['material_sub_id']==85 || $rec_d['material_sub_id']==87  || $rec_d['material_sub_id']==115)
{
$form->addTextField('text', 'subTitle', __('Sub Title'), $rec_d['sub_title'], 'rows="1" style="width: 100%;"');
}
if($rec_d['material_sub_id']==78 || $rec_d['material_sub_id']==112 || $rec_d['material_sub_id']==113 || $rec_d['material_sub_id']==114  || $rec_d['material_sub_id']==117 || $rec_d['material_sub_id']==77 || $rec_d['material_sub_id']==79 || $rec_d['material_sub_id']==80 || $rec_d['material_sub_id']==81 || $rec_d['material_sub_id']==82 || $rec_d['material_sub_id']==83 || $rec_d['material_sub_id']==88 || $rec_d['material_sub_id']==115)
{
   // biblio series title
    $form->addTextField('textarea', 'seriesTitle', __('Series Title'), $rec_d['series_title'], 'rows="1" style="width: 100%;"');
}
if($rec_d['material_sub_id']==78 || $rec_d['material_sub_id']==112 || $rec_d['material_sub_id']==113 || $rec_d['material_sub_id']==88)
{
  // biblio edition
    $form->addTextField('text', 'edition', __('Edition'), $rec_d['edition'], 'style="width: 40%;"');
}
if($rec_d['material_sub_id']==78 || $rec_d['material_sub_id']==112 || $rec_d['material_sub_id']==113 || $rec_d['material_sub_id']==114 || $rec_d['material_sub_id']==115 || $rec_d['material_sub_id']==116 || $rec_d['material_sub_id']==117 || $rec_d['material_sub_id']==118 || $rec_d['material_sub_id']==77 || $rec_d['material_sub_id']==79 || $rec_d['material_sub_id']==80 || $rec_d['material_sub_id']==81 || $rec_d['material_sub_id']==82 || $rec_d['material_sub_id']==83 || $rec_d['material_sub_id']==84 ||$rec_d['material_sub_id']==85 || $rec_d['material_sub_id']==86 || $rec_d['material_sub_id']==87 || $rec_d['material_sub_id']==89 || $rec_d['material_sub_id']==90 || $rec_d['material_sub_id']==91 || $rec_d['material_sub_id']==92 || $rec_d['material_sub_id']==93 || $rec_d['material_sub_id']==94 || $rec_d['material_sub_id']==95 || $rec_d['material_sub_id']==96 || $rec_d['material_sub_id']==88 || $rec_d['material_sub_id']==97 || $rec_d['material_sub_id']==98 || $rec_d['material_sub_id']==99 || $rec_d['material_sub_id']==100 || $rec_d['material_sub_id']==101 || $rec_d['material_sub_id']==102 || $rec_d['material_sub_id']==104 || $rec_d['material_sub_id']==105 || $rec_d['material_sub_id']==106 || $rec_d['material_sub_id']==68 || $rec_d['material_sub_id']==69 || $rec_d['material_sub_id']==70 || $rec_d['material_sub_id']==71 || $rec_d['material_sub_id']==72 || $rec_d['material_sub_id']==73 || $rec_d['material_sub_id']==74 || $rec_d['material_sub_id']==75 || $rec_d['material_sub_id']==76)
{
       // biblio keywords
    $form->addTextField('text', 'tags', __('Keywords'), $rec_d['tags'], 'style="width: 40%;"');
}
if($rec_d['material_sub_id']==78 || $rec_d['material_sub_id']==112 || $rec_d['material_sub_id']==113 || $rec_d['material_sub_id']==88)
{
    // biblio specific detail info/area
    $form->addTextField('textarea', 'specDetailInfo', __('Specific Detail Info'), $rec_d['spec_detail_info'], 'rows="2" style="width: 100%"');
}
if($rec_d['material_sub_id']==78 || $rec_d['material_sub_id']==112 || $rec_d['material_sub_id']==113 || $rec_d['material_sub_id']==116 || $rec_d['material_sub_id']==117 || $rec_d['material_sub_id']==77 || $rec_d['material_sub_id']==79 || $rec_d['material_sub_id']==80 || $rec_d['material_sub_id']==81 || $rec_d['material_sub_id']==82 || $rec_d['material_sub_id']==83 || $rec_d['material_sub_id']==88 || $rec_d['material_sub_id']==84 || $rec_d['material_sub_id']==85 || $rec_d['material_sub_id']==87 || $rec_d['material_sub_id']==115)
{
    // biblio authors
        $str_input = '<div class="'.$visibility.'"><a class="notAJAX" href="javascript: openHTMLpop(\''.MODULES_WEB_ROOT_DIR.'bibliography/pop_author.php?biblioID='.$rec_d['biblio_id'].'\', 500, 200, \''.__('Authors/Roles').'\')">'.__('Add Author(s)').'</a></div>';
        $str_input .= '<iframe name="authorIframe" id="authorIframe" class="borderAll" style="width: 100%; height: 70px;" src="'.MODULES_WEB_ROOT_DIR.'bibliography/iframe_author.php?biblioID='.$rec_d['biblio_id'].'&block=1"></iframe>';
    $form->addAnything(__('Author(s)'), $str_input);
}
/*
// biblio editors
        $str_input = '<div class="'.$visibility.'"><a class="notAJAX" href="javascript: openHTMLpop(\''.MODULES_WEB_ROOT_DIR.'bibliography/pop_editor.php?biblioID='.$rec_d['biblio_id'].'\', 500, 200, \''.__('Editor').'\')">'.__('Add Editor(s)').'</a></div>';
        $str_input .= '<iframe name="authorIframe" id="authorIframe" class="borderAll" style="width: 100%; height: 70px;" src="'.MODULES_WEB_ROOT_DIR.'bibliography/iframe_editor.php?biblioID='.$rec_d['biblio_id'].'&block=1"></iframe>';
    $form->addAnything(__('Editor(s)'), $str_input);
*/
if($rec_d['material_sub_id']==78 || $rec_d['material_sub_id']==112 || $rec_d['material_sub_id']==113 || $rec_d['material_sub_id']==114 || $rec_d['material_sub_id']==118 || $rec_d['material_sub_id']==77 || $rec_d['material_sub_id']==79 || $rec_d['material_sub_id']==80 || $rec_d['material_sub_id']==81 || $rec_d['material_sub_id']==82 || $rec_d['material_sub_id']==83 || $rec_d['material_sub_id']==88)
{
// biblio publish frequencies
        // get frequency data related to this record from database
        $freq_q = $dbs->query('SELECT frequency_id, frequency FROM mst_frequency');
        $freq_options[] = array('0', strtoupper(__('Not Applicable')));
        while ($freq_d = $freq_q->fetch_row()) {
            $freq_options[] = array($freq_d[0], $freq_d[1]);
        }
        $str_input = simbio_form_element::selectList('frequencyID', $freq_options, $rec_d['frequency_id']);
        $str_input .= '&nbsp;';
        $str_input .= ' '.__('Use this for Serial publication');
   	  $form->addAnything(__('Frequency'), $str_input);
}
if($rec_d['material_sub_id']==78 || $rec_d['material_sub_id']==112 || $rec_d['material_sub_id']==113 || $rec_d['material_sub_id']==114 || $rec_d['material_sub_id']==117 || $rec_d['material_sub_id']==77 || $rec_d['material_sub_id']==79 || $rec_d['material_sub_id']==80 || $rec_d['material_sub_id']==81 || $rec_d['material_sub_id']==82 || $rec_d['material_sub_id']==83 || $rec_d['material_sub_id']==88 || $rec_d['material_sub_id']==84 || $rec_d['material_sub_id']==85 || $rec_d['material_sub_id']==87)
{
    // biblio ISBN/ISSN
    $form->addTextField('text', 'isbn_issn', __('ISBN/ISSN'), $rec_d['isbn_issn'], 'style="width: 40%;" onchange="return checkspecialcharacterdynamic(this.name);"');
}
//biblio company
if($rec_d['material_sub_id']==115)
{
    $form->addTextField('text', 'company', __('Company'), $rec_d['company'] , 'style="width: 40%;"');
}
//biblio Key Actors
if($rec_d['material_sub_id']==115)
{
    $form->addTextField('text', 'actors', __('Key Actors'), $rec_d['actors'] , 'style="width: 40%;"');
}
//biblio country
if($rec_d['material_sub_id']==115 || $rec_d['material_sub_id']==118)
{
    $form->addTextField('text', 'country', __('Country'), $rec_d['country'] , 'style="width: 40%;"');
}
//biblio state
if($rec_d['material_sub_id']==118)
{
    $form->addTextField('text', 'state', __('State'), $rec_d['state'] , 'style="width: 40%;"');
}
//biblio city
if($rec_d['material_sub_id']==118)
{
    $form->addTextField('text', 'city', __('City'), $rec_d['city'] , 'style="width: 40%;"');
}
//biblio Age Groups
if($rec_d['material_sub_id']==115)
{
    $form->addTextField('text', 'age_group', __('Age Groups'), $rec_d['age_group'] , 'style="width: 40%;"');
}
//biblio Awards
if($rec_d['material_sub_id']==115)
{
    $form->addTextField('text', 'awards', __('Awards'), $rec_d['awards'] , 'style="width: 40%;"');
}
//biblio editorial
if($rec_d['material_sub_id']==114 || $rec_d['material_sub_id']==88)
{
 $form->addTextField('text', 'editorial', __('Editorial'), $rec_d['editorial'], 'style="width: 40%;"');
}
if($rec_d['material_sub_id']==78 || $rec_d['material_sub_id']==112 || $rec_d['material_sub_id']==113  || $rec_d['material_sub_id']==116 || $rec_d['material_sub_id']==88  || $rec_d['material_sub_id']==84 || $rec_d['material_sub_id']==85 || $rec_d['material_sub_id']==87 || $rec_d['material_sub_id']==115)
{    
  // biblio classification
   $form->addTextField('text', 'class', __('Classification No.'), $rec_d['classification'], 'style="width: 40%;"');
}
if($rec_d['material_sub_id']==78 || $rec_d['material_sub_id']==112 || $rec_d['material_sub_id']==113 || $rec_d['material_sub_id']==114 || $rec_d['material_sub_id']==116 || $rec_d['material_sub_id']==118 || $rec_d['material_sub_id']==77 || $rec_d['material_sub_id']==79 || $rec_d['material_sub_id']==80 || $rec_d['material_sub_id']==81 || $rec_d['material_sub_id']==82 || $rec_d['material_sub_id']==83 || $rec_d['material_sub_id']==88 || $rec_d['material_sub_id']==84 || $rec_d['material_sub_id']==85 || $rec_d['material_sub_id']==87)
{
    // biblio publisher
        // AJAX expression
        $ajax_exp = "ajaxFillSelect('".SENAYAN_WEB_ROOT_DIR."admin/AJAX_lookup_handler.php', 'mst_publisher', 'publisher_id:publisher_name', 'publisherID', $('publ_search_str').getValue())";
        if ($rec_d['publisher_name']) {
            $publ_options[] = array($rec_d['publisher_id'], $rec_d['publisher_name']);
        }
        $publ_options[] = array('0', __('Publisher'));
        // string element
        $str_input = simbio_form_element::selectList('publisherID', $publ_options, '', 'style="width: 50%;"');
        $str_input .= '&nbsp;';
        $str_input .= simbio_form_element::textField('text', 'publ_search_str', $rec_d['publisher_name'], 'style="width: 45%;" onkeyup="'.$ajax_exp.'"');
    $form->addAnything(__('Publisher'), $str_input);
}
if($rec_d['material_sub_id']==78 || $rec_d['material_sub_id']==112 || $rec_d['material_sub_id']==113 || $rec_d['material_sub_id']==116 || $rec_d['material_sub_id']==88 || $rec_d['material_sub_id']==84 || $rec_d['material_sub_id']==85 || $rec_d['material_sub_id']==87)
{
    // biblio publish year
    $form->addTextField('text', 'year', __('Publishing Year'), $rec_d['publish_year'], 'style="width: 40%;"');
}
if($rec_d['material_sub_id']==78 || $rec_d['material_sub_id']==112 || $rec_d['material_sub_id']==113  || $rec_d['material_sub_id']==114 || $rec_d['material_sub_id']==116 || $rec_d['material_sub_id']==117 || $rec_d['material_sub_id']==77 || $rec_d['material_sub_id']==79 || $rec_d['material_sub_id']==80 || $rec_d['material_sub_id']==81 || $rec_d['material_sub_id']==82 || $rec_d['material_sub_id']==83 || $rec_d['material_sub_id']==88 || $rec_d['material_sub_id']==84 || $rec_d['material_sub_id']==85 || $rec_d['material_sub_id']==87)
{
    // biblio publish place
        // AJAX expression
        $ajax_exp = "ajaxFillSelect('".SENAYAN_WEB_ROOT_DIR."admin/AJAX_lookup_handler.php', 'mst_place', 'place_id:place_name', 'placeID', $('plc_search_str').getValue())";
        // string element
        if ($rec_d['place_name']) {
            $plc_options[] = array($rec_d['publish_place_id'], $rec_d['place_name']);
        }
        $plc_options[] = array('0', __('Publishing Place'));
        $str_input = simbio_form_element::selectList('placeID', $plc_options, '', 'style="width: 50%;"');
        $str_input .= '&nbsp;';
        $str_input .= simbio_form_element::textField('text', 'plc_search_str', $rec_d['place_name'], 'style="width: 45%;" onkeyup="'.$ajax_exp.'"');
    $form->addAnything(__('Publishing Place'), $str_input);
}
// biblio qualification/degree
if($rec_d['material_sub_id']==116 || $rec_d['material_sub_id']==88)
{
$form->addTextField('text', 'qualification', __('Qualification/Degree'), $rec_d['qualification'], 'style="width: 40%;"');
}
// biblio college/inst/dept
if($rec_d['material_sub_id']==116 || $rec_d['material_sub_id']==88)
{
$form->addTextField('text', 'college_inst_dept', __('College/Inst/Dept'), $rec_d['college_inst_dept'], 'style="width: 40%;"');
}
// biblio university
if($rec_d['material_sub_id']==116 || $rec_d['material_sub_id']==88)
{
$form->addTextField('text', 'university', __('University'), $rec_d['university'], 'style="width: 40%;"');
}
//biblio volume number
if($rec_d['material_sub_id']==114 || $rec_d['material_sub_id']==117 || $rec_d['material_sub_id']==88 || $rec_d['material_sub_id']==84 || $rec_d['material_sub_id']==85 || $rec_d['material_sub_id']==87)
{
$form->addTextField('text', 'vol_no', __('Volume No.'), $rec_d['vol_no'], 'style="width: 40%;"');
}
//biblio index number
if($rec_d['material_sub_id']==114 || $rec_d['material_sub_id']==117 || $rec_d['material_sub_id']==88 || $rec_d['material_sub_id']==84 || $rec_d['material_sub_id']==85 || $rec_d['material_sub_id']==87)
{
$form->addTextField('text', 'index_no', __('Index No.'), $rec_d['index_no'], 'style="width: 40%;"');
}
//biblio duration
if($rec_d['material_sub_id']==114 || $rec_d['material_sub_id']==117 || $rec_d['material_sub_id']==88 || $rec_d['material_sub_id']==115)
{
$form->addTextField('text', 'duration', __('Duration'), $rec_d['duration'], 'style="width: 40%;"');
}
//biblio issue number
if($rec_d['material_sub_id']==114 || $rec_d['material_sub_id']==88)
{
$form->addTextField('text', 'issue_no', __('Issue No.'), $rec_d['issue_no'], 'style="width: 40%;"');
}
//biblio serial no
if($rec_d['material_sub_id']==117 || $rec_d['material_sub_id']==88)
{
$form->addTextField('text', 'serial_no', __('Serial No.'), $rec_d['serial_no'], 'style="width: 40%;"');
}
//biblio publication date
if($rec_d['material_sub_id']==114  || $rec_d['material_sub_id']==117 || $rec_d['material_sub_id']==118 || $rec_d['material_sub_id']==77 || $rec_d['material_sub_id']==79 || $rec_d['material_sub_id']==80 || $rec_d['material_sub_id']==81 || $rec_d['material_sub_id']==82 || $rec_d['material_sub_id']==83 || $rec_d['material_sub_id']==88)
{
$form->addDateField('pubdate', __('Publication Date'), $rec_d['publication_date']?$rec_d['publication_date']:date('Y-m-d'));
}
if($rec_d['material_sub_id']==78 || $rec_d['material_sub_id']==112 || $rec_d['material_sub_id']==113 || $rec_d['material_sub_id']==88 || $rec_d['material_sub_id']==84 || $rec_d['material_sub_id']==85 || $rec_d['material_sub_id']==87)
{    
// biblio collation
    $form->addTextField('text', 'collation', __('Book Size/ Number of page'), $rec_d['collation'] , 'style="width: 40%;" onchange="return numericcheck(this.name);"');
}
if($rec_d['material_sub_id']==78 || $rec_d['material_sub_id']==112 || $rec_d['material_sub_id']==113 || $rec_d['material_sub_id']==88)
{   
    // biblio call_number
    $form->addTextField('text', 'callNumber', __('Call Number'), $rec_d['call_number'] , 'style="width: 40%;"');
}
if($rec_d['material_sub_id']==78 || $rec_d['material_sub_id']==112 || $rec_d['material_sub_id']==113 || $rec_d['material_sub_id']==114 || $rec_d['material_sub_id']==117 || $rec_d['material_sub_id']==118 || $rec_d['material_sub_id']==77 || $rec_d['material_sub_id']==79 || $rec_d['material_sub_id']==80 || $rec_d['material_sub_id']==81 || $rec_d['material_sub_id']==82 || $rec_d['material_sub_id']==83 || $rec_d['material_sub_id']==88)
{ 
    // biblio topics
        $str_input = '<div class="'.$visibility.'"><a class="notAJAX"  href="javascript: openHTMLpop(\''.MODULES_WEB_ROOT_DIR.'bibliography/pop_topic.php?biblioID='.$rec_d['biblio_id'].'\', 500, 200, \''.__('Subjects/Topics').'\')">'.__('Add Subject(s)').'</a></div>';
        $str_input .= '<iframe name="topicIframe" id="topicIframe" class="borderAll" style="width: 100%; height: 70px;" src="'.MODULES_WEB_ROOT_DIR.'bibliography/iframe_topic.php?biblioID='.$rec_d['biblio_id'].'block=1"></iframe>';
    $form->addAnything(__('Subject(s)'), $str_input);
}
if($rec_d['material_sub_id']==78 || $rec_d['material_sub_id']==112 || $rec_d['material_sub_id']==113 || $rec_d['material_sub_id']==117 || $rec_d['material_sub_id']==88)
{
 // biblio standard
        $str_input = '<div class="'.$visibility.'"><a class="notAJAX"  href="javascript: openHTMLpop(\''.MODULES_WEB_ROOT_DIR.'bibliography/pop_standard.php?biblioID='.$rec_d['biblio_id'].'\', 500, 200, \''.__('Standard').'\')">'.__('Add Standard(s)').'</a></div>';
        $str_input .= '<iframe name="standardIframe" id="standardIframe" class="borderAll" style="width: 100%; height: 70px;" src="'.MODULES_WEB_ROOT_DIR.'bibliography/iframe_standard.php?biblioID='.$rec_d['biblio_id'].'block=1"></iframe>';
    $form->addAnything(__('Standard(s)'), $str_input);
}

 //$form->addTextField('text', 'subject', __('Subject'), $rec_d['subject'], 'style="width: 40%;"');
//  $form->addTextField('text', 'standard', __('Standard'), $rec_d['standard'], 'style="width: 40%;"');
if($rec_d['material_sub_id']==78 || $rec_d['material_sub_id']==112 || $rec_d['material_sub_id']==113 || $rec_d['material_sub_id']==114 || $rec_d['material_sub_id']==116 || $rec_d['material_sub_id']==117 || $rec_d['material_sub_id']==118 || $rec_d['material_sub_id']==77 || $rec_d['material_sub_id']==79 || $rec_d['material_sub_id']==80 || $rec_d['material_sub_id']==81 || $rec_d['material_sub_id']==82 || $rec_d['material_sub_id']==83 || $rec_d['material_sub_id']==88)
{
    // biblio language
        // get language data related to this record from database
        $lang_q = $dbs->query("SELECT language_id, language_name FROM mst_language");
        $lang_options = array();
        while ($lang_d = $lang_q->fetch_row()) {
            $lang_options[] = array($lang_d[0], $lang_d[1]);
        }
     $form->addSelectList('languageID', __('Language'), $lang_options, $rec_d['language_id']);	
    //$form->addSelectList('languageID[]', __('Language'), $lang_options, '' ,'multiple="multiple" size=5');
     $str_input = simbio_form_element::textField('text', 'price', '0', 'style="width: 40%;"');
    $str_input .= simbio_form_element::selectList('priceCurrency', $sysconf['currencies'], '' );
    $form->addAnything(__('Price'), $str_input);
}
if($rec_d['material_sub_id']==78 || $rec_d['material_sub_id']==112 || $rec_d['material_sub_id']==113 || $rec_d['material_sub_id']==114 || $rec_d['material_sub_id']==117 || $rec_d['material_sub_id']==118 || $rec_d['material_sub_id']==88 || $rec_d['material_sub_id']==84 || $rec_d['material_sub_id']==85 || $rec_d['material_sub_id']==87 || $rec_d['material_sub_id']==115)
{
    // biblio note
    $form->addTextField('textarea', 'notes', __('Notes'), $rec_d['notes'] , 'style="width: 100%;" rows="2"');
}
if($rec_d['material_sub_id']==78 || $rec_d['material_sub_id']==112 || $rec_d['material_sub_id']==113 || $rec_d['material_sub_id']==114 || $rec_d['material_sub_id']==117 || $rec_d['material_sub_id']==118 || $rec_d['material_sub_id']==88 || $rec_d['material_sub_id']==84 || $rec_d['material_sub_id']==85 || $rec_d['material_sub_id']==87 || $rec_d['material_sub_id']==115)
{
    // biblio abstract
    $form->addTextField('textarea', 'abstract', __('Abstract'), $rec_d['abstract'] , 'style="width: 100%;" rows="2"');
}
if($rec_d['material_sub_id']==78 || $rec_d['material_sub_id']==112 || $rec_d['material_sub_id']==113 || $rec_d['material_sub_id']==118 || $rec_d['material_sub_id']==88)
{
 // biblio review
    $form->addTextField('text', 'review', __('Book Review'), $rec_d['review'] , 'style="width: 40%;" rows="2"');
}
if($rec_d['material_sub_id']==78 || $rec_d['material_sub_id']==112 || $rec_d['material_sub_id']==113 || $rec_d['material_sub_id']==88)
{
    // biblio cover image
    if (!trim($rec_d['image'])) {
        $str_input = simbio_form_element::textField('file', 'image');
        $str_input .= ' Maximum '.$sysconf['max_image_upload'].' KB';
        $form->addAnything(__('Image'), $str_input);
    } else {
        $str_input = '<a href="'.SENAYAN_WEB_ROOT_DIR.'images/docs/'.$rec_d['image'].'" target="_blank"><strong>'.$rec_d['image'].'</strong></a><br />';
        $str_input .= simbio_form_element::textField('file', 'image');
        $str_input .= ' Maximum '.$sysconf['max_image_upload'].' KB';
        $form->addAnything(__('Image'), $str_input);
    }
}
if($rec_d['material_sub_id']==78 || $rec_d['material_sub_id']==112 || $rec_d['material_sub_id']==113 || $rec_d['material_sub_id']==115 || $rec_d['material_sub_id']==116 || $rec_d['material_sub_id']==77 || $rec_d['material_sub_id']==79 || $rec_d['material_sub_id']==80 || $rec_d['material_sub_id']==81 || $rec_d['material_sub_id']==82 || $rec_d['material_sub_id']==83 || $rec_d['material_sub_id']==84 || $rec_d['material_sub_id']==85 || $rec_d['material_sub_id']==86 || $rec_d['material_sub_id']==87 || $rec_d['material_sub_id']==89 || $rec_d['material_sub_id']==90 || $rec_d['material_sub_id']==91 || $rec_d['material_sub_id']==92 || $rec_d['material_sub_id']==93 || $rec_d['material_sub_id']==94 || $rec_d['material_sub_id']==95 || $rec_d['material_sub_id']==96 || $rec_d['material_sub_id']==88 || $rec_d['material_sub_id']==97 || $rec_d['material_sub_id']==98 || $rec_d['material_sub_id']==99 || $rec_d['material_sub_id']==100 || $rec_d['material_sub_id']==101 || $rec_d['material_sub_id']==102 || $rec_d['material_sub_id']==104 || $rec_d['material_sub_id']==105 || $rec_d['material_sub_id']==106 || $rec_d['material_sub_id']==68 || $rec_d['material_sub_id']==69 || $rec_d['material_sub_id']==70 || $rec_d['material_sub_id']==71 || $rec_d['material_sub_id']==72 || $rec_d['material_sub_id']==73 || $rec_d['material_sub_id']==74 || $rec_d['material_sub_id']==75 || $rec_d['material_sub_id']==76)
{
    // biblio file attachment
    $str_input = '<div class="'.$visibility.'"><a class="notAJAX" href="javascript: openHTMLpop(\''.MODULES_WEB_ROOT_DIR.'bibliography/pop_attach.php?biblioID='.$rec_d['biblio_id'].'\', 600, 300, \''.__('File Attachments').'\')">'.__('Add Attachment').'</a></div>';
    $str_input .= '<iframe name="attachIframe" id="attachIframe" class="borderAll" style="width: 100%; height: 70px;" src="'.MODULES_WEB_ROOT_DIR.'bibliography/iframe_attach.php?biblioID='.$rec_d['biblio_id'].'&block=1"></iframe>';
    $form->addAnything(__('File Attachment'), $str_input);
}
if($rec_d['material_sub_id']==78 || $rec_d['material_sub_id']==112 || $rec_d['material_sub_id']==113 || $rec_d['material_sub_id']==114 || $rec_d['material_sub_id']==115 || $rec_d['material_sub_id']==116 || $rec_d['material_sub_id']==117 || $rec_d['material_sub_id']==118 || $rec_d['material_sub_id']==77 || $rec_d['material_sub_id']==79 || $rec_d['material_sub_id']==80 || $rec_d['material_sub_id']==81 || $rec_d['material_sub_id']==82 || $rec_d['material_sub_id']==83 || $rec_d['material_sub_id']==84 || $rec_d['material_sub_id']==85 || $rec_d['material_sub_id']==86 || $rec_d['material_sub_id']==87 || $rec_d['material_sub_id']==89 || $rec_d['material_sub_id']==90 || $rec_d['material_sub_id']==91 || $rec_d['material_sub_id']==92 || $rec_d['material_sub_id']==93 || $rec_d['material_sub_id']==94 || $rec_d['material_sub_id']==95 || $rec_d['material_sub_id']==96 || $rec_d['material_sub_id']==88 || $rec_d['material_sub_id']==97 || $rec_d['material_sub_id']==98 || $rec_d['material_sub_id']==99 || $rec_d['material_sub_id']==100 || $rec_d['material_sub_id']==101 || $rec_d['material_sub_id']==102 || $rec_d['material_sub_id']==104 || $rec_d['material_sub_id']==105 || $rec_d['material_sub_id']==106 || $rec_d['material_sub_id']==68 || $rec_d['material_sub_id']==69 || $rec_d['material_sub_id']==70 || $rec_d['material_sub_id']==71 || $rec_d['material_sub_id']==72 || $rec_d['material_sub_id']==73 || $rec_d['material_sub_id']==74 || $rec_d['material_sub_id']==75 || $rec_d['material_sub_id']==76)
{
// biblio labels
        $arr_labels = !empty($rec_d['labels'])?unserialize($rec_d['labels']):array();
        if ($arr_labels) {
            foreach ($arr_labels as $label) { $arr_labels[$label[0]] = $label[1]; }
        }
        $str_input = '';
        // get label data from database
        $label_q = $dbs->query("SELECT * FROM mst_label LIMIT 20");
        while ($label_d = $label_q->fetch_assoc()) {
            $checked = isset($arr_labels[$label_d['label_name']])?' checked':'';
            $url = isset($arr_labels[$label_d['label_name']])?$arr_labels[$label_d['label_name']]:'';
            $str_input .= '<div '
                .'style="background: url('.SENAYAN_WEB_ROOT_DIR.IMAGES_DIR.'/labels/'.$label_d['label_image'].') left center no-repeat; padding-left: 30px; height: 45px;"> '
                .'<input type="checkbox" name="labels[]" value="'.$label_d['label_name'].'"'.$checked.' /> '.$label_d['label_desc']
                .'<div>URL : <input type="text" title="Enter a website link/URL to make this label clickable" '
                .'name="label_urls['.$label_d['label_name'].']" size="50" maxlength="300" value="'.$url.'" /></div></div>';
        }
    $form->addAnything('Label', $str_input);
}




}
      /**
     * Custom fields
     */
    if (isset($biblio_custom_fields)) {
        if (is_array($biblio_custom_fields) && $biblio_custom_fields) {
            foreach ($biblio_custom_fields as $fid => $cfield) {

                // custom field properties
                $cf_dbfield = $cfield['dbfield'];
                $cf_label = $cfield['label'];
                $cf_default = $cfield['default'];
                $cf_data = (isset($cfield['data']) && $cfield['data'])?$cfield['data']:array();

                // custom field processing
                if (in_array($cfield['type'], array('text', 'longtext', 'numeric'))) {
                    $cf_max = isset($cfield['max'])?$cfield['max']:'200';
                    $cf_width = isset($cfield['width'])?$cfield['width']:'50';
                    $form->addTextField( ($cfield['type'] == 'longtext')?'textarea':'text', $cf_dbfield, $cf_label, isset($rec_cust_d[$cf_dbfield])?$rec_cust_d[$cf_dbfield]:$cf_default, 'style="width: '.$cf_width.'%;" maxlength="'.$cf_max.'"');
                } else if ($cfield['type'] == 'dropdown') {
                    $form->addSelectList($cf_dbfield, $cf_label, $cf_data, isset($rec_cust_d[$cf_dbfield])?$rec_cust_d[$cf_dbfield]:$cf_default);
                } else if ($cfield['type'] == 'checklist') {
                    $form->addCheckBox($cf_dbfield, $cf_label, $cf_data, isset($rec_cust_d[$cf_dbfield])?$rec_cust_d[$cf_dbfield]:$cf_default);
                } else if ($cfield['type'] == 'choice') {
                    $form->addRadio($cf_dbfield, $cf_label, $cf_data, isset($rec_cust_d[$cf_dbfield])?$rec_cust_d[$cf_dbfield]:$cf_default);
                } else if ($cfield['type'] == 'date') {
                    $form->addDateField($cf_dbfield, $cf_label, isset($rec_cust_d[$cf_dbfield])?$rec_cust_d[$cf_dbfield]:$cf_default);
                }
            }
        }
    }

    // biblio hide from opac
    $hide_options[] = array('0', __('Show'));
    $hide_options[] = array('1', __('Hide'));
    $form->addRadio('opacHide', __('Hide For Member Access'), $hide_options, $rec_d['opac_hide']?'1':'0');
    // biblio promote to front page
    $promote_options[] = array('0', __('Don\'t Promote'));
    $promote_options[] = array('1', __('Promote'));
    $form->addRadio('promote', __('Promote To Homepage'), $promote_options, $rec_d['promoted']?'1':'0');
  /*  // biblio labels
        $arr_labels = !empty($rec_d['labels'])?unserialize($rec_d['labels']):array();
        if ($arr_labels) {
            foreach ($arr_labels as $label) { $arr_labels[$label[0]] = $label[1]; }
        }
        $str_input = '';
        // get label data from database
        $label_q = $dbs->query("SELECT * FROM mst_label LIMIT 20");
        while ($label_d = $label_q->fetch_assoc()) {
            $checked = isset($arr_labels[$label_d['label_name']])?' checked':'';
            $url = isset($arr_labels[$label_d['label_name']])?$arr_labels[$label_d['label_name']]:'';
            $str_input .= '<div '
                .'style="background: url('.SENAYAN_WEB_ROOT_DIR.IMAGES_DIR.'/labels/'.$label_d['label_image'].') left center no-repeat; padding-left: 30px; height: 45px;"> '
                .'<input type="checkbox" name="labels[]" value="'.$label_d['label_name'].'"'.$checked.' /> '.$label_d['label_desc']
                .'<div>URL : <input type="text" title="Enter a website link/URL to make this label clickable" '
                .'name="label_urls['.$label_d['label_name'].']" size="50" maxlength="300" value="'.$url.'" /></div></div>';
        }
    $form->addAnything('Label', $str_input);
    // $form->addCheckBox('labels', 'Label', $label_options, explode(' ', $rec_d['labels'])); */

    // edit mode messagge
    if ($form->edit_mode) {
        echo '<div class="infoBox" style="overflow: auto;">'
            .'<div style="float: left; width: 80%;">'.__('You are going to edit biblio data').' : <b>'.$rec_d['title'].'</b>  <br />'.__('Last Updated').$rec_d['last_update'].'</div>'; //mfc
            if ($rec_d['image']) {
                if (file_exists(IMAGES_BASE_DIR.'docs/'.$rec_d['image'])) {
                    $upper_dir = '';
                    if ($in_pop_up) {
                        $upper_dir = '../../';
                    }
                    echo '<div style="float: right;"><img src="'.$upper_dir.'../lib/phpthumb/phpThumb.php?src=../../images/docs/'.urlencode($rec_d['image']).'&w=53" style="border: 1px solid #999999" /></div>';
                }
            }
        echo '</div>'."\n";
    }
    // print out the form object
    echo $form->printOut();


}



 else {

    require SIMBIO_BASE_DIR.'simbio_UTILS/simbio_tokenizecql.inc.php';
    require LIB_DIR.'biblio_list.inc.php';
    /* BIBLIOGRAPHY LIST */
    // callback function to show title and authors in datagrid
        function showTitleImage($obj_db, $array_data)
    {
    if(isset($_REQUEST['action1']))
	{
        $query = 'SELECT b.title, b.image, a.author_name, opac_hide, promoted FROM biblio AS b
            LEFT JOIN biblio_author AS ba ON b.biblio_id=ba.biblio_id
            LEFT JOIN mst_author AS a ON ba.author_id=a.author_id
            WHERE b.biblio_id='.$array_data[0]; 
        $_sql_biblio_q = $query;
	}
	else
	{
	$_sql_biblio_q = sprintf('SELECT b.title, b.image, a.author_name, opac_hide, promoted  FROM biblio AS b
            LEFT JOIN biblio_author AS ba ON b.biblio_id=ba.biblio_id
            LEFT JOIN mst_author AS a ON ba.author_id=a.author_id
            WHERE b.biblio_id=%d', $array_data[0]);	
	}
        //echo $_sql_biblio_q."<br/>";
        $_biblio_q = $obj_db->query($_sql_biblio_q);
        $_authors = '';
        while ($_biblio_d = $_biblio_q->fetch_row()) {
            $_title = $_biblio_d[0];
            //$_authors .= $_biblio_d[1].' - ';
            //$_opac_hide = (integer)$_biblio_d[2];
            //$_promoted = (integer)$_biblio_d[3];
            $_image = $_biblio_d[1];
         
        }
         $upper_dir = '';
                    if ($in_pop_up) {
                        $upper_dir = '../../';
                    }
        
       $_output = '<div style="float: left;"><img src="'.$upper_dir.'../lib/phpthumb/phpThumb.php?src=../../images/docs/'.urlencode($_image).'&w=53" style="border: 1px solid #999999" /></div>';
        return $_output;
    } 
    function showTitleAuthors($obj_db, $array_data)
    {
        // biblio author detail
        #$_biblio_q = $obj_db->query('SELECT b.title, a.author_name, opac_hide, promoted FROM biblio AS b
        #    LEFT JOIN biblio_author AS ba ON b.biblio_id=ba.biblio_id
        #    LEFT JOIN mst_author AS a ON ba.author_id=a.author_id
        #    WHERE b.biblio_id='.$array_data[0]);
       /* $_sql_biblio_q = sprintf('SELECT b.title, a.author_name, opac_hide, promoted FROM biblio AS b
            LEFT JOIN biblio_author AS ba ON b.biblio_id=ba.biblio_id
            LEFT JOIN mst_author AS a ON ba.author_id=a.author_id
            WHERE b.biblio_id=%d', $array_data[0]);*/
        $_sql_biblio_q = sprintf('SELECT b.title, b.publish_year, b.publisher_id, a.author_name, opac_hide, promoted FROM biblio AS b LEFT JOIN biblio_author AS ba ON b.biblio_id=ba.biblio_id
            LEFT JOIN mst_author AS a ON ba.author_id=a.author_id
            WHERE b.biblio_id=%d', $array_data[0]); 
        $_biblio_q = $obj_db->query($_sql_biblio_q);
        $_authors = '';
        while ($_biblio_d = $_biblio_q->fetch_row()) {
           /* $_title = $_biblio_d[0];
            $_authors .= $_biblio_d[1].' - ';
            $_opac_hide = (integer)$_biblio_d[2];
            $_promoted = (integer)$_biblio_d[3];*/
            $_title = $_biblio_d[0];
            $_publish_year =  $_biblio_d[1];
            $_publisher_id = $_biblio_d[2];
            $_authors .= $_biblio_d[3].' - ';
            $_opac_hide = (integer)$_biblio_d[4];
            $_promoted = (integer)$_biblio_d[5]; 
        }
        $_authors = substr_replace($_authors, '', -3);
        $_sql_biblio_q1 = 'SELECT COUNT(item_id) from item
            WHERE biblio_id='.$array_data[0];
	$_biblio_q1 = $obj_db->query($_sql_biblio_q1);
        while ($_biblio_d1 = $_biblio_q1->fetch_row()) {
            $_copies = $_biblio_d1[0]." copies";
        } 
        $_sql_biblio_q2 = "SELECT publisher_name from mst_publisher WHERE publisher_id='".$_publisher_id."'";
	$_biblio_q2 = $obj_db->query($_sql_biblio_q2);
        while ($_biblio_d2 = $_biblio_q2->fetch_row()) {
            $_publication = $_biblio_d2[0];
        }          
        $_output = '<div style="float: left;"><b>'.$_title.'</b><br/><br />By :- <i>'.$_authors.'</i><br/>Publication :- '.$_publication.'<br/>Publish Year :- '.$_publish_year.'<br/>Availability :- '.$_copies.'</div>';
       /* $_output = '<div style="float: left;"><b>'.$_title.'</b><br /><i>'.$_authors.'</i></div>';*/
        // check for opac hide flag
        if ($_opac_hide) {
            $_output .= '<div style="float: right; width: 20px; height: 20px;" class="lockFlagIcon" title="Hidden in OPAC">&nbsp;</div>';
        }
        // check for promoted flag
        if ($_promoted) {
            $_output .= '<div style="float: right; width: 20px; height: 20px;" class="homeFlagIcon" title="Promoted To Homepage">&nbsp;</div>';
        }
        return $_output;
    }

    // create datagrid
    $datagrid = new simbio_datagrid();
    if ($can_read AND $can_write) {
       /* $datagrid->setSQLColumn('biblio.biblio_id', 'biblio.biblio_id AS bid',
            'biblio.title AS \''.__('Title').'\'',
            //'biblio.isbn_issn AS \''.__('ISBN/ISSN').'\'',
            'IF(COUNT(item.item_id)>0, COUNT(item.item_id), \'<strong style="color: #FF0000;">'.__('None').'</strong>\') AS \''.__('Copies').'\'',
            'DATE_FORMAT(biblio.last_update,"%d-%m-%Y %H:%i:%s") AS \''.__('Last Update').'\'');*/
        $datagrid->setSQLColumn('biblio.biblio_id', 'biblio.biblio_id AS bid',
            'biblio.title AS \''.__('Title').'\'',
            //'biblio.isbn_issn AS \''.__('ISBN/ISSN').'\'',
            'biblio.image AS \''.__('').'\''); 
        $datagrid->modifyColumnContent(2, 'callback{showTitleAuthors}');
         $datagrid->modifyColumnContent(3, 'callback{showTitleImage}'); 
    } else {
       /* $datagrid->setSQLColumn('biblio.biblio_id AS bid', 'biblio.title AS \''.__('Title').'\'',
           // 'biblio.isbn_issn AS \''.__('ISBN/ISSN').'\'',
            'IF(COUNT(item.item_id)>0, COUNT(item.item_id), \'<strong style="color: #FF0000;">'.__('None').'</strong>\') AS \''.__('Copies').'\'',
            'biblio.last_update AS \''.__('Last Update').'\'');*/
           $datagrid->setSQLColumn('biblio.biblio_id AS bid', 'biblio.title AS \''.__('Title').'\'',
           // 'biblio.isbn_issn AS \''.__('ISBN/ISSN').'\'',
            'biblio.image AS \''.__('').'\'');
        // modify column value
        $datagrid->modifyColumnContent(1, 'callback{showTitleAuthors}');
         $datagrid->modifyColumnContent(3, 'callback{showTitleImage}'); 
    }
    $datagrid->invisible_fields = array(0);
    $datagrid->setSQLorder('biblio.last_update DESC');
    $datagrid->setSQLcriteria('WHERE g.material_resource_id="5" ');
    // is there any search
    if (isset($_GET['keywords']) AND $_GET['keywords']) {
        $keywords = $dbs->escape_string(trim($_GET['keywords']));
        $searchable_fields = array('title', 'author', 'subject', 'isbn', 'publisher');
        if ($_GET['field'] != '0' AND in_array($_GET['field'], $searchable_fields)) {
            $field = $_GET['field'];
            $search_str = $field.'='.$keywords;
        } else {
            $search_str = '';
            foreach ($searchable_fields as $search_field) {
                $search_str .= $search_field.'='.$keywords.' OR ';
            }
            $search_str = substr_replace($search_str, '', -4);
        }

        $biblio_list = new biblio_list($dbs);
        $criteria = $biblio_list->setSQLcriteria($search_str);
    }

    if (isset($criteria)) {
        $datagrid->setSQLcriteria('('.$criteria['sql_criteria'].')');
    }
    // table spec
    $table_spec = 'biblio LEFT JOIN item ON biblio.biblio_id=item.biblio_id LEFT JOIN mst_gmd AS g ON g.gmd_id=biblio.gmd_id';

    // set group by
    $datagrid->sql_group_by = 'biblio.biblio_id';

    // set table and table header attributes
    $datagrid->table_attr = 'align="center" id="dataList" cellpadding="5" cellspacing="0"';
    $datagrid->table_header_attr = 'class="dataListHeader" style="font-weight: bold;"';
    // set delete proccess URL
    $datagrid->chbox_form_URL = $_SERVER['PHP_SELF'];
    $datagrid->debug = true;

    $biblio_result_num = ($sysconf['biblio_result_num']>100)?100:$sysconf['biblio_result_num'];
    // put the result into variables
    $datagrid_result = $datagrid->createDataGrid($dbs, $table_spec, $biblio_result_num, ($can_read AND $can_write));
    if (isset($_GET['keywords']) AND $_GET['keywords']) {
        $msg = str_replace('{result->num_rows}', $datagrid->num_rows, __('Found <strong>{result->num_rows}</strong> from your keywords')); //mfc
        echo '<div class="infoBox">'.$msg.' : "'.$_GET['keywords'].'"<div>'.__('Query took').' <b>'.$datagrid->query_time.'</b> '.__('second(s) to complete').'</div></div>'; //mfc
    }

    echo $datagrid_result;
}
/* main content end */
?>
