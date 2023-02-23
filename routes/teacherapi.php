<?php

use Illuminate\Http\Request;
use App\Http\Controllers\api\teacherapiController;

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

Route::POST('teacherSocialCollabrativeAPI', [teacherapiController::class, 'teacherSocialCollabrativeAPI']);

Route::POST('add_teacherSocialCollabrativeAPI', [teacherapiController::class, 'add_teacherSocialCollabrativeAPI']);

Route::POST('add_teacherContentAPI', [teacherapiController::class, 'add_teacherContentAPI']);

Route::POST('get_teacherVirtualClassroomAPI', [teacherapiController::class, 'get_teacherVirtualClassroomAPI']);

Route::POST('add_teacherVirtualClassroomAPI', [teacherapiController::class, 'add_teacherVirtualClassroomAPI']);

Route::POST('get_teacherResourceFieldAPI', [teacherapiController::class, 'get_teacherResourceFieldAPI']);

Route::POST('get_teacherResourceAPI', [teacherapiController::class, 'get_teacherResourceAPI']);

Route::POST('add_teacherResourceAPI', [teacherapiController::class, 'add_teacherResourceAPI']);

Route::POST('add_teacherQuestionAnswerAPI', [teacherapiController::class, 'add_teacherQuestionAnswerAPI']);

Route::POST('get_visitorAPI', [visitor_masterController::class, 'get_visitorAPI']);

Route::POST('add_visitorAPI', [visitor_masterController::class, 'store']);

Route::POST('get_visitorTypeAPI', [visitor_masterController::class, 'get_visitorTypeAPI']);

Route::POST('add_teacherStudentDisciplineAPI', [teacherapiController::class, 'add_teacherStudentDisciplineAPI']);

Route::POST('get_teacherSubjectAPI', [teacherapiController::class, 'get_teacherSubjectAPI']);

Route::POST('get_teacherContentAPI', [teacherapiController::class, 'get_teacherContentAPI']);

Route::POST('get_teacher_timetablewiseStandard', [teacherapiController::class, 'get_teacher_timetablewiseStandard']);

Route::POST('get_teacher_timetablewiseSubject', [teacherapiController::class, 'get_teacher_timetablewiseSubject']);

Route::POST('get_teacher_timetablewiseDivision', [teacherapiController::class, 'get_teacher_timetablewiseDivision']);

Route::POST('add_teacherLessonPlanning', [teacherapiController::class, 'add_teacherLessonPlanning']);

Route::POST('add_teacherLessonPlanningExecution', [teacherapiController::class, 'add_teacherLessonPlanningExecution']);

Route::POST('get_teacherPTMBookingList', [teacherapiController::class, 'get_teacherPTMBookingList']);

Route::POST('add_teacherPTMStatus', [teacherapiController::class, 'add_teacherPTMStatus']);

Route::POST('get_teacherResultExamList', [teacherapiController::class, 'get_teacherResultExamList']);

Route::POST('get_teacherResultCoscholasticParentList', [teacherapiController::class, 'get_teacherResultCoscholasticParentList']);

Route::POST('get_teacherResultCoscholasticList', [teacherapiController::class, 'get_teacherResultCoscholasticList']);

Route::POST('add_teacherExamSchedule', [teacherapiController::class, 'add_teacherExamSchedule']);

Route::POST('get_teachertaskAPI', [teacherapiController::class, 'get_teachertaskAPI']);

Route::POST('add_teachertaskAPI', [teacherapiController::class, 'add_teachertaskAPI']);

Route::post('get_teacherRequisitionAPI', [teacherapiController::class, 'get_teacherRequisitionAPI']);

Route::post('add_teacherRequisitionAPI', [teacherapiController::class, 'add_teacherRequisitionAPI']);

Route::POST('get_teachercomplaintAPI', [teacherapiController::class, 'get_teachercomplaintAPI']);

Route::POST('add_teachercomplaintAPI', [teacherapiController::class, 'add_teachercomplaintAPI']);

Route::POST('get_teacherExamSchedule', [teacherapiController::class, 'get_teacherExamSchedule']);
