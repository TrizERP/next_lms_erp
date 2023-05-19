<?php

use App\Http\Controllers\AJAXController;
use App\Http\Controllers\api\NewLMS_ApiController;
use App\Http\Controllers\api\NewLMS_StudentApiController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\CkeditorFileUploadController;
use App\Http\Controllers\dashboardController;
use App\Http\Controllers\easy_com\send_birthday_notification\send_birthday_notification_controller;
use App\Http\Controllers\loginController;
use App\Http\Controllers\school_setup\batchController;
use App\Http\Controllers\school_setup\changePasswordController;
use App\Http\Controllers\school_setup\chapterController;
use App\Http\Controllers\school_setup\classteacherController;
use App\Http\Controllers\school_setup\classteacherReportController;
use App\Http\Controllers\school_setup\classwisetimetableController;
use App\Http\Controllers\school_setup\dashboardSettingController;
use App\Http\Controllers\school_setup\divisionCapacityMasterController;
use App\Http\Controllers\school_setup\erpstatusController;
use App\Http\Controllers\school_setup\facultywisetimetableController;
use App\Http\Controllers\school_setup\lessonplanningController;
use App\Http\Controllers\school_setup\lessonplanningReportController;
use App\Http\Controllers\school_setup\periodController;
use App\Http\Controllers\school_setup\proxyController;
use App\Http\Controllers\school_setup\proxyReportController;
use App\Http\Controllers\school_setup\schoolController;
use App\Http\Controllers\school_setup\std_divController;
use App\Http\Controllers\school_setup\sub_std_mapController;
use App\Http\Controllers\school_setup\subject1Controller;
use App\Http\Controllers\school_setup\teacherdailyReportController;
use App\Http\Controllers\school_setup\teachertransferController;
use App\Http\Controllers\school_setup\timetableController;
use App\Http\Controllers\school_setup\todaysproxyReportController;
use App\Http\Controllers\school_setup\topicController;
use App\Http\Controllers\school_setup\workflowController;
use App\Http\Controllers\signupController;
use App\Http\Controllers\lms\questionWiseReportController;
use App\Http\Controllers\template_result\TemplateResult;
use App\Http\Controllers\tourController;
use App\Http\Controllers\used_storage_graphController;
use App\Http\Controllers\UserFormbuilderController;
use App\Models\general_dataModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Import\ImportController;
use App\Http\Controllers\leave\HolidayController;
use App\Http\Controllers\leave\LeaveController;
use App\Http\Controllers\leave\LeaveTypeController;
use App\Http\Controllers\Payroll\PayrollController;
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

if (isset($_REQUEST['sub_institute_id']) && $_REQUEST['sub_institute_id'] != '') {
    $sub_institute_id = $_REQUEST['sub_institute_id'];

    $get_general_data = general_dataModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

    if (count($get_general_data) > 0) {
        $loginpage_link = $get_general_data[0]['fieldvalue'];
        $loginpage_logo = $get_general_data[1]['fieldvalue'];
        $loginpage_title = $get_general_data[2]['fieldvalue'];
        $loginpage_description = $get_general_data[3]['fieldvalue'];
        $loginpage_favicon = $get_general_data[4]['fieldvalue'];
        $loginpage_backgrond = $get_general_data[5]['fieldvalue'];

        session(['loginpage_link' => $loginpage_link]);
        session(['loginpage_logo' => $loginpage_logo]);
        session(['loginpage_title' => $loginpage_title]);
        session(['loginpage_description' => $loginpage_description]);
        session(['loginpage_favicon' => $loginpage_favicon]);
        session(['loginpage_backgrond' => $loginpage_backgrond]);
    }
} else {
    Route::get('/', function (Request $request) {
        $user_id = $request->session()->get('user_id');

        if (!empty($user_id)) {
            return redirect()->route('dashboard');
        } else {
            return view('login');
        }
    })->name('home');
    // echo 'aaaa';die;
}

//PAYROLL SYSTEM
Route::group([ 'middleware' => ['session', 'menu', 'logRoute']], function () {
    Route::get('/payroll-type', [PayrollController::class, 'payrollType']);
    Route::get('/payroll-type/create', [PayrollController::class, 'payrollCreate'])->name('payroll_type.create');
    Route::post('/payroll-type/store', [PayrollController::class, 'payrollStore'])->name('payroll_type.store');
    Route::get('/payroll-type/create/{id}', [PayrollController::class, 'payrollCreate']);
    Route::post('/payroll-type/destroy', [PayrollController::class, 'payrollDestroy'])->name('payroll_type.destroy');
});


