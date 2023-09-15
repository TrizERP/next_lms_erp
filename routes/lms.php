<?php


use App\Http\Controllers\front_desk\book_list\book_listController;
use App\Http\Controllers\lms\assignment\annotateAssignmentController;
use App\Http\Controllers\lms\assignment\assignmentController;
use App\Http\Controllers\lms\assignment\assignmentSubmissionController;
use App\Http\Controllers\lms\bulk_chapter_uploadController;
use App\Http\Controllers\lms\chapterController;
use App\Http\Controllers\lms\contentController;
use App\Http\Controllers\lms\counselling\counselling_questionmasterController;
use App\Http\Controllers\lms\counselling\counsellingExamController;
use App\Http\Controllers\lms\counselling\lmsCounsellingController;
use App\Http\Controllers\lms\counselling\MBTIController;
use App\Http\Controllers\lms\courseController;
use App\Http\Controllers\lms\flashcard\flashcardController;
use App\Http\Controllers\lms\leaderboard\lbMasterController;
use App\Http\Controllers\lms\lessonplan\lms_lessonplanController;
use App\Http\Controllers\lms\lms_apiController;
use App\Http\Controllers\lms\lms_contentCategoryController;
use App\Http\Controllers\lms\lmsActivityStreamController;
use App\Http\Controllers\lms\lmsCommunicationController;
use App\Http\Controllers\lms\lmsDoubtController;
use App\Http\Controllers\lms\lmsDoubtConversationController;
use App\Http\Controllers\lms\lmsexamController;
use App\Http\Controllers\lms\lmsLeaderboardController;
use App\Http\Controllers\lms\lmsmappingController;
use App\Http\Controllers\lms\lmsPortfolioController;
use App\Http\Controllers\lms\lmsSocialCollabrotiveController;
use App\Http\Controllers\lms\lmsVirtualClassroomController;
use App\Http\Controllers\lms\locategoryController;
use App\Http\Controllers\lms\loindicatorController;
use App\Http\Controllers\lms\lomasterController;
use App\Http\Controllers\lms\onlineExamController;
use App\Http\Controllers\lms\questionmasterController;
use App\Http\Controllers\lms\questionpaperController;
use App\Http\Controllers\lms\reports\examWiseProgressReportController;
use App\Http\Controllers\lms\reports\studentReportController;
use App\Http\Controllers\lms\subtopicController;
use App\Http\Controllers\lms\teacher_resource\lms_teacherResourceController;
use App\Http\Controllers\lms\topicController;
use App\Http\Controllers\lms\questionWiseReportController;

