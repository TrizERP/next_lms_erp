<?php

session_start();
/**
 *
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

/* Item List */

// main system configuration
require '../../../../sysconfig.inc.php';
// start the session
require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
require SENAYAN_BASE_DIR.'admin/default/session_check.inc.php';
// privileges checking
$can_read = utility::havePrivilege('reporting', 'r');
$can_write = utility::havePrivilege('reporting', 'w');

if (!$can_read) {
    die('<div class="errorBox">'.__('You don\'t have enough privileges to access this area!').'</div>');
}

require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/form_maker/simbio_form_element.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require MODULES_BASE_DIR.'reporting/report_dbgrid.inc.php';

$page_title = 'Items/Copies Report';
$reportView = false;
$num_recs_show = 20;
if (isset($_GET['reportView'])) {
    $reportView = true;
}

if (!$reportView) {
?>
<table  align=center>
<tr>
	<td valign=top>
	<?php
	$bradecum = '';       
        $basedir = basename(dirname(__FILE__));
      $bradecum = "<a href=javascript:void(0); onclick=javascript:new_set_home(); >Home</a>-><a class='' href=javascript:void(0); onclick=javascript:new_set('reporting');>"; 
	$query = "select module_name from mst_module where module_path = 'reporting'";
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
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'reporting/customs/book_verification_lost_report.php class="headerText2">Books Lost/Damage</a>';
echo $bradecum;
        ?>	
	</td>
</tr>
</table>
    <!-- filter -->
    <fieldset style="margin-bottom: 3px;">
    <legend style="font-weight: bold"><?php echo strtoupper(__('Books Lost/Damage')); ?> - <?php echo __('Report Filter'); ?></legend>
    <form method="get" action="<?php echo $_SERVER['PHP_SELF']; ?>" target="reportView">
    <div id="filterForm">
        <div class="divRow">
            <div class="divRowLabel"><?php echo __('Item Code'); ?></div>
            <div class="divRowContent">
            <?php  
           //comment by iresh on 25-1-2011  echo simbio_form_element::textField('text', 'itemCode', '', 'style="width: 50%"'); 
            /*added by iresh on 25-1-2011*/echo simbio_form_element::textField('text', 'itemCode', '', 'style="width: 140px"');   
            ?>
            </div>
            <div class="divRowLabel"><?php echo __('Academic Year'); ?></div>
            <div class="divRowContent">
            <?php
            $source_options[] = array('0', __('ALL'));
            $source_options[] = array('2019', __('2019'));
            $source_options[] = array('2020', __('2020'));
            $source_options[] = array('2021', __('2021'));
        
            
            echo simbio_form_element::selectList('academic_year', $source_options);
            ?>
            </div>
            <div class="divRowLabel"><?php echo __('Item Status'); ?></div>
            <div class="divRowContent">
            <?php
            $author_q = $dbs->query('SELECT item_status_id,item_status_name FROM mst_item_status');
            $author_options = array();
            $author_options[] = array('0', __('ALL'));
            while ($author_d = $author_q->fetch_row()) {
                $author_options[] = array($author_d[0], $author_d[1]);
            }
            echo simbio_form_element::selectList('itemStatus', $author_options);
            ?>
            </div>
        </div>
       
    </div>
    <div style="padding-top: 10px; clear: both;">
    <input type="submit" name="applyFilter" value="<?php echo __('Search'); ?>" />
    <!-- <input type="reset" name="applyReset" value="<?php echo __('Reset'); ?>" />	 -->
<!--added Ended by Parth 23/8/2011 -->
    <input type="hidden" name="reportView" value="true" />
    </div>
    </form>
    </fieldset>
    <script type="text/javascript">hideRows('filterForm', 1);</script>
    <!-- filter end -->
    <div class="dataListHeader" style="padding: 3px;"><span id="pagingBox"></span></div>
    <iframe name="reportView" id="reportView" src="<?php echo $_SERVER['PHP_SELF'].'?reportView=true'; ?>" frameborder="0" style="width: 100%; height:600px;"></iframe>
<?php
} else {
$host = $_SESSION['host'];
$user_name = $_SESSION['user_name'];
$passwd = $_SESSION['passwd'];
$dbname = $_SESSION['library_database'];

// Create connection
$conn = mysqli_connect($host, $user_name, $passwd, $dbname);

// Check connection
if ($mysqli_connect_errno) {
  die("Connection failed: " . $mysqli_connect_errno);
}
// echo "Connected successfully";
	
	
    $sql = "SELECT i.item_code,b.title,ct.coll_type_name,isd.remarks,s.item_status_name,isd.item_status_id
FROM item_scan_details as isd
left join item as i ON isd.item_code COLLATE utf8_general_ci = i.item_code COLLATE utf8_general_ci
LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id
LEFT JOIN mst_coll_type AS ct ON i.coll_type_id=ct.coll_type_id
INNER JOIN mst_item_status AS s ON isd.item_status_id=s.item_status_id
WHERE isd.scan_status = 'No' 
";

	if(isset($_REQUEST['itemCode']) && $_REQUEST['itemCode'] != ''){
		$sql .= " AND i.item_code = '".$_REQUEST['itemCode']."' ";
	}
	if(isset($_REQUEST['academic_year']) && $_REQUEST['academic_year'] != 0){
		$sql .= " AND isd.syear = '".$_REQUEST['academic_year']."' ";
	}
    if(isset($_REQUEST['itemStatus']) && $_REQUEST['itemStatus'] != 0){
        $sql .= " AND isd.item_status_id = '".$_REQUEST['itemStatus']."' ";
    }
	$sql .= " order by i.item_code
    "; //limit 0,20
	// echo $sql;
    $result = mysqli_query($conn,$sql) or die(mysqli_error());
	
	echo '<table>';
	$html = '<table>';
	echo $LO_columns = '<tr>
                <th>Sr.No.</th>
                <th>Item Code</th>
                <th>Title</th>
                <th>Collection Type</th>
                <th>Remarks</th>
                <th>Item Status</th>
			</tr>';
	$html .= $LO_columns;		
	$i = 1;
    while ($row = mysqli_fetch_array($result)) 
	{
		
		echo $RET = '<tr>
                <td>'.$i.'</td>
                <td>'.$row['item_code'].'</td>
                <td>'.$row['title'].'</td>
                <td>'.$row['coll_type_name'].'</td>
                <td>'.$row['remarks'].'</td>
                <td>'.$row['item_status_name'].'</td>
			</tr>';
		$html .= $RET;	
        $i++;

	}	
	echo '
	</table>';
	$html .= '</table>';

	echo '<form name="excel" method="post" action="exportExcel.php">';
	echo '<input type="hidden" name="hdnexcelVal" value="' . $html . '" />';
	echo '<input type="hidden" name="excelName" value="book_verification_lost_report" />';		
	echo '<input type="submit" value="Excel" name="sbtExcel" class="btn_medium">';
	echo "</FORM>";	
}
	
echo '<style>
table {
  font-family: arial, sans-serif;
  width: 100%;
  font-size: 13px;
}

td, th {
  border: 1px solid #dddddd;
  text-align: left;
  padding: 8px;
}

tr:nth-child(even) {
  background-color: #dddddd;
}
th{
	font-weight: bold;
	background: #F7F7F7;
	color: #1756DC;
	padding: 10px;
	font-size: 13px;
	text-transform: uppercase;
	border-right: 1px solid #CCCCCC;
}
</style>';

?>
