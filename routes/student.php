<?php

use App\Http\Controllers\easy_com\send_email_parents\send_email_parents_controller;
use App\Http\Controllers\easy_com\send_sms_parents\send_sms_parents_controller;
use App\Http\Controllers\front_desk\circular\circularController;
use App\Http\Controllers\front_desk\dicipline\diciplineController;
use App\Http\Controllers\front_desk\dicipline_report\dicipline_reportController;
use App\Http\Controllers\front_desk\exam_schedule\exam_scheduleController;
use App\Http\Controllers\front_desk\leave_application\leaveApplicationController;
use App\Http\Controllers\front_desk\parentCommunication\parentCommunicationController;
use App\Http\Controllers\front_desk\photo_video_gallary\photo_video_gallaryController;
use App\Http\Controllers\front_desk\school_detail\schooldetailController;
use App\Http\Controllers\hostel_management\tblhostelRoomAllocationController;
use App\Http\Controllers\student\bulkStudentController;
use App\Http\Controllers\student\studentStrengthReportController;
use App\Http\Controllers\student\fees_graph\student_fees_graphController;
use App\Http\Controllers\student\graph_attendance\student_graph_attendanceController;
use App\Http\Controllers\student\houseAutomationController;
use App\Http\Controllers\student\houseController;
use App\Http\Controllers\student\InactiveStudentReportController;
use App\Http\Controllers\student\missingDocumentReportController;
use App\Http\Controllers\student\rollOverController;
use App\Http\Controllers\student\student_certificate_reportController;
use App\Http\Controllers\student\studentAttendanceController;
use App\Http\Controllers\student\studentCertificateController;
use App\Http\Controllers\student\studentChangeRequestTypeController;
use App\Http\Controllers\student\studentHealthController;
use App\Http\Controllers\student\studentHomeworkController;
use App\Http\Controllers\student\studentHomeworkSubmissionController;
use App\Http\Controllers\student\studentHWController;
use App\Http\Controllers\student\studentIcardController;
use App\Http\Controllers\student\studentInfirmaryController;
use App\Http\Controllers\student\studentQuotaController;
use App\Http\Controllers\student\studentReportController;
use App\Http\Controllers\student\studentRequestController;
use App\Http\Controllers\student\studentRequestReportController;
use App\Http\Controllers\student\studentSearchController;
use App\Http\Controllers\student\studentTransferController;
use App\Http\Controllers\student\studentVaccinationController;
use App\Http\Controllers\student\tblstudentController;
use App\Http\Controllers\student\tblstudentDocumentController;
use App\Http\Controllers\student\tblstudentFamilyHistoryController;
use App\Http\Controllers\student\tblstudentFeesDetailController;
use App\Http\Controllers\student\tblstudentParentFeedbackController;
use App\Http\Controllers\student\tblstudentPastEducationController;
use App\Http\Controllers\student\tblstudentTcController;
use App\Http\Controllers\student\teacherIcardController;
use App\Http\Controllers\student\transferStudentController;
use App\Http\Controllers\student\studentBulkUpdateController;
use App\Http\Controllers\student\studentOptionalSubjectController;
use App\Http\Controllers\student\studentAnacdotalController;
use Illuminate\Support\Facades\Route;


