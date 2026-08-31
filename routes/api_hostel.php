<?php

use App\Http\Controllers\api\HostelSetupApiController;
use Illuminate\Support\Facades\Route;

// Registered stateless (prefix('api')->middleware('api')) in
// RouteServiceProvider::mapHostelApiRoutes(). The bare `api` group is just
// throttle + SubstituteBindings (see app/Http/Kernel.php) — no auth — so
// `api.session` (real JWT validation + legacy-session hydration) is added
// here explicitly, matching the convention used by every other ported
// module (see routes/talent_management.php, routes/organization_management.php).
Route::middleware(['api.session'])->group(function () {
    Route::get('hostel-setup/{module}', [HostelSetupApiController::class, 'index']);
    Route::post('hostel-setup/{module}', [HostelSetupApiController::class, 'store']);
    Route::match(['put', 'patch'], 'hostel-setup/{module}/{id}', [HostelSetupApiController::class, 'update']);
    Route::delete('hostel-setup/{module}/{id}', [HostelSetupApiController::class, 'destroy']);
});
