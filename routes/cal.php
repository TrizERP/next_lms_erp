<?php

use App\Http\Controllers\calendar\calendar\calendar_controller;
use Illuminate\Support\Facades\Route;


Route::controller(calendar_controller::class)->group(function () {
    Route::group(['prefix' => 'calendar', 'middleware' => ['session', 'menu','logRoute']], static function() {
        Route::resource('calendar', 'calendar\calendar\calendar_controller');
    });

    Route::POST('/studentCalenderAPI', 'calendar\calendar\calendar_controller@studentCalenderAPI');

    Route::GET('calendar/fetchData', 'calendar\calendar\calendar_controller@fetchData');
    Route::POST('calendar/TeacherFetchData', 'calendar\calendar\calendar_controller@TeacherFetchData');    
});
