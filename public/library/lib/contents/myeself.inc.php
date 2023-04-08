<link rel="stylesheet" type="text/css" href="template/school/css/tab_tab.css"/>
<script src="template/school/jquery_tab.js" type="text/javascript">
</script>
<script>
// perform JavaScript after the document is scriptable.
$(function() 
{    
	// setup ul.tabs to work as tabs for each div directly under div.panes
	$("ul.tabs").tabs("div.panes > div");
});
</script>
	<style>
	
/* tab pane styling */
.panes div {
	display:none;		
	padding:15px 10px;
	border:1px solid #999;
	border-top:1;
	height:auto;
	font-size:14px;
	background-color:#fff;
}
.panes div div
{
display:block;
border:0px;
padding:0px 0px;
}

	</style>
<?php

require LIB_DIR.'member_logon.inc.php';
error_reporting(0);
echo "<ul class=tabs>";
echo '<li><a href="#"><h3 class="memberInfoHead">'.__('My-Eshelf').'</h3></a></li>';
echo '<li><a href="#"><h3 class="memberInfoHead">'.__('Google Docs').'</h3>'."\n".'</a></li>';
//echo '<li><a href="#"><h3 class="memberInfoHead">'.__('Create Your Document').'</h3>'."\n".'</a></li>';
echo '</ul>';
echo '<div class="panes">';
echo '<div id="main-content2"><table border=0 align=center width=100% align=center>';
echo '<tr><td  style="font-size:19px;" align="center" bgcolor=lightgray>My-Eshelf</td></tr><tr><td width=100%>';

//echo "hiiii";
if($_SESSION['DUSER_ID']!='')
{	
        //added start by Parth 4/8/2011
	if(isset($_REQUEST['set_delete']))
	{
            
	//$last_update = date('Y-m-d H:i:s');
	  
       
                  
          
              $sql="DELETE from biblio_myself where member_id = '".$_SESSION['DUSER_ID']."' AND biblio_id = $_GET[set_delete]";
       	      //echo $sql;
              $dbs->query($sql);
              
	}
	
}
//$main_content = $biblio_list->getDocumentList($sysconf['opac_result_num']);
include LIB_DIR.'contents/default.inc.php';
echo '</td></tr></table></div><div><table  align=center><tr><td style="font-size:19px;" align="center" bgcolor=lightgray>Google Docs</td></tr><tr><td valign=top>';
/*header("Content-type: application/vnd.ms-word");
header("Content-Disposition: attachment;Filename=document_name.doc");

// // echo "<html>";
echo "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=Windows-1252\">";
echo "<body>";
echo "<b>My first document</b>";
echo "</body>";
echo "</html>";*/
$query = "select * from ".$inte_schema.".tblstudent where enrollment_no = '".$_SESSION['DUSER_ID']."' and sub_institute_id = '".$_SESSION['SUB_INSTITUTE_ID']."'";
 $check1 = $dbs->query($query);	
$get = $check1->fetch_assoc();
//print_r($get);die;
?>
        
<iframe src="https://docs.google.com" target="_self" name="FRAME1" width=900px frameborder=0 height=400px scrollbar=yes allowautotransparency=true></iframe>
</td></tr></table></div>
<div><table  align=center><tr><td align=center>
<iframe src="../../../pfn/forlibrary.php?username=<?php echo $_SESSION['DUSER_ID']; ?>&password=<?php echo $get['password'];?>" target="_self" name="FRAME1" width=900px frameborder=0 height=450px scrollbar=yes allowautotransparency=true></iframe>

<?php

echo '</td></tr></table></div>';
echo '</div>';

?>



