<?php

error_reporting(0);

require '../../../sysconfig.inc.php';
require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/form_maker/simbio_form_table_AJAX.inc.php';
require SIMBIO_BASE_DIR.'simbio_FILE/simbio_file_upload.inc.php';


$can_read = utility::havePrivilege('bibliography', 'r');

$can_write = utility::havePrivilege('bibliography', 'w');


if ( !function_exists('sys_get_temp_dir') )
{
function sys_get_temp_dir() 
{
    if (!empty($_ENV['TMP'])) { return realpath($_ENV['TMP']); }
    if (!empty($_ENV['TMPDIR'])) { return realpath( $_ENV['TMPDIR']); }
    if (!empty($_ENV['TEMP'])) { return realpath( $_ENV['TEMP']); }
    $tempfile=tempnam(__FILE__,'');
    
    if (file_exists($tempfile)) 
    {
      unlink($tempfile);
      return realpath(dirname($tempfile));
    }
    return null;
  }
}


$max_chars = 1024*1000;
$fileext=pathinfo($_FILES['importFile']['name'], PATHINFO_EXTENSION);


$file = $_FILES['importFile']['name'];
$size= ($_FILES["importFile"]["size"] / 1024);


if (isset($_POST['doImport'])) 
{
    
    // check for form validity
    if (!$_FILES['importFile']['name']) 
    {
        utility::jsAlert(__('Please select the file to import!'));
        exit();
    }
    
    if ($fileext != "csv" ) 
    {
        utility::jsAlert(__('Please select only .csv file!'));
        exit();
    }
    elseif (empty($_POST['fieldSep']) OR empty($_POST['fieldEnc'])) 
    {
        utility::jsAlert(__('Required fields (*)  must be filled correctly!'));
        exit();
    } 
    elseif($_POST['materialresourceid']=="Material Resource Type")
    {
	utility::jsAlert(__('Please select the Material Resource Type!'));
        exit();
        
    }
    elseif($_POST['gmdID']==0)
    {
	utility::jsAlert(__('Please select the Material Type!'));
        exit();
    }else if($_POST['materialsubid']==0){
	utility::jsAlert(__('Please select the Material Sub Type!'));
        exit();
    }
    else 
    {
        $start_time = time();    
        set_time_limit(0);        
        ob_implicit_flush();        
        $upload = new simbio_file_upload();
               
        $temp_dir = sys_get_temp_dir();
        unlink($uploaded_file);        
        $max_size = $sysconf['max_upload']*1024;
       
        $upload->setAllowableFormat(array('.csv'));
        $upload->setMaxSize($max_size);        
        $upload->setUploadDir(REPO_BASE_DIR);
        $upload_status = $upload->doUpload('importFile');
        if ($upload_status != 1) 
        { 
            utility::jsAlert(__('Upload failed! File type not allowed or the size is more than').($sysconf['max_upload']/1024).' MB'); //mfc
            exit();
        }
        $uploaded_file=REPO_BASE_DIR.$upload->new_filename; 
        $row_count = 0;        
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
        $i=0;
        while (!feof($file)) 
        {
                // record count
                if ($record_num > 0 AND $row_count == $record_num) 
                 {
                    break;
                 }
                // go to offset
                if ($row_count < $record_offset) 
                {
                    // pass and continue to next loop
                    $field = fgetcsv($file, 1024, $field_sep, $field_enc);
                    
                     $row_count++;
                    continue;
                } 
                else 
                {
                    // get an array of field
                    $field = fgetcsv($file, $max_chars, $field_sep, $field_enc);
                    if ($field) 
                    {                        
                            foreach ($field as $idx => $value) 
                            {
                                $field[$idx] = str_replace('\\', '', trim($value));
                                $field[$idx] = $dbs->escape_string($field[$idx]);
                            }
		             
                            
                             //echo  $item;    
                            
                             $materialsubid = intval($_POST['materialsubid']);
                             $materialresourceid=intval($_POST['materialresourceid']);
                             $gmdID=intval($_POST['gmdID']);
                           
                                if ($materialsubid==130 )
                                {       
                                        
                                        $_sql = "select max(item_code) AS item_code from item";
                                        $code = $dbs->query($_sql);
                                        $row = $code->fetch_row();
                                            // utility::jsAlert($row[0]);die;
                                            $item_start = str_pad($field[15], 13, "0", STR_PAD_LEFT); 
                                             //utility::jsAlert($item_start);die;
                                            if($item_start <= $row[0]){
                                               utility::jsAlert("Item code is already registered.");die;
                                            }
                                            else{
                                               $items = trim($field[15]);
                                               //exit();
                                            }
//                                        $fiel = preg_match('!^[\w @.-]*$!', $field[0]);
//                                        utility::jsAlert($fiel);die;
                                        if($field[0] == '' && $field[15] == '' && $field[17] == '')
                                        {
                                             utility::jsAlert("You cannot import this file : please enter the following values first : title,item start value,copies.");die;
                                        }
                                        else if($field[0] == '')
                                        {
                                             utility::jsAlert("You cannot import this file : please enter the title value first.");die;
                                        }
                                        else if($field[15] == '')
                                        {
                                             utility::jsAlert("You cannot import this file : please enter the item start value first.");die;
                                        }
                                        else if($field[17] == '')
                                        {
                                             utility::jsAlert("You cannot import this file : please enter the copies value first.");die;
                                        }
                                        if($field[24] == ''){
                                            //utility::jsAlert("Please enter the correct standard value.");die;
                                            $field[24] = trim($field[24]); 
                                        }
                                        else
                                        {
                                            
                                           //                                            utility::jsAlert($obj_db);die;
                                             $confirm = $dbs->query("select title from $cms_database.COURSE_SUBJECTS where school_id=1");
                                             
                                             while($con=$confirm->fetch_row())
		                             {
                                                // utility::jsAlert($num);die;
			                       $stand=$con[0];
                                              
                                               if($field[24] == $stand){
                                                   $fie = $stand; 
                                                   //utility::jsAlert($fie);die;
                                                   //exit();
                                               }
                                               else if($field[24] == $stand){
                                                   utility::jsAlert("Please enter the correct standard value.");die;
                                               }
                                            }	       
                                            
                                            
                                        }
                                       
                                            
                                        $title = $field[0];
                                        $title = '\''.$title.'\'';                      
                                       // utility::jsAlert($title);
                                        $edition = $field[2]?'\''.$field[1].'\'':'NULL';                    
                                        //utility::jsAlert($edition);
                                        $isbn_issn = $field[3]?'\''.$field[2].'\'':'NULL';                    
                                        //utility::jsAlert($isbn_issn);
                                        $publisher_id = utility::getID($dbs, 'mst_publisher', 'publisher_id', 'publisher_name', $field[3], $publ_id_cache);                    
                                        //utility::jsAlert($publisher_id);
                                        $publish_year = $field[4]?'\''.$field[4].'\'':'NULL';

                                        $series_title = $field[5]?'\''.$field[5].'\'':'NULL';                    
                                        $call_number = $field[6]?'\''.$field[6].'\'':'NULL';
                                        $language_id = utility::getID($dbs, 'mst_language', 'language_id', 'language_name', $field[7], $lang_id_cache);
                                        $publish_place_id = utility::getID($dbs, 'mst_place', 'place_id', 'place_name', $field[8], $place_id_cache);
                                        $classification = $field[9]?'\''.$field[9].'\'':'NULL';
                                        $notes = $field[10]?'\''.$field[10].'\'':'NULL';;
                                        $image = $field[11]?'\''.$field[11].'\'':'NULL';
                                        $file_att = $field[12]?'\''.$field[12].'\'':'NULL';
                                        // $authors = preg_replace('@\\\s*'.$field_enc.'$@i', '', $field[15]);
                                        $authors = trim($field[13]);
										$author_id = utility::getID($dbs, 'mst_author', 'author_id', 'author_name', $field[13], $author_id_cache);
                                        $subjects = trim($field[14]);

//                                        $item_start = trim($field[15]);   
                                       
                                        
                                        $specific_detail= $field[17]?'\''.$field[16].'\'':'NULL';
                                        $copies = trim($field[17]);
                                        $price = trim($field[18]);


                                        $arr_label=array();
                                        $arr_label[] = array('label-new', $field[19]?$field[19]:'NULL');

                                        $url = $arr_label?serialize($arr_label):'NULL';
                                        $url = '\''.$url.'\'';

                                        $supplier_id = utility::getID($dbs, 'mst_supplier', 'supplier_id', 'supplier_name', $field[20], $supplier_id_cache);

                                        $subtitle= preg_replace('@^\\\s*'.$field_enc.'@i', '', $field[21]);
                                        $subtitle = '\''.$subtitle.'\'';


                                        $keywords= preg_replace('@^\\\s*'.$field_enc.'@i', '', $field[22]);
                                        $keywords = '\''.$keywords.'\'';  

                                        $bookreview= preg_replace('@^\\\s*'.$field_enc.'@i', '', $field[23]);
                                        $bookreview = '\''.$bookreview.'\'';       

                                        $standards=$fie;
                                        //utility::jsAlert($standards);die;
                                        
                                        $frequency_id = utility::getID($dbs, 'mst_frequency', 'frequency_id', 'frequency', $field[25], $frequency_id_cache);                                                                                                                      

                                          $sql_str ="INSERT IGNORE INTO biblio";
                                          $sql_str .="(material_resource_id, gmd_id, material_sub_id, title, edition, spec_detail_info, isbn_issn,publisher_id,author_id, publish_year,series_title,language_id, publish_place_id, classification, notes, image, file_att, input_date, last_update,labels,sub_title,tags,review,standard,frequency_id)"; 
                                          $sql_str .="VALUES ($materialresourceid, $gmdID, $materialsubid, $title, $edition,$specific_detail, $isbn_issn,$publisher_id,$author_id,$publish_year,$series_title,'$language_id',$publish_place_id,$classification,$notes,$image,$file_att,$curr_datetime,$curr_datetime,$url,$subtitle,$keywords,'$bookreview','$standards','$frequency_id')";
										  
										  $sql_str_biblio_item ="INSERT IGNORE INTO biblio_item";
                                          $sql_str_biblio_item .="(material_resource_id, gmd_id, material_sub_id, title, edition, spec_detail_info, isbn_issn,publisher_id,author_id, publish_year,series_title,language_id, publish_place_id, classification, notes, image, file_att, input_date, last_update,labels,sub_title,tags,review,standard,frequency_id)"; 
                                          $sql_str_biblio_item .="VALUES ($materialresourceid, $gmdID, $materialsubid, $title, $edition,$specific_detail, $isbn_issn,$publisher_id,$author_id,$publish_year,$series_title,'$language_id',$publish_place_id,$classification,$notes,$image,$file_att,$curr_datetime,$curr_datetime,$url,$subtitle,$keywords,'$bookreview','$standards','$frequency_id')";
										  $dbs->query($sql_str_biblio_item);
//utility::jsAlert($sql_str);die;
                                }  
                                elseif ($materialsubid==84  || $materialsubid==85 || $materialsubid==87 || $materialsubid==88)
                                {           
                                          if($field[15]!=''){
                                          $att="vir".date('YmdHis');
                                          $field[15]=$att;
//                                          utility::jsAlert($field[15]);die;
                                          }
                                          $filee = "select max(file_att)as file_att from biblio";
                                          $fil=$dbs->query($filee);
                                          $rows = $fil->fetch_row();
//                                            utility::jsAlert($rows[0]);die;
                                          if($field[15] <= $rows[0]){
//                                              utility::jsAlert("hiiii");
                                               utility::jsAlert("file attach is already registered.");die;
                                          }
                                          else{
//                                              utility::jsAlert("hey");
                                              $file_att = $field[15];
                                               //exit();
                                          }
//                                        $_sql = "select max(item_code) AS item_code from item";
//                                        $code = $dbs->query($_sql);
//                                        $row = $code->fetch_row();
//                                        // utility::jsAlert($row[0]);die;
//                                        $item_start = str_pad($field[15], 13, "0", STR_PAD_LEFT); 
//                                        //utility::jsAlert($item_start);die;
//                                        if($item_start <= $row[0]){
//                                            utility::jsAlert("Item code is already registered.");die;
//                                        }
//                                        else{
//                                            $items = trim($field[24]);
//                                            //exit();
//                                        }
                                        if($field[0] == '')
                                        {
                                             utility::jsAlert("You cannot import this file : please enter the title value first.");die;
                                        }
                                        
                                        $title = preg_replace('@^\\\s*'.$field_enc.'@i', '', $field[0]);
                                        $title = '\''.$title.'\''; 
                                        
                                        $edition = $field[1]?'\''.$field[1].'\'':'NULL'; 
                                       
                                        $isbn_issn = $field[2]?'\''.$field[2].'\'':'NULL';  
                                       
                                        $series_title = $field[3]?'\''.$field[3].'\'':'NULL';                    

                                        $keywords= preg_replace('@^\\\s*'.$field_enc.'@i', '', $field[4]);
                                        $keywords = '\''.$keywords.'\'';  
 
                                        $specific_detail= $field[5]?'\''.$field[5].'\'':'NULL';

                                  //      $authors = trim($field[6]);

                                        $frequency_id = utility::getID($dbs, 'mst_frequency', 'frequency_id', 'frequency', $field[7], $frequency_id_cache);                                                                                                                                                  
                                       
                                        $publisher_id = utility::getID($dbs, 'mst_publisher', 'publisher_id', 'publisher_name', $field[8], $publ_id_cache);                    
                                        
                                        $publish_year = $field[9]?'\''.$field[9].'\'':'NULL';
                                        
                                        $publish_place_id = utility::getID($dbs, 'mst_place', 'place_id', 'place_name', $field[10], $place_id_cache);                            
                                        
                                        $publication_date = $field[11]?'\''.$field[11].'\'':'NULL';
                                        $subjects = trim($field[12]);                                                        
                                        $language_id = utility::getID($dbs, 'mst_language', 'language_id', 'language_name', $field[13], $lang_id_cache);                            
                                        $image = $field[14]?'\''.$field[14].'\'':'NULL';
//                                        $file_att = $field[15]?'\''.$field[15].'\'':'NULL';

                                        $arr_label=array();
                                        $arr_label[] = array('label-new', $field[16]?$field[16]:'NULL');

                                        $url = $arr_label?serialize($arr_label):'NULL';
                                        $url = '\''.$url.'\'';

                                        $notes = $field[17]?'\''.$field[17].'\'':'NULL';;

                                        $subtitle= preg_replace('@^\\\s*'.$field_enc.'@i', '', $field[18]);
                                        $subtitle = '\''.$subtitle.'\'';

                                        $classification = $field[19]?'\''.$field[19].'\'':'NULL';

                                        $standards = trim($field[21]);   
                                        $abstract = $field[22]?'\''.$field[22].'\'':'NULL';	
                                        // $authors = preg_replace('@\\\s*'.$field_enc.'$@i', '', $field[15]);

                                        $bookreview= preg_replace('@^\\\s*'.$field_enc.'@i', '', $field[23]);
                                        $bookreview = '\''.$bookreview.'\'';       

                                        $academic_year = $field[24]?'\''.$field[24].'\'':'NULL';     
//                                        $issue_no= trim($field[25]);  
//                                        $serial_no= trim($field[26]);  

                                          $sql_str ="INSERT IGNORE INTO biblio";
                                          $sql_str .="(material_resource_id, gmd_id, material_sub_id, title, edition, spec_detail_info, isbn_issn,publisher_id, publish_year,series_title,language_id, publish_place_id, classification, notes, image, file_att, input_date, last_update,labels,sub_title,tags,review,frequency_id,publication_date,abstract,academic_year)";//,issue_no,serial_no)"; 
                                          $sql_str .="VALUES ($materialresourceid, $gmdID, $materialsubid, $title, $edition,$specific_detail, $isbn_issn,$publisher_id,$publish_year,$series_title,'$language_id',$publish_place_id,$classification,$notes,$image,'$file_att',$curr_datetime,$curr_datetime,$url,$subtitle,$keywords,$bookreview,$frequency_id,$publication_date,$abstract,$academic_year)";//,$issue_no,$serial_no)";

//                                        utility::jsalert($sql_str);die;
                                }

                                elseif($materialsubid==78 || $materialsubid==112 || $materialsubid==113)
                                {
//                                        $_sql = "select max(item_code) AS item_code from item";
//                                        $code = $dbs->query($_sql);
//                                        $row = $code->fetch_row();
//                                        // utility::jsAlert($row[0]);die;
//                                        $item_start = str_pad($field[15], 13, "0", STR_PAD_LEFT); 
//                                        //utility::jsAlert($item_start);die;
//                                        if($item_start <= $row[0]){
//                                            utility::jsAlert("Item code is already registered.");die;
//                                        }
//                                        else{
//                                            $items = trim($field[24]);
//                                            //exit();
//                                        }
                                        if($field[0] == '' && $field[15] == '' && $field[17] == '')
                                        {
                                             utility::jsAlert("You cannot import this file : please enter the following values first : title,specific detail info.,supplier id.");die;
                                        }
                                        else if($field[0] == '')
                                        {
                                             utility::jsAlert("You cannot import this file : please enter the title value first.");die;
                                        }
                                        else if($field[15] == '')
                                        {
                                             utility::jsAlert("You cannot import this file : please enter the specific detail info. value first.");die;
                                        }
                                        else if($field[17] == '')
                                        {
                                             utility::jsAlert("You cannot import this file : please enter the suppiler id value first.");die;
                                        }
                                        $title = preg_replace('@^\\\s*'.$field_enc.'@i', '', $field[0]);
                                        $title = '\''.$title.'\'';                                                 
                                        $edition = $field[2]?'\''.$field[1].'\'':'NULL';                    

                                        $isbn_issn = $field[3]?'\''.$field[2].'\'':'NULL';                    

                                        $publisher_id = utility::getID($dbs, 'mst_publisher', 'publisher_id', 'publisher_name', $field[3], $publ_id_cache);                    

                                        $publish_year = $field[4]?'\''.$field[4].'\'':'NULL';

                                        $series_title = $field[5]?'\''.$field[5].'\'':'NULL';                    
                                        $call_number = $field[6]?'\''.$field[6].'\'':'NULL';
                                        $language_id = utility::getID($dbs, 'mst_language', 'language_id', 'language_name', $field[7], $lang_id_cache);
                                        $publish_place_id = utility::getID($dbs, 'mst_place', 'place_id', 'place_name', $field[8], $place_id_cache);
                                        $classification = $field[9]?'\''.$field[9].'\'':'NULL';
                                        $notes = $field[10]?'\''.$field[10].'\'':'NULL';;
                                        $image = $field[11]?'\''.$field[11].'\'':'NULL';
                                        $file_att = $field[12]?'\''.$field[12].'\'':'NULL';
                                        // $authors = preg_replace('@\\\s*'.$field_enc.'$@i', '', $field[15]);
                                        $authors = trim($field[13]);
                                        $subjects = trim($field[14]);

                                        //$items = trim($field[15]);   


                                        $specific_detail= $field[15]?'\''.$field[15].'\'':'NULL';
                                        //$copies = trim($field[17]);
                                        //$price = trim($field[18]);


                                        $arr_label=array();
                                        $arr_label[] = array('label-new', $field[16]?$field[16]:'NULL');

                                        $url = $arr_label?serialize($arr_label):'NULL';
                                        $url = '\''.$url.'\'';

                                        $supplier_id = utility::getID($dbs, 'mst_supplier', 'supplier_id', 'supplier_name', $field[17], $supplier_id_cache);

                                        $subtitle= preg_replace('@^\\\s*'.$field_enc.'@i', '', $field[18]);
                                        $subtitle = '\''.$subtitle.'\'';


                                        $keywords= preg_replace('@^\\\s*'.$field_enc.'@i', '', $field[19]);
                                        $keywords = '\''.$keywords.'\'';  

                                        $bookreview= preg_replace('@^\\\s*'.$field_enc.'@i', '', $field[20]);
                                        $bookreview = '\''.$bookreview.'\'';       

                                        $standards = trim($field[21]);   

                                        $frequency_id = utility::getID($dbs, 'mst_frequency', 'frequency_id', 'frequency', $field[22], $frequency_id_cache);                                                                                                                      

                                          $sql_str ="INSERT IGNORE INTO biblio";
                                          $sql_str .="(material_resource_id, gmd_id, material_sub_id, title, edition, spec_detail_info, isbn_issn,publisher_id, publish_year,series_title,language_id, publish_place_id, classification, notes, image, file_att, input_date, last_update,labels,sub_title,tags,review,frequency_id)"; 
                                          $sql_str .="VALUES ($materialresourceid, $gmdID, $materialsubid, $title, $edition,$specific_detail, $isbn_issn,$publisher_id,$publish_year,$series_title,'$language_id',$publish_place_id,$classification,$notes,$image,'$file_att',$curr_datetime,$curr_datetime,$url,$subtitle,$keywords,$bookreview,$frequency_id)";
                                          

                                }
                                elseif($materialsubid==131)
                                {
                                        $_sql = "select max(item_code) AS item_code from item";
                                        $code = $dbs->query($_sql);
                                        $row = $code->fetch_row();                                        
                                        $item_start = str_pad($field[5], 13, "0", STR_PAD_LEFT);                                         
                                        if($item_start <= $row[0]){
                                            utility::jsAlert("Item code is already registered.");die;
                                        }
                                        else{
                                            $items = trim($field[5]);
                                            //exit();
                                        }
                                        if($field[0] == '' && $field[5] == '' && $field[6] == '')
                                        {
                                             utility::jsAlert("You cannot import this file : please enter the following values first : title,item start value,copies.");die;
                                        }
                                        else if($field[0] == '')
                                        {
                                             utility::jsAlert("You cannot import this file : please enter the title value first.");die;
                                        }
                                        else if($field[5] == '')
                                        {
                                             utility::jsAlert("You cannot import this file : please enter the item start value first.");die;
                                        }
                                        else if($field[6] == '')
                                        {
                                             utility::jsAlert("You cannot import this file : please enter the copies value first.");die;
                                        }
                                        $title = preg_replace('@^\\\s*'.$field_enc.'@i', '', $field[0]);
                                        $title = '\''.$title.'\'';                                                 
                                        $edition = $field[1]?'\''.$field[1].'\'':'NULL';                    

                                        $isbn_issn = $field[2]?'\''.$field[2].'\'':'NULL';                    

                                        $keywords= preg_replace('@^\\\s*'.$field_enc.'@i', '', $field[3]);
                                        $keywords = '\''.$keywords.'\'';                                                        

                                        $specific_detail= $field[4]?'\''.$field[4].'\'':'NULL';

                                        $items = trim($field[5]);                               
                                        $copies = trim($field[6]);
                                        $price = trim($field[7]);

                                        $sql_str ="INSERT IGNORE INTO biblio";
                                        $sql_str .="(material_resource_id, gmd_id, material_sub_id, title, edition, spec_detail_info, isbn_issn,input_date, last_update,tags)"; 
                                        $sql_str .="VALUES ($materialresourceid, $gmdID, $materialsubid, $title, $edition,$specific_detail, $isbn_issn,$curr_datetime,$curr_datetime,$keywords)";
                                        //   utility::jsAlert($sql_str);                     
                                        // exit();
                                }
                                elseif($materialsubid==132 || $materialsubid==133)
                                {
                                        $_sql = "select max(item_code) AS item_code from item";
                                        $code = $dbs->query($_sql);
                                        $row = $code->fetch_row();
                                        // utility::jsAlert($row[0]);die;
                                        $item_start = str_pad($field[5], 13, "0", STR_PAD_LEFT); 
                                        //utility::jsAlert($item_start);die;
                                        if($item_start <= $row[0])
                                        { 
                                            utility::jsAlert("Item code is already registered.");die;
                                        }
                                        else
                                        {
                                            $items = trim($field[5]);                                        
                                        }

                                        if($field[0] == '' && $field[5] == '' && $field[6] == '')
                                        {
                                             utility::jsAlert("You cannot import this file : please enter the following values first : title,item start value,copies.");die;
                                        }
                                        else if($field[0] == '')
                                        {
                                             utility::jsAlert("You cannot import this file : please enter the title value first.");die;
                                        }
                                        else if($field[4] == '')
                                        {
                                             utility::jsAlert("You cannot import this file : please enter the item start value first.");die;
                                        }
                                        else if($field[5] == '')
                                        {
                                             utility::jsAlert("You cannot import this file : please enter the copies value first.");die;
                                        }
                                        $ite = str_pad($field[4],13,'0',STR_PAD_LEFT);
                                       // utility::jsAlert($item);die;
                                        
                                        $title = preg_replace('@^\\\s*'.$field_enc.'@i', '', $field[0]);
                                        $title = '\''.$title.'\'';                                                 
                                        $edition = $field[2]?'\''.$field[1].'\'':'NULL';                    

                                        //$isbn_issn = $field[3]?'\''.$field[2].'\'':'NULL';                    

                                        $keywords= preg_replace('@^\\\s*'.$field_enc.'@i', '', $field[2]);
                                        $keywords = '\''.$keywords.'\'';                                                        

                                        $specific_detail= $field[3]?'\''.$field[3].'\'':'NULL';

                                        $items = trim($field[4]);                               
                                        $copies = trim($field[5]);
                                        $price = trim($field[6]);

                                        $sql_str ="INSERT IGNORE INTO biblio";
                                        $sql_str .="(material_resource_id, gmd_id, material_sub_id, title, edition, spec_detail_info,input_date, last_update,tags)"; 
                                        $sql_str .="VALUES ($materialresourceid, $gmdID, $materialsubid, $title, $edition,$specific_detail,$curr_datetime,$curr_datetime,$keywords)";                                                                      
                                }                                                            
                                elseif($materialsubid==114 || $materialsubid==115 || $materialsubid==116 || $materialsubid==117 || $materialsubid==118)
                                {
//                                        $_sql = "select max(item_code) AS item_code from item";
//                                        $code = $dbs->query($_sql);
//                                        $row = $code->fetch_row();
//                                        // utility::jsAlert($row[0]);die;
//                                        $item_start = str_pad($field[15], 13, "0", STR_PAD_LEFT); 
//                                        //utility::jsAlert($item_start);die;
//                                        if($item_start <= $row[0]){
//                                             utility::jsAlert("Item code is already registered.");die;
//                                        }
//                                        else{
//                                             $items = trim($field[24]);
//                                             //exit();
//                                        }
                                        if($field[0] == '')
                                        {
                                             utility::jsAlert("You cannot import this file : please enter the title value first.");die;
                                        }
                                        $title = preg_replace('@^\\\s*'.$field_enc.'@i', '', $field[0]);
                                        $title = '\''.$title.'\'';                      

                                       // $edition = $field[2]?'\''.$field[1].'\'':'NULL';                    

                                        $isbn_issn = $field[1]?'\''.$field[1].'\'':'NULL';                    

                                        $series_title = $field[2]?'\''.$field[2].'\'':'NULL';                    

                                        $keywords= preg_replace('@^\\\s*'.$field_enc.'@i', '', $field[3]);
                                        $keywords = '\''.$keywords.'\'';  

                                        $authors = trim($field[4]);

                                        $frequency_id = utility::getID($dbs, 'mst_frequency', 'frequency_id', 'frequency', $field[5], $frequency_id_cache);                                                                                                                      

                                        $publisher_id = utility::getID($dbs, 'mst_publisher', 'publisher_id', 'publisher_name', $field[6], $publ_id_cache);                    

                                        $publish_place_id = utility::getID($dbs, 'mst_place', 'place_id', 'place_name', $field[7], $place_id_cache);

                                        $publication_date = $field[8]?'\''.$field[8].'\'':'NULL';


                                        $subjects = trim($field[9]);

                                        $language_id = utility::getID($dbs, 'mst_language', 'language_id', 'language_name', $field[10], $lang_id_cache);

                                        $file_att = $field[11]?'\''.$field[11].'\'':'NULL';
                                        // $authors = preg_replace('@\\\s*'.$field_enc.'$@i', '', $field[15]);

                                        $arr_label=array();
                                        $arr_label[] = array('label-new', $field[12]?$field[12]:'NULL');

                                        $url = $arr_label?serialize($arr_label):'NULL';
                                        $url = '\''.$url.'\'';         


                                         $notes = $field[13]?'\''.$field[13].'\'':'NULL';
                                         $classification = $field[14]?'\''.$field[14].'\'':'NULL';

                                         $subtitle= preg_replace('@^\\\s*'.$field_enc.'@i', '', $field[15]);
                                         $subtitle = '\''.$subtitle.'\'';

                                         $abstract = $field[16]?'\''.$field[16].'\'':'NULL';	


                                          $sql_str ="INSERT IGNORE INTO biblio";
                                          $sql_str .="(material_resource_id, gmd_id, material_sub_id, title,isbn_issn,series_title,tags,frequency_id,publisher_id,publish_place_id,language_id,file_att,labels, input_date, last_update,publication_date,notes,classification,sub_title,abstract)"; 
                                          $sql_str .="VALUES ($materialresourceid, $gmdID, $materialsubid, $title,$isbn_issn,$series_title,$keywords,$frequency_id,$publisher_id,$publish_place_id,'$language_id',$file_att,$url,$curr_datetime,$curr_datetime,$publication_date,$notes,$classification,$subtitle,$abstract)";                           

                                }                                                                                                                                                                                                                                                                                           
                                elseif($materialsubid==83 || $materialsubid==79 || $materialsubid==77 || $materialsubid==80 || $materialsubid==81  || $materialsubid==82)
                                {
//                                        $_sql = "select max(item_code) AS item_code from item";
//                                        $code = $dbs->query($_sql);
//                                        $row = $code->fetch_row();
//                                        // utility::jsAlert($row[0]);die;
//                                        $item_start = str_pad($field[15], 13, "0", STR_PAD_LEFT); 
//                                        //utility::jsAlert($item_start);die;
//                                        if($item_start <= $row[0]){
//                                            utility::jsAlert("Item code is already registered.");die;
//                                        }
//                                        else{
//                                            $items = trim($field[24]);
//                                            //exit();
//                                        }
                                        if($field[0] == '')
                                        {
                                             utility::jsAlert("You cannot import this file : please enter the title value first.");die;
                                        }
                                        $title = preg_replace('@^\\\s*'.$field_enc.'@i', '', $field[0]);
                                        $title = '\''.$title.'\'';                      

                                       // $edition = $field[2]?'\''.$field[1].'\'':'NULL';                    

                                        $isbn_issn = $field[1]?'\''.$field[1].'\'':'NULL';                    

                                        $series_title = $field[2]?'\''.$field[2].'\'':'NULL';                    

                                        $keywords= preg_replace('@^\\\s*'.$field_enc.'@i', '', $field[3]);
                                        $keywords = '\''.$keywords.'\'';  

                                        $authors = trim($field[4]);

                                        $frequency_id = utility::getID($dbs, 'mst_frequency', 'frequency_id', 'frequency', $field[5], $frequency_id_cache);                                                                                                                      

                                        $publisher_id = utility::getID($dbs, 'mst_publisher', 'publisher_id', 'publisher_name', $field[6], $publ_id_cache);                    

                                        $publish_place_id = utility::getID($dbs, 'mst_place', 'place_id', 'place_name', $field[7], $place_id_cache);

                                        $publication_date = $field[8]?'\''.$field[8].'\'':'NULL';


                                        $subjects = trim($field[9]);

                                        $language_id = utility::getID($dbs, 'mst_language', 'language_id', 'language_name', $field[10], $lang_id_cache);

                                        $file_att = $field[11]?'\''.$field[11].'\'':'NULL';
                                        // $authors = preg_replace('@\\\s*'.$field_enc.'$@i', '', $field[15]);

                                        $arr_label=array();
                                        $arr_label[] = array('label-new', $field[12]?$field[12]:'NULL');

                                        $url = $arr_label?serialize($arr_label):'NULL';
                                        $url = '\''.$url.'\'';                                                                       

                                          $sql_str ="INSERT IGNORE INTO biblio";
                                          $sql_str .="(material_resource_id, gmd_id, material_sub_id, title,isbn_issn,series_title,tags,frequency_id,publisher_id,publish_place_id,language_id,file_att,labels, input_date, last_update,publication_date)"; 
                                          $sql_str .="VALUES ($materialresourceid, $gmdID, $materialsubid, $title,$isbn_issn,$series_title,$keywords,$frequency_id,$publisher_id,$publish_place_id,'$language_id',$file_att,$url,$curr_datetime,$curr_datetime,$publication_date)";   


                                }                                                                                                  
                                elseif ($materialsubid==68 || $materialsubid==69 || $materialsubid==70 || $materialsubid==71 || $materialsubid==72 || $materialsubid==73 || $materialsubid==74 || $materialsubid==75|| $materialsubid==76  || $materialsubid==97||$materialsubid==138 || $materialsubid==89 || $materialsubid==86 || $materialsubid==90 || $materialsubid==91 || $materialsubid==92 || $materialsubid==93 || $materialsubid==94 || $materialsubid==95 || $materialsubid==96)
                                {
									
                                        
//                                        $_sql = "select max(item_code) AS item_code from item";
//                                        $code = $dbs->query($_sql);
//                                        $row = $code->fetch_row();
//                                        // utility::jsAlert($row[0]);die;
//                                        $item_start = str_pad($field[15], 13, "0", STR_PAD_LEFT); 
//                                        //utility::jsAlert($item_start);die;
//                                        if($item_start <= $row[0]){
//                                            utility::jsAlert("Item code is already registered.");die;
//                                        }
//                                        else{
//                                            $items = trim($field[24]);
//                                            //exit();
//                                        }
                                        if($field[0] == '')
                                        {
                                             utility::jsAlert("You cannot import this file : please enter the title value first.");die;
                                        }
                                        $title = preg_replace('@^\\\s*'.$field_enc.'@i', '', $field[0]);
                                        $title = '\''.$title.'\'';  
//                                        utility::jsAlert($title);die;
                                        
                                        //$edition = $field[2]?'\''.$field[1].'\'':'NULL';                    

                                        //$isbn_issn = $field[3]?'\''.$field[2].'\'':'NULL';                    

                                        $keywords= preg_replace('@^\\\s*'.$field_enc.'@i', '', $field[1]);
                                        $keywords = '\''.$keywords.'\'';    
                                       
                                        $file_att = $field[2]?'\''.$field[2].'\'':'NULL';

                                        $arr_label=array();
                                        $arr_label[] = array('label-new', $field[3]?$field[3]:'NULL');

                                        $url = $arr_label?serialize($arr_label):'NULL';
                                        $url = '\''.$url.'\'';
                                        $sql_str ="INSERT INTO biblio";
                                        $sql_str .="(material_resource_id, gmd_id, material_sub_id, title,input_date, last_update,tags,file_att,labels)"; 
                                        $sql_str .="VALUES ($materialresourceid, $gmdID, $materialsubid, $title,$curr_datetime,$curr_datetime,$keywords,$file_att,$url)";
                                 }

                                
                                if (!empty($items))
                                { 
                                        $temp_item = explode('-',$items);
                                        $temp_items=intval(trim($temp_item[0]));
                                        $temp_items_inc=intval(trim($temp_item[0]));
                                        $temp_copies=intval(trim($copies));                           
                                        $item_code_exist='';
                                         for($i=0;$i<$temp_copies;$i++)                         
                                         {                                                                                                    
                                                $temp_items=str_pad($temp_items,13,"0",STR_PAD_LEFT);			 	

                                                $qry = $dbs->query("select item_code from item where item_code IN($temp_items)");                                  
                                                $result = $qry->fetch_assoc(); 

                                                if (trim($result['item_code'])!='')
                                                    $item_code_exis.=ltrim($result['item_code'], '0').',';
                                              
                                                      $item_sql = substr_replace($item_sql, '', -1);                                 
                                                      $dbs->query($item_sql);
                                                      $temp_items++;

                                          }
                                          $tot_available=explode(',',$item_code_exis);
                                          $tot_available1=count($tot_available)-1;
                                    }   
                                    //utility::jsalert($sql_str);
                                    //exit;
                    $dbs->query($sql_str);                   
                    $biblio_id = $dbs->insert_id;                    
					utility::jsalert('Inserted data');
                    if (!$dbs->error) 
                    {                       
                        $inserted_row++;                        
                        if (!empty($authors)) 
                        {
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
			 if (!empty($producers)) 
                         {
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
			 if (!empty($directors)) 
                         {
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
                        
                        
                        if (!empty($file_att))
                        {
                           $date_time=date('Y-m-d H:i:s');
                           
//                           insert into files(file_title,file_name,uploader_id,input_date,last_update)
                                   
                                   $file_sql = "INSERT IGNORE INTO files(file_title,file_name,uploader_id,input_date,last_update) VALUES";                                 
                                   $file_sql .= " ($file_att, $file_att,0,'$date_time','$date_time')";                                                                                                                                         
                                   $dbs->query($file_sql);
                                   $file_id = $dbs->insert_id;
                                    
                                   //insert into biblio_attachment(biblio_id,file_id,access_type) values('','','enum('public','private')');
                                   $file1_sql = "INSERT IGNORE INTO biblio_attachment(biblio_id,file_id,access_type) VALUES";                                 
                                   $file1_sql .= " ($biblio_id, $file_id,'public')";                                                                    
                                    //utility::jsAlert($file1_sql);
                                   $dbs->query($file1_sql);
                                   
                         
                        }
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
                        
                        if (!empty($items))
                        { 
                            $item = explode('-',$items);
                            $items=intval(trim($item[0]));
                            /* $qry = $dbs->query('select max(item_code) as max_item  from item');
                             $result = $qry->fetch_assoc();
                             $items = $result['max_item']+1;                             */
                             for($i=0;$i<$copies;$i++)                         
                             {                                                                                                    
                                  $items=str_pad($items,9,"0",STR_PAD_LEFT);
			 	  $item_sql = "INSERT IGNORE INTO item (biblio_id, item_code,price,supplier_id) VALUES";                                 
				  $item_sql .= " ('$biblio_id', '$items','$price','$supplier_id'),";
                                   //$item_sql ="INSERT INTO item (biblio_id, item_code, call_number, inventory_code, location_id, site, coll_type_id, item_status_id, source, order_no, order_date, received_date, supplier_id, invoice, invoice_date, price_currency, price, input_date, last_update) VALUES( '$biblio_id', '$items', '$call_number','NULL', 'NULL','',,'',, '', '00-00-0000', '00-00-0000', '$supplier_id', '', '00-00-0000', NULL, '$price', '0000-00-00 00:00:00', '0000-00-00 00:00:00')";                                  
                                   //utility::jsAlert($item_sql);
				  $item_sql = substr_replace($item_sql, '', -1);                                 
                                  $dbs->query($item_sql);
				  $items++;
                                 
                              }
			
                        }
                                              
                    }
                  //  utility::jsAlert("hiii");die;
                }
                $row_count++;
            }
            $i=$i+1;
//            $inserted_row++;
            if ($i>500)
            {
             utility::jsAlert('Number of Of row is Greater than 500! Please Decrease the Row');
             exit();
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
<a href="<?php echo MODULES_WEB_ROOT_DIR; ?>bibliography/import.php" class="headerText2"><?php echo __('Import Data'); ?></a> </li><!--<li> <a href="<?php //echo  MODULES_WEB_ROOT_DIR; ?>bibliography/item_import.php" class="headerText2"><?php// echo __('Import Item'); ?></a> </li>--></ul>
		</td></tr></table>
</fieldset>
<fieldset class="menuBox">
<div class="menuBoxInner importIcon">
    <?php echo __('IMPORT TOOL'); ?>
    <p class="only_border">&nbsp;</p>
 <!--comment by iresh on 11/1/2011   <?php //echo __('Import for bibliographics data from CSV file. For guide on CVS fields order and format please refer to documentation or visit <a href="http://senayan.diknas.go.id" target="_blank">Official Website</a>'); ?>-->
<!-- folowing line added by iresh on 11/1/2011-->
<?php //echo __('Import for bibliographics data from CSV file'); ?>
<!--<a href="javascript:void(0)" onclick="openHTMLpop('<?php //echo MODULES_WEB_ROOT_DIR.'bibliography/Master_Table.xls';?>', 500, 500, '<?php //echo MODULES_WEB_ROOT_DIR.'bibliography/Master_Table.xls';?>')">Download Formate</a>-->
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
$str_input .= '</br>';
$form->addAnything(__('File To Import'), $str_input);

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

       $material_options = array('Material Resource Type');
        while ($material_d = $material_q->fetch_row()) 
	
	{
            $material_options[] = array($material_d[0], $material_d[1]);
	
        }

$ajax_exp1 = "ajaxFillSelectnew('".SENAYAN_WEB_ROOT_DIR."admin/modules/bibliography/iframe_import_format.php','materialnewid1', $('materialsubid').getValue())";   

$ajax = "ajaxFillSelect('".SENAYAN_WEB_ROOT_DIR."admin/AJAX_material_sub_type_handler.php', 'mst_gmd', 'gmd_id:gmd_name:material_resource_id', 'gmdID', $('materialresourceid').getValue())";

       if ($rec_d['gmd_name']) 
       {
            $mst_options[] = array($rec_d['gmd_id'],$rec_d['gmd_name']);
       }
	$mst_options[] = array('0', __('Material Type'));


 $ajax_exp = "ajaxFillSelect('".SENAYAN_WEB_ROOT_DIR."admin/AJAX_material_sub_type_handler.php', 'mst_material_sub_type', 'material_sub_id:material_sub_name:gmd_id', 'materialsubid', $('gmdID').getValue())";

 //$ajax_exp = "ajaxFillSelect('".SENAYAN_WEB_ROOT_DIR."admin/AJAX_material_sub_type_handler.php', 'mst_material_sub_type', 'material_sub_id:material_sub_name:gmd_id', 'materialsubid', $('gmdID').getValue())";
       if ($rec_d['material_sub_name']) {
            $mst_material_sub_type_options[] = array($rec_d['material_sub_id'],$rec_d['material_sub_name']);
        }
	$mst_material_sub_type_options[] = array('0', __('Material Sub Type'));
        // string element
       
//$str_input='';       
$str_input = simbio_form_element::selectList('materialresourceid',$material_options, $rec_d['material_resource_id'],'onchange="'.$ajax.'"');
$str_input .= simbio_form_element::selectList('gmdID', $mst_options, $rec_d['gmd_id'],'onchange="'.$ajax_exp.'"');
//$str_input .= simbio_form_element::selectList('gmdID', $mst_options, $rec_d['gmd_id'],'onchange="ccc(this.value);'.$ajax_exp.'"');
$str_input .= '&nbsp;';
$str_input .= simbio_form_element::selectList('materialsubid', $mst_material_sub_type_options, $rec_d['material_sub_id'],  'onchange="'.$ajax_exp1.'"');
$str_input .= '<tr><td id="txtHint" class="alterCell2" colspan="3"></td></tr>';
$str_input .= '<br><tr><td id="materialnewid1" class="alterCell2" colspan="3"></td></tr>';
$form->addAnything(__('Material Type '), $str_input);
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
