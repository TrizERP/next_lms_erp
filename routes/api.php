<?php

use App\Http\Controllers\api\apiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\settings\instituteDetailController;
use App\Http\Controllers\neo4jGraph\StudentResultGraphController;
use App\Http\Controllers\StudentGraphController;
use App\Http\Controllers\api\ApiLoginController;
use App\Http\Controllers\api\MenuRightsController;
use App\Http\Controllers\api\ApiLmsCourseController;
use App\Http\Controllers\api\ApiQuestionPaperController;
use App\Http\Controllers\api\AiSopGenerationController;
use App\Http\Controllers\api\admissionEnquiryAPIController;
use App\Http\Controllers\api\onlineAdmissionConfirmAPIController;
use App\Http\Controllers\api\admissionRegistrationAPIController;
use App\Http\Controllers\api\ClassTeacherApiController;
use App\Http\Controllers\api\AcademicSetupApiController;
use App\Http\Controllers\api\TransportationApiController;
use App\Http\Controllers\api\TeacherDailyReportApiController;
use App\Http\Controllers\api\UserLogReportApiController;
use App\Http\Controllers\fees\fees_cancel\feesCancelController;
use App\Http\Controllers\fees\fees_circular\feesCircularController;
use App\Http\Controllers\fees\fees_circular\feesCircularMasterController;


// Student Assessment API - Get student assessment data with scores and levels
Route::get('/student-assessment', [StudentGraphController::class, 'getStudentAssessment']);

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

Route::get('/student-results/{stuId}/graph', [StudentResultGraphController::class, 'show']);

Route::post('whats-send-app',function (Request $request) {
    \Illuminate\Support\Facades\Log::info(json_encode($request->all()));
});

Route::post('whats-comming-app',function (Request $request) {
    \Illuminate\Support\Facades\Log::info(json_encode($request->all()));
});

Route::post('update-message',[\App\Http\Controllers\WhatsappController::class,'updateDeliveryStatus']);
Route::post('incoming-message',[\App\Http\Controllers\WhatsappController::class,'incomingMessage']);


Route::controller(apiController::class)->group(function () {
    Route::post('login', 'login');
    Route::post('login_hills', 'login_hills');
    Route::post('check_otp', 'check_otp');
    Route::post('homescreen', 'homescreen');
    Route::post('teacherlogin', 'teacherlogin');
    Route::post('teacher_check_otp', 'teacher_check_otp');
    Route::post('playscreen', 'playscreen');
    Route::post('homescreen', 'homescreen');
    Route::post('gcm_insert', 'gcm_insert');
    Route::get('testkey', 'testkey');
});

Route::post('api-login', [ApiLoginController::class, 'login'])->name('api.api-login');
// 12-11-2024
Route::get('crm-whatsapp', [\App\Http\Controllers\WhatsappController::class, 'whatsappCRM'])->withoutMiddleware([Authenticate::class])->name('crm-whatsapp');
Route::get('crm-whatsapp-update', [\App\Http\Controllers\WhatsappController::class, 'updateCRMWhatsappStatus'])->withoutMiddleware([Authenticate::class])->name('updateCRMWhatsappStatus');

// 27-01-2025 only for API
Route::get('/compliance/list',[instituteDetailController::class,'index']);
Route::post('/compliance/create',[instituteDetailController::class,'store']);
Route::post('/compliance/update/{id}',[instituteDetailController::class,'update']);
Route::post('/compliance/delete/{id}',[instituteDetailController::class,'destroy']);
//getmenu rights level wise
Route::post('/menu-rights', [App\Http\Controllers\api\MenuRightsController::class, 'getMenuRightsLevelWise']);
Route::get('/master-menu-rights', [App\Http\Controllers\api\MenuRightsController::class, 'getMasterMenuApi']);

