<?php
/**
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

/* Membership module submenu items */
$menu[] = array('Header2', 'System');
//$menu[] = array('Header', gettext('System'));
// only administrator have privileges for below menus
//added started by Parth 25/8/2011
$menu[] = array(gettext('Change User Profiles'), MODULES_WEB_ROOT_DIR.'system/app_user.php?changecurrent=true&action=detail', gettext('Change Current User Profiles and Password'));
//added ended by Parth 25/8/2011
if ($_SESSION['uid'] == 1) {
    $menu[] = array(gettext('System Configuration'), MODULES_WEB_ROOT_DIR.'system/index.php', gettext('Configure Global System Preferences'));
}
$menu[] = array(gettext('Content'), MODULES_WEB_ROOT_DIR.'system/content.php', gettext('Content'));
// only administrator have privileges for below menus
if ($_SESSION['uid'] == 1) {
    $menu[] = array(gettext('Modules'), MODULES_WEB_ROOT_DIR.'system/module.php', gettext('Configure Application Modules'));
    $menu[] = array(gettext('System Users'), MODULES_WEB_ROOT_DIR.'system/app_user.php', gettext('Manage Application User or Library Staff'));
    $menu[] = array(gettext('User Group'), MODULES_WEB_ROOT_DIR.'system/user_group.php', gettext('Manage Group of Application User'));
}
$menu[] = array(gettext('Holiday Setting'), MODULES_WEB_ROOT_DIR.'system/holiday.php', gettext('Configure Holiday Setting'));
$menu[] = array(gettext('Barcode Generator'), MODULES_WEB_ROOT_DIR.'system/barcode_generator.php', gettext('Barcode Generator'));
$menu[] = array(gettext('System Log'), MODULES_WEB_ROOT_DIR.'system/sys_log.php', gettext('View Application System Log'));
$menu[] = array(gettext('Database Backup'), MODULES_WEB_ROOT_DIR.'system/backup.php', gettext('Backup Application Database'));
?>
