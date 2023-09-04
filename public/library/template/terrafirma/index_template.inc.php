<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<!--
	terrafirma1.0 by nodethirtythree design
	http://www.nodethirtythree.com
	Modified for SLiMS template by Hendro Wicaksono (hendrowicaksono@yahoo.com)
-->
<html>
<head>
<meta http-equiv="content-type" content="text/html; charset=iso-8859-1" />
<title><?php echo $page_title; ?></title>
<link href="template/core.style.css" rel="stylesheet" type="text/css" />
<meta name="keywords" content="" />
<meta name="description" content="" />
<!--<link rel="stylesheet" type="text/css" href="style.css" />-->
<link href="<?php echo $template_css; ?>" rel="stylesheet" type="text/css" />
</head>
<body>

<div id="outer">

	<div id="upbg"></div>

	<div id="inner">

		<div id="header">
			<!-- <h1><span>terra</span>firma<sup>1.0</sup></h1> -->
			<h1><?php echo $page_title; ?></h1>
			<!-- <h2>by nodethirtythree & Hendro Wicaksono</h2> -->
			<h2><?php echo $library_subname; ?></h2>
		</div>
	
		<div id="splash"></div>
	
		<div id="menu">
			<ul>
          <li class="first"><a href="index.php"><?php echo gettext('Home'); ?></a></li>
         <!-- comment by iresh on 11/1/2011 <li><a href="index.php?p=libinfo"><?php echo gettext('Library Information'); ?></a></li>-->
          <li><a href="index.php?p=help"><?php echo gettext('Help on Search'); ?></a></li>
         <!-- comment by iresh on 11/1/2011  <li><a href="index.php?p=member"><?php echo gettext('Member Area'); ?></a></li>-->
	 <!--Added by iresh on 11/1/2011 --><li><a href="index.php?p=member"><?php echo gettext('Member Login'); ?></a></li>
          <li><a href="index.php?p=login"><?php echo gettext('Librarian LOGIN'); ?></a></li>

			<!--	<li class="first"><a href="#">Home</a></li>
				<li><a href="#">Archives</a></li>
				<li><a href="#">Links</a></li>
				<li><a href="#">Resources</a></li>
				<li><a href="#">Contact</a></li>
                        -->
			</ul>

		<!--comment by iresh on 11/1/2011<div id="date">August 1, 2006</div>-->
		</div>
	

		<div id="primarycontent">
		
			<!-- primary content start -->


<!--		
			<div class="post">
				<div class="header">
					<h3>TerraFirma<sup>1.0</sup> by NodeThirtyThree</h3>
					<div class="date">August 1, 2006</div>
				</div>
				<div class="content">
					<img src="template/terrafirma/images/pic1.jpg" class="picA floatleft" alt="" />
					<p><strong>TerraFirma</strong><sup>1.0</sup> is a free, lightweight, tableless, W3C-compliant website design by <a href="http://www.nodethirtythree.com/">NodeThirtyThree Design</a>. You're free to dissect, manipulate and use it to your heart's content. We only ask that you link back to our site in some way. If you find this design useful, feel free to let us know :)</p>
					<p>This template has been ported for SLiMS (Senayan Library Management System) by <a href="mailto:hendrowicaksono@yahoo.com">Hendro Wicaksono</a>. Feel free to use and modify it, as long as you dont remove copyright statement.</p>
				</div>			

				<div class="footer">
					<ul>
						<li class="printerfriendly"><a href="#">Printer Friendly</a></li>
						<li class="comments"><a href="#">Comments (18)</a></li>
						<li class="readmore"><a href="#">Read more</a></li>
					</ul>
				</div>

			</div>
-->

<?php echo $header_info; ?>

<!-- simple search -->
    <form name="simpleSearch" id="simpleSearch" action="index.php" method="get">
    <strong>Quick Search :</strong> <input type="text" name="keywords" id="simpleKeywords" style="width: 65%;" />
    <input type="submit" name="search" value="<?php echo gettext('Search'); ?>" class="button marginTop" />
    <script type="text/javascript">$('simpleKeywords').focus();</script>
    </form><br />
<!-- simple search end -->

<div id="infoBox"><?php echo $info; ?></div><br />

