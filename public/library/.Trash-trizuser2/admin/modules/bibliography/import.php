<?php
error_reporting(0);
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

/* Biblio Import section */

// main system configuration
require '../../../sysconfig.inc.php';
// start the session
require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/form_maker/simbio_form_table_AJAX.inc.php';
require SIMBIO_BASE_DIR.'simbio_FILE/simbio_file_upload.inc.php';

// privileges checking
$can_read = utility::havePrivilege('bibliography', 'r');
$can_write = utility::havePrivilege('bibliography', 'w');

if (!$can_read) {
    die('<div class="errorBox">'.__('You are not authorized to view this section').'</div>');
}
if ( !function_exists('sys_get_temp_dir') )
{
function sys_get_temp_dir() {
    if (!empty($_ENV['TMP'])) { return realpath($_ENV['TMP']); }
    if (!empty($_ENV['TMPDIR'])) { return realpath( $_ENV['TMPDIR']); }
    if (!empty($_ENV['TEMP'])) { return realpath( $_ENV['TEMP']); }
    $tempfile=tempnam(__FILE__,'');
    if (file_exists($tempfile)) {
      unlink($tempfile);
      return realpath(dirname($tempfile));
    }
    return null;
  }
}
// max chars in line for file operations
$max_chars = 1024*1000;

