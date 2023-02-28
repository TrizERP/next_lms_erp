<?php

use App\Http\Controllers\api\adminapiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::controller(adminapiController::class)->group(function () {
    Route::post('admin_login', 'admin_login');
    Route::post('admin_check_otp', 'admin_check_otp');
    Route::post('get_Syear', 'get_Syear');

    Route::post('get_adminAcademicSection', 'get_adminAcademicSection');

    Route::post('get_adminStandard', 'get_adminStandard');

    Route::post('get_adminDivision', 'get_adminDivision');

    Route::post('get_adminSubject', 'get_adminSubject');

    Route::post('get_adminStudentList', 'get_adminStudentList');

    Route::post('get_adminTeacherList', 'get_adminTeacherList');

    Route::post('get_adminLeaveApplicationListAPI', 'get_adminLeaveApplicationListAPI');

    Route::post('add_adminLeaveApplicationSaveAPI', 'add_adminLeaveApplicationSaveAPI');

    Route::post('get_adminParentCommunicationListAPI', 'get_adminParentCommunicationListAPI');

    Route::post('add_adminParentCommunicationSaveAPI', 'add_adminParentCommunicationSaveAPI');

    Route::post('get_adminPhotoVideoAPI', 'get_adminPhotoVideoAPI');

    Route::post('add_adminPhotoVideoAPI', 'add_adminPhotoVideoAPI');

    Route::post('get_adminPTMBookingTodaysListAPI', 'get_adminPTMBookingTodaysListAPI');

    Route::post('get_adminVisitorListAPI', 'get_adminVisitorListAPI');

    Route::post('get_adminTodaysProxyListAPI', 'get_adminTodaysProxyListAPI');

    Route::post('add_adminSendSmsAPI', 'add_adminSendSmsAPI');

    Route::post('add_adminSendEmailAPI', 'add_adminSendEmailAPI');

    Route::post('get_attendanceGraphAPI', 'get_attendanceGraphAPI');

    Route::post('get_feesGraphAPI', 'get_feesGraphAPI');

    Route::post('get_taskAPI', 'get_taskAPI');

    Route::post('add_taskAPI', 'add_taskAPI');

    Route::post('get_complaintAPI', 'get_complaintAPI');

    Route::post('add_complaintAPI', 'add_complaintAPI');

    Route::post('get_adminAllRequisitionAPI', 'get_adminAllRequisitionAPI');

    Route::post('get_adminRequisitionByListAPI', 'get_adminRequisitionByListAPI');

    Route::post('get_adminItemListAPI', 'get_adminItemListAPI');

    Route::post('add_adminRequisitionAPI', 'add_adminRequisitionAPI');

    Route::post('get_RequisitionStatusAPI', 'get_RequisitionStatusAPI');

    Route::post('adminApprovedRequisitionAPI', 'adminApprovedRequisitionAPI');

    Route::post('add_adminCircularAPI', 'add_adminCircularAPI');

    Route::post('get_adminCircularAPI', 'get_adminCircularAPI');

    Route::post('add_SendNotificationAPI', 'add_SendNotificationAPI');

    Route::post('get_wrtreportAPI', 'get_wrtreportAPI');

    Route::post('add_studentCapturePhotosAPI', 'add_studentCapturePhotosAPI');

    Route::post('add_studentCaptureAttendanceAPI', 'add_studentCaptureAttendanceAPI');

    Route::post('get_attendanceDataAPI', 'get_attendanceDataAPI');

    Route::post('get_studentCapturePhotosAPI', 'get_studentCapturePhotosAPI');
});
