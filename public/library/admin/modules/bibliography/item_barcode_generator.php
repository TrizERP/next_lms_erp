<?php
session_start();
require '../../../sysconfig.inc.php';
// start the session
require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/form_maker/simbio_form_table_AJAX.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/simbio_dbop.inc.php';

// privileges checking
$can_read = utility::havePrivilege('bibliography', 'r');

if (!$can_read) {
    die('<div class="errorBox">'.gettext('You are not authorized to view this section').'</div>');
}

$max_print = 24;

/* RECORD OPERATION */
if (isset($_POST['itemID']) AND !empty($_POST['itemID']) AND isset($_POST['itemAction'])) 
{
    if (!$can_read) {
        die();
    }
    if (!is_array($_POST['itemID'])) {
        // make an array
        $_POST['itemID'] = array((integer)$_POST['itemID']);
    }
    // loop array
    if (isset($_SESSION['barcodes'])) {
        $print_count = count($_SESSION['barcodes']);
    } else {
        $print_count = 0;
    }
    // barcode size
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
        if (isset($_SESSION['barcodes'][$itemID])) {
            continue;
        }
        if (!empty($itemID)) {
            $barcode_text = trim($itemID);
            /* replace space */
            $barcode_text = str_replace(array(' ', '/', '\/'), '_', $barcode_text);
            /* replace invalid characters */
            $barcode_text = str_replace(array(':', ',', '*', '@'), '', $barcode_text);
            // send ajax request
            echo 'new Ajax.Request(\''.SENAYAN_WEB_ROOT_DIR.'lib/phpbarcode/barcode.php?code='.$itemID.'&encoding='.$sysconf['barcode_encoding'].'&scale='.$size.'&mode=png\', { method: \'get\', onFailure: function(sendAlert) { alert(\'Error creating barcode!\'); } });'."\n";
            // add to sessions
            $_SESSION['barcodes'][$itemID] = $itemID;
            $print_count++;
        }
    }
    echo '</script>';
    sleep(2);
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

// clean print queue
if (isset($_GET['action']) AND $_GET['action'] == 'clear') {
    // update print queue count object
    echo '<script type="text/javascript">parent.$(\'queueCount\').update(\'0\');</script>';
    utility::jsAlert(gettext('Print queue cleared!'));
    unset($_SESSION['barcodes']);
    exit();
}