Route::get('/import-data',[ImportController::class,'getImport']);
Route::post('/import_parse', [ImportController::class,'parseImport'])->name('import_parse');
Route::post('/import_parse_fields', [ImportController::class,'matchFields'])->name('update.match_fields');
Route::post('/import_process', [ImportController::class,'processImport'])->name('import_process');

Route::any('/knowledge-base', [dashboardController::class, 'knowledge_base'])->name('knowledge_base')->middleware('session', 'menu');

Route::any('/knowledge-base-detail/{id}/{title}', [dashboardController::class, 'knowledge_base_detail'])->name('knowledge_base_detail')->middleware('session', 'menu');

Route::get('dashboard', [dashboardController::class, 'index'])->name('dashboard')->middleware('session', 'menu', 'logRoute');
// From Build
Route::get('formbuilder/list', [UserFormbuilderController::class, 'index'])->name('formbuild.list')->middleware('session', 'menu', 'logRoute');
Route::get('formbuilder/create', [UserFormbuilderController::class, 'formbuilder'])->name('formbuild.create')->middleware('session', 'menu', 'logRoute');
Route::post('saveformbuild/{id?}', [UserFormbuilderController::class, 'saveformbuilder'])->name('saveformbuild')->middleware('session', 'menu', 'logRoute');
Route::get('formbuilder/edit/{id?}', [UserFormbuilderController::class, 'editformbuilder'])->name('formbuild.edit')->middleware('session', 'menu', 'logRoute');
Route::get('formbuilder/delete/{id?}', [UserFormbuilderController::class, 'deleteformbuilder'])->name('formbuild.delete')->middleware('session', 'menu', 'logRoute');
Route::get('getformbuilder/{name?}', [UserFormbuilderController::class, 'getformbuilder'])->name('getformbuild');
Route::get('apiGetformbuilder/{name?}', [UserFormbuilderController::class, 'Apigetformbuilder'])->name('getformbuild');

# submit form
Route::post('submit_form_data', [UserFormbuilderController::class, 'submitFrom'])->name('submit_form_data');

# view form
Route::get('view_form/{id}/{chapter_id?}', [UserFormbuilderController::class, 'viewForm'])->name('view_form');

# Display form view table
Route::get('displayForm', [UserFormbuilderController::class, 'displayFormDataRecord']);

//End From Build

Route::get('chart_dashboard', [dashboardController::class, 'chart'])->name('chart_dashboard')->middleware('session', 'menu', 'logRoute');

Route::get('schoolList', [dashboardController::class, 'schoolList'])->name('schoolList');

Route::get('siteMap', [dashboardController::class, 'siteMap'])->name('siteMap')->middleware('session', 'menu', 'logRoute');

Route::any('login', [loginController::class, 'index'])->name('login');
Route::any('signup', [signupController::class, 'index'])->name('signup');
//Route::get('aftersignuplogin', 'loginController@aftersignuplogin');

Route::any('ajaxMenuSession', [loginController::class, 'ajaxMenuSession'])->name('ajaxMenuSession');

Route::any('/logout', [loginController::class, 'logout']);

Route::any('/profileAPI', [loginController::class, 'profileAPI']);

Route::any('/tourUpdate', [tourController::class, 'index'])->name('tourUpdate');

Route::get('/implementation', [tourController::class, 'implementation'])->name('implementation')->middleware('session', 'menu', 'logRoute');
Route::get('/implementation_1', [tourController::class, 'implementation_1'])->name('implementation_1')->middleware('session', 'menu', 'logRoute');
Route::get('/implementation_2', [tourController::class, 'implementation_2'])->name('implementation_2')->middleware('session', 'menu', 'logRoute');
Route::get('/skip_implementation', [tourController::class, 'skipImplementation'])->name('skip_implementation')->middleware('session', 'menu', 'logRoute');

Route::get('ajax_SaveDynamicDashboardMenu', [dashboardSettingController::class, 'ajax_SaveDynamicDashboardMenu'])->name('ajax_SaveDynamicDashboardMenu');

// Harshad Start
Route::group(['prefix' => 'student', 'middleware' => ['session', 'menu', 'logRoute']], function () {
    Route::get('studentresult', [TemplateResult::class, 'index']);
    Route::post('studentresult_show', [TemplateResult::class, 'show_result'])->name('studentresult.show');
    Route::get('result_show/{arr?}', [TemplateResult::class, 'result_show'])->name('result.show');
});
// Harshad End


