<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<!--
	green1.0 by Arie Nugraha & Senayan Developers Team
	http://senayan.diknas.go.id
	Do not remove this comment for appreciation reason.
        If you modify this template for your own need, just add 
        "This template has been modified by your_name_here"
-->
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en" dir="ltr"><head><title><?php echo $page_title; ?></title><meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<link rel="icon" href="webicon.ico" type="image/x-icon" />
<link rel="shortcut icon" href="webicon.ico" type="image/x-icon" />
<link href="template/core.style.css" rel="stylesheet" type="text/css" />
<link href="<?php echo $template_css; ?>" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="js/prototype.js"></script>
<script type="text/javascript" src="js/form.js"></script>
<script type="text/javascript" src="js/gui.js"></script>
<?php echo $metadata; ?>
</head>
<body>

<table id="main" cellpadding="0" cellspacing="0">
<!-- main menu -->
<tr>
<td id="mainMenu" colspan="2">
<ul id="menuList">
        <li><a class="menu" href="index.php"><?php echo gettext('Home'); ?></a></li>
      <!--comment is made by iresh on 11/1/2011  <li><a class="menu" href="index.php?p=libinfo"><?php echo gettext('Library Information'); ?></a></li>-->
        <li><a class="menu" href="index.php?p=help"><?php echo gettext('Help on Search'); ?></a></li>
        <!--comment is made by iresh on 11/1/2011 <li><a class="menu" href="index.php?p=member"><?php echo gettext('Member Area'); ?></a></li>-->
        <li><a class="menu" href="index.php?p=member"><?php echo gettext('Member Login'); ?></a></li>
        <li><a class="menu" href="index.php?p=login"><?php echo gettext('Librarian LOGIN'); ?></a></li>
</ul>
</td>
</tr>
<!-- main menu end -->

<!-- header -->
<tr>
        <td id="mainHeader" colspan="2"><div id="headerImage">&nbsp;</div>
            <div id="libraryName"><?php echo $page_title; ?>
                <div id="librarySubName"><?php echo $library_subname; ?></div>
            </div>
        </td>
</tr>
<!-- header end -->

<!--body-->
<tr>
<!-- sidepan -->
<td id="sidepan" valign="top">
    <!-- language selection -->
       <!-- comment by iresh on 11/1/2011 <div class="heading"><?php echo gettext('Select Language'); ?></div>
        <form name="langSelect" action="index.php" method="get">
        <select name="select_lang" style="width: 99%;" onchange="document.langSelect.submit();">
        <?php echo $language_select; ?>
        </select>
        </form>-->
    <!-- language selection end -->

    <!-- simple search -->
        <div class="heading"><?php echo gettext('Simple Search'); ?></div>
        <form name="simpleSearch" action="index.php" method="get">
        <input type="text" name="keywords" style="width: 99%;" /><br />
        <input type="submit" name="search" value="<?php echo gettext('Search'); ?>" class="button marginTop" />
        </form>
    <!-- simple search end -->

    <!-- advanced search -->
        <div class="heading"><?php echo gettext('Advanced Search'); ?></div>
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
    <!-- advanced search end -->

    <!-- license -->
      <!--comment made by iresh on 11/1/2011    <div class="heading">License</div>
        <p>
        This Software is Released Under <a href="http://www.gnu.org/copyleft/gpl.html" title="GNU GPL License" target="_blank">GNU GPL License</a>
        Version 3.
        </p>-->
    <!-- license end -->

    <!-- award -->
  <!--comment made by iresh on 11/1/2011      <div class="heading">Award</div>
        <p align="center">
        The Winner in the Category of OSS
        <img src="template/green/media/logo-inaicta.png"
            alt="Indonesia ICT Award 2009" border="0" />
        <br />
        </p>-->
    <!-- award -->

    <!-- w3c validate -->
     <!--comment made by iresh on 11/1/2011     <div class="heading">Validated</div>
        <p align="center">
        <a href="http://validator.w3.org/check?uri=referer"><img
            src="template/valid-xhtml10.png"
            alt="Valid XHTML 1.0 Transitional" border="0" /></a>
        <br />
        <img src="template/valid-css.png" alt="Valid CSS" />
        </p>-->
    <!-- w3c validate end -->
</td>
<!-- main menu end -->
<!-- main content -->
<td id="mainContent" valign="top">
<?php echo $header_info; ?>
<div id="infoBox"><?php echo $info; ?></div>
<?php echo $main_content; ?>
<br />
</td>
<!-- main content end -->
</tr>
<!--body end-->

</table>

</body>
</html>
