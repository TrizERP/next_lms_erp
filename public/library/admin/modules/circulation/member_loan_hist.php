<?php
require '../../../sysconfig.inc.php';
// start the session
//require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
require SENAYAN_BASE_DIR.'admin/default/session_check.inc.php';

if (!isset($_SESSION['memberID'])) { die(); }

require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/datagrid/simbio_dbgrid.inc.php';

// page title
$page_title = 'Member Loan List';

// start the output buffering
ob_start();
// check if there is member ID
if (isset($_SESSION['memberID']) AND !empty($_SESSION['memberID'])) {
    /* LOAN HISTORY LIST */
    $memberID = trim($_SESSION['memberID']);
    // table spec
    $table_spec = 'loan AS l
        LEFT JOIN item AS i ON l.item_code=i.item_code
        LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id';

    // create datagrid
    $datagrid = new simbio_datagrid();
    $datagrid->setSQLColumn(
        'l.item_code AS \''.gettext('Item Code').'\'',
        'b.title AS \''.gettext('Title').'\'',
        'DATE_FORMAT(l.loan_date,"%d-%m-%Y") AS \''.gettext('Issue Date').'\'',
        'IF(return_date IS NULL, \'<i>'.gettext('Not Returned Yet').'</i>\', DATE_FORMAT(return_date,"%d-%m-%Y")) AS \''.gettext('Return Date').'\'');
    $datagrid->setSQLorder("l.loan_date DESC");

    $criteria = 'l.member_id=\''.$dbs->escape_string($memberID).'\' ';
    // is there any search
    if (isset($_GET['keywords']) AND $_GET['keywords']) {
        $keyword = $dbs->escape_string($_GET['keywords']);
        $criteria .= " AND (l.item_code LIKE '%$keyword%' OR b.title LIKE '%$keyword%')";
    }
    $datagrid->setSQLCriteria($criteria);

    // set table and table header attributes
    $datagrid->table_attr = 'align="center" id="dataList" cellpadding="0" cellspacing="0" style="width: 100%;"';
    $datagrid->table_header_attr = 'class="dataListHeader" style="font-weight: bold;"';
    $datagrid->icon_edit = SENAYAN_WEB_ROOT_DIR.'admin/'.$sysconf['admin_template']['dir'].'/'.$sysconf['admin_template']['theme'].'/edit.gif';
    // special properties
    $datagrid->using_AJAX = false;
    $datagrid->column_width = array(1 => '50%');
    $datagrid->disableSort('Return Date');

    // put the result into variables
    $datagrid_result = $datagrid->createDataGrid($dbs, $table_spec, 20, false);
    if (isset($_GET['keywords']) AND $_GET['keywords']) {
        $msg = str_replace('{result->num_rows}', $datagrid->num_rows, gettext('Found <strong>{result->num_rows}</strong> from your keywords')); //mfc
        echo '<div class="infoBox">'.$msg.' : "'.$_GET['keywords'].'"</div>';
    }

    echo $datagrid_result;
}

// get the buffered content
$content = ob_get_clean();
//echo '<div><a style="color:#990000;" target="_blank" href="export_loan_historydata_excel.php?memberid='.$memberID.'" title="Click To View File"><strong>[Export To Excel]</strong></a></div>';
// include the page template
//echo SENAYAN_BASE_DIR.'/admin/'.$sysconf['admin_template']['dir'].'/notemplate_page_tpl.php';die;
require SENAYAN_BASE_DIR.'/admin/admin_template/notemplate_page_tpl.php';
?>
