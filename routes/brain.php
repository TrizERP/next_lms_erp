<?php

use App\Http\Controllers\Brain\BrainAcademicIntelligenceController;
use App\Http\Controllers\Brain\BrainAdmissionsIntelligenceController;
use App\Http\Controllers\Brain\BrainAttendanceIntelligenceController;
use App\Http\Controllers\Brain\BrainCapabilityIntelligenceController;
use App\Http\Controllers\Brain\BrainCommunicationIntelligenceController;
use App\Http\Controllers\Brain\BrainController;
use App\Http\Controllers\Brain\BrainCorrespondenceIntelligenceController;
use App\Http\Controllers\Brain\BrainFeesIntelligenceController;
use App\Http\Controllers\Brain\BrainHomeworkIntelligenceController;
use App\Http\Controllers\Brain\BrainHostelIntelligenceController;
use App\Http\Controllers\Brain\BrainHrIntelligenceController;
use App\Http\Controllers\Brain\BrainIntelligenceController;
use App\Http\Controllers\Brain\BrainIntelligenceIntegrationController;
use App\Http\Controllers\Brain\BrainInventoryIntelligenceController;
use App\Http\Controllers\Brain\BrainLibraryIntelligenceController;
use App\Http\Controllers\Brain\BrainLmsActivityIntelligenceController;
use App\Http\Controllers\Brain\BrainOrganizationIntelligenceController;
use App\Http\Controllers\Brain\BrainResultIntelligenceController;
use App\Http\Controllers\Brain\BrainStaffAttendanceIntelligenceController;
use App\Http\Controllers\Brain\BrainStudentIntelligenceController;
use App\Http\Controllers\Brain\BrainTalentIntelligenceController;
use App\Http\Controllers\Brain\BrainTaskIntelligenceController;
use App\Http\Controllers\Brain\BrainTeachLearnIntelligenceController;
use App\Http\Controllers\Brain\BrainTransportIntelligenceController;
use App\Http\Controllers\Brain\BrainVisitorIntelligenceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Enterprise Brain API
|--------------------------------------------------------------------------
|
| Mounted at /api/brain by RouteServiceProvider::mapBrainRoutes(). Every route
| authenticates with the LMS's own JWT (brain.auth), is pinned to the tenant in
| that token (brain.tenant) and is gated by the Brain permission model.
|
*/

