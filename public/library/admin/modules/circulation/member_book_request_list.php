<?php
session_start();
error_reporting(E_ALL);

require '../../../sysconfig.inc.php';
//require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
require SENAYAN_BASE_DIR.'admin/default/session_check.inc.php';
//require SENAYAN_BASE_DIR.'admin/default/session_check.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/form_maker/simbio_form_table_AJAX.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/template_parser/simbio_template_parser.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/simbio_dbop.inc.php';

// privileges checking
$can_read = utility::havePrivilege('circulation', 'r');
$can_write = utility::havePrivilege('circulation', 'w');

$inte_schema = $_SESSION['inte_schema'];

if (!$can_read) 
{
    die('<div class="errorBox">'.gettext('You don\'t have enough privileges to view this section').'</div>');
}

?>
<?php

 if ((isset($_POST['itemID']) AND !empty($_POST['itemID']) AND isset($_POST['itemAction'])) || $_POST['Confirm']=='Confirm') 
 {
    if (!($can_read AND $can_write)) 
    {
        die();
    }    
    if ($_REQUEST['confirm']=='confirm')
    { 
        
//           $data['status'] = 'Confirm';
//            $data['confirm_date']=date("Y-m-d");	    
//            $sql_op = new simbio_dbop($dbs);
//            $failed_array = array();
//            $error_num = 0;
//    
//            if (!is_array($_POST['itemID'])) 
//            {
//                // make an array
//                $_POST['itemID'] = array((integer)$_POST['itemID']);
//            }
//    
//            foreach ($_POST['itemID'] as $itemID) 
//            {
//                $itemID = (integer)$itemID;
//                if (!$sql_op->update('temp_request',$data,'temp_id='.$itemID))
//                {
//                   $error_num++;
//                }
//            }
//
//    
//            if ($error_num == 0) 
//            {
//                utility::jsAlert(__('All Data Successfully Confirmed'));
//                echo '<script language="Javascript">parent.setContent(\'mainContent\', \''.$_SERVER['PHP_SELF'].'?'.$_POST['lastQueryStr'].'\', \'post\');</script>';
//                exit();
//            } 
//            else
//            {
//                utility::jsAlert(__('Some or All Data NOT deleted successfully!\nPlease contact system administrator'));
//                echo '<script language="Javascript">parent.setContent(\'mainContent\', \''.$_SERVER['PHP_SELF'].'?'.$_POST['lastQueryStr'].'\', \'post\');</script>';
//               exit();
//            }
           //exit();
    }           
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
if ($can_read AND $can_write) 
{
    
function showbookrequest($num_recs_show = 20)
{  
               
	global $dbs;
    
    echo $sql="select * from (
    SELECT L.user_type,L.item_code,L.confirm_date,L.req_status,b.biblio_id,L.temp_id, b.title, L.request_date,m.enrollment_no,m.first_name,b.material_sub_id,L.req_time 
    FROM temp_request AS L 
    INNER JOIN ".$inte_schema.".tblstudent AS m ON m.SUB_INSTITUTE_ID='$_SESSION[SUB_INSTITUTE_ID]' AND L.member_id=m.enrollment_no 
    INNER JOIN item as I ON L.item_code=I.item_code
    INNER JOIN biblio AS b ON I.biblio_id=b.biblio_id
    union 
    SELECT L.user_type,L.item_code,L.confirm_date,L.req_status,b.biblio_id,L.temp_id, b.title, L.request_date,m.user_name as enrollment_no,m.first_name,b.material_sub_id,L.req_time 
    FROM temp_request AS L 
    INNER JOIN ".$inte_schema.".tbluser AS m ON m.SUB_INSTITUTE_ID='$_SESSION[SUB_INSTITUTE_ID]' AND L.member_id=m.user_name 
    INNER JOIN item as I ON L.item_code=I.item_code
    INNER JOIN biblio AS b ON I.biblio_id=b.biblio_id
     ) as x where x.confirm_date is null 
    order by x.request_date,x.req_time";
         die;                     
    $loan_list_query = $dbs->query($sql);
                
    echo'<form method="post" action="index.php?mod=circulation">';
        
	echo "<table border='0' width=100% cellspacing=0 cellpadding=5>";
	echo "<tr class='dataListHeader'><!--<td></td>--><td>&nbsp;</td><td>Item Code</td><td>Title</td><td>Total Copies</td><td>Available Copies</td><td>Member Id</td><td>Member Name</td><td>Request Date</td><td>Time</td><td>Confirm Date</td><td>Status</td></tr>";
	 
	$i='';
    $j=0;
	while ($loan_list_data = $loan_list_query->fetch_assoc())
	{                            
		$bib="select biblio_id from item where item_code=".$loan_list_data['item_code']."";
		$bib=$dbs->query($bib);
		$b='';
		while($row=$bib->fetch_assoc())
		{
			$b.=$row['biblio_id'];
		}

		$t="select count(i.item_code) AS item_code,b.title AS title from item i left join biblio b on b.biblio_id=i.biblio_id 
            where i.biblio_id='$b'";            
		$t=$dbs->query($t);
		$tt='';
		while($row=$t->fetch_assoc())
		{
			$tt.=$row['item_code'];
		}

		$available_item="select count(i.item_code) AS item_code from item i
				        left join loan l on l.item_code=i.item_code
		                where i.biblio_id='$b' AND l.loan_date is not null AND l.return_date is null";
        $available_item=$dbs->query($available_item);                
		$available_item1='';
		while($available=$available_item->fetch_assoc())
		{
            $available_item1.=$available['item_code'];
		}
		$available_item1;                
		$available['item_code']=($tt-$available_item1);

        //added Started By Parth 10/8/2011 & 22/8/2011
        $date1 = date("d-m-Y H:i:s",strtotime($loan_list_data['confirm_date']));
        $date2 = date('d-m-Y H:i:s');
        $days = (strtotime($date2) - strtotime($date1))/ (60 * 60 * 24);
//echo $days."-->";
		//if(date("d-m-Y",strtotime($loan_list_data['confirm_date']))<date('d-m-Y') && ($loan_list_data['status']=='Confirm'))
                if(($days>2) && ($loan_list_data['status']=='Confirm'))
                {
                } //<input type=checkbox name=values[$loan_list_data[temp_id]] id=checkbox[] value=". $loan_list_data['item_code']." > 
                  //<input type=hidden   name=temp_id1 id=temp_id1[] value=".$loan_list_data['temp_id'].">
		else
                {
                        /*echo "<tr>";
                       
                        echo "<td class='alterCell2'>
                                <input type=checkbox name=temp_id1[] id=temp_id1[]  id=checkbox[]  value=".$loan_list_data['temp_id']."> 
                                <input type=hidden  name=values[$loan_list_data[temp_id]] value=". $loan_list_data['item_code']."  >
                              </td>"; 
                                              
                        echo "<td class='alterCell2' >" . $loan_list_data['title'] ."</td>";                        
                        echo "<td class='alterCell2'>".$tt." </td>";                        
                        echo "<td class='alterCell2'>".  $available['item_code']." </td>";
                        echo "<td class='alterCell2'>" . $loan_list_data['member_id'] ."</td>";
                        echo "<td class='alterCell2'>" . $loan_list_data['member_name'] ."</td>";
                        echo "<td class='alterCell2'>" .date("d-m-Y", strtotime( $loan_list_data['request_date'])) ."</td>";
                        echo "<td class='alterCell2'>" .date("H:i:s", strtotime( $loan_list_data['time']))."</td>";                        
                        echo "<input type=\"hidden\"name=\"temp_id[]\" id=\"temp_id\" value='".$loan_list_data['temp_id']."'>";                      
                        echo "<input type=\"hidden\"name=\"member_id\" id=\"member_id\" value='".$loan_list_data['member_id']."'>";
                        echo "<input type=\"hidden\"name=\"avl_copy\" id=\"avl_copy\" value='".$available['item_code']."'>";
                        echo "<input type=\"hidden\"name=\"status\" id=\"status\" value='".$loan_list_data['item_status_id']."'>";
                        echo "<input type=\"hidden\"name=\"material_sub_id\" id=\"material_sub_id\" value='".$loan_list_data['material_sub_id']."'>";
                        echo "<input type=\"hidden\"name=\"request_time\" id=\"request_time\" value='".$loan_list_data['time']."'>";
                        echo "<input type=\"hidden\"name=\"biblio_id\" id=\"biblio_id\" value='".$loan_list_data['biblio_id']."'>";                                                
                        echo "</tr>";
                    
                        echo "<tr>";                        */
                        
                        /*if (empty($loan_list_data[req_status]))
                           echo "<td class='alterCell2' width='5%;'><input type='checkbox' name='checkbox[]' id='checkbox[]' value=".$loan_list_data['temp_id']." ></td>";
                        else
                           echo "<td class='alterCell2' width='5%;'>N/A</td>";*/
                           
                        /*echo "<input type=hidden  name=values[$loan_list_data[temp_id]] value=". $loan_list_data['item_code']."  >
                              </td>";  */
                        echo "<tr>";
                        echo "<td><input type=checkbox name=temp_id1[] id=temp_id1[]  id=checkbox[]  value=".$loan_list_data['temp_id'].">"; 
                        echo "<input type=hidden  name=values[$loan_list_data[temp_id]] value=". $loan_list_data['item_code']."  >";
                        echo "</td>";
                        echo "<td class='alterCell2'>".  $loan_list_data['item_code']." </td>";                      
                        echo "<td class='alterCell2' >" . $loan_list_data['title'] ."</td>";
                        echo "<td class='alterCell2'>".$tt." </td>";                        
                        echo "<td class='alterCell2'>".  $available['item_code']." </td>";
                        echo "<td class='alterCell2'>" . $loan_list_data['enrollment_no'] ."</td>";
                        echo "<td class='alterCell2'>" . $loan_list_data['first_name'] ."</td>";
                        echo "<td class='alterCell2'>" .date("d-m-Y", strtotime( $loan_list_data['request_date'])) ."</td>";
                        echo "<td class='alterCell2'>" .date("H:i:s", strtotime( $loan_list_data['req_time']))."</td>";
                        if(empty($loan_list_data['confirm_date']))
                           echo "<td class='alterCell2'>--</td>";
                        else
                           echo "<td class='alterCell2'>" .date("d-m-Y H:i:s", strtotime( $loan_list_data['confirm_date'])) ."</td>";
                        if(intval($days) == 1)
                        {
                           $loan_list_data['req_status']='cancel';  
                           echo "<td class='alterCell2'>" . $loan_list_data['req_status'] ."</td>"; 
                           $sql="update temp_request set req_status='cancel' where temp_id=".$loan_list_data['temp_id'];
                           $dbs->query($sql);
                        }
                        else
                          echo "<td class='alterCell2'>" . $loan_list_data['req_status'] ."</td>";   

                        echo "<input type=\"hidden\"name=\"temp_id[]\" id=\"temp_id\" value='".$loan_list_data['temp_id']."'>";
                      
                        echo "<input type=\"hidden\"name=\"member_id[]\" id=\"member_id\" value='".$loan_list_data['enrollment_no']."'>";
                        echo "<input type=\"hidden\"name=\"avl_copy\" id=\"avl_copy\" value='".$available['item_code']."'>";
                        echo "<input type=\"hidden\"name=\"status\" id=\"status\" value='".$loan_list_data['item_status_id']."'>";
                        echo "<input type=\"hidden\"name=\"material_sub_id\" id=\"material_sub_id\" value='".$loan_list_data['material_sub_id']."'>";
                        echo "<input type=\"hidden\"name=\"request_time\" id=\"request_time\" value='".$loan_list_data['time']."'>";
                        echo "<input type=\"hidden\"name=\"biblio_id\" id=\"biblio_id\" value='".$loan_list_data['biblio_id']."'>";
                        echo "<input type=\"hidden\"name=\"user_type[]\" id=\"user_type\" value='".$loan_list_data['user_type']."'>";                                                
                        echo "</tr>";
               }
               $j++;
	}
        if($j>0)
        {
            echo "<table cellpadding=5><tr><td><input type='submit' id='Confirm' name='Confirm' value='Confirm' ></tr></table>";	
            echo "</table>";
        }
       // echo "</form>";

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
        echo showbookrequest();
	}
	else
	{	
		echo "<table width=100% align='center' class='memberLoanList' cellpadding='5' cellspacing='0'>";
		echo "<tr><td align='center' style='color: red; background-color: rgb(204, 204, 204);'>No Data</td></tr>";
		echo "</table>";
	}


}
?>