Route::group(['prefix' => 'school_setup', 'middleware' => ['session', 'menu', 'logRoute']], function () {
    Route::resource('add_school', schoolController::class);
    Route::resource('std_div_map', std_divController::class);
    Route::resource('division_capacity_master', divisionCapacityMasterController::class);
    Route::resource('subject_master', subject1Controller::class);
    Route::resource('chapter_master', chapterController::class);
    Route::get('ajax_StandardwiseSubject', [chapterController::class, 'StandardwiseSubject'])->name('ajax_StandardwiseSubject');
    Route::resource('topic_master', topicController::class);
    Route::resource('sub_std_map', sub_std_mapController::class);
    Route::resource('period_master', periodController::class);
    Route::resource('change_password', changePasswordController::class);
    Route::resource('dashboard_setting', dashboardSettingController::class);
    Route::get('device_check', [changePasswordController::class, 'device_check'])->name('device_check');

    Route::resource('erp_status', erpstatusController::class);
    Route::resource('workflow', workflowController::class);
    Route::get('ajax_wk_modulewise_fields', [workflowController::class, 'wk_modulewise_fields'])->name('ajax_wk_modulewise_fields');
    Route::post('ajax_wk_savemail', [workflowController::class, 'wk_savemail'])->name('ajax_wk_savemail');
    Route::post('ajax_wk_saveupdatequery', [workflowController::class, 'wk_saveupdatequery'])->name('ajax_wk_saveupdatequery');
    Route::post('ajax_wk_savesms', [workflowController::class, 'wk_savesms'])->name('ajax_wk_savesms');

    Route::resource('batch_master', batchController::class);
    Route::post('ajaxdestroybatch_master', [batchController::class, 'ajaxdestroy'])->name('ajaxdestroybatch_master');
    Route::get('ajax_StandardwiseDivision', [batchController::class, 'StandardwiseDivision'])->name('ajax_StandardwiseDivision');

    Route::resource('timetable', timetableController::class);
    Route::get('ajax_AcademicwiseStandard', [timetableController::class, 'AcademicwiseStandard'])->name('ajax_AcademicwiseStandard');
    Route::post('ajax_getTimetable', [timetableController::class, 'getTimetable'])->name('ajax_getTimetable');

    Route::post('insert_data', [subject1Controller::class, 'insert_data'])->name('insert_data');

    // Route::post('/school_setup/subject_master/insert_data','school_setup\subject1Controller@insert_data');

    Route::resource('classwisetimetable', classwisetimetableController::class);
    Route::post('ajax_getClasswiseTimetable', [classwisetimetableController::class, 'getClasswiseTimetable'])->name('ajax_getClasswiseTimetable');
    Route::get('ajax_Batch_Timetable', [timetableController::class, 'getBatchTimetable'])->name('ajax_Batch_Timetable');

    Route::get('ajax_New_Standard_Div', [timetableController::class, 'getNewStandardDiv'])->name('ajax_New_Standard_Div');

    Route::get('ajax_Delete_Timetable', [timetableController::class, 'deleteTimetable'])->name('ajax_Delete_Timetable');

    Route::get('ajax_Mapping_Teachers', [timetableController::class, 'getMappingTeachers'])->name('ajax_Mapping_Teachers');

    Route::resource('proxy_master', proxyController::class);

    Route::post('ajax_getproxyperiod', [proxyController::class, 'getproxyperiod'])->name('ajax_getproxyperiod');
    Route::resource('facultywisetimetable', facultywisetimetableController::class);
    Route::post('ajax_getFacultywiseTimetable', [facultywisetimetableController::class, 'getFacultywiseTimetable'])->name('ajax_getFacultywiseTimetable');

    Route::resource('proxy_report', proxyReportController::class);
    Route::post('ajax_getproxyreport', [proxyReportController::class, 'getproxyreport'])->name('ajax_getproxyreport');

    Route::resource('todays_proxy_report', todaysproxyReportController::class);

    Route::resource('classteacher', classteacherController::class);
    Route::resource('classteacherReport', classteacherReportController::class);

    Route::resource('teachertransfer', teachertransferController::class);

    Route::resource('teacher_daily_report', teacherdailyReportController::class);
    Route::post('ajax_getTeacherDailyReport', [teacherdailyReportController::class, 'getTeacherDailyReport'])->name('ajax_getTeacherDailyReport');

    Route::resource('lessonplanning', lessonplanningController::class);
    Route::get('ajax_getlp_subject', [lessonplanningController::class, 'getSubjectData'])->name('ajax_getlp_subject');
    Route::get('ajax_getlp_division', [lessonplanningController::class, 'getDivisionData'])->name('ajax_getlp_division');

    Route::resource('lessonplanningReport', lessonplanningReportController::class);

    Route::get('google-analytics-summary', array('as' => 'google-analytics-summary', 'uses' => 'school_setup\HomeController@getAnalyticsSummary'));

    Route::resource('used_storage_graph', used_storage_graphController::class);

});
Route::post('get_proxy_master', [proxyController::class, 'getproxydata']);
Route::get('school_setup/ajax_getTeacherDailyDetailsReport', [teacherdailyReportController::class, 'getTeacherDailyDetailsReport'])->name("ajax_getTeacherDailyDetailsReport");


