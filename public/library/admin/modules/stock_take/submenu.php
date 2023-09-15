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

/* Stock Take module submenu items */
$menu[] = array('Header2', 'Stock Take');
//$menu[] = array('Header', gettext('Stock Take'));
$menu[] = array(gettext('Stock Take History'), MODULES_WEB_ROOT_DIR.'stock_take/index.php', gettext('View Stock Take History'));
// check if there is any active stock take proccess
$stk_query = $dbs->query('SELECT * FROM stock_take WHERE is_active=1');
if ($stk_query->num_rows) {
    $menu[] = array(gettext('Current Stock Take'), MODULES_WEB_ROOT_DIR.'stock_take/current.php', gettext('View Current Stock Take Process'));
    $menu[] = array(gettext('Stock Take Report'), MODULES_WEB_ROOT_DIR.'stock_take/st_report.php', gettext('View Current Stock Take Report'));
    $menu[] = array(gettext('Finish Stock Take'), MODULES_WEB_ROOT_DIR.'stock_take/finish.php', gettext('Finish Current Stock Take Proccess'));
    $menu[] = array(gettext('Current Lost Item'), MODULES_WEB_ROOT_DIR.'stock_take/lost_item_list.php', gettext('View Lost Item in Current Stock Take Proccess'));
    $menu[] = array(gettext('Stock Take Log'), MODULES_WEB_ROOT_DIR.'stock_take/st_log.php', gettext('View Log of Current Stock Take Proccess'));
    $menu[] = array(gettext('Resynchronize'), MODULES_WEB_ROOT_DIR.'stock_take/resync.php', gettext('Resynchronize bibliographic data with current stock take'));
    $menu[] = array(gettext('Upload List'), MODULES_WEB_ROOT_DIR.'stock_take/st_upload.php', gettext('Upload List in text file'));
} else {
    $menu[] = array(gettext('Initialize'), MODULES_WEB_ROOT_DIR.'stock_take/init.php', gettext('Initialize New Stock Take Proccess'));
}
?>
