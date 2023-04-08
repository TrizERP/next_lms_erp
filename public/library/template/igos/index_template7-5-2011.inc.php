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
</head>
<body>
<div class="container_12">
    <!--header-->
    <div class="grid_12" id="header">
    <h1 id="app-title"><a href="index.php"><?php echo $sysconf['library_name']; ?></a><div><?php echo $sysconf['library_subname']; ?></div></h1>
    </div>
    <div class="clear">&nbsp;</div>
    <!--header end-->

    <!--application main menu-->
    <div class="grid_12 tabs" id="main-menu">
        <ul id="primary-links">
            <li><a class="menu" href="index.php"><span><?php echo __('Home'); ?></a></span></li>
          <!--comment made by iresh on 11/1/2011    <li><a class="menu" href="index.php?p=libinfo"><span><?php echo __('Library Information'); ?></span></a></li>-->
			<!--comment made by iresh on 11/1/2011 <li><a class="menu" href="index.php?p=member"><span><?php echo __('Member Area'); ?></span></a></li>-->
		<!--added by iresh on 11/1/2011 --><!--comment by iresh on 22-1-2011	<li><a class="menu" href="index.php?p=member"><span><?php echo __('Member Login'); ?></span></a></li>-->
          <!--comment made by iresh on 11/1/2011    <li><a class="menu" href="index.php?p=peta"><span>Show map</span></a></li>-->
            <li><a class="menu" href="index.php?p=help"><span><?php echo __('Help on Search'); ?></span></a></li>
	   <?php if (utility::isMemberLogin()) {?><!-- added by iresh on 7-4-2011 -->
           <li><a class="menu" href="index.php?p=member"><span><?php echo __('My Profile'); ?></span></a></li>
           <?php }?>
	   <?php if (utility::isMemberLogin()) {?><!-- added by iresh on 7-4-2011 -->
           <li><a class="menu" href="index.php?p=book_request"><span><?php echo __('View Request'); ?></span></a></li>
           <?php }?>
 <!--comment made by iresh on 11/1/2011    <li><a class="menu" href="http://www.igos.web.id"><span>IGOS</span></a></li>
            <li><a class="menu" href="http://senayan.diknas.go.id"><span>SENAYAN</span></a></li>-->
           <!--comment by iresh on 22-1-2011 <li><a class="menu" href="index.php?p=login"><span><?php echo __('Librarian LOGIN'); ?></span></a></li>-->
        </ul>
    </div>
    <div class="clear">&nbsp;</div>
    <div class="spacer">&nbsp;</div>
    <!--application main menu end-->
  
    <!--application navigation menu/side menu-->
    <div class="grid_2" id="side-menu">
        <!-- language selection -->
            <!-- comment by iresh on 11/1/2011<div class="block-header"><?php echo __('Select Language'); ?></div>
            <form name="langSelect" action="index.php" method="get">
            <select name="select_lang" onchange="document.langSelect.submit();">
            <?php echo $language_select; ?>
            </select>
            </form>-->
        <!-- language selection end -->
         <!--common login for admin and member added by iresh on 22-1-2011 -->
         <div class="block-header"><?php echo __('Login'); ?></div>
            <form name="login" action="index.php?p=member" method="post">
            <div class="fieldLabel"><?php echo __('Id/Username'); ?>:</div>
            <input type="text" name="uname" value="<?php echo 'Enter Username'.$_REQUEST['username']; ?>" onclick="this.value=''"/>
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
                 
 	<!--end login form  by iresh--> 
        <!-- simple search -->
            <!-- commment by iresh on 21-1-2011 <div class="block-header"><?php echo __('Simple Search'); ?></div>-->
	    <div class="block-header"><?php echo __('Search'); ?></div>
            <form name="simpleSearch" action="index.php" method="get">
            <input type="text" name="keywords" />
            <input type="submit" name="search" value="<?php echo __('Search'); ?>" class="button marginTop"/>
            </form>
        <!-- simple search end -->


