<?php

use App\Http\Controllers\bazar\bulkUploadSheetController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::group(['prefix' => 'bazar', 'middleware' => ['session', 'menu', 'logRoute']], function () {
    Route::resource('bulk_upload_sheet', bulkUploadSheetController::class);

});
