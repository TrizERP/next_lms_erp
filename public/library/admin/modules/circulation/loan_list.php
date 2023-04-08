<?php
/* loan list iframe content */

// main system configuration
require '../../../sysconfig.inc.php';
// start the session
//require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
require SENAYAN_BASE_DIR.'admin/default/session_check.inc.php';

//if (!isset($_SESSION['memberID1'])) { die(); }

require SIMBIO_BASE_DIR.'simbio_GUI/form_maker/simbio_form_table_AJAX.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/simbio_dbop.inc.php';
require SIMBIO_BASE_DIR.'simbio_UTILS/simbio_date.inc.php';
require MODULES_BASE_DIR.'membership/member_base_lib.inc.php';
require MODULES_BASE_DIR.'circulation/circulation_base_lib.inc.php';


$page_title = 'Member Loan List';
ob_start();
?>
<script type="text/javascript">
function confirmProcess(intLoanID, strItemCode, strProcess)
{
    if(strProcess == 'return') 
    {
        var confirmBox = confirm('<?php echo __('Are you sure you want to return the item'); ?> ' + strItemCode);
     }
     else
     {
        var confirmBox = confirm('<?php echo __('Are you sure to extend loan for'); ?> ' + strItemCode); //mfc
     }

    if (confirmBox) 
    {        
        document.loanHiddenForm.process.value = strProcess;
        document.loanHiddenForm.loanID.value = intLoanID;
        document.loanHiddenForm.submit();
    }
}
</script>