if (isset($_POST['doImport'])) {

    // check for form validity
    if (!$_FILES['importFile']['name']) {
        utility::jsAlert(__('Please select the file to import!'));
        exit();
    } else if (empty($_POST['fieldSep']) OR empty($_POST['fieldEnc'])) {
        utility::jsAlert(__('Required fields (*)  must be filled correctly!'));
        exit();
    } else if($_POST['materialresourceid']=="Material Resource Type"){
	utility::jsAlert(__('Please select the Material Resource Type!'));
        exit();
    }else if($_POST['gmdID']==0){
	utility::jsAlert(__('Please select the Material Type!'));
        exit();
    }else if($_POST['materialsubid']==0){
	utility::jsAlert(__('Please select the Material Sub Type!'));
        exit();
    }else {
        $start_time = time();
        // set PHP time limit
        set_time_limit(0);
        // set ob implicit flush
        ob_implicit_flush();
        // create upload object
        $upload = new simbio_file_upload();
        // get system temporary directory location
        $temp_dir = sys_get_temp_dir();
        $uploaded_file = $temp_dir.DIRECTORY_SEPARATOR.$_FILES['importFile']['name'];
        unlink($uploaded_file);
        // set max size
        $max_size = $sysconf['max_upload']*1024;
        $upload->setAllowableFormat(array('.csv'));
        $upload->setMaxSize($max_size);
        //$upload->setUploadDir($temp_dir);
         $upload->setUploadDir(REPO_BASE_DIR);
        $upload_status = $upload->doUpload('importFile');
        if ($upload_status != UPLOAD_SUCCESS) { 
            utility::jsAlert(__('Upload failed! File type not allowed or the size is more than').($sysconf['max_upload']/1024).' MB'); //mfc
            exit();
        }
        // uploaded file path

        //$uploaded_file = $temp_dir.DIRECTORY_SEPARATOR.$_FILES['importFile']['name'];
        //$uploaded_file = REPO_BASE_DIR.DIRECTORY_SEPARATOR.$_FILES['importFile']['name'];  
        $uploaded_file=REPO_BASE_DIR.DIRECTORY_SEPARATOR.$upload->new_filename; 
        $row_count = 0;
        // check for import setting
        $record_num = intval($_POST['recordNum']);
        $field_enc = trim($_POST['fieldEnc']);
        $field_sep = trim($_POST['fieldSep']);
        $record_offset = intval($_POST['recordOffset']);
        $record_offset = $record_offset-1;
        // get current datetime
        $curr_datetime = date('Y-m-d H:i:s');
        $curr_datetime = '\''.$curr_datetime.'\'';
        // foreign key id cache
        $gmd_id_cache = array();
        $publ_id_cache = array();
        $lang_id_cache = array();
        $place_id_cache = array();
        $author_id_cache = array();
        $subject_id_cache = array();
	$standard_id_cache = array();
	$editor_id_cache = array();
	$producer_id_cache = array();
	$director_id_cache = array();
	$guide_id_cache = array();
	$lang_id_2_cache = array();
        // read file line by line
        $inserted_row = 0;
        $file = fopen($uploaded_file, 'rb');
        while (!feof($file)) {
            // record count
            if ($record_num > 0 AND $row_count == $record_num) {
                break;
            }
            // go to offset
            if ($row_count < $record_offset) {
                // pass and continue to next loop
                $field = fgetcsv($file, 1024, $field_sep, $field_enc);
                $row_count++;
                continue;
            } else {
                // get an array of field
                $field = fgetcsv($file, $max_chars, $field_sep, $field_enc);
                if ($field) {
                    // strip escape chars from all fields
                    foreach ($field as $idx => $value) {
                        $field[$idx] = str_replace('\\', '', trim($value));
                        $field[$idx] = $dbs->escape_string($field[$idx]);
                    }
		    
                    // strip leading field encloser if any
                    $title = preg_replace('@^\\\s*'.$field_enc.'@i', '', $field[0]);
                    $title = '\''.$title.'\'';
                    //$gmd_id = utility::getID($dbs, 'mst_gmd', 'gmd_id', 'gmd_name', $field[1], $gmd_id_cache);
                    $edition = $field[2]?'\''.$field[1].'\'':'NULL';
                    $isbn_issn = $field[3]?'\''.$field[2].'\'':'NULL';
                    $publisher_id = utility::getID($dbs, 'mst_publisher', 'publisher_id', 'publisher_name', $field[3], $publ_id_cache);
                    $publish_year = $field[4]?'\''.$field[4].'\'':'NULL';
                    $collation = $field[5]?'\''.$field[5].'\'':'NULL';
                    $series_title = $field[6]?'\''.$field[6].'\'':'NULL';
                    $call_number = $field[7]?'\''.$field[7].'\'':'NULL';
                    $language_id = utility::getID($dbs, 'mst_language', 'language_id', 'language_name', $field[8], $lang_id_cache);
                    $language_id = '\''.$language_id.'\'';
                    $publish_place_id = utility::getID($dbs, 'mst_place', 'place_id', 'place_name', $field[9], $place_id_cache);
                    $classification = $field[10]?'\''.$field[10].'\'':'NULL';;
                    $notes = $field[11]?'\''.$field[11].'\'':'NULL';;
                    $image = $field[12]?'\''.$field[12].'\'':'NULL';
                    $file_att = $field[13]?'\''.$field[13].'\'':'NULL';
                    // $authors = preg_replace('@\\\s*'.$field_enc.'$@i', '', $field[15]);
                    $authors = trim($field[14]);
                    $subjects = trim($field[15]);
                    $items = trim($field[16]);
		    $specific_detail= $field[17]?'\''.$field[17].'\'':'NULL';
                    $copies = trim($field[18]);
		    $price = trim($field[19]);
		    $arr_label=array();
          	    $arr_label[] = array('label-new', $field[20]?$field[20]:'NULL');
                    	  
		    $url = $arr_label?serialize($arr_label):'NULL';
	            $url = '\''.$url.'\'';
                  
                    $supplier_id = utility::getID($dbs, 'mst_supplier', 'supplier_id', 'supplier_name', $field[21], $supplier_id_cache);
		    $subtitle= preg_replace('@^\\\s*'.$field_enc.'@i', '', $field[22]);
                    $subtitle = '\''.$subtitle.'\'';

                    $volumeno = $field[23]?'\''.$field[23].'\'':'NULL';
                    $serialno = $field[24]?'\''.$field[24].'\'':'NULL';
 
                    $keywords= preg_replace('@^\\\s*'.$field_enc.'@i', '', $field[25]);
                    $keywords = '\''.$keywords.'\'';  

                    $bookreview= preg_replace('@^\\\s*'.$field_enc.'@i', '', $field[26]);
                    $bookreview = '\''.$bookreview.'\'';       

                    $standards = trim($field[27]);   

                   $frequency_id = utility::getID($dbs, 'mst_frequency', 'frequency_id', 'frequency', $field[28], $frequency_id_cache);      
		   $editors = trim($field[29]);	
		   $producers = trim($field[30]);
     		   $directors = trim($field[31]);		
                   $guides = trim($field[32]);	
		   //$edition = $field[32]?'\''.$field[32].'\'':'NULL';	
		   $publication = $field[33]?'\''.$field[33].'\'':'NULL';
		   $publication_date = $field[34]?'\''.$field[34].'\'':'NULL';
		   $academic_year = $field[35]?'\''.$field[35].'\'':'NULL';
		   $abstract = $field[36]?'\''.$field[36].'\'':'NULL';					 		
		   $duration = $field[37]?'\''.$field[37].'\'':'NULL';
		   $company = $field[38]?'\''.$field[38].'\'':'NULL';	
		   $keyactor = $field[39]?'\''.$field[39].'\'':'NULL';							 			   $country = $field[40]?'\''.$field[40].'\'':'NULL';							
		   $state = $field[41]?'\''.$field[41].'\'':'NULL';				
		   $city = $field[42]?'\''.$field[42].'\'':'NULL';
		   $age_group = $field[43]?'\''.$field[43].'\'':'NULL';		
		   $awards = $field[44]?'\''.$field[44].'\'':'NULL';	
		   $college_inst_dept = $field[45]?'\''.$field[45].'\'':'NULL';	
		   $university = $field[46]?'\''.$field[46].'\'':'NULL';
		   $qualification = $field[47]?'\''.$field[47].'\'':'NULL';	
		  // $pages = $field[48]?'\''.$field[48].'\'':'NULL';
		   $journal_serial = $field[48]?'\''.$field[48].'\'':'NULL';		
		   $index_no = $field[49]?'\''.$field[49].'\'':'NULL';	
		   $language_id_2 = utility::getID($dbs, 'mst_language', 'language_id', 'language_name', $field[50], $lang_id_2_cache);
                    $language_id_2 = '\''.$language_id_2.'\'';		
$materialresourceid = $_POST['materialresourceid'];
$gmdID = $_POST['gmdID'];
$materialsubid = $_POST['materialsubid'];
                    // sql insert string
$sql_str = "INSERT IGNORE INTO biblio (material_resource_id, gmd_id, material_sub_id, title, edition, spec_detail_info, isbn_issn, publisher_id, publish_year, collation, series_title, call_number, language_id, publish_place_id, classification, notes, image, file_att, input_date, last_update,labels,sub_title,vol_no,serial_no,tags,review,frequency_id,publication,publication_date,academic_year,abstract,duration,company,actors,country,state,city,age_group,awards,college_inst_dept,university,qualification,journal_serial,index_no,language_id_2)VALUES ($materialresourceid, $gmdID, $materialsubid, $title, $edition,$specific_detail, $isbn_issn, $publisher_id, $publish_year, $collation, $series_title, $call_number,$language_id, $publish_place_id, $classification, $notes, $image, $file_att, $curr_datetime, $curr_datetime,$url,$subtitle,$volumeno,$serialno,$keywords,$bookreview,$frequency_id,$publication,$publication_date,$academic_year,$abstract,$duration,$company,$keyactor,$country,$state,$city,$age_group,$awards,$college_inst_dept,$university,$qualification,$journal_serial,$index_no,$language_id_2)";
//utility::jsAlert($sql_str);
			
                    // send query
                    $dbs->query($sql_str);
                    $biblio_id = $dbs->insert_id;
                    if (!$dbs->error) {
                        $inserted_row++;
                        // set authors
                        if (!empty($authors)) {
                            $biblio_author_sql = 'INSERT IGNORE INTO biblio_author (biblio_id, author_id, level) VALUES ';
                            $authors = explode('><', $authors);
                            foreach ($authors as $author) {
                                $author = trim(str_replace(array('>', '<'), '', $author));
                                $author_id = utility::getID($dbs, 'mst_author', 'author_id', 'author_name', $author, $author_id_cache);
                                $biblio_author_sql .= " ($biblio_id, $author_id, 2),";
                            }
                            // remove last comma
                            $biblio_author_sql = substr_replace($biblio_author_sql, '', -1);
                            // execute query
                            $dbs->query($biblio_author_sql);
                            // echo $dbs->error;
                        }
			//set editors
			 if (!empty($editors)) {
                            $biblio_editor_sql = 'INSERT IGNORE INTO biblio_author (biblio_id, author_id, level) VALUES ';
                            $editors = explode('><', $editors);
                            foreach ($editors as $editor) {
                                $editor = trim(str_replace(array('>', '<'), '', $editor));
                                $editor_id = utility::getID($dbs, 'mst_author', 'author_id', 'author_name', $editor, $editor_id_cache);
                                $biblio_editor_sql .= " ($biblio_id, $editor_id, 3),";
                            }
                            // remove last comma
                            $biblio_editor_sql = substr_replace($biblio_editor_sql, '', -1);
                            // execute query
                            $dbs->query($biblio_editor_sql);
                            // echo $dbs->error;
                        }
			//set producers
			 if (!empty($producers)) {
                            $biblio_producer_sql = 'INSERT IGNORE INTO biblio_author (biblio_id, author_id, level) VALUES ';
                            $producers = explode('><', $producers);
                            foreach ($producers as $producer) {
                                $producer = trim(str_replace(array('>', '<'), '', $producer));
                                $producer_id = utility::getID($dbs, 'mst_author', 'author_id', 'author_name', $producer, $producer_id_cache);
                                $biblio_producer_sql .= " ($biblio_id, $producer_id, 6),";
                            }
                            // remove last comma
                            $biblio_producer_sql = substr_replace($biblio_producer_sql, '', -1);
                            // execute query
                            $dbs->query($biblio_producer_sql);
                            // echo $dbs->error;
                        }
			//set directors
			 if (!empty($directors)) {
                            $biblio_director_sql = 'INSERT IGNORE INTO biblio_author (biblio_id, author_id, level) VALUES ';
                            $directors = explode('><', $directors);
                            foreach ($directors as $director) {
                                $director = trim(str_replace(array('>', '<'), '', $director));
                                $director_id = utility::getID($dbs, 'mst_author', 'author_id', 'author_name', $director, $director_id_cache);
                                $biblio_director_sql .= " ($biblio_id, $director_id, 5),";
                            }
                            // remove last comma
                            $biblio_director_sql = substr_replace($biblio_director_sql, '', -1);
                            // execute query
                            $dbs->query($biblio_director_sql);
                            // echo $dbs->error;
                        }
			//set guides
			 if (!empty($guides)) {
                            $biblio_guide_sql = 'INSERT IGNORE INTO biblio_author (biblio_id, author_id, level) VALUES ';
                            $guides = explode('><', $guides);
                            foreach ($guides as $guide) {
                                $guide = trim(str_replace(array('>', '<'), '', $guide));
                                $guide_id = utility::getID($dbs, 'mst_author', 'author_id', 'author_name', $guide, $guide_id_cache);
                                $biblio_guide_sql .= " ($biblio_id, $guide_id, 11),";
                            }
                            // remove last comma
                            $biblio_guide_sql = substr_replace($biblio_guide_sql, '', -1);
                            // execute query
                            $dbs->query($biblio_guide_sql);
                            // echo $dbs->error;
                        }
                        // set topic
                        if (!empty($subjects)) {
                            $biblio_subject_sql = 'INSERT IGNORE INTO biblio_topic (biblio_id, topic_id, level) VALUES ';
                            $subjects = explode('><', $subjects);
                            foreach ($subjects as $subject) {
                                $subject = trim(str_replace(array('>', '<'), '', $subject));
                                $subject_id = utility::getID($dbs, 'mst_topic', 'topic_id', 'topic', $subject, $subject_id_cache);
                                $biblio_subject_sql .= " ($biblio_id, $subject_id, 2),";
                            }
                            // remove last comma
                            $biblio_subject_sql = substr_replace($biblio_subject_sql, '', -1);
                            // execute query
                            $dbs->query($biblio_subject_sql);
                            // echo $dbs->error;
                        }
			if (!empty($standards)) {
                            $biblio_standards_sql = 'INSERT IGNORE INTO biblio_standard (biblio_id, standard_id) VALUES ';
                            //$standards = explode('><', $standards);
                          //  foreach ($standards as $standard) {
                                //$standard = trim(str_replace(array('>', '<'), '', $standard));
                                $standard_id = utility::getID($dbs, 'mst_standard', 'standard_id', 'standard_name', $standards, $standard_id_cache);
                                $biblio_standards_sql .= " ($biblio_id, $standard_id),";
                           // }
                            // remove last comma
                            $biblio_standards_sql = substr_replace($biblio_standards_sql, '', -1);
                            // execute query
                            $dbs->query($biblio_standards_sql);
                             //echo $dbs->error;
			//utility::jsAlert($dbs->error);
                        }
                        // items
                        if (!empty($items)) {
                           /* $item_sql = 'INSERT IGNORE INTO item (biblio_id, item_code) VALUES ';
                            $item_array = explode('><', $items);
                            foreach ($item_array as $item) {
                                $item = trim(str_replace(array('>', '<'), '', $item));
                                $item_sql .= " ($biblio_id, '$item'),";
                            }
                            // remove last comma
                            $item_sql = substr_replace($item_sql, '', -1);
                            // execute query
                            $dbs->query($item_sql);*/
                             for($i=0;$i<$copies;$i++)// added by iresh on 18-6-2011
			   {      $items=str_pad($items,13,"0",STR_PAD_LEFT);
			 	  $item_sql = "INSERT IGNORE INTO item (biblio_id, item_code,price,supplier_id) VALUES";
				  $item_sql .= " ($biblio_id, '$items','$price','$supplier_id'),";
				  $item_sql = substr_replace($item_sql, '', -1);
				  $dbs->query($item_sql);
				  $items++;
			   }
			
                        }
                    }
                }
                $row_count++;
            }
        }
        // close file handle
        fclose($file);
        $end_time = time();
        $import_time_sec = $end_time-$start_time;
        utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'bibliography', 'Importing '.$inserted_row.' bibliographic records from file : '.$_FILES['importFile']['name']);
        echo '<script type="text/javascript">'."\n";
        echo 'parent.$(\'importInfo\').update(\'<strong>'.$inserted_row.'</strong> records inserted successfully to bibliographic database, from record <strong>'.$_POST['recordOffset'].' in '.$import_time_sec.' second(s)</strong>\');'."\n";
        echo 'parent.$(\'importInfo\').setStyle( {display: \'block\'} );'."\n";
        echo '</script>';
         unlink(REPO_BASE_DIR.DIRECTORY_SEPARATOR.$_FILES['importFile']['name']);
        exit();
    }
}
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
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'bibliography/import.php class="headerText2">Import Data</a>';
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
<a href="<?php echo MODULES_WEB_ROOT_DIR; ?>bibliography/import.php" class="headerText2"><?php echo __('Import Data'); ?></a> </li><li> <a href="<?php echo  MODULES_WEB_ROOT_DIR; ?>bibliography/item_import.php" class="headerText2"><?php echo __('Import Item'); ?></a> </li></ul>
		</td></tr></table>