Route::group(['prefix' => 'student', 'middleware' => ['session', 'menu', 'logRoute','check_permissions']], function () {
    Route::resource('add_student', tblstudentController::class);
    Route::resource('add_house', houseController::class);
    Route::resource('past_education', tblstudentPastEducationController::class);
    Route::resource('student_document', tblstudentDocumentController::class);
    Route::resource('student_infirmary', studentInfirmaryController::class);
    Route::resource('student_vaccination', studentVaccinationController::class);
    Route::resource('student_hw', studentHWController::class);
    Route::resource('student_health', studentHealthController::class);
    Route::resource('family_history', tblstudentFamilyHistoryController::class);
    Route::resource('parent_feedback', tblstudentParentFeedbackController::class);
    Route::resource('search_student', studentSearchController::class);
    Route::resource('student_report', studentReportController::class);
    Route::resource('bulk_student_update', bulkStudentController::class);
    Route::resource('transfer_student', transferStudentController::class);
    Route::resource('student_quota', studentQuotaController::class);
    Route::resource('student_icard', studentIcardController::class);
    Route::resource('teacher_icard', teacherIcardController::class);
    Route::resource('student_certificate', studentCertificateController::class);
    Route::resource('student_certificate_report', student_certificate_reportController::class);
    Route::resource('student_request', studentRequestController::class);
    Route::resource('student_strength_report', studentStrengthReportController::class);
    Route::resource('student_request_report', studentRequestReportController::class);
    Route::resource('student_attendance', studentAttendanceController::class);
    Route::resource('student_graph_attendance', student_graph_attendanceController::class);
    Route::resource('student_fees_graph', student_fees_graphController::class);
    Route::resource('student_homework', studentHomeworkController::class);
    Route::resource('student_homework_submission', studentHomeworkSubmissionController::class);
    Route::resource('student_fees_detail', tblstudentFeesDetailController::class);
    Route::resource('student_tc_detail', tblstudentTcController::class);
    Route::resource('Transport', TransportController::class);
    Route::resource('student_transfer', studentTransferController::class);
    Route::resource('rollover', rollOverController::class);
    Route::resource('house_automation', houseAutomationController::class);
    Route::resource('student_bulk_update', studentBulkUpdateController::class);
    Route::resource('student_optional_subject', studentOptionalSubjectController::class);
    Route::resource('anacdotal', studentAnacdotalController::class);

    Route::get('selected_student_view', [rollOverController::class, 'selected_student_view'])->name("selected_student_view");

    Route::post('ajax_toAcademicSections', [studentTransferController::class, 'ajax_toAcademicSections'])->name('ajax_toAcademicSections');
    Route::post('ajax_toStandards', [studentTransferController::class, 'ajax_toStandards'])->name('ajax_toStandards');
    Route::post('ajax_toDivisions', [studentTransferController::class, 'ajax_toDivisions'])->name('ajax_toDivisions');

    Route::get('student_homework_report_index', [studentHomeworkController::class, 'studentHomeworkReportIndex'])->name("student_homework_report_index");
    Route::post('delete-selected-students', [studentHomeworkController::class, 'multipleDelete'])->name('delete_selected_students');

    Route::post('student_homework_report', [studentHomeworkController::class, 'studentHomeworkReport'])->name("student_homework_report");
    Route::get('student_homework_submission_report_index', [studentHomeworkSubmissionController::class, 'studentHomeworkSubmissionReportIndex'])->name("student_homework_submission_report_index");

    Route::post('student_homework_submission_report', [studentHomeworkSubmissionController::class, 'studentHomeworkSubmissionReport'])->name("student_homework_submission_report");

    Route::resource('student_change_request_type', studentChangeRequestTypeController::class);
    Route::post('show_search_student', [studentSearchController::class, 'searchStudent'])->name("show_search_student");
    Route::post('show_search_student_optional_subject', [studentOptionalSubjectController::class, 'searchStudentOptionalSubject'])->name("show_search_student_optional_subject");
    //Route::post('add_student_optional_subject', [studentOptionalSubjectController::class, 'addStudentOptionalSubject'])->name('add_student_optional_subject');
    Route::post('show_student_report', [studentReportController::class, 'searchStudent'])->name("show_student_report");
    Route::resource('missing_document_report', missingDocumentReportController::class);
    Route::resource('inactive_student_report', InactiveStudentReportController::class);
    Route::post('show_bulk_student', [bulkStudentController::class, 'searchStudent'])->name("show_bulk_student");
    Route::post('show_student', [transferStudentController::class, 'searchStudent'])->name("show_student");
    Route::post('bulk_update', [bulkStudentController::class, 'bulkUpdate'])->name("bulk_update");
    Route::post('transfer_student', [transferStudentController::class, 'transferStudent'])->name("transfer_student");
    Route::post('show_student_attendance', [studentAttendanceController::class, 'showStudent'])->name("show_student_attendance");
    Route::post('save_student_attendance', [studentAttendanceController::class, 'saveStudentAttendance'])->name("save_student_attendance");
    Route::get('daywise_student_attendance', [studentAttendanceController::class, 'daywiseStudentAttendance'])->name("daywise_student_attendance_report");
    Route::post('show_daywise_student_attendance', [studentAttendanceController::class, 'showDaywiseStudentAttendance'])->name("show_daywise_student_attendance_report");
    // 2024-11-11
    Route::get('studentAttendanceChatAPI', [studentAttendanceController::class, 'studentAttendanceChatAPI'])->name("studentAttendanceChatAPI");

    Route::get('student_health_report', [studentInfirmaryController::class, 'studentHealthReport'])->name("student_health_report");
    Route::post('show_student_health_report', [studentInfirmaryController::class, 'showStudentHealthReport'])->name("show_student_health_report");

    Route::get('monthwise_student_attendance', [studentAttendanceController::class, 'monthwiseStudentAttendance'])->name("monthwise_student_attendance_report");
    Route::post('show_monthwise_student_attendance', [studentAttendanceController::class, 'showMonthwiseStudentAttendance'])->name("show_monthwise_student_attendance_report");

    Route::get('yearly_student_attendance', 'student\yearly_attendance_controller@index')->name("yearly_student_attendance");
    Route::post('yearly_student_attendance', 'student\yearly_attendance_controller@showYearlyStudentAttendance')->name("show_yearly_student_attendance");

    Route::post('search_student_by_lastname', [studentSearchController::class, 'searchStudentLastName'])->name("search_student_by_lastname");
    Route::post('search_student_by_firstname', [studentSearchController::class, 'searchStudentFirstName'])->name("search_student_by_firstname");
    Route::post('search_student_name', [studentSearchController::class, 'searchStudentName'])->name("search_student_name");
    Route::post('search_student_id', [studentSearchController::class, 'searchStudentId'])->name("search_student_id");
    Route::post('add_student_siblings', [studentSearchController::class, 'addStudentSiblings'])->name("add_student_siblings");
    //Route::post('display_student_siblings', 'student\studentSearchController@displayStudentSiblings')->name("display_student_siblings");

    Route::post('student_icard/show_student', ['as' => 'student_icard.show_student', 'uses' => 'student\studentIcardController@showStudent']);
    Route::post('teacher_icard/show_teacher', ['as' => 'teacher_icard.show_teacher', 'uses' => 'student\teacherIcardController@showTeacher']);

    Route::get('view_samples', [studentIcardController::class, 'viewSamples'])->name("view_samples");
    Route::get('view_samples_user', [teacherIcardController::class, 'viewSamples'])->name("view_samples_user");

    Route::post('student_icard/show_student_icard', [studentIcardController::class, 'showStudentIcard'])->name('show_student_icard');
    Route::post('teacher_icard/show_teacher_icard', [teacherIcardController::class, 'showTeacherIcard'])->name('show_teacher_icard');

    Route::post('student_certificate/show_student', ['as' => 'student_certificate.show_student', 'uses' => 'student\studentCertificateController@showStudent']);

    Route::post('student_certificate/show_student_certificate', [studentCertificateController::class, 'showStudentCertificate'])->name('show_student_certificate');

    Route::get('ajax_getHomeworkSubjects', [studentHomeworkController::class, 'ajax_getHomeworkSubjects'])->name('ajax_getHomeworkSubjects');

});
Route::get('ajax_getBatch', [tblstudentController::class, 'ajax_getBatch'])->name('ajax_getBatch');
Route::get('ajax_getOptionalSubject', [tblstudentController::class, 'ajax_getOptionalSubject'])->name('ajax_getOptionalSubject');