<?php  
// echo '<pre>';
// print_r($_SESSION); 
$inte_schema = $_SESSION['inte_schema']; 
if(isset($_SESSION['memberID1']))
{    
    
    $memberID = trim($_SESSION['memberID1']);               
    $circulation = new circulation($dbs, $memberID);
      
    $sql="SELECT L.loan_id, b.title, i.item_code, L.loan_date, L.due_date, L.return_date, L.renewed FROM loan AS L
        INNER JOIN item AS i ON L.item_code=i.item_code
        INNER JOIN mst_coll_type AS ct ON i.coll_type_id=ct.coll_type_id
        INNER JOIN ".$inte_schema.".tblstudent AS m ON L.member_id = m.enrollment_no
        INNER JOIN biblio AS b ON i.biblio_id=b.biblio_id
        WHERE L.loan_date is not null AND L.return_date is null  AND L.member_id='$memberID' AND m.sub_institute_id = '".$_SESSION['SUB_INSTITUTE_ID']."'";

    $loan_list_query = $dbs->query($sql);
    
    IF ($loan_list_query->num_rows<=0)
    {
        $sql="SELECT L.loan_id, b.title, i.item_code, L.loan_date, L.due_date, L.return_date, L.renewed FROM loan AS L
        INNER JOIN item AS i ON L.item_code=i.item_code
        INNER JOIN mst_coll_type AS ct ON i.coll_type_id=ct.coll_type_id
        INNER JOIN ".$inte_schema.".tbluser AS m ON L.member_id=m.user_name 
        INNER JOIN biblio AS b ON i.biblio_id=b.biblio_id
        WHERE L.loan_date is not null AND L.return_date is null  AND L.member_id='$memberID' AND m.sub_institute_id = '".$_SESSION['SUB_INSTITUTE_ID']."'";
        $loan_list_query = $dbs->query($sql);
    }

    // hidden form for return and extend process
    echo '<form name="loanHiddenForm" id="loanHiddenForm" method="post" action="circulation_action.php"><input type="hidden" name="process" value="return" /><input type="hidden" name="loanID" value="" />';
    echo '<input type="submit" name="submit" class="button" value="Return Selected Data">&nbsp;';
    echo '<input type="submit" name="extend" class="button" value="Renew Selected Data">';

   // create table object
    $loan_list = new simbio_table();
    $loan_list->table_attr = 'align="center" width="100%" cellpadding="3" cellspacing="0"';
    $loan_list->table_header_attr = 'class="dataListHeader" style="font-weight: bold;"';
    $loan_list->highlight_row = true;
    // table header
    $headers = array(__('Return'), __('Extend'), __('Item Code'), __('Title'), __('Issue Date'), __('Due Date'));
    $loan_list->setHeader($headers);
    // row number init
    $row = 1;
    
    while ($loan_list_data = $loan_list_query->fetch_assoc()) 
    {
        
        // echo '<pre>';
        // print_r($loan_list_data);
        // die;
        $row_class = ($row%2 == 0)?'alterCell':'alterCell2';
      

         $return_link = '<input type=checkbox name=loanID[] value='.$loan_list_data[loan_id].'>';

        if ($_SESSION['is_expire']) 
        {
            echo $extend_link = '<span class="noExtendLink" title="'.__('No Extend').'">&nbsp;</span>';
        }
        else
        {
        
            
            // check if this loan just already renewed
            if ($loan_list_data['return_date'] == date('Y-m-d'))
            {
                 $extend_link = '<span class="noExtendLink" title="'.__('No Extend').'">&nbsp;</span>';
            }
            else if (in_array($loan_list_data['loan_id'], $_SESSION['reborrowed'])) 
            {
                 $extend_link = '<span class="noExtendLink" title="'.__('No Extend').'">&nbsp;</span>';
            } 
            else 
            {
                  $extend_link = '<input type=checkbox name=loanID[] value='.$loan_list_data['loan_id'].'>';
            }
        }
        
        // renewed flag
        if ($loan_list_data['renewed'] > 0) 
        {
             echo $loan_list_data['title'] = $loan_list_data['title'].'<strong style="color: blue;">'.__('Extended').'</strong>';
        }
        // check for overdue
        $curr_date = date('Y-m-d');
        $overdue = $circulation->countOverdueValue($loan_list_data['loan_id'], $curr_date);
        if ($overdue) 
        {
            $loan_list_data['title'] .= '<div style="color: red; font-weight: bold;">'.__('OVERDUED for').' '.$overdue['days'].' '.__('days(s) with fines value').' '.$overdue['value'].'</div>'; //mfc
        }
        
        // row colums array
        $fields = array(
            $return_link,
            $extend_link,
            $loan_list_data[item_code],
            $loan_list_data[title],
           date("d-m-Y", strtotime( $loan_list_data[loan_date])),
           date("d-m-Y", strtotime( $loan_list_data[due_date]))
            );

       
        $loan_list->appendTableRow($fields);
        // set the HTML attributes
        $loan_list->setCellAttr($row, null, "valign='top' class='$row_class'");
        $loan_list->setCellAttr($row, 0, "valign='top' align='center' class='$row_class' style='width: 5%;'");
        $loan_list->setCellAttr($row, 1, "valign='top' align='center' class='$row_class' style='width: 5%;'");
        $loan_list->setCellAttr($row, 2, "valign='top' class='$row_class' style='width: 10%;'");
        $loan_list->setCellAttr($row, 3, "valign='top' class='$row_class' style='width: 55%;'");

        $row++;
    }

    
    if (isset($_GET['reserveAlert']) AND !empty($_GET['reserveAlert'])) 
    {


       $reservedItem[] = unserialize($_GET['reserveAlert']);
       foreach($reservedItem as $id=>$ritem)
       {
	  foreach($ritem as $id=>$r)
	  {
		if($r!='')
		{
                    $temp_sql='SELECT r.member_id, m.first_name,r.item_code
			    FROM reserve AS r
			    LEFT JOIN '.$inte_schema.'.tblstudent AS m ON r.member_id=m.enrollment_no
			    WHERE item_code=\''.$r.'\' ORDER BY reserve_date DESC';
	           
                    $reserve_q = $dbs->query($temp_sql);			
			while($reserve_d = $reserve_q->fetch_assoc())
			{
				$reserve_msg = 'Item '.$reserve_d['item_code'].' is being reserved by member '.$reserve_d['first_name'].''; //mfc
				echo '<table><tr><td class="infoBox">'.$reserve_msg.'</td></tr></table>';
			}
			
			
		
		}
		
	  }
       }
       
        $sql_temp2='SELECT r.member_id, m.first_name
            FROM reserve AS r
            LEFT JOIN '.$inte_schema.'.tblstudent AS m ON r.member_id=m.enrollment_no
            WHERE item_code=\''.$reservedItem.'\' ORDER BY reserve_date DESC';
     
        $reserve_q = $dbs->query($sql_temp2);
        $reserve_d = $reserve_q->fetch_row();
        $member = $reserve_d[1].' ('.$reserve_d[0].')';
        $reserve_msg = str_replace(array('{itemCode}', '{member}'), array('<b>'.$reservedItem.'</b>', '<b>'.$member.'</b>'), __('Item {itemCode} is being reserved by member {member}')); //mfc
        echo '<div class="infoBox">'.$reserve_msg.'</div>';
	
	
    }
    echo $loan_list->printTable();
    echo '</form>';
}
//$content = ob_get_clean();
require SENAYAN_BASE_DIR.'/admin/'.$sysconf['admin_template']['dir'].'/notemplate_page_tpl.php';
?>