</fieldset>
<fieldset class="menuBox">
<div class="menuBoxInner importIcon">
    <?php echo __('IMPORT TOOL'); ?>
    <p class="only_border">&nbsp;</p>
 <!--comment by iresh on 11/1/2011   <?php echo __('Import for bibliographics data from CSV file. For guide on CVS fields order and format please refer to documentation or visit <a href="http://senayan.diknas.go.id" target="_blank">Official Website</a>'); ?>-->
<!-- folowing line added by iresh on 11/1/2011-->
<?php echo __('Import for bibliographics data from CSV file'); ?>
<a href="javascript:void(0)" onclick="openHTMLpop('<?php echo MODULES_WEB_ROOT_DIR.'bibliography/Master_Table.xls';?>', 500, 500, '<?php echo MODULES_WEB_ROOT_DIR.'bibliography/Master_Table.xls';?>')">Download Formate</a>
</div>
</fieldset>
<div id="importInfo" class="infoBox" style="display: none;">&nbsp;</div><div id="importError" class="errorBox" style="display: none;">&nbsp;</div>
<?php

// create new instance
$form = new simbio_form_table_AJAX('mainForm', $_SERVER['PHP_SELF'], 'post');
$form->submit_button_attr = 'name="doImport" value="'.__('Import Now').'" class="button"';

