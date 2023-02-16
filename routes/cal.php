<?php

use App\Models\tbluserModel;
use Illuminate\Http\Request;


Route::group(['prefix' => 'calendar', 'middleware' => ['session', 'menu','logRoute']], function() {
    Route::resource('calendar', 'calendar\calendar\calendar_controller');
});

Route::POST('/studentCalenderAPI', 'calendar\calendar\calendar_controller@studentCalenderAPI');

Route::GET('calendar/fetchData', 'calendar\calendar\calendar_controller@fetchData');
Route::POST('calendar/TeacherFetchData', 'calendar\calendar\calendar_controller@TeacherFetchData');