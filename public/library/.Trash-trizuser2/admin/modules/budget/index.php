<?php
/**
 * Copyright (C) 2007,2008,2009,2010  Arie Nugraha (dicarve@yahoo.com)
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

/* Bibliography Management section */

if (!defined('SENAYAN_BASE_DIR')) {
    // main system configuration
    require '../../../sysconfig.inc.php';
    // start the session
    require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
}

require SENAYAN_BASE_DIR.'admin/default/session_check.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/form_maker/simbio_form_table_AJAX.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/simbio_dbop.inc.php';
require SIMBIO_BASE_DIR.'simbio_FILE/simbio_file_upload.inc.php';

// privileges checking
$can_read = utility::havePrivilege('budget', 'r');
$can_write = utility::havePrivilege('budget', 'w');
if(isset($_POST['saveData']) AND $can_read AND $can_write)
{
  $data['expected_budget'] = $_POST['expected_budget'];
  $data['total_budget'] = $_POST['totalbalance']; 
  $data['available_budget'] = $_POST['availablebalance'];   
  $data['year'] = $_POST['expected_budget_year'];
 $sql_op = new simbio_dbop($dbs);
   $insert = $sql_op->insert('mst_budget', $data); 
  $last_insert_id = $sql_op->insert_id;
  for($i=1;$i<=$_POST['cal'];$i++)
     {
       $data1['title'] = $_POST['title'][$i];
       $data1['author'] = $_POST['author'][$i]; 
       $data1['qty'] = $_POST['qty'][$i];   
       $data1['price'] = $_POST['price'][$i];       
      $data1['total'] = $_POST['total'][$i];
       $data1['budget_id'] = $last_insert_id;
        $insert1 = $sql_op->insert('budget_records', $data1);  
     }
header('Location: ../../index.php?mod=budget');
}
if (isset($_POST['itemID']) AND !empty($_POST['itemID']) AND isset($_POST['itemAction'])) {
    if (!($can_read AND $can_write)) {
        die();
    }
    $sql_op = new simbio_dbop($dbs);
    $failed_array = array();
    $error_num = 0;
    if (!is_array($_POST['itemID'])) {
        // make an array
        $_POST['itemID'] = array((integer)$_POST['itemID']);
    }
    // loop array
    foreach ($_POST['itemID'] as $itemID) {
        $itemID = (integer)$itemID; 
	if (!$sql_op->delete('mst_budget', 'budget_id='.$itemID)) {
            $error_num++;
	}
        if (!$sql_op->delete('budget_records', 'budget_id='.$itemID)) {
            $error_num++;
	}
        }
       if ($error_num == 0) {
        utility::jsAlert(__('All Selected Data successfully saved'));
        echo '<script type="text/javascript">parent.setContent(\'mainContent\', \''.$_SERVER['PHP_SELF'].'?'.$_POST['lastQueryStr'].'\', \'post\');</script>';
    } else {
        utility::jsAlert(__('Some or All Data NOT deleted successfully!\nPlease contact system administrator'));
        echo '<script type="text/javascript">parent.setContent(\'mainContent\', \''.$_SERVER['PHP_SELF'].'?'.$_POST['lastQueryStr'].'\', \'post\');</script>';
    }
    exit();
    }
//$form = new simbio_form_table_AJAX('mainForm', $_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'], 'post');
 //$form->submit_button_attr = 'name="saveData" value="'.__('Save').'" class="button"';
    // form table attributes
    

   // $visibility = 'makeVisible';
//echo $form->printOut();
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
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'budget/index.php class="headerText2">Budget</a>';
echo $bradecum;
        ?>	
	</td>
</tr>
</table>
<fieldset class="menuBox">
<div class="menuBoxInner printIcon">
Budget
</div>
</fieldset>
<!--<form name="mainForm" method="POST">
   <input type="button" value="Add another text input" onClick="javascript:addInput('dynamicInput');">
     <div id="dynamicInput">
	  <table border=1><tr><td>Sr. No</td><td>Title</td><td>Author</td><td>Qty</td><td>Price</td><td>Total</td></tr></table>	
          <table  border=1><tr><td>1</td><td><input type="text" name="myInputs[]"></td><td>not</td><td>not</td><td>not</td><td>not</td></tr></table>
     </div>
