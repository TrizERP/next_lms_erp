<?php


require '../../../../sysconfig.inc.php';
//require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
require SENAYAN_BASE_DIR.'admin/default/session_check.inc.php';

$can_read = utility::havePrivilege('circulation', 'r') || utility::havePrivilege('reporting', 'r');
$can_write = utility::havePrivilege('circulation', 'w') || utility::havePrivilege('reporting', 'w');

if (!$can_read) 
{
    die('<div class="errorBox">'.__('You don\'t have enough privileges to access this area!').'</div>');
}

require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/form_maker/simbio_form_element.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require MODULES_BASE_DIR.'reporting/report_dbgrid.inc.php';


    $id=$_REQUEST['id'];
    $gmd_main=$_REQUEST['gmd_main'];

$page_title = 'Loan History Report';
$reportView = false;
$num_recs_show = 10;
if (isset($_GET['reportView'])) 
{
    $reportView = true;
}

/*if (!$reportView) 
{
?>

    
 <!--   <script type="text/javascript">hideRows('filterForm', 1);</script>
    <!-- filter end -->
   <!--<div class="dataListHeader" style="padding: 3px;"><span id="pagingBox"></span></div>
    <iframe name="reportView" id="reportView" src="<?php //echo $_SERVER['PHP_SELF'].'?reportView=true'; ?>" frameborder="0" style="width: 100%; height: 600px;"></iframe> -->
<?php
} 
else 
{*/
 
  
    
    if ($_GET['gmd_main']=='0')
    {
        
        $str_sting="SELECT m.enrollment_no AS 'Member ID' , concat_ws(' ',m.first_name,m.middle_name,m.last_name) AS 'Member Name', i.item_code AS 'Item Code', b.title AS 'Title', DATE_FORMAT(l.loan_date,'%d-%m-%Y') AS 'Loan Date', DATE_FORMAT(l.due_date,'%d-%m-%Y') AS 'Due Date'  
         FROM item AS i 
         LEFT JOIN loan AS l ON l.item_code=i.item_code 
         LEFT JOIN ".$inte_schema.".tblstudent AS m ON l.member_id = m.enrollment_no 
         LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id 
         WHERE b.material_resource_id=$_GET[id] ";
        if ($_GET['continue_loan']=='1')
        $str_sting.=" and l.return_date is null ";  
        if ($_GET['renew']=='1')
        {
                $str_sting.=" OR l.return_date!=''";  
        }
        if ($_GET['over_due']=='1')
        $str_sting.=" OR l.return_date > l.due_date ";
//        else
//        $str_sting.=" and i.item_status_id=''";   
//        
        $str_sting.="ORDER BY l.loan_date ASC ";    
//$str_sting.="LIMIT 27";
    }
    elseif($_GET['gmd_main']=='1')
    {
      $str_sting="SELECT m.enrollment_no AS 'Member ID' , m.first_name AS 'Member Name', i.item_code AS 'Item Code', b.title AS 'Title', DATE_FORMAT(l.loan_date,'%d-%m-%Y') AS 'Loan Date', DATE_FORMAT(l.due_date,'%d-%m-%Y') AS 'Due Date' 
            FROM item AS i  ";
	    $str_sting.=" LEFT JOIN loan AS l ON l.item_code=i.item_code ";
      $str_sting.=" LEFT JOIN ".$inte_schema.".tblstudent AS m ON l.member_id = m.enrollment_no ";        
        $str_sting.=" LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id  ";
        $str_sting.=" WHERE b.gmd_id=$_GET[id] ";
        if ($_GET['continue_loan']=='1')
        $str_sting.=" OR l.return_date is null ";  
        if ($_GET['renew']=='1')
        $str_sting.=" OR l.renewed>0 ";  
        if ($_GET['over_due']=='1')
        $str_sting.=" OR l.return_date > l.due_date "; 
//        else
//        $str_sting.=" and i.item_status_id='' LIMIT".$_GET['over_due'];   
        
        $str_sting.=" ORDER BY l.loan_date ASC ";                
      //  $str_sting.="LIMIT 26";
            
            
    }
    elseif($_GET['gmd_main']=='2')
    {
       
        $str_sting.="SELECT m.enrollment_no AS 'Member ID' , m.first_name AS 'Member Name', i.item_code AS 'Item Code', b.title AS 'Title', DATE_FORMAT(l.loan_date,'%d-%m-%Y') AS 'Loan Date', DATE_FORMAT(l.due_date,'%d-%m-%Y') AS 'Due Date' FROM item AS i  ";
		    $str_sting.="LEFT JOIN loan AS l ON l.item_code=i.item_code  ";
        $str_sting.="LEFT JOIN ".$inte_schema.".tblstudent AS m ON l.member_id = m.enrollment_no ";        
        $str_sting.="LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id  ";
        $str_sting.="WHERE b.material_sub_id=$_GET[id] ";
        
        if ($_GET['continue_loan']=='1')
        $str_sting.=" OR l.return_date is null and l.due_date>now()";  
        if ($_GET['renew']=='1'){
          if($id!='132')
            $str_sting.=" OR l.renewed>0 ";
          else {
            $str_sting.=" OR l.renewed<0 ";
          }
        }
        if ($_GET['over_due']=='1')
        $str_sting.=" OR l.return_date > l.due_date "; 
      // else{
       //     if($id=='132' || $id=='130')
       //       $str_sting.=" and i.item_status_id=''";     
       // }
        $str_sting.=" ORDER BY l.loan_date ASC" ;
       // $str_sting.="LIMIT 19";
      //  echo $str_sting;die;
    }
  //echo $str_sting;  