<!--
			<div class="post">
				<div class="header">
					<h3>Vivamus tortor sed aenean</h3>
					<div class="date">July 31, 2006</div>
				</div>
				<div class="content">
					<p>Volutpat at varius sed sollicitudin et, arcu. Vivamus viverra. Nullam turpis. Vestibulum sed etiam. Lorem ipsum sit amet dolore. Nulla facilisi. Sed tortor. Aenean felis. Quisque eros. Cras lobortis commodo metus. Vestibulum vel purus. In eget odio in sapien adipiscing blandit. Quisque augue tortor, facilisis sit amet, aliquam, suscipit vitae, cursus sed, arcu lorem ipsum dolor sit amet.</p>
					<p>Fermentum at, varius pretium, elit. Mauris egestas scelerisque nunc. Mauris non ligula quis wisi laoreet malesuada. In commodo. Maecenas lobortis cursus dolor.</p>
				</div>			
				<div class="footer">
					<ul>
						<li class="printerfriendly"><a href="#">Printer Friendly</a></li>
						<li class="comments"><a href="#">Comments (18)</a></li>
						<li class="readmore"><a href="#">Read more</a></li>
					</ul>
				</div>
			</div>

			<div class="post">
				<div class="header">
					<h3>Vivamus tortor sed aenean</h3>
					<div class="date">July 31, 2006</div>
				</div>
				<div class="content">
					<p>Volutpat at varius sed sollicitudin et, arcu. Vivamus viverra. Nullam turpis. Vestibulum sed etiam. Lorem ipsum sit amet dolore. Nulla facilisi. Sed tortor. Aenean felis. Quisque eros. Cras lobortis commodo metus. Vestibulum vel purus. In eget odio in sapien adipiscing blandit. Quisque augue tortor, facilisis sit amet, aliquam, suscipit vitae, cursus sed, arcu lorem ipsum dolor sit amet.</p>
					<p>Fermentum at, varius pretium, elit. Mauris egestas scelerisque nunc. Mauris non ligula quis wisi laoreet malesuada. In commodo. Maecenas lobortis cursus dolor.</p>
				</div>			
				<div class="footer">
					<ul>
						<li class="printerfriendly"><a href="#">Printer Friendly</a></li>
						<li class="comments"><a href="#">Comments (18)</a></li>
						<li class="readmore"><a href="#">Read more</a></li>
					</ul>
				</div>
			</div>
-->

<?php echo $main_content; ?>


			<!-- primary content end -->
	
		</div>
		
		<div id="secondarycontent">

			<!-- secondary content start -->
		
			<!-- comment by iresh on 11/1/2011<h3>About SDC</h3>-->
			<div class="content">
				<!--comment by iresh on 11/1/2011 <img src="template/terrafirma/images/pic2.jpg" class="picB" alt="" />-->
				<!-- comment by iresh on 11/1/2011<p><strong>Nullam turpis</strong> vestibulum et sed dolore. Nulla facilisi. Sed tortor. lobortis commodo. <a href="#">More ...</a></p>-->
			</div>

                        <!-- language selection -->
                       <!-- comment by iresh on 11/1/2011 <h3><?php echo gettext('Select Language'); ?></h3>
                        <form name="langSelect" action="index.php" method="get">
                        <select name="select_lang" style="width: 99%;" onchange="document.langSelect.submit();">
                        <?php echo $language_select; ?>
                        </select>
                        </form>-->
                        <br />
                        <!-- language selection end -->

                        <!-- advanced search -->
                        <h3><?php echo gettext('Advanced Search'); ?></h3>
                        <form name="advSearchForm" id="advSearchForm" action="index.php" method="get">
                        <?php echo gettext('Title'); ?> :
                        <input type="text" name="title" class="ajaxInputField" /><br />
                        <?php echo gettext('Author(s)'); ?> :
                        <?php echo $advsearch_author; ?><br />
                        <?php echo gettext('Subject(s)'); ?> :
                        <?php echo $advsearch_topic; ?><br />
                        <?php echo gettext('ISBN/ISSN'); ?> :
                        <input type="text" name="isbn" class="ajaxInputField" /><br />
                        <?php echo gettext('GMD'); ?> :
                        <select name="gmd" class="ajaxInputField" />
                        <?php echo $gmd_list; ?>
                        </select>
                        <?php echo gettext('Collection Type'); ?> :
                        <select name="colltype" class="ajaxInputField" />
                        <?php echo $colltype_list; ?>
                        </select>
                        <?php echo gettext('Location'); ?> :
                        <select name="location" class="ajaxInputField" />
                        <?php echo $location_list; ?>
                        </select>
                        <br />
                        <input type="submit" name="search" value="<?php echo gettext('Search'); ?>" class="button marginTop" />
                        <!-- <input type="button" value="More Options" onclick="" class="button marginTop" /> -->
                        </form>
                        <br />
                        <!-- advanced search end -->

    <!-- award -->
   <!-- comment by iresh on 11/1/2011     <h3>Award</h3>
        <p align="center">
        The Winner in the Category of OSS
        <img src="template/default/media/logo-inaicta.png"
            alt="Indonesia ICT Award 2009" border="0" />
        </p>-->
    <!-- award -->

    <!-- license -->
      <!-- comment by iresh on 11/1/2011    <h3>License</h3>
        <p>
        This Software is Released Under <a href="http://www.gnu.org/copyleft/gpl.html" title="GNU GPL License" target="_blank">GNU GPL License</a>
        Version 3.
        </p>-->
    <!-- license end -->




			<!-- secondary content end -->

		</div>
	
		<div id="footer">
		
			<!--comment by iresh on 11/1/2011 &copy; My Website. All rights reserved. Design by <a href="http://www.nodethirtythree.com/">NodeThirtyThree</a>. Modified for SLiMS by <a href="mailto:hendrowicaksono@yahoo.com">Hendro Wicaksono</a>.-->
		
		</div>

	</div>

</div>

</body>
</html>
