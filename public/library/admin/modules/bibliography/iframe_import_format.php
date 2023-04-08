<?php
require '../../../sysconfig.inc.php';
require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/simbio_dbop.inc.php';
//ob_start();


if (isset($_POST['keywords']) AND !empty($_POST['keywords'])) 
{

   $keywords = $dbs->escape_string(urldecode(trim($_POST['keywords'])));
}
else
{
    $keywords = '';
}

echo "<br>";
echo "<font color='red' size='2' >Import File Format(Please Do check Format Before Import Data)</font>";
if ($keywords==132 || $keywords==133)
{
?>
    <a href="#" onclick="openHTMLpop('<?php echo MODULES_WEB_ROOT_DIR.'bibliography/catalog_import/master_magazine.xls';?>')">Download Formate</a>
<?php
}
elseif ($keywords==130)
{
?>
        <a href="#" onclick="openHTMLpop('<?php echo MODULES_WEB_ROOT_DIR.'bibliography/catalog_import/master_book.xls';?>')">Download Formate</a>
<!--    <a href="javascript:void(0)" onclick="openHTMLpop('<?php // echo MODULES_WEB_ROOT_DIR.'bibliography/catalog_import/master_book.xls';?>', 500, 500, '<?php //echo MODULES_WEB_ROOT_DIR.'bibliography/master_book.xls';?>')">Download Formate</a>-->
<?php
}
elseif ($keywords==131)
{
?>
        <a href="#" onclick="openHTMLpop('<?php echo MODULES_WEB_ROOT_DIR.'bibliography/catalog_import/master_cd.xls';?>')">Download Formate</a>    
<?php
}
elseif($keywords==68 || $keywords==69|| $keywords==70|| $keywords==71 || $keywords==72 || $keywords ==73 || $keywords==74 || $keywords==75 || $keywords==76 || $keywords==97 || $keywords==138 || $keywords==89 || $keywords==86 || $keywords==90 || $keywords==91 || $keywords==92 || $keywords==92 || $keywords==93 || $keywords==94 || $keywords==95 || $keywords==96)
{
?>
<a href="#" onclick="openHTMLpop('<?php echo MODULES_WEB_ROOT_DIR.'bibliography/catalog_import/master_useful website.xls';?>')">Download Formate</a>                
<?php
}
elseif ($_POST['keywords']==78 || $_POST['keywords']==112 || $_POST['keywords']==113 )
{ ?>
<a href="#" onclick="openHTMLpop('<?php echo MODULES_WEB_ROOT_DIR.'bibliography/catalog_import/master_Textbook.xls';?>')">Download Formate</a>                    
<?php 
}
elseif ($_POST['keywords']==114 || $_POST['keywords']==115 || $_POST['keywords']==116  || $_POST['keywords']==117 || $_POST['keywords']==118)
{
?>
<a href="#" onclick="openHTMLpop('<?php echo MODULES_WEB_ROOT_DIR.'bibliography/catalog_import/master_journal.xls';?>')">Download Formate</a>                    
<?php 
}  
elseif($_POST['keywords']==84 ||$_POST['keywords']==85||$_POST['keywords']==87||$_POST['keywords']==88)
{
?>
<a href="#" onclick="openHTMLpop('<?php echo MODULES_WEB_ROOT_DIR.'bibliography/catalog_import/master_dictionaries.xls';?>')">Download Formate</a>                    
<?
}
elseif($_POST['keywords']==83 || $_POST['keywords']==79 ||$_POST['keywords']==77 || $_POST['keywords']==80 || $_POST['keywords']==81  || $_POST['keywords']==82)
{
?>
<a href="#" onclick="openHTMLpop('<?php echo MODULES_WEB_ROOT_DIR.'bibliography/catalog_import/master_teacher.xls';?>')">Download Formate</a>                    

<?php
}
$keywords=intval($keywords);
//echo $keywords;die;
$book1=array('Column Name:','Title' ,'Edition' ,'ISBN', 'Publisher Name','Publishing Year','Serial Title','Call Number','Language','publishing place','Classification','Notes','Image','File Attach','Author','Subject','Item Start value','Specific Detail Info','Copies','Price','label','Supplier','Subtitle','Keyword','Book review','Standards','Frequncy');
$book_row2=array('Nature(Mandatory/Optional) :','Mandatory' ,'Optional' ,'Optional', 'Optional','Optional','Optional','Optional','Optional','Optional','Optional','Optional','Optional','Optional','Optional','Optional','Mandatory','Optional','Mandatory','Optional','Optional','Optional','Optional','Optional','Optional','Optional','Optional');
$book_row3=array('Data Type :','Text' ,'Alphanumeric' ,'Alphanumeric', 'Text','Date(yyyy)','Text','Numeric','Text','Text','Text','Text','Text','Text','Text','Text','Numeric','Text','Numeric','Numeric','Text','Text','Text','Text','Text','Text','Text');
$book_row4=array('Max Length :','100' ,'35' ,'20', '40','4','100','10','30','30','30','50','80','100','30','30','13','50','10','10','30','30','30','30','30','30','10');