<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">

<head>

<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.4.2/jquery.min.js"></script>

<script type="text/javascript" src="js/ddaccordion.js">

/***********************************************
* Accordion Content script- (c) Dynamic Drive DHTML code library (www.dynamicdrive.com)
* Visit http://www.dynamicDrive.com for hundreds of DHTML scripts
* This notice must stay intact for legal use
***********************************************/

</script>


<script type="text/javascript">


ddaccordion.init({
	headerclass: "expandable", //Shared CSS class name of headers group that are expandable
	contentclass: "categoryitems", //Shared CSS class name of contents group
	revealtype: "click", //Reveal content when user clicks or onmouseover the header? Valid value: "click", "clickgo", or "mouseover"
	mouseoverdelay: 200, //if revealtype="mouseover", set delay in milliseconds before header expands onMouseover
	collapseprev: true, //Collapse previous content (so only one open at any time)? true/false 
	defaultexpanded: [0], //index of content(s) open by default [index1, index2, etc]. [] denotes no content
	onemustopen: false, //Specify whether at least one header should be open always (so never all headers closed)
	animatedefault: false, //Should contents open by default be animated into view?
	persiststate: true, //persist state of opened contents within browser session?
	toggleclass: ["", "openheader"], //Two CSS classes to be applied to the header when it's collapsed and expanded, respectively ["class1", "class2"]
	togglehtml: ["prefix", "", ""], //Additional HTML added to the header when it's collapsed and expanded, respectively  ["position", "html1", "html2"] (see docs)
	animatespeed: "fast", //speed of animation: integer in milliseconds (ie: 200), or keywords "fast", "normal", or "slow"
	oninit:function(headers, expandedindices){ //custom code to run when headers have initalized
		//do nothing
	},
	onopenclose:function(header, index, state, isuseractivated){ //custom code to run whenever a header is opened or closed
		//do nothing
	}
})


</script>

<style type="text/css">

.arrowlistmenu{
width: 180px; /*width of accordion menu*/
}

.arrowlistmenu .menuheader{ /*CSS class for menu headers in general (expanding or not!)*/
font: bold 13px Arial;
color: white;
background: black url(template/igos/media/titlebar.png) repeat-x center left;
margin-bottom: 10px; /*bottom spacing between header and rest of content*/
text-transform: capitalize;
padding: 4px 0 4px 10px; /*header text is indented 10px*/
cursor: hand;
cursor: pointer;
}

.arrowlistmenu .openheader{ /*CSS class to apply to expandable header when it's expanded*/
background-image: url(template/igos/media/titlebar-active.png);
}

.arrowlistmenu ul{ /*CSS for UL of each sub menu*/
list-style-type: none;
margin: 0;
padding: 0;
margin-bottom: 8px; /*bottom spacing between each UL and rest of content*/
}

.arrowlistmenu ul li{
padding-bottom: 2px; /*bottom spacing between menu items*/
}

.arrowlistmenu ul li a{
color: #A70303;
background: url(template/igos/media/arrowbullet.png) no-repeat center left; /*custom bullet list image*/
display: block;
padding: 2px 0;
padding-left: 19px; /*link text is indented 19px*/
text-decoration: none;
font-weight: bold;
border-bottom: 1px solid #dadada;
font-size: 90%;
}

.arrowlistmenu ul li a:visited{
color: #A70303;
}

.arrowlistmenu ul li a:hover{ /*hover state CSS*/
color: #A70303;
background-color: #F3F3F3;
}

</style>

</head>

<body>

<div class="arrowlistmenu">

<h3 class="menuheader expandable">Categories</h3>
<ul class="categoryitems">
<?php
	$subject='select topic from mst_topic';
	$subject=$dbs->query($subject);
	$s='';
	while($row=$subject->fetch_assoc())
	{
		
		
		echo "<li><a href=index.php?subject_search=". $row['topic'] .">".$row['topic']."</a></li>";
		

	}
	
	 ?>

