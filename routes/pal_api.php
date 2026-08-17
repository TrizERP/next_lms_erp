<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\api\PAL\NewPalContentModelController;
use App\Http\Controllers\api\PAL\NewPalGamificationController;
use App\Http\Controllers\api\PAL\PALAPIController;
use App\Http\Controllers\api\PAL\PedagogyEngineController;
use App\Http\Controllers\api\PAL\PalContentIntelligenceController;
use App\Http\Controllers\api\PAL\PalH5PModelController;
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

    // ==================== CONTENT INTELLIGENCE LAYER (PAL V4 spec) ====================
    //
    // The 4-type content model, 30+ field metadata schema, 5-level Bloom ladder,
    // misconception library and the authoring/review workflow.
    //
    // Route ordering note: every literal segment is declared BEFORE the wildcard
    // that could swallow it, and each numeric wildcard is constrained to digits —
    // the same collision guard the /workspace routes above use.
    //
    // The pre-existing GET /content/variants/{conceptId} (PALAPIController) is
    // left untouched; the variant-coverage view below is a different endpoint on
    // a different path so nothing that already calls the old one breaks.

    // Vocabulary — every dropdown in the authoring UI reads this (spec §9.1)
    Route::get('/content/vocabulary', [PalContentIntelligenceController::class, 'vocabulary']);

    // Coverage / monitoring (spec §7.3)
    Route::get('/content/coverage', [PalContentIntelligenceController::class, 'coverage']);

    // Bloom ladder (spec §3) — literals first, {conceptId} constrained to digits
    Route::post('/content/ladder/evaluate', [PalContentIntelligenceController::class, 'evaluateLadder']);
    Route::post('/content/ladder/regression', [PalContentIntelligenceController::class, 'checkRegression']);
    Route::get('/content/ladder/{conceptId}', [PalContentIntelligenceController::class, 'ladder'])
        ->where('conceptId', '[0-9]+');
    Route::get('/content/practice/{learnerId}/{conceptId}', [PalContentIntelligenceController::class, 'practiceItems'])
        ->where(['learnerId' => '[0-9]+', 'conceptId' => '[0-9]+']);

    // Variant routing (spec §2, CONTENT LAW C7)
    Route::post('/content/next-variant', [PalContentIntelligenceController::class, 'nextVariant']);
    Route::get('/content/variant-coverage/{conceptId}', [PalContentIntelligenceController::class, 'variants'])
        ->where('conceptId', '[0-9]+');

    // Misconception library (spec §4) — singular /misconception/* are the
    // pipeline verbs, plural /misconceptions/* are the library CRUD.
    Route::post('/content/misconception/detect', [PalContentIntelligenceController::class, 'detect']);
    Route::post('/content/misconception/outcome', [PalContentIntelligenceController::class, 'recordOutcome']);
    Route::post('/content/misconception/class-prevalence', [PalContentIntelligenceController::class, 'classPrevalence']);
    Route::get('/content/misconception/health', [PalContentIntelligenceController::class, 'libraryHealth']);

    Route::get('/content/misconceptions', [PalContentIntelligenceController::class, 'listMisconceptions']);
    Route::post('/content/misconceptions', [PalContentIntelligenceController::class, 'storeMisconception']);
    Route::get('/content/misconceptions/{id}', [PalContentIntelligenceController::class, 'showMisconception'])
        ->where('id', '[0-9]+');
    Route::post('/content/misconceptions/{id}/correctives', [PalContentIntelligenceController::class, 'storeCorrective'])
        ->where('id', '[0-9]+');

    // Authoring + QA review console (spec §7.1, §9.1)
    Route::get('/content/review-queue/{entityType}', [PalContentIntelligenceController::class, 'reviewQueue']);
    Route::post('/content/review/{entityType}/bulk', [PalContentIntelligenceController::class, 'bulkTransition']);
    Route::post('/content/review/{entityType}/{metadataId}', [PalContentIntelligenceController::class, 'transition'])
        ->where('metadataId', '[0-9]+');

    Route::get('/content/metadata/{entityType}/{entityId}', [PalContentIntelligenceController::class, 'show'])
        ->where('entityId', '[0-9]+');
    Route::post('/content/metadata/{entityType}/{entityId}', [PalContentIntelligenceController::class, 'upsert'])
        ->where('entityId', '[0-9]+');

    // ==================== NEW PAL → CONTENT MODEL ====================
    //
    // The PAL V4 Content Intelligence Layer projected live out of
    // `semantic_intelligence`: the 4-type content model, the 5-level Bloom
    // ladder, the 30+ field metadata schema, the misconception library, the
    // 6 Indian cultural contexts, the 9 language variants, and the authoring
    // + QA workflow.
    //
    // Prefixed /new/ so it can never collide with the /content/* routes above,
    // which read a different estate (lms_question_master / content_master).
    //
    // Route ordering: literals before wildcards, numeric ids constrained to
    // digits, and {nodeKey} constrained to the derived key grammar
    // (PREFIX.id.concept-slug[.discriminator]) so a key containing dots does
    // not need encoding and cannot swallow a sibling literal route.
    Route::prefix('/new/content-model')->group(function () {
        $nodeKey = '[A-Z]{2}\.[0-9]+\.[a-z0-9-]+(\.[A-Za-z0-9_.-]+)?';

        Route::get('/vocabulary', [NewPalContentModelController::class, 'vocabulary']);
        Route::get('/coverage', [NewPalContentModelController::class, 'coverage']);
        Route::get('/review-queue', [NewPalContentModelController::class, 'reviewQueue']);

        Route::get('/chapters', [NewPalContentModelController::class, 'chapters']);
        Route::get('/chapters/{semanticId}', [NewPalContentModelController::class, 'chapter'])
            ->where('semanticId', '[0-9]+');
        Route::get('/chapters/{semanticId}/misconceptions', [NewPalContentModelController::class, 'chapterMisconceptions'])
            ->where('semanticId', '[0-9]+');
        Route::get('/chapters/{semanticId}/concepts/{conceptSlug}', [NewPalContentModelController::class, 'concept'])
            ->where(['semanticId' => '[0-9]+', 'conceptSlug' => '[a-z0-9-]+']);
        Route::get('/chapters/{semanticId}/concepts/{conceptSlug}/ladder', [NewPalContentModelController::class, 'ladder'])
            ->where(['semanticId' => '[0-9]+', 'conceptSlug' => '[a-z0-9-]+']);

        // The literal /nodes/bulk-transition is declared before {nodeKey}.
        Route::post('/nodes/bulk-transition', [NewPalContentModelController::class, 'bulkTransition']);

        Route::get('/nodes/{nodeKey}', [NewPalContentModelController::class, 'node'])->where('nodeKey', $nodeKey);
        Route::post('/nodes/{nodeKey}', [NewPalContentModelController::class, 'saveNode'])->where('nodeKey', $nodeKey);
        Route::get('/nodes/{nodeKey}/revisions', [NewPalContentModelController::class, 'revisions'])->where('nodeKey', $nodeKey);
        Route::post('/nodes/{nodeKey}/restore', [NewPalContentModelController::class, 'restore'])->where('nodeKey', $nodeKey);
        Route::post('/nodes/{nodeKey}/transition', [NewPalContentModelController::class, 'transitionNode'])->where('nodeKey', $nodeKey);
        Route::post('/nodes/{nodeKey}/enrich', [NewPalContentModelController::class, 'enrich'])->where('nodeKey', $nodeKey);
        Route::post('/nodes/{nodeKey}/translate', [NewPalContentModelController::class, 'translate'])->where('nodeKey', $nodeKey);
    });

    // ==================== H5P MODEL ====================
    //
    // The backend for LMS+PAL → Tech/Learn → Subject → Chapter → H5P Content:
    // the 21-type registry, the 12 pedagogies, the CASEL/NGSS/NCDG/Music/
    // Sports/Finance frameworks, the §9 coverage matrix, per-node PAL tagging,
    // computed §8.3 engagement metadata and the §8.2 xAPI pipeline.
    //
    // Reads the H5P tables that already exist (h5p_scenarios,
    // h5p_interactive_video, h5p_flashcard, the MCQ slice of
    // lms_question_master) plus pal_vocabulary, pal_h5p_node_metadata and
    // pal_telemetry_events. Nothing in a response is hard-coded here.
    //
    // Route ordering: every literal segment precedes the wildcard that could
    // swallow it, {nodeId} is constrained to digits, and {h5pType} to the
    // registry's snake_case code grammar — the same collision guard the
    // /workspace and /new/content-model groups use.
    Route::prefix('/h5p')->group(function () {
        $h5pType = '[a-z][a-z0-9_]{1,47}';

        // Registry + matrix — no chapter needed.
        Route::get('/registry', [PalH5PModelController::class, 'registry']);
        Route::get('/coverage-matrix', [PalH5PModelController::class, 'coverageMatrix']);

        // Chapter-scoped model.
        Route::get('/hub', [PalH5PModelController::class, 'hub']);
        Route::get('/chapters', [PalH5PModelController::class, 'chapters']);
        Route::get('/chapter-model', [PalH5PModelController::class, 'chapterModel']);
        Route::get('/coverage', [PalH5PModelController::class, 'coverage']);
        Route::get('/engagement', [PalH5PModelController::class, 'engagement']);
        Route::get('/pedagogy/select', [PalH5PModelController::class, 'selectPedagogy']);

        // DeepSeek insight layer sitting ON TOP of the xAPI stream: the
        // evidence pack is pure SQL over the events, the narration is the
        // model reading that pack. Read-only.
        Route::get('/insights', [PalH5PModelController::class, 'insights']);

        // AI tagging proposals (never written by the machine itself).
        Route::post('/suggest-tags', [PalH5PModelController::class, 'suggestTags']);

        // xAPI ingest (§8.2). Literals declared before /nodes/{h5pType}.
        Route::post('/xapi/batch', [PalH5PModelController::class, 'ingestXapiBatch']);
        Route::post('/xapi', [PalH5PModelController::class, 'ingestXapi']);

        // Per-node reads and writes.
        Route::get('/nodes/{h5pType}/{nodeId}/preview', [PalH5PModelController::class, 'previewTags'])
            ->where(['h5pType' => $h5pType, 'nodeId' => '[0-9]+']);
        Route::get('/nodes/{h5pType}/{nodeId}', [PalH5PModelController::class, 'node'])
            ->where(['h5pType' => $h5pType, 'nodeId' => '[0-9]+']);
        Route::post('/nodes/{h5pType}/{nodeId}/tags', [PalH5PModelController::class, 'saveTags'])
            ->where(['h5pType' => $h5pType, 'nodeId' => '[0-9]+']);
        Route::post('/nodes/{h5pType}/{nodeId}/transition', [PalH5PModelController::class, 'transitionTags'])
            ->where(['h5pType' => $h5pType, 'nodeId' => '[0-9]+']);
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
