<?php
require '../../../sysconfig.inc.php';
require SENAYAN_BASE_DIR.'admin/default/session.inc.php';

$js = '<script type="text/javascript" src="'.JS_WEB_ROOT_DIR.'calendar.js"></script>';

$content = '<script type="text/javascript">'."\n";
$biblioID = intval($_GET['biblioID']);

if (isset($_GET['itemID']) AND isset($_GET['action']))
{    
    $itemID = (integer)$_GET['itemID'];
    $content .= 'setContent(\'pageContent\', \'item.php?inPopUp=true&action=detail&biblioID='.$biblioID.'\', \'post\', \'itemID='.$itemID.'&detail=true\', true);';
} 
else 
{    
    
    $content .= 'setContent(\'pageContent\', \'item.php?inPopUp=true&action=detail&biblioID='.$biblioID.'\', \'post\', \'\', true);';
}
$content .= '</script>';


$page_title = 'Bibliography Items';


require SENAYAN_BASE_DIR.'/admin/'.$sysconf['admin_template']['dir'].'/notemplate_page_tpl.php';
?>
