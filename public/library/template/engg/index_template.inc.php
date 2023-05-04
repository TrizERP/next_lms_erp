<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en" dir="ltr"><head><title><?php echo $page_title; ?></title><meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

<link rel="icon" href="webicon.ico" type="image/x-icon" />
<link rel="shortcut icon" href="webicon.ico" type="image/x-icon" />
<link href="template/core.style.css" rel="stylesheet" type="text/css" />
<link href="template/igos/960.css" rel="stylesheet" type="text/css" />
<link href="<?php echo $template_css; ?>" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="js/prototype.js"></script>
<script type="text/javascript" src="js/form.js"></script>
<script type="text/javascript" src="js/gui.js"></script>



<?php error_reporting(0);?>

<?php echo $metadata; ?>
<!-- added script by parth 20/7/2011 -->
<script type="text/javascript">
function changeselectbox()
{
//alert(document.getElementById("selectlibrary").value);
location.href=<?php echo SENAYAN_WEB_ROOT_DIR; ?>+'index.php?selectlibrary='+document.getElementById("selectlibrary").value;
}
</script>
<!-- added ended script by parth 20/7/2011 -->
<div id="main_wrapper">
<div class="container_12">
    <!--header-->
   <div class="new_header">
    <div  id="header">
    <h1 id="app-title"><a href="index.php"><?php echo $page_title; ?></a><div><?php echo $library_subname; ?><!--<select name="selectlibrary" id="selectlibrary" onchange="javascript:changeselectbox()"><option value="trizino_slibrary">School Library</option><option value="select library" selected="selected">select library</option><option value="trizino_engg">Engineering Library</option></select>--></div></h1><?php /*if (empty($_REQUEST['p']) and empty($_SESSION['m_member_type'])) {*/ ?><!--<div id="loginlink"> <a href="#" style="text-decoration:none;font-size:15px;"><?php /*include('login_test.php');*/ ?></a></div>--> <?php /*}*/ ?>
    </div>
   
    </div>
    <div class="clear">&nbsp;</div>
    <!--header end-->

    <!--application main menu-->
   <div class="menu_bg" id="main-menu">
        <ul id="primary-links">
            <li><a class="menu" href="index.php"><span><?php echo gettext('Home'); ?></a></span></li>
          <!--comment made by iresh on 11/1/2011    <li><a class="menu" href="index.php?p=libinfo"><span><?php echo gettext('Library Information'); ?></span></a></li>-->
			<!--comment made by iresh on 11/1/2011 <li><a class="menu" href="index.php?p=member"><span><?php echo gettext('Member Area'); ?></span></a></li>-->
		<!--added by iresh on 11/1/2011 --><!--comment by iresh on 22-1-2011	<li><a class="menu" href="index.php?p=member"><span><?php echo gettext('Member Login'); ?></span></a></li>-->
          <!--comment made by iresh on 11/1/2011    <li><a class="menu" href="index.php?p=peta"><span>Show map</span></a></li>-->
            <li><a class="menu" href="index.php?p=help"><span><?php echo gettext('Help on Search'); ?></span></a></li>
       <?php /*if (empty($_REQUEST['p']) and empty($_SESSION['m_member_type'])) {*/ ?>      <!-- <li><a class="menu" href="index.php?p1=login" onclick="javascript:popup"><span>--><?php /*echo gettext('Login');  }*/?><!--</span></a></li>-->
	   <?php if (utility::isMemberLogin()) {?><!-- added by iresh on 7-4-2011 -->
           <li><a class="menu" href="index.php?p=member&login"><span><?php echo gettext('My Profile'); ?></span></a></li>
           <?php }?>
	   <?php if (utility::isMemberLogin()) {?><!-- added by iresh on 7-4-2011 -->
           <li><a class="menu" href="index.php?p=book_request"><span><?php echo gettext('Book Request'); ?></span></a></li>
           <?php }?>
            <?php if (utility::isMemberLogin()) {?><!-- added by iresh on 7-4-2011 -->  
	   <li><a class="menu" href="index.php?p=rescentview"><span><?php echo gettext('Rescent Views'); ?></span></a></li>
         <?php }?> 
         <?php if (utility::isMemberLogin()) {?><!-- added by iresh on 7-4-2011 -->  
	   <li><a class="menu" href="index.php?p=myeself"><span><?php echo gettext('My-Eshelf'); ?></span></a></li>
         <?php }?> 
 <!--comment made by iresh on 11/1/2011    <li><a class="menu" href="http://www.igos.web.id"><span>IGOS</span></a></li>
            <li><a class="menu" href="http://senayan.diknas.go.id"><span>SENAYAN</span></a></li>-->
           <!--comment by iresh on 22-1-2011 <li><a class="menu" href="index.php?p=login"><span><?php echo gettext('Librarian LOGIN'); ?></span></a></li>-->
        </ul>
	 </div>
     <div>
	 <?php if (utility::isMemberLogin()) {?><li><?php echo $header_info; ?></li><?php }?>
