<?php

use App\Http\Controllers\api\PAL\EsoEngineController;
use App\Http\Controllers\api\PAL\PilotMetricsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Adaptive Learning Engine (Learning ESO) API
|--------------------------------------------------------------------------
|
| Kept in its own file rather than appended to routes/pal_api.php — that
| file is already ~135 routes with at least one confirmed prefix-nesting bug
| (see docs/ADAPTIVE_LEARNING_ENGINE_IMPLEMENTATION_PLAN.md §L.6). All routes
| here are additive; none touch an existing PAL endpoint.
|
| Every route names the learner `{learnerId}` so pal.auth's
| PalApiAuth::resolveTargetLearner() enforces per-learner ownership the same
| way it already does for every other PAL V4 route.
*/
Route::prefix('api/pal/eso')->middleware('pal.auth')->group(function () {
    // ── Reporting / tenant-level — teacher/parent/staff readable, per the
    // existing institute/class scoping PalApiAuth already applies to every
    // other PAL V4 feature. Deliberately NOT student-only: this is exactly
    // the "teacher may continue to view student progress" carve-out. ──────

    // No {learnerId} — this lists chapter content, not per-learner state, so
    // it deliberately does not go through the per-learner ownership check.
    Route::get('/chapter-concepts/{chapterId}', [EsoEngineController::class, 'chapterConcepts'])
        ->where('chapterId', '[0-9]+');

    // "The plain-language audit trace for parents/teachers" (see
    // EsoEngineController::decisionLog()) — a read-only report, not a way to
    // act as the learner, so it stays on ordinary institute/class scoping.
    Route::get('/decision-log/{learnerId}/{conceptId}', [EsoEngineController::class, 'decisionLog'])
        ->where(['learnerId' => '[0-9]+', 'conceptId' => '[0-9]+']);

    Route::get('/pilot/metrics', [PilotMetricsController::class, 'summary']);

    // ── Start/advance a session — student-only (see eso.student /
    // EsoStudentOnlyAuth). Every route below either serves or mutates one
    // student's live learning state; a teacher/staff/admin session must
    // never be able to act as the learner here, even for a student
    // genuinely within their institute/class scope. ──────────────────────
    Route::middleware('eso.student')->group(function () {
        Route::get('/diagnostic/{learnerId}/{conceptId}', [EsoEngineController::class, 'diagnostic'])
            ->where(['learnerId' => '[0-9]+', 'conceptId' => '[0-9]+']);

        Route::post('/diagnostic/{learnerId}/{conceptId}/submit', [EsoEngineController::class, 'submitDiagnostic'])
            ->where(['learnerId' => '[0-9]+', 'conceptId' => '[0-9]+']);

        Route::get('/practice-item/{learnerId}/{nodeId}', [EsoEngineController::class, 'practiceItem'])
            ->where(['learnerId' => '[0-9]+', 'nodeId' => '[0-9]+']);

        Route::get('/next-action/{learnerId}/{conceptId}', [EsoEngineController::class, 'nextAction'])
            ->where(['learnerId' => '[0-9]+', 'conceptId' => '[0-9]+']);

        Route::post('/practice/{learnerId}/{nodeId}/attempt', [EsoEngineController::class, 'recordAttempt'])
            ->where(['learnerId' => '[0-9]+', 'nodeId' => '[0-9]+']);

        Route::post('/retrieval/{learnerId}/{nodeId}/check', [EsoEngineController::class, 'retrievalCheck'])
            ->where(['learnerId' => '[0-9]+', 'nodeId' => '[0-9]+']);

        Route::get('/retrieval-items/{learnerId}/{nodeId}', [EsoEngineController::class, 'retrievalItems'])
            ->where(['learnerId' => '[0-9]+', 'nodeId' => '[0-9]+']);

        Route::get('/due-for-retrieval/{learnerId}', [EsoEngineController::class, 'dueForRetrieval'])
            ->where('learnerId', '[0-9]+');

        Route::post('/render', [EsoEngineController::class, 'render']);
    });
});
