<?php


use App\Http\Controllers\front_desk\book_list\book_listController;
use App\Http\Controllers\front_desk\school_detail\schooldetailController;
use App\Http\Controllers\front_desk\syllabus\syllabusController;
use App\Http\Controllers\front_desk\user_log\user_logController;
use App\Http\Controllers\frontdesk\complaintController;
use App\Http\Controllers\frontdesk\frontdeskController;
use App\Http\Controllers\frontdesk\PettyCashController;
use App\Http\Controllers\frontdesk\PettyCashMasterController;
use App\Http\Controllers\frontdesk\PettyCashReportController;
use App\Http\Controllers\frontdesk\taskController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'frontdesk', 'middleware' => ['session', 'menu', 'logRoute']], function () {
    Route::resource('frontdesk', frontdeskController::class);
    Route::resource('task', taskController::class);
    Route::resource('complaint', complaintController::class);
    Route::resource('pettycashmaster', PettyCashMasterController::class);
    Route::resource('pettycash', PettyCashController::class);

    Route::get('frontdesk_report_index',
        [frontdeskController::class, 'frontDeskReportIndex'])->name("frontdesk_report_index");

	Route::post('frontdesk_report', [frontdeskController::class, 'frontDeskReport'])->name("frontdesk_report");

	Route::resource('syllabus', syllabusController::class);
	Route::resource('user_log', user_logController::class);
	Route::resource('book_list', book_listController::class);
	Route::resource('schooldetail', schooldetailController::class);
	Route::get('task_report', [taskController::class, 'taskReportIndex'])->name("task_report_index");
	Route::get('complaint_report', [complaintController::class, 'complaintReportIndex'])->name("complaint_report_index");
	Route::resource('pettycashreport', PettyCashReportController::class);
	Route::post('ajax_getpettycashreport', [PettyCashReportController::class, 'getpettycashreport'])->name('ajax_getpettycashreport');
});