$book_virtual1=array('Column Name:','Title' ,'Edition' ,'ISBN', 'Publisher Name','Publishing Year','Serial Title','Call Number','Language','publishing place','Classification','Notes','Image','File Attach','Author','Subject','Specific Detail Info','label','Supplier','Subtitle','Keyword','Book review','Standards','Frequncy');
$book_virtual_row2=array('Nature(Mandatory/Optional):','Mandatory' ,'Optional' ,'Optional', 'Optional','Optional','Optional','Optional','Optional','Optional','Optional','Optional','Optional','Optional','Optional','Optional','Mandatory','Optional','Mandatory','Optional','Optional','Optional','Optional','Optional');
$book_virtual_row3=array('Data Type :','Text' ,'Alphanumeric' ,'Alphanumeric', 'Text','Date(yyyy)','Text','Numeric','Text','Text','Text','Text','Text','Text','Text','Text','Text','Text','Text','Text','Text','Text','Text','Text');
$book_virtual_row4=array('Max Length :','100' ,'35' ,'20', '40','4','100','10','30','30','30','50','80','100','30','30','13','50','10','10','30','30','30','30');



$Mag_row1=array('Column Name:','Title','Edition' ,'Keyword', 'Specific Detaile Info','Item Start Value','Copies','Price');
$Mag_row2=array('Nature(Mandatory/Optional) :','Mandatory','Optional' ,'Optional', 'Optional','Mandatory','Mandatory','Optional');
$Mag_row3=array('Data Type :','Text','Alphanumeric' ,'Text', 'Text','Numeric','Numeric','Numeric');
$Mag_row4=array('Max Length :','100','35' ,'50', '50','13','10','10');

    
$cd_row1=array('Column Name:','Title','Edition' ,'ISBN','Keyword', 'Specific Detaile Info','Item Start Value','Copies','Price');
$cd_row2=array('Nature(Mandatory/Optional):','Mandatory','Optional','Optional' ,'Optional', 'Optional','Mandatory','Mandatory','Optional');
$cd_row3=array('Data Type :','Text','Alphanumeric' ,'Alphanumeric', 'Text','Text','Numeric','Numeric','Numeric');
$cd_row4=array('Max Length :','100','35' ,'20', '50','50','13','10','10');

$news_row1=array('Column Name:','Title','Edition' ,'Keyword', 'Specific Detaile Info','Item Start Value','Copies','Price');
$news_row2=array('Nature(Mandatory/Optional) :','Mandatory','Optional' ,'Optional', 'Optional','Mandatory','Mandatory','Optional');
$news_row3=array('Data Type :','Text','Alphanumeric' ,'Text','Text','Numeric','Numeric','Numeric');
$news_row4=array('Max Length :','100','35' ,'50', '50','13','10','10');

$usefull_website_row1=array('Column Name:','Title','Keyword', 'File Attach','lable');
$usefull_website_row2=array('Nature(Mandatory/Optional) :','Mandatory','Optional' ,'Optional', 'Optional');
$usefull_website_row3=array('Data Type :','Text','Text','Text','URL');
$usefull_website_row4=array('Max Length :','100','35' ,'50', '50');

$teacher_row1=array('Column Name:','Title','ISBN','Series Title' ,'Keyword', 'Author','Frequency','Publisher','Publishing Place','Publication Date','Subjects','Language','File Attach','Label');
$teacher_row2=array('Nature(Mandatory/Optional) :','Mandatory','Optional' ,'Optional', 'Optional','Optional','Optional','Optional','Optional','Optional','Optional','Optional','Optional','Optional');
$teacher_row3=array('Data Type :','Text','Alphanumeric' ,'Text', 'Text','Text','Text','Text','Text','Date(dd-mm-yyyy)','Text','Text','Text','URL');
$teacher_row4=array('Max Length :','100','35' ,'50', '50','50','10','50','50','10','50','50','50','50');



$referance_row1=array('Column Name:','Title','Edition','ISBN','Series Title' ,'Keyword','Specific Detaile Info', 'Author','Frequency','Publisher','Publishing Year','Publishing Place','Publication Date','Subjects','Language','Image','File Attach','Label','Notes','Sub Title','Classification','Number OF Pages','Standard','Abstract','Book Review','Academic Year','Issue No.','Serial no');
$referance_row2=array('Nature(Mandatory/Optional) :','Mandatory','Optional' ,'Optional', 'Optional','Optional','Optional','Optional','Optional','Optional','Optional','Optional','Optional','Optional','Optional','Optional','Optional','Optional','Optional','Optional','Optional','Optional','Optional','Optional','Optional','Optional','Optional','Optional');
$referance_row3=array('Data Type :','Text','Alphanumeric' ,'Text', 'Text','Text','Text','Text','Text','Text','Date(yyyy)','Text','Date(dd-mm-yyyy)','Text','Text','Text','Text','URL','Text','Text','Text','Numeric','Text','Text','Text','Date(yyyy)','Text','Text');
$referance_row4=array('Max Length :','100','35' ,'50', '50','50','50','35','10','35','4','35','10','35','35','35','35','35','35','35','35','10','35','35','35','4','35','35');



