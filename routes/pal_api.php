<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\api\PAL\PALAPIController;
use App\Http\Controllers\api\PAL\PedagogyEngineController;
use App\Http\Controllers\api\PAL\PalWorkspaceController;

/*
|--------------------------------------------------------------------------
| PAL V4 API Routes
|--------------------------------------------------------------------------
|
| All PAL V4 Intelligence Layer, Pedagogy Engine, and AI APIs
| These APIs are designed for data-agnostic operation - data provided
| by backend team via database, services consume via models
|
| Secured by the `pal.auth` middleware: central JWT authentication plus
| per-learner tenant/ownership scoping (students see only their own record;
| staff/admins are scoped to their own institute/client).
|
*/

/*
| Pedagogy Engine (read-only rule set)
|
| The codified PAL V4 decision rules -- mastery tiers, engagement state, error
| and misconception handling, learning style / context, the engagement score
| composition and the pedagogy x trigger map. This is reference data: it carries
| no learner or tenant scope, so it sits outside `pal.auth` alongside the other
| read-only PAL content endpoints the Next.js server renderer reads. Anything
| learner-specific stays inside the authenticated group below.
*/
Route::prefix('api/pal/pedagogy-engine')->group(function () {
    Route::get('/', [PedagogyEngineController::class, 'index']);
    Route::get('/sections', [PedagogyEngineController::class, 'sections']);
    Route::get('/chapters', [PedagogyEngineController::class, 'chapters']);
    Route::get('/{section}', [PedagogyEngineController::class, 'show'])
        ->where('section', '[A-Za-z0-9\-]+');
});

