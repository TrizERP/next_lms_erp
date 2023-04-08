<?php

/* Common Variables */

/* Location list */
ob_start();
echo '<option value="0">'.__('All Locations').'</option>';
$loc_q = $dbs->query('SELECT location_name FROM mst_location LIMIT 50');
while ($loc_d = $loc_q->fetch_row()) {
    echo '<option value="'.$loc_d[0].'">'.$loc_d[0].'</option>';
}
$location_list = ob_get_clean();

/* Collection type List */
ob_start();
echo '<option value="0">'.__('All Collections').'</option>';
$colltype_q = $dbs->query('SELECT coll_type_name FROM mst_coll_type LIMIT 50');
while ($colltype_d = $colltype_q->fetch_row()) {
    echo '<option value="'.$colltype_d[0].'">'.$colltype_d[0].'</option>';
}
$colltype_list = ob_get_clean();

/* GMD List */
ob_start();
/*added by iresh on 22-1-2011 */echo '<option value="0">'.__('All Material Type/Media').'</option>';
$gmd_q = $dbs->query('SELECT gmd_name FROM mst_gmd LIMIT 50');
while ($gmd_d = $gmd_q->fetch_row()) {
    echo '<option value="'.$gmd_d[0].'">'.$gmd_d[0].'</option>';
}
$gmd_list = ob_get_clean();

//added start by Parth 7/7/2011
/* Sub Type Data*/
ob_start();
/*added by iresh on 22-1-2011 */echo '<option value="0">'.__('All Material Sub Type/Media').'</option>';
$mst_sub_q = $dbs->query('SELECT material_sub_name FROM mst_material_sub_type LIMIT 50');
while ($mst_sub_d = $mst_sub_q->fetch_row()) {
    echo '<option value="'.$mst_sub_d[0].'">'.$mst_sub_d[0].'</option>';
}
$mst_sub_list = ob_get_clean();
//Ended by Parth 7/7/2011

/* Language selection list */
ob_start();
require_once(LANGUAGES_BASE_DIR.'localisation.php');
foreach ($available_languages AS $lang_index) {
            $selected = null;
            $lang_code = $lang_index[0];
            $lang_name = $lang_index[1];
            if ($lang_code == $sysconf['default_lang']) {
                $selected = 'selected';
            }
            echo '<option value="'.$lang_code.'" '.$selected.'>'.$lang_name.'</option>';
}
$language_select = ob_get_clean();

/* include simbio form element library */
require SIMBIO_BASE_DIR.'simbio_GUI/form_maker/simbio_form_element.inc.php';
/* Advanced Search Author AJAX field */
ob_start();
// create AJAX drop down
$ajaxDD = new simbio_fe_AJAX_select();
$ajaxDD->element_name = 'author';
$ajaxDD->element_css_class = 'ajaxInputField';
$ajaxDD->additional_params = 'type=author';
$ajaxDD->handler_URL = 'lib/contents/advsearch_AJAX_response.php';
echo $ajaxDD->out();
$advsearch_author = ob_get_clean();


/* Advanced Search Topic/Subject AJAX field */
ob_start();
// create AJAX drop down
$ajaxDD = new simbio_fe_AJAX_select();
$ajaxDD->element_name = 'subject';
$ajaxDD->element_css_class = 'ajaxInputField';
$ajaxDD->additional_params = 'type=topic';
$ajaxDD->handler_URL = 'lib/contents/advsearch_AJAX_response.php';
echo $ajaxDD->out();
$advsearch_topic = ob_get_clean();
?>
