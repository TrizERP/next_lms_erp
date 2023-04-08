<?php
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
require SIMBIO_BASE_DIR.'simbio_DB/simbio_dbop.inc.php';
//////////////////////////////////





//////////////////////////////////////////////////////////////

// privileges checking
/*$can_read = utility::havePrivilege('circulation', 'r');
$can_write = utility::havePrivilege('circulation', 'w');

if (!($can_read AND $can_write)) {
    die('<div class="errorBox">'.__('You don\'t have enough privileges to view this section').'</div>');
}

if (!isset($_SESSION['memberID'])) { die(); }

require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO_BASE_DIR.'simbio_UTILS/simbio_date.inc.php';

// page title
$page_title = 'Member Loan List';

ob_start();




if (isset($_SESSION['memberID'])) {
    $memberID = trim($_SESSION['memberID']);
   
    
$loan_list_query = $dbs->query("SELECT L.temp_id, b.title, i.item_code, L.request_date,L.status FROM temp AS L
        LEFT JOIN item AS i ON L.item_code=i.item_code
        LEFT JOIN mst_coll_type AS ct ON i.coll_type_id=ct.coll_type_id
        LEFT JOIN member AS m ON L.member_id=m.member_id
        LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id
        WHERE  L.member_id='$memberID'");

  /*  // create table object
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
    }*/

//$memberID = trim($_SESSION['memberID']);
$loan_list_query = $dbs->query("SELECT L.temp_id, b.title, i.item_code, L.request_date,L.status,m.member_id,m.member_name FROM temp AS L
        LEFT JOIN item AS i ON L.item_code=i.item_code
        LEFT JOIN mst_coll_type AS ct ON i.coll_type_id=ct.coll_type_id
        LEFT JOIN member AS m ON L.member_id=m.member_id
        LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id
       ");
  echo'<form method="post">';	
  echo "<table border='0' width=100% cellspacing=0>";
  echo "<tr class='dataListHeader'><td></td><td align=center>Item Code</td><td align=center>Title</td><td><align=center>Member Id</td><td><align=center>Member Name</td><td align=center>Request Date</td><td align=center>Status</td></tr>";
 
  
  $i='';	
  while ($loan_list_data = $loan_list_query->fetch_assoc())
 {
	if($i%2==0)
		{
			$style="background-color:#DEDEDC";
		}
		else
		{
			$style="background-color:#C1C1C1";
		}
	echo "<tr  style='".$style."'>";
        echo "<td><input type=checkbox name=values[$loan_list_data[temp_id]] id=checkbox[] value=". $loan_list_data['temp_id']." ></td>"; 
	echo "<td align=center>" . $loan_list_data['item_code'] ."</td>";
	echo "<td align=center>" . $loan_list_data['title'] ."</td>";
	echo "<td align=center>" . $loan_list_data['member_id'] ."</td>";
	echo "<td align=center>" . $loan_list_data['member_name'] ."</td>";
	echo "<td align=center>" . $loan_list_data['request_date'] ."</td>";
        echo "<td align=center>" . $loan_list_data['status'] ."</td>";
	
	echo "</tr>";
       $i++;
}
echo "<tr><td><td><td><td><input type=submit name='Confirm' value='Confirm' align=center></td></tr>";	
echo "</table>";
echo"</form>";


// get the buffered content
$content = ob_get_clean();
// include the page template
require SENAYAN_BASE_DIR.'/admin/'.$sysconf['admin_template']['dir'].'/notemplate_page_tpl.php';
?>