$quick_link_row1=array('Column Name:','Title','ISBN','Series Title' ,'Keyword', 'Author','Frequency','Publisher','Publishing Place','Publication Date','Subjects','Language','File Attach','Label','Notes','Classification','Sub Title','Abstract');
$quick_link_row2=array('Nature(Mandatory/Optional) :','Mandatory','Optional' ,'Optional', 'Optional','Optional','Optional','Optional','Optional','Optional','Optional','Optional','Optional','Optional','Optional','Optional','Optional','Optional');
$quick_link_row3=array('Data Type :','Text','Alphanumeric' ,'Text', 'Text','Text','Text','Text','Text','Date(dd-mm-yyyy)','Text','Text','Text','Text','Text','Text','Text','Text');
$quick_link_row4=array('Max Length :','100','35' ,'50', '50','50','50','35','35','10','35','35','35','35','35','35','35','35');


 echo '<div style="width:850px;;overflow:auto;">';
 $table = new simbio_table(); 
 $table->table_attr = 'align="center" class="detailTable" style="width: 100%;;font-size:12px;" cellpadding="2" cellspacing="0" border="1" ';
 if ($_POST['keywords']==130)
 {
    $table->appendTableRow($book1);
    $table->appendTableRow($book_row2);
    $table->appendTableRow($book_row3);
    $table->appendTableRow($book_row4);
     
    
    
 }
 elseif ($_POST['keywords']==114 || $_POST['keywords']==115 || $_POST['keywords']==116  || $_POST['keywords']==117 || $_POST['keywords']==118)
 {
    $table->appendTableRow($quick_link_row1);
    $table->appendTableRow($quick_link_row2);
    $table->appendTableRow($quick_link_row3);
    $table->appendTableRow($quick_link_row4);
    
    
 }
 elseif ($_POST['keywords']==78 || $_POST['keywords']==112 || $_POST['keywords']==113 )
 {
    $table->appendTableRow($book_virtual1);
    $table->appendTableRow($book_virtual_row2);
    $table->appendTableRow($book_virtual_row3);
    $table->appendTableRow($book_virtual_row4);
    
    
 }
 elseif($_POST['keywords']==132)
 {
    $table->appendTableRow($Mag_row1);
     $table->appendTableRow($Mag_row2);
     $table->appendTableRow($Mag_row3);
     $table->appendTableRow($Mag_row4);
     
 }
 elseif($_POST['keywords']==133)
 {
    $table->appendTableRow($news_row1);
     $table->appendTableRow($news_row2);
     $table->appendTableRow($news_row3);
     $table->appendTableRow($news_row4);
     
 }
 elseif($_POST['keywords']==83 || $_POST['keywords']==79 ||$_POST['keywords']==77 || $_POST['keywords']==80 || $_POST['keywords']==81  || $_POST['keywords']==82)
 {
    $table->appendTableRow($teacher_row1);
     $table->appendTableRow($teacher_row2);
     $table->appendTableRow($teacher_row3);
     $table->appendTableRow($teacher_row4);
     
 }
 elseif($_POST['keywords']==68 ||$_POST['keywords']==69||$_POST['keywords']==70||$_POST['keywords']==71 || $_POST['keywords']==72 || $_POST['keywords']==73 || $_POST['keywords']==74 || $_POST['keywords']==75 || $_POST['keywords']==76 || $_POST['keywords']==97|| $_POST['keywords']==89 || $_POST['keywords']==86 || $_POST['keywords']==90 || $_POST['keywords']==91 || $_POST['keywords']==92 || $_POST['keywords']==92 || $_POST['keywords']==93 || $_POST['keywords']==94 || $_POST['keywords']==95 || $_POST['keywords']==96 || $_POST['keywords']==138)
 {
     $table->appendTableRow($usefull_website_row1);
     $table->appendTableRow($usefull_website_row2);
     $table->appendTableRow($usefull_website_row3);
     $table->appendTableRow($usefull_website_row4);
     
 }
 elseif($_POST['keywords']==84 ||$_POST['keywords']==85||$_POST['keywords']==87||$_POST['keywords']==88)
 {
     $table->appendTableRow($referance_row1);
     $table->appendTableRow($referance_row2);
     $table->appendTableRow($referance_row3);
     $table->appendTableRow($referance_row4);
     
 }
 elseif ($_POST['keywords']==131)
 {
     $table->appendTableRow($cd_row1);
     $table->appendTableRow($cd_row2);
     $table->appendTableRow($cd_row3);
     $table->appendTableRow($cd_row4);
 }
  echo $table->printTable();
  echo '</div>';
  echo "<br>";


//$content = ob_get_clean();
//require SENAYAN_BASE_DIR.'/admin/'.$sysconf['admin_template']['dir'].'/notemplate_page_tpl.php';
?>
