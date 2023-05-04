<?php
error_reporting(0);
if (!defined('SENAYAN_BASE_DIR')) {
    // main system configuration
    require '../../../sysconfig.inc.php';
    // start the session
    require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
     
}

$q=$_GET["q"];
$p=$_GET["p"];

//echo $_SESSION["iresh"]=$q;
//echo "<input type=text value='".$_SESSION['iresh']."'>";


require SENAYAN_BASE_DIR.'admin/default/session_check.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/form_maker/simbio_form_table_AJAX.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/paging/simbio_paging.inc.php';
//require SIMBIO_BASE_DIR.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
//require SIMBIO_BASE_DIR.'simbio_DB/simbio_dbop.inc.php';
echo $sql="SELECT material_sub_name FROM mst_material_sub_type WHERE material_sub_id='$q'";
$sql=$dbs->query($sql);
$gmdtitle='';
while($row=$sql->fetch_assoc())
{
	 $gmdtitle.=$row['material_sub_name'];
	
}
$form = new simbio_form_table_AJAX('main', $_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'].'&'.$p, 'post');
//$form->submit_button_attr = ' value=""';
$form->table_attr = 'align="center" id="dataList"  border=0 cellpadding="5" cellspacing="0"';
$form->table_header_attr = 'class="altermain" style="font-weight: bold;"';
$form->table_content_attr = 'class="altermain2"';


if($gmdtitle=='E-Books' || $gmdtitle=='Biographies' )
{
     // biblio sub title
    $form->addTextField('text', 'subTitle', gettext('Sub Title'), $rec_d['sub_title'], 'rows="1" style="width: 100%;"');
   // biblio series title
    $form->addTextField('textarea', 'seriesTitle', gettext('Series Title'), $rec_d['series_title'], 'rows="1" style="width: 100%;"');
  // biblio edition
    $form->addTextField('text', 'edition', gettext('Edition'), $rec_d['edition'], 'style="width: 40%;"');
       // biblio keywords
    $form->addTextField('text', 'tags', gettext('Volume No'), $rec_d['tags'], 'style="width: 40%;"');
    // biblio specific detail info/area
    $form->addTextField('textarea', 'specDetailInfo', gettext('Specific Detail Info'), $rec_d['spec_detail_info'], 'rows="2" style="width: 100%"');
    // biblio authors
        $str_input = '<div class="'.$visibility.'"><a class="notAJAX" href="javascript: openHTMLpop(\''.MODULES_WEB_ROOT_DIR.'bibliography/pop_author.php?biblioID='.$rec_d['biblio_id'].'\', 500, 200, \''.gettext('Authors/Roles').'\')">'.gettext('Add Author(s)').'</a></div>';
        $str_input .= '<iframe name="authorIframe" id="authorIframe" class="borderAll" style="width: 100%; height: 70px;" src="'.MODULES_WEB_ROOT_DIR.'bibliography/iframe_author.php?biblioID='.$rec_d['biblio_id'].'&block=1"></iframe>';
    $form->addAnything(gettext('Author(s)'), $str_input);
/*
// biblio editors
        $str_input = '<div class="'.$visibility.'"><a class="notAJAX" href="javascript: openHTMLpop(\''.MODULES_WEB_ROOT_DIR.'bibliography/pop_editor.php?biblioID='.$rec_d['biblio_id'].'\', 500, 200, \''.gettext('Editor').'\')">'.gettext('Add Editor(s)').'</a></div>';
        $str_input .= '<iframe name="authorIframe" id="authorIframe" class="borderAll" style="width: 100%; height: 70px;" src="'.MODULES_WEB_ROOT_DIR.'bibliography/iframe_editor.php?biblioID='.$rec_d['biblio_id'].'&block=1"></iframe>';
    $form->addAnything(gettext('Editor(s)'), $str_input);
*/
// biblio publish frequencies
        // get frequency data related to this record from database
        $freq_q = $dbs->query('SELECT frequency_id, frequency FROM mst_frequency');
        $freq_options[] = array('0', strtoupper(gettext('Not Applicable')));
        while ($freq_d = $freq_q->fetch_row()) {
            $freq_options[] = array($freq_d[0], $freq_d[1]);
        }
        $str_input = simbio_form_element::selectList('frequencyID', $freq_options, $rec_d['frequency_id']);
        $str_input .= '&nbsp;';
        $str_input .= ' '.gettext('Use this for Serial publication');
   	  $form->addAnything(gettext('Frequency'), $str_input);
    // biblio ISBN/ISSN
    $form->addTextField('text', 'isbn_issn', gettext('ISBN/ISSN'), $rec_d['isbn_issn'], 'style="width: 40%;"');
    
  // biblio classification
   $form->addTextField('text', 'class', gettext('Classification No.'), $rec_d['classification'], 'style="width: 40%;"');
    // biblio publisher
        // AJAX expression
        $ajax_exp = "ajaxFillSelect('".SENAYAN_WEB_ROOT_DIR."admin/AJAX_lookup_handler.php', 'mst_publisher', 'publisher_id:publisher_name', 'publisherID', $('publ_search_str').getValue())";
        if ($rec_d['publisher_name']) {
            $publ_options[] = array($rec_d['publisher_id'], $rec_d['publisher_name']);
        }
        $publ_options[] = array('0', gettext('Publisher'));
        // string element
        $str_input = simbio_form_element::selectList('publisherID', $publ_options, '', 'style="width: 50%;"');
        $str_input .= '&nbsp;';
        $str_input .= simbio_form_element::textField('text', 'publ_search_str', $rec_d['publisher_name'], 'style="width: 45%;" onkeyup="'.$ajax_exp.'"');
    $form->addAnything(gettext('Publisher'), $str_input);
    // biblio publish year
    $form->addTextField('text', 'year', gettext('Publishing Year'), $rec_d['publish_year'], 'style="width: 40%;"');
    // biblio publish place
        // AJAX expression
        $ajax_exp = "ajaxFillSelect('".SENAYAN_WEB_ROOT_DIR."admin/AJAX_lookup_handler.php', 'mst_place', 'place_id:place_name', 'placeID', $('plc_search_str').getValue())";
        // string element
        if ($rec_d['place_name']) {
            $plc_options[] = array($rec_d['publish_place_id'], $rec_d['place_name']);
        }
        $plc_options[] = array('0', gettext('Publishing Place'));
        $str_input = simbio_form_element::selectList('placeID', $plc_options, '', 'style="width: 50%;"');
        $str_input .= '&nbsp;';
        $str_input .= simbio_form_element::textField('text', 'plc_search_str', $rec_d['place_name'], 'style="width: 45%;" onkeyup="'.$ajax_exp.'"');
    $form->addAnything(gettext('Publishing Place'), $str_input);
    // biblio collation
    $form->addTextField('text', 'collation', gettext('Book Size/ Number of page'), $rec_d['collation'], 'style="width: 40%;"');
   
    // biblio call_number
    $form->addTextField('text', 'callNumber', gettext('Call Number'), $rec_d['call_number'], 'style="width: 40%;"');
 
    // biblio topics
        $str_input = '<div class="'.$visibility.'"><a class="notAJAX"  href="javascript: openHTMLpop(\''.MODULES_WEB_ROOT_DIR.'bibliography/pop_topic.php?biblioID='.$rec_d['biblio_id'].'\', 500, 200, \''.gettext('Subjects/Topics').'\')">'.gettext('Add Subject(s)').'</a></div>';
        $str_input .= '<iframe name="topicIframe" id="topicIframe" class="borderAll" style="width: 100%; height: 70px;" src="'.MODULES_WEB_ROOT_DIR.'bibliography/iframe_topic.php?biblioID='.$rec_d['biblio_id'].'&block=1"></iframe>';
    $form->addAnything(gettext('Subject(s)'), $str_input);

 // biblio standard
        $str_input = '<div class="'.$visibility.'"><a class="notAJAX"  href="javascript: openHTMLpop(\''.MODULES_WEB_ROOT_DIR.'bibliography/pop_standard.php?biblioID='.$rec_d['biblio_id'].'\', 500, 200, \''.gettext('Standard').'\')">'.gettext('Add Standard(s)').'</a></div>';
        $str_input .= '<iframe name="standardIframe" id="standardIframe" class="borderAll" style="width: 100%; height: 70px;" src="'.MODULES_WEB_ROOT_DIR.'bibliography/iframe_standard.php?biblioID='.$rec_d['biblio_id'].'&block=1"></iframe>';
    $form->addAnything(gettext('Standard(s)'), $str_input);


 //$form->addTextField('text', 'subject', gettext('Subject'), $rec_d['subject'], 'style="width: 40%;"');
//  $form->addTextField('text', 'standard', gettext('Standard'), $rec_d['standard'], 'style="width: 40%;"');

    // biblio language
        // get language data related to this record from database
        $lang_q = $dbs->query("SELECT language_id, language_name FROM mst_language");
        $lang_options = array();
        while ($lang_d = $lang_q->fetch_row()) {
            $lang_options[] = array($lang_d[0], $lang_d[1]);
        }
    $form->addSelectList('languageID[]', gettext('Language'), $lang_options, $rec_d['language_id'],'multiple="multiple" size=5');
     $str_input = simbio_form_element::textField('text', 'price', !empty($rec_d['price'])?$rec_d['price']:'0', 'style="width: 40%;"');
    $str_input .= simbio_form_element::selectList('priceCurrency', $sysconf['currencies'], $rec_d['price_currency']);;
    $form->addAnything(gettext('Price'), $str_input);
    // biblio note
    $form->addTextField('textarea', 'notes', gettext('Abstract/Notes'), $rec_d['notes'], 'style="width: 100%;" rows="2"');
 // biblio review
    $form->addTextField('text', 'review', gettext('Book Review'), $rec_d['review'], 'style="width: 40%;" rows="2"');
    // biblio cover image
    if (!trim($rec_d['image'])) {
        $str_input = simbio_form_element::textField('file', 'image');
        $str_input .= ' Maximum '.$sysconf['max_image_upload'].' KB';
        $form->addAnything(gettext('Image'), $str_input);
    } else {
        $str_input = '<a href="'.SENAYAN_WEB_ROOT_DIR.'images/docs/'.$rec_d['image'].'" target="_blank"><strong>'.$rec_d['image'].'</strong></a><br />';
        $str_input .= simbio_form_element::textField('file', 'image');
        $str_input .= ' Maximum '.$sysconf['max_image_upload'].' KB';
        $form->addAnything(gettext('Image'), $str_input);
    }
    // biblio file attachment
    $str_input = '<div class="'.$visibility.'"><a class="notAJAX" href="javascript: openHTMLpop(\''.MODULES_WEB_ROOT_DIR.'bibliography/pop_attach.php?biblioID='.$rec_d['biblio_id'].'\', 600, 300, \''.gettext('File Attachments').'\')">'.gettext('Add Attachment').'</a></div>';
    $str_input .= '<iframe name="attachIframe" id="attachIframe" class="borderAll" style="width: 100%; height: 70px;" src="'.MODULES_WEB_ROOT_DIR.'bibliography/iframe_attach.php?biblioID='.$rec_d['biblio_id'].'&block=1"></iframe>';
    $form->addAnything(gettext('File Attachment'), $str_input);
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
if($gmdtitle=='Online newspaper')
{

       // biblio keywords
    $form->addTextField('text', 'tags', gettext('Keywords'), $rec_d['tags'], 'style="width: 40%;"');
// biblio publish frequencies
        // get frequency data related to this record from database
        $freq_q = $dbs->query('SELECT frequency_id, frequency FROM mst_frequency');
        $freq_options[] = array('0', strtoupper(gettext('Not Applicable')));
        while ($freq_d = $freq_q->fetch_row()) {
            $freq_options[] = array($freq_d[0], $freq_d[1]);
        }
        $str_input = simbio_form_element::selectList('frequencyID', $freq_options, $rec_d['frequency_id']);
        $str_input .= '&nbsp;';
        $str_input .= ' '.gettext('Use this for Serial publication');
   	  $form->addAnything(gettext('Frequency'), $str_input);
// biblio language
        // get language data related to this record from database
        $lang_q = $dbs->query("SELECT language_id, language_name FROM mst_language");
        $lang_options = array();
        while ($lang_d = $lang_q->fetch_row()) {
            $lang_options[] = array($lang_d[0], $lang_d[1]);
        }
    $form->addSelectList('languageID[]', gettext('Language'), $lang_options, $rec_d['language_id'],'multiple="multiple" size=5');

// biblio topics
        $str_input = '<div class="'.$visibility.'"><a class="notAJAX"  href="javascript: openHTMLpop(\''.MODULES_WEB_ROOT_DIR.'bibliography/pop_topic.php?biblioID='.$rec_d['biblio_id'].'\', 500, 200, \''.gettext('Subjects/Topics').'\')">'.gettext('Add Subject(s)').'</a></div>';
        $str_input .= '<iframe name="topicIframe" id="topicIframe" class="borderAll" style="width: 100%; height: 70px;" src="'.MODULES_WEB_ROOT_DIR.'bibliography/iframe_topic.php?biblioID='.$rec_d['biblio_id'].'&block=1"></iframe>';
    $form->addAnything(gettext('Subject(s)'), $str_input);

 //$form->addTextField('text', 'subject', gettext('Subject'), $rec_d['subject'], 'style="width: 40%;"');
  // biblio publisher
        // AJAX expression
        $ajax_exp = "ajaxFillSelect('".SENAYAN_WEB_ROOT_DIR."admin/AJAX_lookup_handler.php', 'mst_publisher', 'publisher_id:publisher_name', 'publisherID', $('publ_search_str').getValue())";
        if ($rec_d['publisher_name']) {
            $publ_options[] = array($rec_d['publisher_id'], $rec_d['publisher_name']);
        }
        $publ_options[] = array('0', gettext('Publisher'));
        // string element
        $str_input = simbio_form_element::selectList('publisherID', $publ_options, '', 'style="width: 50%;"');
        $str_input .= '&nbsp;';
        $str_input .= simbio_form_element::textField('text', 'publ_search_str', $rec_d['publisher_name'], 'style="width: 45%;" onkeyup="'.$ajax_exp.'"');
    $form->addAnything(gettext('Publisher'), $str_input);
// biblio publication date
     
  //  $form->addDateField('pubdate', gettext('Publication Date'), $rec_d['publication_date']?$rec_d['publication_date']:date('Y-m-d'));
 // biblio Country
    $form->addTextField('text', 'country', gettext('Country'), $rec_d['country'], 'style="width: 40%;"');
 // biblio State
    $form->addTextField('text', 'state', gettext('State'), $rec_d['state'], 'style="width: 40%;"');
 // biblio city
    $form->addTextField('text', 'city', gettext('City'), $rec_d['city'], 'style="width: 40%;"');
 // biblio note
    $form->addTextField('textarea', 'notes', gettext('Abstract/Notes'), $rec_d['notes'], 'style="width: 100%;" rows="2"');
  // biblio review
    $form->addTextField('text', 'review', gettext('Review'), $rec_d['review'], 'style="width: 40%;" rows="2"');
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
if($gmdtitle=='Online journle')
{
// biblio series title
    $form->addTextField('textarea', 'seriesTitle', gettext('Series Title'), $rec_d['series_title'], 'rows="1" style="width: 100%;"');
 
       // biblio keywords
    $form->addTextField('text', 'tags', gettext('Keywords'), $rec_d['tags'], 'style="width: 40%;"');
  
    
// biblio publish frequencies
        // get frequency data related to this record from database
        $freq_q = $dbs->query('SELECT frequency_id, frequency FROM mst_frequency');
        $freq_options[] = array('0', strtoupper(gettext('Not Applicable')));
        while ($freq_d = $freq_q->fetch_row()) {
            $freq_options[] = array($freq_d[0], $freq_d[1]);
        }
        $str_input = simbio_form_element::selectList('frequencyID', $freq_options, $rec_d['frequency_id']);
        $str_input .= '&nbsp;';
        $str_input .= ' '.gettext('Use this for Serial publication');
   	  $form->addAnything(gettext('Frequency'), $str_input);
    // biblio ISBN/ISSN
    $form->addTextField('text', 'isbn_issn', gettext('ISBN/ISSN'), $rec_d['isbn_issn'], 'style="width: 40%;"');
    // biblio Editorial
    $form->addTextField('text', 'editorial', gettext('Editorial'), $rec_d['editorial'], 'style="width: 40%;"');
    // biblio classification
   // $form->addTextField('text', 'class', gettext('Classification'), $rec_d['classification'], 'style="width: 40%;"');
    // biblio publisher
        // AJAX expression
        $ajax_exp = "ajaxFillSelect('".SENAYAN_WEB_ROOT_DIR."admin/AJAX_lookup_handler.php', 'mst_publisher', 'publisher_id:publisher_name', 'publisherID', $('publ_search_str').getValue())";
        if ($rec_d['publisher_name']) {
            $publ_options[] = array($rec_d['publisher_id'], $rec_d['publisher_name']);
        }
        $publ_options[] = array('0', gettext('Publisher'));
        // string element
        $str_input = simbio_form_element::selectList('publisherID', $publ_options, '', 'style="width: 50%;"');
        $str_input .= '&nbsp;';
        $str_input .= simbio_form_element::textField('text', 'publ_search_str', $rec_d['publisher_name'], 'style="width: 45%;" onkeyup="'.$ajax_exp.'"');
    $form->addAnything(gettext('Publisher'), $str_input);
    
    // biblio publish place
        // AJAX expression
        $ajax_exp = "ajaxFillSelect('".SENAYAN_WEB_ROOT_DIR."admin/AJAX_lookup_handler.php', 'mst_place', 'place_id:place_name', 'placeID', $('plc_search_str').getValue())";
        // string element
        if ($rec_d['place_name']) {
            $plc_options[] = array($rec_d['publish_place_id'], $rec_d['place_name']);
        }
        $plc_options[] = array('0', gettext('Publishing Place'));
        $str_input = simbio_form_element::selectList('placeID', $plc_options, '', 'style="width: 50%;"');
        $str_input .= '&nbsp;';
        $str_input .= simbio_form_element::textField('text', 'plc_search_str', $rec_d['place_name'], 'style="width: 45%;" onkeyup="'.$ajax_exp.'"');
    $form->addAnything(gettext('Publishing Place'), $str_input);
    // biblio volume number
    $form->addTextField('text', 'vol_no', gettext('Volume No.'), $rec_d['vol_no'], 'style="width: 40%;"');
    // biblio issue number
    $form->addTextField('text', 'issue_no', gettext('Issue No.'), $rec_d['issue_no'], 'style="width: 40%;"');
    // biblio publication date
     
    $form->addDateField('pubdate', gettext('Publication Date'), $rec_d['publication_date']?$rec_d['publication_date']:date('Y-m-d'));
  
 // biblio topics
        $str_input = '<div class="'.$visibility.'"><a class="notAJAX"  href="javascript: openHTMLpop(\''.MODULES_WEB_ROOT_DIR.'bibliography/pop_topic.php?biblioID='.$rec_d['biblio_id'].'\', 500, 200, \''.gettext('Subjects/Topics').'\')">'.gettext('Add Subject(s)').'</a></div>';
        $str_input .= '<iframe name="topicIframe" id="topicIframe" class="borderAll" style="width: 100%; height: 70px;" src="'.MODULES_WEB_ROOT_DIR.'bibliography/iframe_topic.php?biblioID='.$rec_d['biblio_id'].'&block=1"></iframe>';
    $form->addAnything(gettext('Subject(s)'), $str_input);

 
// $form->addTextField('text', 'subject', gettext('Subject'), $rec_d['subject'], 'style="width: 40%;"');
  



    // biblio language
        // get language data related to this record from database
        $lang_q = $dbs->query("SELECT language_id, language_name FROM mst_language");
        $lang_options = array();
        while ($lang_d = $lang_q->fetch_row()) {
            $lang_options[] = array($lang_d[0], $lang_d[1]);
        }
    $form->addSelectList('languageID[]', gettext('Language'), $lang_options, $rec_d['language_id'],'multiple="multiple" size=5');
     
    $form->addTextField('textarea', 'notes', gettext('Abstract/Notes'), $rec_d['notes'], 'style="width: 100%;" rows="2"');
  
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
if($gmdtitle=='Serials')
{
// biblio sub title
    $form->addTextField('text', 'subTitle', gettext('Sub Title'), $rec_d['sub_title'], 'rows="1" style="width: 100%;"');
    // biblio keywords
    $form->addTextField('text', 'tags', gettext('Keywords'), $rec_d['tags'], 'style="width: 40%;"');
 // biblio authors
        $str_input = '<div class="'.$visibility.'"><a class="notAJAX" href="javascript: openHTMLpop(\''.MODULES_WEB_ROOT_DIR.'bibliography/pop_author.php?biblioID='.$rec_d['biblio_id'].'\', 500, 200, \''.gettext('Authors/Roles').'\')">'.gettext('Add Author(s)').'</a></div>';
        $str_input .= '<iframe name="authorIframe" id="authorIframe" class="borderAll" style="width: 100%; height: 70px;" src="'.MODULES_WEB_ROOT_DIR.'bibliography/iframe_author.php?biblioID='.$rec_d['biblio_id'].'&block=1"></iframe>';
    $form->addAnything(gettext('Author(s)'), $str_input);
// biblio publish frequencies
        // get frequency data related to this record from database
        $freq_q = $dbs->query('SELECT frequency_id, frequency FROM mst_frequency');
        $freq_options[] = array('0', strtoupper(gettext('Not Applicable')));
        while ($freq_d = $freq_q->fetch_row()) {
            $freq_options[] = array($freq_d[0], $freq_d[1]);
        }
        $str_input = simbio_form_element::selectList('frequencyID', $freq_options, $rec_d['frequency_id']);
        $str_input .= '&nbsp;';
        $str_input .= ' '.gettext('Use this for Serial publication');
   	  $form->addAnything(gettext('Frequency'), $str_input);
// biblio ISBN/ISSN
    $form->addTextField('text', 'isbn_issn', gettext('ISBN/ISSN'), $rec_d['isbn_issn'], 'style="width: 40%;"');
// biblio publisher
        // AJAX expression
        $ajax_exp = "ajaxFillSelect('".SENAYAN_WEB_ROOT_DIR."admin/AJAX_lookup_handler.php', 'mst_publisher', 'publisher_id:publisher_name', 'publisherID', $('publ_search_str').getValue())";
        if ($rec_d['publisher_name']) {
            $publ_options[] = array($rec_d['publisher_id'], $rec_d['publisher_name']);
        }
        $publ_options[] = array('0', gettext('Publisher'));
        // string element
        $str_input = simbio_form_element::selectList('publisherID', $publ_options, '', 'style="width: 50%;"');
        $str_input .= '&nbsp;';
        $str_input .= simbio_form_element::textField('text', 'publ_search_str', $rec_d['publisher_name'], 'style="width: 45%;" onkeyup="'.$ajax_exp.'"');
    $form->addAnything(gettext('Publisher'), $str_input);
    
    // biblio publish place
        // AJAX expression
        $ajax_exp = "ajaxFillSelect('".SENAYAN_WEB_ROOT_DIR."admin/AJAX_lookup_handler.php', 'mst_place', 'place_id:place_name', 'placeID', $('plc_search_str').getValue())";
        // string element
        if ($rec_d['place_name']) {
            $plc_options[] = array($rec_d['publish_place_id'], $rec_d['place_name']);
        }
        $plc_options[] = array('0', gettext('Publishing Place'));
        $str_input = simbio_form_element::selectList('placeID', $plc_options, '', 'style="width: 50%;"');
        $str_input .= '&nbsp;';
        $str_input .= simbio_form_element::textField('text', 'plc_search_str', $rec_d['place_name'], 'style="width: 45%;" onkeyup="'.$ajax_exp.'"');
    $form->addAnything(gettext('Publishing Place'), $str_input);
 // biblio publication date
     
    $form->addDateField('pubdate', gettext('Publication Date'), $rec_d['publication_date']?$rec_d['publication_date']:date('Y-m-d'));
 // biblio topics
        $str_input = '<div class="'.$visibility.'"><a class="notAJAX"  href="javascript: openHTMLpop(\''.MODULES_WEB_ROOT_DIR.'bibliography/pop_topic.php?biblioID='.$rec_d['biblio_id'].'\', 500, 200, \''.gettext('Subjects/Topics').'\')">'.gettext('Add Subject(s)').'</a></div>';
        $str_input .= '<iframe name="topicIframe" id="topicIframe" class="borderAll" style="width: 100%; height: 70px;" src="'.MODULES_WEB_ROOT_DIR.'bibliography/iframe_topic.php?biblioID='.$rec_d['biblio_id'].'&block=1"></iframe>';
    $form->addAnything(gettext('Subject(s)'), $str_input);
 // biblio volume number
    $form->addTextField('text', 'vol_no', gettext('Volume No.'), $rec_d['vol_no'], 'style="width: 40%;"');
    // biblio serial number
    $form->addTextField('text', 'serial_no', gettext('Serial No.'), $rec_d['serial_no'], 'style="width: 40%;"');
// biblio language
        // get language data related to this record from database
        $lang_q = $dbs->query("SELECT language_id, language_name FROM mst_language");
        $lang_options = array();
        while ($lang_d = $lang_q->fetch_row()) {
            $lang_options[] = array($lang_d[0], $lang_d[1]);
        }
    $form->addSelectList('languageID[]', gettext('Language'), $lang_options, $rec_d['language_id'],'multiple="multiple" size=5');
   // biblio note
    $form->addTextField('textarea', 'notes', gettext('Abstract/Notes'), $rec_d['notes'], 'style="width: 100%;" rows="2"');
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
if($gmdtitle=='Audiobook/podcast/speech/lecture by popular authors,leaders' || $gmdtitle=='Video/tv/documentarios/history/war/science/friction/scfric/animation')
{
// biblio sub title
    $form->addTextField('text', 'subTitle', gettext('Sub Title'), $rec_d['sub_title'], 'rows="1" style="width: 100%;"');
    // biblio keywords
    $form->addTextField('text', 'tags', gettext('Keywords'), $rec_d['tags'], 'style="width: 40%;"');
   // biblio duration
    $form->addTextField('text', 'duration', gettext('Duration'), $rec_d['duration'], 'style="width: 40%;"');
 // biblio authors
        $str_input = '<div class="'.$visibility.'"><a class="notAJAX" href="javascript: openHTMLpop(\''.MODULES_WEB_ROOT_DIR.'bibliography/pop_author.php?biblioID='.$rec_d['biblio_id'].'\', 500, 200, \''.gettext('Authors/Roles').'\')">'.gettext('Add Author(s)').'</a></div>';
        $str_input .= '<iframe name="authorIframe" id="authorIframe" class="borderAll" style="width: 100%; height: 70px;" src="'.MODULES_WEB_ROOT_DIR.'bibliography/iframe_author.php?biblioID='.$rec_d['biblio_id'].'&block=1"></iframe>';
    $form->addAnything(gettext('Author(s)'), $str_input);
 // biblio company
   $form->addTextField('text', 'company', gettext('Company'), $rec_d['company'], 'style="width: 40%;"');
// biblio actors
   $form->addTextField('text', 'actors', gettext('Key Actors'), $rec_d['actors'], 'style="width: 40%;"');
 // biblio classification
   $form->addTextField('text', 'class', gettext('Classification No.'), $rec_d['classification'], 'style="width: 40%;"');

// biblio publish year
    $form->addTextField('text', 'year', gettext('Year'), $rec_d['publish_year'], 'style="width: 40%;"');
 // biblio topics
        $str_input = '<div class="'.$visibility.'"><a class="notAJAX"  href="javascript: openHTMLpop(\''.MODULES_WEB_ROOT_DIR.'bibliography/pop_topic.php?biblioID='.$rec_d['biblio_id'].'\', 500, 200, \''.gettext('Subjects/Topics').'\')">'.gettext('Add Subject(s)').'</a></div>';
        $str_input .= '<iframe name="topicIframe" id="topicIframe" class="borderAll" style="width: 100%; height: 70px;" src="'.MODULES_WEB_ROOT_DIR.'bibliography/iframe_topic.php?biblioID='.$rec_d['biblio_id'].'&block=1"></iframe>';
    $form->addAnything(gettext('Subject(s)'), $str_input);
// biblio Country
    $form->addTextField('text', 'country', gettext('Country'), $rec_d['country'], 'style="width: 40%;"');
    // biblio series title
    $form->addTextField('textarea', 'seriesTitle', gettext('Series'), $rec_d['series_title'], 'rows="1" style="width: 100%;"');
// biblio age group
   $form->addTextField('text', 'age_group', gettext('Age Group'), $rec_d['age_group'], 'style="width: 40%;"');
// biblio awards
   $form->addTextField('text', 'awards', gettext('Awards'), $rec_d['awards'], 'style="width: 40%;"');
// biblio language
        // get language data related to this record from database
        $lang_q = $dbs->query("SELECT language_id, language_name FROM mst_language");
        $lang_options = array();
        while ($lang_d = $lang_q->fetch_row()) {
            $lang_options[] = array($lang_d[0], $lang_d[1]);
        }
    $form->addSelectList('languageID[]', gettext('Language'), $lang_options, $rec_d['language_id'],'multiple="multiple" size=5');
   // biblio note
    $form->addTextField('textarea', 'notes', gettext('Abstract/Notes'), $rec_d['notes'], 'style="width: 100%;" rows="2"');
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
if($gmdtitle=='Reports')
{

// biblio sub title
    $form->addTextField('text', 'subTitle', gettext('Sub Title'), $rec_d['sub_title'], 'rows="1" style="width: 100%;"');
    // biblio keywords
    $form->addTextField('text', 'tags', gettext('Keywords'), $rec_d['tags'], 'style="width: 40%;"');

 // biblio authors
        $str_input = '<div class="'.$visibility.'"><a class="notAJAX" href="javascript: openHTMLpop(\''.MODULES_WEB_ROOT_DIR.'bibliography/pop_author.php?biblioID='.$rec_d['biblio_id'].'\', 500, 200, \''.gettext('Authors/Roles').'\')">'.gettext('Add Author(s)').'</a></div>';
        $str_input .= '<iframe name="authorIframe" id="authorIframe" class="borderAll" style="width: 100%; height: 70px;" src="'.MODULES_WEB_ROOT_DIR.'bibliography/iframe_author.php?biblioID='.$rec_d['biblio_id'].'&block=1"></iframe>';
    $form->addAnything(gettext('Author(s)'), $str_input);

 // biblio classification
   $form->addTextField('text', 'class', gettext('Classification No.'), $rec_d['classification'], 'style="width: 40%;"');
// biblio publisher
        // AJAX expression
        $ajax_exp = "ajaxFillSelect('".SENAYAN_WEB_ROOT_DIR."admin/AJAX_lookup_handler.php', 'mst_publisher', 'publisher_id:publisher_name', 'publisherID', $('publ_search_str').getValue())";
        if ($rec_d['publisher_name']) {
            $publ_options[] = array($rec_d['publisher_id'], $rec_d['publisher_name']);
        }
        $publ_options[] = array('0', gettext('Publisher'));
        // string element
        $str_input = simbio_form_element::selectList('publisherID', $publ_options, '', 'style="width: 50%;"');
        $str_input .= '&nbsp;';
        $str_input .= simbio_form_element::textField('text', 'publ_search_str', $rec_d['publisher_name'], 'style="width: 45%;" onkeyup="'.$ajax_exp.'"');
    $form->addAnything(gettext('Publisher'), $str_input);
    
    // biblio publish place
        // AJAX expression
        $ajax_exp = "ajaxFillSelect('".SENAYAN_WEB_ROOT_DIR."admin/AJAX_lookup_handler.php', 'mst_place', 'place_id:place_name', 'placeID', $('plc_search_str').getValue())";
        // string element
        if ($rec_d['place_name']) {
            $plc_options[] = array($rec_d['publish_place_id'], $rec_d['place_name']);
        }
        $plc_options[] = array('0', gettext('Publishing Place'));
        $str_input = simbio_form_element::selectList('placeID', $plc_options, '', 'style="width: 50%;"');
        $str_input .= '&nbsp;';
        $str_input .= simbio_form_element::textField('text', 'plc_search_str', $rec_d['place_name'], 'style="width: 45%;" onkeyup="'.$ajax_exp.'"');
    $form->addAnything(gettext('Publishing Place'), $str_input);
// biblio publish year
    $form->addTextField('text', 'year', gettext('Year'), $rec_d['publish_year'], 'style="width: 40%;"');
 // biblio topics
        $str_input = '<div class="'.$visibility.'"><a class="notAJAX"  href="javascript: openHTMLpop(\''.MODULES_WEB_ROOT_DIR.'bibliography/pop_topic.php?biblioID='.$rec_d['biblio_id'].'\', 500, 200, \''.gettext('Subjects/Topics').'\')">'.gettext('Add Subject(s)').'</a></div>';
        $str_input .= '<iframe name="topicIframe" id="topicIframe" class="borderAll" style="width: 100%; height: 70px;" src="'.MODULES_WEB_ROOT_DIR.'bibliography/iframe_topic.php?biblioID='.$rec_d['biblio_id'].'&block=1"></iframe>';
    $form->addAnything(gettext('Subject(s)'), $str_input);

// biblio qualification/degree
   $form->addTextField('text', 'qualification', gettext('Qualification/Degree'), $rec_d['qualification'], 'style="width: 40%;"');
// biblio college/inst/dept
   $form->addTextField('text', 'college_inst_dept', gettext('College/Inst/Dept'), $rec_d['college_inst_dept'], 'style="width: 40%;"');
// biblio university
   $form->addTextField('text', 'university', gettext('University'), $rec_d['university'], 'style="width: 40%;"');
// biblio language
        // get language data related to this record from database
        $lang_q = $dbs->query("SELECT language_id, language_name FROM mst_language");
        $lang_options = array();
        while ($lang_d = $lang_q->fetch_row()) {
            $lang_options[] = array($lang_d[0], $lang_d[1]);
        }
    $form->addSelectList('languageID[]', gettext('Language'), $lang_options, $rec_d['language_id'],'multiple="multiple" size=5');
   // biblio note
    $form->addTextField('textarea', 'notes', gettext('Abstract/Notes'), $rec_d['notes'], 'style="width: 100%;" rows="2"');



}
if($gmdtitle=='Article')
{
// biblio sub title
    $form->addTextField('text', 'subTitle', gettext('Sub Title'), $rec_d['sub_title'], 'rows="1" style="width: 100%;"');
    // biblio keywords
    $form->addTextField('text', 'tags', gettext('Keywords'), $rec_d['tags'], 'style="width: 40%;"');
 // biblio series title
    $form->addTextField('textarea', 'seriesTitle', gettext('Jouranl/Serial'), $rec_d['series_title'], 'rows="1" style="width: 100%;"');
 // biblio authors
        $str_input = '<div class="'.$visibility.'"><a class="notAJAX" href="javascript: openHTMLpop(\''.MODULES_WEB_ROOT_DIR.'bibliography/pop_author.php?biblioID='.$rec_d['biblio_id'].'\', 500, 200, \''.gettext('Authors/Roles').'\')">'.gettext('Add Author(s)').'</a></div>';
        $str_input .= '<iframe name="authorIframe" id="authorIframe" class="borderAll" style="width: 100%; height: 70px;" src="'.MODULES_WEB_ROOT_DIR.'bibliography/iframe_author.php?biblioID='.$rec_d['biblio_id'].'&block=1"></iframe>';
    $form->addAnything(gettext('Author(s)'), $str_input);

// biblio ISBN/ISSN
    $form->addTextField('text', 'isbn_issn', gettext('ISBN/ISSN'), $rec_d['isbn_issn'], 'style="width: 40%;"');
 // biblio topics
        $str_input = '<div class="'.$visibility.'"><a class="notAJAX"  href="javascript: openHTMLpop(\''.MODULES_WEB_ROOT_DIR.'bibliography/pop_topic.php?biblioID='.$rec_d['biblio_id'].'\', 500, 200, \''.gettext('Subjects/Topics').'\')">'.gettext('Add Subject(s)').'</a></div>';
        $str_input .= '<iframe name="topicIframe" id="topicIframe" class="borderAll" style="width: 100%; height: 70px;" src="'.MODULES_WEB_ROOT_DIR.'bibliography/iframe_topic.php?biblioID='.$rec_d['biblio_id'].'&block=1"></iframe>';
    $form->addAnything(gettext('Subject(s)'), $str_input);
// biblio standard
        $str_input = '<div class="'.$visibility.'"><a class="notAJAX"  href="javascript: openHTMLpop(\''.MODULES_WEB_ROOT_DIR.'bibliography/pop_standard.php?biblioID='.$rec_d['biblio_id'].'\', 500, 200, \''.gettext('Standard').'\')">'.gettext('Add Standard(s)').'</a></div>';
        $str_input .= '<iframe name="standardIframe" id="standardIframe" class="borderAll" style="width: 100%; height: 70px;" src="'.MODULES_WEB_ROOT_DIR.'bibliography/iframe_standard.php?biblioID='.$rec_d['biblio_id'].'&block=1"></iframe>';
    $form->addAnything(gettext('Academic Year'), $str_input);
    
    // biblio publish place
        // AJAX expression
        $ajax_exp = "ajaxFillSelect('".SENAYAN_WEB_ROOT_DIR."admin/AJAX_lookup_handler.php', 'mst_place', 'place_id:place_name', 'placeID', $('plc_search_str').getValue())";
        // string element
        if ($rec_d['place_name']) {
            $plc_options[] = array($rec_d['publish_place_id'], $rec_d['place_name']);
        }
        $plc_options[] = array('0', gettext('Publishing Place'));
        $str_input = simbio_form_element::selectList('placeID', $plc_options, '', 'style="width: 50%;"');
        $str_input .= '&nbsp;';
        $str_input .= simbio_form_element::textField('text', 'plc_search_str', $rec_d['place_name'], 'style="width: 45%;" onkeyup="'.$ajax_exp.'"');
    $form->addAnything(gettext('Publishing Place'), $str_input);
 // biblio publication date
     
    $form->addDateField('pubdate', gettext('Publication Date'), $rec_d['publication_date']?$rec_d['publication_date']:date('Y-m-d'));
 // biblio topics
        $str_input = '<div class="'.$visibility.'"><a class="notAJAX"  href="javascript: openHTMLpop(\''.MODULES_WEB_ROOT_DIR.'bibliography/pop_topic.php?biblioID='.$rec_d['biblio_id'].'\', 500, 200, \''.gettext('Subjects/Topics').'\')">'.gettext('Add Subject(s)').'</a></div>';
        $str_input .= '<iframe name="topicIframe" id="topicIframe" class="borderAll" style="width: 100%; height: 70px;" src="'.MODULES_WEB_ROOT_DIR.'bibliography/iframe_topic.php?biblioID='.$rec_d['biblio_id'].'&block=1"></iframe>';
    $form->addAnything(gettext('Subject(s)'), $str_input);
 // biblio volume number
    $form->addTextField('text', 'vol_no', gettext('Volume No.'), $rec_d['vol_no'], 'style="width: 40%;"');
    // biblio serial number
    $form->addTextField('text', 'serial_no', gettext('Serial No.'), $rec_d['serial_no'], 'style="width: 40%;"');
// biblio language
        // get language data related to this record from database
        $lang_q = $dbs->query("SELECT language_id, language_name FROM mst_language");
        $lang_options = array();
        while ($lang_d = $lang_q->fetch_row()) {
            $lang_options[] = array($lang_d[0], $lang_d[1]);
        }
    $form->addSelectList('languageID[]', gettext('Language'), $lang_options, $rec_d['language_id'],'multiple="multiple" size=5');
   // biblio note
    $form->addTextField('textarea', 'notes', gettext('Abstract/Notes'), $rec_d['notes'], 'style="width: 100%;" rows="2"');
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
if($gmdtitle=='Reference')
{
// biblio sub title
    $form->addTextField('text', 'subTitle', gettext('Sub Title'), $rec_d['sub_title'], 'rows="1" style="width: 100%;"');
    // biblio keywords
    $form->addTextField('text', 'tags', gettext('Keywords'), $rec_d['tags'], 'style="width: 40%;"');
 
 // biblio edition
    $form->addTextField('text', 'edition', gettext('Edition'), $rec_d['edition'], 'style="width: 40%;"');
 // biblio authors
        $str_input = '<div class="'.$visibility.'"><a class="notAJAX" href="javascript: openHTMLpop(\''.MODULES_WEB_ROOT_DIR.'bibliography/pop_author.php?biblioID='.$rec_d['biblio_id'].'\', 500, 200, \''.gettext('Authors/Roles').'\')">'.gettext('Add Author(s)').'</a></div>';
        $str_input .= '<iframe name="authorIframe" id="authorIframe" class="borderAll" style="width: 100%; height: 70px;" src="'.MODULES_WEB_ROOT_DIR.'bibliography/iframe_author.php?biblioID='.$rec_d['biblio_id'].'&block=1"></iframe>';
    $form->addAnything(gettext('Author(s)'), $str_input);

// biblio ISBN/ISSN
    $form->addTextField('text', 'isbn_issn', gettext('ISBN/ISSN'), $rec_d['isbn_issn'], 'style="width: 40%;"');
 // biblio topics
        $str_input = '<div class="'.$visibility.'"><a class="notAJAX"  href="javascript: openHTMLpop(\''.MODULES_WEB_ROOT_DIR.'bibliography/pop_topic.php?biblioID='.$rec_d['biblio_id'].'\', 500, 200, \''.gettext('Subjects/Topics').'\')">'.gettext('Add Subject(s)').'</a></div>';
        $str_input .= '<iframe name="topicIframe" id="topicIframe" class="borderAll" style="width: 100%; height: 70px;" src="'.MODULES_WEB_ROOT_DIR.'bibliography/iframe_topic.php?biblioID='.$rec_d['biblio_id'].'&block=1"></iframe>';
    $form->addAnything(gettext('Subject(s)'), $str_input);

 // biblio publisher
        // AJAX expression
        $ajax_exp = "ajaxFillSelect('".SENAYAN_WEB_ROOT_DIR."admin/AJAX_lookup_handler.php', 'mst_publisher', 'publisher_id:publisher_name', 'publisherID', $('publ_search_str').getValue())";
        if ($rec_d['publisher_name']) {
            $publ_options[] = array($rec_d['publisher_id'], $rec_d['publisher_name']);
        }
        $publ_options[] = array('0', gettext('Publisher'));
        // string element
        $str_input = simbio_form_element::selectList('publisherID', $publ_options, '', 'style="width: 50%;"');
        $str_input .= '&nbsp;';
        $str_input .= simbio_form_element::textField('text', 'publ_search_str', $rec_d['publisher_name'], 'style="width: 45%;" onkeyup="'.$ajax_exp.'"');
    $form->addAnything(gettext('Publisher'), $str_input);  
    // biblio publish place
        // AJAX expression
        $ajax_exp = "ajaxFillSelect('".SENAYAN_WEB_ROOT_DIR."admin/AJAX_lookup_handler.php', 'mst_place', 'place_id:place_name', 'placeID', $('plc_search_str').getValue())";
        // string element
        if ($rec_d['place_name']) {
            $plc_options[] = array($rec_d['publish_place_id'], $rec_d['place_name']);
        }
        $plc_options[] = array('0', gettext('Publishing Place'));
        $str_input = simbio_form_element::selectList('placeID', $plc_options, '', 'style="width: 50%;"');
        $str_input .= '&nbsp;';
        $str_input .= simbio_form_element::textField('text', 'plc_search_str', $rec_d['place_name'], 'style="width: 45%;" onkeyup="'.$ajax_exp.'"');
    $form->addAnything(gettext('Publishing Place'), $str_input);
// biblio publish year
    $form->addTextField('text', 'year', gettext('Year'), $rec_d['publish_year'], 'style="width: 40%;"');
 
 // biblio classification
   $form->addTextField('text', 'class', gettext('Classification No.'), $rec_d['classification'], 'style="width: 40%;"');
 // biblio volume number
    $form->addTextField('text', 'vol_no', gettext('Volume No.'), $rec_d['vol_no'], 'style="width: 40%;"');
 // biblio collation
    $form->addTextField('text', 'collation', gettext('Book Size/ Number of page'), $rec_d['collation'], 'style="width: 40%;"');
// biblio language
        // get language data related to this record from database
        $lang_q = $dbs->query("SELECT language_id, language_name FROM mst_language");
        $lang_options = array();
        while ($lang_d = $lang_q->fetch_row()) {
            $lang_options[] = array($lang_d[0], $lang_d[1]);
        }
    $form->addSelectList('languageID[]', gettext('Language'), $lang_options, $rec_d['language_id'],'multiple="multiple" size=5');
   // biblio note
    $form->addTextField('textarea', 'notes', gettext('Abstract/Notes'), $rec_d['notes'], 'style="width: 100%;" rows="2"');
 // biblio review
    $form->addTextField('text', 'review', gettext('Book Review'), $rec_d['review'], 'style="width: 40%;" rows="2"');
 // biblio index
     $form->addTextField('text', 'index_no', gettext('Index'), $rec_d['index_no'], 'style="width: 40%;" rows="2"');
 // biblio Doc.Type
    $form->addTextField('text', 'doc_type', gettext('Document Type'), $rec_d['doc_type'], 'style="width: 40%;" rows="2"');
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
if($gmdtitle=='Teachers')
{
// biblio series title
    $form->addTextField('textarea', 'seriesTitle', gettext('Series Title'), $rec_d['series_title'], 'rows="1" style="width: 100%;"');
 
       // biblio keywords
    $form->addTextField('text', 'tags', gettext('Keywords'), $rec_d['tags'], 'style="width: 40%;"');
  
    
// biblio publish frequencies
        // get frequency data related to this record from database
        $freq_q = $dbs->query('SELECT frequency_id, frequency FROM mst_frequency');
        $freq_options[] = array('0', strtoupper(gettext('Not Applicable')));
        while ($freq_d = $freq_q->fetch_row()) {
            $freq_options[] = array($freq_d[0], $freq_d[1]);
        }
        $str_input = simbio_form_element::selectList('frequencyID', $freq_options, $rec_d['frequency_id']);
        $str_input .= '&nbsp;';
        $str_input .= ' '.gettext('Use this for Serial publication');
   	  $form->addAnything(gettext('Frequency'), $str_input);
    // biblio ISBN/ISSN
    $form->addTextField('text', 'isbn_issn', gettext('ISBN/ISSN'), $rec_d['isbn_issn'], 'style="width: 40%;"');
   // biblio authors
        $str_input = '<div class="'.$visibility.'"><a class="notAJAX" href="javascript: openHTMLpop(\''.MODULES_WEB_ROOT_DIR.'bibliography/pop_author.php?biblioID='.$rec_d['biblio_id'].'\', 500, 200, \''.gettext('Authors/Roles').'\')">'.gettext('Add Author(s)').'</a></div>';
        $str_input .= '<iframe name="authorIframe" id="authorIframe" class="borderAll" style="width: 100%; height: 70px;" src="'.MODULES_WEB_ROOT_DIR.'bibliography/iframe_author.php?biblioID='.$rec_d['biblio_id'].'&block=1"></iframe>';
    $form->addAnything(gettext('Author(s)'), $str_input);
    // biblio classification
   // $form->addTextField('text', 'class', gettext('Classification'), $rec_d['classification'], 'style="width: 40%;"');
    // biblio publisher
        // AJAX expression
        $ajax_exp = "ajaxFillSelect('".SENAYAN_WEB_ROOT_DIR."admin/AJAX_lookup_handler.php', 'mst_publisher', 'publisher_id:publisher_name', 'publisherID', $('publ_search_str').getValue())";
        if ($rec_d['publisher_name']) {
            $publ_options[] = array($rec_d['publisher_id'], $rec_d['publisher_name']);
        }
        $publ_options[] = array('0', gettext('Publisher'));
        // string element
        $str_input = simbio_form_element::selectList('publisherID', $publ_options, '', 'style="width: 50%;"');
        $str_input .= '&nbsp;';
        $str_input .= simbio_form_element::textField('text', 'publ_search_str', $rec_d['publisher_name'], 'style="width: 45%;" onkeyup="'.$ajax_exp.'"');
    $form->addAnything(gettext('Publisher'), $str_input);
    
    // biblio publish place
        // AJAX expression
        $ajax_exp = "ajaxFillSelect('".SENAYAN_WEB_ROOT_DIR."admin/AJAX_lookup_handler.php', 'mst_place', 'place_id:place_name', 'placeID', $('plc_search_str').getValue())";
        // string element
        if ($rec_d['place_name']) {
            $plc_options[] = array($rec_d['publish_place_id'], $rec_d['place_name']);
        }
        $plc_options[] = array('0', gettext('Publishing Place'));
        $str_input = simbio_form_element::selectList('placeID', $plc_options, '', 'style="width: 50%;"');
        $str_input .= '&nbsp;';
        $str_input .= simbio_form_element::textField('text', 'plc_search_str', $rec_d['place_name'], 'style="width: 45%;" onkeyup="'.$ajax_exp.'"');
    $form->addAnything(gettext('Publishing Place'), $str_input);
   
    // biblio publication date
     
    $form->addDateField('pubdate', gettext('Publication Date'), $rec_d['publication_date']?$rec_d['publication_date']:date('Y-m-d'));
  
 // biblio topics
        $str_input = '<div class="'.$visibility.'"><a class="notAJAX"  href="javascript: openHTMLpop(\''.MODULES_WEB_ROOT_DIR.'bibliography/pop_topic.php?biblioID='.$rec_d['biblio_id'].'\', 500, 200, \''.gettext('Subjects/Topics').'\')">'.gettext('Add Subject(s)').'</a></div>';
        $str_input .= '<iframe name="topicIframe" id="topicIframe" class="borderAll" style="width: 100%; height: 70px;" src="'.MODULES_WEB_ROOT_DIR.'bibliography/iframe_topic.php?biblioID='.$rec_d['biblio_id'].'&block=1"></iframe>';
    $form->addAnything(gettext('Subject(s)'), $str_input);

 
// $form->addTextField('text', 'subject', gettext('Subject'), $rec_d['subject'], 'style="width: 40%;"');
  
    // biblio language
        // get language data related to this record from database
        $lang_q = $dbs->query("SELECT language_id, language_name FROM mst_language");
        $lang_options = array();
        while ($lang_d = $lang_q->fetch_row()) {
            $lang_options[] = array($lang_d[0], $lang_d[1]);
        }
    $form->addSelectList('languageID[]', gettext('Language'), $lang_options, $rec_d['language_id'],'multiple="multiple" size=5');
     
    $form->addTextField('textarea', 'notes', gettext('Abstract/Notes'), $rec_d['notes'], 'style="width: 100%;" rows="2"');
   // biblio file attachment
    $str_input = '<div class="'.$visibility.'"><a class="notAJAX" href="javascript: openHTMLpop(\''.MODULES_WEB_ROOT_DIR.'bibliography/pop_attach.php?biblioID='.$rec_d['biblio_id'].'\', 600, 300, \''.gettext('File Attachments').'\')">'.gettext('Add Attachment').'</a></div>';
    $str_input .= '<iframe name="attachIframe" id="attachIframe" class="borderAll" style="width: 100%; height: 70px;" src="'.MODULES_WEB_ROOT_DIR.'bibliography/iframe_attach.php?biblioID='.$rec_d['biblio_id'].'&block=1"></iframe>';
    $form->addAnything(gettext('File Attachment'), $str_input); 
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
//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
if($gmdtitle=='Database' || $gmdtitle=='Website' || $gmdtitle=='Blog' || $gmdtitle=='Blog' || $gmdtitle=='Library forum' || $gmdtitle=='E-learning resource' || $gmdtitle=='Google scholar' || $gmdtitle=='Encyclopedia, pictorial, audio and video' || $gmdtitle=='Authors and writes' || $gmdtitle=='Yearbook' || $gmdtitle=='Maps' || $gmdtitle=='Histroy'|| $gmdtitle=='Dictionary, wordbook' || $gmdtitle=='Qutations'|| $gmdtitle=='Thesaurus' || $gmdtitle=='Thesaurus' || $gmdtitle=='Telephone and email directory' || $gmdtitle=='Directory' || $gmdtitle=='Acronyms and abbreviations' || $gmdtitle=='Symbols' || $gmdtitle=='Biographies' || $gmdtitle=='Book finder' || $gmdtitle=='ISBN' || $gmdtitle=='Library' || $gmdtitle=='Government informention' || $gmdtitle=='International organization' || $gmdtitle=='Patents' || $gmdtitle=='Search engine' || $gmdtitle=='Travel' || $gmdtitle=='Statstics' || $gmdtitle=='Reference' || $gmdtitle=='Standard' || $gmdtitle=='Comments &; suggestions'  || $gmdtitle=='Copy right' || $gmdtitle=='FAQ' || $gmdtitle=='Help sheets & documentations' || $gmdtitle=='Comments & suggestions' || $gmdtitle=='Library serivces' || $gmdtitle=='Online textbooks' || $gmdtitle=='Textual referance' || $gmdtitle=='Know your self' || $gmdtitle=='Book trailers' || $gmdtitle=='Subjectwise' || $gmdtitle=='Hands on activities' || $gmdtitle=='Reading mission' || $gmdtitle=='Career development' || $gmdtitle=='Study skill development' || $gmdtitle=='Various online test' || $gmdtitle=='International education' || $gmdtitle=='Languange corner' || $gmdtitle=='Projects & reports' || $gmdtitle=='Do it yourself' || $gmdtitle=='Online book renewals' || $gmdtitle=='My penulties' || $gmdtitle=='My reservations' || $gmdtitle=='My stuff' || $gmdtitle=='Document management system' || $gmdtitle=='Staff info' || $gmdtitle=='Deaprtment info' || $gmdtitle=='Reports' || $gmdtitle=='Course design' || $gmdtitle=='Curriculam design' || $gmdtitle=='Teaching strategies' || $gmdtitle=='Motivating students' || $gmdtitle=='Aproch to learning' || $gmdtitle=='Classroom assesment tools & techniques' || $gmdtitle=='Teaching with technology' || $gmdtitle=='Instructional technology' || $gmdtitle=='Web 2.0' || $gmdtitle=='Facebook' || $gmdtitle=='Twitter' || $gmdtitle=='How it work or do you know or do it yourself' || $gmdtitle=='CBTs (Computer Based Training Modules)' || $gmdtitle=='New records added')
{
       // biblio keywords
    $form->addTextField('text', 'tags', gettext('Keywords'), $rec_d['tags'], 'style="width: 40%;"');
}
if($gmdtitle=='Database' || $gmdtitle=='Website' || $gmdtitle=='Blog' || $gmdtitle=='Blog' || $gmdtitle=='Library forum' || $gmdtitle=='E-learning resource' || $gmdtitle=='Google scholar' || $gmdtitle=='Encyclopedia, pictorial, audio and video' || $gmdtitle=='Authors and writes' || $gmdtitle=='Yearbook' || $gmdtitle=='Maps' || $gmdtitle=='Histroy'|| $gmdtitle=='Dictionary, wordbook' || $gmdtitle=='Qutations'|| $gmdtitle=='Thesaurus' || $gmdtitle=='Thesaurus' || $gmdtitle=='Telephone and email directory' || $gmdtitle=='Directory' || $gmdtitle=='Acronyms and abbreviations' || $gmdtitle=='Symbols' || $gmdtitle=='Biographies' || $gmdtitle=='Book finder' || $gmdtitle=='ISBN' || $gmdtitle=='Library' || $gmdtitle=='Government informention' || $gmdtitle=='International organization' || $gmdtitle=='Patents' || $gmdtitle=='Search engine' || $gmdtitle=='Travel' || $gmdtitle=='Statstics' || $gmdtitle=='Reference' || $gmdtitle=='Standard' || $gmdtitle=='Comments &; suggestions'  || $gmdtitle=='Copy right' || $gmdtitle=='FAQ' || $gmdtitle=='Help sheets & documentations' || $gmdtitle=='Comments & suggestions' || $gmdtitle=='Library serivces' || $gmdtitle=='Online textbooks' || $gmdtitle=='Textual referance' || $gmdtitle=='Know your self' || $gmdtitle=='Book trailers' || $gmdtitle=='Subjectwise' || $gmdtitle=='Hands on activities' || $gmdtitle=='Reading mission' || $gmdtitle=='Career development' || $gmdtitle=='Study skill development' || $gmdtitle=='Various online test' || $gmdtitle=='International education' || $gmdtitle=='Languange corner' || $gmdtitle=='Projects & reports' || $gmdtitle=='Do it yourself' || $gmdtitle=='Online book renewals' || $gmdtitle=='My penulties' || $gmdtitle=='My reservations' || $gmdtitle=='My stuff' || $gmdtitle=='Document management system' || $gmdtitle=='Staff info' || $gmdtitle=='Deaprtment info' || $gmdtitle=='Reports' || $gmdtitle=='Course design' || $gmdtitle=='Curriculam design' || $gmdtitle=='Teaching strategies' || $gmdtitle=='Motivating students' || $gmdtitle=='Aproch to learning' || $gmdtitle=='Classroom assesment tools & techniques' || $gmdtitle=='Teaching with technology' || $gmdtitle=='Instructional technology' || $gmdtitle=='Web 2.0' || $gmdtitle=='Facebook' || $gmdtitle=='Twitter' || $gmdtitle=='How it work or do you know or do it yourself' || $gmdtitle=='CBTs (Computer Based Training Modules)' || $gmdtitle=='New records added')
{
   // biblio file attachment
    $str_input = '<div class="'.$visibility.'"><a class="notAJAX" href="javascript: openHTMLpop(\''.MODULES_WEB_ROOT_DIR.'bibliography/pop_attach.php?biblioID='.$rec_d['biblio_id'].'\', 600, 300, \''.gettext('File Attachments').'\')">'.gettext('Add Attachment').'</a></div>';
    $str_input .= '<iframe name="attachIframe" id="attachIframe" class="borderAll" style="width: 100%; height: 70px;" src="'.MODULES_WEB_ROOT_DIR.'bibliography/iframe_attach.php?biblioID='.$rec_d['biblio_id'].'&block=1"></iframe>';
    $form->addAnything(gettext('File Attachment'), $str_input); 
}

if($gmdtitle=='Database' || $gmdtitle=='Website' || $gmdtitle=='Blog' || $gmdtitle=='Blog' || $gmdtitle=='Library forum' || $gmdtitle=='E-learning resource' || $gmdtitle=='Google scholar' || $gmdtitle=='Encyclopedia, pictorial, audio and video' || $gmdtitle=='Authors and writes' || $gmdtitle=='Yearbook' || $gmdtitle=='Maps' || $gmdtitle=='Histroy'|| $gmdtitle=='Dictionary, wordbook' || $gmdtitle=='Qutations'|| $gmdtitle=='Thesaurus' || $gmdtitle=='Thesaurus' || $gmdtitle=='Telephone and email directory' || $gmdtitle=='Directory' || $gmdtitle=='Acronyms and abbreviations' || $gmdtitle=='Symbols' || $gmdtitle=='Biographies' || $gmdtitle=='Book finder' || $gmdtitle=='ISBN' || $gmdtitle=='Library' || $gmdtitle=='Government informention' || $gmdtitle=='International organization' || $gmdtitle=='Patents' || $gmdtitle=='Search engine' || $gmdtitle=='Travel' || $gmdtitle=='Statstics' || $gmdtitle=='Reference' || $gmdtitle=='Standard' || $gmdtitle=='Comments &; suggestions'  || $gmdtitle=='Copy right' || $gmdtitle=='FAQ' || $gmdtitle=='Help sheets & documentations' || $gmdtitle=='Comments & suggestions' || $gmdtitle=='Library serivces' || $gmdtitle=='Online textbooks' || $gmdtitle=='Textual referance' || $gmdtitle=='Know your self' || $gmdtitle=='Book trailers' || $gmdtitle=='Subjectwise' || $gmdtitle=='Hands on activities' || $gmdtitle=='Reading mission' || $gmdtitle=='Career development' || $gmdtitle=='Study skill development' || $gmdtitle=='Various online test' || $gmdtitle=='International education' || $gmdtitle=='Languange corner' || $gmdtitle=='Projects & reports' || $gmdtitle=='Do it yourself' || $gmdtitle=='Online book renewals' || $gmdtitle=='My penulties' || $gmdtitle=='My reservations' || $gmdtitle=='My stuff' || $gmdtitle=='Document management system' || $gmdtitle=='Staff info' || $gmdtitle=='Deaprtment info' || $gmdtitle=='Reports' || $gmdtitle=='Course design' || $gmdtitle=='Curriculam design' || $gmdtitle=='Teaching strategies' || $gmdtitle=='Motivating students' || $gmdtitle=='Aproch to learning' || $gmdtitle=='Classroom assesment tools & techniques' || $gmdtitle=='Teaching with technology' || $gmdtitle=='Instructional technology' || $gmdtitle=='Web 2.0' || $gmdtitle=='Facebook' || $gmdtitle=='Twitter' || $gmdtitle=='How it work or do you know or do it yourself' || $gmdtitle=='CBTs (Computer Based Training Modules)' || $gmdtitle=='New records added')
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

///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
echo $form->printOut();

?>


