<?php


use App\Http\Controllers\consent\consent_masterController;
use App\Http\Controllers\consent\delete_consent_masterController;
use App\Http\Controllers\consent\report_consent_masterController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'consent', 'middleware' => ['session', 'menu', 'logRoute']], function () {
    Route::resource('add_consent_master', consent_masterController::class);
    Route::resource('delete_consent_master', delete_consent_masterController::class);
    Route::resource('report_consent_master', report_consent_masterController::class);
});
Route::post('/consentListAPI', [consent_masterController::class, 'consentListAPI']);
