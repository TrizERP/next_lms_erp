<?php

use App\Http\Controllers\AJAXController;
use App\Http\Controllers\transportation\add_driver\add_driver_controller;
use App\Http\Controllers\transportation\add_route\add_route_controller;
use App\Http\Controllers\transportation\add_stop\add_stop_controller;
use App\Http\Controllers\transportation\add_vehicle\add_vehicle_controller;
use App\Http\Controllers\transportation\map_route_bus\map_route_bus_controller;
use App\Http\Controllers\transportation\map_route_stop\map_route_stop_controller;
use App\Http\Controllers\transportation\map_student\map_student_controller;
use App\Http\Controllers\transportation\send_late_sms\send_late_sms_controller;
use App\Http\Controllers\transportation\van_wise_report\van_wise_report_controller;
use App\Http\Controllers\transportation\transport_rate\transportRateController;
use App\Http\Controllers\transportation\add_shift\shiftController;
use App\Http\Controllers\transportation\van_wise_students_detail\van_wise_students_detail_report_controller;

use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'transportation', 'middleware' => ['session', 'menu', 'logRoute','check_permissions']], function () {
    Route::resource('add_driver', add_driver_controller::class);
    Route::resource('add_vehicle', add_vehicle_controller::class);
    Route::resource('add_route', add_route_controller::class);
    Route::resource('add_stop', add_stop_controller::class);
    Route::resource('map_route_bus', map_route_bus_controller::class);
    Route::resource('map_route_stop', map_route_stop_controller::class);
    Route::resource('map_student', map_student_controller::class);
    Route::resource('send_late_sms', send_late_sms_controller::class);
    Route::resource('van_wise_report', van_wise_report_controller::class);
    Route::resource('transport_rate', transportRateController::class);
    Route::resource('transport_shift', shiftController::class);
    Route::resource('van_wise_students_detail_report', van_wise_students_detail_report_controller::class);
    
    Route::post('show_van_wise_report', [van_wise_report_controller::Class, 'showVanWiseReport'])->name('show_van_wise_report');
});

Route::get('api/get-bus-list', [AJAXController::class, 'getBusList']);
Route::get('api/get-stop-list', [AJAXController::class, 'getStopList']);
Route::get('map_student/fetchData', [map_student_controller::class, 'fetchData']);
Route::get('ajaxCheckRemainCapacity', [map_student_controller::class, 'ajaxChackRemainCapacity'])->name('ajaxCheckRemainCapacity');

Route::get('/transportation/transportationLists/studentLists/{t_id}/{t_s_id}', [van_wise_students_detail_report_controller::class, 'retrieveDataByUserId']);

//Route::get('api/get-to_bus-list', 'AJAXController@getBusList');
//Route::get('api/get-to_stop-list', 'AJAXController@getStopList');

