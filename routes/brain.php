<?php

use App\Http\Controllers\Brain\BrainController;
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

        // Section landing pages and the registry-driven screens.
        Route::get('sections/{section}', [BrainController::class, 'section'])->middleware('brain.permission:read');
        Route::get('screens/{screen}', [BrainController::class, 'screen'])->middleware('brain.permission:read');
    });
});
