<?php


use App\Http\Controllers\hostel_management\admission_category_masterController;
use App\Http\Controllers\hostel_management\hostel_building_masterController;
use App\Http\Controllers\hostel_management\hostel_floor_masterController;
use App\Http\Controllers\hostel_management\hostel_masterController;
use App\Http\Controllers\hostel_management\hostel_reportController;
use App\Http\Controllers\hostel_management\hostel_room_masterController;
use App\Http\Controllers\hostel_management\hostel_visitor_masterController;
use App\Http\Controllers\hostel_management\hostel_visitor_reportController;
use App\Http\Controllers\hostel_management\hosteltypemasterController;
use App\Http\Controllers\hostel_management\room_type_masterController;
use App\Http\Controllers\hostel_management\tblhostelRoomAllocationController;
use Illuminate\Support\Facades\Route;


Route::group(['prefix' => 'hostel_management', 'middleware' => ['session', 'menu', 'logRoute','check_permissions']], function() {
    Route::resource('add_hostel_type_master', hosteltypemasterController::class);
    // Route::get('listhosteltype', 'hostel_management\hosteltypemasterController@listhosteltype')->name('listhosteltype');
    Route::resource('add_room_type_master', room_type_masterController::class);
    Route::resource('add_admission_category_master', admission_category_masterController::class);
    Route::resource('add_hostel_master', hostel_masterController::class);
    Route::resource('add_hostel_visitor_master', hostel_visitor_masterController::class);
    Route::resource('add_hostel_building_master', hostel_building_masterController::class);
    Route::resource('add_hostel_floor_master', hostel_floor_masterController::class);
    Route::resource('add_hostel_room_master', hostel_room_masterController::class);
    Route::resource('show_hostel_visitor_report', hostel_visitor_reportController::class);
    Route::resource('hostel_room_allocation', tblhostelRoomAllocationController::class);
    Route::resource('hostel_report', hostel_reportController::class);
    Route::get('room_report', [hostel_reportController::class, 'roomIndex'])->name("room_report");
    Route::post('show_room_report', [hostel_reportController::class, 'roomReport'])->name("show_room_report");
    Route::post('student_hostel_room_allocation', [tblhostelRoomAllocationController::class, 'student_hostel_room_allocation'])->name("student_hostel_room_allocation");
    Route::post('hostelList', [hostel_masterController::class, 'hostelList'])->name('hostelList');
    Route::post('hostelWiseRoomList', [hostel_room_masterController::class, 'hostelWiseRoomList'])->name('hostelWiseRoomList');

    Route::post('show_hostel_report', [hostel_reportController::class, 'showHostelReport'])->name("show_hostel_report");
});
?>
