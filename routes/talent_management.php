<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\api\TalentManagement\TalentDashboardController;

use App\Http\Controllers\api\TalentManagement\Recruitment\JobPostingController;
use App\Http\Controllers\api\TalentManagement\Recruitment\JobApplicationController;
use App\Http\Controllers\api\TalentManagement\Recruitment\CandidateController;
use App\Http\Controllers\api\TalentManagement\Recruitment\InterviewScheduleController;
use App\Http\Controllers\api\TalentManagement\Recruitment\InterviewController;
use App\Http\Controllers\api\TalentManagement\Recruitment\InterviewPanelController;
use App\Http\Controllers\api\TalentManagement\Recruitment\FeedbackController;
use App\Http\Controllers\api\TalentManagement\Recruitment\ScreeningResultController;
use App\Http\Controllers\api\TalentManagement\Recruitment\OfferController;
use App\Http\Controllers\api\TalentManagement\Recruitment\AcquisitionController;
use App\Http\Controllers\api\TalentManagement\Recruitment\AnalyzeJDController;

use App\Http\Controllers\api\TalentManagement\Onboarding\OnboardingOverviewController;
use App\Http\Controllers\api\TalentManagement\Onboarding\OnboardingJourneyController;
use App\Http\Controllers\api\TalentManagement\Onboarding\OnboardingTaskController;
use App\Http\Controllers\api\TalentManagement\Onboarding\OnboardingDocumentController;
use App\Http\Controllers\api\TalentManagement\Onboarding\OnboardingNoteController;
use App\Http\Controllers\api\TalentManagement\Onboarding\OnboardingProbationController;

use App\Http\Controllers\api\TalentManagement\Performance\PerformanceOverviewController;
use App\Http\Controllers\api\TalentManagement\Performance\PerformanceCycleController;
use App\Http\Controllers\api\TalentManagement\Performance\PerformanceReviewController;
use App\Http\Controllers\api\TalentManagement\Performance\PerformanceGoalController;
use App\Http\Controllers\api\TalentManagement\Performance\PerformanceAppraisalController;
use App\Http\Controllers\api\TalentManagement\Performance\PerformanceCompensationController;
use App\Http\Controllers\api\TalentManagement\Performance\PerformanceBonusController;
use App\Http\Controllers\api\TalentManagement\Performance\PerformanceCalibrationController;
use App\Http\Controllers\api\TalentManagement\Performance\PerformanceActivityController;
use App\Http\Controllers\api\TalentManagement\Performance\PerformanceSavedViewController;

use App\Http\Controllers\api\TalentManagement\Mobility\MobilityOverviewController;
use App\Http\Controllers\api\TalentManagement\Mobility\MobilityJobController;
use App\Http\Controllers\api\TalentManagement\Mobility\MobilityApplicationController;
use App\Http\Controllers\api\TalentManagement\Mobility\MobilityTransferController;
use App\Http\Controllers\api\TalentManagement\Mobility\MobilityPromotionController;
use App\Http\Controllers\api\TalentManagement\Mobility\MobilitySuccessionController;
use App\Http\Controllers\api\TalentManagement\Mobility\MobilityTalentPoolController;

use App\Http\Controllers\api\TalentManagement\Offboarding\OffboardingController;

use App\Http\Controllers\api\TalentManagement\Administration\AdminWorkflowController;

