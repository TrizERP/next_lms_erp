<?php
Route::group(['prefix' => 'driver', 'middleware' => ['session', 'menu', 'logRoute']], function () {
    Route::get('van_driver_report', 'driver_management\driver_masterController@index')->name("van_driver_report");
});