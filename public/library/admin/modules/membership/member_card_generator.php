<?php
session_start();
/* Member card print */

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

//echo SENAYAN_WEB_ROOT_DIR.FILES_DIR.'/'.$print_file_name;die;
// privileges checking
$can_read = utility::havePrivilege('membership', 'r');

if (!$can_read) {
    die('<div class="errorBox">You dont have enough privileges to view this section</div>');
}

// local settings
$max_print = 65;

// clean print queue
if (isset($_GET['action']) AND $_GET['action'] == 'clear') {
    // update print queue count object
    echo '<script type="text/javascript">parent.$(\'queueCount\').update(\'0\');</script>';
    utility::jsAlert(gettext('Print queue cleared!'));
    unset($_SESSION['card']);
    exit();
}

if (isset($_POST['itemID']) AND !empty($_POST['itemID']) AND isset($_POST['itemAction'])) {
    if (!$can_read) {
        die();
    }
    if (!is_array($_POST['itemID'])) {
        // make an array
        $_POST['itemID'] = array($_POST['itemID']);
    }
    // loop array
    if (isset($_SESSION['card'])) {
        $print_count = count($_SESSION['card']);
    } else {
        $print_count = 0;
    }
    // card size
    $size = 2;
    // create AJAX request
    echo '<script type="text/javascript" src="'.JS_WEB_ROOT_DIR.'prototype.js"></script>';
    echo '<script type="text/javascript">';
    // loop array
    foreach ($_POST['itemID'] as $itemID) {
        if ($print_count == $max_print) {
            $limit_reach = true;
            break;
        }
        if (isset($_SESSION['card'][$itemID])) {
            continue;
        }
        if (!empty($itemID)) {
            $card_text = trim($itemID);
            echo 'new Ajax.Request(\''.SENAYAN_WEB_ROOT_DIR.'lib/phpbarcode/barcode.php?code='.$card_text.'&encoding='.$sysconf['barcode_encoding'].'&scale='.$size.'&mode=png\', { method: \'get\', onFailure: function(sendAlert) { alert(\'Error creating card!\'); } });'."\n";
            // add to sessions
            $_SESSION['card'][$itemID] = $itemID;
            $print_count++;
        }
    }
    echo '</script>';
    if (isset($limit_reach)) {
        $msg = str_replace('{max_print}', $max_print, gettext('Selected items NOT ADDED to print queue. Only {max_print} can be printed at once')); //mfc
        utility::jsAlert($msg);
    } else {
        // update print queue count object
        echo '<script type="text/javascript">parent.$(\'queueCount\').update(\''.$print_count.'\');</script>';
        utility::jsAlert(gettext('Selected items added to print queue'));
    }
    exit();
}

