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
            <input type="text" name="uname" value="<?php echo $_REQUEST['username']; ?>" />
	    <div class="fieldLabel marginTop"><?php echo __('Password'); ?>:</div>
	    <input type="password" name="pass" value="<?php echo $_REQUEST['password']; ?>" />
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
            <input type="submit" name="search" value="<?php echo __('Search'); ?>" class="button marginTop" />
            </form>
        <!-- simple search end -->
	<!--search by subject -->

	<div class="block-header"><?php echo __('Search By Subject'); ?></div>
  	<form name="advSearchForm" id="advSearchForm" action="index.php" method="post">
	<table border='0' width=100% cellspacing=0 cellpadding=5>
	<?php
	$subject='select topic from mst_topic';
	$subject=$dbs->query($subject);
	$s='';
	while($row=$subject->fetch_assoc())
	{
		
		echo "<tr>";
		echo "<td><a href=index.php?subject_search=". $row['topic'] .">".$row['topic']."</a></td>";
		echo "</tr>";

	}
	
	 ?>
	</table>
	</form>
	
	
	<!--search by subject end -->
        <!-- advanced search -->
            <div class="block-header"><?php echo __('Advanced Search'); ?></div>
            <form name="advSearchForm" id="advSearchForm" action="index.php" method="get">
            <?php echo __('Title'); ?> :
            <input type="text" name="title" />
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
		echo "<a href=\"?letter=" . $letter . "\">" . $letter . "</a>&nbsp;¦&nbsp;";
		}
		
		echo "<table><tr><td align='center'><a href=\"?\">Show All</a></td></tr></table>";
	?>
   </form>
  </div>
<script language="javascript" type="text/javascript">
<!--
   var i=0;
   var finished=false;
   var paused=false;
   var running=false;
   function loadPics()
   {
      pic0=new Image();
      pic0.src="images/docs/cathedral_bazaar.jpg";
      pic1=new Image();
      pic1.src="images/docs/corruption_development.jpg";
      pic2=new Image();
      pic2.src="images/docs/mysql_def_guide.jpg.jpg";
      pic3=new Image();
      pic3.src="images/docs/lords_of_poverty.jpg";
      pict=new Array();
      pict[0]=pic0.src;
      pict[1]=pic1.src;
      pict[2]=pic2.src;
      pict[3]=pic3.src;
   }
   function next()
   {alert("hi");
      finished=false;
      if(i<pict.length-1)
      {
         i++;
         document.getElementById('picture').src=pict[i];
         document.getElementById('number').innerHTML="Picture "+1*(i+1);
      }
      else
      {
         i=0;
         document.getElementById('picture').src=pict[i];
         document.getElementById('number').innerHTML="Picture "+1*(i+1);
      }
   }
   function prev()
   {
	
      finished=false;
      if (i>0)
      {
         i--;
         document.getElementById('picture').src=pict[i];
         document.getElementById('number').innerHTML="Picture "+1*(i+1);
      }
      else
      {
         i=pict.length-1;
         document.getElementById('picture').src=pict[i];
         document.getElementById('number').innerHTML="Picture "+1*(i+1);
      }
   }

   function startSlide()
   {
      running=true;
      if(navigator.appVersion.indexOf("MSIE") == -1)
      {
         interval = setInterval(FFSlideshow,5000);
      }
      else
      {
         interval = setInterval(slideshow,5000);
      }
      document.getElementById("slideshow").disabled=true;
      document.getElementById('number').innerHTML="Picture "+1*(i+1);
   }
   function slideshow()
   {
      if (i<pict.length-1)
      {
         document.getElementById('pictureContainer').filters[0].Apply();
         document.getElementById("slideshow").disabled=true;
         i++;
         document.getElementById('picture').src=pict[i];
         document.getElementById('number').innerHTML="Picture "+1*(i+1);
         document.getElementById('pictureContainer').filters[0].Play();
      }
      else if (i==pict.length-1 && finished==false)
      {
         document.getElementById("slideshow").disabled=false;
         document.getElementById('number').innerHTML="End of slideshow";
         finished=true;
         running=false;
         clearInterval(interval);
      }
      else
      {
         document.getElementById('pictureContainer').filters[0].Apply();
         i=0;
         finished=false;
         document.getElementById('picture').src=pict[i];
         document.getElementById('number').innerHTML="Picture "+1*(i+1);
         document.getElementById('pictureContainer').filters[0].Play();
      }
   }
   function FFSlideshow()
   {
      if (i<pict.length-1)
      {
         document.getElementById("slideshow").disabled=true;
         i++
         document.getElementById('picture').src=pict[i];
         document.getElementById('number').innerHTML="Picture "+1*(i+1);
      }
      else if (i==pict.length-1 && finished==false)
      {
         document.getElementById("slideshow").disabled=false;
         document.getElementById('number').innerHTML="End of slideshow";
         finished=true;
         running=false
         clearInterval(interval);
      }
      else
      {
         i=0;
         finished=false;
         document.getElementById('picture').src=pict[i];
         document.getElementById('number').innerHTML="Picture "+1*(i+1);
      }
   }
   function pause()
   {
      if(running==true)
      {
         if(paused==false)
         {
            paused=true;
            document.getElementById("pause").value="resume";
            clearInterval(interval);
            document.getElementById('number').innerHTML="Paused";
         }
         else
         {
            startSlide()
            paused=false;
            document.getElementById("pause").value="pause";
         }
      }
   }
//-->
</script>


<html>
<body onload="loadPics()">
<center>
<div class="grid_2" id="t-menu">
<div id="pictureContainer" style="width:560px; filter:progid:DXImageTransform.Microsoft.Fade(duration=1.0,overlap=1.0)">
<img id="picture" name="picture" src="images/docs/cathedral_bazaar.jpg" /></div><br />
<input id="prev" type="button" value="prev" onclick="prev()" />
<input id="next" type="button" value="next" onclick="next(alert(hi);)" />
<input id="slideshow" type="button" value="slideshow" onclick="startSlide()" />
<input id="pause" type="button" value="pause" onclick="pause()" /><br />
<div id="number">Picture 1</div>
</div>
</center>
</body>
</html> 



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
