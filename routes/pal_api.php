<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\api\PAL\PALAPIController;

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

Route::prefix('api/pal')->middleware('pal.auth')->group(function () {
    
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