<?php if(empty($_GET['subtype']) && empty($_GET['search']) && empty($_GET['advancesearch'])) {?>
<div align="center">     
<form method="get" action="index.php">

<div style="solid black;padding:4px;width:50em;">
<table border="0">
<tr><td  align="center"><input type="text" name="q" size="25" maxlength="255" value="<?php echo $_GET['q']; ?>" /></td><td><input type="submit" name="Search" value="Keyword Search" /></td><td><input type="submit" name="Search" value="Google Search" /><input type="hidden" name="search1" value="Search" /></td><td>
<select name="material_sub_type_select"> 
<option value="SELECT">SELECT</option>
<?php
if($_SESSION['m_member_type']=='Teacher')
{
$set_select=array("Class Room Data","Teachers Book","Annual Lesson Plan","Repository","Lecture Series","E-Learning","Expert Database","Text Book","E-Books","Journal","Audio/Video","Reports","Article Database","Newspapers","Case Studies","Dictionaries","Directories","Acronyms","Encyclopedia","Biographies","Database","Google scholar","Maps","Quotations","Thesaurus","Yearbook","Right to Information Act","Copyright","Career Development","Search Engines","Government","International education","Travel","Downloads","Education in India","How it work","Hands-on-activities","Reading mission","Symbols","Unit Converter","Currency","World at Glance","Music","Blog","Facebook","Twitter","Orkut");
$qry2 = "select * from mst_material_sub_type";
$stat_queryforstudentsubtype = 	$dbs->query($qry2);
while ($datasubtype = $stat_queryforstudentsubtype->fetch_assoc()) {

$flagset = 0;
										
				for($i=0;$i<count($set_select);$i++)
						{
										
								if($datasubtype['material_sub_name']==$set_select[$i])
											{
											$flagset = 1;
											}
												
						}
										
						if($flagset == 1)
						{
							$subtype = urlencode($datasubtype['material_sub_name']);
					echo "<option value=$datasubtype[material_sub_id]>$datasubtype[material_sub_name]</option>";
						}
									}
}
if($_SESSION['m_member_type']=='Student')
{
$set_select=array("Text Book","E-Books","Journal","Audio/Video","Reports","Article Database","Newspapers","Case Studies","Dictionaries","Directories","Acronyms","Encyclopedia","Biographies","Database","Google scholar","Maps","Quotations","Thesaurus","Yearbook","Right to Information Act","Copyright","Career Development","Search Engines","Government","International education","Travel","Downloads","Education in India","Entertainment & Media","How it work","Hands-on-activities","Reading mission","Symbols","Unit Converter","Currency","World at Glance","Music","Blog","Facebook","Twitter","Orkut");
$qry2 = "select * from mst_material_sub_type";
$stat_queryforstudentsubtype = 	$dbs->query($qry2);
while ($datasubtype = $stat_queryforstudentsubtype->fetch_assoc()) {
$flagset = 0;
										
				for($i=0;$i<count($set_select);$i++)
						{
										
								if($datasubtype['material_sub_name']==$set_select[$i])
											{
											$flagset = 1;
											}
												
						}
										
						if($flagset == 1)
						{
							$subtype = urlencode($datasubtype['material_sub_name']);
					echo "<option value=$datasubtype[material_sub_id]>$datasubtype[material_sub_name]</option>";
						}
									}

}
if($_SESSION['m_member_type']=='Parent')
{
$set_select=array("Text Book","Newspapers","Assignments","Dictionaries","Directories","Acronyms","Encyclopedia","Biographies","Database","Google scholar","Maps","Quotations","Thesaurus","Yearbook","Right to Information Act","Copyright","Career Development","Search Engines","Government","International education","Travel","Downloads","Education in India","How it work","Hands-on-activities","Reading mission","Symbols","Unit Converter","Currency","World at Glance","Music","Blog","Facebook","Twitter","Orkut");
$qry2 = "select * from mst_material_sub_type";
$stat_queryforstudentsubtype = 	$dbs->query($qry2);
while ($datasubtype = $stat_queryforstudentsubtype->fetch_assoc()) {

$flagset = 0;
										
				for($i=0;$i<count($set_select);$i++)
						{
										
								if($datasubtype['material_sub_name']==$set_select[$i])
											{
											$flagset = 1;
											}
												
						}
										
						if($flagset == 1)
						{
							$subtype = urlencode($datasubtype['material_sub_name']);
					echo "<option value=$datasubtype[material_sub_id]>$datasubtype[material_sub_name]</option>";
						}
									}

}
if(empty($_SESSION['m_member_type']))
{
$set_select=array("Class Room Data","Teachers Book","Annual Lesson Plan","Repository","Lecture Series","E-Learning","Expert Database","Text Book","E-Books","Journal","Audio/Video","Reports","Article Database","Newspapers","Case Studies","Dictionaries","Directories","Acronyms","Encyclopedia","Biographies","Database","Google scholar","Maps","Quotations","Thesaurus","Yearbook","Right to Information Act","Copyright","Career Development","Search Engines","Government","International education","Travel","Downloads","Education in India","How it work","Hands-on-activities","Reading mission","Symbols","Unit Converter","Currency","World at Glance","Music","Blog","Facebook","Twitter","Orkut");
$qry2 = "select * from mst_material_sub_type";
$stat_queryforstudentsubtype = 	$dbs->query($qry2);
while ($datasubtype = $stat_queryforstudentsubtype->fetch_assoc()) {

$flagset = 0;
										
				for($i=0;$i<count($set_select);$i++)
						{
										
								if($datasubtype['material_sub_name']==$set_select[$i])
											{
											$flagset = 1;
											}
												
						}
										
						if($flagset == 1)
						{
							$subtype = urlencode($datasubtype['material_sub_name']);
					echo "<option value=$datasubtype[material_sub_id]>$datasubtype[material_sub_name]</option>";
						}
									}
}
?>

</select></td><td><a href="index.php?advancesearch=set">Advance Search</a></td></tr>
</table>
</div>

</form> </div>
<?php } 
?>


    </div>
   
    <div class="clear">&nbsp;</div>
    <div class="spacer">&nbsp;</div>
    <!--application main menu end-->
  
    <!--application navigation menu/side menu--><table>
 <?php  if (($_REQUEST['p']!='book_request' && $_REQUEST['p']!='member' && $_REQUEST['p']!='help' && $_REQUEST['p']!='rescentview' && $_REQUEST['p']!='myeself' && isset($_REQUEST['subtype'])) || ($_REQUEST['search1']=='Search'  && empty($_GET['Search'])) ||  isset($_REQUEST['advancesearch']))
{  ?>
<table border="1" align="center">
<tr>

<td valign="top">Search Criteria
</td>

<td><?php } ?>
<!-- <div class="grid_9" id="side-menu1"> -->

        <!-- language selection -->
            <!-- comment by iresh on 11/1/2011<div class="block-header"><?php echo gettext('Select Language'); ?></div>
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

      

