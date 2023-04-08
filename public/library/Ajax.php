<?php
  error_reporting(0);
    session_start();
  include_once 'sysconfig.inc.php'; 
  require LIB_DIR.'member_logon.inc.php';     
  //include_once LIB_DIR.'member_session.inc.php';
  include_once LIB_DIR.'contents/common.inc.php';       
  
  // require $data_file;
  

  $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
if ($action != '') {
    if ($action == 'getBookName') {
        $bookfield = isset($_REQUEST['bookfield']) ? $_REQUEST['bookfield'] : '';
        $name_startsWith = isset($_REQUEST['name_startsWith']) ? $_REQUEST['name_startsWith'] : '';
        $bookSql = "SELECT b.title FROM biblio b LEFT JOIN biblio_author AS ba ON b.biblio_id=ba.biblio_id
LEFT JOIN mst_author AS a ON ba.author_id=a.author_id
LEFT JOIN mst_publisher as mp ON mp.publisher_id = b.publisher_id WHERE 1";
    
    $search_str = '';
        $keywords = $dbs->escape_string(trim($name_startsWith));
        $searchable_fields = array('title', 'author', 'subject', 'isbn', 'publisher');
    
        if (in_array($bookfield, $searchable_fields)) {
      if($bookfield == 'author') {
        $field = 'author_name';
      } else if($bookfield == 'publisher') {
        $field = 'publisher_name';
      } else if($bookfield == 'isbn') {
        $field = 'isbn_issn';
      } else if($bookfield == 'title') {
        $field = 'b.title';
      } 
      $search_str = $field . ' LIKE "%' . $keywords . '%"';
      
        } else {
            $search_str = '';
            foreach ($searchable_fields as $search_field) {
        if($search_field == 'author') {
          $search_field = 'author_name';
        } else if($search_field == 'publisher') {
          $search_field = 'publisher_name';
        } else if($search_field == 'isbn') {
          $search_field = 'isbn_issn';
        } else if($search_field == 'title') {
          $field = 'b.title';
        }
                $search_str .= $search_field . ' LIKE "%' . $keywords . '%" OR ';
            }
            $search_str = substr_replace($search_str, '', -4);
      
        }
    
        $bookSql .= " AND ($search_str)";
        $bookSql .= " GROUP BY b.biblio_id ORDER BY b.title ASC";
        
        $sql1 = $dbs->query($bookSql);
        $data = array();
        while ($row = $sql1->fetch_assoc()) {
            $data[] = $row['title'];
        }
        // print_r($data);
        echo json_encode($data);

    }
}
  if ($_REQUEST['type']=='getBookRequest')
  {        
      
      global $dbs;                           
        $memberID = trim($_SESSION['DUSER_ID']);    
        //echo $memberID;die;
	$count_request="select count(temp_id) as temp_id from temp_request where member_id='$memberID' and temp_id not in(select temp_id from loan)";                  	
        $count_request=$dbs->query($count_request);
	$a='';
        
	while($row=$count_request->fetch_assoc())
	{
		 $a=$row['temp_id'];
	}
	if($a>0)
	{
            
                echo "Currently You Have ". $a ." Book Requests<font color='red'>(You can not Delete Confirm Request)</font>";
                echo showbookrequest();
	}
	else
	{	echo "Currently You Have " . $a . " Book Requests<font color='red'>(You can not Delete Confirm Request)</font>";
		//echo "<table width=100% align='center' class='memberLoanList' cellpadding='5' cellspacing='0'>";
		//echo "<tr><td align='center' style='color: red; background-color: rgb(204, 204, 204);'>No Data</td></tr>";
		//echo "</table>";
                echo showbookrequest();

	}
        
  }
  elseif ($_REQUEST['type']=='getCurrentLoan')  
  {
       global $dbs;                           
        $memberID = trim($_SESSION['DUSER_ID']);    
      echo showLoanList();
  }
 
  
