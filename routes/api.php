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
Route::post('lms-chapters', [ApiLmsCourseController::class, 'chapters']);
Route::post('lms-chapter-content', [ApiLmsCourseController::class, 'chapterContent']);
Route::post('lms-questions', [ApiLmsCourseController::class, 'getLmsQuestions']);
Route::post('lms-chapters/store', [ApiLmsCourseController::class, 'storeChapter']);
Route::post('lms-create-content', [ApiLmsCourseController::class, 'createContent']);
Route::post('lms-store-content', [ApiLmsCourseController::class, 'storeContent']);
Route::post('lms-content-mapping-values', [ApiLmsCourseController::class, 'getContentMappingValues']);
Route::post('lms-store-subject', [ApiLmsCourseController::class, 'storeSubject']);
Route::post('lms-homework/get-subjects', [\App\Http\Controllers\api\lms\StudentHomeworkApiController::class, 'getSubjects']);
Route::post('lms-homework/get-chapters', [\App\Http\Controllers\api\lms\StudentHomeworkApiController::class, 'getChapters']);


Route::apiResource('question-paper', ApiQuestionPaperController::class);
Route::post('question-paper/search', [ApiQuestionPaperController::class, 'search']);

// Intelligence Lesson Plan - Lesson Plan -> Period -> Concepts hierarchy
Route::match(['GET', 'POST'], 'intelligence/lesson-plans', [\App\Http\Controllers\api\lms\IntelligenceLessonPlanApiController::class, 'index']);

/*
|--------------------------------------------------------------------------
| Intelligent Document Management System (IDMS) API v1
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->group(function () {
    Route::get('documents', [\App\Http\Controllers\api\v1\DocumentController::class, 'index']);
    Route::post('documents', [\App\Http\Controllers\api\v1\DocumentController::class, 'store']);
    Route::get('documents/{id}', [\App\Http\Controllers\api\v1\DocumentController::class, 'show']);
    Route::patch('documents/{id}', [\App\Http\Controllers\api\v1\DocumentController::class, 'update']);
    Route::delete('documents/{id}', [\App\Http\Controllers\api\v1\DocumentController::class, 'destroy']);
    Route::post('documents/{id}/confirm', [\App\Http\Controllers\api\v1\DocumentController::class, 'confirm']);
    Route::post('documents/{id}/tags', [\App\Http\Controllers\api\v1\DocumentController::class, 'updateTags']);
    Route::get('documents/{id}/preview', [\App\Http\Controllers\api\v1\DocumentController::class, 'preview']);
    Route::get('documents/{id}/download', [\App\Http\Controllers\api\v1\DocumentController::class, 'download']);
    Route::get('documents/{id}/versions', [\App\Http\Controllers\api\v1\DocumentController::class, 'getVersions']);
    Route::post('documents/{id}/versions', [\App\Http\Controllers\api\v1\DocumentController::class, 'addVersion']);
    Route::post('documents/{id}/versions/{versionNumber}/restore', [\App\Http\Controllers\api\v1\DocumentController::class, 'restoreVersion']);
    Route::get('documents/{id}/related', [\App\Http\Controllers\api\v1\DocumentController::class, 'related']);

    Route::post('search/parse', [\App\Http\Controllers\api\v1\DocumentController::class, 'parseSearch']);
    Route::get('browse/tree', [\App\Http\Controllers\api\v1\DocumentController::class, 'tree']);
    Route::get('tags', [\App\Http\Controllers\api\v1\DocumentController::class, 'tags']);
    Route::get('audit', [\App\Http\Controllers\api\v1\DocumentController::class, 'audit']);
});


