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
    die('<div class="errorBox">'.gettext('You don\'t have enough privileges to access this area!').'</div>');
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
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'reporting/customs/all_book_list.php class="headerText2">All Book List</a>';
echo $bradecum;
        ?>	
	</td>
</tr>
</table>
    <!-- filter -->
    <fieldset style="margin-bottom: 3px;">
    <legend style="font-weight: bold"><?php echo strtoupper(gettext('All Book List')); ?> - <?php echo gettext('Report Filter'); ?></legend>
    <form method="get" action="<?php echo $_SERVER['PHP_SELF']; ?>" target="reportView">
    <div id="filterForm">
        <div class="divRow">
            <div class="divRowLabel"><?php echo gettext('Title'); ?></div>
            <div class="divRowContent">
            <?php
           //comment by iresh on 25-1-2011 echo simbio_form_element::textField('text', 'title', '', 'style="width: 50%"'); 
           /*added by iresh on 25-1-2011*/ echo simbio_form_element::textField('text', 'title', '', 'style="width: 140px"'); 
            ?>
            </div>
        </div>
        <div class="divRow">
            <div class="divRowLabel"><?php echo gettext('Accession Register'); ?></div>
            <div class="divRowContent">
            <?php  
           //comment by iresh on 25-1-2011  echo simbio_form_element::textField('text', 'itemCode', '', 'style="width: 50%"'); 
            /*added by iresh on 25-1-2011*/echo simbio_form_element::textField('text', 'itemCode', '', 'style="width: 140px"');   
            ?>
            </div>
        </div>
		<div class="divRow">
            <div class="divRowLabel"><?php echo gettext('Donated Register'); ?></div>
            <div class="divRowContent">
           <?php
            $source_options[] = array('0', gettext('ALL'));
			$source_options[] = array('1', gettext('Buy'));
			$source_options[] = array('2', gettext('Prize/Grant'));
        
            
            echo simbio_form_element::selectList('source', $source_options);
            ?>
            </div>
        </div>
		<div class="divRow">
            <div class="divRowLabel"><?php echo gettext('Author'); ?></div>
            <div class="divRowContent">
            <?php
            $author_q = $dbs->query('SELECT author_id,author_name FROM mst_author');
            $author_options = array();
            $author_options[] = array('0', gettext('ALL'));
            while ($author_d = $author_q->fetch_row()) {
                $author_options[] = array($author_d[0], $author_d[1]);
            }
            echo simbio_form_element::selectList('author', $author_options);
            ?>
            </div>
        </div>
		<div class="divRow">
            <div class="divRowLabel"><?php echo gettext('Publisher'); ?></div>
            <div class="divRowContent">
            <?php
            $publisher_q = $dbs->query('SELECT publisher_id,publisher_name FROM mst_publisher');
            $publisher_options = array();
            $publisher_options[] = array('0', gettext('ALL'));
            while ($publisher_d = $publisher_q->fetch_row()) {
                $publisher_options[] = array($publisher_d[0], $publisher_d[1]);
            }
            echo simbio_form_element::selectList('publisher', $publisher_options);
            ?>
            </div>
        </div>
		<div class="divRow">
            <div class="divRowLabel"><?php echo gettext('Subject'); ?></div>
            <div class="divRowContent">
            <?php
            $subject_q = $dbs->query('SELECT topic_id,topic FROM mst_topic');
            $subject_options = array();
            $subject_options[] = array('0', gettext('ALL'));
            while ($subject_d = $subject_q->fetch_row()) {
                $subject_options[] = array($subject_d[0], $subject_d[1]);
            }
            echo simbio_form_element::selectList('subject', $subject_options);
            ?>
            </div>
        </div>
        <div class="divRow">
            <div class="divRowLabel"><?php echo gettext('Classification'); ?></div>
            <div class="divRowContent">
            <?php 
           //comment by iresh on 25-1-2011  echo simbio_form_element::textField('text', 'class', '', 'style="width: 50%"'); 
           /*added by iresh on 25-1-2011*/ echo simbio_form_element::textField('text', 'class', '', 'style="width: 140px"'); 
            ?>
            </div>
        </div>
        <div class="divRow">
            <div class="divRowLabel"><?php echo gettext('Book Type'); ?></div>
            <div class="divRowContent">
            <?php
            $coll_type_q = $dbs->query('SELECT coll_type_id, coll_type_name FROM mst_coll_type');
            $coll_type_options = array();
            $coll_type_options[] = array('0', gettext('ALL'));
            while ($coll_type_d = $coll_type_q->fetch_row()) {
                $coll_type_options[] = array($coll_type_d[0], $coll_type_d[1]);
            }
            echo simbio_form_element::selectList('collType', $coll_type_options, '');//, 'multiple="multiple" size="5"'
            ?>
            </div>
        </div>
        <!--<div class="divRow">
            <div class="divRowLabel"><?php echo gettext('Item Status'); ?></div>
            <div class="divRowContent">
            <?php
            // $status_q = $dbs->query('SELECT item_status_id, item_status_name FROM mst_item_status');
            // $status_options = array();
            // $status_options[] = array('0', __('ALL'));
            // while ($status_d = $status_q->fetch_row()) {
                // $status_options[] = array($status_d[0], $status_d[1]);
            // }
            // echo simbio_form_element::selectList('status', $status_options);
            ?>
            </div>
        </div>
        <div class="divRow">
            <div class="divRowLabel"><?php echo __('Location'); ?></div>
            <div class="divRowContent">
            <?php
            // $loc_q = $dbs->query('SELECT location_id, location_name FROM mst_location');
            // $loc_options = array();
            // $loc_options[] = array('0', __('ALL'));
            // while ($loc_d = $loc_q->fetch_row()) {
                // $loc_options[] = array($loc_d[0], $loc_d[1]);
            // }
            // echo simbio_form_element::selectList('location', $loc_options);
            ?>
            </div>
        </div>-->
        <!--<div class="divRow">
            <div class="divRowLabel"><?php echo __('Record each page'); ?></div>
            <div class="divRowContent"><input type="text" name="recsEachPage" size="3" maxlength="3" value="<?php echo $num_recs_show; ?>" /> <?php echo __('Set between 20 and 200'); ?></div>
        </div>-->
    </div>
    <div style="padding-top: 10px; clear: both;">
    <input type="submit" name="applyFilter" value="<?php echo gettext('Search'); ?>" />
    <input type="button" name="moreFilter" value="<?php echo gettext('Advance Search'); ?>" onclick="showHideTableRows('filterForm', 1, this, '<?php echo gettext('Show More Search Options'); ?>', '<?php echo gettext('Hide Search Options'); ?>')" />