Route::get('ajax_saveData', 'student\studentCertificateController@ajax_saveData')->name('ajax_saveData');

Route::post('/studentAttendanceAPI', [studentAttendanceController::class, 'studentAttendanceAPI']);
Route::post('/studentTeacherListAPI', [studentAttendanceController::Class, 'studentTeacherListAPI']);
Route::post('/studentHealthAPI', [studentHealthController::class, 'studentHealthAPI']);
Route::post('/studentExamScheduleAPI', [exam_scheduleController::class, 'studentExamScheduleAPI']);
Route::post('/studentDisciplineAPI', [diciplineController::class, 'studentDisciplineAPI']);
Route::post('/schoolDetailAPI', [schooldetailController::class, 'schoolDetailAPI']);
Route::post('/studentPhotoVideoGalleryAPI', [photo_video_gallaryController::class, 'studentPhotoVideoGalleryAPI']);
Route::post('/studentLeaveApplicationAPI', [leaveApplicationController::class, 'studentLeaveApplicationAPI']);
Route::post('/studentHostelAllocationAPI', [tblhostelRoomAllocationController::class, 'studentHostelAllocationAPI']);
Route::post('/studentCertificateAPI', [studentCertificateController::class, 'studentCertificateAPI']);
Route::post('/teacherSendSmsParentsAPI', [send_sms_parents_controller::class, 'teacherSendSmsParentsAPI']);
Route::post('/teacherSendEmailParentsAPI', [send_email_parents_controller::class, 'sendEmail']);

Route::post('/teacherLeaveApplicationListAPI', [leaveApplicationController::class, 'teacherLeaveApplicationListAPI']);
Route::post('/teacherLeaveApplicationSaveAPI', [leaveApplicationController::class, 'teacherLeaveApplicationSaveAPI']);

Route::post('/studentInfirmaryAPI', [studentInfirmaryController::class, 'studentInfirmaryAPI']);
Route::post('/studentVaccinationAPI', [studentVaccinationController::class, 'studentVaccinationAPI']);
Route::post('/studentHWAPI', [studentHWController::class, 'studentHWAPI']);

