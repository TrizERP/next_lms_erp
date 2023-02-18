<?php

use App\Http\Controllers\result\cbse_result\cbse_11_t2_result_controller;
use App\Http\Controllers\result\cbse_result\cbse_1t5_result_controller;
use App\Http\Controllers\result\cbse_result\cbse_1t5_t2_result_controller;
use App\Http\Controllers\result\cbse_result\overall_mark_report_controller;
use App\Http\Controllers\result\cbse_result\result_report_controller;
use App\Http\Controllers\result\cbse_result\WRT_progress_report_controller;
use App\Http\Controllers\result\cbse_result\WRT_report_controller;
use App\Http\Controllers\result\co_scholastic\co_scholastic_controller;
use App\Http\Controllers\result\co_scholastic_marks_entry\co_scholastic_marks_entry_controller;
use App\Http\Controllers\result\co_scholastic_master\co_scholastic_master_controller;
use App\Http\Controllers\result\exam_creation\exam_creation_controller;
use App\Http\Controllers\result\ExamMaster\ExamMasterController;
use App\Http\Controllers\result\ExamTypeMaster\ExamTypeMasterController;
use App\Http\Controllers\result\GradeMaster\GradeMasterController;
use App\Http\Controllers\result\marks_entry\marks_entry_controller;
use App\Http\Controllers\result\result_book_master\result_book_master_controller;
use App\Http\Controllers\result\result_master\result_master_controller;
use App\Http\Controllers\result\result_remark_master\result_remark_master_controller;
use App\Http\Controllers\result\std_grd_maping\std_grd_maping_controller;
use App\Http\Controllers\result\student_attendance_master\student_attendance_master_controller;
use App\Http\Controllers\result\upload_result\upload_result_controller;
use App\Http\Controllers\result\working_day_master\working_day_master_controller;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'result', 'middleware' => ['session', 'menu', 'logRoute']], function () {
	Route::resource('exam_type_master', ExamTypeMasterController::class);
	Route::resource('exam_master', ExamMasterController::class);
	Route::resource('grade_master', GradeMasterController::class);
	Route::get('grade_master/createData/{id}', ['as' => 'grade_master.createData', 'uses' => 'result\GradeMaster\GradeMasterController@AddAllData']);
	Route::resource('std_grd_maping', std_grd_maping_controller::class);
	Route::resource('result_master', result_master_controller::class);
	Route::resource('exam_creation', exam_creation_controller::class);
	Route::resource('result_remark_master', result_remark_master_controller::class);
	Route::resource('result_book_master', result_book_master_controller::class);
	Route::resource('co_scholastic_master', co_scholastic_master_controller::class);
	Route::resource('co_scholastic', co_scholastic_controller::class);
	Route::resource('working_day_master', working_day_master_controller::class);
	Route::resource('student_attendance_master', student_attendance_master_controller::class);
	Route::resource('marks_entry', marks_entry_controller::class);
	Route::resource('co_scholastic_marks_entry', co_scholastic_marks_entry_controller::class);
	Route::resource('cbse_1t5_result', cbse_1t5_result_controller::class);
	Route::resource('cbse_1t5_t2_result', cbse_1t5_t2_result_controller::class);
	Route::resource('overall_mark_report', overall_mark_report_controller::class);
	Route::post('cbse_1t5_result/show_result', ['as' => 'cbse_1t5_result.show_result', 'uses' => 'result\cbse_result\cbse_1t5_result_controller@show_result']);
    Route::post('cbse_1t5_t2_result/show_result', ['as' => 'cbse_1t5_t2_result.show_result', 'uses' => 'result\cbse_result\cbse_1t5_t2_result_controller@show_result']);
	Route::resource('cbse_11_t2_result', cbse_11_t2_result_controller::class);
	Route::post('cbse_11_t2_result/show_result', ['as' => 'cbse_11_t2_result.show_result', 'uses' => 'result\cbse_result\cbse_11_t2_result_controller@show_result']);
	Route::resource('WRT_report', WRT_report_controller::class);
	Route::post('WRT_report/show_result', ['as' => 'WRT_report.show_result', 'uses' => 'result\cbse_result\WRT_report_controller@show_result']);
	Route::resource('WRT_progress_report', WRT_progress_report_controller::class);
	Route::post('WRT_progress_report/show_result', ['as' => 'WRT_progress_report.show_result', 'uses' => 'result\cbse_result\WRT_progress_report_controller@show_result']);

    Route::resource('result_report', result_report_controller::class);
	Route::post('show_result_report', 'result\cbse_result\result_report_controller@show_result_report')->name('show_result_report');
	Route::GET('ajax_StandardwiseSubject', 'result\cbse_result\result_report_controller@ajax_StandardwiseSubject')->name('ajax_StandardwiseSubject');

	Route::resource('upload_result', upload_result_controller::class);
	// Route::GET('student_homework_submission_report_index', 'student\studentHomeworkSubmissionController@studentHomeworkSubmissionReportIndex')->name("student_homework_submission_report_index");

//    Route::post('cbse_1t5_result', 'result\cbse_result\cbse_1t5_result_controller');
});

