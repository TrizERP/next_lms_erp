<?php

use App\Http\Controllers\api\apiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\settings\instituteDetailController;
use App\Http\Controllers\neo4jGraph\StudentResultGraphController;
use App\Http\Controllers\StudentGraphController;
use App\Http\Controllers\api\ApiLoginController;



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

