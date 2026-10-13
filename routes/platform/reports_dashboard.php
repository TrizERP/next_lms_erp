<?php

use App\Http\Controllers\api\Platform\ReportsDashboardController;
use Illuminate\Support\Facades\Route;

/*
| Reporting engine and dashboard engine. Reads need a session; schedule changes
| need platform.report; the dashboard is per user, so it needs only a session.
*/

Route::middleware('lms.auth')->group(function () {

    Route::get('/reports', [ReportsDashboardController::class, 'catalog']);
    Route::get('/reports/schedules', [ReportsDashboardController::class, 'schedules']);
    Route::get('/reports/{key}/export', [ReportsDashboardController::class, 'export'])->where('key', '[A-Za-z0-9_.]+');

    Route::post('/reports/schedules', [ReportsDashboardController::class, 'storeSchedule'])->middleware('perm:platform.report,create');
    Route::put('/reports/schedules/{id}', [ReportsDashboardController::class, 'toggleSchedule'])->where('id', '[0-9]+')->middleware('perm:platform.report,update');
    Route::delete('/reports/schedules/{id}', [ReportsDashboardController::class, 'deleteSchedule'])->where('id', '[0-9]+')->middleware('perm:platform.report,delete');
    Route::post('/reports/schedules/{id}/run-now', [ReportsDashboardController::class, 'runSchedule'])->where('id', '[0-9]+')->middleware('perm:platform.report,update');

    Route::get('/dashboard', [ReportsDashboardController::class, 'dashboard']);
    Route::put('/dashboard/layout', [ReportsDashboardController::class, 'saveDashboardLayout']);
});
