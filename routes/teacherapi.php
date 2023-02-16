<?php

use Illuminate\Http\Request;

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

Route::post('teacher_homescreen', 'api\teacherapiController@teacher_homescreen');

Route::POST('teacherSocialCollabrativeAPI', 'api\teacherapiController@teacherSocialCollabrativeAPI');

Route::POST('add_teacherSocialCollabrativeAPI', 'api\teacherapiController@add_teacherSocialCollabrativeAPI');

Route::POST('add_teacherContentAPI', 'api\teacherapiController@add_teacherContentAPI');

Route::POST('get_teacherVirtualClassroomAPI', 'api\teacherapiController@get_teacherVirtualClassroomAPI');

Route::POST('add_teacherVirtualClassroomAPI', 'api\teacherapiController@add_teacherVirtualClassroomAPI');

Route::POST('get_teacherResourceFieldAPI', 'api\teacherapiController@get_teacherResourceFieldAPI');

Route::POST('get_teacherResourceAPI', 'api\teacherapiController@get_teacherResourceAPI');

Route::POST('add_teacherResourceAPI', 'api\teacherapiController@add_teacherResourceAPI');

Route::POST('add_teacherQuestionAnswerAPI', 'api\teacherapiController@add_teacherQuestionAnswerAPI');

Route::POST('get_visitorAPI', 'visitor_management\visitor_masterController@get_visitorAPI');

Route::POST('add_visitorAPI', 'visitor_management\visitor_masterController@store');

Route::POST('get_visitorTypeAPI', 'visitor_management\visitor_masterController@get_visitorTypeAPI');

Route::POST('add_teacherStudentDisciplineAPI', 'api\teacherapiController@add_teacherStudentDisciplineAPI');

Route::POST('get_teacherSubjectAPI', 'api\teacherapiController@get_teacherSubjectAPI');

Route::POST('get_teacherContentAPI', 'api\teacherapiController@get_teacherContentAPI');

Route::POST('get_teacher_timetablewiseStandard', 'api\teacherapiController@get_teacher_timetablewiseStandard');

Route::POST('get_teacher_timetablewiseSubject', 'api\teacherapiController@get_teacher_timetablewiseSubject');

Route::POST('get_teacher_timetablewiseDivision', 'api\teacherapiController@get_teacher_timetablewiseDivision');

Route::POST('add_teacherLessonPlanning', 'api\teacherapiController@add_teacherLessonPlanning');

Route::POST('add_teacherLessonPlanningExecution', 'api\teacherapiController@add_teacherLessonPlanningExecution');

Route::POST('get_teacherPTMBookingList', 'api\teacherapiController@get_teacherPTMBookingList');

Route::POST('add_teacherPTMStatus', 'api\teacherapiController@add_teacherPTMStatus');

Route::POST('get_teacherResultExamList', 'api\teacherapiController@get_teacherResultExamList');

Route::POST('get_teacherResultCoscholasticParentList', 'api\teacherapiController@get_teacherResultCoscholasticParentList');

Route::POST('get_teacherResultCoscholasticList', 'api\teacherapiController@get_teacherResultCoscholasticList');

Route::POST('add_teacherExamSchedule', 'api\teacherapiController@add_teacherExamSchedule');

Route::POST('get_teachertaskAPI', 'api\teacherapiController@get_teachertaskAPI');

Route::POST('add_teachertaskAPI', 'api\teacherapiController@add_teachertaskAPI');

Route::post('get_teacherRequisitionAPI', 'api\teacherapiController@get_teacherRequisitionAPI');

Route::post('add_teacherRequisitionAPI', 'api\teacherapiController@add_teacherRequisitionAPI');

Route::POST('get_teachercomplaintAPI', 'api\teacherapiController@get_teachercomplaintAPI');

Route::POST('add_teachercomplaintAPI', 'api\teacherapiController@add_teachercomplaintAPI');

Route::POST('get_teacherExamSchedule', 'api\teacherapiController@get_teacherExamSchedule');
