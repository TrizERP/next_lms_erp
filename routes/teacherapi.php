<?php

use App\Http\Controllers\api\teacherapiController;
use App\Http\Controllers\visitor_management\visitor_masterController;
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

Route::post('teacher_homescreen', [teacherapiController::class, 'teacher_homescreen']);

Route::post('teacherSocialCollabrativeAPI', [teacherapiController::class, 'teacherSocialCollabrativeAPI']);

Route::post('add_teacherSocialCollabrativeAPI', [teacherapiController::class, 'add_teacherSocialCollabrativeAPI']);

Route::post('add_teacherContentAPI', [teacherapiController::class, 'add_teacherContentAPI']);

Route::post('get_teacherVirtualClassroomAPI', [teacherapiController::class, 'get_teacherVirtualClassroomAPI']);

Route::post('add_teacherVirtualClassroomAPI', [teacherapiController::class, 'add_teacherVirtualClassroomAPI']);

Route::post('get_teacherResourceFieldAPI', [teacherapiController::class, 'get_teacherResourceFieldAPI']);

Route::post('get_teacherResourceAPI', [teacherapiController::class, 'get_teacherResourceAPI']);

Route::post('add_teacherResourceAPI', [teacherapiController::class, 'add_teacherResourceAPI']);

Route::post('add_teacherQuestionAnswerAPI', [teacherapiController::class, 'add_teacherQuestionAnswerAPI']);

Route::post('get_visitorAPI', [visitor_masterController::class, 'get_visitorAPI']);

Route::post('add_visitorAPI', [visitor_masterController::class, 'store']);

Route::post('get_visitorTypeAPI', [visitor_masterController::class, 'get_visitorTypeAPI']);

Route::post('add_teacherStudentDisciplineAPI', [teacherapiController::class, 'add_teacherStudentDisciplineAPI']);

Route::post('get_teacherSubjectAPI', [teacherapiController::class, 'get_teacherSubjectAPI']);

Route::post('get_teacherContentAPI', [teacherapiController::class, 'get_teacherContentAPI']);

Route::post('get_teacher_timetablewiseStandard', [teacherapiController::class, 'get_teacher_timetablewiseStandard']);

Route::post('get_teacher_timetablewiseSubject', [teacherapiController::class, 'get_teacher_timetablewiseSubject']);

Route::post('get_teacher_timetablewiseDivision', [teacherapiController::class, 'get_teacher_timetablewiseDivision']);

Route::post('add_teacherLessonPlanning', [teacherapiController::class, 'add_teacherLessonPlanning']);

Route::post('add_teacherLessonPlanningExecution', [teacherapiController::class, 'add_teacherLessonPlanningExecution']);

Route::post('get_teacherPTMBookingList', [teacherapiController::class, 'get_teacherPTMBookingList']);

Route::post('add_teacherPTMStatus', [teacherapiController::class, 'add_teacherPTMStatus']);

Route::post('get_teacherResultExamList', [teacherapiController::class, 'get_teacherResultExamList']);

Route::post('get_teacherResultCoscholasticParentList',
    [teacherapiController::class, 'get_teacherResultCoscholasticParentList']);

Route::post('get_teacherResultCoscholasticList', [teacherapiController::class, 'get_teacherResultCoscholasticList']);

Route::post('add_teacherExamSchedule', [teacherapiController::class, 'add_teacherExamSchedule']);

Route::post('get_teachertaskAPI', [teacherapiController::class, 'get_teachertaskAPI']);

Route::post('add_teachertaskAPI', [teacherapiController::class, 'add_teachertaskAPI']);

Route::post('get_teacherRequisitionAPI', [teacherapiController::class, 'get_teacherRequisitionAPI']);

Route::post('add_teacherRequisitionAPI', [teacherapiController::class, 'add_teacherRequisitionAPI']);

Route::post('get_teachercomplaintAPI', [teacherapiController::class, 'get_teachercomplaintAPI']);

Route::post('add_teachercomplaintAPI', [teacherapiController::class, 'add_teachercomplaintAPI']);

Route::post('get_teacherExamSchedule', [teacherapiController::class, 'get_teacherExamSchedule']);
