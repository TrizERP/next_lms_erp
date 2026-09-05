<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| LMS (People & Competency) — G2G migration
|--------------------------------------------------------------------------
|
| PACKAGE 0 (shared scaffolding) skeleton for the G2G "LMS" module, migrated
| into LMS-K12 under People & Competency (Next.js frontend at
| app/people-competency/lms/**, see routeMapper.ts's G2G_LMS_ROUTE_NAME_MAP).
| Registered stateless (mapApiRoutes-style: prefix('api')->middleware('api'))
| in RouteServiceProvider::mapG2gLmsRoutes(), so every path below is actually
| reachable at `api/g2g-lms/<segment>`.
|
| Middleware: ['api.session', 'staff.only'] — matches the majority sibling
| convention under this same People & Competency area (Talent Management,
| Task Management and Competency Management — the module structurally
| closest to this one, itself nested as a Talent Management submenu — all
| use this exact pair; only Organization Management uses 'api.session' alone).
| See PACKAGE 0's final report for the full reasoning.
|
| This is NOT the existing native LMS (app/Http/Controllers/lms,
| routes/lms.php) or the H5P/PAL/Exam modules already served under
| routes/lms.php's route names — those are a separate, existing K12 module
| and are left completely untouched. This file only ever grows the new
| App\Http\Controllers\G2gLms\* controller namespace.
|
| Packages 1-4 fill in the actual route definitions (GET/POST/PUT/DELETE per
| controller action) inside each screen's prefix group below — do not add
| routes outside your assigned screen's group.
*/

Route::middleware(['api.session', 'staff.only'])->group(function () {

    Route::prefix('g2g-lms')->group(function () {

        // ---------------------------------------------------------------
        // Learning Dashboard — PACKAGE 1
        // ---------------------------------------------------------------
        Route::prefix('learning-dashboard')->group(function () {
            Route::get('enrolled-courses', [\App\Http\Controllers\G2gLms\LearningDashboardController::class, 'enrolledCourses']);
            Route::get('available-courses', [\App\Http\Controllers\G2gLms\LearningDashboardController::class, 'availableCourses']);
            Route::post('enroll', [\App\Http\Controllers\G2gLms\LearningDashboardController::class, 'enroll']);
            Route::get('skill-progress', [\App\Http\Controllers\G2gLms\LearningDashboardController::class, 'skillProgress']);
            Route::get('streak', [\App\Http\Controllers\G2gLms\LearningDashboardController::class, 'streak']);
            Route::get('weekly-goal', [\App\Http\Controllers\G2gLms\LearningDashboardController::class, 'weeklyGoal']);
            Route::get('achievements', [\App\Http\Controllers\G2gLms\LearningDashboardController::class, 'achievements']);
            Route::get('peer-comparison', [\App\Http\Controllers\G2gLms\LearningDashboardController::class, 'peerComparison']);
            Route::get('calendar', [\App\Http\Controllers\G2gLms\LearningDashboardController::class, 'calendar']);
            Route::get('recent-activity', [\App\Http\Controllers\G2gLms\LearningDashboardController::class, 'recentActivity']);
        });

        // ---------------------------------------------------------------
        // Learning Catalog — PACKAGE 1
        // ---------------------------------------------------------------
        Route::prefix('learning-catalog')->group(function () {
            // Static routes declared BEFORE courses/{id}, so they are not
            // swallowed by the {id} wildcard (matches hp_erp's ordering).
            Route::get('kpis', [\App\Http\Controllers\G2gLms\LearningCatalogController::class, 'kpis']);
            Route::get('filters', [\App\Http\Controllers\G2gLms\LearningCatalogController::class, 'filters']);
            Route::post('bulk', [\App\Http\Controllers\G2gLms\LearningCatalogController::class, 'bulk']);
            Route::get('courses/{id}/audience/preview', [\App\Http\Controllers\G2gLms\LearningCatalogController::class, 'audiencePreview']);
            Route::post('courses/{id}/audience', [\App\Http\Controllers\G2gLms\LearningCatalogController::class, 'assignAudience']);

            Route::get('courses', [\App\Http\Controllers\G2gLms\LearningCatalogController::class, 'index']);
            Route::post('courses', [\App\Http\Controllers\G2gLms\LearningCatalogController::class, 'store']);
            Route::get('courses/{id}', [\App\Http\Controllers\G2gLms\LearningCatalogController::class, 'show']);
            Route::put('courses/{id}', [\App\Http\Controllers\G2gLms\LearningCatalogController::class, 'update']);
            Route::delete('courses/{id}', [\App\Http\Controllers\G2gLms\LearningCatalogController::class, 'destroy']);
        });

        // ---------------------------------------------------------------
        // My Learning — PACKAGE 1
        // ---------------------------------------------------------------
        Route::prefix('my-learning')->group(function () {
            Route::get('courses', [\App\Http\Controllers\G2gLms\MyLearningController::class, 'courses']);
            Route::get('courses/{courseId}', [\App\Http\Controllers\G2gLms\MyLearningController::class, 'course']);
            Route::get('assessments', [\App\Http\Controllers\G2gLms\MyLearningController::class, 'assessments']);
            Route::post('progress', [\App\Http\Controllers\G2gLms\MyLearningController::class, 'saveProgress']);
            Route::post('courses/{courseId}/complete', [\App\Http\Controllers\G2gLms\MyLearningController::class, 'completeCourse']);

            Route::get('notes', [\App\Http\Controllers\G2gLms\MyLearningController::class, 'notes']);
            Route::post('notes', [\App\Http\Controllers\G2gLms\MyLearningController::class, 'storeNote']);
            Route::put('notes/{id}', [\App\Http\Controllers\G2gLms\MyLearningController::class, 'updateNote']);
            Route::delete('notes/{id}', [\App\Http\Controllers\G2gLms\MyLearningController::class, 'destroyNote']);

            Route::post('chapters', [\App\Http\Controllers\G2gLms\MyLearningController::class, 'storeChapter']);
            Route::put('chapters/{id}', [\App\Http\Controllers\G2gLms\MyLearningController::class, 'updateChapter']);
            Route::delete('chapters/{id}', [\App\Http\Controllers\G2gLms\MyLearningController::class, 'destroyChapter']);

            Route::post('content', [\App\Http\Controllers\G2gLms\MyLearningController::class, 'storeContent']);
            Route::put('content/{id}', [\App\Http\Controllers\G2gLms\MyLearningController::class, 'updateContent']);
            Route::delete('content/{id}', [\App\Http\Controllers\G2gLms\MyLearningController::class, 'destroyContent']);

            // Static routes declared BEFORE certificates/{id}/*, matching the
            // hp_erp / Learning Catalog ordering convention above.
            Route::get('certificates/verify/{code}', [\App\Http\Controllers\G2gLms\MyLearningController::class, 'verifyCertificate']);
            Route::get('certificates', [\App\Http\Controllers\G2gLms\MyLearningController::class, 'certificates']);
            Route::post('certificates', [\App\Http\Controllers\G2gLms\MyLearningController::class, 'issueCertificate']);
            Route::get('certificates/{id}/download', [\App\Http\Controllers\G2gLms\MyLearningController::class, 'downloadCertificate']);
            Route::post('certificates/{id}/reissue', [\App\Http\Controllers\G2gLms\MyLearningController::class, 'reissueCertificate']);

            Route::get('discussions', [\App\Http\Controllers\G2gLms\MyLearningController::class, 'discussions']);
            Route::post('discussions', [\App\Http\Controllers\G2gLms\MyLearningController::class, 'storeDiscussion']);
            Route::post('discussions/{id}/replies', [\App\Http\Controllers\G2gLms\MyLearningController::class, 'replyToDiscussion']);
            Route::delete('discussions/{id}', [\App\Http\Controllers\G2gLms\MyLearningController::class, 'destroyDiscussion']);
        });

        // ---------------------------------------------------------------
        // Assignments — PACKAGE 2
        //
        // Ported from G2G's /lmsAssignment/* (hp_erp's
        // App\Http\Controllers\lms\assignment\assignmentController). Static
        // segments (stats, learners, courses, enrollments, import,
        // bulk-status, bulk-review) stay ahead of the /{id}/... routes so
        // they are not swallowed as an id, matching hp_erp's original route
        // ordering.
        // ---------------------------------------------------------------
        Route::prefix('assignments')->group(function () {
            Route::get('/stats', [\App\Http\Controllers\G2gLms\AssignmentsController::class, 'stats']);
            Route::get('/learners', [\App\Http\Controllers\G2gLms\AssignmentsController::class, 'learners']);
            Route::get('/courses', [\App\Http\Controllers\G2gLms\AssignmentsController::class, 'courses']);
            Route::get('/enrollments', [\App\Http\Controllers\G2gLms\AssignmentsController::class, 'enrollments']);
            Route::post('/import', [\App\Http\Controllers\G2gLms\AssignmentsController::class, 'import']);
            Route::post('/bulk-status', [\App\Http\Controllers\G2gLms\AssignmentsController::class, 'bulkUpdateStatus']);
            Route::post('/bulk-review', [\App\Http\Controllers\G2gLms\AssignmentsController::class, 'bulkReview']);
            Route::post('/{id}/status', [\App\Http\Controllers\G2gLms\AssignmentsController::class, 'updateStatus']);
            Route::post('/{id}/review', [\App\Http\Controllers\G2gLms\AssignmentsController::class, 'review']);
            Route::get('/', [\App\Http\Controllers\G2gLms\AssignmentsController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\G2gLms\AssignmentsController::class, 'store']);
        });

        // ---------------------------------------------------------------
        // Sessions & Calendar — PACKAGE 2
        //
        // Ported from G2G's /lms/sessions/* (hp_erp's
        // App\Http\Controllers\Api\LmsSessionController). Static segments
        // (stats, deadlines) stay ahead of /{id}/... so they are not
        // swallowed as an id, matching hp_erp's original route ordering.
        // ---------------------------------------------------------------
        Route::prefix('sessions-calendar')->group(function () {
            Route::get('/stats', [\App\Http\Controllers\G2gLms\SessionsCalendarController::class, 'stats']);
            Route::get('/deadlines', [\App\Http\Controllers\G2gLms\SessionsCalendarController::class, 'deadlines']);
            Route::get('/{id}/attendees', [\App\Http\Controllers\G2gLms\SessionsCalendarController::class, 'attendees']);
            Route::post('/{id}/register', [\App\Http\Controllers\G2gLms\SessionsCalendarController::class, 'register']);
            Route::delete('/{id}/register', [\App\Http\Controllers\G2gLms\SessionsCalendarController::class, 'cancelRegistration']);
            Route::get('/', [\App\Http\Controllers\G2gLms\SessionsCalendarController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\G2gLms\SessionsCalendarController::class, 'store']);
            Route::put('/{id}', [\App\Http\Controllers\G2gLms\SessionsCalendarController::class, 'update']);
            Route::delete('/{id}', [\App\Http\Controllers\G2gLms\SessionsCalendarController::class, 'destroy']);
        });

        // ---------------------------------------------------------------
        // Certifications & Records — PACKAGE 3
        // ---------------------------------------------------------------
        Route::prefix('certifications-records')->group(function () {
            Route::get('certificates', [\App\Http\Controllers\G2gLms\CertificationsRecordsController::class, 'index']);
            Route::post('certificates', [\App\Http\Controllers\G2gLms\CertificationsRecordsController::class, 'issue']);
            Route::get('certificates/{id}/download', [\App\Http\Controllers\G2gLms\CertificationsRecordsController::class, 'download']);
            Route::post('certificates/{id}/reissue', [\App\Http\Controllers\G2gLms\CertificationsRecordsController::class, 'reissue']);
            Route::get('transcript', [\App\Http\Controllers\G2gLms\CertificationsRecordsController::class, 'transcript']);
            Route::get('completion-history', [\App\Http\Controllers\G2gLms\CertificationsRecordsController::class, 'completionHistory']);
        });

        // ---------------------------------------------------------------
        // Course Builder — PACKAGE 3
        // ---------------------------------------------------------------
        Route::prefix('course-builder')->group(function () {
            Route::get('options', [\App\Http\Controllers\G2gLms\CourseBuilderController::class, 'options']);
            Route::get('courses/{id}', [\App\Http\Controllers\G2gLms\CourseBuilderController::class, 'show']);
            Route::post('courses', [\App\Http\Controllers\G2gLms\CourseBuilderController::class, 'store']);
            Route::put('courses/{id}', [\App\Http\Controllers\G2gLms\CourseBuilderController::class, 'update']);
            Route::get('courses/{id}/modules', [\App\Http\Controllers\G2gLms\CourseBuilderController::class, 'modules']);
            Route::get('courses/{id}/audience/preview', [\App\Http\Controllers\G2gLms\CourseBuilderController::class, 'audiencePreview']);
            Route::post('courses/{id}/audience', [\App\Http\Controllers\G2gLms\CourseBuilderController::class, 'assignAudience']);

            Route::post('chapters', [\App\Http\Controllers\G2gLms\CourseBuilderController::class, 'storeModule']);
            Route::put('chapters/{id}', [\App\Http\Controllers\G2gLms\CourseBuilderController::class, 'updateModule']);
            Route::delete('chapters/{id}', [\App\Http\Controllers\G2gLms\CourseBuilderController::class, 'destroyModule']);

            Route::post('content', [\App\Http\Controllers\G2gLms\CourseBuilderController::class, 'storeContent']);
            Route::delete('content/{id}', [\App\Http\Controllers\G2gLms\CourseBuilderController::class, 'destroyContent']);

            Route::get('assessments', [\App\Http\Controllers\G2gLms\CourseBuilderController::class, 'assessments']);
            Route::post('assessments', [\App\Http\Controllers\G2gLms\CourseBuilderController::class, 'storeAssessment']);
            Route::delete('assessments/{id}', [\App\Http\Controllers\G2gLms\CourseBuilderController::class, 'destroyAssessment']);

            Route::get('ai/status', [\App\Http\Controllers\G2gLms\AiCourseController::class, 'status']);
            Route::post('ai/outline', [\App\Http\Controllers\G2gLms\AiCourseController::class, 'generateOutline']);
            Route::post('ai/presentation', [\App\Http\Controllers\G2gLms\AiCourseController::class, 'generatePresentation']);
            Route::get('ai/presentation/{generationId}', [\App\Http\Controllers\G2gLms\AiCourseController::class, 'generationStatus']);
            Route::get('ai/outlines', [\App\Http\Controllers\G2gLms\AiCourseController::class, 'outlines']);
            Route::post('ai/outlines/{id}/publish', [\App\Http\Controllers\G2gLms\AiCourseController::class, 'publish']);
        });

        // ---------------------------------------------------------------
        // Administration & Governance — PACKAGE 4
        //
        // Ported from hp_erp's LmsGovernanceController (kpis, users, roles,
        // permissions, audit-logs, system-health) + LmsPartnerController
        // (trainers, vendors, integrations). See GovernanceController's and
        // PartnerController's own docblocks for the identity/schema
        // adaptations this package made.
        // ---------------------------------------------------------------
        Route::prefix('administration-governance')->group(function () {
            Route::get('/kpis', [\App\Http\Controllers\G2gLms\GovernanceController::class, 'kpis']);
            Route::get('/system-health', [\App\Http\Controllers\G2gLms\GovernanceController::class, 'systemHealth']);

            Route::get('/users', [\App\Http\Controllers\G2gLms\GovernanceController::class, 'users']);
            Route::post('/users', [\App\Http\Controllers\G2gLms\GovernanceController::class, 'storeUser']);
            Route::post('/users/import', [\App\Http\Controllers\G2gLms\GovernanceController::class, 'importUsers']);
            Route::put('/users/{id}', [\App\Http\Controllers\G2gLms\GovernanceController::class, 'updateUser']);
            Route::delete('/users/{id}', [\App\Http\Controllers\G2gLms\GovernanceController::class, 'destroyUser']);

            Route::get('/roles', [\App\Http\Controllers\G2gLms\GovernanceController::class, 'roles']);
            Route::post('/roles', [\App\Http\Controllers\G2gLms\GovernanceController::class, 'storeRole']);
            Route::put('/roles/{id}', [\App\Http\Controllers\G2gLms\GovernanceController::class, 'updateRole']);
            Route::delete('/roles/{id}', [\App\Http\Controllers\G2gLms\GovernanceController::class, 'destroyRole']);

            Route::get('/permissions', [\App\Http\Controllers\G2gLms\GovernanceController::class, 'permissions']);
            Route::post('/permissions', [\App\Http\Controllers\G2gLms\GovernanceController::class, 'savePermissions']);

            Route::get('/audit-logs', [\App\Http\Controllers\G2gLms\GovernanceController::class, 'auditLogs']);

            Route::get('/trainers', [\App\Http\Controllers\G2gLms\PartnerController::class, 'trainers']);
            Route::post('/trainers', [\App\Http\Controllers\G2gLms\PartnerController::class, 'storeTrainer']);
            Route::put('/trainers/{id}', [\App\Http\Controllers\G2gLms\PartnerController::class, 'updateTrainer']);
            Route::delete('/trainers/{id}', [\App\Http\Controllers\G2gLms\PartnerController::class, 'destroyTrainer']);

            Route::get('/vendors', [\App\Http\Controllers\G2gLms\PartnerController::class, 'vendors']);
            Route::post('/vendors', [\App\Http\Controllers\G2gLms\PartnerController::class, 'storeVendor']);
            Route::put('/vendors/{id}', [\App\Http\Controllers\G2gLms\PartnerController::class, 'updateVendor']);
            Route::delete('/vendors/{id}', [\App\Http\Controllers\G2gLms\PartnerController::class, 'destroyVendor']);

            Route::get('/integrations', [\App\Http\Controllers\G2gLms\PartnerController::class, 'integrations']);
            Route::post('/integrations', [\App\Http\Controllers\G2gLms\PartnerController::class, 'storeIntegration']);
            Route::put('/integrations/{id}', [\App\Http\Controllers\G2gLms\PartnerController::class, 'updateIntegration']);
            Route::delete('/integrations/{id}', [\App\Http\Controllers\G2gLms\PartnerController::class, 'destroyIntegration']);
        });

        // ---------------------------------------------------------------
        // Assessments — PACKAGE 4
        //
        // Ported from hp_erp's Api\Competency\{AssessmentController,
        // AssessmentCycleController, AiAssessmentController}, trimmed to
        // what the ported frontend screens (cm-assessment-workspace.tsx,
        // cm-my-assessment.tsx) actually call - see AssessmentCycleController's
        // and AiAssessmentController's own docblocks for exactly what was
        // and was not ported, and why.
        // ---------------------------------------------------------------
        Route::prefix('assessments')->group(function () {
            // s_competency_assessment_cycles ("campaigns")
            Route::get('/assessment-cycles/metrics', [\App\Http\Controllers\G2gLms\AssessmentCycleController::class, 'metrics']);
            Route::get('/assessment-cycles/participant-ratings', [\App\Http\Controllers\G2gLms\AssessmentCycleController::class, 'participantRatings']);
            Route::get('/assessment-cycles/calibration', [\App\Http\Controllers\G2gLms\AssessmentCycleController::class, 'calibration']);
            Route::get('/assessment-cycles/approvals', [\App\Http\Controllers\G2gLms\AssessmentCycleController::class, 'approvals']);
            Route::get('/assessment-cycles/closed', [\App\Http\Controllers\G2gLms\AssessmentCycleController::class, 'closed']);
            Route::put('/assessment-cycles/assessments/{id}/review', [\App\Http\Controllers\G2gLms\AssessmentCycleController::class, 'reviewAssessment']);
            Route::get('/assessment-cycles/{id}/participants', [\App\Http\Controllers\G2gLms\AssessmentCycleController::class, 'participants']);
            Route::get('/assessment-cycles', [\App\Http\Controllers\G2gLms\AssessmentCycleController::class, 'index']);
            Route::post('/assessment-cycles', [\App\Http\Controllers\G2gLms\AssessmentCycleController::class, 'store']);

            // s_competency_assessments (the individual assessment record)
            Route::get('/assessments', [\App\Http\Controllers\G2gLms\AssessmentController::class, 'index']);
            Route::post('/assessments', [\App\Http\Controllers\G2gLms\AssessmentController::class, 'store']);
            Route::delete('/assessments/{id}', [\App\Http\Controllers\G2gLms\AssessmentController::class, 'destroy']);

            // AI-generated capability assessment (competency_assessment_test/_question/_response)
            Route::get('/ai-assessment/jobroles', [\App\Http\Controllers\G2gLms\AiAssessmentController::class, 'jobroles']);
            Route::post('/ai-assessment/generate', [\App\Http\Controllers\G2gLms\AiAssessmentController::class, 'generate']);
            Route::post('/ai-assessment/publish', [\App\Http\Controllers\G2gLms\AiAssessmentController::class, 'publish']);
            Route::get('/ai-assessment/mine', [\App\Http\Controllers\G2gLms\AiAssessmentController::class, 'mine']);
            Route::post('/ai-assessment/submit', [\App\Http\Controllers\G2gLms\AiAssessmentController::class, 'submit']);
            Route::get('/ai-assessment/my-result', [\App\Http\Controllers\G2gLms\AiAssessmentController::class, 'myResult']);
            Route::get('/ai-assessment/tests', [\App\Http\Controllers\G2gLms\AiAssessmentController::class, 'tests']);
            Route::get('/ai-assessment/tests/{id}', [\App\Http\Controllers\G2gLms\AiAssessmentController::class, 'show']);
            Route::post('/ai-assessment/responses/{id}/score', [\App\Http\Controllers\G2gLms\AiAssessmentController::class, 'scoreResponse']);
        });
    });
});

/*
| Public certificate verification — PACKAGE 3.
|
| Deliberately OUTSIDE the api.session/staff.only group above: checking a
| credential's authenticity must not require the checker to hold an account
| or a staff session, matching hp_erp's unauthenticated
| `GET /verify/certificate/{code}` endpoint. Still reachable under the same
| `api/g2g-lms/...` prefix (RouteServiceProvider::mapG2gLmsRoutes() wraps this
| whole file in prefix('api'), unaffected by which inner group a route sits
| in).
*/
Route::prefix('g2g-lms/certifications-records')->group(function () {
    Route::get('certificates/verify/{code}', [\App\Http\Controllers\G2gLms\CertificationsRecordsController::class, 'verify']);
});
