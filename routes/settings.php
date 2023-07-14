<?php

use App\Http\Controllers\settings\biomatrixController;
use App\Http\Controllers\settings\instituteDetailController;
use App\Http\Controllers\settings\manageInstituteController;
use App\Http\Controllers\settings\smtpController;
use App\Http\Controllers\settings\tblcustomfieldsController;
use App\Http\Controllers\settings\templateMasterController;
use Illuminate\Support\Facades\Route;


Route::group(['prefix' => 'settings', 'middleware' => ['session', 'menu', 'logRoute']], function () {
    Route::resource('add_fields', tblcustomfieldsController::class);
    Route::resource('smtp_setting', smtpController::class);
    Route::resource('biomatrix', biomatrixController::class);
    Route::get('setsession', [tblcustomfieldsController::class, 'setsession'])->name('setsession');
    Route::get('setinstitute', [tblcustomfieldsController::class, 'setinstitute'])->name('setinstitute');
    Route::post('check-email', [smtpController::class, 'CheckEmail'])->name('check-email');    

    Route::resource('templatemaster', templateMasterController::class);
    Route::get('view_all_tag', [templateMasterController::class, 'viewAllTag'])->name('view_all_tag');
    Route::resource('institute_detail', instituteDetailController::class);
    Route::resource('manage_institute', manageInstituteController::class);

});
