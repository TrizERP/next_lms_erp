<?php


use App\Http\Controllers\implementation\implementation_MasterController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'implementation', 'middleware' => ['session', 'menu', 'logRoute']], function () {
    Route::resource('add_implementation', implementation_MasterController::class);
});
