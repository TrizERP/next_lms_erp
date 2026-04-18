<?php


use App\Http\Controllers\implementation\implementation_MasterController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\settings\conversationalAIController;

Route::group(['prefix' => 'implementation', 'middleware' => ['session', 'menu', 'logRoute','check_permissions']], function () {
    Route::resource('add_implementation', implementation_MasterController::class);
});

Route::group(['prefix' => 'conversational_ai', 'middleware' => ['session']], function () {
route::get('genkitDetailsAPI', [conversationalAIController::class, 'genkitDetailsAPI'])->name('genkitDetailsAPI');
route::get('MockQueriesAPI', [conversationalAIController::class, 'MockQueriesAPI'])->name('MockQueriesAPI');
});
Route::get('/get-intents-list', [conversationalAIController::class, 'getIntentsList'])->name('getIntentsList');