Route::prefix('api/pal')->middleware('pal.auth')->group(function () {

    // ==================== WORKSPACE (student PAL landing) ====================
    // Stateless replacement for the legacy /lms/pal web flow. The literal
    // /workspace/students route is declared before the {learnerId} wildcard,
    // and {learnerId} is constrained to digits so the two never collide.
    Route::get('/workspace/students', [PalWorkspaceController::class, 'students']);
    Route::get('/workspace/preview', [PalWorkspaceController::class, 'preview']);
    Route::get('/workspace/{learnerId}', [PalWorkspaceController::class, 'workspace'])
        ->where('learnerId', '[0-9]+');

    // ==================== INTELLIGENCE LAYER ====================
    
    // Learner State APIs
    Route::get('/learner-state/{learnerId}', [PALAPIController::class, 'getLearnerState']);
    Route::get('/mastery-map/{learnerId}', [PALAPIController::class, 'getMasteryMap']);
    
    // Learning Velocity APIs
    Route::get('/velocity/{learnerId}', [PALAPIController::class, 'getLearningVelocity']);
    Route::get('/plateau/{learnerId}', [PALAPIController::class, 'detectPlateau']);
    Route::get('/regression/{learnerId}', [PALAPIController::class, 'detectRegression']);
    
    // Misconception APIs
    Route::post('/misconception/analyze', [PALAPIController::class, 'analyzeMisconception']);
    Route::get('/misconception/cluster/{conceptId}', [PALAPIController::class, 'clusterMisconceptions']);
    Route::get('/remediation/{learnerId}/{misconceptionId}', [PALAPIController::class, 'getRemediation']);
    
    // Prediction APIs
    Route::get('/disengagement-risk/{learnerId}', [PALAPIController::class, 'predictDisengagement']);
    Route::get('/failure-risk/{learnerId}', [PALAPIController::class, 'predictFailure']);
    Route::get('/burnout-risk/{learnerId}', [PALAPIController::class, 'predictBurnout']);
    
    // Engagement APIs
    Route::get('/engagement/{learnerId}/{sessionId}', [PALAPIController::class, 'calculateEngagement']);
    Route::get('/frustration/{learnerId}/{sessionId}', [PALAPIController::class, 'detectFrustration']);
    
    // ==================== PEDAGOGY ENGINE ====================
    
    // Pedagogy Recommendation APIs
    Route::get('/pedagogy/recommend/{learnerId}/{conceptId}', [PALAPIController::class, 'getRecommendedPedagogy']);
    Route::get('/pedagogy/engine/suggested-content', [PALAPIController::class, 'getPedagogyEngineSuggestedContent']);
    Route::get('/content/variants/{conceptId}', [PALAPIController::class, 'getContentVariants']);
    Route::post('/pedagogy/track', [PALAPIController::class, 'trackPedagogyEffectiveness']);
    
    // ==================== CONTENT INTELLIGENCE ====================
    
    // Content Recommendation APIs
    Route::get('/content/recommend/{learnerId}/{conceptId}', [PALAPIController::class, 'getContentRecommendation']);
    Route::get('/h5p/variants/{conceptId}', [PALAPIController::class, 'getH5PVariants']);
    Route::get('/frameworks/catalog', [PALAPIController::class, 'getFrameworkCatalog']);
    Route::get('/content/{contentId}/framework-metadata', [PALAPIController::class, 'getContentFrameworkMetadata']);
    Route::post('/content/{contentId}/framework-metadata', [PALAPIController::class, 'updateContentFrameworkMetadata']);
    Route::get('/dashboard/learner/{learnerId}', [PALAPIController::class, 'getLearnerDashboard']);
    Route::get('/dashboard/teacher', [PALAPIController::class, 'getTeacherDashboard']);
    Route::get('/ulu', [PALAPIController::class, 'listULU']);
    Route::get('/ulu/{id}', [PALAPIController::class, 'getULU'])->where('id', '[0-9]+');
    Route::post('/ulu', [PALAPIController::class, 'createULU']);
    Route::put('/ulu/{id}', [PALAPIController::class, 'updateULU'])->where('id', '[0-9]+');
    Route::delete('/ulu/{id}', [PALAPIController::class, 'deleteULU'])->where('id', '[0-9]+');
    Route::post('/ulu/{id}/duplicate', [PALAPIController::class, 'duplicateULU'])->where('id', '[0-9]+');
    Route::post('/ulu/{id}/archive', [PALAPIController::class, 'archiveULU'])->where('id', '[0-9]+');
    Route::post('/ulu/{id}/approve', [PALAPIController::class, 'approveULU'])->where('id', '[0-9]+');
    Route::get('/ulu/{id}/analytics', [PALAPIController::class, 'getULUAnalytics'])->where('id', '[0-9]+');
    Route::get('/ulu/{id}/preview', [PALAPIController::class, 'getULUPreview'])->where('id', '[0-9]+');
    
    // ==================== TELEMETRY LAYER ====================
    
    // xAPI Processing APIs
    Route::post('/telemetry/xapi', [PALAPIController::class, 'processxAPI']);
    Route::post('/telemetry/batch', [PALAPIController::class, 'processxAPIBatch']);
    Route::get('/telemetry/session/{sessionId}', [PALAPIController::class, 'getSessionTelemetry']);
    Route::get('/time-on-task/{learnerId}', [PALAPIController::class, 'getTimeOnTask']);
    Route::get('/telemetry/important-events/{learnerId}', [PALAPIController::class, 'getImportantEvents']);
    
    // ==================== AI ORCHESTRATION ====================
    
    // AI Generation APIs
    Route::post('/ai/explanation', [PALAPIController::class, 'generateExplanation']);
    Route::post('/ai/remediation', [PALAPIController::class, 'generateRemediation']);
    Route::post('/ai/practice', [PALAPIController::class, 'generatePractice']);
    Route::post('/ai/summary', [PALAPIController::class, 'summarizeContent']);
    Route::post('/ai/teacher-insights', [PALAPIController::class, 'getTeacherInsights']);
    
    // ==================== METACOGNITION ====================
    
    // Metacognition APIs
    Route::get('/metacognition/prompts/{learnerId}', [PALAPIController::class, 'getMetacognitivePrompts']);
    Route::post('/metacognition/reflect', [PALAPIController::class, 'recordReflection']);
});
