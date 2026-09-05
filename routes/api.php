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
use App\Http\Controllers\api\AiPlatformController;
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
use App\Http\Controllers\api\RoleDashboardApiController;
use App\Http\Controllers\api\AdmissionsDashboardApiController;
use App\Http\Controllers\api\StudentsDashboardApiController;
use App\Http\Controllers\api\LibraryDashboardApiController;
use App\Http\Controllers\api\HostelDashboardApiController;
use App\Http\Controllers\api\TransportationDashboardApiController;
use App\Http\Controllers\api\FeesRefundApiController;
use App\Http\Controllers\api\TeacherAssignmentMobileApiController;
use App\Http\Controllers\api\TeacherTimetableApiController;
use App\Http\Controllers\api\TeacherFeeDuesApiController;
use App\Http\Controllers\api\TeacherIcardApiController;


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
Route::get('academic-terms', [ApiLoginController::class, 'academicTerms'])->name('api.academic-terms');
// Isolated mobile Own Profile API; legacy profile controllers are unchanged.
Route::middleware('api.session')->get('own-profile', [\App\Http\Controllers\api\OwnProfileApiController::class, 'show']);
Route::middleware('api.session')->prefix('hrms')->group(function () {
    Route::get('today', [\App\Http\Controllers\api\HrmsMobileApiController::class, 'today']);
    Route::post('punch', [\App\Http\Controllers\api\HrmsMobileApiController::class, 'punch']);
    Route::get('attendance', [\App\Http\Controllers\api\HrmsMobileApiController::class, 'attendance']);
    Route::get('leaves', [\App\Http\Controllers\api\HrmsMobileApiController::class, 'leaves']);
});
Route::post('fees-dashboard/summary', [FeesDashboardApiController::class, 'summary']);
// Module dashboards (Admissions/Students) — same stateless pattern as
// fees-dashboard/summary above: tenant/year travel in the request body, so
// no session middleware is required.
Route::post('admissions-dashboard/summary', [AdmissionsDashboardApiController::class, 'summary']);
Route::post('students-dashboard/summary', [StudentsDashboardApiController::class, 'summary']);
Route::post('library-dashboard/summary', [LibraryDashboardApiController::class, 'summary']);
Route::post('hostel-dashboard/summary', [HostelDashboardApiController::class, 'summary']);
Route::post('transportation-dashboard/summary', [TransportationDashboardApiController::class, 'summary']);
// Role-based dashboards (Admin/Teacher/Student) — identity comes only from the
// JWT via ApiSessionHydrator, never from the request body, so a token cannot
// be used to fetch another role's or another user's data.
Route::middleware('api.session')->group(function () {
    Route::post('admin-dashboard/summary', [RoleDashboardApiController::class, 'adminSummary']);
    Route::post('teacher-dashboard/summary', [RoleDashboardApiController::class, 'teacherSummary']);
    Route::post('teacher-timetable/summary', [TeacherTimetableApiController::class, 'summary']);
    Route::post('teacher-fee-dues/summary', [TeacherFeeDuesApiController::class, 'summary']);
    Route::post('student-dashboard/summary', [RoleDashboardApiController::class, 'studentSummary']);
    // Self-service "My ID card" — scoped to the caller's own user_id only,
    // see App\Http\Controllers\api\TeacherIcardApiController::mine().
    Route::post('teacher-icard/mine', [TeacherIcardApiController::class, 'mine']);
});
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

