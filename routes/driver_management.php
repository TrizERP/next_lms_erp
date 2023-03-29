<?php


use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'driver', 'middleware' => ['session', 'menu', 'logRoute']], static function () {
    Route::get('van_driver_report', [driver_masterController::class, 'index'])->name("van_driver_report");
});
