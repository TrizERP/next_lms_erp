<?php

use App\Http\Controllers\api\Platform\NotificationController;
use App\Http\Controllers\api\Platform\RegistryController;
use App\Http\Controllers\api\Platform\SchedulerController;
use App\Http\Controllers\api\Platform\WorkflowController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Platform services API
|--------------------------------------------------------------------------
|
| The three centralised engines every module configures against rather than
| rebuilding: Communication (which notification is raised, on which channel),
| Scheduler (when each component's recurring work runs) and Workflow (who signs
| off on which action). All three address the same catalogue —
| config/platform_services.php — as `module.component`, which is why they share
| one route file and one registry endpoint.
|
| Prefixed `api/platform` by RouteServiceProvider::mapPlatformRoutes(), the same
| way routes/brain.php is mounted. Nothing in routes/api.php is touched.
|
| AUTHENTICATION: `lms.auth` on the whole group. These are administration
| screens; an anonymous caller has no institute to scope to, so the controllers
| answer 401 rather than guessing. Nothing in the product read these endpoints
| before today, so starting strict cannot break anything — the opposite
| trade-off from the content routes, and deliberate.
|
| AUTHORISATION: `perm:platform.<service>,<action>` on every write, and the route
| is the authority. The frontend also asks /api/permissions so it can disable a
| control the user cannot use, but that is cosmetic — Decision #23, configuration
| never grants. The three keys are registered in config/rbac_modules.php; an
| unregistered key answers deny-all, which is the correct failure.
|
| READS ARE NOT PERMISSION-GATED beyond needing a session. Seeing which
| notifications the product can raise is useful to most staff and harmful to
| none; it is CHANGING them for an entire institute that is an administrator's
| right.
|
*/

Route::middleware('lms.auth')->group(function () {

    // The catalogue every screen renders from and every write validates against.
    Route::get('/registry', [RegistryController::class, 'index']);

    /*
    | Communication.
    |
    | The matrix save is a PUT with a list of changes because the screen has one
    | Save changes button over a whole module. Channels are a separate endpoint,
    | one switch per call: it is the highest-blast-radius control here, and a
    | bulk body invites turning three channels off by accident.
    */
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::put('/notifications', [NotificationController::class, 'update'])
        ->middleware('perm:platform.notification,update');
    Route::put('/notifications/channels', [NotificationController::class, 'updateChannel'])
        ->middleware('perm:platform.notification,update');

    /*
    | Scheduler. One task per call — a schedule is edited deliberately, and each
    | edit is worth its own audit entry.
    */
    Route::get('/scheduler', [SchedulerController::class, 'index']);
    Route::put('/scheduler', [SchedulerController::class, 'update'])
        ->middleware('perm:platform.scheduler,update');

    /*
    | Workflow. The verbs carry their own actions rather than all mapping to
    | `update`: defining a new approval chain and deleting one are genuinely
    | different rights, and flattening them would mean anyone who may adjust an
    | SLA may also delete the chain.
    */
    Route::get('/workflow', [WorkflowController::class, 'index']);
    Route::post('/workflow', [WorkflowController::class, 'store'])
        ->middleware('perm:platform.workflow,create');
    Route::put('/workflow/{id}', [WorkflowController::class, 'update'])
        ->where('id', '[0-9]+')
        ->middleware('perm:platform.workflow,update');
    Route::delete('/workflow/{id}', [WorkflowController::class, 'destroy'])
        ->where('id', '[0-9]+')
        ->middleware('perm:platform.workflow,delete');
});