/*echo '<pre>';
print_r($_REQUEST);
echo '<pre>';*/
// barcode pdf download
if (isset($_GET['action']) AND $_GET['action'] == 'print') {
    // check if label session array is available
    if (!isset($_SESSION['barcodes'])) 
    {
        utility::jsAlert(gettext('There is no data to print!'));
        die();
    }
    if (count($_SESSION['barcodes']) < 1) {
        utility::jsAlert(gettext('There is no data to print!'));
        die();
    }

    // concat all ID together
    $item_ids = '';
    foreach ($_SESSION['barcodes'] as $id) 
    {
        $item_ids .= '\''.$id.'\',';
    }
    // strip the last comma
    $item_ids = substr_replace($item_ids, '', -1);
    // send query to database
    $item_q = $dbs->query('SELECT CONCAT_WS(".",SUBSTRING(b.title, 1, 25),".") as title, i.item_code, b.classification FROM item AS i
        LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id
        WHERE i.item_code IN('.$item_ids.')');
		// echo 'divya SELECT CONCAT_WS(".",SUBSTRING(b.title, 1, 25),".") as title, i.item_code, b.classification FROM item AS i
        // LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id
        // WHERE i.item_code IN('.$item_ids.')';
    $item_data_array = array();
    while ($item_d = $item_q->fetch_assoc()) {
        if ($item_d['item_code']) {
            $item_data_array[] = $item_d;
        }
    }

    // include printed settings configuration file
    require SENAYAN_BASE_DIR.'admin'.DIRECTORY_SEPARATOR.'admin_template'.DIRECTORY_SEPARATOR.'printed_settings.inc.php';
    // check for custom template settings
    $custom_settings = SENAYAN_BASE_DIR.'admin'.DIRECTORY_SEPARATOR.$sysconf['admin_template']['dir'].DIRECTORY_SEPARATOR.$sysconf['template']['theme'].DIRECTORY_SEPARATOR.'printed_settings.inc.php';
    if (file_exists($custom_settings)) {
        include $custom_settings;
    }
    // chunk barcode array
    //print_r($item_data_array);
    $chunked_barcode_arrays = array_chunk($item_data_array, $barcode_items_per_row);
	
	$html_str = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">'."\n";
    $html_str .= '<html xmlns="http://www.w3.org/1999/xhtml"><head><title>Item Barcode Label Print Result</title>'."\n";
    $html_str .= '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
    $html_str .= '<meta http-equiv="Pragma" content="no-cache" /><meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, post-check=0, pre-check=0" /><meta http-equiv="Expires" content="Sat, 26 Jul 1997 05:00:00 GMT" />';
    $html_str .= '<style type="text/css">'."\n";
    $html_str .= 'body { padding: 0; margin: 0cm; font-family: '.$barcode_fonts.'; font-size: '.$barcode_font_size.'; background: #fff; }'."\n";
//    $html_str .= '.labelStyle { width: 38mm; height: 21mm; text-align: center; margin: 15px 0.0cm 0 0.0cm; border: '.$barcode_border_size.'px solid #000000;}'."\n";
    $html_str .= '.labelStyle { width: 6.9cm; height: 30mm; text-align: center; margin: 18px 0.0cm 0 0.0cm; border: '.$barcode_border_size.'px solid #000000;}'."\n";    
    $html_str .= '.labelHeaderStyle { background-color: #CCCCCC; font-weight: bold; padding: 0px; margin-bottom: 12px; }'."\n";
    $html_str .= '</style>'."\n";
    $html_str .= '</head>'."\n";
    $html_str .= '<body>'."\n";
    //$html_str .= '<a href="#" onclick="window.print()">Print Again</a>'."\n";
	
    $html_str .= '<table style="margin: 0; padding: 0;margin-top: 30px;" cellspacing="0" cellpadding="0">'."\n";
	
    // create html ouput
    /*$html_str = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">'."\n";
    $html_str .= '<html xmlns="http://www.w3.org/1999/xhtml"><head><title>Item Barcode Label Print Result</title>'."\n";
    $html_str .= '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
    $html_str .= '<meta http-equiv="Pragma" content="no-cache" /><meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, post-check=0, pre-check=0" /><meta http-equiv="Expires" content="Sat, 26 Jul 1997 05:00:00 GMT" />';
    $html_str .= '<style type="text/css">'."\n";
    $html_str .= 'body { padding: 0; margin: 1cm; font-family: '.$barcode_fonts.'; font-size: '.$barcode_font_size.'; background: #fff; }'."\n";
    $html_str .= '.labelStyle { width: '.$barcode_box_width.'cm; height: '.$barcode_box_height.'cm; text-align: center; margin: '.$barcode_items_margin.'cm; border: '.$barcode_border_size.'px solid #000000;}'."\n";
    $html_str .= '.labelHeaderStyle { background-color: #CCCCCC; font-weight: bold; padding: 5px; margin-bottom: 5px; }'."\n";
    $html_str .= '</style>'."\n";
    $html_str .= '</head>'."\n";
    $html_str .= '<body>'."\n";
    $html_str .= '<a href="#" onclick="window.print()">Print Again</a>'."\n";
    $html_str .= '<table style="margin: 0; padding: 0;" cellspacing="0" cellpadding="10">'."\n";*/
    // loop the chunked arrays to row
    foreach ($chunked_barcode_arrays as $barcode_rows) {
        $html_str .= '<tr>'."\n";
        foreach ($barcode_rows as $barcode) {
            $html_str .= '<td valign="top">';
            $html_str .= '<div class="labelStyle">';
            if ($barcode_include_header_text) {
			
			 //$html_str .= '<div class="labelHeaderStyle">'.($barcode_header_text?$barcode_header_text:$sysconf['library_name']).'</div>'; 
			 //$html_str .= '<div class="labelHeaderStyle">&nbsp;</div>'; 
			 }
            // document title
            //$html_str .= '<div style="font-size: 8pt;" ><br/>TMS-School';
			//$html_str .= '</div>';
           /* if ($barcode_cut_title) {
                $html_str .= substr($barcode[0], 0, $barcode_cut_title).'...';
            } else { $html_str .= $barcode[0]; }
            $html_str .= '</div>';*/
            $html_str .= '<div id="bio">';
            $html_str .= '<img height=60px src="'.SENAYAN_WEB_ROOT_DIR.IMAGES_DIR.'/barcodes/'.str_replace(array(' '), '_', $barcode['item_code']).'.png" style="width: '.$barcode_scale.'%;" border="0" />';
            $html_str .= '<div style="font-size: 12px;font-weight: bold;">'.$barcode['title'].'  -  '.$barcode['classification'].'</div>';
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
    unset($_SESSION['barcodes']);
    // write to file
    $print_file_name = 'item_barcode_gen_print_result_'.strtolower(str_replace(' ', '_', $_SESSION['uname'])).'.html';
    $file_write = @file_put_contents(FILES_UPLOAD_DIR.$print_file_name, $html_str);
    if ($file_write) {
        // update print queue count object
        echo '<script type="text/javascript">parent.$(\'queueCount\').update(\'0\');</script>';
        // open result in window
        echo '<script type="text/javascript">top.openHTMLpop(\''.SENAYAN_WEB_ROOT_DIR.FILES_DIR.'/'.$print_file_name.'\', 700, 500, \''.gettext('Item Barcodes Printing').'\')</script>';
    } else { utility::jsAlert('ERROR! Item barcodes failed to generate, possibly because '.SENAYAN_BASE_DIR.FILES_DIR.' directory is not writable'); }
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
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'bibliography/index.php?action=detail&physical=physical class="headerText2">Add Hardcopy Book</a>';
}
else
{
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'bibliography/index.php class="headerText2">Book List</a>';
}*/
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'bibliography/item_barcode_generator.php class="headerText2">Item Barcode Printing</a>';
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
<!--<a href="<?php echo MODULES_WEB_ROOT_DIR; ?>bibliography/dl_print.php" class="headerText2"><?php // echo __('Label Print'); ?></a> </li><li>-->
<a href="<?php echo  MODULES_WEB_ROOT_DIR; ?>bibliography/item_barcode_generator.php" class="headerText2"><?php echo gettext('Item Barcode Printing'); ?></a> </li>
			</ul>
	</td>
</tr>
</table>
<fieldset class="menuBox">
<div class="menuBoxInner printIcon">
    <?php echo gettext('Item Barcodes Printing'); ?> - <a target="blindSubmit" href="<?php echo MODULES_WEB_ROOT_DIR; ?>bibliography/item_barcode_generator.php?action=print" class="notAJAX headerText3"><?php echo gettext('Print Barcodes for Selected Data');?></a>
    &nbsp; <a target="blindSubmit" href="<?php echo MODULES_WEB_ROOT_DIR; ?>bibliography/item_barcode_generator.php?action=clear" class="notAJAX headerText3" style="color: #FF0000;"><?php echo gettext('Clear Print Queue'); ?></a>
   <p class="only_border">&nbsp;</p>
    <form name="search" action="<?php echo MODULES_WEB_ROOT_DIR; ?>bibliography/item_barcode_generator.php" id="search" method="get" style="display: inline;"><?php echo gettext('Search'); ?> :
  <!--commnet by iresh on 25-1-2011  <input type="text" name="keywords" id="keywords" size="30" />-->
   <!-- added by iresh on 25-1-2011 --> <input type="text" name="keywords" id="keywords" width=140px/>
    <input type="submit" id="doSearch" value="<?php echo gettext('Search'); ?>" class="button" />
    </form>
    <div style="margin-top: 3px;">
    <?php
    echo gettext('Maximum').' <font style="color: #FF0000">'.$max_print.'</font> '.gettext('records can be printed at once. Currently there is').' '; //mfc
    if (isset($_SESSION['barcodes']))
    {
        echo '<font id="queueCount" style="color: #FF0000">'.count($_SESSION['barcodes']).'</font>';
    }
    else 
    {
         echo '<font id="queueCount" style="color: #FF0000">0</font>';      
    }
    echo ' '.gettext('in queue waiting to be printed.'); //mfc
    ?>
    </div>
</div>
</fieldset>
<?php
/* search form end */
/* ITEM LIST */
require SIMBIO_BASE_DIR.'simbio_UTILS/simbio_tokenizecql.inc.php';
require LIB_DIR.'biblio_list.inc.php';
// table spec
$table_spec = 'item LEFT JOIN biblio ON item.biblio_id=biblio.biblio_id';
// create datagrid
$datagrid = new simbio_datagrid();
$datagrid->setSQLColumn('item.item_code',
    'item.item_code AS \''.gettext('Item Code').'\'',
    'biblio.title AS \''.gettext('Title').'\'',
    'biblio.classification AS \''.gettext('Classification').'\'');
$datagrid->setSQLorder('CAST(item.item_code AS SIGNED)');//biblio.title DESC
// is there any search
if (isset($_GET['keywords']) AND $_GET['keywords']) {
    $keywords = $dbs->escape_string(trim($_GET['keywords']));
    $searchable_fields = array('title', 'author', 'subject', 'itemcode');
    $search_str = '';
    // if no qualifier in fields
    if (!preg_match('@[a-z]+\s*=\s*@i', $keywords)) {
        foreach ($searchable_fields as $search_field) {
            $search_str .= $search_field.'='.$keywords.' OR ';
        }
    } else {
        $search_str = $keywords;
    }
    $biblio_list = new biblio_list($dbs);
    $criteria = $biblio_list->setSQLcriteria($search_str);
}
if (isset($criteria)) {
    $datagrid->setSQLcriteria('('.$criteria['sql_criteria'].')');
}
// set table and table header attributes
$datagrid->table_attr = 'align="center" id="dataList" cellpadding="5" cellspacing="0"';
$datagrid->table_header_attr = 'class="dataListHeader" style="font-weight: bold;"';
// edit and checkbox property
$datagrid->edit_property = false;
$datagrid->chbox_property = array('itemID', gettext('Add'));
$datagrid->chbox_action_button = gettext('Add To Print Queue');
$datagrid->chbox_confirm_msg = gettext('Add to print queue?');
$datagrid->column_width = array('10%', '80%', '10%');
// set checkbox action URL
$datagrid->chbox_form_URL = $_SERVER['PHP_SELF'];
// put the result into variables
$datagrid_result = $datagrid->createDataGrid($dbs, $table_spec, 24, $can_read);
if (isset($_GET['keywords']) AND $_GET['keywords']) {
    $msg = str_replace('{result->num_rows}', $datagrid->num_rows, gettext('Found <strong>{result->num_rows}</strong> from your keywords')); //mfc
    echo '<div class="infoBox">'.$msg.' : "'.$_GET['keywords'].'"<div>'.gettext('Query took').' <b>'.$datagrid->query_time.'</b> '.gettext('second(s) to complete').'</div></div>'; //mfc
}
echo $datagrid_result;
/* main content end */
?>
