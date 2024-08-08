<?php


use App\Http\Controllers\inventory\inventory_delivery_status_reportController;
use App\Http\Controllers\inventory\inventory_generate_poController;
use App\Http\Controllers\inventory\inventory_item_allocationController;
use App\Http\Controllers\inventory\inventory_item_category_masterController;
use App\Http\Controllers\inventory\inventory_item_defectiveController;
use App\Http\Controllers\inventory\inventory_item_direct_purchaseController;
use App\Http\Controllers\inventory\inventory_item_lost_reportController;
use App\Http\Controllers\inventory\inventory_item_lostController;
use App\Http\Controllers\inventory\inventory_item_masterController;
use App\Http\Controllers\inventory\inventory_item_quotationController;
use App\Http\Controllers\inventory\inventory_item_receivableController;
use App\Http\Controllers\inventory\inventory_item_returnController;
use App\Http\Controllers\inventory\inventory_item_sub_category_masterController;
use App\Http\Controllers\inventory\inventory_item_wise_reportController;
use App\Http\Controllers\inventory\inventory_master_setupController;
use App\Http\Controllers\inventory\inventory_negotiate_poController;
use App\Http\Controllers\inventory\inventory_overall_item_reportController;
use App\Http\Controllers\inventory\inventory_requisition_reportController;
use App\Http\Controllers\inventory\inventory_staff_wise_reportController;
use App\Http\Controllers\inventory\inventory_tax_masterController;
use App\Http\Controllers\inventory\inventory_vendor_masterController;
use App\Http\Controllers\inventory\requisitionApprovedController;
use App\Http\Controllers\inventory\requisitionController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'inventory', 'middleware' => ['session', 'menu', 'logRoute','check_permissions']], function() {
    Route::resource('add_inventory_master_setup', inventory_master_setupController::class);
    Route::resource('add_inventory_item_category_master', inventory_item_category_masterController::class, ['parameters' => [
            'add_inventory_item_category_master' => 'item_category'
        ]]);
    Route::resource('add_inventory_item_sub_category_master', inventory_item_sub_category_masterController::class, ['parameters' => [
            'add_inventory_item_sub_category_master' => 'item_sub_category'
        ]]);
    Route::resource('add_inventory_item', inventory_item_masterController::class);
    
    Route::resource('add_inventory_item_tax_master', inventory_tax_masterController::class);
    Route::resource('add_inventory_vendor_master', inventory_vendor_masterController::class);
    Route::resource('add_inventory_item_quotation', inventory_item_quotationController::class);
    Route::resource('add_item_direct_purchase', inventory_item_direct_purchaseController::class);    
    Route::resource('add_inventory_generate_po', inventory_generate_poController::class);
    Route::resource('add_inventory_negotiate_po', inventory_negotiate_poController::class);
    Route::resource('show_inventory_item_receivable', inventory_item_receivableController::class);
    Route::resource('show_inventory_item_allocation', inventory_item_allocationController::class);
    Route::resource('show_inventory_item_return', inventory_item_returnController::class);
    Route::resource('add_inventory_item_lost', inventory_item_lostController::class);
    Route::resource('add_inventory_item_defective', inventory_item_defectiveController::class);
    Route::resource('inventory_delivery_status_report', inventory_delivery_status_reportController::class);
    Route::resource('inventory_requisition_report', inventory_requisition_reportController::class);
    Route::resource('inventory_staff_wise_report', inventory_staff_wise_reportController::class);
    Route::resource('inventory_item_wise_report', inventory_item_wise_reportController::class);
    Route::resource('inventory_item_lost_report', inventory_item_lost_reportController::class);
    Route::resource('inventory_overall_item_report', inventory_overall_item_reportController::class);
    
    Route::controller(inventory_item_masterController::class)->group(function () {
        Route::get('ajax_CategorywiseSubcategory', 'ajax_CategorywiseSubcategory')->name('ajax_CategorywiseSubcategory');
        Route::get('ajax_SubcategoryeiseItems', 'ajax_SubcategoryeiseItems')->name('ajax_SubcategoryeiseItems');    
    });
    
    Route::resource('add_vendor_master_setup', vendor_masterController::class);
    Route::resource('add_requisition', requisitionController::class);
    Route::resource('requisition_approved', requisitionApprovedController::class);
});