Route::post('lms-courses', [ApiLmsCourseController::class, 'index']);
Route::post('lms-courses/search', [ApiLmsCourseController::class, 'search']);
Route::post('lms-chapter-concepts', [ApiLmsCourseController::class, 'getChapterConcepts']);
Route::post('lms-chapters', [ApiLmsCourseController::class, 'chapters']);
Route::post('lms-chapter-content', [ApiLmsCourseController::class, 'chapterContent']);
Route::post('lms-questions', [ApiLmsCourseController::class, 'getLmsQuestions']);
Route::get('question-mapping-levels', [ApiLmsCourseController::class, 'getQuestionMappingLevels']);
Route::post('lms-chapters/store', [ApiLmsCourseController::class, 'storeChapter']);
Route::post('lms-create-content', [ApiLmsCourseController::class, 'createContent']);
Route::post('lms-store-content', [ApiLmsCourseController::class, 'storeContent']);
Route::post('lms-content-mapping-values', [ApiLmsCourseController::class, 'getContentMappingValues']);
Route::post('lms-store-subject', [ApiLmsCourseController::class, 'storeSubject']);
Route::post('lms/gamma-content-master', [\App\Http\Controllers\lms\contentController::class, 'storeGammaContent']);
Route::get('ai-sop', [AiSopGenerationController::class, 'index']);
Route::get('ai-sop/department-job-roles', [AiSopGenerationController::class, 'departmentJobRoles']);
Route::post('ai-sop/generate', [AiSopGenerationController::class, 'generate']);
Route::post('ai-sop/store', [AiSopGenerationController::class, 'store']);
Route::post('lms-homework/get-subjects', [\App\Http\Controllers\api\lms\StudentHomeworkApiController::class, 'getSubjects']);
Route::post('lms-homework/get-chapters', [\App\Http\Controllers\api\lms\StudentHomeworkApiController::class, 'getChapters']);

Route::controller(admissionEnquiryAPIController::class)->group(function () {
    Route::get('admission_enquiry', 'index');
    Route::post('admission_enquiry', 'store');
    Route::match(['put', 'patch'], 'admission_enquiry/{id}', 'update');
    Route::delete('admission_enquiry/{id}', 'destroy');
});


Route::controller(onlineAdmissionConfirmAPIController::class)->group(function () {
    Route::get('online_admission_confirm', 'index');
    Route::post('online_admission_confirm', 'store');
    Route::get('online_admission_confirm/{id}', 'show');
    Route::match(['put', 'patch'], 'online_admission_confirm/{id}', 'update');
    Route::delete('online_admission_confirm/{id}', 'destroy');
});

Route::controller(admissionRegistrationAPIController::class)->group(function () {
    Route::get('admission_registration', 'index');
    Route::get('admission_registration/{id}/edit', 'edit');
    Route::match(['put', 'patch'], 'admission_registration/{id}', 'update');
    Route::delete('admission_registration/{id}', 'destroy');
    Route::post('admission_student', 'saveStudent');
    Route::get('ajax_getDivision', 'ajax_getDivision');
});


Route::apiResource('question-paper', ApiQuestionPaperController::class);
Route::apiResource('class-teachers', ClassTeacherApiController::class)->except(['show']);
Route::get('user-logs/bootstrap', [UserLogReportApiController::class, 'bootstrap']);
Route::post('user-logs/search', [UserLogReportApiController::class, 'search']);
Route::post('teacher-daily-reports/search', [TeacherDailyReportApiController::class, 'search']);
Route::get('teacher-daily-reports/{teacherId}/details', [TeacherDailyReportApiController::class, 'details']);
Route::get('academic-setup/{module}', [AcademicSetupApiController::class, 'index']);
Route::post('academic-setup/{module}', [AcademicSetupApiController::class, 'store']);
Route::match(['put', 'patch'], 'academic-setup/{module}/{id}', [AcademicSetupApiController::class, 'update']);
Route::delete('academic-setup/{module}/{id}', [AcademicSetupApiController::class, 'destroy']);
Route::get('transportation-setup/{module}', [TransportationApiController::class, 'index']);
Route::post('transportation-setup/{module}', [TransportationApiController::class, 'store']);
Route::match(['put', 'patch'], 'transportation-setup/{module}/{id}', [TransportationApiController::class, 'update']);
Route::delete('transportation-setup/{module}/{id}', [TransportationApiController::class, 'destroy']);
Route::post('question-paper/search', [ApiQuestionPaperController::class, 'search']);
Route::post('fees-cancel/search', [feesCancelController::class, 'search']);

