<?php

use App\Http\Controllers\api\Platform\AuditController;
use App\Http\Controllers\api\Platform\IntegrationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Platform services — shared audit trail and integrations store
|--------------------------------------------------------------------------
|
| Auto-mounted under /api/platform by RouteServiceProvider::mapPlatformRoutes().
|
| AUDIT is read-only over HTTP. Entries are written by other modules through
| App\Services\Platform\AuditTrail::record(); no route can create, edit or
| delete one. Reads are gated like Event Bus (they expose who did what):
| `perm:platform.audit,view`. (The `lms.staff` alias used by the Event Bus
| routes is not registered in app/Http/Kernel.php, so it is deliberately not used here.)
|
| INTEGRATIONS: reads need a session only (secrets are masked in every
| response); writes and the connectivity test need `perm:platform.integration`.
*/

Route::middleware('lms.auth')->group(function () {

    Route::prefix('/audit')
        ->middleware('perm:platform.audit,view')
        ->group(function () {
            Route::get('/', [AuditController::class, 'index']);
            Route::get('/summary', [AuditController::class, 'summary']);
        });

    Route::get('/integrations', [IntegrationController::class, 'index']);
    Route::get('/integrations/{id}', [IntegrationController::class, 'show'])->where('id', '[0-9]+');
    Route::post('/integrations', [IntegrationController::class, 'store'])
        ->middleware('perm:platform.integration,create');
    Route::put('/integrations/{id}', [IntegrationController::class, 'update'])
        ->where('id', '[0-9]+')
        ->middleware('perm:platform.integration,update');
    Route::delete('/integrations/{id}', [IntegrationController::class, 'destroy'])
        ->where('id', '[0-9]+')
        ->middleware('perm:platform.integration,delete');
    Route::post('/integrations/{id}/test', [IntegrationController::class, 'test'])
        ->where('id', '[0-9]+')
        ->middleware('perm:platform.integration,update');
});
