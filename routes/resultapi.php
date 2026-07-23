<?php

use App\Http\Controllers\api\result\AllResultsApiController;
use App\Http\Controllers\api\result\ApproveMobileResultApiController;
use App\Http\Controllers\api\result\Cbse11T2ResultApiController;
use App\Http\Controllers\api\result\Cbse1t5ResultApiController;
use App\Http\Controllers\api\result\Cbse1t5T2ResultApiController;
use App\Http\Controllers\api\result\ClasswiseGradeReportApiController;
use App\Http\Controllers\api\result\ConsolidateReportApiController;
use App\Http\Controllers\api\result\CoScholasticApiController;
use App\Http\Controllers\api\result\CoScholasticMarksEntryApiController;
use App\Http\Controllers\api\result\CoScholasticMasterApiController;
use App\Http\Controllers\api\result\DropdownApiController;
use App\Http\Controllers\api\result\ExamCreationApiController;
use App\Http\Controllers\api\result\ExamMasterApiController;
use App\Http\Controllers\api\result\ExamTypeMasterApiController;
use App\Http\Controllers\api\result\GradeMasterApiController;
use App\Http\Controllers\api\result\MarksEntryApiController;
use App\Http\Controllers\api\result\OverallMarkReportApiController;
use App\Http\Controllers\api\result\ResultActivityMarksApiController;
use App\Http\Controllers\api\result\ResultActivityMarksV1ApiController;
use App\Http\Controllers\api\result\ResultActivityMasterApiController;
use App\Http\Controllers\api\result\ResultBookMasterApiController;
use App\Http\Controllers\api\result\ResultLookupApiController;
use App\Http\Controllers\api\result\ResultMasterApiController;
use App\Http\Controllers\api\result\ResultRemarkMasterApiController;
use App\Http\Controllers\api\result\ResultReportApiController;
use App\Http\Controllers\api\result\ResultSkillsetApiController;
use App\Http\Controllers\api\result\ResultTemplateApiController;
use App\Http\Controllers\api\result\StdGradeMappingApiController;
use App\Http\Controllers\api\result\StudentAttendanceMasterApiController;
use App\Http\Controllers\api\result\StudentResultApiController;
use App\Http\Controllers\api\result\StudentResultRemarksApiController;
use App\Http\Controllers\api\result\UploadResultApiController;
use App\Http\Controllers\api\result\WorkingDayMasterApiController;
use App\Http\Controllers\api\result\WrtProgressReportApiController;
use App\Http\Controllers\api\result\WrtReportApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Result Module REST API (Next.js frontend)
|--------------------------------------------------------------------------
| Stateless JSON APIs wrapping the existing Result module controllers.
| Auth: same ERP JWT (obtain user_token via POST /api/api-login), sent as
| "Authorization: Bearer <token>". The api.session middleware hydrates the
| legacy session context from the token so existing business logic runs
| unchanged. Docs: docs/result-api/README.md
*/

