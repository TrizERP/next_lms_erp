<?php

use App\Models\general_dataModel;
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

if(isset($_REQUEST['sub_institute_id']) && $_REQUEST['sub_institute_id'] != '')
{
    $sub_institute_id = $_REQUEST['sub_institute_id'];

    $get_general_data = general_dataModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

    if(count($get_general_data) > 0)
    {
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
}else{
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

Route::any('/knowledge-base', 'dashboardController@knowledge_base')->name('knowledge_base')->middleware('session', 'menu');

Route::any('/knowledge-base-detail/{id}/{title}', 'dashboardController@knowledge_base_detail')->name('knowledge_base_detail')->middleware('session', 'menu');

Route::get('dashboard', 'dashboardController@index')->name('dashboard')->middleware('session', 'menu','logRoute');
// From Build
Route::get('formbuilder/list', 'UserFormbuilderController@index')->name('formbuild.list')->middleware('session', 'menu','logRoute');
Route::get('formbuilder/create', 'UserFormbuilderController@formbuilder')->name('formbuild.create')->middleware('session', 'menu','logRoute');
Route::post('saveformbuild/{id?}', 'UserFormbuilderController@saveformbuilder')->name('saveformbuild')->middleware('session', 'menu','logRoute');
Route::get('formbuilder/edit/{id?}', 'UserFormbuilderController@editformbuilder')->name('formbuild.edit')->middleware('session', 'menu','logRoute');
Route::get('formbuilder/delete/{id?}', 'UserFormbuilderController@deleteformbuilder')->name('formbuild.delete')->middleware('session', 'menu','logRoute');
Route::get('getformbuilder/{name?}', 'UserFormbuilderController@getformbuilder')->name('getformbuild');
Route::get('apiGetformbuilder/{name?}', 'UserFormbuilderController@Apigetformbuilder')->name('getformbuild');

# submit form
Route::post('submit_form_data', 'UserFormbuilderController@submitFrom')->name('submit_form_data');

# view form
Route::get('view_form/{id}/{chapter_id?}', 'UserFormbuilderController@viewForm')->name('view_form');

# Display form view table
Route::get('displayForm', 'UserFormbuilderController@displayFormDataRecord');

//End From Build

Route::get('chart_dashboard', 'dashboardController@chart')->name('chart_dashboard')->middleware('session', 'menu','logRoute');

Route::get('schoolList', 'dashboardController@schoolList')->name('schoolList');

Route::get('siteMap', 'dashboardController@siteMap')->name('siteMap')->middleware('session', 'menu','logRoute');

Route::any('login', 'loginController@index')->name('login');
Route::any('signup', 'signupController@index')->name('signup');
//Route::get('aftersignuplogin', 'loginController@aftersignuplogin');

Route::any('ajaxMenuSession', 'loginController@ajaxMenuSession')->name('ajaxMenuSession');

Route::any('/logout', 'loginController@logout');

Route::any('/profileAPI', 'loginController@profileAPI');

Route::any('/tourUpdate', 'tourController@index')->name('tourUpdate');

Route::get('/implementation', 'tourController@implementation')->name('implementation')->middleware('session', 'menu','logRoute');
Route::get('/implementation_1', 'tourController@implementation_1')->name('implementation_1')->middleware('session', 'menu','logRoute');
Route::get('/implementation_2', 'tourController@implementation_2')->name('implementation_2')->middleware('session', 'menu','logRoute');
Route::get('/skip_implementation', 'tourController@skipImplementation')->name('skip_implementation')->middleware('session', 'menu','logRoute');

Route::get('ajax_SaveDynamicDashboardMenu', 'school_setup\dashboardSettingController@ajax_SaveDynamicDashboardMenu')->name('ajax_SaveDynamicDashboardMenu');

// Harshad Start
Route::group(['prefix' => 'student', 'middleware' => ['session', 'menu','logRoute']], function () {
    Route::get('studentresult', 'template_result\TemplateResult@index');
    Route::post('studentresult_show', 'template_result\TemplateResult@show_result')->name('studentresult.show');
    Route::get('result_show/{arr?}', 'template_result\TemplateResult@result_show')->name('result.show');
});
// Harshad End


Route::group(['prefix' => 'school_setup', 'middleware' => ['session', 'menu','logRoute']], function () {
    Route::resource('add_school', 'school_setup\schoolController');
    Route::resource('std_div_map', 'school_setup\std_divController');
    Route::resource('division_capacity_master', 'school_setup\divisionCapacityMasterController');
    Route::resource('subject_master', 'school_setup\subject1Controller');
    Route::resource('chapter_master', 'school_setup\chapterController');
    Route::get('ajax_StandardwiseSubject', 'school_setup\chapterController@StandardwiseSubject')->name('ajax_StandardwiseSubject');
    Route::resource('topic_master', 'school_setup\topicController');
    Route::resource('sub_std_map', 'school_setup\sub_std_mapController');
    Route::resource('period_master', 'school_setup\periodController');
    Route::resource('change_password', 'school_setup\changePasswordController');
    Route::resource('dashboard_setting', 'school_setup\dashboardSettingController');
    Route::get('device_check', 'school_setup\changePasswordController@device_check')->name('device_check');

    Route::resource('erp_status', 'school_setup\erpstatusController');
    Route::resource('workflow', 'school_setup\workflowController');
    Route::get('ajax_wk_modulewise_fields', 'school_setup\workflowController@wk_modulewise_fields')->name('ajax_wk_modulewise_fields');
    Route::post('ajax_wk_savemail', 'school_setup\workflowController@wk_savemail')->name('ajax_wk_savemail');
    Route::post('ajax_wk_saveupdatequery', 'school_setup\workflowController@wk_saveupdatequery')->name('ajax_wk_saveupdatequery');
    Route::post('ajax_wk_savesms', 'school_setup\workflowController@wk_savesms')->name('ajax_wk_savesms');

    Route::resource('batch_master', 'school_setup\batchController');
    Route::post('ajaxdestroybatch_master', 'school_setup\batchController@ajaxdestroy')->name('ajaxdestroybatch_master');
    Route::get('ajax_StandardwiseDivision', 'school_setup\batchController@StandardwiseDivision')->name('ajax_StandardwiseDivision');

    Route::resource('timetable', 'school_setup\timetableController');
    Route::get('ajax_AcademicwiseStandard', 'school_setup\timetableController@AcademicwiseStandard')->name('ajax_AcademicwiseStandard');
    Route::post('ajax_getTimetable', 'school_setup\timetableController@getTimetable')->name('ajax_getTimetable');

    Route::resource('classwisetimetable', 'school_setup\classwisetimetableController');
    Route::post('ajax_getClasswiseTimetable', 'school_setup\classwisetimetableController@getClasswiseTimetable')->name('ajax_getClasswiseTimetable');
    Route::get('ajax_Batch_Timetable', 'school_setup\timetableController@getBatchTimetable')->name('ajax_Batch_Timetable');

    Route::get('ajax_New_Standard_Div', 'school_setup\timetableController@getNewStandardDiv')->name('ajax_New_Standard_Div');

    Route::get('ajax_Delete_Timetable', 'school_setup\timetableController@deleteTimetable')->name('ajax_Delete_Timetable');

    Route::get('ajax_Mapping_Teachers', 'school_setup\timetableController@getMappingTeachers')->name('ajax_Mapping_Teachers');

    Route::resource('proxy_master', 'school_setup\proxyController');

    Route::post('ajax_getproxyperiod', 'school_setup\proxyController@getproxyperiod')->name('ajax_getproxyperiod');
    Route::resource('facultywisetimetable', 'school_setup\facultywisetimetableController');
    Route::post('ajax_getFacultywiseTimetable', 'school_setup\facultywisetimetableController@getFacultywiseTimetable')->name('ajax_getFacultywiseTimetable');

    Route::resource('proxy_report', 'school_setup\proxyReportController');
    Route::post('ajax_getproxyreport', 'school_setup\proxyReportController@getproxyreport')->name('ajax_getproxyreport');

    Route::resource('todays_proxy_report', 'school_setup\todaysproxyReportController');

    Route::resource('classteacher', 'school_setup\classteacherController');
    Route::resource('classteacherReport', 'school_setup\classteacherReportController');

    Route::resource('teachertransfer', 'school_setup\teachertransferController');

    Route::resource('teacher_daily_report', 'school_setup\teacherdailyReportController');
    Route::post('ajax_getTeacherDailyReport', 'school_setup\teacherdailyReportController@getTeacherDailyReport')->name('ajax_getTeacherDailyReport');

    Route::resource('lessonplanning', 'school_setup\lessonplanningController');
    Route::get('ajax_getlp_subject', 'school_setup\lessonplanningController@getSubjectData')->name('ajax_getlp_subject');
    Route::get('ajax_getlp_division', 'school_setup\lessonplanningController@getDivisionData')->name('ajax_getlp_division');

    Route::resource('lessonplanningReport', 'school_setup\lessonplanningReportController');

    Route::get('google-analytics-summary', array('as' => 'google-analytics-summary', 'uses' => 'school_setup\HomeController@getAnalyticsSummary'));

    Route::resource('used_storage_graph', 'used_storage_graphController');

});
Route::post('get_proxy_master', 'school_setup\proxyController@getproxydata');
Route::get('school_setup/ajax_getTeacherDailyDetailsReport', 'school_setup\teacherdailyReportController@getTeacherDailyDetailsReport')->name("ajax_getTeacherDailyDetailsReport");


Route::post('/teacherTimetableAPI', 'school_setup\facultywisetimetableController@teacherTimetableAPI');
Route::post('/studentTimetableAPI', 'school_setup\classwisetimetableController@studentTimetableAPI');

Route::get('ajax_load_rightSideMenu', 'AJAXController@ajax_load_rightSideMenu')->name('ajax_load_rightSideMenu');
Route::get('ajax_load_helpguide', 'AJAXController@ajax_load_helpguide')->name('ajax_load_helpguide');

Route::post('ajax_sendmail', 'AJAXController@ajax_sendmail')->name('ajax_sendmail');

Route::get('ckeditor/create', 'CkeditorFileUploadController@create')->name('ckeditor.create');
Route::post('ckeditor', 'CkeditorFileUploadController@store')->name('uploadimage');

// Route::get('exception/index', 'ExceptionController@index');

//Auth::routes();

// Route::get('/home', 'HomeController@index')->name('home');

// Forgot Password Routes
Route::get('forget-password', 'Auth\ForgotPasswordController@showForgetPasswordForm')->name('forget.password.get');
Route::post('forget-password', 'Auth\ForgotPasswordController@submitForgetPasswordForm')->name('forget.password.post');
Route::get('reset-password/{token}/{email}','Auth\ForgotPasswordController@showResetPasswordForm')->name('reset.password.get');
Route::post('reset-password','Auth\ForgotPasswordController@submitResetPasswordForm')->name('reset.password.post');


Route::post('NewLMS_temp_signup', 'api\NewLMS_ApiController@NewLMS_temp_signup')->name("NewLMS_temp_signup");
Route::post('NewLMS_signup', 'api\NewLMS_ApiController@NewLMS_signup')->name("NewLMS_signup");
Route::get('Resend_otp', 'api\NewLMS_ApiController@Resend_otp')->name("Resend_otp");

Route::post('NewLMS_temp_signup_student', 'api\NewLMS_StudentApiController@NewLMS_temp_signup_student')->name("NewLMS_temp_signup_student");
Route::post('NewLMS_signup_student', 'api\NewLMS_StudentApiController@NewLMS_signup_student')->name("NewLMS_signup_student");


Route::get('get_trizStandard', 'signupController@get_trizStandard')->name("get_trizStandard");

Route::post('searching_menu', 'AJAXController@searchMenu')->name("searching_menu");
Route::post('get_search_url', 'AJAXController@get_search_url')->name("get_search_url");


// send birthday notification
Route::get('send_birthday_notification', 'easy_com\send_birthday_notification\send_birthday_notification_controller@send_birthday_notification')->name('send_birthday_notification');

// Question Wise Report
Route::get('questionReport', 'student\questionWiseReportController@index')->name('question_wise_report');
Route::post('show_question_wise_report', 'student\questionWiseReportController@show_question_wise_report')->name('show_question_wise_report');

// Admin Authorization (Show Result)
Route::get('result_admin_permission', 'result\result_admin_permission\ResultAdminPermissionController@index')->name('result_admin_permission');
Route::post('show_result_admin_permission', 'result\result_admin_permission\ResultAdminPermissionController@show_result_admin_permission')->name('show_result_admin_permission');
Route::post('allow_admin_permission', 'result\result_admin_permission\ResultAdminPermissionController@allow_admin_permission')->name('allow_admin_permission');


// create result excel
Route::get('download_create_result', 'result\MarkUploadController@index');
Route::post('generate_create_result_excel', 'result\MarkUploadController@create')->name('create-excel');
Route::get('upload_create_result', 'result\MarkUploadController@store')->name('upload_create_result');

Route::get('fetch_payment_status', 'fees\online_fees\online_fees_collect_controller@razorpay_fetch_payment_status');



