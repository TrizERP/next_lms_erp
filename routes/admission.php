<?php

use App\Http\Controllers\admission\admissionEnquiryController;
use App\Http\Controllers\admission\admissionFollowUpController;
use App\Http\Controllers\admission\admissionFormController;
use App\Http\Controllers\admission\admissionRegistrationController;
use App\Http\Controllers\admission\admissionReportController;
use App\Http\Controllers\admission\onlineAdmissionConfirmController;
use Illuminate\Support\Facades\Route;


Route::group(['prefix' => 'admission', 'middleware' => ['session', 'menu', 'logRoute','check_permissions']], static function () {
    Route::resource('admission_enquiry', admissionEnquiryController::class);
    Route::resource('admission_registration', admissionFormController::class);
    Route::resource('admission_confirmation', admissionRegistrationController::class);
    Route::resource('admission_follow_up', admissionFollowUpController::class);
    Route::resource('online_admission_confirm', onlineAdmissionConfirmController::class);

    Route::get('admissionAI', [admissionEnquiryController::class,'admissionAI'])->name('admissionAI');

    Route::controller(admissionRegistrationController::class)->group(function () {
        Route::post('admission_student', 'saveStudent')->name('admission_student');
    });

    Route::controller(admissionReportController::class)->group(function () {
        Route::any('admission_enquiry_report', 'enquiryReport')->name('admission_enquiry_report');
        Route::any('admission_registration_report', 'formReport')->name('admission_registration_report');
        Route::any('admission_without_con_report', 'regReport')->name('admission_without_con_report');
        Route::any('admission_confirmation_report', 'conReport')->name('admission_confirmation_report');
        Route::any('admission_enquiry_followup_report', 'followUpReport')->name('admission_enquiry_followup_report');
    });

    Route::controller(onlineAdmissionConfirmController::class)->group(function () {
        Route::any('online_admission_report', 'onlineAdmissionReport')->name('online_admission_report');
        Route::post('ajax_AdmissionConfirmReport', 'ajax_AdmissionConfirmReport')->name('ajax_AdmissionConfirmReport');
    });

    Route::controller(admissionEnquiryController::class)->group(function () {
        Route::get('ajax_getFeesBreakoff', 'ajax_getFeesBreakoff')->name('ajax_getFeesBreakoff');
    });

});

Route::controller(admissionEnquiryController::class)->group(function () {
    Route::get('admission/online-admission/{id}/{title}', 'onlineEnquiryFirst')->name('onlineEnquiryFirst');
    Route::get('admission/online-admission-enquiry/{id}/{title}', 'onlineEnquiry')->name('onlineEnquiry');
    Route::post('admission/process-admission-enquiry', 'processOnlineEnquiry')->name('processOnlineEnquiry');
    Route::any('admission/admission-receipt', 'receipt')->name('receipt');

    Route::get('ajax_listCalendarData', 'ajax_listCalendarData')
        ->middleware('logRoute')->name('ajax_listCalendarData');

});

Route::controller(admissionRegistrationController::class)->group(function () {
    Route::get('ajax_getDivision', 'ajax_getDivision')->name('ajax_getDivision');
});

