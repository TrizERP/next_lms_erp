<link rel="stylesheet" type="text/css" href="template/school/css/tab_tab.css"/>
<script src="template/school/jquery_tab.js" type="text/javascript">
</script>
<script src="js/ajax.js" type="text/javascript">
</script>

<script>
// perform JavaScript after the document is scriptable.
$(function() {
	// setup ul.tabs to work as tabs for each div directly under div.panes
	$("ul.tabs").tabs("div.panes > div");
        
});
function getBookRequest()
{    
    document.getElementById("divContent").style.display="";
    getData("Ajax.php?type=getBookRequest","divContent");
    
}
function getCurrentLoan()
{
    document.getElementById("divContent").style.display="";
    getData("Ajax.php?type=getCurrentLoan","divContent");
}
function getReserveBook()
{
    document.getElementById("divContent").style.display="none";
    getData("Ajax.php","divContent");
}


</script>
	<style>
	
/* tab pane styling */
.panes div {
	display:none;	
        position: relative;
	padding:15px 10px;
/*        border: none;*/
	border:1px solid #999;
	border-top:1;
/*	height:100%;*/
	font-size:14px;
	background-color:#fff;
}
.panes div.subItem
{
   display:block;
   border: none;
   padding:0px;
}
</style>

        
<?php
require LIB_DIR.'member_logon.inc.php';
//require SIMBIO_BASE_DIR.'simbio_GUI/paging/simbio_paging.inc.php';
error_reporting(0);

echo "<ul class=tabs>";
echo '<li><a href="#" onclick="getReserveBook();"><b>'.__('Reserve Book').'</b></a></li>';
echo '<li><a href="#" onclick="getCurrentLoan();"><b>'.__('Your Current Loan').'</b>'."\n".'</a></li>';
echo '<li><a href="#" onclick="getBookRequest();"><b>'.__('My Book Request').'</b>'."\n".'</a></li>';
echo '</ul>';
//echo "<pre>";
//print_r($_REQUEST);
//echo "<pre>";
    echo "<div id='divContent'></div>";    
    echo '<div class="panes">';
    echo '<div id="main-content2" style="position:relative;">';
    
    
    $qry = "select material_sub_id from mst_material_sub_type where material_sub_name = 'Books'";
    $result = $dbs->query($qry);
    while($row=$result->fetch_assoc())
    {
	$sub_id = $row['material_sub_id'];
    }
 ?>
        
     <!--<h2 class="tab">Search</h2>-->
         <script type="text/javascript">tp1.addTabPage( document.getElementById( "tabPage1" ) );</script>
         <script type="text/javascript">
             function displaychange()
             {
               location.href = "index.php?keywords="+document.simpleSearch.keywords.value+"&search1=Search&material_sub_type_select="+document.simpleSearch.material_sub_type_select.value+"&subtype="+document.simpleSearch.subtype.value+"&subid="+document.simpleSearch.subid.value;
             }              
         </script>

         <form name="simpleSearch" action="index.php" method="get">             
             <center><?php include('lib/search_table.php');?></center>
             <input type="hidden" name="p" id="p" value="book_request"></input>                      
        </form>	
        <br>     
	<script type="text/javascript">tp1.addTabPage( document.getElementById( "tabPage2" ) );</script>
        <center>
	 <form name="advSearchForm" id="advSearchForm" action="index.php" method="post">
              <h2 class="tab">Alphabetical Search</h2>		
	        <?php 
               
                    $alphabet = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z');
                    foreach ($alphabet as $letter)
                    {
                      if ($letter=='Z')  
                          $seprator='';
                      else
                          $seprator='¦';
                      //include LIB_DIR.'contents/default.inc.php';  
                        //echo "<a href=\"?letternew=" . $letter . "&subtype=".$_GET['subtype']."&subid=".$_GET['subid']."\"style='font-weight: bold;color: #666;  margin-right:10px;'>" .$letter . "</a>&nbsp;¦&nbsp;";
                       // echo "<a href=?letter=" . $letter . "&subtype=Books>" .$letter . "</a>&nbsp;$seprator&nbsp;";
                       echo "<a href=?p=book_request&letter=" . $letter . "&subtype=Books>" .$letter . "</a>&nbsp;$seprator&nbsp;";
                    }
                   // echo "<a href=\"?subtype=".$_GET['subtype']."&subid=".$_GET['subid']."\"style='font-weight: bold;color: #666;'>Show-All</a>";
                   //echo "<a href=\"?subtype=Books&subid=".$_GET['subid']."\"style='font-weight: bold;color: #666;'>Show-All</a>";
                ?>
        </form>
       </center>
        <br>

     </script>
      <?php
     if ($_REQUEST['search1']=='Search' || isset($_REQUEST['letter']))
        {                                  
         include LIB_DIR.'contents/default.inc.php';
        }
         
    //echo "hiii"; 
        if ($_REQUEST['search1']=='Search') {
            // '<hr width="97%" size="1" />'."\n";
            echo '<center>'.simbio_paging::paging(100, 10, 5).'</center>';
       } 
        if(isset($_REQUEST['advancesearch'])) 
       {
      ?>
     	<div class="tab-page" id="tabPage3" align="left">
	<h2 class="tab">Advanced Search&nbsp;&nbsp;&nbsp;&nbsp;<a href="index.php?subtype=<?php echo 'Books'; ?>&subid=<?php echo $_GET['subid']; ?>">Go To Simple Search</a></h2>
        <script type="text/javascript">tp1.addTabPage( document.getElementById( "tabPage3" ) );</script>        
           <form name="advSearchForm" id="advSearchForm" action="index.php" method="get">
            
                    <table>
                            <tr>
                                    <td align=left valign=top>
                                        <select name="fields[]">
                                            <option value=keywords>Keywords</option>
                                            <option value=title>Title</option>
                                            <option value=author>Author(s)</option>
                                            <option value=isbn_issn>ISBN/ISSN</option>
                                            <option value=publisher>publisher</option>
                                            <option value=subject>Subject</option>
                                        </select>
                                    </td>
                                    <td align=left valign=top></td>
                                    <td align=left valign=top class="l_inp">
                                        <input type="text" name="textbox[]" value="<?php echo $_REQUEST['textbox'][0]; ?>" size="70"  />
                                    </td>
                                    <td align=left valign=top>
                                        <input type="button" onclick="appendtable12()" value="+" />
                                    </td>
                                    <td align=left valign=top>
                                        <input type=hidden name="andor[]" value='s' />
                                    </td>
                            </tr>
                     </table>

<table>
    <tr>
        <td>
            <div id="divide" height="100" align="center"></div>
        </td>
    </tr>
</table>
<input type="hidden" name="cal" id="cal" value="" />
     <?php echo __('Material Type'); ?>                                     
     <?php echo __('Material Sub Type'); ?> :
     <?php echo '<table><tr><td valign=top>';
        
    $mst_sub_q = $dbs->query('SELECT material_sub_name FROM mst_material_sub_type LIMIT 50');
    $count=1;
        while ($mst_sub_d = $mst_sub_q->fetch_row()) 
        {   
            echo '<input type=checkbox value='.urlencode($mst_sub_d[0]).' name=mst_sub[] />'.$mst_sub_d[0].'<br/>';
            if($count%8==0)
            {
                echo '</td><td valign=top>';
            } 	
            $count=$count+1;	
        }
    echo '</td></tr></table>';

	    ?>	 

      
 <?php echo __('Collection Type'); ?> :       
 <?php echo $colltype_list; ?>    
 <?php echo __('Location'); ?> :
 <?php echo $location_list; ?>
       
     <br/> 
            <input type="submit" name="search" value="<?php echo __('Search'); ?>" />
	<input type="hidden" name="advancesearch" value="set" /> 		
            
            </form>
	  
		
	</div>
      <?php      
      }
      
      ?>
     
     
             
             
         
     
    <?php
    //echo '<a href="index.php?subtype=Books&subid='.$sub_id.'">Request for Reserve</a>';
    //echo '</div><div>';        
   //echo '</div><div>';
    
    //    echo showLoanList();                   
