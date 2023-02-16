<?php
Route::group(['prefix' => 'inward_outward', 'middleware' => ['session', 'menu','logRoute']], function() {
    Route::resource('add_physical_file_location', 'inward_outward\physical_file_locationController');
    Route::resource('add_place_master', 'inward_outward\place_masterController');
    Route::resource('add_inward', 'inward_outward\inwardController');
    Route::resource('add_outward', 'inward_outward\outwardController');
    Route::resource('show_inward_report', 'inward_outward\inward_reportController');
    Route::resource('show_outward_report', 'inward_outward\outward_reportController');
});
?>