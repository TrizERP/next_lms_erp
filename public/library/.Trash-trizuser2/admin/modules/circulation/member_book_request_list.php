<?php
error_reporting(0);
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

/* Loan Rules management section */

require '../../../sysconfig.inc.php';
// start the session
require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
require SENAYAN_BASE_DIR.'admin/default/session_check.inc.php';
require SENAYAN_BASE_DIR.'admin/default/session_check.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/form_maker/simbio_form_table_AJAX.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/template_parser/simbio_template_parser.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/simbio_dbop.inc.php';

// privileges checking
$can_read = utility::havePrivilege('circulation', 'r');
$can_write = utility::havePrivilege('circulation', 'w');

if (!$can_read) {
    die('<div class="errorBox">'.__('You don\'t have enough privileges to view this section').'</div>');
}

?>
<?php


 if (isset($_POST['itemID']) AND !empty($_POST['itemID']) AND isset($_POST['itemAction'])) {



    if (!($can_read AND $can_write)) {
        die();
    }

	
    $data['status'] = 'Confirm';
   $data['confirm_date']=date("Y-m-d");	
    /* DATA DELETION PROCESS */
    // create sql op object
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
        if (!$sql_op->update('temp_request',$data,'temp_id='.$itemID))
	
	 {
            $error_num++;
        }
    }

    // error alerting
    if ($error_num == 0) {
        utility::jsAlert(__('All Data Successfully Confirmed'));
        echo '<script language="Javascript">parent.setContent(\'mainContent\', \''.$_SERVER['PHP_SELF'].'?'.$_POST['lastQueryStr'].'\', \'post\');</script>';
    } else {
        utility::jsAlert(__('Some or All Data NOT deleted successfully!\nPlease contact system administrator'));
        echo '<script language="Javascript">parent.setContent(\'mainContent\', \''.$_SERVER['PHP_SELF'].'?'.$_POST['lastQueryStr'].'\', \'post\');</script>';
    }
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
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'circulation/member_book_request_list.php class="headerText2">Reservation</a>';
echo $bradecum;
        ?>	
	</td>
</tr>
</table>
<!--<fieldset class="menuBox">
<div class="menuBoxInner loanRulesIcon">
     <hr />
    <form name="search" action="<?php echo MODULES_WEB_ROOT_DIR; ?>circulation/member_book_request_list.php" id="search" method="get" style="display: inline;"><?php echo __('Search'); ?> :
commnet by iresh on 25-1-2011  <input type="text" name="keywords" id="keywords" size="30" />
  added by iresh on 25-1-2011  <input type="text" name="keywords" id="keywords" width=140px/>
   <input type="submit" id="doSearch" value="<?php echo __('Search'); ?>" class="button">
    </form>
</div>
</fieldset>-->
<?php

    /* LOAN RULES LIST */
    // table spec




