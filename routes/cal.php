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
    Route::get('/assessments', [Neo4jAssessmentController::class, 'index']);
    Route::get('/assessment/{assId}', [Neo4jAssessmentController::class, 'show']);
    Route::put('/assessment/{assId}', [Neo4jAssessmentController::class, 'update']);
    Route::delete('/assessment/{assId}', [Neo4jAssessmentController::class, 'destroy']);
    
    // Special endpoint for question paper data
    Route::post('/assessment/question-paper', [Neo4jAssessmentController::class, 'storeFromQuestionPaper']);
});