</table>
</form>-->
<table border=0>
<tr><td>
<form name="mainForm" id="mainForm" action="<?php echo $_SERVER['PHP_SELF']."?".$_SERVER['QUERY_STRING']; ?>" method="post" enctype="multipart/form-data" target="_self">
<br/>
Enter Your Budget <input type="text" name="expected_budget" id="expected_budget" onchange="getbudget()"/>
Enter Your Budget Year <input type="text" name="expected_budget_year" id="expected_budget_year" /> 
<input type="button" onclick="appendtable()" value="Start Adding Data" class="button">
<INPUT type="button" value="Delete Row" onclick="deleteRow()" class="button"/>
<input type="submit" value="save" name="saveData" class="button">
<div id="divide" height="100" align="center"></div>
<table width="80%">
<tr align="right">
<td>
Total Balance :- <input type="text" id="totalbalance" name="totalbalance" value="0"/>
</td>
</tr>
<tr align="right">
<td>
Available Budget :- <input type="text" id="availablebalance" name="availablebalance" value="0"/>
<input type="hidden" name="cal" id="cal" value="" />
</td>
</tr>
</table>
</form>
</td></tr>
</table>
<?php
if (isset($_POST['detail']) OR (isset($_GET['action']) AND $_GET['action'] == 'detail')) {
    if (!($can_read AND $can_write)) {
        die('<div class="errorBox">'.__('You don\'t have enough privileges to view this section').'</div>');
    }
    /* RECORD FORM */
    $itemID = (integer)isset($_POST['itemID'])?$_POST['itemID']:0;
    $rec_q = $dbs->query('SELECT * FROM mst_budget WHERE budget_id='.$itemID);
    $rec_d = $rec_q->fetch_assoc();
    echo "<table width=100%><tr><td align=center>Expected Budget :- $rec_d[expected_budget]</td><td>Expected Year :- $rec_d[year]</td></tr></table>";
    echo "<table width=100% align=center id=dataList cellpadding=5 cellspacing=0 class=dataListHeader style=font-weight: bold;><tr><td class=dataListHeader style=font-weight: bold;>Title</td><td class=dataListHeader style=font-weight: bold;>Author</td><td class=dataListHeader style=font-weight: bold;>Qty</td><td class=dataListHeader style=font-weight: bold;>Price</td><td class=dataListHeader style=font-weight: bold;>Total</td></tr>";
    $rec_q1 = $dbs->query('SELECT * FROM budget_records WHERE budget_id='.$itemID);
    while($rec_d1 = $rec_q1->fetch_assoc())
      {
       echo "<tr style=color:black><td class=alterCell2 valign=top style='' >$rec_d1[title]</td><td class=alterCell2 valign=top style=''>$rec_d1[author]</td><td class=alterCell2 valign=top style=''>$rec_d1[qty]</td><td class=alterCell2 valign=top style=''>$rec_d1[price]</td><td class=alterCell2 valign=top style=''>$rec_d1[total]</td></tr>";
       }
    echo "</table>" ;
    echo "<table width=100%><tr><td align=right>Total Budget :- $rec_d[total_budget]</td></tr></table>";
    echo "<table width=100%><tr><td align=right>Total Budget :- $rec_d[available_budget]</td></tr></table>";
}

else
{
require SIMBIO_BASE_DIR.'simbio_UTILS/simbio_tokenizecql.inc.php';
    require LIB_DIR.'biblio_list.inc.php';
    /* BIBLIOGRAPHY LIST */
    // callback function to show title and authors in datagrid
       // create datagrid
    $datagrid = new simbio_datagrid();
    if ($can_read AND $can_write) {
        $datagrid->setSQLColumn('budget.budget_id', 'budget.budget_id AS bid',
            'budget.expected_budget AS \''.__('Expected Budget').'\'',
            'budget.available_budget AS \''.__('Available Budget').'\'',
            'budget.year AS \''.__('Year').'\'',
            'budget.total_budget AS \''.__('Total Budget').'\'');
        //$datagrid->modifyColumnContent(2, 'callback{showTitleAuthors}');
    } else {
        $datagrid->setSQLColumn('budget.budget_id', 'budget.budget_id AS bid',
            'budget.expected_budget AS \''.__('Expected Budget').'\'',
            'budget.available_budget AS \''.__('Available Budget').'\'',
            'budget.year AS \''.__('Year').'\'',
            'budget.total_budget AS \''.__('Total Budget').'\'');
        // modify column value
        //$datagrid->modifyColumnContent(1, 'callback{showTitleAuthors}');
    }
    $datagrid->invisible_fields = array(0);
    $datagrid->setSQLorder('');

    // is there any search
    if (isset($_GET['keywords']) AND $_GET['keywords']) {
        $keywords = $dbs->escape_string(trim($_GET['keywords']));
        $searchable_fields = array('expected_budget', 'available_budget', 'total_budget', 'year');
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
    $table_spec = 'mst_budget budget';

    // set group by
    $datagrid->sql_group_by = 'budget.budget_id';

    // set table and table header attributes
    $datagrid->table_attr = 'align="center" id="dataList" cellpadding="5" cellspacing="0"';
    $datagrid->table_header_attr = 'class="dataListHeader" style="font-weight: bold;"';
    // set delete proccess URL
    $datagrid->chbox_form_URL = $_SERVER['PHP_SELF'];
    $datagrid->debug = true;

    $biblio_result_num = ($sysconf['biblio_result_num']>100)?100:$sysconf['biblio_result_num'];
    // put the result into variables
    $datagrid_result = $datagrid->createDataGrid($dbs, $table_spec, $biblio_result_num, ($can_read AND $can_write));
    if (isset($_GET['keywords']) AND $_GET['keywords']) {
        $msg = str_replace('{result->num_rows}', $datagrid->num_rows, __('Found <strong>{result->num_rows}</strong> from your keywords')); //mfc
        echo '<div class="infoBox">'.$msg.' : "'.$_GET['keywords'].'"<div>'.__('Query took').' <b>'.$datagrid->query_time.'</b> '.__('second(s) to complete').'</div></div>'; //mfc
    }

    echo $datagrid_result;

}
?>
