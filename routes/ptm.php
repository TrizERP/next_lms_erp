<?php
Route::group(['prefix' => 'ptm', 'middleware' => ['session', 'menu','logRoute']], function() {
    Route::resource('add_ptm_time_slot_master', 'ptm\ptmtimeslotmasterController');
    Route::resource('add_ptm_attened_status', 'ptm\ptmattenedstatusController');
});
Route::POST('/ptmBookAPI', 'ptm\ptmattenedstatusController@ptmBookAPI');
Route::POST('/ptmBookingStatusAPI', 'ptm\ptmattenedstatusController@ptmBookingStatusAPI');
Route::POST('/ptmBookingTimeAPI', 'ptm\ptmattenedstatusController@ptmBookingTimeAPI');
Route::POST('/ptmTeacherListAPI', 'ptm\ptmattenedstatusController@ptmTeacherListAPI');

?>