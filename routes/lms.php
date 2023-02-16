<?php
Route::group(['prefix' => 'lms', 'middleware' => ['session', 'menu', 'logRoute']], function () {
    Route::resource('chapter_master', 'lms\chapterController');
    Route::resource('course_master', 'lms\courseController');
    Route::resource('topic_master', 'lms\topicController');
    Route::resource('subtopic_master', 'lms\subtopicController');
    Route::GET('chapter_search', 'lms\chapterController@chapter_search')->name('chapter_search');
    Route::resource('content_master', 'lms\contentController');
    Route::GET('create_content_master', 'lms\contentController@createChapter')->name('create_content_master');
    Route::POST('store_content_master', 'lms\contentController@storeChapter')->name('store_content_master');

    Route::resource('virtual_classroom_master', 'lms\virtualclassroomController');

    Route::GET('ajax_SubjectwiseChapter', 'lms\contentController@ajax_SubjectwiseChapter')->name('ajax_SubjectwiseChapter');
    Route::GET('ajax_ChapterwiseTopic', 'lms\contentController@ajax_ChapterwiseTopic')->name('ajax_ChapterwiseTopic');

    Route::GET('ajax_ChapterwiseLOmaster', 'lms\lomasterController@ajax_ChapterwiseLOm
        aster')->name('ajax_ChapterwiseLOmaster');

    Route::resource('lo_master', 'lms\lomasterController');

    Route::resource('lo_indicator', 'lms\loindicatorController');

    Route::resource('lo_category', 'lms\locategoryController');

    // multi delete questions
    Route::GET('multi_delete_questions', 'lms\questionmasterController@ajax_multiDeleteQuestion')->name('multi_delete_questions');

    Route::GET('ajax_chapterDependencies', 'lms\chapterController@ajax_chapterDependencies')->name('ajax_chapterDependencies');
    Route::GET('ajax_topicDependencies', 'lms\topicController@ajax_topicDependencies')->name('ajax_topicDependencies');
    Route::GET('ajax_questionDependencies', 'lms\questionmasterController@ajax_questionDependencies')->name('ajax_questionDependencies');
    Route::GET('ajax_subStdMappingDependencies', 'school_setup\sub_std_mapController@ajax_subStdMappingDependencies')->name('ajax_subStdMappingDependencies');
    Route::GET('ajax_questionpaperDependencies', 'lms\questionpaperController@ajax_questionpaperDependencies')->name('ajax_questionpaperDependencies');

    Route::GET('ajax_SubjectwiseLoCategory', 'lms\locategoryController@ajax_SubjectwiseLoCategory')->name('ajax_SubjectwiseLoCategory');
    Route::GET('ajax_LoMasterwiseLoIndicator', 'lms\loindicatorController@ajax_LoMasterwiseLoIndicator')->name('ajax_LoMasterwiseLoIndicator');

    Route::resource('question_master', 'lms\questionmasterController');
    Route::POST('ajaxdestroyanswer_master', 'lms\questionmasterController@ajaxdestroyanswer_master')->name('ajaxdestroyanswer_master');
    Route::GET('question_chapter_master', 'lms\questionmasterController@indexChapter')->name('question_chapter_master');

    Route::resource('question_paper', 'lms\questionpaperController');
    Route::resource('bulk_chapter_upload', 'lms\bulk_chapter_uploadController');
    Route::GET('ajax_SubjectwiseQuestion', 'lms\questionpaperController@ajax_SubjectwiseQuestion')->name('ajax_SubjectwiseQuestion');

    Route::GET('ajax_LMS_MappingValue', 'lms\contentController@ajax_LMS_MappingValue')->name('ajax_LMS_MappingValue');

    Route::GET('course_search', 'lms\courseController@course_search')->name('course_search');

    Route::GET('ajax_LMS_StandardwiseSubject', 'lms\questionpaperController@ajax_LMS_StandardwiseSubject')->name('ajax_LMS_StandardwiseSubject');

    Route::resource('lmsmapping', 'lms\lmsmappingController');
    Route::resource('lmsexam', 'lms\lmsexamController');
    Route::resource('lmsActivityStream', 'lms\lmsActivityStreamController');
    Route::resource('lmsCommunication', 'lms\lmsCommunicationController');
    Route::resource('lmsSocialCollabrotive', 'lms\lmsSocialCollabrotiveController');
    Route::resource('lmsVirtualClassroom', 'lms\lmsVirtualClassroomController');
    Route::resource('lmsPortfolio', 'lms\lmsPortfolioController');
    Route::resource('lmsLeaderboard', 'lms\lmsLeaderboardController');

    Route::GET('ajax_AddLMS_MappingFromContent', 'lms\lmsmappingController@ajax_AddLMS_MappingFromContent')->name('ajax_AddLMS_MappingFromContent');
    Route::resource('online_exam', 'lms\onlineExamController');
    Route::GET('online_exam_attempt', 'lms\onlineExamController@online_exam_attempt')->name('online_exam_attempt');

    Route::GET('ajax_LMS_SubjectwiseChapter', 'lms\lmsPortfolioController@ajax_LMS_SubjectwiseChapter')->name('ajax_LMS_SubjectwiseChapter');

    Route::GET('ajax_LMS_SubjectwiseChapterForBooklist', 'front_desk\book_list\book_listController@ajax_LMS_SubjectwiseChapterForBooklist')->name('ajax_LMS_SubjectwiseChapterForBooklist');

    Route::GET('ajax_LMS_ChapterwiseTopic', 'lms\lmsPortfolioController@ajax_LMS_ChapterwiseTopic')->name('ajax_LMS_ChapterwiseTopic');

    Route::POST('ajax_lmsPortfolio_feedback', 'lms\lmsPortfolioController@ajax_lmsPortfolio_feedback')->name('ajax_lmsPortfolio_feedback');

    Route::resource('lmsDoubt', 'lms\lmsDoubtController');
    Route::resource('lmsCounselling', 'lms\counselling\lmsCounsellingController');
    Route::resource('lmsCounsellingQuestion', 'lms\counselling\counselling_questionmasterController');
    Route::POST('ajaxdestroycounsellinganswer_master', 'lms\counselling\counselling_questionmasterController@ajaxdestroycounsellinganswer_master')->name('ajaxdestroycounsellinganswer_master');
    Route::resource('lmsCounsellingExam', 'lms\counselling\counsellingExamController');

    Route::resource('lmsMBTIPaper', 'lms\counselling\MBTIController');

    Route::GET('ajax_getQuestionList', 'lms\onlineExamController@ajax_getQuestionList')->name('ajax_getQuestionList');

    Route::resource('lmsDoubtConversation', 'lms\lmsDoubtConversationController');

    Route::resource('lb_master', 'lms\leaderboard\lbMasterController');

    Route::resource('lmsAssignment', 'lms\assignment\assignmentController');

    Route::resource('lmsAssignment_submission', 'lms\assignment\assignmentSubmissionController');

    Route::resource('lmsAnnotate_assignment', 'lms\assignment\annotateAssignmentController');

    Route::resource('lmsStudent_report', 'lms\reports\studentReportController');

    Route::resource('lmsExamwise_progress_report', 'lms\reports\examWiseProgressReportController');
    Route::GET('ajax_LMS_SubjectWiseExam', 'lms\reports\examWiseProgressReportController@ajax_LMS_SubjectWiseExam')->name('ajax_LMS_SubjectWiseExam');

    Route::resource('lms_lessonplan', 'lms\lessonplan\lms_lessonplanController');
    Route::GET('ajax_Timetable', 'lms\lessonplan\lms_lessonplanController@ajax_Timetable')->name('ajax_Timetable');
    Route::GET('ajax_getTeacher', 'lms\lessonplan\lms_lessonplanController@ajax_getTeacher')->name('ajax_getTeacher');

    Route::resource('lms_content_category', 'lms\lms_contentCategoryController');

    Route::GET('ajax_getYouTubeSuggestion', 'lms\contentController@ajax_getYouTubeSuggestion')->name('ajax_getYouTubeSuggestion');

    Route::resource('lms_teacherResource', 'lms\teacher_resource\lms_teacherResourceController');

    Route::resource('lms_flashcard', 'lms\flashcard\flashcardController');
    // Route::GET('ajax_SaveAnnotations', 'lms\assignment\annotateAssignmentController@ajax_SaveAnnotations')->name('ajax_SaveAnnotations');

    Route::resource('subjectwise_graph', 'lms\chapterController');

    //Route::GET('questionReport', 'student\questionWiseReportController@index')->name('question_wise_report');
    //Route::POST('show_question_wise_report', 'student\questionWiseReportController@show_question_wise_report')->name('show_question_wise_report');

});

Route::POST('/studentVirtualClassroomAPI', 'lms\lms_apiController@studentVirtualClassroomAPI');
Route::POST('/studentPortfolioAPI', 'lms\lms_apiController@studentPortfolioAPI');
Route::POST('/studentSocialCollabrativeAPI', 'lms\lms_apiController@studentSocialCollabrativeAPI');
Route::POST('/studentSubjectAPI', 'lms\lms_apiController@studentSubjectAPI');
Route::POST('/studentContentAPI', 'lms\lms_apiController@studentContentAPI');
Route::POST('/studentQuestionPaperListAPI', 'lms\lms_apiController@studentQuestionPaperListAPI');
Route::POST('/studentQuestionPaperAPI', 'lms\lms_apiController@studentQuestionPaperAPI');
Route::POST('/studentAssessmentAPI', 'lms\lms_apiController@studentAssessmentAPI');
Route::POST('/studentTransportAPI', 'lms\lms_apiController@studentTransportAPI');
Route::POST('/studentLeaderBoardAPI', 'lms\lms_apiController@studentLeaderBoardAPI');
Route::POST('/studentActivityStreamAPI', 'lms\lms_apiController@studentActivityStreamAPI');
Route::POST('/studentBookListAPI', 'lms\lms_apiController@studentBookListAPI');
Route::POST('/studentSyllabusAPI', 'lms\lms_apiController@studentSyllabusAPI');
Route::POST('/studentQuestionPaperSaveAPI', 'lms\lms_apiController@studentQuestionPaperSaveAPI');
Route::POST('/studentAssessmentDetailAPI', 'lms\lms_apiController@studentAssessmentDetailAPI');
Route::POST('/lmsCategorywiseSubjectAPI', 'lms\lms_apiController@lmsCategorywiseSubjectAPI');
Route::POST('/trizStandardAPI', 'lms\lms_apiController@trizStandardAPI');