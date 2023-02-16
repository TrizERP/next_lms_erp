<?php
Route::group(['prefix' => 'student', 'middleware' => ['session', 'menu','logRoute']], function () {
	Route::resource('add_student', 'student\tblstudentController');
	Route::resource('add_house', 'student\houseController');
	Route::resource('past_education', 'student\tblstudentPastEducationController');
	Route::resource('student_document', 'student\tblstudentDocumentController');
	Route::resource('student_infirmary', 'student\studentInfirmaryController');
	Route::resource('student_vaccination', 'student\studentVaccinationController');
	Route::resource('student_hw', 'student\studentHWController');
	Route::resource('student_health', 'student\studentHealthController');
	Route::resource('family_history', 'student\tblstudentFamilyHistoryController');
	Route::resource('parent_feedback', 'student\tblstudentParentFeedbackController');
	Route::resource('search_student', 'student\studentSearchController');
	Route::resource('student_report', 'student\studentReportController');
	Route::resource('bulk_student_update', 'student\bulkStudentController');
	Route::resource('transfer_student', 'student\transferStudentController');
	Route::resource('student_quota', 'student\studentQuotaController');
    Route::resource('student_icard', 'student\studentIcardController');
    Route::resource('teacher_icard', 'student\teacherIcardController');
	Route::resource('student_certificate', 'student\studentCertificateController');
	Route::resource('student_certificate_report', 'student\student_certificate_reportController');
	Route::resource('student_request', 'student\studentRequestController');
	Route::resource('student_request_report', 'student\studentRequestReportController');
	Route::resource('student_attendance', 'student\studentAttendanceController');
	Route::resource('student_graph_attendance', 'student\graph_attendance\student_graph_attendanceController');
	Route::resource('student_fees_graph', 'student\fees_graph\student_fees_graphController');
	Route::resource('student_homework', 'student\studentHomeworkController');
	Route::resource('student_homework_submission', 'student\studentHomeworkSubmissionController');
	Route::resource('student_fees_detail', 'student\tblstudentFeesDetailController');
	Route::resource('student_tc_detail', 'student\tblstudentTcController');
    Route::resource('Transport','student/TransportController');
    Route::resource('student_transfer', 'student\studentTransferController');
    Route::resource('rollover', 'student\rollOverController');
    Route::resource('house_automation', 'student\houseAutomationController');

    Route::GET('selected_student_view', 'student\rollOverController@selected_student_view')->name("selected_student_view");

    Route::GET('ajax_toAcademicSections', 'student\studentTransferController@ajax_toAcademicSections')->name('ajax_toAcademicSections');
    Route::GET('ajax_toStandards', 'student\studentTransferController@ajax_toStandards')->name('ajax_toStandards');
    Route::GET('ajax_toDivisions', 'student\studentTransferController@ajax_toDivisions')->name('ajax_toDivisions');

	Route::GET('student_homework_report_index', 'student\studentHomeworkController@studentHomeworkReportIndex')->name("student_homework_report_index");
	Route::POST('student_homework_report', 'student\studentHomeworkController@studentHomeworkReport')->name("student_homework_report");
	Route::GET('student_homework_submission_report_index', 'student\studentHomeworkSubmissionController@studentHomeworkSubmissionReportIndex')->name("student_homework_submission_report_index");

	Route::POST('student_homework_submission_report', 'student\studentHomeworkSubmissionController@studentHomeworkSubmissionReport')->name("student_homework_submission_report");

	Route::resource('student_change_request_type', 'student\studentChangeRequestTypeController');
	Route::POST('show_search_student', 'student\studentSearchController@searchStudent')->name("show_search_student");
	Route::POST('show_student_report', 'student\studentReportController@searchStudent')->name("show_student_report");
	Route::resource('missing_document_report', 'student\missingDocumentReportController');
	Route::resource('inactive_student_report', 'student\InactiveStudentReportController');
	Route::POST('show_bulk_student', 'student\bulkStudentController@searchStudent')->name("show_bulk_student");
	Route::POST('show_student', 'student\transferStudentController@searchStudent')->name("show_student");
	Route::POST('bulk_update', 'student\bulkStudentController@bulkUpdate')->name("bulk_update");
	Route::POST('transfer_student', 'student\transferStudentController@transferStudent')->name("transfer_student");
	Route::POST('show_student_attendance', 'student\studentAttendanceController@showStudent')->name("show_student_attendance");
	Route::POST('save_student_attendance', 'student\studentAttendanceController@saveStudentAttendance')->name("save_student_attendance");
	Route::GET('daywise_student_attendance', 'student\studentAttendanceController@daywiseStudentAttendance')->name("daywise_student_attendance_report");
	Route::POST('show_daywise_student_attendance', 'student\studentAttendanceController@showDaywiseStudentAttendance')->name("show_daywise_student_attendance_report");

	Route::GET('student_health_report', 'student\studentInfirmaryController@studentHealthReport')->name("student_health_report");
	Route::POST('show_student_health_report', 'student\studentInfirmaryController@showStudentHealthReport')->name("show_student_health_report");

	Route::GET('monthwise_student_attendance', 'student\studentAttendanceController@monthwiseStudentAttendance')->name("monthwise_student_attendance_report");
	Route::POST('show_monthwise_student_attendance', 'student\studentAttendanceController@showMonthwiseStudentAttendance')->name("show_monthwise_student_attendance_report");

	Route::POST('search_student_by_lastname', 'student\studentSearchController@searchStudentLastName')->name("search_student_by_lastname");
	Route::POST('search_student_by_firstname', 'student\studentSearchController@searchStudentFirstName')->name("search_student_by_firstname");
	Route::POST('search_student_name', 'student\studentSearchController@searchStudentName')->name("search_student_name");
	Route::POST('search_student_id', 'student\studentSearchController@searchStudentId')->name("search_student_id");
	Route::POST('add_student_siblings', 'student\studentSearchController@addStudentSiblings')->name("add_student_siblings");
	//Route::POST('display_student_siblings', 'student\studentSearchController@displayStudentSiblings')->name("display_student_siblings");

	Route::post('student_icard/show_student', ['as' => 'student_icard.show_student', 'uses' => 'student\studentIcardController@showStudent']);
	Route::post('teacher_icard/show_teacher', ['as' => 'teacher_icard.show_teacher', 'uses' => 'student\teacherIcardController@showTeacher']);

	Route::GET('view_samples', 'student\studentIcardController@viewSamples')->name("view_samples");
	Route::GET('view_samples_user', 'student\teacherIcardController@viewSamples')->name("view_samples_user");

	Route::post('student_icard/show_student_icard', 'student\studentIcardController@showStudentIcard')->name('show_student_icard');
	Route::post('teacher_icard/show_teacher_icard', 'student\teacherIcardController@showTeacherIcard')->name('show_teacher_icard');

	Route::post('student_certificate/show_student', ['as' => 'student_certificate.show_student', 'uses' => 'student\studentCertificateController@showStudent']);

	Route::post('student_certificate/show_student_certificate', 'student\studentCertificateController@showStudentCertificate')->name('show_student_certificate');
	Route::GET('ajax_getBatch', 'student\tblstudentController@ajax_getBatch')->name('ajax_getBatch');	
	Route::GET('ajax_getOptionalSubject', 'student\tblstudentController@ajax_getOptionalSubject')->name('ajax_getOptionalSubject');

	Route::GET('ajax_getHomeworkSubjects', 'student\studentHomeworkController@ajax_getHomeworkSubjects')->name('ajax_getHomeworkSubjects');

});



