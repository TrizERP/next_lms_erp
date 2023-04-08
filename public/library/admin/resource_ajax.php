<?php

$SENAYAN_BASE_DIR=$_REQUEST['dir'];

// main system configuration
require '../sysconfig.inc.php';
// start the session
// 
//echo SENAYAN_BASE_DIR.'admin/default/session.inc.php';
echo "<pre>";
print_r($_POST);
echo "</pre>";

require $SENAYAN_BASE_DIR.'admin/default/session.inc.php';
require $SENAYAN_BASE_DIR.'admin/default/session_check.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/form_maker/simbio_form_table_AJAX.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/simbio_dbop.inc.php';
require SIMBIO_BASE_DIR.'simbio_FILE/simbio_file_upload.inc.php';

$itemID = (integer)isset($_REQUEST['itemID'])?$_REQUEST['itemID']:0;

if (isset($_REQUEST['itemID']) && $_REQUEST['itemID']!='130') 
    {                                                                             
                    $_sql_rec_q = sprintf('SELECT b.*, p.publisher_name, pl.place_name,mst.material_sub_name,g.gmd_name,mr.material_resource_name FROM biblio AS b
                        LEFT JOIN mst_publisher AS p ON b.publisher_id=p.publisher_id
                        LEFT JOIN mst_place AS pl ON b.publish_place_id=pl.place_id
                        LEFT JOIN mst_material_sub_type AS mst ON mst.material_sub_id=b.material_sub_id
                        LEFT JOIN mst_gmd AS g ON g.gmd_id=b.gmd_id
                        LEFT JOIN mst_material_resource_type AS mr ON mr.material_resource_id=b.material_resource_id
                        WHERE biblio_id=%d', $itemID);                
                    $rec_q = $dbs->query($_sql_rec_q);
                    $rec_d = $rec_q->fetch_assoc();
                    
                   
                    $form = new simbio_form_table_AJAX('mainForm1', $_SERVER['PHP_SELF'].'?save=save&'.$_SERVER['QUERY_STRING'], 'post');                                
                   
                    $form->submit_button_attr = 'name="saveData" value="'.__('Save').'" class="button"';
                     $form->table_attr = 'align="center" id="dataList1" cellpadding="5" cellspacing="0"';
                    $form->table_header_attr = 'class="alterCell" style="font-weight: bold;"';
                    $form->table_content_attr = 'class="alterCell2"';
                    
                    $form->addTextField('textarea', 'title', __('Title').'*', $rec_d['title'], 'rows="1" style="width: 100%; overflow: auto;"');
                    
                    $form->addTextField('text', 'edition', __('Edition'), $rec_d['edition'], 'style="width: 40%;"');
                    
                    $form->addTextField('text', 'tags', __('Volume No'), $rec_d['tags'], 'style="width: 40%;"'); 
                    
                    $form->addTextField('textarea', 'specDetailInfo', __('Specific Detail Info'), $rec_d['spec_detail_info'], 'rows="2" style="width: 100%"');

                    // biblio hide from opac
                    $hide_options[] = array('0', __('Show'));
                    $hide_options[] = array('1', __('Hide'));
                    $form->addRadio('opacHide', __('Hide For Member Access'), $hide_options, $rec_d['opac_hide']?'1':'0');
                    // biblio promote to front page
                    $promote_options[] = array('0', __('Don\'t Promote'));
                    $promote_options[] = array('1', __('Promote'));
                    $form->addRadio('promote', __('Promote To Homepage'), $promote_options, $rec_d['promoted']?'1':'0');
                    echo $form->printOut();
                    
} 
else
{

                       
                    
                    //$itemID = (integer)isset($_POST['itemID'])?$_POST['itemID']:0;
                
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
                    $form = new simbio_form_table_AJAX('mainForm1', $_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'], 'post');                                
                    $form->submit_button_attr = 'name="saveData" value="'.__('Save').'" class="button"';
                     $form->table_attr = 'align="center" id="dataList1" cellpadding="5" cellspacing="0"';
                    $form->table_header_attr = 'class="alterCell" style="font-weight: bold;"';
                    $form->table_content_attr = 'class="alterCell2"';

                    
                    
                    $visibility = 'makeVisible';
                    // edit mode flag set
                    if ($rec_q->num_rows > 0) 
                    {
                        $form->edit_mode = true;
                        // record ID for delete process
                        if (!$in_pop_up) 
                        {
                            // form record id
                            $form->record_id = $itemID;
                        }
                        else
                        {
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
                    if (file_exists(MODULES_BASE_DIR.'bibliography/custom_fields.inc.php'))
                    {

                        include MODULES_BASE_DIR.'bibliography/custom_fields.inc.php';
                    }
                    
                           $material_q = $dbs->query('SELECT material_resource_id, material_resource_name FROM mst_material_resource_type where active_inactive="1" AND material_resource_name="Physical Library"');
                        //$gmd_options='';
                       //$material_options = array('N/A');
                       $material_options = array('--Select--');
                        while ($material_d = $material_q->fetch_row()) 
                        //while ($gmd_d = $gmd_q->fetch_assoc())		
                        {
                            $material_options[] = array($material_d[0], $material_d[1]);
                            //$material_options[] = array($material_d[0], $material_d[1]);
                                
                                //$gmd_options.=$gmd_d['gmd_id'].',';
                        }
                            
                $ajax = "ajaxFillSelect('".SENAYAN_WEB_ROOT_DIR."admin/AJAX_material_sub_type_handler.php', 'mst_gmd', 'gmd_id:gmd_name:material_resource_id', 'gmdID', $('materialresourceid').getValue())";

                       if ($rec_d['gmd_name']) 
                       {
                            $mst_options[] = array($rec_d['gmd_id'],$rec_d['gmd_name']);
                        }
                        $mst_options[] = array('0', __('--Select Material Type--'));


                 $ajax_exp = "ajaxFillSelect('".SENAYAN_WEB_ROOT_DIR."admin/AJAX_material_sub_type_handler.php', 'mst_material_sub_type', 'material_sub_id:material_sub_name:gmd_id', 'materialsubid', $('gmdID').getValue())";

                       if ($rec_d['material_sub_name'])
                        {
                            $mst_material_sub_type_options[] = array($rec_d['material_sub_id'],$rec_d['material_sub_name']);
                        }
                        $mst_material_sub_type_options[] = array('0', __('-- Select Material Sub Type--'));
                        // string element

                $str_input=''; 
                if(isset($_POST['virtual']))
                {   
                    
                   
                    $str_input .= simbio_form_element::selectList('materialresourceid',$material_options, $rec_d['material_resource_id'],'onchange="'.$ajax.'"');
                }
                else
                {     
                    
                    $str_input .= simbio_form_element::selectList('materialresourceid',$material_options, $rec_d['material_resource_id'],'onchange="'.$ajax.'"');
                }
                
                if(isset($_POST['virtual']))
                {
                    
            
                    $str_input .= simbio_form_element::selectList('gmdID', $mst_options, $rec_d['gmd_id'],'onchange="'.$ajax_exp.'"');
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
                     
                     $onchange="generatetable1(this,'".SENAYAN_BASE_DIR."')";
                    
                     $str_input .= simbio_form_element::selectList('materialsubid', $mst_material_sub_type_options, $rec_d['material_sub_id'],'onchange='.$onchange.'');
                     //$str_input .= simbio_form_element::selectList('materialsubid', $mst_material_sub_type_options, $rec_d['material_sub_id'], ' style="width: 20%;"');
                
                    
                    
                    
                }
                
                //$str_input .= '<tr><td id="txtHint" class="alterCell2" colspan="3"></td></tr>';
                $str_input .= '<tr><td id="txtHint" class="alterCell2" colspan="3"></td></tr>';
                
                
                
                $form->addAnything(__('Material Type *'), $str_input);
                
                
              
                    
                    $form->addTextField('textarea', 'title', __('Title').'*', $rec_d['title'], 'rows="1" style="width: 100%; overflow: auto;"');
                    
                    $form->addTextField('text', 'edition', __('Edition'), $rec_d['edition'], 'style="width: 40%;"');
                    
                    $form->addTextField('text', 'tags', __('Volume No'), $rec_d['tags'], 'style="width: 40%;"'); 
                    
                    $form->addTextField('textarea', 'specDetailInfo', __('Specific Detail Info'), $rec_d['spec_detail_info'], 'rows="2" style="width: 100%"');

                    
                if($rec_d['biblio_id']=='')
                {
                    $qry = $dbs->query("select biblio_id from biblio ORDER BY biblio_id DESC LIMIT 0,1");
                    $result = $qry->fetch_assoc();
                    $rec_d['biblio_id'] = $result['biblio_id']+1;
                }
                        $str_input = '<div class="'.$visibility.'"><a class="notAJAX" href="javascript: openHTMLpop(\''.MODULES_WEB_ROOT_DIR.'bibliography/pop_item.php?inPopUp=true&action=detail&biblioID='.$rec_d['biblio_id'].'\', 650, 400, \''.__('Items/Copies').'\')">'.__('Add New Items').'</a></div>';
                        $str_input .= '<iframe name="itemIframe" id="itemIframe" class="borderAll" style="width: 100%; height: 70px;" src="'.MODULES_WEB_ROOT_DIR.'bibliography/iframe_item_list.php?biblioID='.$rec_d['biblio_id'].'&block=1"></iframe>'."\n";
                        $form->addAnything('Item(s) Data', $str_input);
                  /*}//*/

                /*//added by iresh on 7-2-2011

                    $str_input = '<div class="'.$visibility.'"><a class="notAJAX" href="javascript: openHTMLpop(\''.MODULES_WEB_ROOT_DIR.'bibliography/pop_item.php?inPopUp=true&action=detail&biblioID='.$rec_d['biblio_id'].'\', 650, 400, \''.__('Items/Copies').'\')">'.__('Add New Items').'</a></div>';
                        $str_input .= '<iframe name="itemIframe" id="itemIframe" class="borderAll" style="width: 100%; height: 70px;" src="'.MODULES_WEB_ROOT_DIR.'bibliography/iframe_item_list.php?biblioID='.$rec_d['biblio_id'].'&block=1"></iframe>'."\n";
                        $form->addAnything('Item(s) Data', $str_input);*/
                //end by iresh

                    // biblio authors
                        $str_input = '<div class="'.$visibility.'"><a class="notAJAX" href="javascript: openHTMLpop(\''.MODULES_WEB_ROOT_DIR.'bibliography/pop_author.php?biblioID='.$rec_d['biblio_id'].'\', 500, 200, \''.__('Authors/Roles').'\')">'.__('Add Author(s)').'</a></div>';
                        $str_input .= '<iframe name="authorIframe" id="authorIframe" class="borderAll" style="width: 100%; height: 70px;" src="'.MODULES_WEB_ROOT_DIR.'bibliography/iframe_author.php?biblioID='.$rec_d['biblio_id'].'&block=1"></iframe>';
                    $form->addAnything(__('Author(s)'), $str_input);
                    // biblio gmd
                        // get gmd data related to this record from database
                   /*    $material_q = $dbs->query('SELECT material_resource_id, material_resource_name FROM mst_material_resource_type where active_inactive="1" AND material_resource_name="Physical Library"');
                        //$gmd_options='';
                       //$material_options = array('N/A');
                       $material_options = array('--Select--');
                        while ($material_d = $material_q->fetch_row()) 
                        //while ($gmd_d = $gmd_q->fetch_assoc())		
                        {
                            $material_options[] = array($material_d[0], $material_d[1]);
                            //$material_options[] = array($material_d[0], $material_d[1]);
                                
                                //$gmd_options.=$gmd_d['gmd_id'].',';
                        }
                            
                $ajax = "ajaxFillSelect('".SENAYAN_WEB_ROOT_DIR."admin/AJAX_material_sub_type_handler.php', 'mst_gmd', 'gmd_id:gmd_name:material_resource_id', 'gmdID', $('materialresourceid').getValue())";

                       if ($rec_d['gmd_name']) {
                            $mst_options[] = array($rec_d['gmd_id'],$rec_d['gmd_name']);
                        }
                        $mst_options[] = array('0', __('--Select Material Type--'));


                 $ajax_exp = "ajaxFillSelect('".SENAYAN_WEB_ROOT_DIR."admin/AJAX_material_sub_type_handler.php', 'mst_material_sub_type', 'material_sub_id:material_sub_name:gmd_id', 'materialsubid', $('gmdID').getValue())";

                       if ($rec_d['material_sub_name'])
                        {
                            $mst_material_sub_type_options[] = array($rec_d['material_sub_id'],$rec_d['material_sub_name']);
                        }
                        $mst_material_sub_type_options[] = array('0', __('-- Select Material Sub Type--'));
                        // string element

                $str_input=''; 
                if(isset($_POST['virtual']))
                {   
                    
                    $str_input .= simbio_form_element::selectList('materialresourceid',$material_options, $rec_d['material_resource_id'],'onchange="'.$ajax.'"');
                }
                else
                {     
                    
                    $str_input .= simbio_form_element::selectList('materialresourceid',$material_options, $rec_d['material_resource_id'],'onchange="'.$ajax.'"');
                }
                
                if(isset($_POST['virtual']))
                {
                    $str_input .= simbio_form_element::selectList('gmdID', $mst_options, $rec_d['gmd_id'],'onchange="'.$ajax_exp.'"');
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
                    
                    $str_input .= simbio_form_element::selectList('materialsubid', $mst_material_sub_type_options, $rec_d['material_sub_id'], ' style="width: 20%;"');
                
                    
                    
                    
                }
                
                //$str_input .= '<tr><td id="txtHint" class="alterCell2" colspan="3"></td></tr>';
                $str_input .= '<tr><td id="txtHint" class="alterCell2" colspan="3"></td></tr>';
                
                
                
                $form->addAnything(__('Material Type *'), $str_input);*/
                
                        $freq_q = $dbs->query('SELECT frequency_id, frequency FROM mst_frequency');
                        $freq_options[] = array('0', strtoupper(__('--Select Frequency--')));
                        while ($freq_d = $freq_q->fetch_row()) 
                        {
                            echo "<td>";
                            $freq_options[] = array($freq_d[0], $freq_d[1]);
                            echo "</td>";
                            
                        }
                        $str_input = simbio_form_element::selectList('frequencyID', $freq_options, $rec_d['frequency_id']);
                        $str_input .= '&nbsp;';
                        $str_input .= ' '.__('Use this for Serial publication');
                    $form->addAnything(__('Frequency').'*', $str_input);
                    // biblio ISBN/ISSN
                    $form->addTextField('text', 'isbn_issn', __('ISBN/ISSN'), $rec_d['isbn_issn'], 'style="width: 40%;" onchange="return checkspecialcharacterdynamic(this.name);"');
                    // biblio classification
                    $form->addTextField('text', 'class', __('Classification'), $rec_d['classification'], 'style="width: 40%;"');
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
                    // biblio publish year
                    $form->addTextField('text', 'year', __('Publishing Year'), $rec_d['publish_year'], 'style="width: 40%;"');
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
                    // biblio collation
                    $form->addTextField('text', 'collation', __('Book Size/ Number of page'), $rec_d['collation'], 'style="width: 40%;" onchange="return numericcheck(this.name);"');
                    // biblio series title
                    $form->addTextField('textarea', 'seriesTitle', __('Series Title'), $rec_d['series_title'], 'rows="1" style="width: 100%;"');
                    // biblio call_number
                    $form->addTextField('text', 'callNumber', __('Call Number'), $rec_d['call_number'], 'style="width: 40%;"');
                    // biblio topics
                        $str_input = '<div class="'.$visibility.'"><a class="notAJAX"  href="javascript: openHTMLpop(\''.MODULES_WEB_ROOT_DIR.'bibliography/pop_topic.php?biblioID='.$rec_d['biblio_id'].'\', 500, 200, \''.__('Subjects/Topics').'\')">'.__('Add Subject(s)').'</a></div>';
                        $str_input .= '<iframe name="topicIframe" id="topicIframe" class="borderAll" style="width: 100%; height: 70px;" src="'.MODULES_WEB_ROOT_DIR.'bibliography/iframe_topic.php?biblioID='.$rec_d['biblio_id'].'&block=1"></iframe>';
                    $form->addAnything(__('Subject(s)'), $str_input);
                    // biblio language
                        // get language data related to this record from database
                        $lang_q = $dbs->query("SELECT language_id, language_name FROM mst_language");
                        $lang_options = array();
                        while ($lang_d = $lang_q->fetch_row()) {
                            $lang_options[] = array($lang_d[0], $lang_d[1]);
                        }
                    $form->addSelectList('languageID', __('Language'), $lang_options, $rec_d['language_id']);
                    // biblio note
                    $form->addTextField('textarea', 'notes', __('Abstract/Notes'), $rec_d['notes'], 'style="width: 100%;" rows="2"');
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
                    // biblio file attachment
                    $str_input = '<div class="'.$visibility.'"><a class="notAJAX" href="javascript: openHTMLpop(\''.MODULES_WEB_ROOT_DIR.'bibliography/pop_attach.php?biblioID='.$rec_d['biblio_id'].'\', 600, 300, \''.__('File Attachments').'\')">'.__('Add Attachment').'</a></div>';
                    $str_input .= '<iframe name="attachIframe" id="attachIframe" class="borderAll" style="width: 100%; height: 70px;" src="'.MODULES_WEB_ROOT_DIR.'bibliography/iframe_attach.php?biblioID='.$rec_d['biblio_id'].'&block=1"></iframe>';
                    $form->addAnything(__('File Attachment'), $str_input);

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
                //commnted started by Parth 3/9/2011
                    // biblio labels
                       /* $arr_labels = !empty($rec_d['labels'])?unserialize($rec_d['labels']):array();
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
                    $form->addAnything('Label', $str_input);*/
                //commnted ended by Parth 3/9/2011
                    // $form->addCheckBox('labels', 'Label', $label_options, explode(' ', $rec_d['labels']));

                    // edit mode messagge
                    if ($form->edit_mode)
                    {
                        echo '<div class="infoBox" style="overflow: auto;">'
                            .'<div style="float: left; width: 80%;">'.__('You are going to edit biblio data').' : <b>'.$rec_d['title'].'</b>  <br />'.__('Last Updated').$rec_d['last_update'].'</div>'; //mfc
                            if ($rec_d['image']) 
                            {
                                if (file_exists(IMAGES_BASE_DIR.'docs/'.$rec_d['image'])) 
                                {
                                    $upper_dir = '';
                                    if ($in_pop_up) 
                                    {
                                        $upper_dir = '../../';
                                    }
                                    echo '<div style="float: right;"><img src="'.$upper_dir.'../lib/phpthumb/phpThumb.php?src=../../images/docs/'.urlencode($rec_d['image']).'&w=53" style="border: 1px solid #999999" /></div>';
                                }
                            }
                        echo '</div>'."\n";
                    }
                    // print out the form object                                        
                    
                   
//                    $form->addAnything($str_input);
                    
                    echo $form->printOut();

}
    


?>
