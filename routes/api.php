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
use App\Http\Controllers\api\GeneralSetupApiController;
use App\Http\Controllers\api\TeacherDailyReportApiController;
use App\Http\Controllers\api\UserLogReportApiController;
use App\Http\Controllers\api\InventoryApiController;
use App\Http\Controllers\fees\fees_cancel\feesCancelController;
use App\Http\Controllers\fees\fees_circular\feesCircularController;
use App\Http\Controllers\fees\fees_circular\feesCircularMasterController;
use App\Http\Controllers\api\FeesDashboardApiController;
use App\Http\Controllers\api\FeesRefundApiController;
use App\Http\Controllers\api\TeacherAssignmentMobileApiController;


// Student Assessment API - Get student assessment data with scores and levels
Route::get('/student-assessment', [StudentGraphController::class, 'getStudentAssessment']);
Route::middleware('api.session')->group(function () { Route::post('teacher/assignments/standards', [TeacherAssignmentMobileApiController::class, 'standards']); Route::post('teacher/assignments/divisions', [TeacherAssignmentMobileApiController::class, 'divisions']); });

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
// Isolated mobile Own Profile API; legacy profile controllers are unchanged.
Route::middleware('api.session')->get('own-profile', [\App\Http\Controllers\api\OwnProfileApiController::class, 'show']);
Route::middleware('api.session')->prefix('hrms')->group(function () {
    Route::get('today', [\App\Http\Controllers\api\HrmsMobileApiController::class, 'today']);
    Route::post('punch', [\App\Http\Controllers\api\HrmsMobileApiController::class, 'punch']);
    Route::get('attendance', [\App\Http\Controllers\api\HrmsMobileApiController::class, 'attendance']);
    Route::get('leaves', [\App\Http\Controllers\api\HrmsMobileApiController::class, 'leaves']);
});
Route::post('fees-dashboard/summary', [FeesDashboardApiController::class, 'summary']);
Route::middleware('api.session')->prefix('fees-refund')->group(function () {
    Route::post('search', [FeesRefundApiController::class, 'search']);
    Route::post('detail/{studentId}', [FeesRefundApiController::class, 'detail']);
    Route::post('save', [FeesRefundApiController::class, 'save']);
});

// Stateless replacements for the legacy session/Blade ERP migration modules.
// These are deliberately separate from the old controllers so the Next.js
// application never has to manufacture a Laravel session.
Route::get('migration-modules/{module}', [\App\Http\Controllers\api\MigrationModulesApiController::class, 'index']);
Route::post('migration-modules/{module}', [\App\Http\Controllers\api\MigrationModulesApiController::class, 'store']);
Route::delete('migration-modules/{module}/{id}', [\App\Http\Controllers\api\MigrationModulesApiController::class, 'destroy']);
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
Route::post('lms-question-bank', [ApiLmsCourseController::class, 'getQuestionBank']);
Route::get('question-mapping-levels', [ApiLmsCourseController::class, 'getQuestionMappingLevels']);
Route::post('lms-chapters/store', [ApiLmsCourseController::class, 'storeChapter']);
Route::post('lms-create-content', [ApiLmsCourseController::class, 'createContent']);
Route::post('lms-store-content', [ApiLmsCourseController::class, 'storeContent']);
Route::post('lms-chapter-content/upload', [ApiLmsCourseController::class, 'uploadContent']);
Route::post('lms-content-mapping-values', [ApiLmsCourseController::class, 'getContentMappingValues']);
Route::post('lms-store-subject', [ApiLmsCourseController::class, 'storeSubject']);
Route::post('lms/gamma-content-master', [\App\Http\Controllers\lms\contentController::class, 'storeGammaContent']);
Route::get('ai-sop', [AiSopGenerationController::class, 'index']);
Route::get('ai-sop/department-job-roles', [AiSopGenerationController::class, 'departmentJobRoles']);
Route::post('ai-sop/generate', [AiSopGenerationController::class, 'generate']);
Route::post('ai-sop/store', [AiSopGenerationController::class, 'store']);
Route::post('lms-homework/get-subjects', [\App\Http\Controllers\api\lms\StudentHomeworkApiController::class, 'getSubjects']);
Route::post('lms-homework/get-chapters', [\App\Http\Controllers\api\lms\StudentHomeworkApiController::class, 'getChapters']);
// Student Homework (assign) + Student Homework Report
Route::post('lms-homework/list', [\App\Http\Controllers\api\lms\StudentHomeworkApiController::class, 'index']);
Route::post('lms-homework/store', [\App\Http\Controllers\api\lms\StudentHomeworkApiController::class, 'store']);
Route::post('lms-homework/show/{id}', [\App\Http\Controllers\api\lms\StudentHomeworkApiController::class, 'show']);
Route::post('lms-homework/update/{id}', [\App\Http\Controllers\api\lms\StudentHomeworkApiController::class, 'update']);
Route::post('lms-homework/delete/{id}', [\App\Http\Controllers\api\lms\StudentHomeworkApiController::class, 'destroy']);
Route::post('lms-homework/bulk-delete', [\App\Http\Controllers\api\lms\StudentHomeworkApiController::class, 'bulkDelete']);
Route::post('lms-homework/students', [\App\Http\Controllers\api\lms\StudentHomeworkApiController::class, 'studentsList']);
Route::post('lms-homework/homework-subjects', [\App\Http\Controllers\api\lms\StudentHomeworkApiController::class, 'homeworkSubjects']);
// Homework Submission (entry) + Student Homework Submission Report
Route::post('lms-homework/submission-list', [\App\Http\Controllers\api\lms\StudentHomeworkApiController::class, 'submissionList']);
Route::post('lms-homework/submission-store', [\App\Http\Controllers\api\lms\StudentHomeworkApiController::class, 'submissionStore']);
Route::post('lms-homework/submission-report', [\App\Http\Controllers\api\lms\StudentHomeworkApiController::class, 'submissionReport']);