use App\Http\Controllers\lms\virtualclassroomController;
use App\Http\Controllers\school_setup\sub_std_mapController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'lms', 'middleware' => ['session', 'menu', 'logRoute']], function () {
    Route::resource('chapter_master', chapterController::class);
    Route::resource('course_master', courseController::class);
    Route::resource('topic_master', topicController::class);
    Route::resource('subtopic_master', subtopicController::class);
    Route::get('chapter_search', [chapterController::class, 'chapter_search'])->name('chapter_search');
    Route::resource('content_master', contentController::class);
    Route::get('create_content_master', [contentController::class, 'createChapter'])->name('create_content_master');
    Route::post('store_content_master', [contentController::class, 'storeChapter'])->name('store_content_master');

    Route::resource('virtual_classroom_master', virtualclassroomController::class);

    Route::get('ajax_SubjectwiseChapter', [contentController::class, 'ajax_SubjectwiseChapter'])->name('ajax_SubjectwiseChapter');
    Route::get('ajax_ChapterwiseTopic', [contentController::class, 'ajax_ChapterwiseTopic'])->name('ajax_ChapterwiseTopic');

    Route::get('ajax_ChapterwiseLOmaster', [lomasterController::class, 'ajax_ChapterwiseLOmaster'])->name('ajax_ChapterwiseLOmaster');

    Route::resource('lo_master', lomasterController::class);

    Route::resource('lo_indicator', loindicatorController::class);

    Route::resource('lo_category', locategoryController::class);

    // multi delete questions
    Route::get('multi_delete_questions', [questionmasterController::class, 'ajax_multiDeleteQuestion'])->name('multi_delete_questions');

    Route::get('ajax_chapterDependencies', [chapterController::class, 'ajax_chapterDependencies'])->name('ajax_chapterDependencies');
    Route::get('ajax_topicDependencies', [topicController::class, 'ajax_topicDependencies'])->name('ajax_topicDependencies');
    Route::get('ajax_questionDependencies', [questionmasterController::class, 'ajax_questionDependencies'])
        ->name('ajax_questionDependencies');
    Route::get('ajax_subStdMappingDependencies', [sub_std_mapController::class, 'ajax_subStdMappingDependencies'])
        ->name('ajax_subStdMappingDependencies');
    Route::get('ajax_questionpaperDependencies', [questionpaperController::class, 'ajax_questionpaperDependencies'])
        ->name('ajax_questionpaperDependencies');

    Route::get('ajax_SubjectwiseLoCategory', [locategoryController::class, 'ajax_SubjectwiseLoCategory'])
        ->name('ajax_SubjectwiseLoCategory');
    Route::get('ajax_LoMasterwiseLoIndicator', [loindicatorController::class, 'ajax_LoMasterwiseLoIndicator'])
        ->name('ajax_LoMasterwiseLoIndicator');

    
    Route::resource('question_master', questionmasterController::class);
    Route::post('ajaxdestroyanswer_master', [questionmasterController::class, 'ajaxdestroyanswer_master'])->name('ajaxdestroyanswer_master');
    Route::get('question_chapter_master', [questionmasterController::class, 'indexChapter'])->name('question_chapter_master');

    Route::resource('question_paper', questionpaperController::class);
    Route::post('question_paper/search', [questionpaperController::class,'search']);
    Route::resource('bulk_chapter_upload', bulk_chapter_uploadController::class);
    Route::get('ajax_SubjectwiseQuestion', [questionpaperController::class, 'ajax_SubjectwiseQuestion'])->name('ajax_SubjectwiseQuestion');

    Route::get('ajax_LMS_MappingValue', [contentController::class, 'ajax_LMS_MappingValue'])->name('ajax_LMS_MappingValue');

    Route::get('course_search', [courseController::class, 'course_search'])->name('course_search');

    Route::get('ajax_LMS_StandardwiseSubject', [questionpaperController::class, 'ajax_LMS_StandardwiseSubject'])->name('ajax_LMS_StandardwiseSubject');

    Route::resource('lmsmapping', lmsmappingController::class);
    Route::resource('lmsexam', lmsexamController::class);
    Route::resource('lmsActivityStream', lmsActivityStreamController::class);
    Route::resource('lmsCommunication', lmsCommunicationController::class);
    Route::resource('lmsSocialCollabrotive', lmsSocialCollabrotiveController::class);
    Route::resource('lmsVirtualClassroom', lmsVirtualClassroomController::class);
    Route::resource('lmsPortfolio', lmsPortfolioController::class);
    Route::resource('lmsLeaderboard', lmsLeaderboardController::class);

    Route::get('ajax_AddLMS_MappingFromContent', [lmsmappingController::class, 'ajax_AddLMS_MappingFromContent'])
        ->name('ajax_AddLMS_MappingFromContent');
    Route::resource('online_exam', onlineExamController::class);
    Route::get('online_exam_attempt', [onlineExamController::class, 'online_exam_attempt'])->name('online_exam_attempt');

    Route::get('ajax_LMS_SubjectwiseChapter', [lmsPortfolioController::class, 'ajax_LMS_SubjectwiseChapter'])
        ->name('ajax_LMS_SubjectwiseChapter');

    Route::get('ajax_LMS_SubjectwiseChapterForBooklist', [book_listController::class, 'ajax_LMS_SubjectwiseChapterForBooklist'])
        ->name('ajax_LMS_SubjectwiseChapterForBooklist');

    Route::get('ajax_LMS_ChapterwiseTopic', [lmsPortfolioController::class, 'ajax_LMS_ChapterwiseTopic'])->name('ajax_LMS_ChapterwiseTopic');

    Route::post('ajax_lmsPortfolio_feedback', [lmsPortfolioController::class, 'ajax_lmsPortfolio_feedback'])->name('ajax_lmsPortfolio_feedback');

    Route::resource('lmsDoubt', lmsDoubtController::class);
    Route::resource('lmsCounselling', lmsCounsellingController::class);
    Route::resource('lmsCounsellingQuestion', counselling_questionmasterController::class);
    Route::post('ajaxdestroycounsellinganswer_master', [counselling_questionmasterController::class, 'ajaxdestroycounsellinganswer_master'])
        ->name('ajaxdestroycounsellinganswer_master');
    Route::resource('lmsCounsellingExam', counsellingExamController::class);

    Route::get('lmsIndustryListing', [lmsCounsellingController::class, 'lmsIndustryListing'])->name('lmsIndustryListing');
    Route::get('lmsIndustryListing/careersInIndustry/{id}', [lmsCounsellingController::class, 'careersInIndustry'])->name('lmsIndustryListing.careersInIndustry');
    Route::get('lmsIndustryListing/careersInIndustry/careerReport/{id}', [lmsCounsellingController::class, 'careerReport'])->name('lmsIndustryListing.careersInIndustry.careerReport');
    Route::get('lmsIndustryListing/careersInIndustry/careerReport/resources/{id}/{title}', [lmsCounsellingController::class, 'resources'])->name('lmsIndustryListing.careersInIndustry.careerReport.resources');

    Route::resource('lmsMBTIPaper', MBTIController::class);

    Route::get('ajax_getQuestionList', [onlineExamController::class, 'ajax_getQuestionList'])->name('ajax_getQuestionList');

    Route::resource('lmsDoubtConversation', lmsDoubtConversationController::class);

    Route::resource('lb_master', lbMasterController::class);

    Route::resource('lmsAssignment', assignmentController::class);

    Route::resource('lmsAssignment_submission', assignmentSubmissionController::class);

    Route::resource('lmsAnnotate_assignment', annotateAssignmentController::class);

    Route::resource('lmsStudent_report', studentReportController::class);

    Route::resource('lmsExamwise_progress_report', examWiseProgressReportController::class);
    Route::get('ajax_LMS_SubjectWiseExam', [examWiseProgressReportController::class, 'ajax_LMS_SubjectWiseExam'])
        ->name('ajax_LMS_SubjectWiseExam');

    Route::resource('lms_lessonplan', lms_lessonplanController::class);
    Route::get('ajax_Timetable', [lms_lessonplanController::class, 'ajax_Timetable'])->name('ajax_Timetable');
     Route::GET('ajax_daywisedata', 'lms\lessonplan\lms_lessonplanController@ajax_DayWiseData')->name('ajax_daywisedata');
    Route::GET('ajax_contentmasterdata', 'lms\lessonplan\lms_lessonplanController@ajax_contentMasterData')->name('ajax_contentmasterdata');
    Route::GET('ajax_questionpaperdata', 'lms\lessonplan\lms_lessonplanController@ajax_questionPaperData')->name('ajax_questionpaperdata');
    Route::GET('ajax_daywisedata', 'lms\lessonplan\lms_lessonplanController@ajax_DayWiseData')->name('ajax_daywisedata');
   
    Route::get('ajax_getTeacher', [lms_lessonplanController::class, 'ajax_getTeacher'])->name('ajax_getTeacher');

Route::get('questionReport', [questionWiseReportController::class, 'index'])->name('question_wise_report');
Route::post('show_question_wise_report',
    [questionWiseReportController::class, 'show_question_wise_report'])->name('show_question_wise_report');
    Route::resource('lms_content_category', lms_contentCategoryController::class);

    Route::get('ajax_getYouTubeSuggestion', [contentController::class, 'ajax_getYouTubeSuggestion'])->name('ajax_getYouTubeSuggestion');

    Route::resource('lms_teacherResource', lms_teacherResourceController::class);

    Route::resource('lms_flashcard', flashcardController::class);
    // Route::get('ajax_SaveAnnotations', 'lms\assignment\annotateAssignmentController@ajax_SaveAnnotations')->name('ajax_SaveAnnotations');

    Route::resource('subjectwise_graph', chapterController::class);

    //Route::get('questionReport', 'student\questionWiseReportController@index')->name('question_wise_report');
    //Route::post('show_question_wise_report', 'student\questionWiseReportController@show_question_wise_report')->name('show_question_wise_report');

});

