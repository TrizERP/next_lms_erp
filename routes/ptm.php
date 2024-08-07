<?php


use App\Http\Controllers\ptm\ptmattenedstatusController;
use App\Http\Controllers\ptm\ptmtimeslotmasterController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'ptm', 'middleware' => ['session', 'menu', 'logRoute','check_permissions']], function() {
    Route::resource('add_ptm_time_slot_master', ptmtimeslotmasterController::class);
    Route::resource('add_ptm_attened_status', ptmattenedstatusController::class);
});

Route::controller(ptmattenedstatusController::class)->group(function () {
    Route::post('/ptmBookAPI', 'ptmBookAPI');
    Route::post('/ptmBookingStatusAPI', 'ptmBookingStatusAPI');
    Route::post('/ptmBookingTimeAPI', 'ptmBookingTimeAPI');
    Route::post('/ptmTeacherListAPI', 'ptmTeacherListAPI');
});
