<?php

use App\Http\Controllers\calendar\calendar\calendar_controller;
use App\Http\Controllers\calendar\calendar\calendar_api_controller;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\neo4J\addAssesmentController;

Route::group(['prefix' => 'calendar', 'middleware' => ['session', 'menu', 'logRoute','check_permissions']], static function () {
    Route::resource('calendar', 'calendar\calendar\calendar_controller');
});

// New JSON API routes for the Next.js /front_desk/calendar page. Kept
// separate from the resource group above so the existing calendar_controller
// routes/behaviour are untouched — see calendar_api_controller for why.
Route::group(['prefix' => 'calendar', 'middleware' => ['session', 'menu', 'logRoute', 'check_permissions']], static function () {
    Route::post('calendar-api-store', [calendar_api_controller::class, 'store']);
    Route::get('calendar-api-list', [calendar_api_controller::class, 'index']);
});

Route::controller(calendar_controller::class)->group(function () {
    Route::post('/studentCalenderAPI', 'studentCalenderAPI');
    Route::get('calendar/fetchData', 'fetchData');
    Route::post('calendar/TeacherFetchData', 'TeacherFetchData');
    Route::get('calendar/searchByDate', 'searchByDate')->name('searchByDate');
});


Route::prefix('neo4j')->group(function () {
    // Assessment CRUD operations
    Route::post('/assessment', [addAssesmentController::class, 'store']);
    // REMOVED 2026-09-04 — five routes bound to Neo4jAssessmentController, a class that exists
    // nowhere in the repo and was never imported here, so every one of them threw on dispatch:
    // GET /assessments, GET|PUT|DELETE /assessment/{assId}, POST /assessment/question-paper.
    // addAssesmentController already carries equivalent methods if they are wanted back.
});