Route::middleware(['brain.auth', 'brain.tenant'])->group(function () {
    Route::get('access', [BrainController::class, 'access'])->middleware('brain.permission:read');
    Route::get('navigation', [BrainController::class, 'navigation'])->middleware('brain.permission:read');

    Route::prefix('{tenantId}')->group(function () {
        Route::get('overview', [BrainController::class, 'overview'])->middleware('brain.permission:read');
        Route::get('foundation', [BrainController::class, 'foundation'])->middleware('brain.permission:read');
        Route::get('search', [BrainController::class, 'search'])->middleware('brain.permission:read');

        // Foundation — the LMS's own organization, reused rather than duplicated.
        Route::get('departments', [BrainController::class, 'departments'])->middleware('brain.permission:read');
        Route::get('people', [BrainController::class, 'people'])->middleware('brain.permission:read');

        // Capabilities.
        Route::get('capabilities', [BrainController::class, 'capabilities'])->middleware('brain.permission:read');
        Route::post('capabilities', [BrainController::class, 'capabilityStore'])->middleware('brain.permission:create');
        Route::get('capabilities/{id}', [BrainController::class, 'capabilityShow'])->middleware('brain.permission:read');
        Route::patch('capabilities/{id}', [BrainController::class, 'capabilityUpdate'])->middleware('brain.permission:update');
        Route::post('capabilities/{id}/assign', [BrainController::class, 'capabilityAssign'])->middleware('brain.permission:update');
        Route::delete('capabilities/{id}/assign/{assignmentId}', [BrainController::class, 'capabilityUnassign'])->middleware('brain.permission:delete');

        // Ingestion.
        Route::get('ingestion', [BrainController::class, 'ingestion'])->middleware('brain.permission:read');
        Route::post('ingestion/run', [BrainController::class, 'ingestionRun'])->middleware('brain.permission:create');

        // Knowledge screens with their own shape.
        Route::get('kasba', [BrainController::class, 'kasba'])->middleware('brain.permission:read');
        Route::get('ai-assistant', [BrainController::class, 'aiAssistant'])->middleware('brain.permission:read');

        // Account.
        Route::get('settings', [BrainController::class, 'settings'])->middleware('brain.permission:read');
        Route::put('settings', [BrainController::class, 'settingsUpdate'])->middleware('brain.permission:settings.manage');

        // Intelligence loop — the real pipeline over vivek_erp.
        Route::get('intelligence', [BrainIntelligenceController::class, 'intelligence'])->middleware('brain.permission:read');
        Route::post('intelligence/run', [BrainIntelligenceController::class, 'intelligenceRun'])->middleware('brain.permission:create');
        Route::get('signals', [BrainIntelligenceController::class, 'signals'])->middleware('brain.permission:read');
        Route::get('signals/{id}', [BrainIntelligenceController::class, 'signalShow'])->middleware('brain.permission:read');
        Route::get('recommendations', [BrainIntelligenceController::class, 'recommendations'])->middleware('brain.permission:read');

        // The one path that creates a decision, and the report-back that closes
        // the loop into an outcome. Both require a named LMS user.
        Route::post('recommendations/{id}/decide', [BrainIntelligenceController::class, 'decide'])->middleware('brain.permission:update');
        Route::post('executions/{id}/complete', [BrainIntelligenceController::class, 'executionComplete'])->middleware('brain.permission:update');

        /*
         * Fees Intelligence — the Fees module's own loop, for the academic year
         * the LMS header has selected.
         *
         * Inside this group on purpose: it inherits brain.auth (the LMS's own
         * JWT), brain.tenant (pinned to the token's institute) and the Brain
         * permission model, so the Fees screen gets tenant isolation and
         * year validation without a second authentication path to keep correct.
         *
         * Decisions and outcomes are NOT duplicated here — a fee recommendation
         * is decided through `recommendations/{id}/decide` and reported through
         * `executions/{id}/complete` above, the same two writes every other
         * recommendation in the system goes through.
         */
        Route::get('fees/intelligence', [BrainFeesIntelligenceController::class, 'index'])->middleware('brain.permission:read');
        Route::post('fees/intelligence/run', [BrainFeesIntelligenceController::class, 'run'])->middleware('brain.permission:create');
        Route::get('fees/accounts', [BrainFeesIntelligenceController::class, 'accounts'])->middleware('brain.permission:read');

        /*
         * The fourteen module Intelligence endpoints. Unlike the Fees endpoint
         * above, these emit the canonical ModuleIntelligencePayload directly, so
         * their frontend contracts need no adapter.
         *
         * Each has a `run` counterpart that writes its findings into the signal
         * ledger through ModuleSignalBridge, which is what gives them
         * recommendations, a decision trail and a learning memory. `run` is
         * idempotent — SignalWriter dedupes on (tenant, rule, year) — so pressing
         * it twice refreshes the same signals rather than duplicating them.
         *
         * Decisions and outcomes are NOT duplicated per module: a module
         * recommendation is decided through `recommendations/{id}/decide` and
         * reported through `executions/{id}/complete` above, the same two writes
         * every other recommendation in the system goes through.
         */
        Route::get('result/intelligence', [BrainResultIntelligenceController::class, 'index'])->middleware('brain.permission:read');
        Route::post('result/intelligence/run', [BrainResultIntelligenceController::class, 'run'])->middleware('brain.permission:create');
        Route::get('attendance/intelligence', [BrainAttendanceIntelligenceController::class, 'index'])->middleware('brain.permission:read');
        Route::post('attendance/intelligence/run', [BrainAttendanceIntelligenceController::class, 'run'])->middleware('brain.permission:create');
        Route::get('student/intelligence', [BrainStudentIntelligenceController::class, 'index'])->middleware('brain.permission:read');
        Route::post('student/intelligence/run', [BrainStudentIntelligenceController::class, 'run'])->middleware('brain.permission:create');
        Route::get('transport/intelligence', [BrainTransportIntelligenceController::class, 'index'])->middleware('brain.permission:read');
        Route::post('transport/intelligence/run', [BrainTransportIntelligenceController::class, 'run'])->middleware('brain.permission:create');
        Route::get('library/intelligence', [BrainLibraryIntelligenceController::class, 'index'])->middleware('brain.permission:read');
        Route::post('library/intelligence/run', [BrainLibraryIntelligenceController::class, 'run'])->middleware('brain.permission:create');
        Route::get('academic/intelligence', [BrainAcademicIntelligenceController::class, 'index'])->middleware('brain.permission:read');
        Route::post('academic/intelligence/run', [BrainAcademicIntelligenceController::class, 'run'])->middleware('brain.permission:create');
        Route::get('hr/intelligence', [BrainHrIntelligenceController::class, 'index'])->middleware('brain.permission:read');
        Route::post('hr/intelligence/run', [BrainHrIntelligenceController::class, 'run'])->middleware('brain.permission:create');
        Route::get('communication/intelligence', [BrainCommunicationIntelligenceController::class, 'index'])->middleware('brain.permission:read');
        Route::post('communication/intelligence/run', [BrainCommunicationIntelligenceController::class, 'run'])->middleware('brain.permission:create');
        Route::get('homework/intelligence', [BrainHomeworkIntelligenceController::class, 'index'])->middleware('brain.permission:read');
        Route::post('homework/intelligence/run', [BrainHomeworkIntelligenceController::class, 'run'])->middleware('brain.permission:create');
        Route::get('admissions/intelligence', [BrainAdmissionsIntelligenceController::class, 'index'])->middleware('brain.permission:read');
        Route::post('admissions/intelligence/run', [BrainAdmissionsIntelligenceController::class, 'run'])->middleware('brain.permission:create');
        Route::get('inventory/intelligence', [BrainInventoryIntelligenceController::class, 'index'])->middleware('brain.permission:read');
        Route::post('inventory/intelligence/run', [BrainInventoryIntelligenceController::class, 'run'])->middleware('brain.permission:create');
        Route::get('hostel/intelligence', [BrainHostelIntelligenceController::class, 'index'])->middleware('brain.permission:read');
        Route::post('hostel/intelligence/run', [BrainHostelIntelligenceController::class, 'run'])->middleware('brain.permission:create');
        Route::get('visitor/intelligence', [BrainVisitorIntelligenceController::class, 'index'])->middleware('brain.permission:read');
        Route::post('visitor/intelligence/run', [BrainVisitorIntelligenceController::class, 'run'])->middleware('brain.permission:create');
        Route::get('correspondence/intelligence', [BrainCorrespondenceIntelligenceController::class, 'index'])->middleware('brain.permission:read');
        Route::post('correspondence/intelligence/run', [BrainCorrespondenceIntelligenceController::class, 'run'])->middleware('brain.permission:create');
        Route::get('teach-learn/intelligence', [BrainTeachLearnIntelligenceController::class, 'index'])->middleware('brain.permission:read');
        Route::post('teach-learn/intelligence/run', [BrainTeachLearnIntelligenceController::class, 'run'])->middleware('brain.permission:create');

        /*
         * The six People & Competency module Intelligence endpoints, added
         * alongside the fourteen above and following the same shape: each emits
         * the canonical ModuleIntelligencePayload directly and has a `run`
         * counterpart that writes its findings into the signal ledger through
         * ModuleSignalBridge.
         *
         * 'staff-attendance' is deliberately distinct from the pupil-register
         * 'attendance' key above — it covers staff biometric/punch data, not
         * the student attendance register.
         */
        Route::get('organization/intelligence', [BrainOrganizationIntelligenceController::class, 'index'])->middleware('brain.permission:read');
        Route::post('organization/intelligence/run', [BrainOrganizationIntelligenceController::class, 'run'])->middleware('brain.permission:create');
        Route::get('task-management/intelligence', [BrainTaskIntelligenceController::class, 'index'])->middleware('brain.permission:read');
        Route::post('task-management/intelligence/run', [BrainTaskIntelligenceController::class, 'run'])->middleware('brain.permission:create');
        Route::get('talent/intelligence', [BrainTalentIntelligenceController::class, 'index'])->middleware('brain.permission:read');
        Route::post('talent/intelligence/run', [BrainTalentIntelligenceController::class, 'run'])->middleware('brain.permission:create');
        Route::get('capability/intelligence', [BrainCapabilityIntelligenceController::class, 'index'])->middleware('brain.permission:read');
        Route::post('capability/intelligence/run', [BrainCapabilityIntelligenceController::class, 'run'])->middleware('brain.permission:create');
        Route::get('staff-attendance/intelligence', [BrainStaffAttendanceIntelligenceController::class, 'index'])->middleware('brain.permission:read');
        Route::post('staff-attendance/intelligence/run', [BrainStaffAttendanceIntelligenceController::class, 'run'])->middleware('brain.permission:create');
        Route::get('lms-activity/intelligence', [BrainLmsActivityIntelligenceController::class, 'index'])->middleware('brain.permission:read');
        Route::post('lms-activity/intelligence/run', [BrainLmsActivityIntelligenceController::class, 'run'])->middleware('brain.permission:create');

        /*
         * Cross-Module Integration & Cross-Module Workflows.
         * Real data integration relationships and multi-module workflow execution across all modules.
         */
        Route::get('{module}/integration', [BrainIntelligenceIntegrationController::class, 'getIntegration'])->middleware('brain.permission:read');
        Route::get('modules/{module}/integration', [BrainIntelligenceIntegrationController::class, 'getIntegration'])->middleware('brain.permission:read');
        Route::get('{module}/workflows', [BrainIntelligenceIntegrationController::class, 'getWorkflows'])->middleware('brain.permission:read');
        Route::get('modules/{module}/workflows', [BrainIntelligenceIntegrationController::class, 'getWorkflows'])->middleware('brain.permission:read');
        Route::post('{module}/workflows/{flowKey}/trigger', [BrainIntelligenceIntegrationController::class, 'triggerWorkflow'])->middleware('brain.permission:create');
        Route::post('modules/{module}/workflows/{flowKey}/trigger', [BrainIntelligenceIntegrationController::class, 'triggerWorkflow'])->middleware('brain.permission:create');

        // Executive intelligence: health, what changed, what is at risk, what to do.
        Route::get('executive', [BrainIntelligenceController::class, 'executive'])->middleware('brain.permission:read');

        // Intelligence about one kind of thing, in the school's own language.
        Route::get('intelligence/classes', [BrainIntelligenceController::class, 'classIntelligence'])->middleware('brain.permission:read');
        Route::get('intelligence/departments', [BrainIntelligenceController::class, 'departmentIntelligence'])->middleware('brain.permission:read');
        Route::get('intelligence/teachers', [BrainIntelligenceController::class, 'teacherIntelligence'])->middleware('brain.permission:read');
        Route::get('intelligence/students/{id}', [BrainIntelligenceController::class, 'studentIntelligence'])->middleware('brain.permission:read');

        // Graph explorer over the LMS's own relationships.
        Route::get('graph', [BrainIntelligenceController::class, 'graph'])->middleware('brain.permission:read');

        // Analytics, knowledge and automation, each computed from live LMS rows.
        Route::get('analytics', [BrainIntelligenceController::class, 'analytics'])->middleware('brain.permission:read');
        Route::get('knowledge', [BrainIntelligenceController::class, 'knowledge'])->middleware('brain.permission:read');
        Route::get('automation', [BrainIntelligenceController::class, 'automation'])->middleware('brain.permission:read');
        Route::get('students', [BrainIntelligenceController::class, 'students'])->middleware('brain.permission:read');

        // Section landing pages and the registry-driven screens.
        Route::get('sections/{section}', [BrainController::class, 'section'])->middleware('brain.permission:read');
        Route::get('screens/{screen}', [BrainController::class, 'screen'])->middleware('brain.permission:read');
    });
});