// ------------------------------------------------------------------
// LMS Assignment / Assignment Submission / Annotate Assignment
// (dedicated LmsAssignmentApiController - token-auth counterparts of the
//  session/blade controllers under App\Http\Controllers\lms\assignment)
// ------------------------------------------------------------------
// Module 1 - Assignment (teacher create)
Route::post('lms-assignment/subjects', [\App\Http\Controllers\api\lms\LmsAssignmentApiController::class, 'subjects']);
Route::post('lms-assignment/students', [\App\Http\Controllers\api\lms\LmsAssignmentApiController::class, 'students']);
Route::post('lms-assignment/exam-papers', [\App\Http\Controllers\api\lms\LmsAssignmentApiController::class, 'examPapers']);
Route::post('lms-assignment/store', [\App\Http\Controllers\api\lms\LmsAssignmentApiController::class, 'store']);
Route::post('lms-assignment/list', [\App\Http\Controllers\api\lms\LmsAssignmentApiController::class, 'index']);
// Module 2 - Assignment Submission (student upload)
Route::post('lms-assignment/submission-list', [\App\Http\Controllers\api\lms\LmsAssignmentApiController::class, 'submissionList']);
Route::post('lms-assignment/submission-store', [\App\Http\Controllers\api\lms\LmsAssignmentApiController::class, 'submissionStore']);
// Module 3 - Annotate Assignment (teacher review / grade)
Route::post('lms-assignment/annotate-list', [\App\Http\Controllers\api\lms\LmsAssignmentApiController::class, 'annotateList']);
Route::post('lms-assignment/annotate-questions', [\App\Http\Controllers\api\lms\LmsAssignmentApiController::class, 'annotateQuestions']);
Route::post('lms-assignment/annotate-store', [\App\Http\Controllers\api\lms\LmsAssignmentApiController::class, 'annotateStore']);

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
Route::get('general-setup/{module}', [GeneralSetupApiController::class, 'index']);
Route::post('general-setup/{module}', [GeneralSetupApiController::class, 'store']);
Route::match(['put', 'patch'], 'general-setup/{module}/{id}', [GeneralSetupApiController::class, 'update']);
Route::delete('general-setup/{module}/{id}', [GeneralSetupApiController::class, 'destroy']);
Route::get('inventory/{module}', [InventoryApiController::class, 'index'])->where('module', '^(?!reports$).+');
Route::get('inventory/reports/{module}', [InventoryApiController::class, 'reportIndex']);
Route::post('inventory/{module}', [InventoryApiController::class, 'store'])->where('module', '^(?!reports$).+');
Route::match(['put', 'patch'], 'inventory/{module}/{id}', [InventoryApiController::class, 'update'])->where('module', '^(?!reports$).+');
Route::delete('inventory/{module}/{id}', [InventoryApiController::class, 'destroy'])->where('module', '^(?!reports$).+');
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