// Fees Circular - stateless JSON entry points for the Next.js frontend.
// Callers must send type=JSON (is_mobile then returns response()->json) plus
// syear/sub_institute_id/user_id, which the controllers seed into session()
// for the downstream fee helpers. api.php does not run StartSession.
Route::post('fees-circular/filters', [feesCircularController::class, 'index']);
Route::post('fees-circular/students', [feesCircularController::class, 'showStudent']);
Route::post('fees-circular/generate', [feesCircularController::class, 'showCircular']);
Route::post('fees-circular-master', [feesCircularMasterController::class, 'index']);
Route::post('fees-circular-master/store', [feesCircularMasterController::class, 'store']);
Route::post('fees-circular-master/{id}/update', [feesCircularMasterController::class, 'update']);
Route::post('fees-circular-master/{id}/delete', [feesCircularMasterController::class, 'destroy']);

// Intelligence Lesson Plan - Lesson Plan -> Period -> Concepts hierarchy
Route::match(['GET', 'POST'], 'intelligence/lesson-plans', [\App\Http\Controllers\api\lms\IntelligenceLessonPlanApiController::class, 'index']);

// Intelligence Question Generation - MCQ / narrative items via DeepSeek LLM -> lms_question_master
Route::post('intelligence/questions/generate', [\App\Http\Controllers\api\lms\IntelligenceQuestionGenerationApiController::class, 'generate']);

// Semantic Intelligence - read-only chapter intelligence for presentation generators
Route::get('semantic-intelligence', [\App\Http\Controllers\api\lms\SemanticIntelligenceApiController::class, 'index']);
Route::get('semantic-intelligence/{extraction_id}/result', [\App\Http\Controllers\api\lms\SemanticIntelligenceApiController::class, 'show']);

Route::get('/departments', [\App\Http\Controllers\HRMS\departmentController::class, 'index']);
Route::get('/departments/create', [\App\Http\Controllers\HRMS\departmentController::class, 'create']);
Route::get('/department-employee-lists', [\App\Http\Controllers\HRMS\departmentController::class, 'departmentEmpLists']);
Route::get('/sub-department-list', [\App\Http\Controllers\HRMS\departmentController::class, 'subDepartmentList']);
Route::get('/department-employee-list', [\App\Http\Controllers\HRMS\departmentController::class, 'departmentEmployeeList']);
Route::get('/departments/hierarchy', [\App\Http\Controllers\HRMS\departmentController::class, 'hierarchy']);



use App\Http\Controllers\api\UserManagementApiController;

Route::controller(UserManagementApiController::class)->group(function () {
    Route::get('users', 'index');
    Route::post('users', 'store');
    Route::get('users/{id}', 'show');
    Route::post('users/{id}', 'update');
    Route::post('users/{id}/deactivate', 'destroy');
    Route::get('user-profiles', 'profiles');
    Route::post('user-profiles', 'storeProfile');
    Route::post('user-profiles/{id}', 'updateProfile');
    Route::post('user-profiles/{id}/delete', 'destroyProfile');
    Route::get('user-reports/bootstrap', 'reportBootstrap');
    Route::post('user-reports/search', 'report');
});


Route::get('teacher-transfer', [\App\Http\Controllers\api\TeacherTransferApiController::class, 'index']);
Route::post('teacher-transfer', [\App\Http\Controllers\api\TeacherTransferApiController::class, 'store']);

