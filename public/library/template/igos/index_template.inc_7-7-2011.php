<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en" dir="ltr"><head><title><?php echo $page_title; ?></title><meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

<link rel="icon" href="webicon.ico" type="image/x-icon" />
<link rel="shortcut icon" href="webicon.ico" type="image/x-icon" />
<link href="template/core.style.css" rel="stylesheet" type="text/css" />
<link href="template/igos/960.css" rel="stylesheet" type="text/css" />
<link href="<?php echo $sysconf['template']['css']; ?>" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="js/prototype.js"></script>
<script type="text/javascript" src="js/form.js"></script>
<script type="text/javascript" src="js/gui.js"></script>


<?php error_reporting(0);?>

<?php echo $metadata; ?>

<div id="main_wrapper">
<div class="container_12">
    <!--header-->
   <div class="new_header">
    <div  id="header">
    <h1 id="app-title"><a href="index.php"><?php echo $sysconf['library_name']; ?></a><div><?php echo $sysconf['library_subname']; ?></div></h1>
    </div>
   
    </div>
    <div class="clear">&nbsp;</div>
    <!--header end-->

    <!--application main menu-->
   <div class="menu_bg" id="main-menu">
        <ul id="primary-links">
            <li><a class="menu" href="index.php"><span><?php echo __('Home'); ?></a></span></li>
          <!--comment made by iresh on 11/1/2011    <li><a class="menu" href="index.php?p=libinfo"><span><?php echo __('Library Information'); ?></span></a></li>-->
			<!--comment made by iresh on 11/1/2011 <li><a class="menu" href="index.php?p=member"><span><?php echo __('Member Area'); ?></span></a></li>-->
		<!--added by iresh on 11/1/2011 --><!--comment by iresh on 22-1-2011	<li><a class="menu" href="index.php?p=member"><span><?php echo __('Member Login'); ?></span></a></li>-->
          <!--comment made by iresh on 11/1/2011    <li><a class="menu" href="index.php?p=peta"><span>Show map</span></a></li>-->
            <li><a class="menu" href="index.php?p=help"><span><?php echo __('Help on Search'); ?></span></a></li>
       <?php if (empty($_REQUEST['p']) and empty($_SESSION['m_member_type'])) {?>       <li><a class="menu" href="index.php?p1=login" onclick="javascript:popup"><span><?php echo __('Login');  }?></span></a></li>
	   <?php if (utility::isMemberLogin()) {?><!-- added by iresh on 7-4-2011 -->
           <li><a class="menu" href="index.php?p=member&login"><span><?php echo __('My Profile'); ?></span></a></li>
           <?php }?>
	   <?php if (utility::isMemberLogin()) {?><!-- added by iresh on 7-4-2011 -->
           <li><a class="menu" href="index.php?p=book_request"><span><?php echo __('View Request'); ?></span></a></li>
	   
         <?php }?> 
 <!--comment made by iresh on 11/1/2011    <li><a class="menu" href="http://www.igos.web.id"><span>IGOS</span></a></li>
            <li><a class="menu" href="http://senayan.diknas.go.id"><span>SENAYAN</span></a></li>-->
           <!--comment by iresh on 22-1-2011 <li><a class="menu" href="index.php?p=login"><span><?php echo __('Librarian LOGIN'); ?></span></a></li>-->
        </ul>
	 </div>
     <div>
	 <?php if (utility::isMemberLogin()) {?><li><?php echo $header_info; ?></li><?php }?>
<?php if(empty($_GET['subtype']) && empty($_GET['search'])) {?>
<div align="center">     
<form method="get" action="index.php">

<div style="solid black;padding:4px;width:50em;">
<table border="0">
<tr><td  align="center"><input type="text" name="q" size="25" maxlength="255" value="" /></td><td><input type="submit" name="Search" value="Keyword Search" /></td><td><input type="submit" name="Search" value="Google Search" /><input type="hidden" name="search1" value="Search" /></td><td><!--<a href="index.php?advsearch="p">Advance Search</a>--></td></tr>

</table>
</div>

</form> </div>
<?php } 
?>


    </div>
   
    <div class="clear">&nbsp;</div>
    <div class="spacer">&nbsp;</div>
    <!--application main menu end-->
  
    <!--application navigation menu/side menu-->
 <div class="grid_9" id="side-menu1"> 

        <!-- language selection -->
            <!-- comment by iresh on 11/1/2011<div class="block-header"><?php echo __('Select Language'); ?></div>
            <form name="langSelect" action="index.php" method="get">
            <select name="select_lang" onchange="document.langSelect.submit();">
            <?php echo $language_select; ?>
            </select>
            </form>-->
        <!-- language selection end -->
