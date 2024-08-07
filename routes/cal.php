<?php

use App\Http\Controllers\calendar\calendar\calendar_controller;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'calendar', 'middleware' => ['session', 'menu', 'logRoute','check_permissions']], static function () {
    Route::resource('calendar', 'calendar\calendar\calendar_controller');
});

Route::controller(calendar_controller::class)->group(function () {
    Route::post('/studentCalenderAPI', 'studentCalenderAPI');
    Route::get('calendar/fetchData', 'fetchData');
    Route::post('calendar/TeacherFetchData', 'TeacherFetchData');
});
