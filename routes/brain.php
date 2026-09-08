<?php

use App\Http\Controllers\Brain\BrainController;
use App\Http\Controllers\Brain\BrainIntelligenceController;
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