// card pdf download
if (isset($_GET['action']) AND $_GET['action'] == 'print') {
    // check if label session array is available
    if (!isset($_SESSION['card'])) {
        utility::jsAlert(gettext('There is no data to print!'));
        die();
    }
    if (count($_SESSION['card']) < 1) {
        utility::jsAlert(gettext('There is no data to print!'));
        die();
    }
    // concat all ID together
    $member_ids = '';
    foreach ($_SESSION['card'] as $id) {
        $member_ids .= '\''.$id.'\',';
    }
    // strip the last comma
    $member_ids = substr_replace($member_ids, '', -1);
    // send query to database
    $member_q = $dbs->query('SELECT m.first_name,m.last_name,m.middle_name, m.enrollment_no, m.image as image_file, up.name as user_group_name,CS.name as Standard,
        SS.name as Section,
        m.roll_no as Roll_no
		FROM '.$inte_schema.'.tblstudent AS m
		LEFT JOIN '.$inte_schema.'.tbluserprofilemaster AS up ON up.id = m.user_profile_id
		inner join '.$inte_schema.'.tblstudent_enrollment SE on SE.student_id =m.id and SE.SYEAR='.$dyear.' and SE.end_date is null
		inner join '.$inte_schema.'.standard CS on SE.standard_id =CS.id
		inner join '.$inte_schema.'.division SS on SS.id=SE.section_id
		WHERE m.SUB_INSTITUTE_ID='.$SUB_INSTITUTE_ID.' AND m.enrollment_no IN('.$member_ids.')
		order by Standard,Section,CAST(Roll_no AS SIGNED)'		
		);
		
		
		
    $member_datas = array();
    while ($member_d = $member_q->fetch_assoc()) {
        if ($member_d['enrollment_no']) {
            $member_datas[] = $member_d;
        }
    }

    // include printed settings configuration file
    include SENAYAN_BASE_DIR.'admin'.DIRECTORY_SEPARATOR.'admin_template'.DIRECTORY_SEPARATOR.'printed_settings.inc.php';
    // check for custom template settings
    $custom_settings = SENAYAN_BASE_DIR.'admin'.DIRECTORY_SEPARATOR.$sysconf['admin_template']['dir'].DIRECTORY_SEPARATOR.$sysconf['template']['theme'].DIRECTORY_SEPARATOR.'printed_settings.inc.php';
    if (file_exists($custom_settings)) {
        include $custom_settings;
    }
    // chunk cards array
    //
    $chunked_card_arrays = array_chunk($member_datas,$card_items_per_row );
    // create html ouput
    $html_str = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">'."\n";
    $html_str .= '<html xmlns="http://www.w3.org/1999/xhtml"><head><title>Member card Label Print Result</title>'."\n";
    $html_str .= '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
    $html_str .= '<meta http-equiv="Pragma" content="no-cache" /><meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, post-check=0, pre-check=0" /><meta http-equiv="Expires" content="Sat, 26 Jul 1997 05:00:00 GMT" />';
    $html_str .= '<style type="text/css">'."\n";
    //$html_str .= 'body { padding: 0; margin: 1cm; font-size: '.$card_font_size.'pt; font-family: '.$card_fonts.'; background: #fff; }'."\n";
    $html_str .= 'body {  padding: 0; margin: 1cm; font-size: 10pt; font-family: '.$card_fonts.'; background: #fff; }'."\n";
    $html_str .= '.labelStyle { width: 38mm; height: 17.5mm; text-align: center; border: '.$card_border_size.'px solid #666666; overflow: hidden;}'."\n";
    //$html_str .= '.labelStyle { width: 11cm; height: 4cm; text-align: center; margin: '.$card_items_margin.'cm; border: '.$card_border_size.'px solid #666666; padding: 5px; overflow: hidden;}'."\n";
    //$html_str .= '.labelStyle { width: 8.7cm; height: 3cm; text-align: center; margin: '.$card_items_margin.'cm; border: '.$card_border_size.'px solid #666666; padding: 5px; overflow: hidden;}'."\n";
    $html_str .= '.labelHeaderStyle { background-color: #CCCCCC; font-weight: bold; padding: 5px; margin-bottom: 5px; }'."\n";
    $html_str .= '#photo { border: 1px solid #666666; float: left; width: '.$card_photo_width.'cm; height: '.$card_photo_height.'cm; overflow: hidden; }'."\n";
    //$html_str .= '#photo { border: 1px solid #666666; float: left; width:2cm; height: 2cm; overflow: hidden; }'."\n";
    $html_str .= '#photo img { width: 100%; }'."\n";
    $html_str .= '#bio { float: left; padding-left: 5px; text-align: left; overflow: hidden; width: '.(($card_box_width+1)-$card_photo_width-0.3).'cm;font-size: 10px; }'."\n";
    //$html_str .= '#bio { float: left; padding-left: 5px; text-align: left; overflow: hidden; width: 6cm; }'."\n";
    $html_str .= '</style>'."\n";
    $html_str .= '</head>'."\n";
    $html_str .= '<body>'."\n";
//    $html_str .= '<a href="#" onclick="window.print()" >Print Again</a>'."\n";
    $html_str .= '<table style="margin: 0; padding: 0;" cellspacing="0" cellpadding="12">'."\n";
    // loop the chunked arrays to row
    print_r($chunked_card_arrays);
    foreach ($chunked_card_arrays as $card_rows) 
    {
        $html_str .= '<tr>'."\n";
        
        foreach ($card_rows as $card)
         {
            $html_str .= '<td valign="top">';
            $html_str .= '<div class="labelStyle">';
            if (trim($card_header_text) != '') { $html_str .= '<div class="labelHeaderStyle">'.$card_header_text.'</div>'; }
           // $html_str .= '<div id="photo">';
            //if($card['member_image']!='')
            //{
           // $html_str .= '<img src="'.SENAYAN_WEB_ROOT_DIR.IMAGES_DIR.'/persons/'.$card['member_image'].'" border="0" />';
            //}
            //$html_str .= '</div>';
            $html_str .= '<div id="bio">';
//            $html_str .= '<div>'.( $card_include_field_label?__('GR.No').' : ':'' ).'<strong>'.$card['enrollment_no'].'</strong></div>';
			
            $html_str .= '<div>'.$card['last_name'].' '.$card['first_name'].' '.$card['middle_name'].'</div>';
            //$html_str .= '<div>'.( $card_include_field_label?__('Membership Type').' : ':'' ).'<strong>'.$card['member_type_name'].'</strong></div>';
            $html_str .= '<div ><img src="'.SENAYAN_WEB_ROOT_DIR.IMAGES_DIR.'/barcodes/'.str_replace(array(' '), '_', $card['enrollment_no']).'.png" style="width:134px ;  height: 50px;" border="0" /></div>';
           // $html_str .= '<div style="text-align: left;"><img src="'.SENAYAN_WEB_ROOT_DIR.IMAGES_DIR.'/barcodes/'.str_replace(array(' '), '_', $card['member_id']).'.png" style="width: 90%; margin-top: 10px; height: 50px;" border="0" /></div>';
            $html_str .= '</div>';
            $html_str .= '</div>';
            $html_str .= '</td>';
        }
        $html_str .= '<tr>'."\n";
    }
    $html_str .= '</table>'."\n";
    $html_str .= '<script type="text/javascript">self.print();</script>'."\n";
    $html_str .= '</body></html>'."\n";
    // unset the session
    unset($_SESSION['card']);
    // write to file
    $print_file_name = 'member_card_gen_print_result_'.strtolower(str_replace(' ', '_', $_SESSION['uname'])).'.html';
    $file_write = @file_put_contents(FILES_UPLOAD_DIR.$print_file_name, $html_str);
    if ($file_write) {
        // update print queue count object
        echo '<script type="text/javascript">parent.$(\'queueCount\').update(\'0\');</script>';
        // open result in window
        //utility::jsAlert($print_file_name);
        echo "<center>";
     echo '<script  type="text/javascript">top.openHTMLpop(\''.SENAYAN_WEB_ROOT_DIR.FILES_DIR.'/'.$print_file_name.'\', 900, 500, \''.gettext('Member Card Printing').'\')</script>';
   } else { utility::jsAlert('ERROR! Cards failed to generate, possibly because '.SENAYAN_BASE_DIR.FILES_DIR.' directory is not writable'); }
    exit();
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
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'membership/index.php?action=detail class="headerText2">Add New Member</a>';
}
else
{
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'membership/index.php class="headerText2">View Member List</a>';
}*/
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'membership/member_card_generator.php class="headerText2">Member Card Printing</a>';
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
<a href="<?php echo MODULES_WEB_ROOT_DIR; ?>membership/member_card_generator.php" class="headerText2"><?php echo gettext('Member Card Printing'); ?></a> </li><li>
<!--<a href="<?php echo  MODULES_WEB_ROOT_DIR; ?>membership/import.php" class="headerText2"><?php //echo __('Import Member Data'); ?></a> </li><li> <a href="<?php echo  MODULES_WEB_ROOT_DIR; ?>membership/export.php" class="headerText2"><?php //echo __('Export Member Data'); ?></a> </li></ul>-->
	</td>