Route::GET('ajax_saveData', 'student\studentCertificateController@ajax_saveData')->name('ajax_saveData');

Route::POST('/studentAttendanceAPI', 'student\studentAttendanceController@studentAttendanceAPI');
Route::POST('/studentTeacherListAPI', 'student\studentAttendanceController@studentTeacherListAPI');
Route::POST('/studentHealthAPI', 'student\studentHealthController@studentHealthAPI');
Route::POST('/studentExamScheduleAPI', 'front_desk\exam_schedule\exam_scheduleController@studentExamScheduleAPI');
Route::POST('/studentDisciplineAPI', 'front_desk\dicipline\diciplineController@studentDisciplineAPI');
Route::POST('/schoolDetailAPI', 'front_desk\school_detail\schooldetailController@schoolDetailAPI');
Route::POST('/studentPhotoVideoGalleryAPI', 'front_desk\photo_video_gallary\photo_video_gallaryController@studentPhotoVideoGalleryAPI');
Route::POST('/studentLeaveApplicationAPI', 'front_desk\leave_application\leaveApplicationController@studentLeaveApplicationAPI');
Route::POST('/studentHostelAllocationAPI', 'hostel_management\tblhostelRoomAllocationController@studentHostelAllocationAPI');
Route::POST('/studentCertificateAPI', 'student\studentCertificateController@studentCertificateAPI');
Route::POST('/teacherSendSmsParentsAPI', 'easy_com\send_sms_parents\send_sms_parents_controller@teacherSendSmsParentsAPI');
Route::POST('/teacherSendEmailParentsAPI', 'easy_com\send_email_parents\send_email_parents_controller@sendEmail');