// GET is accepted alongside POST so these can be opened in a browser or curled without
// a body — the handlers read their parameters through $request->input(), which covers
// the query string as well. POST is unchanged, so existing callers are unaffected.
Route::match(['get', 'post'], 'lms-courses', [ApiLmsCourseController::class, 'index']);
Route::match(['get', 'post'], 'lms-courses/search', [ApiLmsCourseController::class, 'search']);
Route::post('lms-chapter-concepts', [ApiLmsCourseController::class, 'getChapterConcepts']);
Route::post('lms-chapters', [ApiLmsCourseController::class, 'chapters']);
Route::post('lms-chapter-content', [ApiLmsCourseController::class, 'chapterContent']);
Route::post('lms-questions', [ApiLmsCourseController::class, 'getLmsQuestions']);
Route::post('lms-question-bank', [ApiLmsCourseController::class, 'getQuestionBank']);
Route::post('lms-question-bank/update', [ApiLmsCourseController::class, 'updateQuestionBank']);
Route::post('lms-question-bank/delete', [ApiLmsCourseController::class, 'deleteQuestionBank']);
Route::get('question-mapping-levels', [ApiLmsCourseController::class, 'getQuestionMappingLevels']);
Route::post('lms-chapters/store', [ApiLmsCourseController::class, 'storeChapter']);
Route::post('lms-create-content', [ApiLmsCourseController::class, 'createContent']);
Route::post('lms-store-content', [ApiLmsCourseController::class, 'storeContent']);
Route::post('lms-chapter-content/upload', [ApiLmsCourseController::class, 'uploadContent']);
Route::post('lms-content-mapping-values', [ApiLmsCourseController::class, 'getContentMappingValues']);
Route::post('lms-store-subject', [ApiLmsCourseController::class, 'storeSubject']);
Route::post('lms/gamma-content-master', [\App\Http\Controllers\lms\contentController::class, 'storeGammaContent']);
Route::get('ai-sop', [AiSopGenerationController::class, 'index']);
Route::get('ai-platforms', [AiPlatformController::class, 'index']);
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
Route::post('lms-homework/ai-status/{id}', [\App\Http\Controllers\api\lms\StudentHomeworkApiController::class, 'aiEvaluationStatus']);

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
Route::post('lms-assignment/bulk-delete', [\App\Http\Controllers\api\lms\LmsAssignmentApiController::class, 'bulkDelete']);
// Module 2 - Assignment Submission (student upload)
Route::post('lms-assignment/submission-list', [\App\Http\Controllers\api\lms\LmsAssignmentApiController::class, 'submissionList']);
Route::post('lms-assignment/submission-store', [\App\Http\Controllers\api\lms\LmsAssignmentApiController::class, 'submissionStore']);
Route::post('lms-assignment/ai-status/{id}', [\App\Http\Controllers\api\lms\LmsAssignmentApiController::class, 'aiEvaluationStatus']);
// Module 3 - Annotate Assignment (teacher review / grade)
Route::post('lms-assignment/annotate-list', [\App\Http\Controllers\api\lms\LmsAssignmentApiController::class, 'annotateList']);
Route::post('lms-assignment/annotate-questions', [\App\Http\Controllers\api\lms\LmsAssignmentApiController::class, 'annotateQuestions']);
Route::post('lms-assignment/annotate-store', [\App\Http\Controllers\api\lms\LmsAssignmentApiController::class, 'annotateStore']);
// Student side of the Assignment module is deliberately narrow: a student may
// VIEW the assignments given to them and SUBMIT a file against them, nothing
// else. Every other screen -- creating assignments, picking students, reading
// the class-wide list, annotating and grading -- is teacher-side only, so those
// endpoints sit behind `staff.only`, which rejects Student/Parent tokens.
//
// Both groups run `api.session` first: it verifies the bearer JWT and hydrates
// the session from the token payload. That is what `staff.only` reads, and what
// lets the submission endpoints pin themselves to the caller's own student id
// instead of trusting the user_id in the request body.

// Modules 1 & 3 - Assignment (teacher create) and Annotate Assignment (review / grade)
Route::middleware(['api.session', 'staff.only'])->group(function () {
    Route::post('lms-assignment/subjects', [\App\Http\Controllers\api\lms\LmsAssignmentApiController::class, 'subjects']);
    Route::post('lms-assignment/students', [\App\Http\Controllers\api\lms\LmsAssignmentApiController::class, 'students']);
    Route::post('lms-assignment/exam-papers', [\App\Http\Controllers\api\lms\LmsAssignmentApiController::class, 'examPapers']);
    Route::post('lms-assignment/store', [\App\Http\Controllers\api\lms\LmsAssignmentApiController::class, 'store']);
    Route::post('lms-assignment/list', [\App\Http\Controllers\api\lms\LmsAssignmentApiController::class, 'index']);
    Route::post('lms-assignment/annotate-list', [\App\Http\Controllers\api\lms\LmsAssignmentApiController::class, 'annotateList']);
    Route::post('lms-assignment/annotate-questions', [\App\Http\Controllers\api\lms\LmsAssignmentApiController::class, 'annotateQuestions']);
    Route::post('lms-assignment/annotate-store', [\App\Http\Controllers\api\lms\LmsAssignmentApiController::class, 'annotateStore']);
});

