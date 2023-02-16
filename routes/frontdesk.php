<?php
Route::group(['prefix' => 'frontdesk', 'middleware' => ['session', 'menu','logRoute']], function () {
	Route::resource('frontdesk', 'frontdesk\frontdeskController');
	Route::resource('task', 'frontdesk\taskController');
	Route::resource('complaint', 'frontdesk\complaintController');
	Route::resource('pettycashmaster', 'frontdesk\PettyCashMasterController');
	Route::resource('pettycash', 'frontdesk\PettyCashController');

	Route::GET('frontdesk_report_index', 'frontdesk\frontdeskController@frontDeskReportIndex')->name("frontdesk_report_index");

	Route::POST('frontdesk_report', 'frontdesk\frontdeskController@frontDeskReport')->name("frontdesk_report");

	Route::resource('syllabus', 'front_desk\syllabus\syllabusController');
	Route::resource('user_log', 'front_desk\user_log\user_logController');
	Route::resource('book_list', 'front_desk\book_list\book_listController');
	Route::resource('schooldetail', 'front_desk\school_detail\schooldetailController');
	Route::GET('task_report', 'frontdesk\taskController@taskReportIndex')->name("task_report_index");
	Route::GET('complaint_report', 'frontdesk\complaintController@complaintReportIndex')->name("complaint_report_index");
	Route::resource('pettycashreport', 'frontdesk\PettyCashReportController');
	Route::POST('ajax_getpettycashreport', 'frontdesk\PettyCashReportController@getpettycashreport')->name('ajax_getpettycashreport');
});

?>