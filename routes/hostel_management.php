<?php
Route::group(['prefix' => 'hostel_management', 'middleware' => ['session', 'menu','logRoute']], function() {
    Route::resource('add_hostel_type_master', 'hostel_management\hosteltypemasterController');
    // Route::GET('listhosteltype', 'hostel_management\hosteltypemasterController@listhosteltype')->name('listhosteltype');
    Route::resource('add_room_type_master', 'hostel_management\room_type_masterController');
    Route::resource('add_admission_category_master', 'hostel_management\admission_category_masterController');
    Route::resource('add_hostel_master', 'hostel_management\hostel_masterController');
    Route::resource('add_hostel_visitor_master', 'hostel_management\hostel_visitor_masterController');
    Route::resource('add_hostel_building_master', 'hostel_management\hostel_building_masterController');
    Route::resource('add_hostel_floor_master', 'hostel_management\hostel_floor_masterController');
    Route::resource('add_hostel_room_master', 'hostel_management\hostel_room_masterController');
    Route::resource('show_hostel_visitor_report', 'hostel_management\hostel_visitor_reportController');
    Route::resource('hostel_room_allocation', 'hostel_management\tblhostelRoomAllocationController');
    Route::resource('hostel_report', 'hostel_management\hostel_reportController');
    Route::GET('room_report', 'hostel_management\hostel_reportController@roomIndex')->name("room_report");
    Route::POST('show_room_report', 'hostel_management\hostel_reportController@roomReport')->name("show_room_report");
    Route::POST('student_hostel_room_allocation', 'hostel_management\tblhostelRoomAllocationController@student_hostel_room_allocation')->name("student_hostel_room_allocation");
    Route::POST('hostelList', 'hostel_management\hostel_masterController@hostelList')->name('hostelList');
    Route::POST('hostelWiseRoomList', 'hostel_management\hostel_room_masterController@hostelWiseRoomList')->name('hostelWiseRoomList');

    Route::POST('show_hostel_report', 'hostel_management\hostel_reportController@showHostelReport')->name("show_hostel_report");
});
?>