// Module 2 - Assignment Submission (the student's own view + upload screen)
Route::middleware('api.session')->group(function () {
    Route::post('lms-assignment/submission-list', [\App\Http\Controllers\api\lms\LmsAssignmentApiController::class, 'submissionList']);
    Route::post('lms-assignment/submission-store', [\App\Http\Controllers\api\lms\LmsAssignmentApiController::class, 'submissionStore']);
});

// ------------------------------------------------------------------
// LMS Engagement - Leader Board + Social & Collaborative
// (K12 rebuild of the legacy Blade modules lms/lmsLeaderboard and
//  lms/lmsSocialCollabrotive. Both are reimplemented as stateless REST APIs in
//  App\Http\Controllers\api\lms; the legacy web controllers and routes are
//  untouched and keep serving the old ERP.)
//
// `api.session` validates the bearer JWT and hydrates the session from the
// verified payload, so tenant (sub_institute_id), user and academic year come
// from the token - never from the request body. Both modules are open to
// students AND staff (that is the legacy behaviour: a student raises a doubt,
// a teacher or a classmate replies), so neither sits behind `staff.only`;
// per-role visibility is enforced inside the services.
// ------------------------------------------------------------------
Route::middleware('api.session')->prefix('lms')->group(function () {
    // Leader Board (read-only - nothing in the ERP writes lb_points).
    Route::get('leaderboard', [\App\Http\Controllers\api\lms\LmsLeaderboardApiController::class, 'index']);
    Route::get('leaderboard/filters', [\App\Http\Controllers\api\lms\LmsLeaderboardApiController::class, 'filters']);
    Route::get('leaderboard/rankings', [\App\Http\Controllers\api\lms\LmsLeaderboardApiController::class, 'rankings']);
    Route::get('leaderboard/{userId}', [\App\Http\Controllers\api\lms\LmsLeaderboardApiController::class, 'show'])
        ->where('userId', '[0-9]+');

    // Leader Board Master - the admin points configuration (lb_master).
    // Staff-only: students and parents must not reach the configuration screen.
    Route::middleware('staff.only')->group(function () {
        Route::get('leaderboard-master', [\App\Http\Controllers\api\lms\LmsLeaderboardMasterApiController::class, 'index']);
        Route::post('leaderboard-master', [\App\Http\Controllers\api\lms\LmsLeaderboardMasterApiController::class, 'store']);
        Route::get('leaderboard-master/{id}', [\App\Http\Controllers\api\lms\LmsLeaderboardMasterApiController::class, 'show'])
            ->where('id', '[0-9]+');
        Route::put('leaderboard-master/{id}', [\App\Http\Controllers\api\lms\LmsLeaderboardMasterApiController::class, 'update'])
            ->where('id', '[0-9]+');
        Route::delete('leaderboard-master/{id}', [\App\Http\Controllers\api\lms\LmsLeaderboardMasterApiController::class, 'destroy'])
            ->where('id', '[0-9]+');
    });

    // Social & Collaborative (doubt feed + conversations).
    Route::get('social-collaborative', [\App\Http\Controllers\api\lms\LmsSocialCollaborativeApiController::class, 'index']);
    Route::get('social-collaborative/lookups/subjects', [\App\Http\Controllers\api\lms\LmsSocialCollaborativeApiController::class, 'subjects']);
    Route::get('social-collaborative/lookups/chapters', [\App\Http\Controllers\api\lms\LmsSocialCollaborativeApiController::class, 'chapters']);
    Route::get('social-collaborative/lookups/topics', [\App\Http\Controllers\api\lms\LmsSocialCollaborativeApiController::class, 'topics']);
    Route::get('social-collaborative/{id}', [\App\Http\Controllers\api\lms\LmsSocialCollaborativeApiController::class, 'show'])
        ->where('id', '[0-9]+');
    Route::post('social-collaborative', [\App\Http\Controllers\api\lms\LmsSocialCollaborativeApiController::class, 'store']);
    Route::post('social-collaborative/{id}/comments', [\App\Http\Controllers\api\lms\LmsSocialCollaborativeApiController::class, 'storeComment'])
        ->where('id', '[0-9]+');
});

