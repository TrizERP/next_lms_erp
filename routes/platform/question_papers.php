<?php

use App\Http\Controllers\api\Platform\QuestionPaperController;
use App\Http\Controllers\api\Platform\UserNotificationController;
use Illuminate\Support\Facades\Route;

/*
| Question Paper uploads (Document Library) and the per-person notification read state.
| Mounted under /api/platform by RouteServiceProvider. Identity and school come from the verified
| token (lms.auth); question papers are a staff feature.
*/

Route::middleware('lms.auth')->group(function () {
    Route::middleware('lms.staff')->group(function () {
        Route::post('/question-papers', [QuestionPaperController::class, 'store']);
        Route::get('/question-papers', [QuestionPaperController::class, 'index']);
        Route::get('/question-papers/options', [QuestionPaperController::class, 'options']);
        Route::get('/question-papers/{uuid}', [QuestionPaperController::class, 'show'])->whereUuid('uuid');
        Route::post('/question-papers/{uuid}/retry', [QuestionPaperController::class, 'retry'])->whereUuid('uuid');
        Route::delete('/question-papers/{uuid}', [QuestionPaperController::class, 'destroy'])->whereUuid('uuid');
        Route::get('/question-papers/{uuid}/items', [QuestionPaperController::class, 'items'])->whereUuid('uuid');
        Route::post('/question-papers/{uuid}/activate', [QuestionPaperController::class, 'activate'])->whereUuid('uuid');
    });

    Route::post('/notifications/user/read-all', [UserNotificationController::class, 'markAllRead']);
    Route::post('/notifications/user/{id}/read', [UserNotificationController::class, 'markRead'])->whereNumber('id');
});
