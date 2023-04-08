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

/*
A Handler script for AJAX Lookup
Database
Arie Nugraha 2007
*/

if (!defined('SENAYAN_BASE_DIR')) {
    // main system configuration
    require '../sysconfig.inc.php';
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
// list limit
//$limit = 20;
//$table_name = $dbs->escape_string(trim($_POST['tableName']));
//$table_fields = trim($_POST['tableFields']);

if (isset($_POST['keywords']) AND !empty($_POST['keywords'])) {

   $keywords = $dbs->escape_string(urldecode(trim($_POST['keywords'])));
} else {
    $keywords = '';
}

// explode table fields data
//$fields = str_replace(':', ', ', $table_fields);
// set where criteria
$criteria = '';
//$explode=explode(':', $table_fields);
//$array=array_pop($explode);
//foreach (explode(':', $table_fields) as $field) {
 //  $criteria .= " $field LIKE '%$keywords%' OR";
//}
// remove the last OR
//$criteria = substr_replace($criteria, '', -2);
//$criteria.=" $array ='$keywords'";
//$sql_string = "SELECT $fields ";

// append table name
//$sql_string .= " FROM $table_name ";
/*if ($criteria) {
    $sql_string .= " WHERE $criteria LIMIT $limit";
}*/

// send query to database
//$query = $dbs->query($sql_string);
//$error = $dbs->error;
//if ($error) {
  //  die('<option value="0">SQL ERROR : '.$error.'</option>');
//}
 //echo '<option value="0">N/A</option>'."\n";
//if ($query->num_rows > 0) {
 //   while ($row = $query->fetch_row()) {
   //     echo '<option value="'.$row[0].'">'.$row[1].'</option>'."\n";
    //}
  
//} else {
    // output the SQL string
	
   // echo '<option value="0">'.$sql_string.'</option>';
  //  echo '<option value="0">NO DATA FOUND</option>';
$form = new simbio_form_table_AJAX('mainForms', $_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'], 'post');
$form->table_attr = 'align="center" id="dataList"  border=0 cellpadding="5" cellspacing="0"';
$form->table_header_attr = 'class="altermain" style="font-weight: bold;"';
$form->table_content_attr = 'class="altermain2"';
//biblio publication
if($_POST['keywords']==118)
{ 
 $form->addTextField('text', 'publication', __('Publication'), '' , 'rows="1" style="width: 100%;"');
}
//biblio subtitle
if($_POST['keywords']==78 || $_POST['keywords']==112 || $_POST['keywords']==113 || $_POST['keywords']==116 || $_POST['keywords']==117 || $_POST['keywords']==88 || $_POST['keywords']==84 || $_POST['keywords']==85 || $_POST['keywords']==87  || $_POST['keywords']==115)
{ 
 $form->addTextField('text', 'subTitle', __('Sub Title'), '' , 'rows="1" style="width: 100%;"');
}
if($_POST['keywords']==78 || $_POST['keywords']==112 || $_POST['keywords']==113 || $_POST['keywords']==114  || $_POST['keywords']==117 || $_POST['keywords']==77 || $_POST['keywords']==79 || $_POST['keywords']==80 || $_POST['keywords']==81 || $_POST['keywords']==82 || $_POST['keywords']==83 || $_POST['keywords']==88 || $_POST['keywords']==115)
{
   // biblio series title
    $form->addTextField('textarea', 'seriesTitle', __('Series Title'), '' , 'rows="1" style="width: 100%;"');
}
if($_POST['keywords']==78 || $_POST['keywords']==112 || $_POST['keywords']==113 || $_POST['keywords']==88 || $_POST['keywords']==84 || $_POST['keywords']==85 || $_POST['keywords']==87)
{
  // biblio edition
    $form->addTextField('text', 'edition', __('Edition'), '' , 'style="width: 40%;"');
}
if($_POST['keywords']==78 || $_POST['keywords']==112 || $_POST['keywords']==113 || $_POST['keywords']==114 || $_POST['keywords']==115 || $_POST['keywords']==116 || $_POST['keywords']==117 || $_POST['keywords']==118 || $_POST['keywords']==77 || $_POST['keywords']==79 || $_POST['keywords']==80 || $_POST['keywords']==81 || $_POST['keywords']==82 || $_POST['keywords']==83 || $_POST['keywords']==84 || $_POST['keywords']==85 || $_POST['keywords']==86 || $_POST['keywords']==87 || $_POST['keywords']==89 || $_POST['keywords']==90 || $_POST['keywords']==91 || $_POST['keywords']==92 || $_POST['keywords']==93 || $_POST['keywords']==94 || $_POST['keywords']==95 || $_POST['keywords']==96 || $_POST['keywords']==88 || $_POST['keywords']==97 || $_POST['keywords']==98 || $_POST['keywords']==99 || $_POST['keywords']==100 || $_POST['keywords']==101 || $_POST['keywords']==102 || $_POST['keywords']==104 || $_POST['keywords']==105 || $_POST['keywords']==106 || $_POST['keywords']==68 || $_POST['keywords']==69 || $_POST['keywords']==70 || $_POST['keywords']==71 || $_POST['keywords']==72 || $_POST['keywords']==73 || $_POST['keywords']==74 || $_POST['keywords']==75 || $_POST['keywords']==76)
{
       // biblio keywords
    $form->addTextField('text', 'tags', __('Keywords'), '' , 'style="width: 40%;"');
}
if($_POST['keywords']==78 || $_POST['keywords']==112 || $_POST['keywords']==113 || $_POST['keywords']==88)
{
    // biblio specific detail info/area
    $form->addTextField('textarea', 'specDetailInfo', __('Specific Detail Info'), '' , 'rows="2" style="width: 100%"');
}
if($_POST['keywords']==78 || $_POST['keywords']==112 || $_POST['keywords']==113 || $_POST['keywords']==116 || $_POST['keywords']==117 || $_POST['keywords']==77 || $_POST['keywords']==79 || $_POST['keywords']==80 || $_POST['keywords']==81 || $_POST['keywords']==82 || $_POST['keywords']==83 || $_POST['keywords']==88 || $_POST['keywords']==84 || $_POST['keywords']==85 || $_POST['keywords']==87 || $_POST['keywords']==115)
{
    // biblio authors
        $str_input = '<div class="'.$visibility.'"><a class="notAJAX" href="javascript: openHTMLpop(\''.MODULES_WEB_ROOT_DIR.'bibliography/pop_author.php?biblioID=\', 500, 200, \''.__('Authors/Roles').'\')">'.__('Add Author(s)').'</a></div>';
        $str_input .= '<iframe name="authorIframe" id="authorIframe" class="borderAll" style="width: 100%; height: 70px;" src="'.MODULES_WEB_ROOT_DIR.'bibliography/iframe_author.php?&block=1"></iframe>';
    $form->addAnything(__('Author(s)'), $str_input);
}
/*
// biblio editors
        $str_input = '<div class="'.$visibility.'"><a class="notAJAX" href="javascript: openHTMLpop(\''.MODULES_WEB_ROOT_DIR.'bibliography/pop_editor.php?biblioID='.$rec_d['biblio_id'].'\', 500, 200, \''.__('Editor').'\')">'.__('Add Editor(s)').'</a></div>';
        $str_input .= '<iframe name="authorIframe" id="authorIframe" class="borderAll" style="width: 100%; height: 70px;" src="'.MODULES_WEB_ROOT_DIR.'bibliography/iframe_editor.php?biblioID='.$rec_d['biblio_id'].'&block=1"></iframe>';
    $form->addAnything(__('Editor(s)'), $str_input);
*/
if($_POST['keywords']==78 || $_POST['keywords']==112 || $_POST['keywords']==113 || $_POST['keywords']==114 || $_POST['keywords']==118 || $_POST['keywords']==77 || $_POST['keywords']==79 || $_POST['keywords']==80 || $_POST['keywords']==81 || $_POST['keywords']==82 || $_POST['keywords']==83 || $_POST['keywords']==88)
{
// biblio publish frequencies
        // get frequency data related to this record from database
        $freq_q = $dbs->query('SELECT frequency_id, frequency FROM mst_frequency');
        $freq_options[] = array('0', strtoupper(__('Not Applicable')));
        while ($freq_d = $freq_q->fetch_row()) {
            $freq_options[] = array($freq_d[0], $freq_d[1]);
        }
        $str_input = simbio_form_element::selectList('frequencyID', $freq_options, '');
        $str_input .= '&nbsp;';
        $str_input .= ' '.__('Use this for Serial publication');
   	  $form->addAnything(__('Frequency'), $str_input);
}
if($_POST['keywords']==78 || $_POST['keywords']==112 || $_POST['keywords']==113 || $_POST['keywords']==114 || $_POST['keywords']==117 || $_POST['keywords']==77 || $_POST['keywords']==79 || $_POST['keywords']==80 || $_POST['keywords']==81 || $_POST['keywords']==82 || $_POST['keywords']==83 || $_POST['keywords']==88 || $_POST['keywords']==84 || $_POST['keywords']==85 || $_POST['keywords']==87)
{
    // biblio ISBN/ISSN
    $form->addTextField('text', 'isbn_issn', __('ISBN/ISSN'), '' , 'style="width: 40%;"  onchange="return checkspecialcharacterdynamic(this.name);"');
}
//biblio company
if($_POST['keywords']==115)
{
    $form->addTextField('text', 'company', __('Company'), '' , 'style="width: 40%;"');
}
//biblio Key Actors
if($_POST['keywords']==115)
{
    $form->addTextField('text', 'actors', __('Key Actors'), '' , 'style="width: 40%;"');
}
//biblio country
if($_POST['keywords']==115 || $_POST['keywords']==118)
{
    $form->addTextField('text', 'country', __('Country'), '' , 'style="width: 40%;"');
}
//biblio state
if($_POST['keywords']==118)
{
    $form->addTextField('text', 'state', __('State'), '' , 'style="width: 40%;"');
}
//biblio city
if($_POST['keywords']==118)
{
    $form->addTextField('text', 'city', __('City'), '' , 'style="width: 40%;"');
}
//biblio Age Groups
if($_POST['keywords']==115)
{
    $form->addTextField('text', 'age_group', __('Age Groups'), '' , 'style="width: 40%;"');
}
//biblio Awards
if($_POST['keywords']==115)
{
    $form->addTextField('text', 'awards', __('Awards'), '' , 'style="width: 40%;"');
}
//biblio editorial
if($_POST['keywords']==114 || $_POST['keywords']==88)
{
 $form->addTextField('text', 'editorial', __('Editorial'), $rec_d['editorial'], 'style="width: 40%;"');
}
if($_POST['keywords']==78 || $_POST['keywords']==112 || $_POST['keywords']==113  || $_POST['keywords']==116 || $_POST['keywords']==88 || $_POST['keywords']==84 || $_POST['keywords']==85 || $_POST['keywords']==87 || $_POST['keywords']==115)
{    
  // biblio classification
   $form->addTextField('text', 'class', __('Classification No.'), '' , 'style="width: 40%;"');
}
if($_POST['keywords']==78 || $_POST['keywords']==112 || $_POST['keywords']==113 || $_POST['keywords']==114 || $_POST['keywords']==116 || $_POST['keywords']==118 || $_POST['keywords']==77 || $_POST['keywords']==79 || $_POST['keywords']==80 || $_POST['keywords']==81 || $_POST['keywords']==82 || $_POST['keywords']==83 || $_POST['keywords']==88 || $_POST['keywords']==84 || $_POST['keywords']==85 || $_POST['keywords']==87)
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
        $str_input .= simbio_form_element::textField('text', 'publ_search_str', '' , 'style="width: 45%;" onkeyup="'.$ajax_exp.'"');
    $form->addAnything(__('Publisher'), $str_input);
}
if($_POST['keywords']==78 || $_POST['keywords']==112 || $_POST['keywords']==113 || $_POST['keywords']==116 || $_POST['keywords']==88 || $_POST['keywords']==84 || $_POST['keywords']==85 || $_POST['keywords']==87)
{
    // biblio publish year
    $form->addTextField('text', 'year', __('Publishing Year'), '' , 'style="width: 40%;"');
}
if($_POST['keywords']==78 || $_POST['keywords']==112 || $_POST['keywords']==113  || $_POST['keywords']==114 || $_POST['keywords']==116 || $_POST['keywords']==117 || $_POST['keywords']==77 || $_POST['keywords']==79 || $_POST['keywords']==80 || $_POST['keywords']==81 || $_POST['keywords']==82 || $_POST['keywords']==83 || $_POST['keywords']==88 || $_POST['keywords']==84 || $_POST['keywords']==85 || $_POST['keywords']==87)
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
if($_POST['keywords']==116 || $_POST['keywords']==88)
{
$form->addTextField('text', 'qualification', __('Qualification/Degree'), $rec_d['qualification'], 'style="width: 40%;"');
}
// biblio college/inst/dept
if($_POST['keywords']==116 || $_POST['keywords']==88)
{
$form->addTextField('text', 'college_inst_dept', __('College/Inst/Dept'), $rec_d['college_inst_dept'], 'style="width: 40%;"');
}
// biblio university
if($_POST['keywords']==116 || $_POST['keywords']==88)
{
$form->addTextField('text', 'university', __('University'), $rec_d['university'], 'style="width: 40%;"');
}
//biblio volume number
if($_POST['keywords']==114 || $_POST['keywords']==117 || $_POST['keywords']==88 || $_POST['keywords']==84 || $_POST['keywords']==85 || $_POST['keywords']==87)
{
$form->addTextField('text', 'vol_no', __('Volume No.'), $rec_d['vol_no'], 'style="width: 40%;"');
}
//biblio index number
if($_POST['keywords']==114 || $_POST['keywords']==117 || $_POST['keywords']==88 || $_POST['keywords']==84 || $_POST['keywords']==85 || $_POST['keywords']==87)
{
$form->addTextField('text', 'index_no', __('Index No.'), $rec_d['index_no'], 'style="width: 40%;"');
}
//biblio duration
if($_POST['keywords']==114 || $_POST['keywords']==117 || $_POST['keywords']==88 || $_POST['keywords']==115)
{
$form->addTextField('text', 'duration', __('Duration'), $rec_d['duration'], 'style="width: 40%;"');
}
//biblio issue no
if($_POST['keywords']==114 || $_POST['keywords']==88)
{
$form->addTextField('text', 'issue_no', __('Issue No.'), $rec_d['issue_no'], 'style="width: 40%;"');
}
//biblio serial no
if($_POST['keywords']==117 || $_POST['keywords']==88)
{
$form->addTextField('text', 'serial_no', __('Serial No.'), $rec_d['serial_no'], 'style="width: 40%;"');
}
//biblio publication date
if($_POST['keywords']==114  || $_POST['keywords']==117 || $_POST['keywords']==118 || $_POST['keywords']==77 || $_POST['keywords']==79 || $_POST['keywords']==80 || $_POST['keywords']==81 || $_POST['keywords']==82 || $_POST['keywords']==83 || $_POST['keywords']==88)
{
$form->addDateField('pubdate', __('Publication Date'), $rec_d['publication_date']?$rec_d['publication_date']:date('Y-m-d'));
}
if($_POST['keywords']==78 || $_POST['keywords']==112 || $_POST['keywords']==113 || $_POST['keywords']==88 || $_POST['keywords']==84 || $_POST['keywords']==85 || $_POST['keywords']==87)
{    
// biblio collation
    $form->addTextField('text', 'collation', __('Book Size/ Number of page'), '' , 'style="width: 40%;" onchange="return numericcheck(this.name);"');
}
if($_POST['keywords']==78 || $_POST['keywords']==112 || $_POST['keywords']==113 || $_POST['keywords']==88)
{   
    // biblio call_number
    $form->addTextField('text', 'callNumber', __('Call Number'), '' , 'style="width: 40%;"');
}
if($_POST['keywords']==78 || $_POST['keywords']==112 || $_POST['keywords']==113 || $_POST['keywords']==114 || $_POST['keywords']==117 || $_POST['keywords']==118 || $_POST['keywords']==77 || $_POST['keywords']==79 || $_POST['keywords']==80 || $_POST['keywords']==81 || $_POST['keywords']==82 || $_POST['keywords']==83 || $_POST['keywords']==88 || $_POST['keywords']==84 || $_POST['keywords']==85 || $_POST['keywords']==87 || $_POST['keywords']==115)
{ 
    // biblio topics
        $str_input = '<div class="'.$visibility.'"><a class="notAJAX"  href="javascript: openHTMLpop(\''.MODULES_WEB_ROOT_DIR.'bibliography/pop_topic.php?biblioID='.$rec_d['biblio_id'].'\', 500, 200, \''.__('Subjects/Topics').'\')">'.__('Add Subject(s)').'</a></div>';
        $str_input .= '<iframe name="topicIframe" id="topicIframe" class="borderAll" style="width: 100%; height: 70px;" src="'.MODULES_WEB_ROOT_DIR.'bibliography/iframe_topic.php?biblioID='.$rec_d['biblio_id'].'block=1"></iframe>';
    $form->addAnything(__('Subject(s)'), $str_input);
}
if($_POST['keywords']==78 || $_POST['keywords']==112 || $_POST['keywords']==113 || $_POST['keywords']==117 || $_POST['keywords']==88)
{
 // biblio standard
        $str_input = '<div class="'.$visibility.'"><a class="notAJAX"  href="javascript: openHTMLpop(\''.MODULES_WEB_ROOT_DIR.'bibliography/pop_standard.php?biblioID='.$rec_d['biblio_id'].'\', 500, 200, \''.__('Standard').'\')">'.__('Add Standard(s)').'</a></div>';
        $str_input .= '<iframe name="standardIframe" id="standardIframe" class="borderAll" style="width: 100%; height: 70px;" src="'.MODULES_WEB_ROOT_DIR.'bibliography/iframe_standard.php?biblioID='.$rec_d['biblio_id'].'block=1"></iframe>';
    $form->addAnything(__('Standard(s)'), $str_input);
}

 //$form->addTextField('text', 'subject', __('Subject'), $rec_d['subject'], 'style="width: 40%;"');
//  $form->addTextField('text', 'standard', __('Standard'), $rec_d['standard'], 'style="width: 40%;"');
if($_POST['keywords']==78 || $_POST['keywords']==112 || $_POST['keywords']==113 || $_POST['keywords']==114 || $_POST['keywords']==116 || $_POST['keywords']==117 || $_POST['keywords']==118 || $_POST['keywords']==77 || $_POST['keywords']==79 || $_POST['keywords']==80 || $_POST['keywords']==81 || $_POST['keywords']==82 || $_POST['keywords']==83 || $_POST['keywords']==88 || $_POST['keywords']==84 || $_POST['keywords']==85 || $_POST['keywords']==87)
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
if($_POST['keywords']==78 || $_POST['keywords']==112 || $_POST['keywords']==113 || $_POST['keywords']==114 || $_POST['keywords']==117 || $_POST['keywords']==118 || $_POST['keywords']==88 || $_POST['keywords']==84 || $_POST['keywords']==85 || $_POST['keywords']==87 || $_POST['keywords']==115)
{
    // biblio note
    $form->addTextField('textarea', 'notes', __('Notes'), '' , 'style="width: 100%;" rows="2"');
}
if($_POST['keywords']==78 || $_POST['keywords']==112 || $_POST['keywords']==113 || $_POST['keywords']==114 || $_POST['keywords']==117 || $_POST['keywords']==118 || $_POST['keywords']==88 || $_POST['keywords']==84 || $_POST['keywords']==85 || $_POST['keywords']==87 || $_POST['keywords']==115)
{
    // biblio abstract
    $form->addTextField('textarea', 'abstract', __('Abstract'), '' , 'style="width: 100%;" rows="2"');
}

if($_POST['keywords']==78 || $_POST['keywords']==112 || $_POST['keywords']==113 || $_POST['keywords']==118 || $_POST['keywords']==88 || $_POST['keywords']==84 || $_POST['keywords']==85 || $_POST['keywords']==87)
{
 // biblio review
    $form->addTextField('text', 'review', __('Book Review'), '' , 'style="width: 40%;" rows="2"');
}
//biblio academic year number
if($_POST['keywords']==114 || $_POST['keywords']==117 || $_POST['keywords']==88 || $_POST['keywords']==84 || $_POST['keywords']==85 || $_POST['keywords']==87 || $_POST['keywords']==115 || $_POST['keywords']==113)
{
$form->addTextField('text', 'academic_year', __('Academic Year.'), $rec_d['academic_year'], 'style="width: 40%;"');
}

if($_POST['keywords']==78 || $_POST['keywords']==112 || $_POST['keywords']==113 || $_POST['keywords']==88)
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
if($_POST['keywords']==78 || $_POST['keywords']==112 || $_POST['keywords']==113 || $_POST['keywords']==115 || $_POST['keywords']==116 || $_POST['keywords']==77 || $_POST['keywords']==79 || $_POST['keywords']==80 || $_POST['keywords']==81 || $_POST['keywords']==82 || $_POST['keywords']==83 || $_POST['keywords']==84 || $_POST['keywords']==85 || $_POST['keywords']==86 || $_POST['keywords']==87 || $_POST['keywords']==89 || $_POST['keywords']==90 || $_POST['keywords']==91 || $_POST['keywords']==92 || $_POST['keywords']==93 || $_POST['keywords']==94 || $_POST['keywords']==95 || $_POST['keywords']==96 || $_POST['keywords']==88 || $_POST['keywords']==97 || $_POST['keywords']==98 || $_POST['keywords']==99 || $_POST['keywords']==100 || $_POST['keywords']==101 || $_POST['keywords']==102 || $_POST['keywords']==104 || $_POST['keywords']==105 || $_POST['keywords']==106 || $_POST['keywords']==68 || $_POST['keywords']==69 || $_POST['keywords']==70 || $_POST['keywords']==71 || $_POST['keywords']==72 || $_POST['keywords']==73 || $_POST['keywords']==74 || $_POST['keywords']==75 || $_POST['keywords']==76)
{
    // biblio file attachment
    $str_input = '<div class="'.$visibility.'"><a class="notAJAX" href="javascript: openHTMLpop(\''.MODULES_WEB_ROOT_DIR.'bibliography/pop_attach.php?biblioID='.$rec_d['biblio_id'].'\', 600, 300, \''.__('File Attachments').'\')">'.__('Add Attachment').'</a></div>';
    $str_input .= '<iframe name="attachIframe" id="attachIframe" class="borderAll" style="width: 100%; height: 70px;" src="'.MODULES_WEB_ROOT_DIR.'bibliography/iframe_attach.php?biblioID='.$rec_d['biblio_id'].'&block=1"></iframe>';
    $form->addAnything(__('File Attachment'), $str_input);
}
if($_POST['keywords']==78 || $_POST['keywords']==112 || $_POST['keywords']==113 || $_POST['keywords']==114 || $_POST['keywords']==115 || $_POST['keywords']==116 || $_POST['keywords']==117 || $_POST['keywords']==118 || $_POST['keywords']==77 || $_POST['keywords']==79 || $_POST['keywords']==80 || $_POST['keywords']==81 || $_POST['keywords']==82 || $_POST['keywords']==83 || $_POST['keywords']==84 || $_POST['keywords']==85 || $_POST['keywords']==86 || $_POST['keywords']==87 || $_POST['keywords']==89 || $_POST['keywords']==90 || $_POST['keywords']==91 || $_POST['keywords']==92 || $_POST['keywords']==93 || $_POST['keywords']==94 || $_POST['keywords']==95 || $_POST['keywords']==96 || $_POST['keywords']==88 || $_POST['keywords']==97 || $_POST['keywords']==98 || $_POST['keywords']==99 || $_POST['keywords']==100 || $_POST['keywords']==101 || $_POST['keywords']==102 || $_POST['keywords']==104 || $_POST['keywords']==105 || $_POST['keywords']==106 || $_POST['keywords']==68 || $_POST['keywords']==69 || $_POST['keywords']==70 || $_POST['keywords']==71 || $_POST['keywords']==72 || $_POST['keywords']==73 || $_POST['keywords']==74 || $_POST['keywords']==75 || $_POST['keywords']==76)
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
     

echo $form->printOut1();



?>