</tr>
</table>
</fieldset>
<fieldset class="menuBox">
<div class="menuBoxInner printIcon">
    <?php echo gettext('Member Card Printing'); ?> - <a target="blindSubmit" href="<?php echo MODULES_WEB_ROOT_DIR; ?>membership/member_card_generator.php?action=print" class="notAJAX headerText3"><?php echo gettext('Print Member Cards for Selected Data'); ?></a>
    &nbsp;<a target="blindSubmit" href="<?php echo MODULES_WEB_ROOT_DIR; ?>membership/member_card_generator.php?action=clear" class="notAJAX headerText3" style="color: #FF0000;"><?php echo gettext('Clear Print Queue'); ?></a>
    <p class="only_border">&nbsp;</p>
    <form name="search" action="<?php echo MODULES_WEB_ROOT_DIR; ?>membership/member_card_generator.php" id="search" method="get" style="display: inline;"><?php echo gettext('Search'); ?>:
      <!--commnet by iresh on 25-1-2011  <input type="text" name="keywords" id="keywords" size="30" />-->
   <!-- added by iresh on 25-1-2011 --> <input type="text" name="keywords" id="keywords" width=140px/>
    <input type="submit" id="doSearch" value="<?php echo gettext('Search'); ?>" class="button" />
    </form>
    <div style="margin-top: 3px;">
    <?php
    echo gettext('Maximum').' <font style="color: #FF0000">'.$max_print.'</font> '.gettext('records can be printed at once. Currently there is').' '; //mfc
    if (isset($_SESSION['card'])) {
        echo '<font id="queueCount" style="color: #FF0000">'.count($_SESSION['card']).'</font>';
    } else { echo '<font id="queueCount" style="color: #FF0000">0</font>'; }
    echo ' '.gettext('in queue waiting to be printed.'); //mfc
    ?>
    </div>
