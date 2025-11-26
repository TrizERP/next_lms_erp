<?php

use App\Http\Controllers\visitor_management\visitor_masterController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'visitor_management', 'middleware' => ['session', 'menu', 'logRoute','check_permissions']], function () {
    Route::resource('add_visitor_master', visitor_masterController::class);
    Route::get('show_visitor_report', [visitor_masterController::class, 'show_visitor_report'])
        ->name("show_visitor_report");
    Route::post('show_visitor_report_data', [visitor_masterController::Class, 'show_visitor_report_data'])
        ->name("show_visitor_report_data");

    Route::get('get_student', [visitor_masterController::class, 'getStudent'])->name("get_student"); 
    Route::get('sendOtpVisitor', [visitor_masterController::class, 'sendOTPVisitor'])->name("sendOtpVisitor"); //sendOtpVisitor
    Route::post('confirmOtp', [visitor_masterController::Class, 'confirmOTP'])->name("confirmOtp");

});
Route::get('/visitor_management/add_visitor_master/create', 
    [visitor_masterController::class, 'create']
)->name('add_visitor_master.create');

// store (public)
Route::post('/visitor_management/add_visitor_master', 
    [visitor_masterController::class, 'store']
)->name('add_visitor_master.store');