<?php
/*echo "SELECT b.title,b.image,b.biblio_id,l.item_code,COUNT(l.loan_id) AS total_loans FROM `loan` AS l
    LEFT JOIN item AS i ON l.item_code=i.item_code
    LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id
    GROUP BY b.biblio_id ORDER BY COUNT(l.loan_id) DESC LIMIT 10'";*/
$stat_query = $dbs->query('SELECT b.title,b.image,b.biblio_id,l.item_code,COUNT(l.loan_id) AS total_loans FROM `loan` AS l
    LEFT JOIN item AS i ON l.item_code=i.item_code
    LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id
    GROUP BY b.biblio_id ORDER BY COUNT(l.loan_id) DESC LIMIT 10');
$stat_data = '';
$current_path=$_SERVER['HTTP_HOST'];
while ($data = $stat_query->fetch_row()) {
    
    $stat_data .= 'images/docs/'.$data['1'].',';
    $stat_data_id.= 'http://'.$current_path.'/index.php?p=show_detail&id='.$data['2'].',';
}
$stat_data=explode(",",$stat_data);

$stat_data_id=explode(",",$stat_data_id);



?>
<!-- <?php if(!$_REQUEST['p'])
{?> -->
<!-- <div class="grid_2" id="tt-menu"> -->


 <!-- <?php } echo "<br>"; ?> -->
<?php 
if(isset($_REQUEST['p1']))
{
if(!(utility::isMemberLogin())) { 
?>

         <!--common login for admin and member added by iresh on 22-1-2011 -->
         <div class="block-header1"><?php echo __('Login'); ?></div>
            <form name="login" action="index.php?p=member" method="post">
            <div class="fieldLabel"><?php echo __('Id/Username'); ?>:</div>
            <input type="text" name="uname" value="<?php echo 'Enter Username'.$_REQUEST['username']; ?>" onclick="this.value=''" style="width:115px;"/>
	    <div class="fieldLabel marginTop"><?php echo __('Password'); ?>:</div>
	    <input type="password" name="pass" value="<?php echo 'Enter Password'. $_REQUEST['password']; ?>" onclick="this.value=''"/>
	    <div class="fieldLabel marginTop"><?php echo __('User Profile'); ?>:</div>	
	   <!-- added by iresh on 9-2-2011-->
             <?php	             
		$member_status = 'unchecked';
		$admin_status = 'unchecked';

		
		if($_REQUEST['profile']=='teacher' || $_REQUEST['profile']=='student' )
	        {
		$member_status = 'checked';
		}
		else if ($_REQUEST['profile']=='admin') {
		$admin_status = 'checked';
		}
		else
		{
			$member_status = 'checked';
		}

	
	   ?>
		
             <input type="radio" name="user" value="member"<?php print $member_status; ?>/>member
             <input type="radio" name="user" value="admin" <?php print $admin_status; ?>/>admin
           <!-- end by iresh on 9-2-2011-->
             <br>
            <input type="submit" name="logMeIn" value="<?php echo __('login'); ?>" class="button marginTop" />
            </form>
          
 	<!--end login form  by iresh --> 
<?php } }
?> <!-- if condition end for hiding login menu when member is login -->
<!-- <?php echo "<br>"; echo "<br>";echo "<br>";echo "<br>";echo "<br>";?> -->
<?php if($_REQUEST['p']!='member' && $_REQUEST['p']!='book_request' && $_REQUEST['p']!='help') {?>
<div style="padding:5px;"></div>
<?php } ?>
<?php if($_REQUEST['p']!='member' && $_REQUEST['p']!='book_request' && $_REQUEST['p']!='help') {
//include('template/igos/accordian_menu.php');
//include('template/igos/sidebar_menu.php');
//include('template/igos/fancydropdown.php');
?>


<?php } ?> <!-- if condition end for hiding login menu when member is login -->
 </div>
<!-- added by parth start-->
<!--<div class="grid_9">
<table width="1025px;">
<tr valign="middle"  height="100px;" align="center" style="color:#FFFFFF">
<td bgcolor="#000033" width="250px;">
STUDENT 
</td>
<td bgcolor="#330066" width="250px;">
TEACHERS 
</td>
<td bgcolor="#660000" width="250px;">
PARENTS 
</td>
<td bgcolor="#FF3300" width="250px;">
PUBLIC 
</td>
</tr>
</table>

</div> -->
<!-- ended by parth -->
<?php if (!$_REQUEST['p']){}?>

<!-- added by parth start-->
<div class="grid_front_page"> 
<?php 
if(isset($_GET['subtype']))
{
$qry2 = "select * from mst_material_sub_type where material_sub_name = '$_GET[subtype]'";
$stat_queryforstudentsubtype = 	$dbs->query($qry2);
while ($datasubtype = $stat_queryforstudentsubtype->fetch_assoc()) {
		$qry3 = "select * from mst_gmd where gmd_id = '$datasubtype[gmd_id]'";
		$stat_queryforstudentgmd = $dbs->query($qry3);
		while ($datagmd = $stat_queryforstudentgmd->fetch_assoc()) {
			$header = $datagmd['gmd_name'];
		}		
} 
$url = "http://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
echo "<table style=font-weight:Bold;background-color:#990000;color:white; ><tr>";
 echo "<td><a href=index.php style=text-decoration:none;color:white;>$_SESSION[m_member_type]</a>--></td><td><a href=index.php style=text-decoration:none;color:white;>$header</a>--></td><td><a href=$url style=text-decoration:none;color:white;>$_GET[subtype]</a></td></tr></table>";

}
include('lib/frontdisplay.php');

 ?>
 </div>
 <?php  if (($_REQUEST['p']!='book_request' && $_REQUEST['p']!='member' && $_REQUEST['p']!='help' && isset($_REQUEST['subtype'])) || ($_REQUEST['search1']=='Search'  && empty($_GET['Search']) || ($_REQUEST['search']=='Search')) )
{  ?>
<div class="grid_2" id="t-menu">
 <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<title>Tab Pane Demo (WebFX)</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

<script type="text/javascript" src="template/igos/local/webfxlayout.js"></script>

<!-- this link element includes the css definitions that describes the tab pane -->
<!--
<link type="text/css" rel="stylesheet" href="tab.winclassic.css" />
-->
<link id="luna-tab-style-sheet" type="text/css" rel="stylesheet" href="template/igos/css/luna/tab.css" disabled="disabled" />
<link id="webfx-tab-style-sheet" type="text/css" rel="stylesheet" href="template/igos/css/tab.webfx.css" disabled="disabled" />
<link id="winclassic-tab-style-sheet" type="text/css" rel="stylesheet" href="template/igos/css/tab.winclassic.css"  disabled="disabled" />
<!-- the id is not needed. It is used here to be able to change css file at runtime -->

<style type="text/css">

.dynamic-tab-pane-control .tab-page {
	height:		auto;
	width: 1000px;
}

.dynamic-tab-pane-control .tab-page .dynamic-tab-pane-control .tab-page {
	height:		100px;
}

form {
	margin:		0;
	padding:	0;
}

/* over ride styles from webfxlayout */



.dynamic-tab-pane-control h2 {
	text-align:	center;
	width:		auto;
}

.dynamic-tab-pane-control h2 a {
	display:	inline;
	width:		auto;
}

.dynamic-tab-pane-control a:hover {
	background: transparent;
}



</style>
<script type="text/javascript" src="template/igos/js/tabpane.js"></script>


<script type="text/javascript">
//<![CDATA[

function setLinkSrc( sStyle ) {
	document.getElementById( "luna-tab-style-sheet" ).disabled = sStyle != "luna";
	document.getElementById( "webfx-tab-style-sheet" ).disabled = sStyle != "webfx"
	document.getElementById( "winclassic-tab-style-sheet" ).disabled = sStyle != "winclassic"
	
	document.documentElement.style.background = 
	document.body.style.background = Style == "webfx" ? "white" : "ThreeDFace";
}

setLinkSrc( "luna" );

//]]>
</script>

<div class="tab-pane" id="tabPane1" align="center">

<script type="text/javascript">
tp1 = new WebFXTabPane( document.getElementById( "tabPane1" ) );
//tp1.setClassNameTag( "dynamic-tab-pane-control-luna" );
//alert( 0 )
</script>



	<div class="tab-page" id="tabPage2">

		<h2 class="tab">Search</h2>
		
		<script type="text/javascript">tp1.addTabPage( document.getElementById( "tabPage1" ) );</script>
		<form name="simpleSearch" action="index.php" method="get">
                <input type="text" name="keywords" />
               
                <input type="submit" name="search1" value="<?php echo __('Search'); ?>" class="button marginTop"/>
                <input type="hidden" name="subtype" value="<?php echo $_GET['subtype']; ?>"/> 
                <input type="hidden" name="subid" value="<?php echo $_GET['subid']; ?>"/> 
                
               
                </form>
	
		<h2 class="tab">AlphabeticalSearch</h2>
		
		<script type="text/javascript">tp1.addTabPage( document.getElementById( "tabPage2" ) );</script>
		 <form name="advSearchForm" id="advSearchForm" action="index.php" method="post">
	        <?php  $alphabet = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z');
		foreach ($alphabet as $letter) {
		echo "<a href=\"?letternew=" . $letter . "&subtype=".$_GET['subtype']."&subid=".$_GET['subid']."\"style='font-weight: bold;color: #666;  margin-right:10px;'>" . $letter . "</a>&nbsp;¦&nbsp;";
		}
		echo "<a href=\"?subtype=".$_GET['subtype']."&subid=".$_GET['subid']."\"style='font-weight: bold;color: #666;'>Show-All</a>";
	?>
   </form>
		
	</div>

	<div class="tab-page" id="tabPage3" align="left">
		<h2 class="tab">Advanced Search</h2>
		
		<script type="text/javascript">tp1.addTabPage( document.getElementById( "tabPage3" ) );</script>
		
        <!-- advanced search -->
            <form name="advSearchForm" id="advSearchForm" action="index.php" method="get">
            <?php echo __('Title'); ?> :
            <input type="text" name="title"/>
	   <?php echo __('Author(s)'); ?> :
            <?php echo $advsearch_author; ?>
            <?php echo __('Subject(s)'); ?> :
            <?php echo $advsearch_topic; ?>
            <?php echo __('ISBN/ISSN'); ?> :
            <input type="text" name="isbn" />
           <!-- commment by iresh on 21-1-2011 <?php echo __('GMD'); ?> :-->
	    <?php echo "<br>"; ?>
            <?php echo __('Material Type'); ?> :
            <select name="gmd" />
            <?php echo $gmd_list; ?>
            </select>
	    <?php echo __('Material Sub Type'); ?> :
            <select name="mst_sub" />
            <?php echo $mst_sub_list; ?>
            </select>
            <?php echo __('Collection Type'); ?> :
            <select name="colltype" />
            <?php echo $colltype_list; ?>
            </select>
            <?php echo __('Location'); ?> :
            <select name="location" />
            <?php echo $location_list; ?>
            </select>
           <!--Added by iresh on 21-1-2011 --> <br/> 
            <input type="submit" name="search" value="<?php echo __('Search'); ?>" />
	
            <!-- <input type="button" value="More Options" onclick="" class="button marginTop" /> -->
            </form>
	  <!-- advanced search end -->
		
	</div>

	
	
	</div>
   


<script type="text/javascript">
//<![CDATA[

setupAllTabs();

//]]>
</script>


</div>

<?php } ?>

<!-- ended by parth start-->
    <!--application navigation menu/side menu-->

    <!--application main content -->

<?php if($_REQUEST['p']!='member' && $_REQUEST['p']!='book_request' && $_REQUEST['p']!='help') {?>
    <div class="grid_9"  id="main-content">
<?php }
else {
?>
 <div class="grid_9"  id="main-content2">

<?php }?>

    <!-- <?php echo $header_info; ?> -->
   <!-- comment by iresh on 22-1-2011 <div id="info-box"><?php echo $info; ?></div>-->
    <!--added by iresh on 22-1-2011<?php echo $info; ?>-->

<?php 
if(!$_REQUEST['p'])
{
//include('template/igos/fancydropdown.php');
//include('template/igos/superfish-1.4.8/example.php');
}

?>
 <?php

if(isset($_REQUEST['resource']) || isset($_REQUEST['gmd_search']) )
	{
		
		echo '<font size="15" color="red">'.$_REQUEST['resource']. ' >> ' .$_REQUEST['gmd_search'].'';
	
	}
//Comment Start By Parth 5/7/2011
if($_REQUEST['standard'] || $_REQUEST['subject'] || $_REQUEST['material_sub_type'])
//Comment Ended By Parth 5/7/2011 
//if($_REQUEST['standard'] || $_REQUEST['material_sub_type'])
	 {
if($_REQUEST['material_sub_type']!='')
		{
		$resourcename=$dbs->query('select mr.material_resource_name,g.gmd_name FROM mst_material_sub_type AS ms 
			       LEFT JOIN mst_gmd AS g ON g.gmd_id=ms.gmd_id
			       LEFT JOIN mst_material_resource_type AS mr ON mr.material_resource_id=g.gmd_code
			       WHERE ms.material_sub_id='.$_REQUEST['material_sub_type'].'');
	        while($row=$resourcename->fetch_assoc())
		{
			 $resource=$row['material_resource_name'];
			 $gmdname=$row['gmd_name'];
		}
}
if($_REQUEST['standard']!='')
{
		$standardname=$dbs->query('select standard_name from mst_standard where standard_id='.$_REQUEST['standard'].'');
		while($row=$standardname->fetch_assoc())
		{
			$standard=$row['standard_name'];
		}
}
if($_REQUEST['subject']!='')
{

		$subjectname=$dbs->query('select topic from mst_topic where topic_id='.$_REQUEST['subject'].'');
		while($row=$subjectname->fetch_assoc())
		{
			$subject=$row['topic'];
		}
}
if($_REQUEST['material_sub_type']!='')
{

		$material_sub_type=$dbs->query('select material_sub_name from mst_material_sub_type where material_sub_id='.$_REQUEST['material_sub_type'].'');
		while($row=$material_sub_type->fetch_assoc())
		{	
			$materialsubtype=$row['material_sub_name'];
		}
}
if($_REQUEST['subjecttype']!='')
{
		$subsubject=$dbs->query('select subject_type_name from mst_subject_type where subject_type_id='.$_REQUEST['subjecttype'].'');
		while($row=$subsubject->fetch_assoc())
		{	
			$sub_subject=$row['subject_type_name'];
		}
}
		//echo $resource . '>>' . $gmdname  . '>>' . $standard . '>>' . $subject . '>>' .$materialsubtype;
		echo $resource;
		if($gmdname!='0' && $gmdname!='')
		{
			echo '>>'. $gmdname;
		}
		if($standard!='0' && $standard!='')
		{
			echo '>>' . $standard ;
		}
		if($subject!='0' && $subject!='')
		{
			echo '>>' . $subject;
		}
		if($sub_subject!='0' && $sub_subject!='' )
		{
			echo '>>' . $sub_subject;
		}
		if($materialsubtype!='0' && $materialsubtype!='')
		{
			echo '>>' .$materialsubtype;
		}
		echo '</font>';
	}
		if(!$_REQUEST['p'])
		{
		     //comment by parth 30/06/2011 start
		    //include('admin/standard_subject_filter.php');
			//comment end by parth 30/06/2011 
			
		}
	
 ?>
<!--<select name="standard" style="width: 20%;" onchange="getsubjectofstandard(this.value);">
 <?php

	$standard=$dbs->query('select standard_id,standard_name from mst_standard');

	while($row=$standard->fetch_assoc())
	{	
		echo '<option value='.$row['standard_id'].'>'.$row['standard_name'].'</option>';
	
	}

 ?>
</select>

<?php echo '<div id="ajax"></div>'; ?>
-->
<style type="text/css">
<!--
#container12{
    width:1000px;
    height:1000px;
    overflow:hidden;
    
}
#container12 iframe {
    width:1000px;
    height:1000px;
    //margin-left:-160px;
    margin-top:-280px;   
    border:0;
 }