// ------------------------------------------------------------------
// LMS Result Dashboard - the "Results dashboard" tab beside "Exams" on
// LMS > Test > Exam. Teacher-side only, same gate as the exam screens.
// ------------------------------------------------------------------
Route::middleware(['api.session', 'staff.only'])->group(function () {
    Route::post('lms-result-dashboard/summary', [\App\Http\Controllers\api\lms\LmsResultDashboardApiController::class, 'summary']);
});

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

    // Corrected variants of index()/edit() used by the Admission Without Confirmation
    // Report - see indexWithoutConfirmationReport()/editWithoutConfirmationReport()
    // for why these are new methods/routes rather than edits to the ones above.
    Route::get('admission_without_confirmation_report_v2', 'indexWithoutConfirmationReport');
    Route::get('admission_without_confirmation_report_v2/{id}/edit', 'editWithoutConfirmationReport');
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
// Bulk routes are declared first so they are not shadowed by the {module} rules.
Route::post('transportation-setup/student-mappings/bulk', [TransportationApiController::class, 'bulkStore']);
Route::post('transportation-setup/student-mappings/bulk-delete', [TransportationApiController::class, 'bulkDestroy']);
Route::get('transportation-setup/{module}', [TransportationApiController::class, 'index']);
Route::post('transportation-setup/{module}', [TransportationApiController::class, 'store']);
Route::match(['put', 'patch'], 'transportation-setup/{module}/{id}', [TransportationApiController::class, 'update']);
Route::delete('transportation-setup/{module}/{id}', [TransportationApiController::class, 'destroy']);
Route::get('general-setup/{module}', [GeneralSetupApiController::class, 'index']);
Route::post('general-setup/{module}', [GeneralSetupApiController::class, 'store']);
Route::match(['put', 'patch'], 'general-setup/{module}/{id}', [GeneralSetupApiController::class, 'update']);
Route::delete('general-setup/{module}/{id}', [GeneralSetupApiController::class, 'destroy']);
Route::get('general-setup/{module}/tags', [GeneralSetupApiController::class, 'tags'])->where('module', 'templates');
Route::get('inventory/{module}', [InventoryApiController::class, 'index'])->where('module', '^(?!reports$).+');
Route::get('inventory/reports/{module}', [InventoryApiController::class, 'reportIndex']);
Route::get('inventory/receivables/items', [InventoryApiController::class, 'poItems']);
Route::post('inventory/{module}', [InventoryApiController::class, 'store'])->where('module', '^(?!reports$).+');
Route::post('inventory/receivables/multiple', [InventoryApiController::class, 'saveReceivables']);
Route::match(['put', 'patch'], 'inventory/{module}/{id}', [InventoryApiController::class, 'update'])->where('module', '^(?!reports$).+');
Route::delete('inventory/{module}/{id}', [InventoryApiController::class, 'destroy'])->where('module', '^(?!reports$).+');
Route::post('question-paper/search', [ApiQuestionPaperController::class, 'search']);
Route::post('fees-cancel/search', [feesCancelController::class, 'search']);

// Import Data API - stateless JSON entry points for the Next.js frontend.
// These mirror the legacy web import routes but return JSON instead of HTML.
Route::middleware('api.session')->prefix('import')->group(function () {
    Route::get('tables', [\App\Http\Controllers\api\ImportApiController::class, 'tables']);
    Route::post('parse', [\App\Http\Controllers\api\ImportApiController::class, 'parse']);
    Route::post('process', [\App\Http\Controllers\api\ImportApiController::class, 'process']);
    Route::post('match-fields', [\App\Http\Controllers\api\ImportApiController::class, 'matchFields']);
});

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

// Curriculum Planning - yearly syllabus overview (stats, subject x month grid, upcoming lessons, subject progress)
Route::match(['GET', 'POST'], 'intelligence/curriculum-planning', [\App\Http\Controllers\api\lms\CurriculumPlanningApiController::class, 'index']);

// Monthly Plan - calendar view of scheduled periods for a given month
Route::match(['GET', 'POST'], 'intelligence/monthly-plan', [\App\Http\Controllers\api\lms\MonthlyPlanApiController::class, 'index']);

// Lesson Plan detail - periods (+ concepts) for a date range, for the single-lesson detail page
Route::match(['GET', 'POST'], 'intelligence/lesson-plan-detail', [\App\Http\Controllers\api\lms\LessonPlanDetailApiController::class, 'index']);

// Lesson Plan periods - create / edit / delete a scheduled lesson (monthly-plan "Add lesson")
Route::post('intelligence/lesson-plan-periods', [\App\Http\Controllers\api\lms\LessonPlanPeriodApiController::class, 'store']);
Route::post('intelligence/lesson-plan-periods/{id}/update', [\App\Http\Controllers\api\lms\LessonPlanPeriodApiController::class, 'update']);
Route::post('intelligence/lesson-plan-periods/{id}/delete', [\App\Http\Controllers\api\lms\LessonPlanPeriodApiController::class, 'destroy']);

