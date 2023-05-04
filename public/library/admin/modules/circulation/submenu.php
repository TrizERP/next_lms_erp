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

/* Circulation module submenu items */

//$menu[] = array('Header', __('Circulation'));
$menu[] = array('Header2', 'Circulation');
//if(isset($_REQUEST['memberID'])){
// $menu[] = array(__('Book Circulation'), MODULES_WEB_ROOT_DIR.'circulation/circulation_action.php', __('Start Circulation Transaction Proccess'));
//}
//else{
 $menu[] = array(gettext('Book Circulation'), MODULES_WEB_ROOT_DIR.'circulation/index.php?action=start', gettext('Start Circulation Transaction Proccess'));   
//}
$menu[] = array(gettext('Quick Return'), MODULES_WEB_ROOT_DIR.'circulation/quick_return.php', gettext('Quick Return Collection'));

// $menu[] = array(__('Scan Books'), MODULES_WEB_ROOT_DIR.'circulation/scan_item.php', __('Scan Items'));

//$menu[] = array(__('Loan Rules'), MODULES_WEB_ROOT_DIR.'circulation/loan_rules.php', __('View and Modify Circulation Loan Rules'));
//$menu[] = array(__('Loan History'), MODULES_WEB_ROOT_DIR.'reporting/customs/loan_history.php', __('Loan History Overview'));
//$menu[] = array(__('Overdued List'), MODULES_WEB_ROOT_DIR.'reporting/customs/overdued_list.php', __('View Members Having Overdues'));
//$menu[] = array(__('Reservation'), MODULES_WEB_ROOT_DIR.'reporting/customs/reserve_list.php', __('Reservation'));
$menu[] = array(gettext('Reservation'), MODULES_WEB_ROOT_DIR.'circulation/member_book_request_list.php', gettext('Member Book Request'));
//$menu[] = array(__('Member Book Request'), MODULES_WEB_ROOT_DIR.'reporting/customs/member_book_request_list.php', __('Member Book Request'));
?>