function showbookrequest($num_recs_show = 20)
{
    
        global $dbs;

        $memberID = trim($_SESSION['DUSER_ID']);
        $sertracking=array();

        $sertracking=explode('/', $_SERVER[SCRIPT_FILENAME]);
        $data_file='/'.$sertracking[1].'/'.$sertracking[2].'/'.$sertracking[3].'/'.$sertracking[4].'/data.php';
        require $data_file;        
        
        $sql="SELECT L.confirm_date,IF(L.req_status IS NULL,'Pending','Confirm') as req_status ,L.temp_id, b.title, i.item_code, L.request_date FROM temp_request AS L
        LEFT JOIN item AS i ON L.item_code=i.item_code
        LEFT JOIN mst_coll_type AS ct ON i.coll_type_id=ct.coll_type_id
        LEFT JOIN ".$inte_schema.".tblstudent AS m ON L.member_id=m.enrollment_no
        LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id
        WHERE  L.member_id='$memberID' order by request_date DESC limit 20";
        
        $loan_list_query = $dbs->query($sql);

  echo "<form method=post action=index.php?p=book_request>";
  echo "<table width=100% cellspacing=0 cellpadding=5  class='memberLoanList' align=center>";
  echo "<tr class='dataListHeader' style='font-weight: bold;'><td width='5%;'>Delete</td><td align=center>Book Title</td><td align=center>Requested Date</td><td align=center>Confirmed Date</td><td align=center>Status</td></tr>";
  
  $i='';

              while ($loan_list_data = $loan_list_query->fetch_assoc())
              {
//$date1 = date("d-m-Y H:i:s",strtotime($loan_list_data['confirm_date']));
//$date2 = date('d-m-Y H:i:s');
//$days = (strtotime($date2) - strtotime($date1))/ (60 * 60 * 24);
                   /*if($loan_list_data['confirm_date']!='')
                    {
                        $loan_list_data['confirm_date']=date("d-m-Y", strtotime($loan_list_data['confirm_date']));
                    }*/
                    echo "<tr>";
                    if ($loan_list_data['req_status']!='confirm')
                    echo "<td class='alterCell2' width='5%;'><input type='checkbox' name='checkbox[]' id='checkbox[]' value=".$loan_list_data['temp_id']." ></td>";
                    else
                    echo "<td class='alterCell2' width='5%;'>N/A</td>";
                    
                   //	echo "<td>" . $loan_list_data['item_code'] ."</td>";
                    echo "<td class='alterCell2' align=center>" . $loan_list_data['title'] ."</td>";
                    if ($loan_list_data['request_date']!=null)
                    echo "<td class='alterCell2' align=center>" . date("d-m-Y", strtotime($loan_list_data['request_date'])) ."</td>";
                    else
                    echo "<td class='alterCell2' align=center> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; - </td>";
                   
                    if(empty($loan_list_data['confirm_date']))
                           echo "<td class='alterCell2' align=center>--</td>";
                    else
                           echo "<td class='alterCell2' align=center>" .date("d-m-Y H:i:s", strtotime( $loan_list_data['confirm_date'])) ."</td>";
                    //echo "<td class='alterCell2'> &nbsp; &nbsp; &nbsp;&nbsp;&nbsp;- </td>";
                    
                      echo "<td class='alterCell2' align=center>" . $loan_list_data['req_status'] ."</td>";
                    //echo "<td class='alterCell2'>Pending</td>";

                    echo "</tr>";
                   $i++;
            }
            
            
           /*  $loan_list_query = $dbs->query("select t.temp_id,b.title,it.item_code,t.loan_date from loan as t
                                             left join item as it on it.item_code=t.item_code
                                             left join biblio as b on b.biblio_id=it.biblio_id
                                             where return_date is not null and member_id='amit'");

            
             while ($loan_list_data = $loan_list_query->fetch_assoc())
             {
                    /*if($loan_list_data['confirm_date']!='')
                    {
                        $loan_list_data['confirm_date']=date("d-m-Y", strtotime($loan_list_data['confirm_date']));
                    }*/
                /*    echo "<tr>";
                    echo "<td class='alterCell2'><input type='checkbox' name='checkbox[]' id='checkbox[]' value=".$loan_list_data['temp_id']." ></td>";
                   //	echo "<td>" . $loan_list_data['item_code'] ."</td>";
                    echo "<td class='alterCell2'>" . $loan_list_data['title'] ."</td>";
                    echo "<td class='alterCell2'>" . date("d-m-Y", strtotime($loan_list_data['Loan_date'])) ."</td>";
                    echo "<td class='alterCell2'>" . date("d-m-Y", strtotime($loan_list_data['Loan_date'])) ." </td>";
                    //echo "<td class='alterCell2'>" . $loan_list_data['status'] ."</td>";
                    echo "<td class='alterCell2'>Confirm</td>";

                    echo "</tr>";
                   $i++;
            }*/
            
            
echo "<tr><td align=left class='button'><input  type='submit' name='submit1' value='Remove Request' onclick='return show_confirm();'> </td></tr>";
echo "</table>";

echo "</form>";
}
function showLoanList($num_recs_show=20)
{
       
        global $dbs;
        $memberID = trim($_SESSION['DUSER_ID']);
         
        require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
        require SIMBIO_BASE_DIR.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
        require SIMBIO_BASE_DIR.'simbio_GUI/paging/simbio_paging.inc.php';
        require SIMBIO_BASE_DIR.'simbio_UTILS/simbio_date.inc.php';
        
        
          error_reporting(0);
          $sertracking=array();
          $sertracking=explode('/', $_SERVER[SCRIPT_FILENAME]);
          $data_file='/'.$sertracking[1].'/'.$sertracking[2].'/'.$sertracking[3].'/'.$sertracking[4].'/data.php';
  
        require $data_file;
        
        
        
        $_table_spec = "loan AS l
            INNER JOIN ".$inte_schema.".tblstudent AS m ON l.member_id=m.enrollment_no
            INNER JOIN item AS i ON l.item_code=i.item_code
            INNER JOIN biblio AS b ON i.biblio_id=b.biblio_id";
         
       
        
        // create datagrid
        $_loan_list = new simbio_datagrid();
        $_loan_list->setSQLColumn('l.item_code AS \''.__('Item Code').'\'',
            'b.title AS \''.__('Title').'\'',
            'DATE_FORMAT(l.loan_date,"%d-%m-%Y") AS \''.__('Loan Date').'\'',
            'DATE_FORMAT(l.due_date,"%d-%m-%Y") AS \''.__('Due Date').'\'');
        $_criteria = sprintf("l.member_id='$memberID' AND l.return_date is null ");
        $_loan_list->setSQLorder('l.loan_date DESC');
        $_loan_list->setSQLCriteria($_criteria);

        // modify column value
        //$_loan_list->modifyColumnContent(3, 'callback{showOverdue}');
        // set table and table header attributes
        $_loan_list->table_attr = 'align="center" class="memberLoanList" cellpadding="5" cellspacing="0"';
        $_loan_list->table_header_attr = 'class="dataListHeader" style="font-weight: bold;"';
        $_loan_list->using_AJAX = false;
        // return the result
        $_result = $_loan_list->createDataGrid($dbs, $_table_spec);
        $_result = '<div class="memberLoanListInfo">'.$_loan_list->num_rows.' '.__('item(s) currently on loan').'</div>'."\n".$_result;
        return $_result;
       
}

?>