<!--added Started by Parth 23/8/2011 -->
    <input type="reset" name="applyReset" value="<?php echo gettext('Reset'); ?>" />	
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
	
	$sql = "SELECT i.item_code as Accession_Number,date_format(i.input_date,'%d-%m-%Y') as date,b.series_title as Series,
				b.title as Title,b.sub_title as Sub_Title,a.author_name as Author,b.tags as Volumn_No,l.language_name as Language,p.publisher_name as Publisher,
				b.isbn_issn as ISBN_No,b.classification as Classification_No,ct.coll_type_name as Book_Type,t.topic as Subject,
				b.pages as Pages,b.edition as Edition,pp.place_name as Publication_Place,b.publish_year as Year_published,i.price as Original_Cost,ms.supplier_name as Vendor,date_format(i.order_date,'%d-%m-%Y') as Purchase_Date,b.spec_detail_info as Note
				FROM item AS i
				LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id
				LEFT JOIN mst_coll_type AS ct ON i.coll_type_id=ct.coll_type_id
				LEFT JOIN mst_item_status AS s ON i.item_status_id=s.item_status_id
				LEFT JOIN mst_author AS a ON a.author_id=i.author_id
				LEFT JOIN mst_publisher AS p ON p.publisher_id = i.publisher_id
				LEFT JOIN mst_language As l ON l.language_id collate utf8_general_ci = b.language_id collate utf8_general_ci
				LEFT JOIN mst_topic AS t ON t.topic_id = b.subject
				LEFT JOIN mst_place AS pp ON pp.place_id = b.publish_place_id
				LEFT JOIN mst_supplier as ms ON ms.supplier_id = i.supplier_id
				WHERE 1=1 ";
	
	if(isset($_REQUEST['title']) && $_REQUEST['title'] != ''){
		$sql .= " AND b.title like '%".$_REQUEST['title']."%' ";
	}
	if(isset($_REQUEST['itemCode']) && $_REQUEST['itemCode'] != ''){
		$sql .= " AND i.item_code = '".$_REQUEST['itemCode']."' ";
	}
	if(isset($_REQUEST['source']) && $_REQUEST['source'] != 0){
		$sql .= " AND i.source = '".$_REQUEST['source']."' ";
	}
	if(isset($_REQUEST['author']) && $_REQUEST['author'] != 0){
		$sql .= " AND a.author_id = '".$_REQUEST['author']."' ";
	}
	if(isset($_REQUEST['publisher']) && $_REQUEST['publisher'] != 0){
		$sql .= " AND p.publisher_id = '".$_REQUEST['publisher']."' ";
	}
	if(isset($_REQUEST['subject']) && $_REQUEST['subject'] != 0){
		$sql .= " AND t.topic_id = '".$_REQUEST['subject']."' ";
	}
	if(isset($_REQUEST['class']) && $_REQUEST['class'] != 0){
		$sql .= " AND b.classification = '".$_REQUEST['class']."' ";
	}
	if(isset($_REQUEST['collType']) && $_REQUEST['collType'] != ''){
		$sql .= " AND ct.coll_type_id = '".$_REQUEST['collType']."' ";
	}
    $result = mysqli_query($conn,$sql) or die(mysqli_error());
	
	echo '<table>';
	$html = '<table>';
	echo $LO_columns = '<tr>
                <th>Sr.No.</th>
				<th>Accession Number</th>
				<th>Date</th>
				<th>Series</th>
				<th>Title</th>
				<th>Sub Title</th>
				<th>Author</th>
				<th>Volumn No.</th>
				<th>Language</th>
				<th>Publisher</th>
				<th>ISBN No.</th>
				<th>Classification No.</th>
				<th>Book type</th>
				<th>Subject</th>
				<th>Pages</th>
				<th>Edition</th>
				<th>Publication Place</th>
				<th>Year published</th>
				<th>Original Cost</th>
				<th>Vendor</th>
				<th>Purchased Date</th>
				<th>Note</th>
			</tr>';
	$html .= $LO_columns;		
	$i = 1;
    while ($row = mysqli_fetch_array($result)) 
	{
		// echo '<pre>';
		// echo 'divv';
		// print_r($row);
		
		echo $RET = '<tr>
                <td>'.$i.'</td>
				<td>'.$row['Accession_Number'].'</td>
				<td>'.$row['date'].'</td>
				<td>'.$row['Series'].'</td>
				<td>'.$row['Title'].'</td>
				<td>'.$row['Sub_Title'].'</td>
				<td>'.$row['Author'].'</td>
				<td>'.$row['Volumn_No'].'</td>
				<td>'.$row['Language'].'</td>
				<td>'.$row['Publisher'].'</td>
				<td>'.$row['ISBN_No'].'</td>
				<td>'.$row['Classification_No'].'</td>
				<td>'.$row['Book_Type'].'</td>
				<td>'.$row['Subject'].'</td>
				<td>'.$row['Pages'].'</td>
				<td>'.$row['Edition'].'</td>
				<td>'.$row['Publication_Place'].'</td>
				<td>'.$row['Year_published'].'</td>
				<td>'.$row['Original_Cost'].'</td>
				<td>'.$row['Vendor'].'</td>
				<td>'.$row['Purchase_Date'].'</td>
				<td>'.$row['Note'].'</td>
			</tr>';
		$html .= $RET;	
        $i++;

	}	
	echo '
	</table>';
	$html .= '</table>';

	echo '<form name="excel" method="post" action="exportExcel.php">';
	echo '<input type="hidden" name="hdnexcelVal" value="' . $html . '" />';
	echo '<input type="hidden" name="excelName" value="all_book_list" />';		
	echo '<input type="submit"  value="Excel" name="sbtExcel" class="btn_medium">';
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
