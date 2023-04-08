<!--<?php
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

/* loan list iframe content */

// main system configuration
require '../../../sysconfig.inc.php';
// start the session
require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
require SENAYAN_BASE_DIR.'admin/default/session_check.inc.php';

if (!isset($_SESSION['memberID'])) { die(); }

require SIMBIO_BASE_DIR.'simbio_GUI/form_maker/simbio_form_table_AJAX.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/simbio_dbop.inc.php';
require SIMBIO_BASE_DIR.'simbio_UTILS/simbio_date.inc.php';
require MODULES_BASE_DIR.'membership/member_base_lib.inc.php';
require MODULES_BASE_DIR.'circulation/circulation_base_lib.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/datagrid/simbio_dbgrid.inc.php';

// page title
$page_title = 'Member Loan List';

// start the output buffering
ob_start();
?>

<?php

  // create datagrid
    $datagrid = new simbio_datagrid();
    
        $datagrid->setSQLColumn('biblio.biblio_id', 'biblio.biblio_id AS bid',
            'biblio.title AS \''.__('Title').'\'',
            'biblio.isbn_issn AS \''.__('ISBN/ISSN').'\'',
            'IF(COUNT(item.item_id)>0, COUNT(item.item_id), \'<strong style="color: #FF0000;">'.__('None').'</strong>\') AS \''.__('Copies').'\'',
            'biblio.last_update AS \''.__('Last Update').'\'');
        
   
    $datagrid->invisible_fields = array(0);
    $datagrid->setSQLorder('biblio.last_update DESC');

    // is there any search
    if (isset($_GET['keywords']) AND $_GET['keywords']) {
        $keywords = $dbs->escape_string(trim($_GET['keywords']));
        $searchable_fields = array('title', 'author', 'subject', 'isbn', 'publisher');
        if ($_GET['field'] != '0' AND in_array($_GET['field'], $searchable_fields)) {
            $field = $_GET['field'];
            $search_str = $field.'='.$keywords;
        } else {
            $search_str = '';
            foreach ($searchable_fields as $search_field) {
                $search_str .= $search_field.'='.$keywords.' OR ';
            }
            $search_str = substr_replace($search_str, '', -4);
        }

        $biblio_list = new biblio_list($dbs);
        $criteria = $biblio_list->setSQLcriteria($search_str);
    }

    if (isset($criteria)) {
        $datagrid->setSQLcriteria('('.$criteria['sql_criteria'].')');
    }
    // table spec
    $table_spec = 'biblio LEFT JOIN item ON biblio.biblio_id=item.biblio_id';

    // set group by
    $datagrid->sql_group_by = 'biblio.biblio_id';

    // set table and table header attributes
    $datagrid->table_attr = 'align="center" id="dataList" cellpadding="5" cellspacing="0"';
    $datagrid->table_header_attr = 'class="dataListHeader" style="font-weight: bold;"';
    // set delete proccess URL
    $datagrid->chbox_form_URL = $_SERVER['PHP_SELF'];
    $datagrid->debug = true;

    $biblio_result_num = ($sysconf['biblio_result_num']>100)?100:$sysconf['biblio_result_num'];
    // put the result into variables
    $datagrid_result = $datagrid->createDataGrid($dbs, $table_spec, $biblio_result_num);
    if (isset($_GET['keywords']) AND $_GET['keywords']) {
        $msg = str_replace('{result->num_rows}', $datagrid->num_rows, __('Found <strong>{result->num_rows}</strong> from your keywords')); //mfc
        echo '<div class="infoBox">'.$msg.' : "'.$_GET['keywords'].'"<div>'.__('Query took').' <b>'.$datagrid->query_time.'</b> '.__('second(s) to complete').'</div></div>'; //mfc
    }

    echo $datagrid_result;