Route::controller(lms_apiController::class)->group(function () {
    Route::post('/studentVirtualClassroomAPI', 'studentVirtualClassroomAPI');
    Route::post('/studentPortfolioAPI', 'studentPortfolioAPI');
    Route::post('/studentSocialCollabrativeAPI', 'studentSocialCollabrativeAPI');
    Route::post('/studentSubjectAPI', 'studentSubjectAPI');
    Route::post('/studentContentAPI', 'studentContentAPI');
    Route::post('/studentQuestionPaperListAPI', 'studentQuestionPaperListAPI');
    Route::post('/studentQuestionPaperAPI', 'studentQuestionPaperAPI');
    Route::post('/studentAssessmentAPI', 'studentAssessmentAPI');
    Route::post('/studentTransportAPI', 'studentTransportAPI');
    Route::post('/studentLeaderBoardAPI', 'studentLeaderBoardAPI');
    Route::post('/studentActivityStreamAPI', 'studentActivityStreamAPI');
    Route::post('/studentBookListAPI', 'studentBookListAPI');
    Route::post('/studentSyllabusAPI', 'studentSyllabusAPI');
    Route::post('/studentQuestionPaperSaveAPI', 'studentQuestionPaperSaveAPI');
    Route::post('/studentAssessmentDetailAPI', 'studentAssessmentDetailAPI');
    Route::post('/lmsCategorywiseSubjectAPI', 'lmsCategorywiseSubjectAPI');
    Route::post('/trizStandardAPI', 'trizStandardAPI');
});