// Lesson Plan lookups - chapter and period-slot options for the "Add lesson" form
Route::match(['GET', 'POST'], 'intelligence/lesson-plan-lookup/chapters', [\App\Http\Controllers\api\lms\LessonPlanLookupApiController::class, 'chapters']);
Route::match(['GET', 'POST'], 'intelligence/lesson-plan-lookup/periods', [\App\Http\Controllers\api\lms\LessonPlanLookupApiController::class, 'periods']);

/*
| Lesson Intelligence - the four-phase lesson-plan generator.
|   Phase 0  capacity     how much teaching time the term actually has
|   Phase 1  macro-plan   chapters spread across the term's weeks
|   Phase 2  meso-plan    concepts placed into dated period slots
|   Phase 3  micro-plan   the LLM-written 5E content for a period
| Phases 0-2 are pure arithmetic and free to re-run; phase 3 costs one DeepSeek
| call per period, so it is only ever triggered explicitly.
*/
Route::prefix('lesson-intelligence')->group(function () {
    // Cascading selection - only combinations that have a real timetable.
    Route::match(['GET', 'POST'], 'dropdowns', [\App\Http\Controllers\api\lms\LessonIntelligenceApiController::class, 'dropdowns']);
    Route::match(['GET', 'POST'], 'dropdowns/filter', [\App\Http\Controllers\api\lms\LessonIntelligenceApiController::class, 'dropdownFilter']);

    // Phase 0 - read-only.
    Route::match(['GET', 'POST'], 'capacity', [\App\Http\Controllers\api\lms\LessonIntelligenceApiController::class, 'capacity']);
    Route::match(['GET', 'POST'], 'calendar-events', [\App\Http\Controllers\api\lms\LessonIntelligenceApiController::class, 'calendarEvents']);

    // Phase 1.
    Route::match(['GET', 'POST'], 'macro-plan/show', [\App\Http\Controllers\api\lms\LessonIntelligenceApiController::class, 'showMacroPlan']);
    Route::post('macro-plan', [\App\Http\Controllers\api\lms\LessonIntelligenceApiController::class, 'storeMacroPlan']);

    // Phase 2.
    Route::match(['GET', 'POST'], 'meso-plan/{planId}/teachers', [\App\Http\Controllers\api\lms\LessonIntelligenceApiController::class, 'mesoPlanTeachers']);
    Route::match(['GET', 'POST'], 'meso-plan/{planId}/periods', [\App\Http\Controllers\api\lms\LessonIntelligenceApiController::class, 'mesoPlanPeriods']);
    Route::post('meso-plan/{planId}', [\App\Http\Controllers\api\lms\LessonIntelligenceApiController::class, 'storeMesoPlan']);

    // Phase 3 - billable.
    Route::post('micro-plan/period/{periodId}', [\App\Http\Controllers\api\lms\LessonIntelligenceApiController::class, 'storeMicroPlan']);
    Route::post('micro-plan/plan/{planId}/batch', [\App\Http\Controllers\api\lms\LessonIntelligenceApiController::class, 'storeMicroPlanBatch']);
});

// Intelligence Question Generation - MCQ / narrative items via DeepSeek LLM -> lms_question_master
//
// Teacher-side and billable: one call can be ~17 sequential DeepSeek calls that
// write rows into lms_question_master. It therefore runs the full gate:
//   api.session   verifies the bearer JWT and hydrates the session. The tenant
//                 (sub_institute_id) and author (created_by) are read from that
//                 hydrated session, NOT from the request body, so a caller can
//                 no longer write AI questions into another school attributed
//                 to another user.
//   staff.only    rejects Student/Parent tokens.
//   throttle.qgen per-user spend cap (see config/deepseek.php), replacing the
//                 group's throttle:1000,1 which was no limit at all here.
Route::middleware(['api.session', 'staff.only', 'throttle.qgen'])->group(function () {
    Route::post('intelligence/questions/generate', [\App\Http\Controllers\api\lms\IntelligenceQuestionGenerationApiController::class, 'generate']);
});

// Semantic Intelligence - read-only chapter intelligence for presentation generators
Route::get('semantic-intelligence', [\App\Http\Controllers\api\lms\SemanticIntelligenceApiController::class, 'index']);
Route::get('semantic-intelligence/{extraction_id}/result', [\App\Http\Controllers\api\lms\SemanticIntelligenceApiController::class, 'show']);
Route::get('semantic-intelligence/rows', [\App\Http\Controllers\api\lms\SemanticIntelligenceApiController::class, 'rows']);