Route::POST('/uploadResultAPI', 'result\upload_result\upload_result_controller@uploadResultAPI');

//Route::get('api/dependent-dropdown', 'AJAXController@index');
// Route::get('api/get-grade-list', 'AJAXController@getGradeList');
Route::get('api/get-standard-list', 'AJAXController@getStandardList');
Route::get('api/get-division-list', 'AJAXController@getDivisionList');
Route::get('api/get-subject-list', 'AJAXController@getSubjectList');
/** get exam list */
Route::get('api/get-exam-name-list', 'AJAXController@getExamsList');
/** get subjec by create exam list */
Route::get('api/get-subject-by-create-exam', 'AJAXController@getSubjectByCreateExam');
/** get Exam by create exam list */
Route::get('api/get-exam-name-by-create-exam', 'AJAXController@getExamByCreateExam');
Route::get('api/get-chapter-list', 'AJAXController@getChapterList');
Route::get('api/get-topic-list', 'AJAXController@getTopicList');
Route::get('api/get-exam-list', 'AJAXController@getExamList');
Route::get('api/get-co-scholastic-parent-list', 'AJAXController@getCoScholasticParentList');
Route::get('api/get-co-scholastic-list', 'AJAXController@getCoScholasticList');

Route::GET('ajax_sendEmailFeesReceipt', 'AJAXController@ajax_sendEmailFeesReceipt')->name('ajax_sendEmailFeesReceipt');
Route::GET('ajax_sendBulkEmailFeesReceipt', 'AJAXController@ajax_sendBulkEmailFeesReceipt')->name('ajax_sendBulkEmailFeesReceipt');

// Route::group(['prefix' => 'easy_com', 'middleware' => ['session', 'mastersetup_menu']], function () {
Route::group(['prefix' => 'easy_com', 'middleware' => ['session', 'menu','logRoute']], function () {
	Route::resource('manage_sms_api', 'easy_com\manage_sms_api\manage_sms_api_controller');
	Route::resource('send_sms_parents', 'easy_com\send_sms_parents\send_sms_parents_controller');
	Route::resource('send_email_report', 'easy_com\send_email_report\send_email_report_controller');
	Route::resource('send_email_parents', 'easy_com\send_email_parents\send_email_parents_controller');
	Route::resource('send_email_other', 'easy_com\send_email_other\send_email_other_controller');
	// Route::get('send_email_parents/send_email', 'easy_com\send_email_parents\send_email_parents_controller@sendEmail')->name("send_mail");
	Route::post('send_email_parents/send_email', 'easy_com\send_email_parents\send_email_parents_controller@sendEmail')->name("send_mail");
	Route::post('send_email_other/send_other_mail', 'easy_com\send_email_other\send_email_other_controller@sendEmail')->name("send_other_mail");
	Route::resource('send_sms_staff', 'easy_com\send_sms_staff\send_sms_staff_controller');
	Route::resource('send_sms_report', 'easy_com\send_sms_report\send_sms_report_controller');

	Route::resource('send_notification_parents', 'easy_com\send_notification_parents\send_notification_parents_controller');
	Route::resource('register_parents_report', 'easy_com\send_notification_report\register_parents_report_controller'); 
	Route::resource('notification_report', 'easy_com\send_notification_report\notification_report_controller'); 

});
Route::POST('announcement', 'easy_com\send_sms_staff\send_sms_staff_controller@GetStudentAnnouncement');

