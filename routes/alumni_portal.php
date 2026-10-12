<?php

use App\Http\Controllers\api\alumni\AlumniPortalApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Alumni portal API (the alumnus's own side) - /api/alumni-portal/...
|--------------------------------------------------------------------------
| Registered in RouteServiceProvider::mapAlumniPortalRoutes(). These routes are deliberately NOT behind `api.session`, `api.jwt`
| or `staff.only`: alumni tokens are a different kind of token (different secret and audience, see section 8 of
| docs/ALUMNI_MODULE_IMPLEMENTATION_PLAN.md) and the legacy middleware would misread one as a staff user.
|
|  - The public group needs no token and is rate-limited per email AND per address.
|  - The signed-in group runs `alumni.auth`, which accepts only alumni tokens and checks the live session on every request.
*/
Route::prefix('alumni-portal')->group(function () {
    $c = AlumniPortalApiController::class;

    // public (no token)
    Route::post('register', [$c, 'register'])->middleware('throttle:portal-register');
    Route::post('login', [$c, 'login'])->middleware('throttle:portal-login');
    Route::post('refresh', [$c, 'refresh'])->middleware('throttle:portal-token');
    Route::post('verify-email', [$c, 'verifyEmail'])->middleware('throttle:portal-token');
    Route::post('resend-verification', [$c, 'resendVerification'])->middleware('throttle:portal-reset');
    Route::post('forgot-password', [$c, 'forgotPassword'])->middleware('throttle:portal-reset');
    Route::post('reset-password', [$c, 'resetPassword'])->middleware('throttle:portal-reset');
    Route::post('invite/check', [$c, 'inviteCheck'])->middleware('throttle:portal-token');
    Route::post('invite/accept', [$c, 'inviteAccept'])->middleware('throttle:portal-token');

    // signed in as an alumnus
    Route::middleware(['alumni.auth', 'throttle:portal-api'])->group(function () use ($c) {
        Route::get('me', [$c, 'me']);
        Route::post('me/update', [$c, 'update']);
        Route::get('me/privacy', [$c, 'privacy']);
        Route::post('me/privacy', [$c, 'savePrivacy']);
        Route::post('change-password', [$c, 'changePassword']);
        Route::post('logout', [$c, 'logout']);
        Route::get('events', [$c, 'events']);
        Route::post('events/{id}/register', [$c, 'registerForEvent'])->whereNumber('id');
        Route::post('events/{id}/cancel', [$c, 'cancelEventRegistration'])->whereNumber('id');
    });
});