// Concept Intelligence tab names - renamed per institute, defaults in
// config/lms_concept_intelligence_tabs.php
Route::match(['GET', 'POST'], 'lms/concept-intelligence/tab-labels', [\App\Http\Controllers\api\lms\ConceptIntelligenceTabLabelApiController::class, 'index']);
Route::post('lms/concept-intelligence/tab-labels/update', [\App\Http\Controllers\api\lms\ConceptIntelligenceTabLabelApiController::class, 'update']);
Route::post('lms/concept-intelligence/tab-labels/reset', [\App\Http\Controllers\api\lms\ConceptIntelligenceTabLabelApiController::class, 'reset']);

Route::get('/departments', [\App\Http\Controllers\HRMS\departmentController::class, 'index']);
Route::get('/departments/create', [\App\Http\Controllers\HRMS\departmentController::class, 'create']);
Route::get('/department-employee-lists', [\App\Http\Controllers\HRMS\departmentController::class, 'departmentEmpLists']);
Route::get('/sub-department-list', [\App\Http\Controllers\HRMS\departmentController::class, 'subDepartmentList']);
Route::get('/department-employee-list', [\App\Http\Controllers\HRMS\departmentController::class, 'departmentEmployeeList']);
Route::get('/departments/hierarchy', [\App\Http\Controllers\HRMS\departmentController::class, 'hierarchy']);

// Department Management API - ported from hp_erp's DepartmentManagementController
// (departments-management resource). Reuses the existing departmentController
// which already owns hrms_departments for this page; hierarchy() above is untouched.
Route::get('/departments-management', [\App\Http\Controllers\HRMS\departmentController::class, 'indexManagement']);
Route::post('/departments-management', [\App\Http\Controllers\HRMS\departmentController::class, 'storeManagement']);
// Literal segments before the /{id} wildcard below, so the router doesn't
// misroute these as show($id='merge')/show($id='reorder').
Route::post('/departments-management/merge', [\App\Http\Controllers\HRMS\departmentController::class, 'merge']);
Route::post('/departments-management/reorder', [\App\Http\Controllers\HRMS\departmentController::class, 'reorder']);
Route::get('/departments-management/export', [\App\Http\Controllers\HRMS\departmentController::class, 'export']);
Route::get('/departments-management/employees', [\App\Http\Controllers\HRMS\departmentController::class, 'employees']);
// Staffing a department: transfer/assign employees in (with an optional
// job role of THIS department), or remove them. Backs DepartmentEmployeesPanel.
Route::post('/departments-management/{id}/employees', [\App\Http\Controllers\HRMS\departmentController::class, 'assignEmployees']);
Route::delete('/departments-management/{id}/employees', [\App\Http\Controllers\HRMS\departmentController::class, 'unassignEmployees']);
Route::get('/departments-management/{id}/impact', [\App\Http\Controllers\HRMS\departmentController::class, 'impact']);
Route::patch('/departments-management/{id}/head', [\App\Http\Controllers\HRMS\departmentController::class, 'setHead']);
Route::match(['put', 'patch'], '/departments-management/{id}', [\App\Http\Controllers\HRMS\departmentController::class, 'updateManagement']);
Route::delete('/departments-management/{id}', [\App\Http\Controllers\HRMS\departmentController::class, 'destroyManagement']);



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
// serves the Blade screens. Specific sub-routes are declared before the
// catch-all `{id}` routes so they are not swallowed by them.
Route::get('front-desk/report', [\App\Http\Controllers\api\FrontDeskApiController::class, 'report']);
Route::get('front-desk', [\App\Http\Controllers\api\FrontDeskApiController::class, 'index']);