Route::group(['prefix' => 'learning_outcome', 'middleware' => ['session', 'menu','logRoute']], function () {
	Route::resource('lo_master', 'learning_outcome\lo_master\lo_masterController');
	Route::resource('indicator_mapping', 'learning_outcome\indicator_mapping\indicator_mappingController');
	Route::post('indicator_mapping/get_indicator', 'learning_outcome\indicator_mapping\indicator_mappingController@get_indicator')->name('get_indicator');
	Route::resource('lo_marks_entry', 'learning_outcome\lo_marks_entry\lo_marks_entryController');
	Route::resource('lo_marks_report', 'learning_outcome\lo_marks_report\lo_marks_reportController');
	Route::resource('lo_marks_arNar', 'learning_outcome\lo_marks_arNar\lo_marks_arNarController');
	Route::resource('lo_marks_greport', 'learning_outcome\lo_marks_greport\lo_marks_greportController');
	Route::resource('lo_marks_greport2', 'learning_outcome\lo_marks_greport2\lo_marks_greport2Controller');
	Route::resource('lo_class_greport', 'learning_outcome\lo_class_greport\lo_class_greportController');
	Route::resource('lo_school_greport', 'learning_outcome\lo_school_greport\lo_school_greportController');

});
Route::get('api/get-lo-subject-list', 'AJAXController@getLOSubjectList');
Route::get('api/get-lo', 'AJAXController@getLOList');

Route::POST('api/get_marks_dd', 'result\marks_entry\marks_entry_controller@get_marks_dd');
Route::POST('api/get_co_scholastic_marks_dd', 'result\marks_entry\marks_entry_controller@get_co_scholastic_marks_dd');
Route::POST('api/get_result', 'result\marks_entry\marks_entry_controller@get_result');


Route::group(['prefix' => 'report', 'middleware' => ['session', 'menu']], function() {
	Route::resource('dynamic_report', 'report\dynamic_report\dynamic_report_controller');
	// exam report
	Route::get('student-mark-report', 'report\StudentsMarksReportController@index')->name('student-mark-report');
	// log report
	Route::get('log-report', 'report\StudentsMarksReportController@index')->name('log-report');
	Route::POST('dynamic_report/step2', 'report\dynamic_report\dynamic_report_controller@dynamicReportStep2')->name("dynamic_report_step2");
 	Route::POST('dynamic_report/step3', 'report\dynamic_report\dynamic_report_controller@dynamicReportStep3')->name("dynamic_report_step3");
});

Route::get('api/get-sub_module-list', 'AJAXController@getSubModuleList');

Route::post('save_result_html', 'result\cbse_result\cbse_1t5_result_controller@save_result_html')->name("save_result_html");
Route::post('studentResultPDFAPI', 'result\cbse_result\cbse_1t5_result_controller@studentResultPDFAPI')->name("studentResultPDFAPI");
//Route::group(['prefix' => 'calendar', 'middleware' => ['session', 'menu']], function() {
//    Route::resource('calendar', 'calendar\calendar\calendar_controller');
//});
//Route::group(['prefix' => 'transportation', 'middleware' => ['session', 'menu']], function() {
//    Route::resource('add_driver', 'transportation\add_driver\add_driver_controller');
//    Route::resource('add_vehicle', 'transportation\add_vehicle\add_vehicle_controller');
//});

//Route::group(['namespace'=>'Latfur\Event\Http\Controllers'],function(){
//Route::group(['middleware' => ['session', 'menu']], function() {
//    Route::get('event', 'Event\EventController@index')->name('event');
//    Route::get('event-list', 'Event\EventController@event_list');
//    Route::post('event', 'Event\EventController@save_event');
//    Route::get('all-event', 'Event\EventController@all_event')->name('all-event');
//    Route::get('single-event/{id}', 'Event\EventController@single_event');
//    Route::post('update-event', 'Event\EventController@update_event');
//    Route::delete('delete-event/{id}', 'Event\EventController@delete_event');
//});
