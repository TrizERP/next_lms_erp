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

/* Bibliographic module submenu items */

$menu[] = array('Header2', 'Catalog');
/*$menu[] = array(__('Book List'), MODULES_WEB_ROOT_DIR.'bibliography/index.php', __('Show Existing Book Data'));
//$menu[] = array(__('Add New Book'), MODULES_WEB_ROOT_DIR.'bibliography/index.php?action=detail&physical=physical', __('Add New Book Data/Catalog'));
$menu[] = array(__('Add Physical Resources'), MODULES_WEB_ROOT_DIR.'bibliography/index.php?action=detail&physical=physical', __('Add New Book Data/Catalog'));
$menu[] = array(__('Add Virtual Resources'), MODULES_WEB_ROOT_DIR.'bibliography/virtual_resources.php?action=detail', __('Add Virtual Resources'));
$menu[] = array('Header', __('Items'));
$menu[] = array(__('Item List'), MODULES_WEB_ROOT_DIR.'bibliography/item.php', __('Show List of Library Items'));
$menu[] = array(__('Checkout Items'), MODULES_WEB_ROOT_DIR.'bibliography/checkout_item.php', __('Show List of Checkout Items'));
$menu[] = array('Header', __('Tools'));
$menu[] = array(__('Z3950 Service'), MODULES_WEB_ROOT_DIR.'bibliography/z3950.php', __('Grab Book Data from Z3950 Web Services'));
$menu[] = array(__('Import MRC File'), MODULES_WEB_ROOT_DIR.'bibliography/upld_marc.php', __('Import MRC File Data'));
//$menu[] = array(__('P2P Service'), MODULES_WEB_ROOT_DIR.'bibliography/p2p.php', __('Grab Book Data from Other SLiMS Web Services'));
$menu[] = array(__('Labels Printing'), MODULES_WEB_ROOT_DIR.'bibliography/dl_print.php', __('Print Document Labels'));
$menu[] = array(__('Item Barcodes Printing'), MODULES_WEB_ROOT_DIR.'bibliography/item_barcode_generator.php', __('Print Item Barcodes'));
$menu[] = array(__('Import Data'), MODULES_WEB_ROOT_DIR.'bibliography/import.php', __('Import Data to Book Database from CSV file'));
$menu[] = array(__('Export Data'), MODULES_WEB_ROOT_DIR.'bibliography/export.php', __('Export Book Data To CSV format'));
$menu[] = array(__('Item Import'), MODULES_WEB_ROOT_DIR.'bibliography/item_import.php', __('Import Data to Item/Copies database from CSV file'));
$menu[] = array(__('Item Export'), MODULES_WEB_ROOT_DIR.'bibliography/item_export.php', __('Export Item/Copies data To CSV format'));*/

//$menu[] = array('Header', __('Book'));
$menu[] = array(__('Library Resources'), MODULES_WEB_ROOT_DIR.'bibliography/index.php', __('Show Existing Book Data'));
//$menu[] = array(__('Add New Book'), MODULES_WEB_ROOT_DIR.'bibliography/index.php?action=detail&physical=physical', __('Add New Book Data/Catalog'));
//$menu[] = array(__('Add Physical Resources'), MODULES_WEB_ROOT_DIR.'bibliography/index.php?action=detail&physical=physical', __('Add New Book Data/Catalog'));
//$menu[] = array(__('Add Virtual Resources'), MODULES_WEB_ROOT_DIR.'bibliography/virtual_resources.php?action=detail', __('Add Virtual Resources'));
//$menu[] = array('Header', __('Items'));
$menu[] = array(__('Item'), MODULES_WEB_ROOT_DIR.'bibliography/item.php', __('Show List of Library Items'));
//$menu[] = array(__('Checkout Items'), MODULES_WEB_ROOT_DIR.'bibliography/checkout_item.php', __('Show List of Checkout Items'));
//$menu[] = array('Header', __('Tools'));
$menu[] = array(__('Z3950 Service'), MODULES_WEB_ROOT_DIR.'bibliography/z3950.php', __('Grab Book Data from Z3950 Web Services'));
$menu[] = array(__('Import MARC File'), MODULES_WEB_ROOT_DIR.'bibliography/upld_marc.php', __('Import MRC File Data'));
//$menu[] = array(__('P2P Service'), MODULES_WEB_ROOT_DIR.'bibliography/p2p.php', __('Grab Book Data from Other SLiMS Web Services'));
$menu[] = array(__('Print'), MODULES_WEB_ROOT_DIR.'bibliography/dl_print.php', __('Print Document Labels'));
//$menu[] = array(__('Item Barcodes Printing'), MODULES_WEB_ROOT_DIR.'bibliography/item_barcode_generator.php', __('Print Item Barcodes'));
$menu[] = array(__('Import'), MODULES_WEB_ROOT_DIR.'bibliography/import.php', __('Import Data to Book Database from CSV file'));
//$menu[] = array(__('Import Item'), MODULES_WEB_ROOT_DIR.'bibliography/item_import.php', __('Import Data to Item/Copies database from CSV file'));
$menu[] = array(__('Export'), MODULES_WEB_ROOT_DIR.'bibliography/export.php', __('Export Book Data To CSV format'));
//$menu[] = array(__('Export Item'), MODULES_WEB_ROOT_DIR.'bibliography/item_export.php', __('Export Item/Copies data To CSV format'));
?>