Route::group(['prefix' => 'result', 'middleware' => ['api.session']], function () {

    /* ---------------- Exam Master ---------------- */
    Route::get('exam-master/create', [ExamMasterApiController::class, 'create']);
    Route::get('exam-master/dropdown', [ExamMasterApiController::class, 'dropdown']);
    Route::delete('exam-master/bulk', [ExamMasterApiController::class, 'bulkDestroy']);
    Route::get('exam-master', [ExamMasterApiController::class, 'index']);
    Route::post('exam-master', [ExamMasterApiController::class, 'store']);
    Route::get('exam-master/{id}', [ExamMasterApiController::class, 'show']);
    Route::put('exam-master/{id}', [ExamMasterApiController::class, 'update']);
    Route::delete('exam-master/{id}', [ExamMasterApiController::class, 'destroy']);

    /* ---------------- Exam Type Master ---------------- */
    Route::get('exam-type-master/create', [ExamTypeMasterApiController::class, 'create']);
    Route::get('exam-type-master/dropdown', [ExamTypeMasterApiController::class, 'dropdown']);
    Route::delete('exam-type-master/bulk', [ExamTypeMasterApiController::class, 'bulkDestroy']);
    Route::get('exam-type-master', [ExamTypeMasterApiController::class, 'index']);
    Route::post('exam-type-master', [ExamTypeMasterApiController::class, 'store']);
    Route::get('exam-type-master/{id}', [ExamTypeMasterApiController::class, 'show']);
    Route::put('exam-type-master/{id}', [ExamTypeMasterApiController::class, 'update']);
    Route::delete('exam-type-master/{id}', [ExamTypeMasterApiController::class, 'destroy']);

    /* ---------------- Grade Master ---------------- */
    Route::get('grade-master/create-data/{id}', [GradeMasterApiController::class, 'createData']);
    Route::get('grade-master/dropdown', [GradeMasterApiController::class, 'dropdown']);
    Route::delete('grade-master/bulk', [GradeMasterApiController::class, 'bulkDestroy']);
    Route::get('grade-master', [GradeMasterApiController::class, 'index']);
    Route::post('grade-master', [GradeMasterApiController::class, 'store']);
    Route::delete('grade-master/{id}', [GradeMasterApiController::class, 'destroy']);

    /* ---------------- Standard - Grade Mapping ---------------- */
    Route::get('standard-grade-mapping/create', [StdGradeMappingApiController::class, 'create']);
    Route::get('standard-grade-mapping/dropdown', [StdGradeMappingApiController::class, 'dropdown']);
    Route::delete('standard-grade-mapping/bulk', [StdGradeMappingApiController::class, 'bulkDestroy']);
    Route::get('standard-grade-mapping', [StdGradeMappingApiController::class, 'index']);
    Route::post('standard-grade-mapping', [StdGradeMappingApiController::class, 'store']);
    Route::get('standard-grade-mapping/{id}', [StdGradeMappingApiController::class, 'show']);
    Route::put('standard-grade-mapping/{id}', [StdGradeMappingApiController::class, 'update']);
    Route::delete('standard-grade-mapping/{id}', [StdGradeMappingApiController::class, 'destroy']);

    /* ---------------- Working Day Master ---------------- */
    Route::delete('working-day-master/bulk', [WorkingDayMasterApiController::class, 'bulkDestroy']);
    Route::get('working-day-master', [WorkingDayMasterApiController::class, 'index']);
    Route::post('working-day-master', [WorkingDayMasterApiController::class, 'store']);
    Route::get('working-day-master/{id}', [WorkingDayMasterApiController::class, 'show']);
    Route::put('working-day-master/{id}', [WorkingDayMasterApiController::class, 'update']);
    Route::delete('working-day-master/{id}', [WorkingDayMasterApiController::class, 'destroy']);

    /* ---------------- Student Result Remark Master ---------------- */
    Route::get('result-remark-master/dropdown', [ResultRemarkMasterApiController::class, 'dropdown']);
    Route::delete('result-remark-master/bulk', [ResultRemarkMasterApiController::class, 'bulkDestroy']);
    Route::get('result-remark-master', [ResultRemarkMasterApiController::class, 'index']);
    Route::post('result-remark-master', [ResultRemarkMasterApiController::class, 'store']);
    Route::get('result-remark-master/{id}', [ResultRemarkMasterApiController::class, 'show']);
    Route::put('result-remark-master/{id}', [ResultRemarkMasterApiController::class, 'update']);
    Route::delete('result-remark-master/{id}', [ResultRemarkMasterApiController::class, 'destroy']);

    /* ---------------- Result Master ---------------- */
    Route::get('result-master/dropdown', [ResultMasterApiController::class, 'dropdownList']);
    Route::delete('result-master/bulk', [ResultMasterApiController::class, 'bulkDestroy']);
    Route::get('result-master', [ResultMasterApiController::class, 'index']);
    Route::post('result-master', [ResultMasterApiController::class, 'store']);
    Route::get('result-master/{id}', [ResultMasterApiController::class, 'show']);
    Route::put('result-master/{id}', [ResultMasterApiController::class, 'update']);
    Route::delete('result-master/{id}', [ResultMasterApiController::class, 'destroy']);

    /* ---------------- Exam Creation ---------------- */
    Route::get('exam-creation/create', [ExamCreationApiController::class, 'create']);
    Route::get('exam-creation/dropdown', [ExamCreationApiController::class, 'dropdownList']);
    Route::delete('exam-creation/bulk', [ExamCreationApiController::class, 'bulkDestroy']);
    Route::get('exam-creation', [ExamCreationApiController::class, 'index']);
    Route::post('exam-creation', [ExamCreationApiController::class, 'store']);
    Route::get('exam-creation/{id}', [ExamCreationApiController::class, 'show']);
    Route::put('exam-creation/{id}', [ExamCreationApiController::class, 'update']);
    Route::delete('exam-creation/{id}', [ExamCreationApiController::class, 'destroy']);

    /* ---------------- Result Book Master ---------------- */
    Route::get('result-book-master/dropdown', [ResultBookMasterApiController::class, 'dropdownList']);
    Route::delete('result-book-master/bulk', [ResultBookMasterApiController::class, 'bulkDestroy']);
    Route::get('result-book-master', [ResultBookMasterApiController::class, 'index']);
    Route::post('result-book-master', [ResultBookMasterApiController::class, 'store']);
    Route::get('result-book-master/{id}', [ResultBookMasterApiController::class, 'show']);
    Route::put('result-book-master/{id}', [ResultBookMasterApiController::class, 'update']);
    Route::delete('result-book-master/{id}', [ResultBookMasterApiController::class, 'destroy']);

    /* ---------------- Student Attendance Master ---------------- */
    Route::get('student-attendance-master/create', [StudentAttendanceMasterApiController::class, 'create']);
    Route::post('student-attendance-master', [StudentAttendanceMasterApiController::class, 'store']);

    /* ---------------- Consolidate Report ---------------- */
    Route::get('consolidate-report', [ConsolidateReportApiController::class, 'index']);

    /* ---------------- Co-Scholastic Master ---------------- */
    Route::get('co-scholastic-master/create', [CoScholasticMasterApiController::class, 'create']);
    Route::get('co-scholastic-master/dropdown', [CoScholasticMasterApiController::class, 'dropdown']);
    Route::delete('co-scholastic-master/bulk', [CoScholasticMasterApiController::class, 'bulkDestroy']);
    Route::get('co-scholastic-master', [CoScholasticMasterApiController::class, 'index']);
    Route::post('co-scholastic-master', [CoScholasticMasterApiController::class, 'store']);
    Route::get('co-scholastic-master/{id}', [CoScholasticMasterApiController::class, 'show']);
    Route::put('co-scholastic-master/{id}', [CoScholasticMasterApiController::class, 'update']);
    Route::delete('co-scholastic-master/{id}', [CoScholasticMasterApiController::class, 'destroy']);

    /* ---------------- Co-Scholastic (mapping) ---------------- */
    Route::get('co-scholastic/create', [CoScholasticApiController::class, 'create']);
    Route::get('co-scholastic/dropdown', [CoScholasticApiController::class, 'dropdown']);
    Route::delete('co-scholastic/bulk', [CoScholasticApiController::class, 'bulkDestroy']);
    Route::get('co-scholastic', [CoScholasticApiController::class, 'index']);
    Route::post('co-scholastic', [CoScholasticApiController::class, 'store']);
    Route::get('co-scholastic/{id}', [CoScholasticApiController::class, 'show']);
    Route::put('co-scholastic/{id}', [CoScholasticApiController::class, 'update']);
    Route::delete('co-scholastic/{id}', [CoScholasticApiController::class, 'destroy']);

    /* ---------------- HPC Skillset ---------------- */
    Route::get('hpc-skillset/create', [ResultSkillsetApiController::class, 'create']);
    Route::get('hpc-skillset/dropdown', [ResultSkillsetApiController::class, 'dropdown']);
    Route::delete('hpc-skillset/bulk', [ResultSkillsetApiController::class, 'bulkDestroy']);
    Route::get('hpc-skillset', [ResultSkillsetApiController::class, 'index']);
    Route::post('hpc-skillset', [ResultSkillsetApiController::class, 'store']);
    Route::get('hpc-skillset/{id}', [ResultSkillsetApiController::class, 'show']);
    Route::put('hpc-skillset/{id}', [ResultSkillsetApiController::class, 'update']);
    Route::delete('hpc-skillset/{id}', [ResultSkillsetApiController::class, 'destroy']);

    /* ---------------- HPC Activity Master ---------------- */
    Route::get('hpc-activity-master/create', [ResultActivityMasterApiController::class, 'create']);
    Route::get('hpc-activity-master/activity-lists', [ResultActivityMasterApiController::class, 'activityLists']);
    Route::delete('hpc-activity-master/sub-activity/{id}', [ResultActivityMasterApiController::class, 'subActivityDestroy']);
    Route::delete('hpc-activity-master/bulk', [ResultActivityMasterApiController::class, 'bulkDestroy']);
    Route::get('hpc-activity-master', [ResultActivityMasterApiController::class, 'index']);
    Route::post('hpc-activity-master', [ResultActivityMasterApiController::class, 'store']);
    Route::get('hpc-activity-master/{id}', [ResultActivityMasterApiController::class, 'show']);
    Route::put('hpc-activity-master/{id}', [ResultActivityMasterApiController::class, 'update']);
    Route::delete('hpc-activity-master/{id}', [ResultActivityMasterApiController::class, 'destroy']);

    /* ---------------- Marks Entry ---------------- */
    Route::get('marks-entry/create', [MarksEntryApiController::class, 'create']);
    Route::post('marks-entry/approve', [MarksEntryApiController::class, 'approve']);
    Route::post('marks-entry/marks-approval', [MarksEntryApiController::class, 'marksApproval']);
    Route::post('marks-entry/marks-dd', [MarksEntryApiController::class, 'marksDd']);
    Route::post('marks-entry/co-scholastic-marks-dd', [MarksEntryApiController::class, 'coScholasticMarksDd']);
    Route::post('marks-entry/get-result', [MarksEntryApiController::class, 'getResult']);
    Route::get('marks-entry', [MarksEntryApiController::class, 'index']);
    Route::post('marks-entry', [MarksEntryApiController::class, 'store']);

    /* ---------------- Co-Scholastic Marks Entry ---------------- */
    Route::get('co-scholastic-marks-entry/create', [CoScholasticMarksEntryApiController::class, 'create']);
    Route::post('co-scholastic-marks-entry/approve', [CoScholasticMarksEntryApiController::class, 'approve']);
    Route::get('co-scholastic-marks-entry', [CoScholasticMarksEntryApiController::class, 'index']);
    Route::post('co-scholastic-marks-entry', [CoScholasticMarksEntryApiController::class, 'store']);

    /* ---------------- HPC Activity Entry (result_activity_marks) ---------------- */
    Route::get('hpc-activity-entry/create', [ResultActivityMarksApiController::class, 'create']);
    Route::get('hpc-activity-entry', [ResultActivityMarksApiController::class, 'index']);
    Route::post('hpc-activity-entry', [ResultActivityMarksApiController::class, 'store']);

    /* ---------------- HPC Entry v1 (result_activity_marks_V1) ---------------- */
    Route::get('hpc-entry-v1/create', [ResultActivityMarksV1ApiController::class, 'create']);
    Route::get('hpc-entry-v1', [ResultActivityMarksV1ApiController::class, 'index']);
    Route::post('hpc-entry-v1', [ResultActivityMarksV1ApiController::class, 'store']);

    /* ---------------- Result Template ---------------- */
    Route::get('result-template/tags', [ResultTemplateApiController::class, 'tags']);
    Route::get('result-template/dropdown', [ResultTemplateApiController::class, 'dropdown']);
    Route::delete('result-template/bulk', [ResultTemplateApiController::class, 'bulkDestroy']);
    Route::get('result-template', [ResultTemplateApiController::class, 'index']);
    Route::post('result-template', [ResultTemplateApiController::class, 'store']);
    Route::get('result-template/{id}', [ResultTemplateApiController::class, 'show']);
    Route::put('result-template/{id}', [ResultTemplateApiController::class, 'update']);
    Route::delete('result-template/{id}', [ResultTemplateApiController::class, 'destroy']);

    /* ---------------- Student Result Remarks ---------------- */
    Route::get('student-result-remarks/students', [StudentResultRemarksApiController::class, 'students']);
    Route::get('student-result-remarks', [StudentResultRemarksApiController::class, 'index']);
    Route::post('student-result-remarks', [StudentResultRemarksApiController::class, 'store']);

    /* ---------------- Approve Mobile Result ---------------- */
    Route::get('approve-mobile-result/create', [ApproveMobileResultApiController::class, 'create']);
    Route::get('approve-mobile-result', [ApproveMobileResultApiController::class, 'index']);
    Route::post('approve-mobile-result', [ApproveMobileResultApiController::class, 'store']);

    /* ---------------- Upload Result ---------------- */
    Route::get('upload-result/create', [UploadResultApiController::class, 'create']);
    Route::get('upload-result', [UploadResultApiController::class, 'index']);
    Route::post('upload-result', [UploadResultApiController::class, 'store']);

    /* ---------------- All Results (student token) ---------------- */
    Route::get('all-results', [AllResultsApiController::class, 'index']);
    Route::get('all-results/{id}', [AllResultsApiController::class, 'show']);

    /* ---------------- Result personalize / PAL lookups ---------------- */
    Route::get('personalize-marks', [ResultLookupApiController::class, 'personalizeMarks']);
    Route::get('pal-marks', [ResultLookupApiController::class, 'palMarks']);
    Route::get('map-value', [ResultLookupApiController::class, 'mapValue']);
    Route::get('current-result', [ResultLookupApiController::class, 'currentResult']);

    /* ---------------- Result Report ---------------- */
    Route::get('result-report', [ResultReportApiController::class, 'index']);
    Route::post('result-report/show', [ResultReportApiController::class, 'show']);
    Route::get('result-report/standardwise-subjects', [ResultReportApiController::class, 'standardwiseSubjects']);
    Route::get('result-report/download-overall-excel', [ResultReportApiController::class, 'downloadOverallExcel']);

    /* ---------------- New Report Card (student result) ---------------- */
    Route::get('student-result/create', [StudentResultApiController::class, 'create']);
    Route::post('student-result/save-html', [StudentResultApiController::class, 'saveHtml']);
    Route::get('student-result', [StudentResultApiController::class, 'index']);
    Route::post('student-result', [StudentResultApiController::class, 'store']);

    /* ---------------- Classwise Grade Report ---------------- */
    Route::get('classwise-grade-report/create', [ClasswiseGradeReportApiController::class, 'create']);
    Route::get('classwise-grade-report/exam-create-names', [ClasswiseGradeReportApiController::class, 'examCreateNames']);
    Route::get('classwise-grade-report', [ClasswiseGradeReportApiController::class, 'index']);

    /* ---------------- CBSE Std 1-5 report card ---------------- */
    Route::get('cbse-1t5-result', [Cbse1t5ResultApiController::class, 'index']);
    Route::post('cbse-1t5-result/show', [Cbse1t5ResultApiController::class, 'show']);
    Route::post('cbse-1t5-result/save-html', [Cbse1t5ResultApiController::class, 'saveHtml']);
    Route::post('cbse-1t5-result/pdf', [Cbse1t5ResultApiController::class, 'pdf']);

    /* ---------------- CBSE Std 1-5 Term 2 report card ---------------- */
    Route::get('cbse-1t5-t2-result', [Cbse1t5T2ResultApiController::class, 'index']);
    Route::post('cbse-1t5-t2-result/show', [Cbse1t5T2ResultApiController::class, 'show']);

    /* ---------------- CBSE Std 11 Term 2 report card ---------------- */
    Route::get('cbse-11-t2-result', [Cbse11T2ResultApiController::class, 'index']);
    Route::post('cbse-11-t2-result/show', [Cbse11T2ResultApiController::class, 'show']);

    /* ---------------- WRT report ---------------- */
    Route::get('wrt-report', [WrtReportApiController::class, 'index']);
    Route::post('wrt-report/show', [WrtReportApiController::class, 'show']);

    /* ---------------- WRT progress report ---------------- */
    Route::get('wrt-progress-report', [WrtProgressReportApiController::class, 'index']);
    Route::post('wrt-progress-report/show', [WrtProgressReportApiController::class, 'show']);

    /* ---------------- Overall mark report ---------------- */
    Route::get('overall-mark-report', [OverallMarkReportApiController::class, 'index']);
    Route::post('overall-mark-report/show', [OverallMarkReportApiController::class, 'show']);

    /* ---------------- Dropdown / lookup APIs ---------------- */
    Route::get('dropdowns/standards', [DropdownApiController::class, 'standards']);
    Route::get('dropdowns/divisions', [DropdownApiController::class, 'divisions']);
    Route::get('dropdowns/subjects', [DropdownApiController::class, 'subjects']);
    Route::get('dropdowns/all-subjects', [DropdownApiController::class, 'allSubjects']);
    Route::get('dropdowns/exam-names', [DropdownApiController::class, 'examNames']);
    Route::get('dropdowns/exam-masters', [DropdownApiController::class, 'examMasters']);
    Route::get('dropdowns/subjects-by-create-exam', [DropdownApiController::class, 'subjectsByCreateExam']);
    Route::get('dropdowns/exams-by-create-exam', [DropdownApiController::class, 'examsByCreateExam']);
    Route::get('dropdowns/chapters', [DropdownApiController::class, 'chapters']);
    Route::get('dropdowns/topics', [DropdownApiController::class, 'topics']);
    Route::get('dropdowns/exams', [DropdownApiController::class, 'exams']);
    Route::get('dropdowns/co-scholastic-parents', [DropdownApiController::class, 'coScholasticParents']);
    Route::get('dropdowns/co-scholastics', [DropdownApiController::class, 'coScholastics']);
    Route::get('dropdowns/activity-masters', [DropdownApiController::class, 'activityMasters']);
});