?>
<script language='JavaScript'>
function a()
{    
    
    <?php 
   
  //include('lib/loan_request.php');?>

}

</script> 

<?php	
        $memberID = trim($_SESSION['DUSER_ID']);    
	$count_request="select count(temp_id) as temp_id from temp_request where member_id='$memberID'";   
	$count_request=$dbs->query($count_request);
	$a='';
	while($row=$count_request->fetch_assoc())
	{
		 $a=$row['temp_id'];
	}
	if($a>0)
	{
//                echo 'Currently You Have '. $a .' Book Requests';
//                echo showbookrequest();
	}
	else
	{	//echo 'Currently You Have ' . $a . ' Book Requests';
//                echo showbookrequest();
//		echo "<table width=100% align='center' class='memberLoanList' cellpadding='5' cellspacing='0'>";
//		echo "<tr><td align='center' style='color: red; background-color: rgb(204, 204, 204);'>No Data</td></tr>";
//		echo "</table>";

	}
        

echo '</div></div>';


?>


<?php

function showbookrequest($num_recs_show = 20)
{
//utility::jsAlert("hiii");die;

        global $dbs;

       $memberID = trim($_SESSION['DUSER_ID']);

        $loan_list_query = $dbs->query("SELECT L.confirm_date,L.req_status,L.temp_id, b.title, i.item_code, L.request_date FROM temp_request AS L
        LEFT JOIN item AS i ON L.item_code=i.item_code
        LEFT JOIN mst_coll_type AS ct ON i.coll_type_id=ct.coll_type_id
        LEFT JOIN '".$inte_schema."'.tblstudent AS ts ON L.member_id=ts.enrollment_no
        LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id
        WHERE  L.member_id='$memberID'");
//echo $loan_list_query;die;
  echo "<form method=post action=index.php?p=book_request>";
  echo "<table width=100% cellspacing=0 cellpadding=5  class='memberLoanList'>";
  echo "<tr class='dataListHeader' style='font-weight: bold;'><td>Delete</td><td>Title</td><td>Request Date</td></tr>";
 
  
  $i='';	
              while ($loan_list_data = $loan_list_query->fetch_assoc())
             {
                    if($loan_list_data['confirm_date']!='')
                    {
                    $loan_list_data['confirm_date']=date("d-m-Y", strtotime($loan_list_data['confirm_date']));
                    }
                    echo "<tr>";
                    echo "<td class='alterCell2'><input type='checkbox' name='checkbox[]' id='checkbox[]' value=".$loan_list_data['temp_id']." ></td>";
                    echo "<td>" . $loan_list_data['item_code'] ."</td>";
                    echo "<td class='alterCell2'>" . $loan_list_data['title'] ."</td>";
                    echo "<td class='alterCell2'>" . date("d-m-Y", strtotime($loan_list_data['request_date'])) ."</td>";
                    echo "<td class='alterCell2'>" . $loan_list_data['confirm_date'] ."</td>";
                    echo "<td class='alterCell2'>" . $loan_list_data['req_status'] ."</td>";

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
//echo '</div>';
?>