-->
</style>

    
    <?php 
	if($_SESSION['m_member_type']=='Parent')
	{
		if(isset($_GET['q']) && $_GET['Search'] == "Google Search")
		{?>
		<!-- <iframe src="http://books.google.com/books?q=<?php echo $_GET['q']; ?>" width=1000px height=1000px ></iframe>-->
<div id="container12">
<iframe src="http://www.googlecodesamples.com/books/php/BooksBrowser/index.php?queryType=all&maxResults=6&searchTerm=<?php echo $_GET['q']; ?>" width=1000px height=1000px ></iframe>
</div>
		<?php }
		else if(empty($_GET['subtype']) && $_GET['p']!="help" && $_GET['p']!="member" && $_GET['p']!="book_request" && $_GET['p']!="show_detail" && $_GET['p']!="book_request" && empty($_GET['Search']) && $_GET['search']!="Search")
		{?>		
		<?php //echo $main_content; 	
		}
		else
		{
		echo $main_content; 	
		}
	}
	else if($_SESSION['m_member_type']=='Student')
	{
		if(isset($_GET['q']) && $_GET['Search'] == "Google Search")
		{?>
		<!-- <iframe src="http://books.google.com/books?q=<?php echo $_GET['q']; ?>" width=1000px height=1000px ></iframe>-->
<div id="container12">
<iframe src="http://www.googlecodesamples.com/books/php/BooksBrowser/index.php?queryType=all&maxResults=6&searchTerm=<?php echo $_GET['q']; ?>" width=1000px height=1000px ></iframe>
</div>
		<?php }
		else if(empty($_GET['subtype']) && $_GET['p']!="help" && $_GET['p']!="member" && $_GET['p']!="book_request" && $_GET['p']!="show_detail" && $_GET['p']!="book_request" && empty($_GET['Search']) && $_GET['search']!="Search")
		{?>		
		<?php //echo $main_content; 	
		}
		else
		{
		echo $main_content; 	
		}
	}
	else if($_SESSION['m_member_type']=='Teacher')
	{
		if(isset($_GET['q']) && $_GET['Search'] == "Google Search")
		{?>
		<!-- <iframe src="http://books.google.com/books?q=<?php echo $_GET['q']; ?>" width=1000px height=1000px ></iframe>-->
<div id="container12">
<iframe src="http://www.googlecodesamples.com/books/php/BooksBrowser/index.php?queryType=all&maxResults=6&searchTerm=<?php echo $_GET['q']; ?>" width=1000px height=1000px ></iframe>
</div>
		<?php }
		else if(empty($_GET['subtype']) && $_GET['p']!="help" && $_GET['p']!="member" && $_GET['p']!="show_detail" && $_GET['p']!="book_request" && empty($_GET['Search']) && $_GET['search']!="Search")
		{?>
		
		<?php //echo $main_content; 	
		}
		else
		{
		echo $main_content; 	
		}
	}
	else
	{
		if(isset($_GET['q']) && $_GET['Search'] == "Google Search")
		{
//include('http://www.googlecodesamples.com/books/php/BooksBrowser/index.php?queryType=all&maxResults=6&searchTerm='.$_GET['q']);
?>

		<!--<iframe src="http://books.google.com/books?q=<?php echo $_GET['q']; ?>" width=1000px height=1000px ></iframe>-->

<div id="container12">
<iframe src="http://www.googlecodesamples.com/books/php/BooksBrowser/index.php?queryType=all&maxResults=6&searchTerm=<?php echo $_GET['q']; ?>" width=1000px height=1000px ></iframe>
</div>
		<?php }
		
		else if(empty($_GET['subtype']) && $_GET['p']!="help" && $_GET['p']!="member" && $_GET['p']!="book_request" && $_GET['p']!="show_detail" && $_GET['p']!="book_request" && empty($_GET['Search']) && $_GET['search']!="Search")
		{?>		
		<?php //echo $main_content; 	
		}
		else
		{
		echo $main_content; 	
		}
	
	}
	?>
    
    </div>
    <!--application main content end -->

    <!--footer-->
    <div class="grid_12" id="footer">
    <?php echo $sysconf['page_footer']; ?>
    </div>

    <!--footer end-->

    <div class="clear">&nbsp;</div>
    <div class="spacer">&nbsp;</div>
</div>

</div>
</body>
</html>
