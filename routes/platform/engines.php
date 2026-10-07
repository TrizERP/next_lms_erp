<?php

use App\Http\Controllers\api\Platform\EngineController;
use Illuminate\Support\Facades\Route;

/*
| Platform engines: approval runs, notification delivery log, scheduler
| run-now, template PDF output and file attachments. Reads need a session;
| every write needs the matching platform.<service> right.
*/

Route::middleware('lms.auth')->group(function () {

    Route::get('/workflow/runs', [EngineController::class, 'runs']);
    Route::get('/workflow/runs/{id}', [EngineController::class, 'run'])->where('id', '[0-9]+');
    Route::post('/workflow/runs', [EngineController::class, 'startRun'])->middleware('perm:platform.workflow,create');
    Route::post('/workflow/runs/{id}/{action}', [EngineController::class, 'actOnRun'])
        ->where(['id' => '[0-9]+', 'action' => 'approve|reject|delegate'])
        ->middleware('perm:platform.workflow,update');

    Route::get('/notifications/log', [EngineController::class, 'notificationLog']);
    Route::post('/notifications/test-send', [EngineController::class, 'sendTest'])->middleware('perm:platform.notification,update');

    Route::post('/scheduler/run-now', [EngineController::class, 'runTaskNow'])
        ->middleware('perm:platform.scheduler,update');

    Route::post('/templates/{id}/pdf', [EngineController::class, 'templatePdf'])->where('id', '[0-9]+');

    Route::get('/files', [EngineController::class, 'files']);
    Route::post('/files', [EngineController::class, 'uploadFile'])->middleware('perm:platform.document,create');
    Route::get('/files/{id}/download', [EngineController::class, 'downloadFile'])->where('id', '[0-9]+');
    Route::delete('/files/{id}', [EngineController::class, 'deleteFile'])->where('id', '[0-9]+')->middleware('perm:platform.document,delete');
});