</div>
</fieldset>
<?php
/* search form end */
/* ITEM LIST */

 $table_spec ="        
    (
        SELECT 
        m.enrollment_no, 
        m.enrollment_no AS 'Enrollment_No1', 
        m.roll_no as 'Roll_no',
        m.mobile as 'Mobile',
        concat_ws(' ',cs.name,ss.name) as 'Std_Div',
        concat_ws(' ',m.first_name,m.middle_name,m.last_name) AS 'Member_Name',
        m.email AS 'E_mail', 
        up.name AS 'Membership_Type'
        FROM ".$inte_schema.".tblstudent AS m 
        INNER JOIN ".$inte_schema.".tbluserprofilemaster up ON up.id = m.user_profile_id
        INNER JOIN ".$inte_schema.".tblstudent_enrollment se ON se.student_id=m.id AND se.SYEAR=2021 AND se.end_date IS NULL AND m.sub_institute_id = se.sub_institute_id AND m.sub_institute_id = '".$SUB_INSTITUTE_ID."'
        INNER JOIN ".$inte_schema.".standard cs ON cs.id=se.standard_id AND cs.sub_institute_id = se.sub_institute_id
        INNER JOIN ".$inte_schema.".division ss ON ss.id=se.section_id AND ss.sub_institute_id = se.sub_institute_id
        
        UNION
        
        SELECT 
        m.user_name, 
        m.user_name AS 'Enrollment_No1', 
        ' ' as 'Roll_no',
        m.mobile as 'Mobile',
        ' ' as 'Std-Div',
        concat_ws(' ',m.first_name,m.middle_name,m.last_name) AS 'Member_Name', 
        m.email AS 'E_mail', 
        up.name AS 'Membership_Type'
        FROM ".$inte_schema.".tbluser m
        INNER JOIN ".$inte_schema.".tbluserprofilemaster up ON up.id = m.user_profile_id AND m.sub_institute_id = '".$SUB_INSTITUTE_ID."' 
    )AS X ";//WHERE X.enrollment_no IS NOT NULL


// create datagrid
$datagrid = new simbio_datagrid();


$datagrid->setSQLColumn('X.enrollment_no',
    'X.enrollment_no AS \''.gettext('Member ID').'\'',
	'X.Roll_no AS \''.gettext('Roll_no').'\'',
    'X.Member_Name AS \''.gettext('Member Name').'\'',
	'X.Std_Div AS \''.gettext('Std-Div').'\'',
    'X.Membership_Type AS \''.gettext('Membership Type').'\'');


$datagrid->setSQLorder('X.Membership_Type,X.Std_Div,CAST(X.Roll_no as SIGNED) ASC');


// is there any search
if (isset($_GET['keywords']) AND $_GET['keywords']) {
    $keyword = $dbs->escape_string(trim($_GET['keywords']));
    $words = explode(' ', $keyword);
    if (count($words) > 1) {
        $concat_sql = ' X.enrollment_no IS NOT NULL AND (';
        foreach ($words as $word) {
            $concat_sql .= " X.enrollment_no LIKE '%$word%' OR X.Member_name LIKE '%$word%' AND";
        }
        // remove the last AND
        $concat_sql = substr_replace($concat_sql, '', -3);
        $concat_sql .= ') ';
        $datagrid->setSQLCriteria($concat_sql);
    } else {
        $datagrid->setSQLCriteria("X.enrollment_no IS NOT NULL AND X.enrollment_no LIKE '%$keyword%' OR X.Member_name LIKE '%$keyword%'");
    }
} else {
	$datagrid->setSQLCriteria("X.enrollment_no IS NOT NULL");
}
// set table and table header attributes
$datagrid->table_attr = 'align="center" id="dataList" cellpadding="5" cellspacing="0"';
$datagrid->table_header_attr = 'class="dataListHeader" style="font-weight: bold;"';
// edit and checkbox property
$datagrid->edit_property = false;
$datagrid->chbox_property = array('itemID', gettext('Add'));
$datagrid->chbox_action_button = gettext('Add To Print Queue');
$datagrid->chbox_confirm_msg = gettext('Add to print queue?');
$datagrid->column_width = array('10%', '10%', '55%','15%','10%');
// set checkbox action URL
$datagrid->chbox_form_URL = $_SERVER['PHP_SELF'];
// put the result into variables
$datagrid_result = $datagrid->createDataGrid($dbs, $table_spec, 65, $can_read);
if (isset($_GET['keywords']) AND $_GET['keywords']) {
    echo '<div class="infoBox">'.gettext('Found').' '.$datagrid->num_rows.' '.gettext('from your search with keyword').': "'.$_GET['keywords'].'"</div>'; //mfc
}
echo $datagrid_result;
/* main content end */

?>
