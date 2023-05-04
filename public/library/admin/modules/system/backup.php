<?php
/**
 *
 * Copyright (C) 2007,2008  Arie Nugraha (dicarve@yahoo.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 */

/* Backup Management section */

// main system configuration
require '../../../sysconfig.inc.php';
// start the session
require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
require SENAYAN_BASE_DIR.'admin/default/session_check.inc.php';

// privileges checking
$can_read = utility::havePrivilege('system', 'r');
$can_write = utility::havePrivilege('system', 'w');

if (!($can_read AND $can_write)) {
    die('<div class="errorBox">'.gettext('You don\'t have enough privileges to view this section').'</div>');
}
/* search form */
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
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'membership/index.php?action=detail class="headerText2">Add New Member</a>';
}
else
{
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'membership/index.php class="headerText2">View Member List</a>';
}*/
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'system/backup.php class="headerText2">Database Backup</a>';
echo $bradecum;
        ?>	
	</td>
</tr>
</table>
<table>
<tr>
	<td class="tab_menu_top">
                            <ul class="tabs"> 
<li>
 <a href="<?php echo MODULES_WEB_ROOT_DIR; ?>/system/backup_proc.php" postdata="start=true" loadcontainer="backupStat" class="headerText2"><?php echo gettext('Start New Backup'); ?></a>
</li>
</ul>
	</td>
</tr>
</table>
<fieldset class="menuBox">
<div class="menuBoxInner backupIcon">
  <!--  <?php echo strtoupper(gettext('Database Backup')); ?> - <a href="<?php echo MODULES_WEB_ROOT_DIR; ?>/system/backup_proc.php" postdata="start=true" loadcontainer="backupStat" class="headerText2"><?php echo gettext('Start New Backup'); ?></a>-->
     <p class="only_border">&nbsp;</p>
    <form name="search" action="<?php echo MODULES_WEB_ROOT_DIR; ?>system/backup_proc.php" id="search" method="get" style="display: inline;"><?php echo gettext('Search'); ?> :
    <input type="text" name="keywords" size="30" />
    <input type="submit" id="doSearch" value="<?php echo gettext('Search'); ?>" class="button" />
    </form>
</div>
</fieldset>
<div id="backupStat">
<?php require 'backup_proc.php'; ?>
</div>
