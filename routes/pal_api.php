<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\api\PAL\NewPalContentModelController;
use App\Http\Controllers\api\PAL\NewPalGamificationController;
use App\Http\Controllers\api\PAL\PALAPIController;
use App\Http\Controllers\api\PAL\PedagogyEngineController;
use App\Http\Controllers\api\PAL\PalArchitectureController;
use App\Http\Controllers\api\PAL\PalContentIntelligenceController;
use App\Http\Controllers\api\PAL\PalH5PModelController;
use App\Http\Controllers\api\PAL\PalWorkspaceController;

/*
|--------------------------------------------------------------------------
| PAL V4 API Routes
|--------------------------------------------------------------------------
|
| Public/read-only pedagogy reference routes are kept outside the
| authenticated PAL group. All learner/tenant-scoped PAL APIs are protected
| by pal.auth.
|
*/

/*
|--------------------------------------------------------------------------
| Pedagogy Engine - Read Only
|--------------------------------------------------------------------------
*/
Route::prefix('api/pal/pedagogy-engine')->group(function () {
    Route::get('/', [PedagogyEngineController::class, 'index']);
    Route::get('/sections', [PedagogyEngineController::class, 'sections']);
    Route::get('/chapters', [PedagogyEngineController::class, 'chapters']);
    Route::get('/{section}', [PedagogyEngineController::class, 'show'])
        ->where('section', '[A-Za-z0-9-]+');
});