// check if there is member ID
/*if (isset($_SESSION['memberID'])) {
    $memberID = trim($_SESSION['memberID']);
    $circulation = new circulation($dbs, $memberID);
    
$loan_list_query = $dbs->query("SELECT L.temp_id, b.title, i.item_code, L.request_date,L.status FROM temp AS L
        LEFT JOIN item AS i ON L.item_code=i.item_code
        LEFT JOIN mst_coll_type AS ct ON i.coll_type_id=ct.coll_type_id
        LEFT JOIN member AS m ON L.member_id=m.member_id
        LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id
        WHERE  L.member_id='$memberID'");

    // create table object
    $loan_list = new simbio_table();
    $loan_list->table_attr = 'align="center" width="100%" cellpadding="3" cellspacing="0"';
    $loan_list->table_header_attr = 'class="dataListHeader" style="font-weight: bold;"';
    $loan_list->highlight_row = true;
    // table header
    $headers = array( __('Item Code'), __('Title'), __('Request Date'), __('Status'));
    $loan_list->setHeader($headers);
    // row number init
    $row = 1;
    while ($loan_list_data = $loan_list_query->fetch_assoc()) {
        // alternate the row color
        $row_class = ($row%2 == 0)?'alterCell':'alterCell2';
	
      
  
        // row colums array
        $fields = array(

            $loan_list_data['item_code'],
            $loan_list_data['title'],
            $loan_list_data['request_date'],
            $loan_list_data['status']
	   
            );

        // append data to table row
        $loan_list->appendTableRow($fields);
        // set the HTML attributes
        $loan_list->setCellAttr($row, null, "valign='top' class='$row_class'");
        $loan_list->setCellAttr($row, 0, "valign='top' align='center' class='$row_class' style='width: 5%;'");
        $loan_list->setCellAttr($row, 1, "valign='top' align='center' class='$row_class' style='width: 5%;'");
        $loan_list->setCellAttr($row, 2, "valign='top' class='$row_class' style='width: 10%;'");
        $loan_list->setCellAttr($row, 3, "valign='top' class='$row_class' style='width: 55%;'");

        $row++;
    }

    // show reservation alert if any
    if (isset($_GET['reserveAlert']) AND !empty($_GET['reserveAlert'])) {
        $reservedItem = trim($_GET['reserveAlert']);
        // get reservation data
        $reserve_q = $dbs->query('SELECT r.member_id, m.member_name
            FROM reserve AS r
            LEFT JOIN member AS m ON r.member_id=m.member_id
            WHERE item_code=\''.$reservedItem.'\' ORDER BY reserve_date DESC');
        $reserve_d = $reserve_q->fetch_row();
        $member = $reserve_d[1].' ('.$reserve_d[0].')';
        $reserve_msg = str_replace(array('{itemCode}', '{member}'), array('<b>'.$reservedItem.'</b>', '<b>'.$member.'</b>'), __('Item {itemCode} is being reserved by member {member}')); //mfc
        echo '<div class="infoBox">'.$reserve_msg.'</div>';
    }
    echo $loan_list->printTable();
    // hidden form for return and extend process
    echo '<form name="loanHiddenForm" method="post" action="circulation_action.php"><input type="hidden" name="process" value="return" /><input type="hidden" name="loanID" value="" /></form>';
}

// get the buffered content
$content = ob_get_clean();
// include the page template
require SENAYAN_BASE_DIR.'/admin/'.$sysconf['admin_template']['dir'].'/notemplate_page_tpl.php';*/
?>

-->
<?php

require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
if (isset($_SESSION['memberID'])) {
    $memberID = trim($_SESSION['memberID']);
    $circulation = new circulation($dbs, $memberID);
    
$loan_list_query = $dbs->query("SELECT L.temp_id, b.title, i.item_code, L.request_date,L.status FROM temp AS L
        LEFT JOIN item AS i ON L.item_code=i.item_code
        LEFT JOIN mst_coll_type AS ct ON i.coll_type_id=ct.coll_type_id
        LEFT JOIN member AS m ON L.member_id=m.member_id
        LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id
        WHERE  L.member_id='$memberID'");

    // create table object
    $loan_list = new simbio_table();
    $loan_list->table_attr = 'align="center" width="100%" cellpadding="3" cellspacing="0"';
    $loan_list->table_header_attr = 'class="dataListHeader" style="font-weight: bold;"';
    $loan_list->highlight_row = true;
    // table header
    $headers = array( __('Item Code'), __('Title'), __('Request Date'), __('Status'));
    $loan_list->setHeader($headers);
    // row number init
    $row = 1;
    while ($loan_list_data = $loan_list_query->fetch_assoc()) {
        // alternate the row color
        $row_class = ($row%2 == 0)?'alterCell':'alterCell2';
	
      
  
        // row colums array
        $fields = array(

            $loan_list_data['item_code'],
            $loan_list_data['title'],
            $loan_list_data['request_date'],
            $loan_list_data['status']
	   
            );

        // append data to table row
        $loan_list->appendTableRow($fields);
        // set the HTML attributes
        $loan_list->setCellAttr($row, null, "valign='top' class='$row_class'");
        $loan_list->setCellAttr($row, 0, "valign='top' align='center' class='$row_class' style='width: 5%;'");
        $loan_list->setCellAttr($row, 1, "valign='top' align='center' class='$row_class' style='width: 5%;'");
        $loan_list->setCellAttr($row, 2, "valign='top' class='$row_class' style='width: 10%;'");
        $loan_list->setCellAttr($row, 3, "valign='top' class='$row_class' style='width: 55%;'");

        $row++;
    }

    // show reservation alert if any
    if (isset($_GET['reserveAlert']) AND !empty($_GET['reserveAlert'])) {
        $reservedItem = trim($_GET['reserveAlert']);
        // get reservation data
        $reserve_q = $dbs->query('SELECT r.member_id, m.member_name
            FROM reserve AS r
            LEFT JOIN member AS m ON r.member_id=m.member_id
            WHERE item_code=\''.$reservedItem.'\' ORDER BY reserve_date DESC');
        $reserve_d = $reserve_q->fetch_row();
        $member = $reserve_d[1].' ('.$reserve_d[0].')';
        $reserve_msg = str_replace(array('{itemCode}', '{member}'), array('<b>'.$reservedItem.'</b>', '<b>'.$member.'</b>'), __('Item {itemCode} is being reserved by member {member}')); //mfc
        echo '<div class="infoBox">'.$reserve_msg.'</div>';
    }
    echo $loan_list->printTable();
    // hidden form for return and extend process
    echo '<form name="loanHiddenForm" method="post" action="circulation_action.php"><input type="hidden" name="process" value="return" /><input type="hidden" name="loanID" value="" /></form>';
}

// get the buffered content
$content = ob_get_clean();
// include the page template
require SENAYAN_BASE_DIR.'/admin/'.$sysconf['admin_template']['dir'].'/notemplate_page_tpl.php';
?>





