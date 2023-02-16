<?php

use App\Models\tbluserModel;
use Illuminate\Http\Request;

Route::group(['prefix' => 'transportation', 'middleware' => ['session', 'menu','logRoute']], function() {
    Route::resource('add_driver', 'transportation\add_driver\add_driver_controller');
    Route::resource('add_vehicle', 'transportation\add_vehicle\add_vehicle_controller');
    Route::resource('add_route', 'transportation\add_route\add_route_controller');
    Route::resource('add_stop', 'transportation\add_stop\add_stop_controller');
    Route::resource('map_route_bus', 'transportation\map_route_bus\map_route_bus_controller');
    Route::resource('map_route_stop', 'transportation\map_route_stop\map_route_stop_controller');
    Route::resource('map_student', 'transportation\map_student\map_student_controller');
    Route::resource('send_late_sms', 'transportation\send_late_sms\send_late_sms_controller');
    Route::resource('van_wise_report', 'transportation\van_wise_report\van_wise_report_controller');

    Route::POST('show_van_wise_report', 'transportation\van_wise_report\van_wise_report_controller@showVanWiseReport')->name("show_van_wise_report");
});

Route::get('api/get-bus-list', 'AJAXController@getBusList');
Route::get('api/get-stop-list', 'AJAXController@getStopList');
Route::GET('map_student/fetchData', 'transportation\map_student\map_student_controller@fetchData');
Route::GET('ajaxCheckRemainCapacity', 'transportation\map_student\map_student_controller@ajaxChackRemainCapacity')->name('ajaxCheckRemainCapacity');

//Route::get('api/get-to_bus-list', 'AJAXController@getBusList');
//Route::get('api/get-to_stop-list', 'AJAXController@getStopList');

