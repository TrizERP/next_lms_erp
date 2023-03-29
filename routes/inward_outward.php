<?php


use App\Http\Controllers\inward_outward\inward_reportController;
use App\Http\Controllers\inward_outward\inwardController;
use App\Http\Controllers\inward_outward\outward_reportController;
use App\Http\Controllers\inward_outward\outwardController;
use App\Http\Controllers\inward_outward\physical_file_locationController;
use App\Http\Controllers\inward_outward\place_masterController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'inward_outward', 'middleware' => ['session', 'menu', 'logRoute']], function() {
    Route::resource('add_physical_file_location', physical_file_locationController::class);
    Route::resource('add_place_master', place_masterController::class);
    Route::resource('add_inward', inwardController::class);
    Route::resource('add_outward', outwardController::class);
    Route::resource('show_inward_report', inward_reportController::class);
    Route::resource('show_outward_report', outward_reportController::class);
});