Route::POST('/teacherLeaveApplicationListAPI', 'front_desk\leave_application\leaveApplicationController@teacherLeaveApplicationListAPI');
Route::POST('/teacherLeaveApplicationSaveAPI', 'front_desk\leave_application\leaveApplicationController@teacherLeaveApplicationSaveAPI');

Route::POST('/studentInfirmaryAPI', 'student\studentInfirmaryController@studentInfirmaryAPI');
Route::POST('/studentVaccinationAPI', 'student\studentVaccinationController@studentVaccinationAPI');
Route::POST('/studentHWAPI', 'student\studentHWController@studentHWAPI');

Route::GET('under_development', 'student\studentReportController@underDevelopment')->name("under_development")->middleware(['session', 'menu','logRoute']);
Route::GET('under_development_master', 'student\studentReportController@underDevelopment')->name("under_development_master")->middleware(['session', 'mastersetup_menu','logRoute']);
Route::GET('firstpage_school', 'student\studentReportController@firstpage_school')->name("firstpage_school")->middleware(['session', 'menu','logRoute']);
Route::GET('firstpage_student', 'student\studentReportController@firstpage_student')->name("firstpage_student")->middleware(['session', 'menu','logRoute']);
Route::GET('firstpage_teacher', 'student\studentReportController@firstpage_teacher')->name("firstpage_teacher")->middleware(['session', 'menu','logRoute']);

Route::group(['prefix' => 'front_desk', 'middleware' => ['session', 'menu','logRoute']], function () {
	Route::resource('parent_communication', 'front_desk\parentCommunication\parentCommunicationController');
	Route::resource('leave_application', 'front_desk\leave_application\leaveApplicationController');
	Route::resource('circular', 'front_desk\circular\circularController');
	Route::resource('dicipline', 'front_desk\dicipline\diciplineController');
	Route::resource('dicipline_report', 'front_desk\dicipline_report\dicipline_reportController');
	Route::resource('photo_video_gallary', 'front_desk\photo_video_gallary\photo_video_gallaryController');
	Route::resource('exam_schedule', 'front_desk\exam_schedule\exam_scheduleController');

	Route::POST('search_by_circular_title', 'front_desk\circular\circularController@searchCircularTitle')->name("search_by_circular_title");

});
Route::POST('front_desk/leave_application/add_leave_application', 'front_desk\leave_application\leaveApplicationController@add_leave');
Route::POST('circular/fetchData', 'front_desk\circular\circularController@fetchData');
Route::POST('circular/TeacherFetchData', 'front_desk\circular\circularController@TeacherFetchData');
Route::GET('homework/fetchData', 'student\studentHomeworkController@fetchData');
Route::GET('photo_video_gallary/fetchData', 'front_desk\photo_video_gallary\photo_video_gallaryController@fetchData');
Route::POST('photo_video_gallary/TeacherFetchData', 'front_desk\photo_video_gallary\photo_video_gallaryController@TeacherFetchData');
Route::POST('/notificationHubAPI', 'student\tblstudentController@notificationHubAPI');
Route::POST('/teacherAnnoucementAPI', 'student\tblstudentController@teacherAnnoucementAPI');
Route::POST('/teacherHomeworkAssignmentAPI', 'student\studentHomeworkController@teacherHomeworkAssignmentAPI');
Route::POST('/studentHomeworkAssignmentAPI', 'student\studentHomeworkController@studentHomeworkAssignmentAPI');
Route::POST('/add_communicationAPI', 'front_desk\parentCommunication\parentCommunicationController@add_communicationAPI');

Route::POST('/studentSubjectAPI', 'student\studentHomeworkController@studentSubjectAPI');

Route::POST('/teacherStudentListAPI', 'student\tblstudentController@teacherStudentListAPI');
Route::POST('/allStudentListAPI', 'student\tblstudentController@allStudentListAPI');
Route::POST('/teacherParentcommunicationListAPI', 'front_desk\parentCommunication\parentCommunicationController@teacherParentcommunicationListAPI');
Route::POST('/teacherParentcommunicationSaveAPI', 'front_desk\parentCommunication\parentCommunicationController@teacherParentcommunicationSaveAPI');
Route::POST('/studentParentcommunicationListAPI', 'front_desk\parentCommunication\parentCommunicationController@studentParentcommunicationListAPI');

Route::GET('ajax_checkEmailExist', 'student\tblstudentController@ajax_checkEmailExist')->name('ajax_checkEmailExist');
Route::GET('ajax_checkDivisionCapacity', 'student\tblstudentController@ajax_checkDivisionCapacity')->name('ajax_checkDivisionCapacity');
Route::GET('ajax_StatewiseCity','student\tblstudentController@ajax_StatewiseCity')->name('ajax_StatewiseCity');
// Route::POST('front_desk/leave_application/add_leave_application', function(){
//     echo "asds";
// });

?>