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

Route::controller(teacherapiController::class)->group(function () {
    Route::post('teacher_homescreen', 'teacher_homescreen');

    Route::post('teacherSocialCollabrativeAPI', 'teacherSocialCollabrativeAPI');

    Route::post('add_teacherSocialCollabrativeAPI', 'add_teacherSocialCollabrativeAPI');

    Route::post('add_teacherContentAPI', 'add_teacherContentAPI');

    Route::post('get_teacherVirtualClassroomAPI', 'get_teacherVirtualClassroomAPI');

    Route::post('add_teacherVirtualClassroomAPI', 'add_teacherVirtualClassroomAPI');

    Route::post('get_teacherResourceFieldAPI', 'get_teacherResourceFieldAPI');

    Route::post('get_teacherResourceAPI', 'get_teacherResourceAPI');

    Route::post('add_teacherResourceAPI', 'add_teacherResourceAPI');

    Route::post('add_teacherQuestionAnswerAPI', 'add_teacherQuestionAnswerAPI');

    Route::post('add_teacherStudentDisciplineAPI', 'add_teacherStudentDisciplineAPI');

    Route::post('get_teacherSubjectAPI', 'get_teacherSubjectAPI');

    Route::post('get_teacherContentAPI', 'get_teacherContentAPI');

    Route::post('get_teacher_timetablewiseStandard', 'get_teacher_timetablewiseStandard');

    Route::post('get_teacher_timetablewiseSubject', 'get_teacher_timetablewiseSubject');

    Route::post('get_teacher_timetablewiseDivision', 'get_teacher_timetablewiseDivision');

    Route::post('add_teacherLessonPlanning', 'add_teacherLessonPlanning');

    Route::post('add_teacherLessonPlanningExecution', 'add_teacherLessonPlanningExecution');

    Route::post('get_teacherPTMBookingList', 'get_teacherPTMBookingList');

    Route::post('add_teacherPTMStatus', 'add_teacherPTMStatus');

    Route::post('get_teacherResultExamList', 'get_teacherResultExamList');

    Route::post('get_teacherResultCoscholasticParentList',
        'get_teacherResultCoscholasticParentList');

    Route::post('get_teacherResultCoscholasticList', 'get_teacherResultCoscholasticList');

    Route::post('add_teacherExamSchedule', 'add_teacherExamSchedule');

    Route::post('get_teachertaskAPI', 'get_teachertaskAPI');

    Route::post('add_teachertaskAPI', 'add_teachertaskAPI');

    Route::post('get_teacherRequisitionAPI', 'get_teacherRequisitionAPI');

    Route::post('add_teacherRequisitionAPI', 'add_teacherRequisitionAPI');

    Route::post('get_teachercomplaintAPI', 'get_teachercomplaintAPI');

    Route::post('add_teachercomplaintAPI', 'add_teachercomplaintAPI');

    Route::post('get_teacherExamSchedule', 'get_teacherExamSchedule');

});

Route::controller(visitor_masterController::class)->group(function () {
    Route::post('get_visitorAPI', 'get_visitorAPI');

    Route::post('add_visitorAPI', 'store');

    Route::post('get_visitorTypeAPI', 'get_visitorTypeAPI');
});