// Photo/Video Gallery module - stateless JSON entry points for the Next.js frontend.
// Kept separate from front_desk\photo_video_gallaryController, which is unchanged
// and still serves the Blade screens. The delete route is declared before the
// {id} update route so it is not swallowed by it.
Route::get('front-desk/photo-video-gallery', [\App\Http\Controllers\api\PhotoVideoGallaryApiController::class, 'index']);
Route::post('front-desk/photo-video-gallery', [\App\Http\Controllers\api\PhotoVideoGallaryApiController::class, 'store']);
Route::post('front-desk/photo-video-gallery/{id}/delete', [\App\Http\Controllers\api\PhotoVideoGallaryApiController::class, 'destroy']);
Route::post('front-desk/photo-video-gallery/{id}', [\App\Http\Controllers\api\PhotoVideoGallaryApiController::class, 'update']);

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
| Prefix is `onboarding-modules` (not `onboarding`) to avoid colliding with
| the Talent Management /api/onboarding/* group registered in
| routes/talent_management.php. Both groups use the `api.session` middleware
| and are loaded via RouteServiceProvider, but Laravel overwrites the first
| route that matches a given URI with the last one registered — so without a
| unique prefix the Talent Management `GET overview` silently replaces this
| module-wise `GET overview`, feeding the Next.js onboarding frontend a
| KPI/totals payload instead of the modules array it expects.
|
*/
Route::group(['prefix' => 'onboarding-modules', 'middleware' => ['api.session']], function () {
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

/*
|--------------------------------------------------------------------------
| HRIT dashboard
|--------------------------------------------------------------------------
| Ported verbatim from hp_erp. These are additive: the legacy web routes
| under routes/hrms.php (HrmsController etc.) are unchanged.
*/
Route::get('/attendance-weekly', [\App\Http\Controllers\api\HRITDashboard\AttendanceApiController::class, 'weeklySummary']);
Route::get('/KPI-HRITDashboard', [\App\Http\Controllers\api\HRITDashboard\AttendanceApiController::class, 'KPI']);
Route::get('/employee-attendance-monthly-report', [\App\Http\Controllers\api\HRITDashboard\AttendanceApiController::class, 'employeeMonthlyReport']);

Route::get('/jobroles-by-department', [\App\Http\Controllers\api\HRITDashboard\JobroleApiController::class, 'getDepartmentWise']);
Route::get('/leave-distribution', [\App\Http\Controllers\api\HRITDashboard\LeaveDistribution::class, 'leaveDistribution']);

/*
|--------------------------------------------------------------------------
| Leave Management API
|--------------------------------------------------------------------------
| Token authenticated endpoints backing the Next.js Leave Management module
| (Dashboard, Leave Requests, Reports, Configuration). Every endpoint is
| scoped by sub_institute_id and the April-March leave year - see
| App\Http\Controllers\api\Leave\Concerns\ResolvesLeaveContext.
| Ported verbatim from hp_erp.
*/
Route::prefix('leave')->group(function () {
    // Dashboard
    Route::get('/dashboard', [\App\Http\Controllers\api\Leave\LeaveDashboardController::class, 'index']);
    Route::get('/trend', [\App\Http\Controllers\api\Leave\LeaveDashboardController::class, 'trend']);
    Route::get('/department-summary', [\App\Http\Controllers\api\Leave\LeaveDashboardController::class, 'departmentSummary']);
    Route::get('/type-distribution', [\App\Http\Controllers\api\Leave\LeaveDashboardController::class, 'typeDistribution']);
    Route::get('/holidays/upcoming', [\App\Http\Controllers\api\Leave\LeaveDashboardController::class, 'upcomingHolidays']);

    // Shared lookups
    Route::get('/options', [\App\Http\Controllers\api\Leave\LeaveOptionsController::class, 'index']);
    Route::get('/balances', [\App\Http\Controllers\api\Leave\LeaveOptionsController::class, 'balances']);

    // Leave requests
    Route::get('/requests', [\App\Http\Controllers\api\Leave\LeaveRequestApiController::class, 'index']);
    Route::post('/requests', [\App\Http\Controllers\api\Leave\LeaveRequestApiController::class, 'store']);
    Route::post('/requests/bulk-decision', [\App\Http\Controllers\api\Leave\LeaveRequestApiController::class, 'bulkDecision']);
    Route::get('/requests/{id}', [\App\Http\Controllers\api\Leave\LeaveRequestApiController::class, 'show'])->whereNumber('id');
    Route::post('/requests/{id}/decision', [\App\Http\Controllers\api\Leave\LeaveRequestApiController::class, 'decision'])->whereNumber('id');
    Route::delete('/requests/{id}', [\App\Http\Controllers\api\Leave\LeaveRequestApiController::class, 'destroy'])->whereNumber('id');

    // Reports
    Route::get('/reports/summary', [\App\Http\Controllers\api\Leave\LeaveReportApiController::class, 'summary']);
    Route::get('/reports/register', [\App\Http\Controllers\api\Leave\LeaveReportApiController::class, 'register']);
    Route::get('/reports/balance', [\App\Http\Controllers\api\Leave\LeaveReportApiController::class, 'balance']);

    // Configuration - leave types
    Route::get('/leave-types', [\App\Http\Controllers\api\Leave\LeaveTypeApiController::class, 'index']);
    Route::post('/leave-types', [\App\Http\Controllers\api\Leave\LeaveTypeApiController::class, 'store']);
    Route::put('/leave-types/{id}', [\App\Http\Controllers\api\Leave\LeaveTypeApiController::class, 'store'])->whereNumber('id');
    Route::patch('/leave-types/{id}/status', [\App\Http\Controllers\api\Leave\LeaveTypeApiController::class, 'toggleStatus'])->whereNumber('id');
    Route::delete('/leave-types/{id}', [\App\Http\Controllers\api\Leave\LeaveTypeApiController::class, 'destroy'])->whereNumber('id');

    // Configuration - holidays and weekly off pattern
    Route::get('/holidays', [\App\Http\Controllers\api\Leave\HolidayApiController::class, 'index']);
    Route::post('/holidays', [\App\Http\Controllers\api\Leave\HolidayApiController::class, 'store']);
    Route::put('/holidays/{id}', [\App\Http\Controllers\api\Leave\HolidayApiController::class, 'update'])->whereNumber('id');
    Route::delete('/holidays/{id}', [\App\Http\Controllers\api\Leave\HolidayApiController::class, 'destroy']);
    Route::get('/weekdays', [\App\Http\Controllers\api\Leave\HolidayApiController::class, 'weekdays']);
    Route::post('/weekdays', [\App\Http\Controllers\api\Leave\HolidayApiController::class, 'storeWeekdays']);

    // Configuration - approval workflow and role access
    Route::get('/workflow', [\App\Http\Controllers\api\Leave\LeaveWorkflowApiController::class, 'workflow']);
    Route::put('/workflow', [\App\Http\Controllers\api\Leave\LeaveWorkflowApiController::class, 'saveWorkflow']);
    Route::get('/roles', [\App\Http\Controllers\api\Leave\LeaveWorkflowApiController::class, 'roles']);
    Route::put('/roles', [\App\Http\Controllers\api\Leave\LeaveWorkflowApiController::class, 'saveRoles']);

    // Distribution - new controller, GET /api/leave-distribution above is
    // untouched and still serves its existing consumers.
    Route::get('/distribution', [\App\Http\Controllers\api\Leave\LeaveDistributionApiController::class, 'index']);
});

/*
|--------------------------------------------------------------------------
| Attendance Management API
|--------------------------------------------------------------------------
| Token authenticated, session free endpoints backing the Next.js Attendance
| Management module (Attendance Tracking + Attendance Reports). Ported
| verbatim from hp_erp. These are additive: the legacy web routes
| hrms-attendance, hrms-attendance-in-time/store, hrms-attendance-out-time/store,
| hrms-attendance-report and get-employees-list still point at
| App\Http\Controllers\HRMS\HrmsController, and /api/attendance-weekly plus
| /api/KPI-HRITDashboard still point at
| App\Http\Controllers\api\HRITDashboard\AttendanceApiController.
*/
Route::prefix('attendance')->group(function () {
    // Self service - my attendance calendar and punches
    Route::get('/my-attendance', [\App\Http\Controllers\api\Attendance\AttendanceTrackingApiController::class, 'myAttendance']);
    Route::post('/punch-in', [\App\Http\Controllers\api\Attendance\AttendanceTrackingApiController::class, 'punchIn']);
    Route::post('/punch-out', [\App\Http\Controllers\api\Attendance\AttendanceTrackingApiController::class, 'punchOut']);

    // Report lookups
    Route::get('/report-filters', [\App\Http\Controllers\api\Attendance\AttendanceReportApiController::class, 'filters']);
    Route::get('/employees', [\App\Http\Controllers\api\Attendance\AttendanceReportApiController::class, 'employees']);
    Route::get('/day-detail', [\App\Http\Controllers\api\Attendance\AttendanceReportApiController::class, 'dayDetail']);
    Route::get('/latest-activity-date', [\App\Http\Controllers\api\Attendance\AttendanceReportApiController::class, 'latestActivityDate']);

    // Dashboard analytics (department + employee scoped)
    Route::get('/weekly-summary', [\App\Http\Controllers\api\Attendance\AttendanceDashboardApiController::class, 'weeklySummary']);
    Route::get('/kpi', [\App\Http\Controllers\api\Attendance\AttendanceDashboardApiController::class, 'kpi']);
});