<?php } }
?> <!-- if condition end for hiding login menu when member is login -->
<!-- <?php echo "<br>"; echo "<br>";echo "<br>";echo "<br>";echo "<br>";?> -->
<?php if($_REQUEST['p']!='member' && $_REQUEST['p']!='book_request' && $_REQUEST['p']!='help' && $_REQUEST['p']!='rescentview' && $_REQUEST['p']!='myeself') {?>
<div style="padding:5px;"></div>
<?php } ?>
<?php if($_REQUEST['p']!='member' && $_REQUEST['p']!='book_request' && $_REQUEST['p']!='help' && $_REQUEST['p']!='rescentview' && $_REQUEST['p']!='myeself') {
//include('template/igos/accordian_menu.php');
//include('template/igos/sidebar_menu.php');
//include('template/igos/fancydropdown.php');
?>


<?php } ?> <!-- if condition end for hiding login menu when member is login -->
 </div>

<?php if (!$_REQUEST['p']){}?>

<!-- added by parth start-->
<div align="left"> 
<?php 
if($_GET['material_sub_type_select']!="SELECT")
		{
		$qry_new_for = "select * from mst_material_sub_type where material_sub_id = '$_GET[material_sub_type_select]'";
			$stat_queryforstudentsubtype_new = $dbs->query($qry_new_for);
			while ($datasubtype_new = $stat_queryforstudentsubtype_new->fetch_assoc()) {		
			$_GET['subtype'] = $datasubtype_new['material_sub_name'];
			}
		}
		else
		{
			$_GET['subtype'] = $_GET['subtype'];
		}
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
 echo "<td><a href=index.php style=text-decoration:none;color:white;>$_SESSION[m_member_type]</a>--></td><td><a href=index.php style=text-decoration:none;color:white;>$header</a>--></td><td><a href=# style=text-decoration:none;color:white;>$_GET[subtype]</a></td></tr></table>";

}


 ?>
