<?php

use App\Http\Controllers\api\MobilePageBuilderAdminApiController;
use App\Http\Controllers\api\MobilePageBuilderApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile Page Builder routes
|--------------------------------------------------------------------------
|
| Mounted at api/mobile-page-builder by RouteServiceProvider::
| mapMobilePageBuilderRoutes(), the same way routes/documents.php and
| routes/platform.php get their own prefix rather than growing routes/api.php.
| Every route is behind `api.session` (real JWT, hydrated session), matching
| the mobile/dynamic-page-admin group in routes/api.php rather than the older
| manual-JWT (GetsJwtToken) pattern. GET for reads, POST for create/update,
| DELETE for delete -- the same verb convention that group already uses.
|
*/

Route::middleware('api.session')->group(function () {
    // Any authenticated tenant user -- what a Custom Mobile Page's Next.js
    // runtime route actually calls. Never admin-gated, never serves a draft.
    Route::get('runtime/{slug}', [MobilePageBuilderApiController::class, 'runtime']);

    // Admin/Super-Admin only (enforced inside the controller, same as
    // MobileDynamicPageAdminApiController) -- authoring.
    Route::prefix('pages')->group(function () {
        Route::get('/', [MobilePageBuilderAdminApiController::class, 'index']);
        Route::post('/', [MobilePageBuilderAdminApiController::class, 'store']);
        Route::get('{id}', [MobilePageBuilderAdminApiController::class, 'show']);
        Route::post('{id}', [MobilePageBuilderAdminApiController::class, 'update']);
        Route::post('{id}/draft', [MobilePageBuilderAdminApiController::class, 'saveDraft']);
        Route::post('{id}/publish', [MobilePageBuilderAdminApiController::class, 'publish']);
        Route::get('{id}/versions', [MobilePageBuilderAdminApiController::class, 'versions']);
        Route::delete('{id}', [MobilePageBuilderAdminApiController::class, 'destroy']);
        Route::post('{id}/assets', [MobilePageBuilderAdminApiController::class, 'uploadAsset']);
    });

    // "Select Existing Page" in the create flow -- see MobileFormFieldRegistry.
    Route::prefix('source-pages')->group(function () {
        Route::get('/', [MobilePageBuilderAdminApiController::class, 'sourcePages']);
        Route::get('{key}', [MobilePageBuilderAdminApiController::class, 'sourcePage']);
    });

    // Every real page on the tenant's sidebar (tblmenumaster), for "browse
    // all my pages" in the same picker -- see menuPages()'s own doc.
    Route::get('menu-pages', [MobilePageBuilderAdminApiController::class, 'menuPages']);
});
