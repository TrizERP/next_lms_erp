<?php
session_start();
/* Item Status Management section */

// echo '<pre>';
// print_r($_REQUEST);

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
// require SIMBIO_BASE_DIR.'simbio_GUI/form_maker/simbio_form_element.inc.php';
require MODULES_BASE_DIR.'reporting/report_dbgrid.inc.php';


// privileges checking
$can_read = utility::havePrivilege('master_file', 'r');
$can_write = utility::havePrivilege('master_file', 'w');

// echo '<pre>';
// print_r($_REQUEST);
// echo 'can_read '.$can_read;
if (!$can_read) {
    die('<div class="errorBox">'.__('You don\'t have enough privileges to access this area!').'</div>');
}

// item status rules
$rules_option[] = array(NO_LOAN_TRANSACTION, __('No Books'));

/* RECORD OPERATION */
if (isset($_REQUEST['keywords_for_scan_book']) AND $_REQUEST['keywords_for_scan_book'] != '') 
{
    // if (!($can_read AND $can_write)) 
    // {
    //     echo '1';
    //     die();
    // }
    /* DATA DELETION PROCESS */
    $sql_op = new simbio_dbop($dbs);
    
    $itemCode = $_REQUEST['keywords_for_scan_book'];
    //  echo "INSERT INTO item_scan_details(item_code,scan_status,created_by,created_ip) values('".$itemCode."','Yes','".$_SESSION['ID']."','".$_SERVER['REMOTE_ADDR']."')";die;
        $sql_op->custom_update("INSERT INTO item_scan_details(item_code,scan_status,created_by,created_ip) values('".$itemCode."','Yes','".$_SESSION['ID']."','".$_SERVER['REMOTE_ADDR']."')");
    // error alerting
    if ($sql_op->affected_rows == 1 && $sql_op->affected_rows != 0) {
        utility::jsAlert(__('Book Scaned Successfully'));
        // echo '<script type="text/javascript">parent.setContent(\'mainContent\', \''.$_SERVER['PHP_SELF'].'?'.$itemCode.'\', \'post\');</script>';
    } else {
        utility::jsAlert(__('Some or All Data NOT deleted successfully! Please contact system administrator'));
        // echo '<script type="text/javascript">parent.setContent(\'mainContent\', \''.$_SERVER['PHP_SELF'].'?'.$itemCode.'\', \'post\');</script>';
    }
    // exit();
} 
/* item status update process end */

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
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'circulation/scan_item.php class="headerText2">Scan Books</a>';
echo $bradecum;
        ?>	
	</td>
</tr>
</table>
</table>
<table>
<tr>
	<td class="tab_menu_top">
      <ul class="tabs"> 

<li> 
<a href="<?php echo MODULES_WEB_ROOT_DIR; ?>circulation/scan_item.php" class="headerText2"><?php echo __('Scan Books'); ?></a> </li>
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
    <p class="only_border">&nbsp;</p>
    <form name="search" action="<?php echo MODULES_WEB_ROOT_DIR; ?>circulation/scan_item.php" id="search" method="get" style="display: inline;"><?php echo __('Item Code'); ?> :
    <input type="text" name="keywords_for_scan_book" size="30" required />
    <input type="submit" id="doSearch" value="<?php echo __('Submit'); ?>" class="button" />
    </form>
</div>
</fieldset>

<?
}
?>

<?php

if(isset($_REQUEST['keywords_for_scan_book'])){
    
    ob_start();
    // table spec
    $table_spec = 'item AS i
        LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id
        LEFT JOIN mst_coll_type AS ct ON i.coll_type_id=ct.coll_type_id
        LEFT JOIN mst_item_status AS s ON i.item_status_id=s.item_status_id
        LEFT JOIN item_scan_details AS isd ON i.item_code collate utf8_general_ci  = isd.item_code collate utf8_general_ci 
        ';
     // LEFT JOIN item_scan_details AS isd ON i.item_code collate utf8_general_ci  = isd.item_code collate utf8_general_ci 
    // create datagrid
    $reportgrid = new report_datagrid();

    $reportgrid->setSQLColumn('i.item_code AS \''.__('Item Code').'\'',
        'b.title AS \''.__('Title').'\'',
        'ct.coll_type_name AS \''.__('Collection Type').'\'',
        'isd.scan_status AS \''.__('Scan Status').'\'',
        // 's.item_status_name AS \''.__('Item Status').'\'',
        'i.biblio_id');
    $reportgrid->setSQLorder('i.item_code ASC');
    // echo '<pre>';
    // print_r($reportgrid);
    // is there any search
    $criteria = 'b.biblio_id IS NOT NULL ';
    
    if (isset($_GET['keywords_for_scan_book']) AND !empty($_GET['keywords_for_scan_book'])) {
        $item_code = $dbs->escape_string(trim($_GET['keywords_for_scan_book']));
        $criteria .= ' AND i.item_code LIKE \'%'.$item_code.'%\'';
    }
    // echo $table_spec.$criteria;
    $reportgrid->setSQLCriteria($criteria);
    
    // callback function to show title and authors
    function showTitleAuthors($obj_db, $array_data)
    {
        // author name query
        $_biblio_q = $obj_db->query('SELECT b.title, a.author_name FROM biblio AS b
            LEFT JOIN biblio_author AS ba ON b.biblio_id=ba.biblio_id
            LEFT JOIN mst_author AS a ON ba.author_id=a.author_id
            WHERE b.biblio_id='.$array_data[4]);
        $_authors = '';
        while ($_biblio_d = $_biblio_q->fetch_row()) {
            $_title = $_biblio_d[0];
            $_authors .= $_biblio_d[1].' - ';
        }
        $_authors = substr_replace($_authors, '', -3);
        $_output = $_title.'<br /><i>'.$_authors.'</i>'."\n";
        return $_output;
    }
    // modify column value
    $reportgrid->modifyColumnContent(1, 'callback{showTitleAuthors}');

    // $reportgrid->invisible_fields = array(4);

    echo $reportgrid->createDataGrid($dbs, $table_spec, 20);
    //echo $peging = '<div style="text-align: center;">'.simbio_paging::paging($reportgrid->num_rows, $num_recs_show, 5).'</div>';
    echo '<script type="text/javascript">'."\n";
    echo 'parent.$(\'pagingBox\').update(\''.str_replace(array("\n", "\r", "\t"), '', $reportgrid->paging_set).'\');'."\n";
    echo '</script>';
   

    $content = ob_get_clean();
    // include the page template
    require SENAYAN_BASE_DIR.'/admin/'.$sysconf['admin_template']['dir'].'/printed_page_tpl.php';

}
?>
