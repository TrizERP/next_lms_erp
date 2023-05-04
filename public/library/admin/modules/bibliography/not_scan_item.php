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
// echo '</pre>';
// die;

if (!$can_read) {
    die('<div class="errorBox">'.gettext('You don\'t have enough privileges to access this area!').'</div>');
}

// item status rules
$rules_option[] = array(NO_LOAN_TRANSACTION, gettext('No Books'));

/* RECORD OPERATION */
if (isset($_REQUEST['submit_value']) AND $_REQUEST['submit_value'] != '' && $_REQUEST['submit_value'] == 'Save') 
{
    $sql_op = new simbio_dbop($dbs);

    // loop array
    // echo '<pre>';
    // print_r($_REQUEST);
    // die;
    foreach ($_REQUEST['remarks'] as $itemcode => $val) 
    {
        
        $remarks = $val;    
        $item_status_id = $_REQUEST['item_status'][$itemcode];

        // $item_status = '';
        if($item_status_id != '' && $item_status_id != 'Select Item Status')
        {
             // $item_status = $item_status_id;
            // $sql_op->custom_update("UPDATE item SET item_status_id='".$item_status_id."',remarks = '".$remarks."' WHERE item_code = '".$itemcode."' ");
            $sql_op->custom_update("INSERT INTO item_scan_details(syear,item_code,scan_status,remarks,item_status_id,created_by,created_ip) values('".$dyear."','".$itemcode."','No','".$remarks."','".$item_status_id."','".$_SESSION['ID']."','".$_SERVER['REMOTE_ADDR']."')");
        }
        // echo "UPDATE item SET remarks = '".$remarks."',item_status_id='".$item_status."' WHERE item_code = '".$itemcode."' ";
        // echo '<br>';
        // if(isset($remarks) && $remarks != ''){
        //     $sql_op->custom_update("UPDATE item SET remarks = '".$remarks."' WHERE item_code = '".$itemcode."' ");
        // }
        
    }


    // error alerting
    if ($sql_op->affected_rows >= 1 && $sql_op->affected_rows != 0) {
        utility::jsAlert(gettext('Update Successfully'));
        echo '<script type="text/javascript">parent.setContent(\'mainContent\', \''.$_SERVER['PHP_SELF'].'?'.$itemcode.'\', \'post\');</script>';
    } else {
        utility::jsAlert(gettext('Some or All Data NOT Updated successfully! Please contact system administrator'));
         echo '<script type="text/javascript">parent.setContent(\'mainContent\', \''.$_SERVER['PHP_SELF'].'?'.$itemcode.'\', \'post\');</script>';
    }
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
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'bibliography/not_scan_item.php class="headerText2">Book Verification Remarks</a>';
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
<a href="<?php echo MODULES_WEB_ROOT_DIR; ?>bibliography/not_scan_item.php" class="headerText2"><?php echo gettext('Book Verification Remarks'); ?></a> </li>
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
    <form name="search" action="<?php echo MODULES_WEB_ROOT_DIR; ?>bibliography/not_scan_item.php" id="search" method="get" style="display: inline;"><?php echo gettext('Item Code'); ?> :
    <input type="text" name="keywords_for_scan_book" size="30" />
    <input type="submit" id="doSearch" value="<?php echo gettext('Submit'); ?>" class="button" />
    </form>
</div>
</fieldset>

<?
}
?>

<?php

// if(isset($_REQUEST['keywords_for_scan_book'])){
    
// $strArray = explode('/', $_SERVER[REQUEST_URI]);
// include_once("$_SERVER[DOCUMENT_ROOT]/$strArray[0]/data.php");
//echo $_SERVER[DOCUMENT_ROOT]."--".$_SERVER[REQUEST_URI];
//die();


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


     $sql = "
SELECT i.item_code,b.title,ct.coll_type_name,i.item_status_id
FROM item i
LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id
LEFT JOIN mst_coll_type AS ct ON i.coll_type_id=ct.coll_type_id
LEFT JOIN mst_item_status AS s ON i.item_status_id=s.item_status_id
WHERE i.item_code COLLATE utf8_general_ci NOT IN (SELECT item_code COLLATE utf8_general_ci FROM item_scan_details) 
"; //limit 0,20
    
    if(isset($_REQUEST['keywords_for_scan_book']) && $_REQUEST['keywords_for_scan_book'] != '')
    {
        $sql .= " AND i.item_code = '".$_REQUEST['keywords_for_scan_book']."' ";
    }
    $sql .= " order by i.item_code";
    // echo $sql;
    // die;
    $result = mysqli_query($conn,$sql) or die(mysqli_error());

    echo '<fieldset class="menuBox">';
    echo '<form name="frm_submit" action="'.MODULES_WEB_ROOT_DIR.'bibliography/not_scan_item.php" id="frm_submit" method="get">';

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
        $item_status_id_selected = '';
        if($row['item_status_id'] != 0 && $row['item_status_id'] != '')
        {
            $item_status_id_selected = ' AND item_status_id = "'.$row['item_status_id'].'" ';
        }

        $item_status_sql = "SELECT item_status_id,item_status_name FROM mst_item_status WHERE 1=1 $item_status_id_selected ";    
        $item_status = mysqli_query($conn,$item_status_sql);

        echo $RET = '<tr>
                <td>'.$i.'</td>
                <td>'.$row['item_code'].'</td>
                <td>'.$row['title'].'</td>
                <td>'.$row['coll_type_name'].'</td>
                <td><input type=text name="remarks['.$row['item_code'].']" value="'.$row['remarks'].'"></td>
                <td><select name="item_status['.$row['item_code'].']">
                        <option>Select Item Status</option>';
                        // echo 'dk'.$row['item_status_id'];
                        $selected = '';
                        while ($row1 = mysqli_fetch_array($item_status)){
                            if($row['item_status_id'] == $row1['item_status_id']){
                                $selected = 'selected';
                            }
                            echo '<option value="'.$row1['item_status_id'].'" '.$selected.'>'.$row1['item_status_name'];   
                        }
                    '</option></select>
                </td>
            </tr>';
        $html .= $RET;  
        $i++;

    }   
    echo '
    </table>';
    $html .= '</table>';
    echo '<br><CENTER><INPUT class="btn_medium" type=submit name="submit_value" value=Save></CENTER>';
    echo "</form>";
    echo '</fieldset>';
    // echo '<form name="excel" method="post" action="exportExcel.php">';
    // echo '<input type="hidden" name="hdnexcelVal" value="' . $html . '" />';
    // echo '<input type="hidden" name="excelName" value="all_book_list" />';      
    // echo '<input type="submit"  value="Excel" name="sbtExcel" class="btn_medium">';
    // echo "</FORM>";
    
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
