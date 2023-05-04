<script type="text/javascript">
var con = function(str)
{

if (str.length==0)
  {
  document.getElementById("txtHint").innerHTML="";
  return;
  }
if (window.XMLHttpRequest)
  {// code for IE7+, Firefox, Chrome, Opera, Safari
  xmlhttp=new XMLHttpRequest();
  }
else
  {// code for IE6, IE5
  xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");
  }
xmlhttp.onreadystatechange=function()
  {
  if (xmlhttp.readyState==4 && xmlhttp.status==200)
    {
    document.getElementById("txtHint").innerHTML=xmlhttp.responseText;
    }
  }
xmlhttp.open("GET","gethint.php?q="+str,true);
xmlhttp.send();
}
</script>

<?php
session_start();
echo '<pre>';
print_r($_REQUEST);


// main system configuration
//require '../../../sysconfig.inc.php';
require '../../../../sysconfig.inc.php';
// start the session
require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
require SENAYAN_BASE_DIR.'admin/default/session_check.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/simbio_dbop.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/form_maker/simbio_form_table_AJAX.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/template_parser/simbio_template_parser.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/datagrid/simbio_dbgrid.inc.php';

/*
if(isset($_REQUEST['Confirm'])=='Confirm')
{


                $fields =$values ='';
		foreach($_REQUEST['values'] as $column=>$value)
		{


		$fields .=$column.",";	

		$values .= "'".str_replace("\'","''",$value)."',";

		}
	
	       $values=substr($values,0,-1);

        	if($values)
		{


                  $sql='UPDATE temp set status="Confirm" where temp_id IN('.$values.')';
		  $dbs->query($sql);
		}

		
	        echo '<script type="text/javascript">';
      	        echo 'alert(\''.__('Book Request Confirmed').'\');';
               //echo 'location.href = \'modules/circulation/index.php\';';
                echo '</script>';


}
*/



//$memberID = trim($_SESSION['memberID']);
$loan_list_query = $dbs->query("SELECT L.temp_id, b.title, i.item_code, L.request_date,L.status,m.member_id,m.member_name FROM temp_request AS L
        LEFT JOIN item AS i ON L.item_code=i.item_code
        LEFT JOIN mst_coll_type AS ct ON i.coll_type_id=ct.coll_type_id
        LEFT JOIN member AS m ON L.member_id=m.member_id
        LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id
       ");

  echo'<form method="post">';	
  echo "<table border='0' width=100% cellspacing=0>";
  echo "<tr class='dataListHeader'><td></td><td align=center>Item Code</td><td align=center>Title</td><td align=center>Member Id</td><td align=center>Member Name</td><td align=center>Request Date</td><td align=center>Status</td></tr>";
 
  
  $i='';	
  while ($loan_list_data = $loan_list_query->fetch_assoc())
 {
	if($i%2==0)
		{
			$style="background-color:#DEDEDC";
		}
		else
		{
			$style="background-color:#C1C1C1";
		}
	echo "<tr  style='".$style."'>";
        echo "<td><input type=checkbox name=values[$loan_list_data[temp_id]] id=checkbox[] value=". $loan_list_data['temp_id']." ></td>"; 
	echo "<td align=center>" . $loan_list_data['item_code'] ."</td>";
	echo "<td align=center>" . $loan_list_data['title'] ."</td>";
	echo "<td align=center>" . $loan_list_data['member_id'] ."</td>";
	echo "<td align=center>" . $loan_list_data['member_name'] ."</td>";
	echo "<td align=center>" . $loan_list_data['request_date'] ."</td>";
        echo "<td align=center>" . $loan_list_data['status'] ."</td>";
	
	echo "</tr>";
       $i++;
}

//echo '<tr><td><input  name="button" value="Confirm" type="button" onclick="showHint(this.value)"></td></tr>';	
echo '<tr><td><input type="button" value="'.gettext('Confirm').'" onclick="ccc(\'f1\', \''.gettext('Are you sure want to confirm?').'\')" /><input type="hidden" name="Confirm" value="true" /></td></tr>';	

echo "</table>";
echo"</form>";




// get the buffered content
$content = ob_get_clean();
// include the page template
require SENAYAN_BASE_DIR.'/admin/admin_template/notemplate_page_tpl.php';
?>