<!--started commented by parth on 9/8/2011-->
 <!--</div>-->
<!--ended commented by parth on 9/8/2011-->
<?php
include('lib/frontdisplay.php');
?>
 <?php  if (($_REQUEST['p']!='book_request' && $_REQUEST['p']!='member' && $_REQUEST['p']!='help' && $_REQUEST['p']!='rescentview'  && $_REQUEST['p']!='myeself' && isset($_REQUEST['subtype'])) || ($_REQUEST['search1']=='Search'  && empty($_GET['Search'])) ||  isset($_REQUEST['advancesearch']))
{  ?>
<div class="grid_2_new">
<div align="left" style="margin-left:10px;">
<?php if(empty($_REQUEST['advancesearch'])) {?>
<div class="tab-page" id="tabPage3" align="left">

		<h2 class="tab">Search</h2>
		
		<script type="text/javascript">tp1.addTabPage( document.getElementById( "tabPage1" ) );</script>
                <script type="text/javascript">
                function displaychange()
                    {
                       location.href = "index.php?keywords="+document.simpleSearch.keywords.value+"&search1=Search&material_sub_type_select="+document.simpleSearch.material_sub_type_select.value+"&subtype="+document.simpleSearch.subtype.value+"&subid="+document.simpleSearch.subid.value;
                     } 
                </script>
		<form name="simpleSearch" action="index.php" method="get">
                <table><tr><td><input type="text" name="keywords" value="<?php echo $_GET['keywords']; ?>" style="width:300px;"/></td><td><input type="submit" name="search1" value="<?php echo gettext('Search'); ?>" class="button marginTop"/></td><td>
<select name="material_sub_type_select" onchange="javascript:displaychange();"> 
<option value="SELECT">SELECT</option>
<?php
if($_SESSION['m_member_type']=='Teacher')
{
$set_select=array("Class Room Data","Teachers Book","Annual Lesson Plan","Repository","Lecture Series","E-Learning","Expert Database","Text Book","E-Books","Journal","Audio/Video","Reports","Article Database","Newspapers","Case Studies","Dictionaries","Directories","Acronyms","Encyclopedia","Biographies","Database","Google scholar","Maps","Quotations","Thesaurus","Yearbook","Right to Information Act","Copyright","Career Development","Search Engines","Government","International education","Travel","Downloads","Education in India","How it work","Hands-on-activities","Reading mission","Symbols","Unit Converter","Currency","World at Glance","Music","Blog","Facebook","Twitter","Orkut");
$qry2 = "select * from mst_material_sub_type";
$stat_queryforstudentsubtype = 	$dbs->query($qry2);
while ($datasubtype = $stat_queryforstudentsubtype->fetch_assoc()) {

$flagset = 0;
										
				for($i=0;$i<count($set_select);$i++)
						{
										
								if($datasubtype['material_sub_name']==$set_select[$i])
											{
											$flagset = 1;
											}
												
						}
										
						if($flagset == 1)
						{
							$subtype = urlencode($datasubtype['material_sub_name']);
					echo "<option value=$datasubtype[material_sub_id]>$datasubtype[material_sub_name]</option>";
						}
									}
}
if($_SESSION['m_member_type']=='Student')
{
$set_select=array("Text Book","E-Books","Journal","Audio/Video","Reports","Article Database","Newspapers","Case Studies","Dictionaries","Directories","Acronyms","Encyclopedia","Biographies","Database","Google scholar","Maps","Quotations","Thesaurus","Yearbook","Right to Information Act","Copyright","Career Development","Search Engines","Government","International education","Travel","Downloads","Education in India","Entertainment & Media","How it work","Hands-on-activities","Reading mission","Symbols","Unit Converter","Currency","World at Glance","Music","Blog","Facebook","Twitter","Orkut");
$qry2 = "select * from mst_material_sub_type";
$stat_queryforstudentsubtype = 	$dbs->query($qry2);
while ($datasubtype = $stat_queryforstudentsubtype->fetch_assoc()) {
$flagset = 0;
										
				for($i=0;$i<count($set_select);$i++)
						{
										
								if($datasubtype['material_sub_name']==$set_select[$i])
											{
											$flagset = 1;
											}
												
						}
										
						if($flagset == 1)
						{
							$subtype = urlencode($datasubtype['material_sub_name']);
					echo "<option value=$datasubtype[material_sub_id]>$datasubtype[material_sub_name]</option>";
						}
									}

}
if($_SESSION['m_member_type']=='Parent')
{
$set_select=array("Text Book","Newspapers","Assignments","Dictionaries","Directories","Acronyms","Encyclopedia","Biographies","Database","Google scholar","Maps","Quotations","Thesaurus","Yearbook","Right to Information Act","Copyright","Career Development","Search Engines","Government","International education","Travel","Downloads","Education in India","How it work","Hands-on-activities","Reading mission","Symbols","Unit Converter","Currency","World at Glance","Music","Blog","Facebook","Twitter","Orkut");
$qry2 = "select * from mst_material_sub_type";
$stat_queryforstudentsubtype = 	$dbs->query($qry2);
while ($datasubtype = $stat_queryforstudentsubtype->fetch_assoc()) {

$flagset = 0;
										
				for($i=0;$i<count($set_select);$i++)
						{
										
								if($datasubtype['material_sub_name']==$set_select[$i])
											{
											$flagset = 1;
											}
												
						}
										
						if($flagset == 1)
						{
							$subtype = urlencode($datasubtype['material_sub_name']);
					echo "<option value=$datasubtype[material_sub_id]>$datasubtype[material_sub_name]</option>";
						}
									}

}
if(empty($_SESSION['m_member_type']))
{
$set_select=array("Class Room Data","Teachers Book","Annual Lesson Plan","Repository","Lecture Series","E-Learning","Expert Database","Text Book","E-Books","Journal","Audio/Video","Reports","Article Database","Newspapers","Case Studies","Dictionaries","Directories","Acronyms","Encyclopedia","Biographies","Database","Google scholar","Maps","Quotations","Thesaurus","Yearbook","Right to Information Act","Copyright","Career Development","Search Engines","Government","International education","Travel","Downloads","Education in India","How it work","Hands-on-activities","Reading mission","Symbols","Unit Converter","Currency","World at Glance","Music","Blog","Facebook","Twitter","Orkut");
$qry2 = "select * from mst_material_sub_type";
$stat_queryforstudentsubtype = 	$dbs->query($qry2);
while ($datasubtype = $stat_queryforstudentsubtype->fetch_assoc()) {

$flagset = 0;
										
				for($i=0;$i<count($set_select);$i++)
						{
										
								if($datasubtype['material_sub_name']==$set_select[$i])
											{
											$flagset = 1;
											}
												
						}
										
						if($flagset == 1)
						{
							$subtype = urlencode($datasubtype['material_sub_name']);
					echo "<option value=$datasubtype[material_sub_id]>$datasubtype[material_sub_name]</option>";
						}
									}
}
?>
</td><td><a href="index.php?
advancesearch=set&subtype=<?php echo $_GET['subtype']; ?>&subid=<?php echo $_GET['subid']; ?>">Advance Search</a></td><td>

</select>
</td></tr></table>
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
<?php } if(isset($_REQUEST['advancesearch'])) {?>

	<div class="tab-page" id="tabPage3" align="left">
		<h2 class="tab">Advanced Search</h2>
		
		<script type="text/javascript">tp1.addTabPage( document.getElementById( "tabPage3" ) );</script>
		
        <!-- advanced search -->
            <form name="advSearchForm" id="advSearchForm" action="index.php" method="get">
            <?php echo gettext('Title'); ?> :
            <input type="text" name="title" value="<?php echo $_GET['title']; ?>"/>
            <?php echo gettext('Publisher Name'); ?> :
            <input type="text" name="publish_name" value="<?php echo $_GET['publish_name']; ?>"/>       
	   <?php echo gettext('Author(s)'); ?> :
            <?php //echo $advsearch_author; ?>
             <input type="text" name="author" value="<?php echo $_GET['author']; ?>"/> 
            <?php echo gettext('Subject(s)'); ?> :
            <?php //echo $advsearch_topic; ?> 
             <input type="text" name="subject" value="<?php echo $_GET['subject']; ?>"/>
            <?php echo gettext('ISBN/ISSN'); ?> :
            <input type="text" name="isbn" value="<?php echo $_GET['isbn']; ?>"/>
            <?php echo gettext('Classification'); ?> :
            <input type="text" name="classification" value="<?php echo $_GET['classification']; ?>"/>   
             <?php echo gettext('Publish Year'); ?> :
            <input type="text" name="publish_year" value="<?php echo $_GET['publish_year']; ?>"/>  
            <?php echo gettext('Publisher Name'); ?> :
            <input type="text" name="publish_name" value="<?php echo $_GET['publish_name']; ?>"/>
              <?php echo gettext('Keywords'); ?> :
            <input type="text" name="keywords_tag" value="<?php echo $_GET['keywords_tag']; ?>"/>             
           <!-- commment by iresh on 21-1-2011 <?php echo gettext('GMD'); ?> :-->
	    <?php echo "<br>"; ?>
<!--start commented by Parth 9/7/2011       -->
   <!--  <?php echo gettext('Material Type'); ?> :
            <select name="gmd" />
            <?php echo $gmd_list; ?>
            </select> -->
<!--ended commented by Parth 9/7/2011-->
	    <?php echo gettext('Material Sub Type'); ?> :
            <select name="mst_sub" />
            <?php echo $mst_sub_list; ?>
            </select>

<!--start commented by Parth 9/7/2011           -->
<!-- <?php echo gettext('Collection Type'); ?> :
            <select name="colltype" />
            <?php echo $colltype_list; ?>
            </select> 
            <?php echo gettext('Location'); ?> :
            <select name="location" />
            <?php echo $location_list; ?>
            </select> -->
<!--ended commented by Parth 9/7/2011-->
           <!--Added by iresh on 21-1-2011 --> <br/> 
            <input type="submit" name="search" value="<?php echo gettext('Search'); ?>" />
	<input type="hidden" name="advancesearch" value="set" /> 		
            <!-- <input type="button" value="More Options" onclick="" class="button marginTop" /> -->
            </form>
	  <!-- advanced search end -->
		
	</div>
<?php } ?>
	
	
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

<?php if($_REQUEST['p']!='member' && $_REQUEST['p']!='book_request' && $_REQUEST['p']!='help' && $_REQUEST['p']!='rescentview' && $_REQUEST['p']!='myeself') {?>
<!--    <div class="grid_main_content" id="">-->
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
    width:1200px;
    height:1000px;
    overflow:hidden;
    
}
#container12 iframe {
    width:1200px;
    height:1000px;
    margin-left:-180px;
    margin-top:-110px;	   	   
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
<!--<iframe src="http://www.googlecodesamples.com/books/php/BooksBrowser/index.php?queryType=all&maxResults=6&searchTerm=<?php echo $_GET['q']; ?>" width=1000px height=1000px ></iframe>-->
<iframe src="http://www.google.com/search?tbo=p&tbm=bks&q=<?php echo $_GET['q']; ?>&tbs=,bkv:f&num=10"  width=1200px height=1000px ></iframe>
</div>
		<?php }
		else if(empty($_GET['subtype']) && $_GET['p']!="help" && $_GET['p']!="member" && $_GET['p']!="book_request" && $_GET['p']!="show_detail" && $_GET['p']!="book_request" && $_GET['p']!="myeself" && $_GET['p']!="rescentview" && empty($_GET['Search']) && $_GET['search']!="Search")
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
<!--<iframe src="http://www.googlecodesamples.com/books/php/BooksBrowser/index.php?queryType=all&maxResults=6&searchTerm=<?php echo $_GET['q']; ?>" width=1000px height=1000px ></iframe>-->
<iframe src="http://www.google.com/search?tbo=p&tbm=bks&q=<?php echo $_GET['q']; ?>&tbs=,bkv:f&num=10"  width=1200px height=1000px ></iframe>
</div>
		<?php }
		else if(empty($_GET['subtype']) && $_GET['p']!="help" && $_GET['p']!="member" && $_GET['p']!="book_request" && $_GET['p']!="show_detail" && $_GET['p']!="book_request" && $_GET['p']!="rescentview" && $_GET['p']!="myeself" && empty($_GET['Search']) && $_GET['search']!="Search")
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
<!--<iframe src="http://www.googlecodesamples.com/books/php/BooksBrowser/index.php?queryType=all&maxResults=6&searchTerm=<?php echo $_GET['q']; ?>" width=1000px height=1000px ></iframe>-->
<iframe src="http://www.google.com/search?tbo=p&tbm=bks&q=<?php echo $_GET['q']; ?>&tbs=,bkv:f&num=10"  width=1200px height=1000px ></iframe>
</div>
		<?php }
		else if(empty($_GET['subtype']) && $_GET['p']!="help" && $_GET['p']!="member" && $_GET['p']!="show_detail" && $_GET['p']!="book_request" && $_GET['p']!="rescentview" && $_GET['p']!="myeself" && empty($_GET['Search']) && $_GET['search']!="Search")
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
<!--<iframe src="http://www.googlecodesamples.com/books/php/BooksBrowser/index.php?queryType=all&maxResults=6&searchTerm=<?php echo $_GET['q']; ?>" width=1000px height=1000px ></iframe>-->
<iframe src="http://www.google.com/search?tbo=p&tbm=bks&q=<?php echo $_GET['q']; ?>&tbs=,bkv:f&num=10"  width=1200px height=1000px ></iframe>

</div>
		<?php }
		
		else if(empty($_GET['subtype']) && $_GET['p']!="help" && $_GET['p']!="member" && $_GET['p']!="book_request" && $_GET['p']!="show_detail" && $_GET['p']!="book_request" && $_GET['p']!="rescentview" && $_GET['p']!="myeself" && empty($_GET['Search']) && $_GET['search']!="Search")
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
<?php  if (($_REQUEST['p']!='book_request' && $_REQUEST['p']!='member' && $_REQUEST['p']!='help' && $_REQUEST['p']!='rescentview'   && $_REQUEST['p']!='myeself' && isset($_REQUEST['subtype'])) || ($_REQUEST['search1']=='Search'  && empty($_GET['Search'])) ||  isset($_REQUEST['advancesearch']))
{  ?></td> 
<td valign="top">Refine</td>
</tr></table><?php } ?>
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
