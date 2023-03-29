<?php

use App\Http\Controllers\visitor_management\visitor_masterController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'visitor_management', 'middleware' => ['session', 'menu', 'logRoute']], function () {
    Route::resource('add_visitor_master', visitor_masterController::class);
    Route::get('show_visitor_report', [visitor_masterController::class, 'show_visitor_report'])
        ->name("show_visitor_report");
    Route::post('show_visitor_report_data', [visitor_masterController::Class, 'show_visitor_report_data'])
        ->name("show_visitor_report_data");
});
