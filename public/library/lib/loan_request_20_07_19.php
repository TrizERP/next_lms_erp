<?php	$memberID = trim($_SESSION['mid']);    
	$count_request="select count(temp_id) as temp_id from temp_request where member_id='$memberID'";   
	$count_request=$dbs->query($count_request);
	$a='';
	while($row=$count_request->fetch_assoc())
	{
		 $a=$row['temp_id'];
	}
	if($a>0)
	{
                echo 'Currently You Have '. $a .' Book Requests';
                
	}
	else
	{	echo 'Currently You Have ' . $a . ' Book Requests';
//		echo showbookrequest();
                echo "<table width=100% align='center' class='memberLoanList' cellpadding='5' cellspacing='0'>";
		echo "<tr><td align='center' style='color: red; background-color: rgb(204, 204, 204);'>No Data</td></tr>";
		echo "</table>";

	}
        



?>

<?php
function showbookrequest($num_recs_show = 20)
{

        global $dbs;

       $memberID = trim($_SESSION['mid']);

        $loan_list_query = $dbs->query("SELECT L.temp_id, b.title, i.item_code, L.request_date,L.status,L.confirm_date FROM temp_request AS L
        LEFT JOIN item AS i ON L.item_code=i.item_code
        LEFT JOIN mst_coll_type AS ct ON i.coll_type_id=ct.coll_type_id
        LEFT JOIN ".$inte_schema.".tblstudent AS m ON L.member_id=m.enrollment_no
        LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id
        WHERE  L.member_id='$memberID'");

  echo "<form method=post action=index.php?p=book_request>";
  echo "<table width=100% cellspacing=0 cellpadding=5  class='memberLoanList'>";
  echo "<tr class='dataListHeader' style='font-weight: bold;'><td>Delete</td><td>Title</td><td>Request Date</td><td>Confirm Date</td><td>Status</td></tr>";
 
  
  $i='';	
              while ($loan_list_data = $loan_list_query->fetch_assoc())
             {
                    if($loan_list_data['confirm_date']!='')
                    {
                    $loan_list_data['confirm_date']=date("d-m-Y", strtotime($loan_list_data['confirm_date']));
                    }
                    echo "<tr>";
                    echo "<td class='alterCell2'><input type='checkbox' name='checkbox[]' id='checkbox[]' value=".$loan_list_data['temp_id']." ></td>";
                   //	echo "<td>" . $loan_list_data['item_code'] ."</td>";
                    echo "<td class='alterCell2'>" . $loan_list_data['title'] ."</td>";
                    echo "<td class='alterCell2'>" . date("d-m-Y", strtotime($loan_list_data['request_date'])) ."</td>";
                    echo "<td class='alterCell2'>" . $loan_list_data['confirm_date'] ."</td>";
                    echo "<td class='alterCell2'>" . $loan_list_data['status'] ."</td>";

                    echo "</tr>";
                   $i++;
            }
echo "<tr><td align=left class='button'><input  type='submit' name='submit' value='Remove Request'></td></tr>";
echo "</table>";

echo "</form>";
}
function showLoanList($num_recs_show = 20)
{
       
        global $dbs;
        require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
        require SIMBIO_BASE_DIR.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
        require SIMBIO_BASE_DIR.'simbio_GUI/paging/simbio_paging.inc.php';
        require SIMBIO_BASE_DIR.'simbio_UTILS/simbio_date.inc.php';

        // table spec
        $_table_spec = 'loan AS l
            LEFT JOIN '.$inte_schema.'.tblstudent AS m ON l.member_id=m.enrollment_no
            LEFT JOIN item AS i ON l.item_code=i.item_code
            LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id';

        // create datagrid
        $_loan_list = new simbio_datagrid();
        $_loan_list->setSQLColumn('l.item_code AS \''.__('Item Code').'\'',
            'b.title AS \''.__('Title').'\'',
            'l.loan_date AS \''.__('Loan Date').'\'',
            'l.due_date AS \''.__('Due Date').'\'');
        $_loan_list->setSQLorder('l.loan_date DESC');
        $_criteria = sprintf('m.enrollment_no=\'%s\'', $_SESSION['mid']);
        $_loan_list->setSQLCriteria($_criteria);

        // modify column value
        //$_loan_list->modifyColumnContent(3, 'callback{showOverdue}');
        // set table and table header attributes
        $_loan_list->table_attr = 'align="center" class="memberLoanList" cellpadding="5" cellspacing="0"';
        $_loan_list->table_header_attr = 'class="dataListHeader" style="font-weight: bold;"';
        $_loan_list->using_AJAX = false;
        // return the result
        $_result = $_loan_list->createDataGrid($dbs, $_table_spec, $num_recs_show);
        $_result = '<div class="memberLoanListInfo">'.$_loan_list->num_rows.' '.__('item(s) currently on loan').'</div>'."\n".$_result;
        return $_result;
       
}
if(isset($_REQUEST['checkbox'])!='')
{
	if(isset($_REQUEST['submit']))
		{
		
			$count=$_REQUEST['checkbox'];
			$v='';
			if($count!=0)
			{
				foreach($count as $value)
				{
					$v.=$value.',';
				}
			}
				$del=substr($v,0,-1);
		
				$sql = "DELETE FROM temp_request WHERE temp_id IN($del)";
				$result =$dbs->query($sql);
				unset($del);
		
		
		}
}

?>