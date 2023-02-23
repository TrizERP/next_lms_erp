<?php

use App\Models\tbluserModel;
use Illuminate\Http\Request;
use App\Http\Controllers\AJAXController;

Route::group(['prefix' => 'transportation', 'middleware' => ['session', 'menu','logRoute']], function() {
    Route::resource('add_driver', add_driver_controller::class);
    Route::resource('add_vehicle', add_vehicle_controller::class);
    Route::resource('add_route', add_route_controller::class);
    Route::resource('add_stop', add_stop_controller::class);
    Route::resource('map_route_bus', map_route_bus_controller::class);
    Route::resource('map_route_stop', map_route_stop_controller::class);
    Route::resource('map_student', map_student_controller::class);
    Route::resource('send_late_sms', send_late_sms_controller::class);
    Route::resource('van_wise_report', van_wise_report_controller::class);

    Route::POST('show_van_wise_report', [van_wise_report_controller::Class, 'showVanWiseReport'])->name('show_van_wise_report');
});

Route::get('api/get-bus-list', [AJAXController::class, 'getBusList']);
Route::get('api/get-stop-list', [AJAXController::class, 'getStopList']);
Route::GET('map_student/fetchData', [map_student_controller::class, 'fetchData']);
Route::GET('ajaxCheckRemainCapacity', [map_student_controller::class, 'ajaxChackRemainCapacity'])->name('ajaxCheckRemainCapacity');

//Route::get('api/get-to_bus-list', 'AJAXController@getBusList');
//Route::get('api/get-to_stop-list', 'AJAXController@getStopList');