Route::controller(\App\Http\Controllers\api\UserManagementApiController::class)->group(function () {
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

Route::get('groupwise-rights', [\App\Http\Controllers\api\GroupwiseRightsApiController::class, 'index']);
Route::get('groupwise-rights/{profileId}/matrix', [\App\Http\Controllers\api\GroupwiseRightsApiController::class, 'matrix']);
Route::post('groupwise-rights', [\App\Http\Controllers\api\GroupwiseRightsApiController::class, 'store']);
Route::get('individual-rights', [\App\Http\Controllers\api\IndividualRightsApiController::class, 'index']);
Route::get('individual-rights/{profileId}/users', [\App\Http\Controllers\api\IndividualRightsApiController::class, 'users']);
Route::get('individual-rights/{profileId}/{userId}/matrix', [\App\Http\Controllers\api\IndividualRightsApiController::class, 'matrix']);
Route::post('individual-rights', [\App\Http\Controllers\api\IndividualRightsApiController::class, 'store']);
Route::get('mobile-app-rights/bootstrap', [\App\Http\Controllers\api\MobileAppMenuRightsApiController::class, 'bootstrap']);
Route::get('mobile-app-rights/{profileId}/rights', [\App\Http\Controllers\api\MobileAppMenuRightsApiController::class, 'rights']);
Route::post('mobile-app-rights/rights', [\App\Http\Controllers\api\MobileAppMenuRightsApiController::class, 'saveRights']);
Route::get('mobile-app-rights/config', [\App\Http\Controllers\api\MobileAppMenuRightsApiController::class, 'configIndex']);
Route::post('mobile-app-rights/config/{id}', [\App\Http\Controllers\api\MobileAppMenuRightsApiController::class, 'updateConfig']);


Route::get('teacher-transfer', [\App\Http\Controllers\api\TeacherTransferApiController::class, 'index']);
Route::post('teacher-transfer', [\App\Http\Controllers\api\TeacherTransferApiController::class, 'store']);

// Complaint module - stateless JSON entry points for the Next.js frontend.
// Kept separate from frontdesk\complaintController, which is unchanged and still
// serves the Blade screens. The delete route is declared before the {id} update
// route so it is not swallowed by it.
Route::get('complaints', [\App\Http\Controllers\api\ComplaintApiController::class, 'index']);
Route::post('complaints', [\App\Http\Controllers\api\ComplaintApiController::class, 'store']);
Route::post('complaints/{id}/delete', [\App\Http\Controllers\api\ComplaintApiController::class, 'destroy']);
Route::post('complaints/{id}', [\App\Http\Controllers\api\ComplaintApiController::class, 'update']);

// Consent module - stateless JSON entry points for the Next.js frontend.
// Kept separate from the App\Http\Controllers\consent controllers, which are
// unchanged and still serve the Blade screens.
Route::get('consents/students', [\App\Http\Controllers\api\ConsentApiController::class, 'students']);
Route::get('consents', [\App\Http\Controllers\api\ConsentApiController::class, 'index']);
Route::post('consents/delete', [\App\Http\Controllers\api\ConsentApiController::class, 'destroy']);
Route::post('consents', [\App\Http\Controllers\api\ConsentApiController::class, 'store']);

// Front desk module - stateless JSON entry points for the Next.js frontend.
// Kept separate from frontdesk\frontdeskController, which is unchanged and still
// serves the Blade screens. `report` and `{id}/delete` are declared before the
// `{id}` routes so they are not swallowed by them.
Route::get('front-desk/report', [\App\Http\Controllers\api\FrontDeskApiController::class, 'report']);
Route::get('front-desk', [\App\Http\Controllers\api\FrontDeskApiController::class, 'index']);
Route::get('front-desk/{id}', [\App\Http\Controllers\api\FrontDeskApiController::class, 'show']);
Route::post('front-desk', [\App\Http\Controllers\api\FrontDeskApiController::class, 'store']);
Route::post('front-desk/{id}/delete', [\App\Http\Controllers\api\FrontDeskApiController::class, 'destroy']);
Route::post('front-desk/{id}', [\App\Http\Controllers\api\FrontDeskApiController::class, 'update']);

// Petty cash module - stateless JSON entry points for the Next.js frontend.
// Kept separate from the frontdesk PettyCash* controllers, which are unchanged
// and still serve the Blade screens. The literal `heads` and `report` segments
// are declared before the `{id}` routes so they are not swallowed by them.
Route::get('petty-cash/heads', [\App\Http\Controllers\api\PettyCashApiController::class, 'headIndex']);
Route::post('petty-cash/heads', [\App\Http\Controllers\api\PettyCashApiController::class, 'headStore']);
Route::post('petty-cash/heads/{id}/delete', [\App\Http\Controllers\api\PettyCashApiController::class, 'headDestroy']);
Route::post('petty-cash/heads/{id}', [\App\Http\Controllers\api\PettyCashApiController::class, 'headUpdate']);
Route::get('petty-cash/report', [\App\Http\Controllers\api\PettyCashApiController::class, 'report']);
Route::get('petty-cash', [\App\Http\Controllers\api\PettyCashApiController::class, 'index']);
Route::get('petty-cash/{id}', [\App\Http\Controllers\api\PettyCashApiController::class, 'show']);
Route::post('petty-cash', [\App\Http\Controllers\api\PettyCashApiController::class, 'store']);
Route::post('petty-cash/{id}/delete', [\App\Http\Controllers\api\PettyCashApiController::class, 'destroy']);
Route::post('petty-cash/{id}', [\App\Http\Controllers\api\PettyCashApiController::class, 'update']);


/*
|--------------------------------------------------------------------------
| Module-wise onboarding
|--------------------------------------------------------------------------
|
| Guarded by `api.session`, which performs real JWT validation before
| hydrating the legacy session keys. The onboarding surface deliberately does
| NOT use the `session` + `type=API` convention of the older screens: that pair
| short-circuits both SessionMiddleware and checkPermission on caller-supplied
| input. Tenant scope is read from the validated token payload, never from
| request input.
|
| Replaces the Blade screens at /Onboarding and /transport_Onboarding, which
| stay in place for the legacy UI.
|
*/
Route::group(['prefix' => 'onboarding', 'middleware' => ['api.session']], function () {
    Route::get('overview', [\App\Http\Controllers\api\OnboardingApiController::class, 'overview']);
    Route::get('modules/{moduleKey}', [\App\Http\Controllers\api\OnboardingApiController::class, 'show']);
    Route::post('steps/{stepId}', [\App\Http\Controllers\api\OnboardingApiController::class, 'updateStep']);
});
// Document Templates module - stateless JSON entry points for the Next.js
// frontend's drag-and-drop template designer (/document-templates). Distinct
// from the legacy `template_master` screens, which are unchanged.
// Literal segments (`merge-fields`, `merge-data`, `preview-students`) are
// declared before the `{id}` routes so they are not swallowed by them, and the
// same holds for `{id}/...` sub-paths against `{id}`.
Route::get('document-templates/merge-fields', [\App\Http\Controllers\api\DocumentTemplateApiController::class, 'mergeFields']);
Route::get('document-templates/merge-data', [\App\Http\Controllers\api\DocumentTemplateApiController::class, 'mergeData']);
Route::get('document-templates/preview-students', [\App\Http\Controllers\api\DocumentTemplateApiController::class, 'previewStudents']);
Route::get('document-templates', [\App\Http\Controllers\api\DocumentTemplateApiController::class, 'index']);
Route::get('document-templates/{id}/versions', [\App\Http\Controllers\api\DocumentTemplateApiController::class, 'versions']);
Route::get('document-templates/{id}/versions/{version}', [\App\Http\Controllers\api\DocumentTemplateApiController::class, 'versionContent']);
Route::get('document-templates/{id}', [\App\Http\Controllers\api\DocumentTemplateApiController::class, 'show']);
Route::post('document-templates', [\App\Http\Controllers\api\DocumentTemplateApiController::class, 'store']);
Route::post('document-templates/{id}/duplicate', [\App\Http\Controllers\api\DocumentTemplateApiController::class, 'duplicate']);
Route::post('document-templates/{id}/restore/{version}', [\App\Http\Controllers\api\DocumentTemplateApiController::class, 'restore']);
Route::post('document-templates/{id}/delete', [\App\Http\Controllers\api\DocumentTemplateApiController::class, 'destroy']);
Route::post('document-templates/{id}', [\App\Http\Controllers\api\DocumentTemplateApiController::class, 'update']);

// Fields Configuration module - stateless JSON entry points for the Next.js
// frontend. Distinct from the legacy `add_fields` web routes, which are unchanged.
Route::get('fields-configuration', [\App\Http\Controllers\api\CustomFieldApiController::class, 'index']);
Route::post('fields-configuration/update-sort', [\App\Http\Controllers\api\CustomFieldApiController::class, 'updateSortOrder']);
Route::post('fields-configuration', [\App\Http\Controllers\api\CustomFieldApiController::class, 'store']);
Route::get('fields-configuration/{id}', [\App\Http\Controllers\api\CustomFieldApiController::class, 'show']);
Route::post('fields-configuration/{id}', [\App\Http\Controllers\api\CustomFieldApiController::class, 'update']);
Route::post('fields-configuration/{id}/delete', [\App\Http\Controllers\api\CustomFieldApiController::class, 'destroy']);