$gmd_q = $dbs->query($str_sting);  



    

echo '<table border="1">';
   echo '<tr>';
        echo '<td>';
        echo 'No';
       echo '</td>';
   echo '<td>';
        echo 'Member Id';
       echo '</td>';
       echo '<td>';
        echo 'Member Name';
       echo '</td>';
       
       echo '<td>';
        echo 'Item Code';        
       echo '</td>';
       
       echo '<td>';
       echo 'Title';
       echo '</td>';
       
       echo '<td>';
       echo 'Loan Date';        
       echo '</td>';
       
       
       echo '<td>';
       echo 'Due Date';        
       echo '</td>';
       
       
//       echo '<td>';
//       echo 'Loan Status';        
//       echo '</td>';
//       
//       
//       echo '<td>';
//       echo 'Return Date';
//       echo '</td>';
//       
//       echo '<td>';
//       echo 'Renewed';
//       echo '</td>' ;
       
   echo '</tr>';
   $i=1;
   while ($gmd_d = $gmd_q->fetch_row()) 
    {
                echo '<tr>';
                echo '<td>';
                    echo $i;
                   echo '</td>';
                echo '<td>';
                    echo $gmd_d['0'];
                   echo '</td>';
                   
                   echo '<td>';
                    echo $gmd_d['1'];
                   echo '</td>';

                   echo '<td>';
                   
                    echo $gmd_d['2'];        
                   echo '</td>';

                   echo '<td>';
                   echo $gmd_d['3'];

                   echo '</td>';

                   echo '<td>';
                   echo $gmd_d['4'];                                        

                   echo '</td>';
                   echo '<td>';
                       echo $gmd_d['5'];                   

                   echo '</td>';
                   echo '<td>';
                   
//                   if ($gmd_d['6']=='1')
//                       echo 'Return';
//                   elseif($gmd_d['6']=='0')
//                       echo 'Continue';    
//
//                   echo '</td>';
//                   
//                   echo '<td>';
//                    echo $gmd_d['7'];
//                   echo '</td>';
//                   
//                   echo '<td>';     
//                   
//                   
//                    echo $gmd_d['8'];
//                   echo '</td>';
//                   
//                   echo '<td>';
//                    echo $gmd_d['9'];
//                   echo '</td>';

                    echo '</tr>';
                    $i=$i+1;
    }
   echo '<table>';    
//}
  
?>