Route::post('under_development', [studentReportController::class, 'underDevelopment'])->name("under_development")->middleware(['session', 'menu', 'logRoute']);
Route::post('under_development_master', [studentReportController::class, 'underDevelopment'])->name("under_development_master")->middleware(['session', 'mastersetup_menu', 'logRoute']);
Route::post('firstpage_school', [studentReportController::class, 'firstpage_school'])->name("firstpage_school")->middleware(['session', 'menu', 'logRoute']);
Route::post('firstpage_student', [studentReportController::class, 'firstpage_student'])->name("firstpage_student")->middleware(['session', 'menu', 'logRoute']);
Route::post('firstpage_teacher', [studentReportController::class, 'firstpage_teacher'])->name("firstpage_teacher")->middleware(['session', 'menu', 'logRoute']);

Route::group(['prefix' => 'front_desk', 'middleware' => ['session', 'menu', 'logRoute']], function () {
    Route::resource('parent_communication', parentCommunicationController::class);
    Route::resource('leave_application', leaveApplicationController::class);
    Route::resource('circular', circularController::class);
    Route::resource('dicipline', diciplineController::class);
    Route::resource('dicipline_report', dicipline_reportController::class);
    Route::resource('photo_video_gallary', photo_video_gallaryController::class);
    Route::resource('exam_schedule', exam_scheduleController::class);

    Route::post('search_by_circular_title', [circularController::class, 'searchCircularTitle'])->name("search_by_circular_title");

});
Route::post('front_desk/leave_application/add_leave_application', [leaveApplicationController::class, 'add_leave']);
Route::post('circular/fetchData', [circularController::class, 'fetchData']);
Route::post('circular/TeacherFetchData', [circularController::class, 'TeacherFetchData']);
Route::post('homework/fetchData', [studentHomeworkController::class, 'fetchData']);
Route::post('photo_video_gallary/fetchData', [photo_video_gallaryController::class, 'fetchData']);
Route::post('photo_video_gallary/TeacherFetchData', [photo_video_gallaryController::class, 'TeacherFetchData']);
Route::post('/notificationHubAPI', [tblstudentController::class, 'notificationHubAPI']);
Route::post('/teacherAnnoucementAPI', [tblstudentController::class, 'teacherAnnoucementAPI']);
Route::post('/teacherHomeworkAssignmentAPI', [studentHomeworkController::class, 'teacherHomeworkAssignmentAPI']);
Route::post('/studentHomeworkAssignmentAPI', [studentHomeworkController::class, 'studentHomeworkAssignmentAPI']);
Route::post('/add_communicationAPI', [parentCommunicationController::class, 'add_communicationAPI']);

Route::post('/studentSubjectAPI', [studentHomeworkController::class, 'studentSubjectAPI']);

Route::post('/teacherStudentListAPI', [tblstudentController::class, 'teacherStudentListAPI']);
Route::post('/allStudentListAPI', [tblstudentController::class, 'allStudentListAPI']);
Route::post('/teacherParentcommunicationListAPI', [parentCommunicationController::class, 'teacherParentcommunicationListAPI']);
Route::post('/teacherParentcommunicationSaveAPI', [parentCommunicationController::class, 'teacherParentcommunicationSaveAPI']);
Route::post('/studentParentcommunicationListAPI', [parentCommunicationController::class, 'studentParentcommunicationListAPI']);

Route::get('ajax_checkEmailExist', [tblstudentController::Class, 'ajax_checkEmailExist'])->name('ajax_checkEmailExist');
Route::get('ajax_checkDivisionCapacity', [tblstudentController::class, 'ajax_checkDivisionCapacity'])->name('ajax_checkDivisionCapacity');
Route::get('ajax_StatewiseCity', [tblstudentController::class, 'ajax_StatewiseCity'])->name('ajax_StatewiseCity');
Route::get('get_batch', [studentAttendanceController::class, 'get_batch'])->name('get_batch');

Route::get('document_details', [studentTransferController::class, 'DocumentTypeDetails'])->name('document_details');

// url with parameter http://127.0.0.1:8000/add_students?sub_institute_id=257&syear=2024&type=web
Route::get('add_students', [tblstudentController::class,'index'])->name('add_students.index');
Route::post('add_students/store', [tblstudentController::class,'store'])->name('add_students.store');
Route::get('checkExists', [tblstudentController::class,'checkExists'])->name('checkExists');
// Route::post('front_desk/leave_application/add_leave_application', function(){
//     echo "asds";
// });