//$table_spec = 'biblio LEFT JOIN item ON biblio.biblio_id=item.biblio_id';


    // create datagrid
    $datagrid = new simbio_datagrid();
   if ($can_read AND $can_write) {
function showbookrequest($num_recs_show = 20)
{  

	global $dbs;
	$loan_list_query = $dbs->query("SELECT L.temp_id, b.title, i.item_code, L.confirm_date, L.request_date,L.status,m.member_id,m.member_name FROM temp_request AS L
		LEFT JOIN item AS i ON L.item_code=i.item_code
		LEFT JOIN mst_coll_type AS ct ON i.coll_type_id=ct.coll_type_id
		LEFT JOIN member AS m ON L.member_id=m.member_id
		LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id
	       ");
	  echo'<form method="post" action="index.php?mod=circulation">';	
	  echo "<table border='0' width=100% cellspacing=0 cellpadding=5>";
	  echo "<tr class='dataListHeader'><!--<td></td>--><!--<td>Item Code</td>--><td>Title</td><td>Total Copies</td><td>Available Copies</td><td>Member Id</td><td>Member Name</td><td>Request Date</td><!--<td>Status</td>--></tr>";
	 
	  
	  $i='';	
	  while ($loan_list_data = $loan_list_query->fetch_assoc())
	 {

		$bib="select biblio_id from item where item_code=".$loan_list_data['item_code']."";
		$bib=$dbs->query($bib);
		$b='';
		while($row=$bib->fetch_assoc())
		{
			$b.=$row['biblio_id'];
		}

		$t="select count(i.item_code) AS item_code,b.title AS title from item i

			     left join biblio b on b.biblio_id=i.biblio_id
		             where i.biblio_id='$b'";

		$t=$dbs->query($t);
		$tt='';
		while($row=$t->fetch_assoc())
		{
			 $tt.=$row['item_code'];
		
		}
		$available_item="select count(i.item_code) AS item_code from item i
				 left join loan l on l.item_code=i.item_code
		                 where i.biblio_id='$b' AND l.is_return=0";
	
		$available_item=$dbs->query($available_item);
		$available_item1='';
		while($available=$available_item->fetch_assoc())
		{

			$available_item1.=$available['item_code'];

		}
		 $available_item1;
		 $available['item_code']=($tt-$available_item1);
//added Started By Parth 10/8/2011 & 22/8/2011
$date1 = date("d-m-Y",strtotime($loan_list_data['confirm_date']));
$date2 = date('d-m-Y');
$days = (strtotime($date2) - strtotime($date1)) / (60 * 60 * 24);
//echo $days."-->";
		//if(date("d-m-Y",strtotime($loan_list_data['confirm_date']))<date('d-m-Y') && ($loan_list_data['status']=='Confirm'))
if(($days>2) && ($loan_list_data['status']=='Confirm'))
                {
                } 
		else{
//added Ended By Parth 10/8/2011 & 22/8/2011
		echo "<tr>";
		//echo "<td class='alterCell2'><input type=checkbox name=values[$loan_list_data[item_code]] id=checkbox[] value=". $loan_list_data['item_code']." ></td>"; 
		//echo "<td class='alterCell2'>" . $loan_list_data['item_code'] ."</td>";
		echo "<td class='alterCell2' >" . $loan_list_data['title'] ."</td>";
		echo "<td class='alterCell2'>".$tt." </td>";
		echo "<td class='alterCell2'>". $available['item_code']." </td>";
		echo "<td class='alterCell2'>" . $loan_list_data['member_id'] ."</td>";
		echo "<td class='alterCell2'>" . $loan_list_data['member_name'] ."</td>";
		echo "<td class='alterCell2'>" .date("d-m-Y", strtotime( $loan_list_data['request_date'])) ."</td>";
		//echo "<td class='alterCell2'>" . $loan_list_data['status'] ."</td>";   
		echo "</tr>";
               }
//added Started By Parth 10/8/2011 & 22/8/2011	       
	}
//added Ended By Parth 10/8/2011 & 22/8/2011
	echo "<table cellpadding=5><tr><td><!--<input type='submit' name='submit' value='Confirm'>--></td></tr></table>";	
	echo "</table>";
	echo"</form>";


		

}


$count_request="select count(temp_id) as temp_id from temp_request";   
		$count_request=$dbs->query($count_request);
		$a='';
		while($row=$count_request->fetch_assoc())
		{
			 $a=$row['temp_id'];
		}
		if($a>0)
	{
	 
   	 echo showbookrequest();//added by iresh on 17-2-2011
	}
	else
	{	
		echo "<table width=100% align='center' class='memberLoanList' cellpadding='5' cellspacing='0'>";
		echo "<tr><td align='center' style='color: red; background-color: rgb(204, 204, 204);'>No Data</td></tr>";
		echo "</table>";

	}


/*
$sql='select biblio_id from item where item_code IN(select item_code from temp_request)';
$sql=$dbs->query($sql);
$s='';
while($row=$sql->fetch_assoc())
{
	$s.=$row['biblio_id'].',';
}
echo $s;

          $datagrid->setSQLColumn('t.temp_id',
	    'i.item_code AS \''.__('Item Code').'\'',
            'b.title AS \''.__('Title').'\'',
	    'COUNT(i.item_id) AS \''.__('Copies').'\'',
          
            //'IF(COUNT(i.item_id)>0, COUNT(i.item_id), \'<strong style="color: #FF0000;">'.__('None').'</strong>\') AS \''.__('Copies').'\'',
            'm.member_id AS \''.__('Member Id').'\'',
            'm.member_name AS \''.__('Member Name').'\'',
            't.status AS \''.__('Status').'\'',
	    't.confirm_date AS \''.__('Confirm Date').'\''
            );

  /*$datagrid->setSQLColumn('t.temp_id',
	    't.item_code AS \''.__('Item Code').'\'',
            'b.title AS \''.__('Title').'\'',
           'IF(COUNT(i.item_id)>0,COUNT(i.item_id), \'<strong style="color: #FF0000;">'.__('None').'</strong>\') AS \''.__('Copies').'\'',
            'm.member_id AS \''.__('Member Id').'\'',
            'm.member_name AS \''.__('Member Name').'\'',
            't.status AS \''.__('Status').'\'',
	    't.confirm_date AS \''.__('Confirm Date').'\''
            );*/



   }
/*
    // is there any search
    if (isset($_GET['keywords']) AND $_GET['keywords']) {
       $keywords = $dbs->escape_string($_GET['keywords']);
       $datagrid->setSQLCriteria("m.member_name or t.status LIKE '%$keywords%'");
    }

    // set table and table header attributes
    $datagrid->icon_edit = $sysconf['admin_template']['dir'].'/'.$sysconf['admin_template']['theme'].'/edit.gif';
    $datagrid->table_attr = 'align="center" id="dataList" cellpadding="5" cellspacing="0"';
    $datagrid->table_header_attr = 'class="dataListHeader" style="font-weight: bold;"';
    // set delete proccess URL
    $datagrid->chbox_form_URL = $_SERVER['PHP_SELF'];

    // put the result into variables
$table_spec = 'temp_request AS t
        LEFT JOIN item AS i ON i.item_code=t.item_code
        LEFT JOIN biblio AS b ON b.biblio_id=i.biblio_id
        LEFT JOIN member AS m ON t.member_id=m.member_id';
$sql="select item_code from temp_request";
$sql=$dbs->query($sql);
$s='';
while($row=$sql->fetch_assoc())
{
	$s.=$row['item_code'].',';

}

echo $ss=substr($s,0,-1);
 /*$table_spec = 'biblio AS b
        LEFT JOIN item AS i ON b.biblio_id=i.biblio_id
        LEFT JOIN temp_request AS t ON t.item_code=i.item_code
        LEFT JOIN member AS m ON t.member_id=m.member_id
	';
*/
/* $datagrid->sql_group_by = 'b.biblio_id';
 $datagrid->setSQLCriteria("i.item_code IN ($ss)");

 $datagrid_result = $datagrid->createDataGrid1($dbs, $table_spec, 20, ($can_read AND $can_write));
    if (isset($_GET['keywords']) AND $_GET['keywords']) {
        $msg = str_replace('{result->num_rows}', $datagrid->num_rows, __('Found <strong>{result->num_rows}</strong> from your keywords')); //mfc
        echo '<div class="infoBox">'.$msg.' : "'.$_GET['keywords'].'"</div>';
    }

    echo $datagrid_result;
*/
/* main content end */
?>