/*
|--------------------------------------------------------------------------
| Talent Management
|--------------------------------------------------------------------------
|
| Registered stateless (mapApiRoutes-style: prefix('api')->middleware('api'))
| in RouteServiceProvider::mapTalentManagementRoutes(). Every group below
| additionally requires `api.session` (real JWT validation + legacy-session
| hydration), matching the established convention for this generation of
| Next.js-facing modules (see routes/api.php's "Module-wise onboarding"
| block and app/Http/Controllers/api/OnboardingApiController.php) — never
| the `session` + `type=API` convention of the older screens.
|
| An as-is migration of G2G's (hp_erp) Talent Management module. URLs,
| HTTP methods and path params are kept identical to the source so the
| ported frontend service files need no URL changes beyond the shared
| `/api` base. See the module's individual controllers for per-feature
| porting notes (each documents what, if anything, could not be ported
| 1:1 and why).
|
*/
Route::middleware(['api.session'])->group(function () {

    // ---------------------------------------------------------------
    // Talent Dashboard
    // ---------------------------------------------------------------
    Route::get('talent/dashboard', [TalentDashboardController::class, 'index']);
    Route::get('talent/dashboard/filters', [TalentDashboardController::class, 'filters']);

    // ---------------------------------------------------------------
    // Recruitment
    // ---------------------------------------------------------------
    Route::get('job-postings', [JobPostingController::class, 'index']);
    Route::post('job-postings', [JobPostingController::class, 'store']);
    Route::put('job-postings/{id}', [JobPostingController::class, 'update']);
    Route::delete('job-postings/{id}', [JobPostingController::class, 'destroy']);
    Route::get('talent/team-overview', [JobPostingController::class, 'getHiringStatus']);
    Route::post('gemini/analyze-jd', [AnalyzeJDController::class, 'analyze']);

    Route::get('job-applications', [JobApplicationController::class, 'index']);
    Route::get('job-applications/shortlisted', [JobApplicationController::class, 'getShortlistedCandidates']);
    Route::get('job-applications/candidate/{candidate_id}', [JobApplicationController::class, 'getCandidateApplications']);
    Route::get('job-applications/{id}', [JobApplicationController::class, 'show']);
    Route::post('job-applications', [JobApplicationController::class, 'store']);
    Route::put('job-applications/{id}', [JobApplicationController::class, 'update']);
    Route::post('job-applications/{id}/status', [JobApplicationController::class, 'updateStatus']);

    Route::get('candidate', [CandidateController::class, 'getCandidate']);
    Route::get('candidate-pipeline', [InterviewScheduleController::class, 'candidatepipeline']);

    Route::get('interview-details', [InterviewScheduleController::class, 'index']);
    Route::post('interview-schedules', [InterviewScheduleController::class, 'store']);
    Route::put('interview-schedules', [InterviewScheduleController::class, 'customUpdate']);
    Route::put('interview-schedules/{id}', [InterviewScheduleController::class, 'update']);

    Route::get('positions', [InterviewController::class, 'getPositions']);
    Route::get('interviewers', [InterviewController::class, 'getInterviewers']);
    Route::post('interviews/{id}/decision', [InterviewController::class, 'recordDecision']);

    Route::get('interview-panel/list', [InterviewPanelController::class, 'getInterviewPanel']);
    Route::get('interview-panel/users', [InterviewPanelController::class, 'getInterviewers']);
    Route::post('interview-panel/store', [InterviewPanelController::class, 'storeinterviewer']);
    Route::put('interview-panel/update/{id}', [InterviewPanelController::class, 'update']);
    Route::delete('interview-panel/delete/{id}', [InterviewPanelController::class, 'destroy']);

    Route::post('evaluation', [FeedbackController::class, 'storeFeedback']);
    Route::get('feedback', [FeedbackController::class, 'getAllFeedback']);
    Route::get('pending-feedback', [FeedbackController::class, 'getPendingFeedback']);
    Route::get('feedback/{id}', [FeedbackController::class, 'getFeedback']);
    Route::put('feedback/{id}', [FeedbackController::class, 'updateFeedback']);
    Route::delete('feedback/{id}', [FeedbackController::class, 'destroy']);

    Route::post('talent-screening-results', [ScreeningResultController::class, 'store']);
    Route::get('talent-screening-results/candidate/{candidate_id}', [ScreeningResultController::class, 'show']);

    Route::get('offers', [OfferController::class, 'index']);
    Route::post('talent-offers', [OfferController::class, 'store']);
    Route::post('talent-offers/{id}/reject', [OfferController::class, 'reject']);
    Route::get('talent-offer-letter/{offerId}', [OfferController::class, 'getOfferLetter']);
    Route::get('talent-templates', [OfferController::class, 'getTemplates']);

    Route::post('talent-acquisition/kpis', [AcquisitionController::class, 'getKpis']);
    Route::post('talent-acquisition/dropoff', [AcquisitionController::class, 'getDropoff']);
    Route::post('talent-acquisition/funnel', [AcquisitionController::class, 'getFunnelData']);
    Route::post('talent-acquisition/requisitions', [AcquisitionController::class, 'getRequisitions']);

    // ---------------------------------------------------------------
    // Onboarding
    // ---------------------------------------------------------------
    Route::prefix('onboarding')->group(function () {
        Route::get('overview', [OnboardingOverviewController::class, 'index']);
        Route::get('filters', [OnboardingOverviewController::class, 'filters']);

        Route::get('journeys', [OnboardingJourneyController::class, 'index']);
        Route::post('journeys', [OnboardingJourneyController::class, 'store']);
        Route::post('journeys/from-offer/{offerId}', [OnboardingJourneyController::class, 'storeFromOffer'])->whereNumber('offerId');
        Route::get('journeys/{id}', [OnboardingJourneyController::class, 'show'])->whereNumber('id');
        Route::put('journeys/{id}', [OnboardingJourneyController::class, 'update'])->whereNumber('id');
        Route::delete('journeys/{id}', [OnboardingJourneyController::class, 'destroy'])->whereNumber('id');

        Route::get('journeys/{journeyId}/stages', [OnboardingJourneyController::class, 'stages'])->whereNumber('journeyId');
        Route::put('stages/{id}', [OnboardingJourneyController::class, 'updateStage'])->whereNumber('id');
        Route::post('stages/{id}/complete', [OnboardingJourneyController::class, 'completeStage'])->whereNumber('id');

        Route::get('journeys/{journeyId}/contacts', [OnboardingJourneyController::class, 'contacts'])->whereNumber('journeyId');
        Route::get('journeys/{journeyId}/timeline', [OnboardingJourneyController::class, 'timeline'])->whereNumber('journeyId');

        Route::get('workstreams', [OnboardingTaskController::class, 'workstreams']);
        Route::post('tasks/bulk', [OnboardingTaskController::class, 'bulk']);
        Route::get('tasks', [OnboardingTaskController::class, 'index']);
        Route::post('tasks', [OnboardingTaskController::class, 'store']);
        Route::put('tasks/{id}', [OnboardingTaskController::class, 'update'])->whereNumber('id');
        Route::post('tasks/{id}/complete', [OnboardingTaskController::class, 'complete'])->whereNumber('id');
        Route::delete('tasks/{id}', [OnboardingTaskController::class, 'destroy'])->whereNumber('id');

        Route::get('journeys/{journeyId}/documents', [OnboardingDocumentController::class, 'index'])->whereNumber('journeyId');
        Route::post('journeys/{journeyId}/documents', [OnboardingDocumentController::class, 'store'])->whereNumber('journeyId');
        Route::match(['put', 'post'], 'documents/{id}', [OnboardingDocumentController::class, 'update'])->whereNumber('id');
        Route::delete('documents/{id}', [OnboardingDocumentController::class, 'destroy'])->whereNumber('id');

        Route::get('journeys/{journeyId}/notes', [OnboardingNoteController::class, 'index'])->whereNumber('journeyId');
        Route::post('journeys/{journeyId}/notes', [OnboardingNoteController::class, 'store'])->whereNumber('journeyId');
        Route::put('notes/{id}', [OnboardingNoteController::class, 'update'])->whereNumber('id');
        Route::delete('notes/{id}', [OnboardingNoteController::class, 'destroy'])->whereNumber('id');

        Route::get('probation', [OnboardingProbationController::class, 'index']);
        Route::put('probation/{journeyId}', [OnboardingProbationController::class, 'update'])->whereNumber('journeyId');
        Route::post('probation/{journeyId}/confirm', [OnboardingProbationController::class, 'confirm'])->whereNumber('journeyId');
        Route::post('probation/{journeyId}/extend', [OnboardingProbationController::class, 'extend'])->whereNumber('journeyId');
        Route::post('probation/{journeyId}/terminate', [OnboardingProbationController::class, 'terminate'])->whereNumber('journeyId');
    });

    // ---------------------------------------------------------------
    // Performance Reviews & Appraisals (+ Compensation)
    // ---------------------------------------------------------------
    Route::prefix('performance')->group(function () {
        Route::get('overview', [PerformanceOverviewController::class, 'index']);
        Route::get('filters', [PerformanceOverviewController::class, 'filters']);
        Route::get('team-comparison', [PerformanceOverviewController::class, 'teamComparison']);
        Route::get('timeline', [PerformanceOverviewController::class, 'timeline']);

        Route::get('cycles', [PerformanceCycleController::class, 'index']);
        Route::post('cycles', [PerformanceCycleController::class, 'store']);
        Route::get('cycles/{id}', [PerformanceCycleController::class, 'show'])->whereNumber('id');
        Route::put('cycles/{id}', [PerformanceCycleController::class, 'update'])->whereNumber('id');
        Route::delete('cycles/{id}', [PerformanceCycleController::class, 'destroy'])->whereNumber('id');
        Route::post('cycles/{id}/launch', [PerformanceCycleController::class, 'launch'])->whereNumber('id');
        Route::post('cycles/{id}/close', [PerformanceCycleController::class, 'close'])->whereNumber('id');

        Route::get('reviews/board', [PerformanceReviewController::class, 'board']);
        Route::post('reviews/bulk', [PerformanceReviewController::class, 'bulk']);
        Route::get('reviews', [PerformanceReviewController::class, 'index']);
        Route::get('reviews/{id}', [PerformanceReviewController::class, 'show'])->whereNumber('id');
        Route::put('reviews/{id}', [PerformanceReviewController::class, 'update'])->whereNumber('id');
        Route::delete('reviews/{id}', [PerformanceReviewController::class, 'destroy'])->whereNumber('id');
        Route::post('reviews/{id}/advance', [PerformanceReviewController::class, 'advance'])->whereNumber('id');
        Route::post('reviews/{id}/reminder', [PerformanceReviewController::class, 'sendReminder'])->whereNumber('id');

        Route::get('reviews/{reviewId}/notes', [PerformanceActivityController::class, 'notes'])->whereNumber('reviewId');
        Route::post('reviews/{reviewId}/notes', [PerformanceActivityController::class, 'storeNote'])->whereNumber('reviewId');
        Route::put('notes/{id}', [PerformanceActivityController::class, 'updateNote'])->whereNumber('id');
        Route::delete('notes/{id}', [PerformanceActivityController::class, 'destroyNote'])->whereNumber('id');

        Route::get('reviews/{reviewId}/attachments', [PerformanceActivityController::class, 'attachments'])->whereNumber('reviewId');
        Route::post('reviews/{reviewId}/attachments', [PerformanceActivityController::class, 'storeAttachment'])->whereNumber('reviewId');
        Route::delete('attachments/{id}', [PerformanceActivityController::class, 'destroyAttachment'])->whereNumber('id');

        Route::get('activity/filters', [PerformanceActivityController::class, 'filters']);
        Route::get('activity', [PerformanceActivityController::class, 'index']);

        Route::get('goals', [PerformanceGoalController::class, 'index']);
        Route::post('goals', [PerformanceGoalController::class, 'store']);
        Route::put('goals/{id}', [PerformanceGoalController::class, 'update'])->whereNumber('id');
        Route::delete('goals/{id}', [PerformanceGoalController::class, 'destroy'])->whereNumber('id');

        Route::post('appraisals/bulk', [PerformanceAppraisalController::class, 'bulk']);
        Route::get('appraisals', [PerformanceAppraisalController::class, 'index']);
        Route::post('appraisals', [PerformanceAppraisalController::class, 'store']);
        Route::put('appraisals/{id}', [PerformanceAppraisalController::class, 'update'])->whereNumber('id');
        Route::put('appraisals/{id}/decision', [PerformanceAppraisalController::class, 'decision'])->whereNumber('id');
        Route::delete('appraisals/{id}', [PerformanceAppraisalController::class, 'destroy'])->whereNumber('id');

        Route::post('compensation/bulk', [PerformanceCompensationController::class, 'bulk']);
        Route::get('compensation', [PerformanceCompensationController::class, 'index']);
        Route::post('compensation', [PerformanceCompensationController::class, 'store']);
        Route::put('compensation/{id}', [PerformanceCompensationController::class, 'update'])->whereNumber('id');
        Route::put('compensation/{id}/decision', [PerformanceCompensationController::class, 'decision'])->whereNumber('id');
        Route::delete('compensation/{id}', [PerformanceCompensationController::class, 'destroy'])->whereNumber('id');

        Route::post('bonus/bulk', [PerformanceBonusController::class, 'bulk']);
        Route::get('bonus', [PerformanceBonusController::class, 'index']);
        Route::post('bonus', [PerformanceBonusController::class, 'store']);
        Route::put('bonus/{id}', [PerformanceBonusController::class, 'update'])->whereNumber('id');
        Route::put('bonus/{id}/decision', [PerformanceBonusController::class, 'decision'])->whereNumber('id');
        Route::delete('bonus/{id}', [PerformanceBonusController::class, 'destroy'])->whereNumber('id');

        Route::get('calibration-sessions', [PerformanceCalibrationController::class, 'index']);
        Route::post('calibration-sessions', [PerformanceCalibrationController::class, 'store']);
        Route::put('calibration-sessions/{id}', [PerformanceCalibrationController::class, 'update'])->whereNumber('id');
        Route::delete('calibration-sessions/{id}', [PerformanceCalibrationController::class, 'destroy'])->whereNumber('id');
        Route::get('calibration-sessions/{id}/grid', [PerformanceCalibrationController::class, 'grid'])->whereNumber('id');
        Route::put('calibration-sessions/{id}/calibrate', [PerformanceCalibrationController::class, 'calibrate'])->whereNumber('id');
        Route::post('calibration-sessions/{id}/lock', [PerformanceCalibrationController::class, 'lock'])->whereNumber('id');

        Route::get('saved-views', [PerformanceSavedViewController::class, 'index']);
        Route::post('saved-views', [PerformanceSavedViewController::class, 'store']);
        Route::put('saved-views/{id}', [PerformanceSavedViewController::class, 'update'])->whereNumber('id');
        Route::delete('saved-views/{id}', [PerformanceSavedViewController::class, 'destroy'])->whereNumber('id');
    });

    // ---------------------------------------------------------------
    // Mobility & Succession
    // ---------------------------------------------------------------
    Route::prefix('mobility')->group(function () {
        Route::get('overview', [MobilityOverviewController::class, 'index']);
        Route::get('filters', [MobilityOverviewController::class, 'filters']);

        Route::get('jobs', [MobilityJobController::class, 'index']);
        Route::post('jobs', [MobilityJobController::class, 'store']);
        Route::get('jobs/{id}', [MobilityJobController::class, 'show'])->whereNumber('id');
        Route::put('jobs/{id}', [MobilityJobController::class, 'update'])->whereNumber('id');
        Route::delete('jobs/{id}', [MobilityJobController::class, 'destroy'])->whereNumber('id');

        Route::get('applications', [MobilityApplicationController::class, 'index']);
        Route::post('applications', [MobilityApplicationController::class, 'store']);
        Route::put('applications/{id}', [MobilityApplicationController::class, 'update'])->whereNumber('id');

        Route::get('transfers', [MobilityTransferController::class, 'index']);
        Route::post('transfers', [MobilityTransferController::class, 'store']);
        Route::put('transfers/{id}', [MobilityTransferController::class, 'update'])->whereNumber('id');

        Route::get('promotions', [MobilityPromotionController::class, 'index']);
        Route::post('promotions', [MobilityPromotionController::class, 'store']);
        Route::put('promotions/{id}', [MobilityPromotionController::class, 'update'])->whereNumber('id');

        Route::get('successions', [MobilitySuccessionController::class, 'index']);
        Route::post('successions', [MobilitySuccessionController::class, 'store']);
        Route::put('successions/{id}', [MobilitySuccessionController::class, 'update'])->whereNumber('id');
        Route::delete('successions/{id}', [MobilitySuccessionController::class, 'destroy'])->whereNumber('id');

        Route::get('pools', [MobilityTalentPoolController::class, 'index']);
        Route::post('pools', [MobilityTalentPoolController::class, 'store']);
        Route::get('pools/{id}/members', [MobilityTalentPoolController::class, 'members'])->whereNumber('id');
        Route::post('pools/{id}/members', [MobilityTalentPoolController::class, 'addMember'])->whereNumber('id');
        Route::delete('pools/{id}/members/{userId}', [MobilityTalentPoolController::class, 'removeMember'])->whereNumber('id')->whereNumber('userId');
    });

    // ---------------------------------------------------------------
    // Offboarding
    // ---------------------------------------------------------------
    Route::prefix('offboarding')->group(function () {
        Route::get('overview', [OffboardingController::class, 'overview']);
        Route::get('filters', [OffboardingController::class, 'filters']);
        Route::get('cases', [OffboardingController::class, 'index']);
        Route::post('cases', [OffboardingController::class, 'store']);
        Route::get('cases/{id}', [OffboardingController::class, 'show'])->whereNumber('id');
        Route::put('cases/{id}', [OffboardingController::class, 'update'])->whereNumber('id');
        Route::post('cases/{id}/status', [OffboardingController::class, 'updateStatus'])->whereNumber('id');
        Route::post('cases/{id}/clearance', [OffboardingController::class, 'updateClearance'])->whereNumber('id');
        Route::post('cases/{id}/documents', [OffboardingController::class, 'updateDocuments'])->whereNumber('id');
        Route::post('cases/{id}/comments', [OffboardingController::class, 'addComment'])->whereNumber('id');
        Route::post('cases/{id}/exit-interview', [OffboardingController::class, 'updateExitInterview'])->whereNumber('id');
        Route::delete('cases/{id}', [OffboardingController::class, 'destroy'])->whereNumber('id');
    });

    // ---------------------------------------------------------------
    // Administration
    // ---------------------------------------------------------------
    Route::get('talent/admin/workflows', [AdminWorkflowController::class, 'index']);
});
