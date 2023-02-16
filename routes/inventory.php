<?php
Route::group(['prefix' => 'inventory', 'middleware' => ['session', 'menu','logRoute']], function() {
    Route::resource('add_inventory_master_setup', 'inventory\inventory_master_setupController');
    Route::resource('add_inventory_item_category_master', 'inventory\inventory_item_category_masterController', ['parameters' => [
            'add_inventory_item_category_master' => 'item_category'
        ]]);
    Route::resource('add_inventory_item_sub_category_master', 'inventory\inventory_item_sub_category_masterController', ['parameters' => [
            'add_inventory_item_sub_category_master' => 'item_sub_category'
        ]]);
    Route::resource('add_inventory_item', 'inventory\inventory_item_masterController');
    
    Route::resource('add_inventory_item_tax_master', 'inventory\inventory_tax_masterController');
    Route::resource('add_inventory_vendor_master', 'inventory\inventory_vendor_masterController');
    Route::resource('add_inventory_item_quotation', 'inventory\inventory_item_quotationController');
    Route::resource('add_item_direct_purchase', 'inventory\inventory_item_direct_purchaseController');    
    Route::resource('add_inventory_generate_po', 'inventory\inventory_generate_poController');
    Route::resource('add_inventory_negotiate_po', 'inventory\inventory_negotiate_poController');
    Route::resource('show_inventory_item_receivable', 'inventory\inventory_item_receivableController');
    Route::resource('show_inventory_item_allocation', 'inventory\inventory_item_allocationController');
    Route::resource('show_inventory_item_return', 'inventory\inventory_item_returnController');
    Route::resource('add_inventory_item_lost', 'inventory\inventory_item_lostController');
    Route::resource('add_inventory_item_defective', 'inventory\inventory_item_defectiveController');
    Route::resource('inventory_delivery_status_report', 'inventory\inventory_delivery_status_reportController');
    Route::resource('inventory_requisition_report', 'inventory\inventory_requisition_reportController');
    Route::resource('inventory_staff_wise_report', 'inventory\inventory_staff_wise_reportController');
    Route::resource('inventory_item_wise_report', 'inventory\inventory_item_wise_reportController');
    Route::resource('inventory_item_lost_report', 'inventory\inventory_item_lost_reportController');
    Route::resource('inventory_overall_item_report', 'inventory\inventory_overall_item_reportController');
    
    
     Route::GET('ajax_CategorywiseSubcategory', 'inventory\inventory_item_masterController@ajax_CategorywiseSubcategory')->name('ajax_CategorywiseSubcategory');
    Route::GET('ajax_SubcategoryeiseItems', 'inventory\inventory_item_masterController@ajax_SubcategoryeiseItems')->name('ajax_SubcategoryeiseItems');
    Route::resource('add_vendor_master_setup', 'inventory\vendor_masterController');
    Route::resource('add_requisition', 'inventory\requisitionController');
    Route::resource('requisition_approved', 'inventory\requisitionApprovedController');
});
?>