</ul>
<h3 class="menuheader expandable">Physical Resources</h3>
<ul class="categoryitems">
 <?php
		
	 $material='select gmd_name from mst_gmd where gmd_code="Physical Resources"';
         $material=$dbs->query($material);
	 $m='';
	 while($row=$material->fetch_assoc())
	 {

		
		echo "<li><a href=index.php?subject_search=". $row['gmd_name'] .">".$row['gmd_name']."</a></li>";
		
         }
	 ?>
</ul>

<h3 class="menuheader expandable">Virtual Resources</h3>
<ul class="categoryitems">
 <?php
		
	 $material='select gmd_name from mst_gmd where gmd_code="Virtual Resources"';
         $material=$dbs->query($material);
	 $m='';
	 while($row=$material->fetch_assoc())
	 {

		
		echo "<li><a href=index.php?gmd_search=". $row['gmd_name'] .">".$row['gmd_name']."</a></li>";
		
         }
	 ?>
</ul>


<!-- <h3 class="menuheader expandable">Reference Information</h3>
<ul class="categoryitems">
  <?php
		
	 $material='select gmd_name from mst_gmd where gmd_code="Reference Information"';
         $material=$dbs->query($material);
	 $m='';
	 while($row=$material->fetch_assoc())
	 {

		
		echo "<li><a href=index.php?subject_search=". $row['gmd_name'] .">".$row['gmd_name']."</a></li>";
		
         }
	 ?>
</ul>
-->

</div>
</body>
</html>

<!----------------------------------------------------------------------------------------------------------------------------!>

        <!-- advanced search -->
            <div class="block-header"><?php echo __('Advanced Search'); ?></div>
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
            <?php echo __('Material Type'); ?> :
            <select name="gmd" />
            <?php echo $gmd_list; ?>
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
       
        <!-- license -->
        <!--comment made by iresh on 11/1/2011      <div class="block-header">License</div>
            <p>
            This Software is Released Under <a href="http://www.gnu.org/copyleft/gpl.html" title="GNU GPL License" target="_blank">GNU GPL License</a>
            Version 3.
            </p>-->
        <!-- license end -->
		
        <!-- Awards -->
          <!--comment made by iresh on 11/1/2011    <div class="block-header">Awards</div>
            <p>
            The Winner in the Category of OSS</br>
			<img src='template/igos/media/logo-inaicta.png' />
            </p>-->
        <!-- Awards end -->
    </div>

  <div class="grid_2" id="t-menu">
  <form name="advSearchForm" id="advSearchForm" action="index.php" method="post">
	<?php  $alphabet = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z');
		foreach ($alphabet as $letter) {
		echo "<a href=\"?letter=" . $letter . "\"style='font-weight: bold;color: #666;  margin-right:10px;'>" . $letter . "</a>&nbsp;¦&nbsp;";
		}
		echo "<a href=\"?\"style='font-weight: bold;color: #666;'>Show-All</a>";
	?>
   </form>
  </div>


<?php echo "<br>";echo "<br>";?>

<html>
<head>
<script type="text/javascript">
function showResult(str)
{
if (str.length==0)
  {
  document.getElementById("livesearch").innerHTML="";
  document.getElementById("livesearch").style.border="0px";
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
	
    document.getElementById("livesearch").innerHTML=xmlhttp.responseText;
    document.getElementById("livesearch").style.border="0px";
    }
  }
xmlhttp.open("GET","lib/livesearch.inc.php?q="+str,true);
xmlhttp.send();
}
</script>
</head>
<body>

<form>
<table>
<tr><td>
<!-- <input type="text" size="30"  onkeyup="showResult(this.value)"  style="float:right; margin-left:-800px; width:600px;" value="Enter Keyword Here" onclick="this.value=''"/> -->
 <input type="text" size="30"  onkeyup="showResult(this.value)"  style="float:left; margin-left:10px; width:700px;" value="Enter Keyword Here" onclick="this.value=''"/>
