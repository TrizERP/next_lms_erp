<?php
// session_start();
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

/* Master File module submenu items */
$menu[] = array('Header2', 'Master File');
//$menu[] = array('Header', __('Authority Files'));
//$menu[] = array(__('Library Resource Type'), MODULES_WEB_ROOT_DIR.'master_file/material_resource_type.php', __('Library Resource Type'));
//$menu[] = array(__('Material Type'), MODULES_WEB_ROOT_DIR.'master_file/index.php', __('Material Type'));
//$menu[] = array(__('Material Sub Type'), MODULES_WEB_ROOT_DIR.'master_file/material_sub_type.php', __('Material Sub Type'));



$menu[] = array(gettext('Publisher'), MODULES_WEB_ROOT_DIR.'master_file/publisher.php', gettext('Document Publisher'));
$menu[] = array(gettext('Supplier'), MODULES_WEB_ROOT_DIR.'master_file/supplier.php', gettext('Item Supplier'));
$menu[] = array(gettext('Author'), MODULES_WEB_ROOT_DIR.'master_file/author.php', gettext('Document Authors'));
//$menu[] = array(__('Subject Type'), MODULES_WEB_ROOT_DIR.'master_file/topic.php', __('Subject Type'));
$menu[] = array(gettext('Subject'), MODULES_WEB_ROOT_DIR.'master_file/subject_type.php', gettext('Subject'));
$menu[] = array(gettext('Standard/Class'), MODULES_WEB_ROOT_DIR.'master_file/standard.php', gettext('Standard'));
$menu[] = array(gettext('Book Rack/Shelf'), MODULES_WEB_ROOT_DIR.'master_file/location.php', gettext('Item Location'));
//$menu[] = array('Header', __('Lookup Files'));
$menu[] = array(gettext('Publishing Place'), MODULES_WEB_ROOT_DIR.'master_file/place.php', gettext('Place Name'));
$menu[] = array(gettext('Item Status'), MODULES_WEB_ROOT_DIR.'master_file/item_status.php', gettext('Item Status'));
$menu[] = array(gettext('Book Collection Type'), MODULES_WEB_ROOT_DIR.'master_file/coll_type.php', gettext('Collection Type'));
// Start commented by parth 11/7/2011
/*$menu[] = array(__('Doc. Language'), MODULES_WEB_ROOT_DIR.'master_file/doc_language.php', __('Document Content Language'));*/
//End commented by Parth 11/7/2011
$menu[] = array(gettext('Labels'), MODULES_WEB_ROOT_DIR.'master_file/label.php', gettext('Special Labels for Titles to Show Up On Homepage'));
// Start commented by parth 11/7/2011
/*$menu[] = array(__('Frequency'), MODULES_WEB_ROOT_DIR.'master_file/frequency.php', __('Frequency'));*/
//End commented by Parth 11/7/2011
//Following menu Start Added by Parth 12/11/2011
$menu[] = array(gettext('Check URL Status'), MODULES_WEB_ROOT_DIR.'master_file/url_confirm.php', gettext('URL Check'));
//Addition of Menu Ended by Parth 12/11/2011

//$menu[] = array(__('Book Size'), MODULES_WEB_ROOT_DIR.'master_file/book_size.php', __('Book Size'));

?>