/*
|--------------------------------------------------------------------------
| Authenticated PAL APIs
|--------------------------------------------------------------------------
*/
Route::prefix('api/pal')->middleware('pal.auth')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Workspace
    |--------------------------------------------------------------------------
    */
    Route::get('/workspace/students', [PalWorkspaceController::class, 'students']);
    Route::get('/workspace/preview', [PalWorkspaceController::class, 'preview']);
    Route::get('/workspace/{learnerId}', [PalWorkspaceController::class, 'workspace'])
        ->where('learnerId', '[0-9]+');

    /*
    |--------------------------------------------------------------------------
    | Intelligence Layer
    |--------------------------------------------------------------------------
    */

    // Learner state
    Route::get('/learner-state/{learnerId}', [PALAPIController::class, 'getLearnerState']);
    Route::get('/mastery-map/{learnerId}', [PALAPIController::class, 'getMasteryMap']);

    // Learning velocity
    Route::get('/velocity/{learnerId}', [PALAPIController::class, 'getLearningVelocity']);
    Route::get('/plateau/{learnerId}', [PALAPIController::class, 'detectPlateau']);
    Route::get('/regression/{learnerId}', [PALAPIController::class, 'detectRegression']);

    // Misconceptions
    Route::post('/misconception/analyze', [PALAPIController::class, 'analyzeMisconception']);
    Route::get('/misconception/cluster/{conceptId}', [PALAPIController::class, 'clusterMisconceptions']);
    Route::get('/remediation/{learnerId}/{misconceptionId}', [PALAPIController::class, 'getRemediation']);

    // Risk prediction
    Route::get('/disengagement-risk/{learnerId}', [PALAPIController::class, 'predictDisengagement']);
    Route::get('/failure-risk/{learnerId}', [PALAPIController::class, 'predictFailure']);
    Route::get('/burnout-risk/{learnerId}', [PALAPIController::class, 'predictBurnout']);

    // Engagement
    Route::get('/engagement/{learnerId}/{sessionId}', [PALAPIController::class, 'calculateEngagement']);
    Route::get('/frustration/{learnerId}/{sessionId}', [PALAPIController::class, 'detectFrustration']);

    /*
    |--------------------------------------------------------------------------
    | Pedagogy Engine
    |--------------------------------------------------------------------------
    */
    Route::get('/pedagogy/recommend/{learnerId}/{conceptId}', [PALAPIController::class, 'getRecommendedPedagogy']);
    Route::get('/pedagogy/engine/suggested-content', [PALAPIController::class, 'getPedagogyEngineSuggestedContent']);
    Route::get('/content/variants/{conceptId}', [PALAPIController::class, 'getContentVariants']);
    Route::post('/pedagogy/track', [PALAPIController::class, 'trackPedagogyEffectiveness']);

    /*
    |--------------------------------------------------------------------------
    | Content Recommendation & ULU
    |--------------------------------------------------------------------------
    */
    Route::get('/content/recommend/{learnerId}/{conceptId}', [PALAPIController::class, 'getContentRecommendation']);
    Route::get('/h5p/variants/{conceptId}', [PALAPIController::class, 'getH5PVariants']);
    Route::get('/frameworks/catalog', [PALAPIController::class, 'getFrameworkCatalog']);
    Route::get('/content/{contentId}/framework-metadata', [PALAPIController::class, 'getContentFrameworkMetadata']);
    Route::post('/content/{contentId}/framework-metadata', [PALAPIController::class, 'updateContentFrameworkMetadata']);
    Route::get('/dashboard/learner/{learnerId}', [PALAPIController::class, 'getLearnerDashboard']);
    Route::get('/dashboard/teacher', [PALAPIController::class, 'getTeacherDashboard']);

    Route::get('/ulu', [PALAPIController::class, 'listULU']);
    Route::post('/ulu', [PALAPIController::class, 'createULU']);
    Route::get('/ulu/{id}', [PALAPIController::class, 'getULU'])->where('id', '[0-9]+');
    Route::put('/ulu/{id}', [PALAPIController::class, 'updateULU'])->where('id', '[0-9]+');
    Route::delete('/ulu/{id}', [PALAPIController::class, 'deleteULU'])->where('id', '[0-9]+');
    Route::post('/ulu/{id}/duplicate', [PALAPIController::class, 'duplicateULU'])->where('id', '[0-9]+');
    Route::post('/ulu/{id}/archive', [PALAPIController::class, 'archiveULU'])->where('id', '[0-9]+');
    Route::post('/ulu/{id}/approve', [PALAPIController::class, 'approveULU'])->where('id', '[0-9]+');
    Route::get('/ulu/{id}/analytics', [PALAPIController::class, 'getULUAnalytics'])->where('id', '[0-9]+');
    Route::get('/ulu/{id}/preview', [PALAPIController::class, 'getULUPreview'])->where('id', '[0-9]+');

    /*
    |--------------------------------------------------------------------------
    | Telemetry
    |--------------------------------------------------------------------------
    */
    Route::post('/telemetry/xapi', [PALAPIController::class, 'processxAPI']);
    Route::post('/telemetry/batch', [PALAPIController::class, 'processxAPIBatch']);
    Route::get('/telemetry/session/{sessionId}', [PALAPIController::class, 'getSessionTelemetry']);
    Route::get('/time-on-task/{learnerId}', [PALAPIController::class, 'getTimeOnTask']);
    Route::get('/telemetry/important-events/{learnerId}', [PALAPIController::class, 'getImportantEvents']);

    /*
    |--------------------------------------------------------------------------
    | AI Orchestration
    |--------------------------------------------------------------------------
    */
    Route::post('/ai/explanation', [PALAPIController::class, 'generateExplanation']);
    Route::post('/ai/remediation', [PALAPIController::class, 'generateRemediation']);
    Route::post('/ai/practice', [PALAPIController::class, 'generatePractice']);
    Route::post('/ai/summary', [PALAPIController::class, 'summarizeContent']);
    Route::post('/ai/teacher-insights', [PALAPIController::class, 'getTeacherInsights']);

    /*
    |--------------------------------------------------------------------------
    | Metacognition
    |--------------------------------------------------------------------------
    */
    Route::get('/metacognition/prompts/{learnerId}', [PALAPIController::class, 'getMetacognitivePrompts']);
    Route::post('/metacognition/reflect', [PALAPIController::class, 'recordReflection']);

    /*
    |--------------------------------------------------------------------------
    | Content Intelligence Layer
    |--------------------------------------------------------------------------
    */
    Route::get('/content/vocabulary', [PalContentIntelligenceController::class, 'vocabulary']);
    Route::get('/content/coverage', [PalContentIntelligenceController::class, 'coverage']);

    // Bloom ladder
    Route::post('/content/ladder/evaluate', [PalContentIntelligenceController::class, 'evaluateLadder']);
    Route::post('/content/ladder/regression', [PalContentIntelligenceController::class, 'checkRegression']);
    Route::get('/content/ladder/{conceptId}', [PalContentIntelligenceController::class, 'ladder'])
        ->where('conceptId', '[0-9]+');
    Route::get('/content/practice/{learnerId}/{conceptId}', [PalContentIntelligenceController::class, 'practiceItems'])
        ->where(['learnerId' => '[0-9]+', 'conceptId' => '[0-9]+']);

    // Variant routing
    Route::post('/content/next-variant', [PalContentIntelligenceController::class, 'nextVariant']);
    Route::get('/content/variant-coverage/{conceptId}', [PalContentIntelligenceController::class, 'variants'])
        ->where('conceptId', '[0-9]+');

    // Misconception pipeline
    Route::post('/content/misconception/detect', [PalContentIntelligenceController::class, 'detect']);
    Route::post('/content/misconception/outcome', [PalContentIntelligenceController::class, 'recordOutcome']);
    Route::post('/content/misconception/class-prevalence', [PalContentIntelligenceController::class, 'classPrevalence']);
    Route::get('/content/misconception/health', [PalContentIntelligenceController::class, 'libraryHealth']);

    // Misconception library
    Route::get('/content/misconceptions', [PalContentIntelligenceController::class, 'listMisconceptions']);
    Route::post('/content/misconceptions', [PalContentIntelligenceController::class, 'storeMisconception']);
    Route::get('/content/misconceptions/{id}', [PalContentIntelligenceController::class, 'showMisconception'])
        ->where('id', '[0-9]+');
    Route::post('/content/misconceptions/{id}/correctives', [PalContentIntelligenceController::class, 'storeCorrective'])
        ->where('id', '[0-9]+');

    // Authoring and QA review
    Route::get('/content/review-queue/{entityType}', [PalContentIntelligenceController::class, 'reviewQueue']);
    Route::post('/content/review/{entityType}/bulk', [PalContentIntelligenceController::class, 'bulkTransition']);
    Route::post('/content/review/{entityType}/{metadataId}', [PalContentIntelligenceController::class, 'transition'])
        ->where('metadataId', '[0-9]+');
    Route::get('/content/metadata/{entityType}/{entityId}', [PalContentIntelligenceController::class, 'show'])
        ->where('entityId', '[0-9]+');
    Route::post('/content/metadata/{entityType}/{entityId}', [PalContentIntelligenceController::class, 'upsert'])
        ->where('entityId', '[0-9]+');

    /*
    |--------------------------------------------------------------------------
    | New PAL Content Model
    |--------------------------------------------------------------------------
    */
    Route::prefix('new/content-model')->group(function () {
        $nodeKey = '[A-Z]{2}\.[0-9]+\.[a-z0-9-]+(\.[A-Za-z0-9_.-]+)?';
        $numericId = '[0-9]+';
        $conceptSlug = '[a-z0-9-]+';

        Route::get('/vocabulary', [NewPalContentModelController::class, 'vocabulary']);
        Route::get('/coverage', [NewPalContentModelController::class, 'coverage']);
        Route::get('/review-queue', [NewPalContentModelController::class, 'reviewQueue']);

        Route::get('/chapters', [NewPalContentModelController::class, 'chapters']);
        Route::get('/chapters/{semanticId}', [NewPalContentModelController::class, 'chapter'])
            ->where('semanticId', $numericId);
        Route::get('/chapters/{semanticId}/misconceptions', [NewPalContentModelController::class, 'chapterMisconceptions'])
            ->where('semanticId', $numericId);
        Route::get('/chapters/{semanticId}/concepts/{conceptSlug}', [NewPalContentModelController::class, 'concept'])
            ->where(['semanticId' => $numericId, 'conceptSlug' => $conceptSlug]);
        Route::get('/chapters/{semanticId}/concepts/{conceptSlug}/ladder', [NewPalContentModelController::class, 'ladder'])
            ->where(['semanticId' => $numericId, 'conceptSlug' => $conceptSlug]);

        // Literal route must remain before the {nodeKey} wildcard.
        Route::post('/nodes/bulk-transition', [NewPalContentModelController::class, 'bulkTransition']);

        Route::get('/nodes/{nodeKey}', [NewPalContentModelController::class, 'node'])
            ->where('nodeKey', $nodeKey);
        Route::post('/nodes/{nodeKey}', [NewPalContentModelController::class, 'saveNode'])
            ->where('nodeKey', $nodeKey);
        Route::get('/nodes/{nodeKey}/revisions', [NewPalContentModelController::class, 'revisions'])
            ->where('nodeKey', $nodeKey);
        Route::post('/nodes/{nodeKey}/restore', [NewPalContentModelController::class, 'restore'])
            ->where('nodeKey', $nodeKey);
        Route::post('/nodes/{nodeKey}/transition', [NewPalContentModelController::class, 'transitionNode'])
            ->where('nodeKey', $nodeKey);
        Route::post('/nodes/{nodeKey}/enrich', [NewPalContentModelController::class, 'enrich'])
            ->where('nodeKey', $nodeKey);
        Route::post('/nodes/{nodeKey}/translate', [NewPalContentModelController::class, 'translate'])
            ->where('nodeKey', $nodeKey);
    });

    /*
    |--------------------------------------------------------------------------
    | PAL Administration
    |--------------------------------------------------------------------------
    */
    Route::prefix('new/administration')->group(function () {
        $subsystem = '[a-z][a-z0-9-]*';

        Route::get('/', [PalArchitectureController::class, 'index']);
        Route::get('/{subsystem}', [PalArchitectureController::class, 'show'])
            ->where('subsystem', $subsystem);
        Route::post('/{subsystem}', [PalArchitectureController::class, 'update'])
            ->where('subsystem', $subsystem);
        Route::post('/{subsystem}/reset', [PalArchitectureController::class, 'reset'])
            ->where('subsystem', $subsystem);
    });

    /*
    |--------------------------------------------------------------------------
    | H5P Model
    |--------------------------------------------------------------------------
    */
    Route::prefix('h5p')->group(function () {
        $h5pType = '[a-z][a-z0-9_]{1,47}';
        $numericId = '[0-9]+';

        // Registry and coverage
        Route::get('/registry', [PalH5PModelController::class, 'registry']);
        Route::get('/coverage-matrix', [PalH5PModelController::class, 'coverageMatrix']);

        // Chapter model
        Route::get('/hub', [PalH5PModelController::class, 'hub']);
        Route::get('/chapters', [PalH5PModelController::class, 'chapters']);
        Route::get('/chapter-model', [PalH5PModelController::class, 'chapterModel']);
        Route::get('/coverage', [PalH5PModelController::class, 'coverage']);
        Route::get('/engagement', [PalH5PModelController::class, 'engagement']);
        Route::get('/pedagogy/select', [PalH5PModelController::class, 'selectPedagogy']);

        // Read-only AI insight layer
        Route::get('/insights', [PalH5PModelController::class, 'insights']);

        // AI tagging proposals
        Route::post('/suggest-tags', [PalH5PModelController::class, 'suggestTags']);

        // xAPI ingestion
        Route::post('/xapi/batch', [PalH5PModelController::class, 'ingestXapiBatch']);
        Route::post('/xapi', [PalH5PModelController::class, 'ingestXapi']);

        // Per-node operations
        Route::get('/nodes/{h5pType}/{nodeId}/preview', [PalH5PModelController::class, 'previewTags'])
            ->where(['h5pType' => $h5pType, 'nodeId' => $numericId]);
        Route::get('/nodes/{h5pType}/{nodeId}', [PalH5PModelController::class, 'node'])
            ->where(['h5pType' => $h5pType, 'nodeId' => $numericId]);
        Route::post('/nodes/{h5pType}/{nodeId}/tags', [PalH5PModelController::class, 'saveTags'])
            ->where(['h5pType' => $h5pType, 'nodeId' => $numericId]);
        Route::post('/nodes/{h5pType}/{nodeId}/transition', [PalH5PModelController::class, 'transitionTags'])
            ->where(['h5pType' => $h5pType, 'nodeId' => $numericId]);
    });

    // ==================== NEW PAL → GAMIFICATION ====================
    //
    // The PAL V4 Gamification & Motivation System: Personal Best, badges,
    // streaks, team challenges, the Career Quest, opt-in Challenge Mode and the
    // session summary.
    //
    // Sits under /new/ alongside /new/content-model, so it is unambiguously a
    // New PAL sub-module and can never collide with the legacy /pal routes.
    // Everything served here is computed from the estate's real learning record
    // (question_paper + lms_online_exam PAL attempts, plus whichever pal_*
    // tables this estate has populated) — nothing is seeded or sampled.
    //
    // Learner scope: a student resolves to themselves; staff must pass
    // ?learner_id=, which `pal.auth` has already ownership-checked upstream.
    //
    // Route ordering follows the same guard as the groups above: every literal
    // segment precedes the wildcard that could swallow it, numeric ids are
    // constrained to digits, and {badgeId} to the catalogue's key grammar.
    Route::prefix('/new/gamification')->group(function () {
        $badgeId = 'BADGE_[A-Z0-9_]+';

        Route::get('/overview', [NewPalGamificationController::class, 'overview']);
        Route::get('/specification', [NewPalGamificationController::class, 'specification']);

        // Personal Best (§2) — literal /history before the bare resource.
        Route::get('/personal-best/history', [NewPalGamificationController::class, 'personalBestHistory']);
        Route::get('/personal-best', [NewPalGamificationController::class, 'personalBest']);

        // Badges (§3) — /earned is declared before {badgeId}.
        Route::get('/badges/earned', [NewPalGamificationController::class, 'earnedBadges']);
        Route::get('/badges', [NewPalGamificationController::class, 'badges']);
        Route::post('/badges/{badgeId}/revoke', [NewPalGamificationController::class, 'revokeBadge'])
            ->where('badgeId', $badgeId);
        Route::get('/badges/{badgeId}', [NewPalGamificationController::class, 'badge'])
            ->where('badgeId', $badgeId);

        // Streaks (§7).
        Route::get('/streak/history', [NewPalGamificationController::class, 'streakHistory']);
        Route::get('/streak', [NewPalGamificationController::class, 'streak']);

        // Team challenges (§4) — teacher-initiated only.
        Route::get('/team-challenges', [NewPalGamificationController::class, 'teamChallenges']);
        Route::post('/team-challenges', [NewPalGamificationController::class, 'createTeamChallenge']);
        Route::post('/team-challenges/{challengeId}/end', [NewPalGamificationController::class, 'endTeamChallenge'])
            ->where('challengeId', '[0-9]+');
        Route::put('/team-challenges/{challengeId}', [NewPalGamificationController::class, 'updateTeamChallenge'])
            ->where('challengeId', '[0-9]+');
        Route::get('/team-challenges/{challengeId}', [NewPalGamificationController::class, 'teamChallenge'])
            ->where('challengeId', '[0-9]+');

        // Career Quest (§5).
        Route::get('/career-quest/progress', [NewPalGamificationController::class, 'careerQuestProgress']);
        Route::post('/career-quest/interest', [NewPalGamificationController::class, 'declareInterest']);
        Route::post('/career-quest/pathway', [NewPalGamificationController::class, 'choosePathway']);
        Route::post('/career-quest/report', [NewPalGamificationController::class, 'generateCareerReport']);
        Route::get('/career-quest', [NewPalGamificationController::class, 'careerQuest']);

        // Challenge Mode (§6) — the only leaderboard in PAL V4, strictly opt-in.
        Route::get('/challenge-mode/leaderboard', [NewPalGamificationController::class, 'challengeModeLeaderboard']);
        Route::post('/challenge-mode/opt-in', [NewPalGamificationController::class, 'challengeModeOptIn']);
        Route::post('/challenge-mode/submit', [NewPalGamificationController::class, 'challengeModeSubmit']);
        Route::post('/challenge-mode/class-availability', [NewPalGamificationController::class, 'challengeModeAvailability']);
        Route::get('/challenge-mode', [NewPalGamificationController::class, 'challengeMode']);

        // Session summary + celebration queue (§8).
        Route::get('/session-summary', [NewPalGamificationController::class, 'sessionSummary']);
        Route::post('/notifications/read', [NewPalGamificationController::class, 'readNotifications']);
        Route::get('/notifications', [NewPalGamificationController::class, 'notifications']);
});
});