</td></tr>
<tr><td>
<!-- <div id="livesearch" style="float:left; margin-right:110px; width:500px;" ></div>-->
<div id="livesearch" style="float:left; margin-left:10px; width:700px;" ></div>
</td></tr></table>
</form>

</body>
</html>
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
    $stat_data_id.= 'http://'.$current_path.'/library/index.php?p=show_detail&id='.$data['2'].',';
}
$stat_data=explode(",",$stat_data);

$stat_data_id=explode(",",$stat_data_id);



?>
<?php if(!$_REQUEST['p'])
{?>
<div class="grid_2" id="tt-menu">


<script type="text/javascript">

/***********************************************
* Conveyor belt slideshow script- © Dynamic Drive DHTML code library (www.dynamicdrive.com)
* This notice MUST stay intact for legal use
* Visit Dynamic Drive at http://www.dynamicdrive.com/ for full source code
***********************************************/


//Specify the slider's width (in pixels)
var sliderwidth="824px"
//Specify the slider's height
var sliderheight="200px"
//Specify the slider's slide speed (larger is faster 1-10)
var slidespeed=2
//configure background color:
slidebgcolor="#EAEAEA"

//Specify the slider's images
var leftrightslide=new Array(); 
var finalslide=''
leftrightslide[0]='<a href="<?php echo $stat_data_id[0]?>"><img src=" <?php echo $stat_data[0]?>" border=1 width="150px" height="200px"  align=top title="Click To View More Detail" ></a>'
leftrightslide[1]='<a href="<?php echo $stat_data_id[1]?>"><img src="<?php echo $stat_data[1] ?>" border=1 width="150px" height="200px"  align=top title="Click To View More Detail"></a>'
leftrightslide[2]='<a href="<?php echo $stat_data_id[2]?>"><img src="<?php echo $stat_data[2] ?>" border=1 width="150px" height="200px" align=top title="Click To View More Detail"></a>'
leftrightslide[3]='<a href="<?php echo $stat_data_id[3]?>"><img src="<?php echo $stat_data[3] ?>" border=1 width="150px" height="200px" align=top title="Click To View More Detail"></a>'
leftrightslide[4]='<a href="<?php echo $stat_data_id[4]?>"><img src="<?php echo $stat_data[4] ?>" border=1 width="150px" height="200px" align=top title="Click To View More Detail"></a>'
leftrightslide[5]='<a href="<?php echo $stat_data_id[5]?>"><img src="<?php echo $stat_data[5] ?>" border=1 width="150px" height="200px" align=top title="Click To View More Detail"></a>'
leftrightslide[6]='<a href="<?php echo $stat_data_id[6]?>"><img src="<?php echo $stat_data[6] ?>" border=1 width="150px" height="200px" align=top title="Click To View More Detail"></a>'
leftrightslide[7]='<a href="<?php echo $stat_data_id[7]?>"><img src="<?php echo $stat_data[7] ?>" border=1 width="150px" height="200px" align=top title="Click To View More Detail"></a>'
leftrightslide[8]='<a href="<?php echo $stat_data_id[8]?>"><img src="<?php echo $stat_data[8] ?>" border=1 width="150px" height="200px" align=top title="Click To View More Detail"></a>'
leftrightslide[9]='<a href="<?php echo $stat_data_id[9]?>"><img src="<?php echo $stat_data[9] ?>" border=1 width="150px" height="200px" align=top title="Click To View More Detail"></a>'
//Specify gap between each image (use HTML):
var imagegap=" "

//Specify pixels gap between each slideshow rotation (use integer):
var slideshowgap=10


////NO NEED TO EDIT BELOW THIS LINE////////////

var copyspeed=slidespeed
leftrightslide='<nobr>'+leftrightslide.join(imagegap)+'</nobr>'
var iedom=document.all||document.getElementById
if (iedom)
document.write('<span id="temp" style="visibility:hidden;position:absolute;top:100px;left:-9000px">'+leftrightslide+'</span>')
var actualwidth=''
var cross_slide, ns_slide

function fillup(){
if (iedom){
cross_slide=document.getElementById? document.getElementById("test2") : document.all.test2
cross_slide2=document.getElementById? document.getElementById("test3") : document.all.test3
cross_slide.innerHTML=cross_slide2.innerHTML=leftrightslide
actualwidth=document.all? cross_slide.offsetWidth : document.getElementById("temp").offsetWidth
cross_slide2.style.left=actualwidth+slideshowgap+"px"
}
else if (document.layers){
ns_slide=document.ns_slidemenu.document.ns_slidemenu2
ns_slide2=document.ns_slidemenu.document.ns_slidemenu3
ns_slide.document.write(leftrightslide)
ns_slide.document.close()
actualwidth=ns_slide.document.width
ns_slide2.left=actualwidth+slideshowgap
ns_slide2.document.write(leftrightslide)
ns_slide2.document.close()
}
lefttime=setInterval("slideleft()",30)
}
window.onload=fillup

function slideleft(){
if (iedom){
if (parseInt(cross_slide.style.left)>(actualwidth*(-1)+8))
cross_slide.style.left=parseInt(cross_slide.style.left)-copyspeed+"px"
else
cross_slide.style.left=parseInt(cross_slide2.style.left)+actualwidth+slideshowgap+"px"

if (parseInt(cross_slide2.style.left)>(actualwidth*(-1)+8))
cross_slide2.style.left=parseInt(cross_slide2.style.left)-copyspeed+"px"
else
cross_slide2.style.left=parseInt(cross_slide.style.left)+actualwidth+slideshowgap+"px"

}
else if (document.layers){
if (ns_slide.left>(actualwidth*(-1)+8))
ns_slide.left-=copyspeed
else
ns_slide.left=ns_slide2.left+actualwidth+slideshowgap

if (ns_slide2.left>(actualwidth*(-1)+8))
ns_slide2.left-=copyspeed
else
ns_slide2.left=ns_slide.left+actualwidth+slideshowgap
}
}


if (iedom||document.layers){
with (document){
document.write('<table border="1" cellspacing="0" cellpadding="0" align="top"><td>')
if (iedom){
write('<div style="position:relative;width:'+sliderwidth+';height:'+sliderheight+';overflow:hidden">')
write('<div style="position:absolute;width:'+sliderwidth+';height:'+sliderheight+';background-color:'+slidebgcolor+'" onMouseover="copyspeed=0" onMouseout="copyspeed=slidespeed">')
write('<div id="test2" style="position:absolute;left:0px;top:0px"></div>')
write('<div id="test3" style="position:absolute;left:-1000px;top:0px"></div>')
write('</div></div>')
}
else if (document.layers){
write('<ilayer width='+sliderwidth+' height='+sliderheight+' name="ns_slidemenu" bgColor='+slidebgcolor+'>')
write('<layer name="ns_slidemenu2" left=0 top=0 onMouseover="copyspeed=0" onMouseout="copyspeed=slidespeed"></layer>')
write('<layer name="ns_slidemenu3" left=0 top=0 onMouseover="copyspeed=0" onMouseout="copyspeed=slidespeed"></layer>')
write('</ilayer>')
}
document.write('</td></table>')
}
}
</script>

</div>
<?php } echo "<br>"; ?>

    <!--application navigation menu/side menu-->

    <!--application main content -->
    <div class="grid_9"  id="main-content">
    <?php echo $header_info; ?>
   <!-- comment by iresh on 22-1-2011 <div id="info-box"><?php echo $info; ?></div>-->
    <!--added by iresh on 22-1-2011<?php echo $info; ?>-->
    <?php echo $main_content; ?>
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


</body>
</html>