Route::post('/teacherTimetableAPI', [facultywisetimetableController::class, 'teacherTimetableAPI']);
Route::post('/studentTimetableAPI', [classwisetimetableController::class, 'studentTimetableAPI']);

Route::get('ajax_load_rightSideMenu', [AJAXController::class, 'ajax_load_rightSideMenu'])->name('ajax_load_rightSideMenu');
Route::get('ajax_load_helpguide', [AJAXController::class, 'ajax_load_helpguide'])->name('ajax_load_helpguide');

Route::post('ajax_sendmail', [AJAXController::class, 'ajax_sendmail'])->name('ajax_sendmail');

Route::post('collectsct', [AJAXController::class, 'collectsct'])->name('collectsct');


Route::get('ckeditor/create', [CkeditorFileUploadController::class, 'create'])->name('ckeditor.create');
Route::post('ckeditor', [CkeditorFileUploadController::class, 'store'])->name('uploadimage');

// Route::get('exception/index', 'ExceptionController@index');

//Auth::routes();

// Route::get('/home', 'HomeController@index')->name('home');

// Forgot Password Routes
Route::get('forget-password', [ForgotPasswordController::class, 'showForgetPasswordForm'])->name('forget.password.get');
Route::post('forget-password', [ForgotPasswordController::class, 'submitForgetPasswordForm'])->name('forget.password.post');
Route::get('reset-password/{token}/{email}', [ForgotPasswordController::class, 'showResetPasswordForm'])->name('reset.password.get');
Route::post('reset-password', [ForgotPasswordController::class, 'submitResetPasswordForm'])->name('reset.password.post');


Route::post('NewLMS_temp_signup', [NewLMS_ApiController::class, 'NewLMS_temp_signup'])->name("NewLMS_temp_signup");
Route::post('NewLMS_signup', [NewLMS_ApiController::class, 'NewLMS_signup'])->name("NewLMS_signup");
Route::get('Resend_otp', [NewLMS_ApiController::class, 'Resend_otp'])->name("Resend_otp");

Route::post('NewLMS_temp_signup_student', [NewLMS_StudentApiController::class, 'NewLMS_temp_signup_student'])->name("NewLMS_temp_signup_student");
Route::post('NewLMS_signup_student', [NewLMS_StudentApiController::class, 'NewLMS_signup_student'])->name("NewLMS_signup_student");


Route::get('get_trizStandard', [signupController::class, 'get_trizStandard'])->name("get_trizStandard");

Route::post('searching_menu', [AJAXController::class, 'searchMenu'])->name("searching_menu");
Route::post('get_search_url', [AJAXController::class, 'get_search_url'])->name("get_search_url");


// send birthday notification
Route::get('send_birthday_notification',
    [send_birthday_notification_controller::class, 'send_birthday_notification'])->name('send_birthday_notification');

// Question Wise Report
Route::get('/questionReport', [questionWiseReportController::class, 'index'])->name('question_wise_report');
Route::post('/show_question_wise_report',
    [questionWiseReportController::class, 'show_question_wise_report'])->name('show_question_wise_report');

// Admin Authorization (Show Result)
Route::GET('result_admin_permission', 'result\result_admin_permission\ResultAdminPermissionController@index')->name('result_admin_permission');
Route::POST('show_result_admin_permission', 'result\result_admin_permission\ResultAdminPermissionController@show_result_admin_permission')->name('show_result_admin_permission');
Route::POST('allow_admin_permission', 'result\result_admin_permission\ResultAdminPermissionController@allow_admin_permission')->name('allow_admin_permission');


// create result excel
Route::get('download_create_result', 'result\MarkUploadController@index');
Route::post('generate_create_result_excel', 'result\MarkUploadController@create')->name('create-excel');
Route::get('upload_create_result', 'result\MarkUploadController@store')->name('upload_create_result');

Route::get('fetch_payment_status', 'fees\online_fees\online_fees_collect_controller@razorpay_fetch_payment_status');

Route::group(['middleware' => ['session', 'menu', 'logRoute']], function () {
    Route::resource('leave', LeaveTypeController::class);
    Route::resource('holiday', HolidayController::class);
});

