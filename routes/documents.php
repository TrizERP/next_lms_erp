<?php

use App\Http\Controllers\api\Documents\DocumentAggregationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Document module API — read only
|--------------------------------------------------------------------------
|
| The aggregation layer over every document source the ERP already holds. It has
| three GET endpoints and nothing else. There is no POST, PUT or DELETE here by
| design: uploads, edits and deletions stay with the module that owns the record,
| exactly as they work today.
|
| Mounted at `api/documents` by RouteServiceProvider::mapDocumentRoutes(), the
| same way routes/platform.php and routes/brain.php are mounted. Nothing in
| routes/api.php is touched, and no existing route changes behaviour.
|
| AUTHENTICATION: `api.session` on the whole group, deliberately NOT `lms.auth`.
| The two are not interchangeable today. `lms.auth` honours
| `lms_content.api_auth_enforce`, which currently defaults to FALSE, so it logs
| an anonymous request and lets it through — correct for the content routes it
| was written for, where enforcing on day one would black out 56 tenants, and
| wrong for this one. `api.session` validates the JWT signature and returns 401
| when it fails (see Concerns\HydratesLegacyApiSession). This endpoint returns
| student and staff documents in bulk, so an unauthenticated caller must get
| nothing rather than a warning in a log file.
|
| TENANCY rides in the token. The controller reads session('sub_institute_id'),
| which the middleware sets from the verified payload and never from request
| input, so there is no institute parameter to send and no way for a caller to
| name another school.
|
| AUTHORISATION is not yet gated per action, because there is no action: every
| endpoint is a read, and a `perm:` gate would be decoration while
| `RequirePermission` is in warn-only mode. The keys and menu rows this module
| would gate on are the next step, and the route is where they go when they land.
|
*/

Route::middleware('api.session')->group(function () {

    /** Dashboard: every available source, its count, grouped by domain. */
    Route::get('/sources', [DocumentAggregationController::class, 'sources']);

    /** Newest documents across all sources, merged. */
    Route::get('/recent', [DocumentAggregationController::class, 'recent']);

    /** One source's rows: ?source=<key>&search=&page=&per_page=&syear= */
    Route::get('/', [DocumentAggregationController::class, 'index']);
});