// form table attributes
$form->table_attr = 'align="center" id="dataList" cellpadding="5" cellspacing="0"';
$form->table_header_attr = 'class="alterCell" style="font-weight: bold;"';
$form->table_content_attr = 'class="alterCell2"';

/* Form Element(s) */
// csv files
$str_input = simbio_form_element::textField('file', 'importFile');
$str_input .= ' Maximum '.$sysconf['max_upload'].' KB';
/*$gmd_q = $dbs->query('SELECT gmd_id, gmd_name FROM mst_gmd');
	//$gmd_options='';
       $gmd_options = array('Material Type');
        while ($gmd_d = $gmd_q->fetch_row()) 
	//while ($gmd_d = $gmd_q->fetch_assoc())		
	{
            $gmd_options[] = array($gmd_d[0], $gmd_d[1]);
		//$gmd_options.=$gmd_d['gmd_id'].',';
        }*/
 $material_q = $dbs->query('SELECT material_resource_id, material_resource_name FROM mst_material_resource_type where active_inactive="1"');
	//$gmd_options='';
       $material_options = array('Material Resource Type');
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
       
//$str_input='';       
$str_input .= simbio_form_element::selectList('materialresourceid',$material_options, $rec_d['material_resource_id'],'onchange="'.$ajax.'"');
$str_input .= simbio_form_element::selectList('gmdID', $mst_options, $rec_d['gmd_id'],'onchange="'.$ajax_exp.'"');
//$str_input .= simbio_form_element::selectList('gmdID', $mst_options, $rec_d['gmd_id'],'onchange="ccc(this.value);'.$ajax_exp.'"');
$str_input .= '&nbsp;';
$str_input .= simbio_form_element::selectList('materialsubid', $mst_material_sub_type_options, $rec_d['material_sub_id'], 'style="width: 20%;"');
$str_input .= '<tr><td id="txtHint" class="alterCell2" colspan="3"></td></tr>';
 // $str_input .= '<tr><td id="txtsubmaterial" class="alterCell2" colspan="3"></td></tr>';  
       // $str_input .= '&nbsp;';
       //$str_input .= simbio_form_element::textField('text', 'plc_search_str', $rec_d['place_name'], 'style="width: 45%;" onkeyup="'.$ajax_exp.'"');
 //$form->addAnything(__('Material Type'), $str_input);
$form->addAnything(__('File To Import'), $str_input);
// field separator
$form->addTextField('text', 'fieldSep', __('Field Separator').'*', ''.htmlentities(',').'', 'style="width: 10%;" maxlength="3"');
//  field enclosed
$form->addTextField('text', 'fieldEnc', __('Field Enclosed With').'*', ''.htmlentities('"').'', 'style="width: 10%;"');
// number of records to import
$form->addTextField('text', 'recordNum', __('Number of Records To Export (0 for all records)'), '0', 'style="width: 10%;"');
// records offset
$form->addTextField('text', 'recordOffset', __('Start From Record'), '1', 'style="width: 10%;"');
// output the form
echo $form->printOut();
?>
