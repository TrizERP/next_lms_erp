<?php

use App\Http\Controllers\api\Platform\FeesIntelligenceController;
use Illuminate\Support\Facades\Route;

/*
| The Fees Intelligence banner (family/sibling groups, largest arrears, the
| concession/refund queue, channel engagement, TC no-dues clearance,
| escalation rule) and the request-submission endpoints that feed the
| queue card.
|
| `lms.staff` on every route: this reports tenant-wide arrears and family
| data, which a student or parent session must never read, unlike the
| config-only Workflow/Notification/Scheduler reads in routes/platform.php.
| Writes carry the same `perm:platform.workflow,*` keys startRun/actOnRun
| already use in routes/platform/engines.php, since submitting or acting on
| a concession/refund request runs through the same approval engine.
*/

Route::middleware(['lms.auth', 'lms.staff'])->group(function () {

    Route::get('/workflow/fees-intelligence', [FeesIntelligenceController::class, 'summary']);
    Route::get('/workflow/fees-intelligence/families', [FeesIntelligenceController::class, 'families']);
    Route::get('/workflow/fees-intelligence/families/{anchorStudentId}', [FeesIntelligenceController::class, 'family'])
        ->where('anchorStudentId', '[0-9]+');
    Route::get('/workflow/fees-intelligence/arrears', [FeesIntelligenceController::class, 'arrears']);
    Route::get('/workflow/fees-intelligence/graph-signals', [FeesIntelligenceController::class, 'graphSignals']);

    Route::get('/fees/concession-requests', [FeesIntelligenceController::class, 'requestsIndex']);
    Route::post('/fees/concession-requests', [FeesIntelligenceController::class, 'submitRequest'])
        ->middleware('perm:platform.workflow,create');
    Route::post('/fees/concession-requests/{runId}/{action}', [FeesIntelligenceController::class, 'actOnRequest'])
        ->where(['runId' => '[0-9]+', 'action' => 'approve|reject|delegate'])
        ->middleware('perm:platform.workflow,update');
    Route::get('/fees/concession-grants/{grantId}', [FeesIntelligenceController::class, 'grant'])
        ->where('grantId', '[0-9]+');
});
