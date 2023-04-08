<?php

session_start();
require '../../../sysconfig.inc.php';
require SENAYAN_BASE_DIR.'admin/default/session_check.inc.php';

utility::loadSettings($dbs);

// privileges checking
$can_read = utility::havePrivilege('circulation', 'r');
$can_write = utility::havePrivilege('circulation', 'w');

//if (!($can_read AND $can_write)) {
//    die('<div class="errorBox">'.__('You don\'t have enough privileges to view this section').'</div>');
//}

// check if quick return is enabled
if (!$sysconf['quick_return']) {
    die('<div class="errorBox">'.__('Quick Return is disabled').'</div');
}
?>
<table  align=center>
<tr>
	<td valign=top>
	<?php
	$bradecum = '';       
        $basedir = basename(dirname(__FILE__));
        $bradecum = "<a href=javascript:void(0); onclick=javascript:new_set_home(); >Home</a>-><a class='' href=javascript:void(0); onclick=javascript:new_set('".$basedir."');>"; 
	$query = "select module_name from mst_module where module_path = '".$basedir."'";
	$set_query = $dbs->query($query);
	while($row=$set_query->fetch_assoc())
	{
                $_formated_module_name = ucwords(str_replace('_', ' ', $row['module_name']));
		$bradecum .= $_formated_module_name;
	}
$bradecum .= '</a>->';
/*if(isset($_REQUEST['action']))
{
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'bibliography/index.php?action=detail&physical=physical class="headerText2">Add Hardcopy Book</a>';
}
else
{
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'bibliography/index.php class="headerText2">Book List</a>';
}*/
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'circulation/quick_return.php class="headerText2">Quick Return</a>';
echo $bradecum;
        ?>	
	</td>
</tr>
</table>

<table>
<tr>
	<td class="tab_menu_top">
                            <ul class="tabs"> 
<!--<li><a href="<?php echo MODULES_WEB_ROOT_DIR; ?>bibliography/index.php" class="headerText2"><?php //echo __('Library Resource List'); ?></a></li>-->
<li><a href="<?php echo  MODULES_WEB_ROOT_DIR; ?>circulation/quick_return.php"><?php echo __('Quick Return'); ?></a> </li>
</ul></td></tr></table>


<fieldset class="menuBox">
<div class="menuBoxInner quickReturnIcon">
    <?php echo strtoupper(__('Quick Return')); ?> - <?php echo __('Insert an item ID to return collection with keyboard or barcode reader'); ?>
    <p class="only_border">&nbsp;</p>
    <form class="notAJAX" action="<?php echo MODULES_WEB_ROOT_DIR; ?>circulation/ajax_action.php" target="circAction" method="post" style="display: inline;">
    <?php echo __('Item ID'); ?> :    
    <input onkeyup="return checkspecialcharacterdynamic(this.name);" type="text" name="quickReturnID" id="quickReturnID"  width=140px />
    <input type="submit" value="<?php echo __('Return'); ?>" class="button" />
    </form>
    <iframe name="circAction" id="circAction" style="display: inline; width: 5px; height: 5px; visibility: hidden;"></iframe>
</div>
</fieldset>
<div id="circulationLayer">&nbsp;</div>
<script type="text/javascript">
// focus item code/barcode text field
$('quickReturnID').focus();
</script>
