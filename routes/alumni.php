<?php

use App\Http\Controllers\api\alumni\AlumniAccountApiController;
use App\Http\Controllers\api\alumni\AlumniApiController;
use App\Http\Controllers\api\alumni\AlumniAuditApiController;
use App\Http\Controllers\api\alumni\AlumniCommunicationApiController;
use App\Http\Controllers\api\alumni\AlumniDirectoryApiController;
use App\Http\Controllers\api\alumni\AlumniDonationApiController;
use App\Http\Controllers\api\alumni\AlumniDocumentApiController;
use App\Http\Controllers\api\alumni\AlumniEngagementApiController;
use App\Http\Controllers\api\alumni\AlumniEventApiController;
use App\Http\Controllers\api\alumni\AlumniExportApiController;
use App\Http\Controllers\api\alumni\AlumniImportApiController;
use App\Http\Controllers\api\alumni\AlumniMentorshipApiController;
use App\Http\Controllers\api\alumni\AlumniOpportunityApiController;
use App\Http\Controllers\api\alumni\AlumniReportApiController;
use App\Http\Controllers\api\alumni\AlumniStudentApiController;
use App\Http\Controllers\api\alumni\AlumniSurveyApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Alumni Management API (staff side)
|--------------------------------------------------------------------------
| Registered in RouteServiceProvider::mapAlumniRoutes() under the `api` prefix, so every
| URL is /api/alumni/... . `api.session` verifies the bearer JWT and hydrates the tenant;
| `staff.only` keeps Student/Parent profiles out; the role gate is enforced in the
| controllers (the perm: middleware is warn-only here). Writes are POST, like sibling modules.
| Static paths are declared before `{id}` so they are never read as an id.
*/
Route::middleware(['api.session', 'staff.only'])->prefix('alumni')->group(function () {
    $c = AlumniApiController::class;
    $dir = AlumniDirectoryApiController::class;
    $doc = AlumniDocumentApiController::class;
    $exp = AlumniExportApiController::class;
    $don = AlumniDonationApiController::class;
    $rep = AlumniReportApiController::class;
    $imp = AlumniImportApiController::class;
    $aud = AlumniAuditApiController::class;
    $acc = AlumniAccountApiController::class;
    $eng = AlumniEngagementApiController::class;
    $men = AlumniMentorshipApiController::class;
    $opp = AlumniOpportunityApiController::class;
    $com = AlumniCommunicationApiController::class;
    $evt = AlumniEventApiController::class;
    $svy = AlumniSurveyApiController::class;
    $kinds = 'education|career|business|skills|achievements';

    Route::match(['get', 'post'], 'list', [$c, 'index']);
    Route::get('meta', [$c, 'meta']);
    Route::get('dashboard', [$c, 'dashboard']);
    Route::post('store', [$c, 'store']);

    Route::get('directory', [$dir, 'directory']);
    Route::get('directory/options', [$dir, 'directoryOptions']);
    Route::get('batches', [$dir, 'batches']);
    Route::post('export', [$exp, 'export'])->middleware('throttle:alumni-export');
    Route::post('bulk-status', [$exp, 'bulkStatus'])->middleware('throttle:alumni-bulk');

    // Phase C: engagement, mentorship, opportunities (static paths, before {id})
    Route::get('engagement/overview', [$eng, 'overview']);
    Route::get('engagement/leaderboard', [$eng, 'leaderboard']);
    Route::get('engagement/activities', [$eng, 'activities']);
    Route::post('engagement/record', [$eng, 'record']);
    Route::post('engagement/activities/{id}/delete', [$eng, 'destroy'])->whereNumber('id');

    Route::get('mentorship/mentors', [$men, 'mentors']);
    Route::post('mentorship/mentors/store', [$men, 'mentorStore']);
    Route::post('mentorship/mentors/{id}/update', [$men, 'mentorUpdate'])->whereNumber('id');
    Route::post('mentorship/mentors/{id}/delete', [$men, 'mentorDelete'])->whereNumber('id');
    Route::get('mentorship/students', [$men, 'studentSearch']);
    Route::get('mentorship/requests', [$men, 'requests']);
    Route::post('mentorship/requests/store', [$men, 'requestStore']);
    Route::get('mentorship/requests/{id}', [$men, 'requestShow'])->whereNumber('id');
    Route::get('mentorship/requests/{id}/matches', [$men, 'matches'])->whereNumber('id');
    Route::post('mentorship/requests/{id}/match', [$men, 'match'])->whereNumber('id');
    Route::post('mentorship/requests/{id}/respond', [$men, 'respond'])->whereNumber('id');
    Route::post('mentorship/requests/{id}/cancel', [$men, 'cancel'])->whereNumber('id');
    Route::post('mentorship/requests/{id}/complete', [$men, 'complete'])->whereNumber('id');
    Route::post('mentorship/requests/{id}/sessions/store', [$men, 'sessionStore'])->whereNumber('id');
    Route::post('mentorship/sessions/{sid}/update', [$men, 'sessionUpdate'])->whereNumber('sid');
    Route::post('mentorship/sessions/{sid}/feedback', [$men, 'feedback'])->whereNumber('sid');

    Route::get('opportunities', [$opp, 'index']);
    Route::post('opportunities/store', [$opp, 'store']);
    Route::post('opportunities/{id}/update', [$opp, 'update'])->whereNumber('id');
    Route::post('opportunities/{id}/review', [$opp, 'review'])->whereNumber('id');
    Route::post('opportunities/{id}/close', [$opp, 'close'])->whereNumber('id');
    Route::post('opportunities/{id}/delete', [$opp, 'destroy'])->whereNumber('id');

    // Phase D: communication and events (static paths, before {id})
    Route::get('communication/meta', [$com, 'meta']);
    Route::get('communication/templates', [$com, 'templates']);
    Route::post('communication/templates/store', [$com, 'templateStore']);
    Route::post('communication/templates/{id}/update', [$com, 'templateUpdate'])->whereNumber('id');
    Route::post('communication/templates/{id}/delete', [$com, 'templateDelete'])->whereNumber('id');
    Route::post('communication/preview', [$com, 'preview']);
    Route::post('communication/send', [$com, 'send'])->middleware('throttle:alumni-send');
    Route::get('communication/campaigns', [$com, 'campaigns']);
    Route::get('communication/campaigns/{id}', [$com, 'campaignShow'])->whereNumber('id');
    Route::post('communication/campaigns/{id}/send', [$com, 'sendDraft'])->whereNumber('id')->middleware('throttle:alumni-send');
    Route::post('communication/campaigns/{id}/cancel', [$com, 'cancel'])->whereNumber('id');
    Route::get('communication/history', [$com, 'history']);

    // Portal accounts (staff side): invitations and account control. The alumnus's own side is routes/alumni_portal.php.
    Route::get('accounts', [$acc, 'index']);
    Route::post('accounts/{id}/disable', [$acc, 'disable'])->whereNumber('id');
    Route::post('accounts/{id}/enable', [$acc, 'enable'])->whereNumber('id');
    Route::post('accounts/{id}/revoke-sessions', [$acc, 'revokeSessions'])->whereNumber('id');
    Route::post('accounts/{id}/resend-verification', [$acc, 'resendVerification'])->whereNumber('id')->middleware('throttle:alumni-send');

    // Phase F: audit-log viewer (read-only; Admin-type profiles only, in the controller)
    Route::get('audit', [$aud, 'index']);
    Route::get('audit/{id}', [$aud, 'show'])->whereNumber('id');

    // Phase F: bulk import - nothing is written to the alumni list until confirm
    Route::get('import/meta', [$imp, 'meta']);
    Route::get('import', [$imp, 'index']);
    Route::post('import/upload', [$imp, 'upload'])->middleware('throttle:alumni-import');
    Route::post('import/template', [$imp, 'template']);
    Route::get('import/{id}', [$imp, 'show'])->whereNumber('id');
    Route::get('import/{id}/rows', [$imp, 'rows'])->whereNumber('id');
    Route::post('import/{id}/preview', [$imp, 'preview'])->whereNumber('id')->middleware('throttle:alumni-import');
    Route::post('import/{id}/confirm', [$imp, 'confirm'])->whereNumber('id')->middleware('throttle:alumni-import');
    Route::post('import/{id}/cancel', [$imp, 'cancel'])->whereNumber('id');
    Route::post('import/{id}/problems', [$imp, 'problems'])->whereNumber('id');

    // Phase F: reports and their exports (the gate is per report, in the controller)
    Route::get('reports', [$rep, 'index']);
    Route::get('reports/{key}', [$rep, 'show'])->where('key', '[a-z_]+');
    Route::post('reports/{key}/export', [$rep, 'export'])->where('key', '[a-z_]+')->middleware('throttle:alumni-export');

    // Phase E: donations - recorded offline, confirmed by a person, no gateway (finance gate in the controller)
    Route::get('donations/campaigns', [$don, 'campaigns']);
    Route::post('donations/campaigns/store', [$don, 'saveCampaign']);
    Route::post('donations/campaigns/{id}/update', [$don, 'saveCampaign'])->whereNumber('id');
    Route::get('donations/summary', [$don, 'summary']);
    Route::get('donations/donors', [$don, 'donors'])->middleware('throttle:alumni-pay');
    Route::get('donations', [$don, 'index']);
    Route::post('donations/store', [$don, 'store'])->middleware('throttle:alumni-pay');
    Route::get('donations/{id}', [$don, 'show'])->whereNumber('id');
    Route::post('donations/{id}/update', [$don, 'update'])->whereNumber('id')->middleware('throttle:alumni-pay');
    Route::post('donations/{id}/received', [$don, 'received'])->whereNumber('id')->middleware('throttle:alumni-pay');
    Route::post('donations/{id}/verify', [$don, 'verify'])->whereNumber('id')->middleware('throttle:alumni-pay');
    Route::post('donations/{id}/reject', [$don, 'reject'])->whereNumber('id')->middleware('throttle:alumni-pay');
    Route::post('donations/{id}/cancel', [$don, 'cancel'])->whereNumber('id')->middleware('throttle:alumni-pay');
    Route::post('donations/{id}/refund', [$don, 'refund'])->whereNumber('id')->middleware('throttle:alumni-pay');
    Route::get('donations/{id}/receipt', [$don, 'receipt'])->whereNumber('id');

    Route::get('events', [$evt, 'index']);
    Route::post('events/store', [$evt, 'store']);
    Route::get('events/{id}', [$evt, 'show'])->whereNumber('id');
    Route::post('events/{id}/update', [$evt, 'update'])->whereNumber('id');
    Route::post('events/{id}/publish', [$evt, 'publish'])->whereNumber('id');
    Route::post('events/{id}/cancel', [$evt, 'cancel'])->whereNumber('id');
    Route::post('events/{id}/complete', [$evt, 'complete'])->whereNumber('id');
    Route::post('events/{id}/invite', [$evt, 'invite'])->whereNumber('id')->middleware('throttle:alumni-send');
    Route::get('events/{id}/registrations', [$evt, 'registrations'])->whereNumber('id');
    Route::post('events/{id}/registrations/store', [$evt, 'register'])->whereNumber('id');
    Route::post('events/{id}/registrations/{rid}/confirm', [$evt, 'confirm'])->whereNumber(['id', 'rid']);
    Route::post('events/{id}/registrations/{rid}/cancel', [$evt, 'cancelRegistration'])->whereNumber(['id', 'rid']);
    Route::post('events/{id}/registrations/{rid}/payment', [$evt, 'payment'])->whereNumber(['id', 'rid']);
    Route::post('events/{id}/attendance', [$evt, 'attendance'])->whereNumber('id');
    Route::post('events/{id}/feedback', [$evt, 'feedback'])->whereNumber('id');
    Route::get('events/{id}/report', [$evt, 'report'])->whereNumber('id');
    Route::post('events/{id}/files/upload', [$evt, 'fileUpload'])->whereNumber('id')->middleware('throttle:alumni-upload');
    Route::post('events/{id}/files/{fid}/download', [$evt, 'fileDownload'])->whereNumber(['id', 'fid']);
    Route::post('events/{id}/files/{fid}/delete', [$evt, 'fileDelete'])->whereNumber(['id', 'fid']);

    Route::get('surveys', [$svy, 'index']);
    Route::post('surveys/store', [$svy, 'store']);
    Route::get('surveys/{id}', [$svy, 'show'])->whereNumber('id');
    Route::post('surveys/{id}/update', [$svy, 'update'])->whereNumber('id');
    Route::post('surveys/{id}/publish', [$svy, 'publish'])->whereNumber('id');
    Route::post('surveys/{id}/close', [$svy, 'close'])->whereNumber('id');
    Route::post('surveys/{id}/delete', [$svy, 'destroy'])->whereNumber('id');
    Route::post('surveys/{id}/send', [$svy, 'send'])->whereNumber('id')->middleware('throttle:alumni-send');
    Route::get('surveys/{id}/results', [$svy, 'results'])->whereNumber('id');
    Route::get('surveys/{id}/participation', [$svy, 'participation'])->whereNumber('id');
    Route::post('surveys/{id}/respond', [$svy, 'respond'])->whereNumber('id');

    Route::get('conversion/preview', [$c, 'conversionPreview']);
    Route::post('conversion/run', [$c, 'conversionRun'])->middleware('throttle:alumni-conversion');
    Route::get('settings', [$c, 'settings']);
    Route::post('settings', [$c, 'saveSettings']);

    Route::get('{id}', [$c, 'show'])->whereNumber('id');
    Route::post('{id}/update', [$c, 'update'])->whereNumber('id');
    Route::post('{id}/status', [$c, 'status'])->whereNumber('id');
    Route::post('{id}/verify', [$c, 'verify'])->whereNumber('id');
    Route::get('{id}/academic-history', [$c, 'academicHistory'])->whereNumber('id');

    Route::get('{id}/engagement', [$eng, 'forAlumnus'])->whereNumber('id');
    Route::get('{id}/communication', [$com, 'alumnusHistory'])->whereNumber('id');
    Route::get('{id}/events', [$evt, 'alumnusEvents'])->whereNumber('id');
    Route::get('{id}/donations', [$don, 'alumnusDonations'])->whereNumber('id');
    Route::get('{id}/account', [$acc, 'show'])->whereNumber('id');
    Route::post('{id}/invite', [$acc, 'invite'])->whereNumber('id')->middleware('throttle:alumni-send');
    Route::get('{id}/privacy', [$dir, 'privacyShow'])->whereNumber('id');
    Route::post('{id}/privacy', [$dir, 'privacySave'])->whereNumber('id');

    Route::get('{id}/documents', [$doc, 'index'])->whereNumber('id');
    Route::post('{id}/documents/upload', [$doc, 'upload'])->whereNumber('id')->middleware('throttle:alumni-upload');
    Route::post('{id}/documents/{docId}/download', [$doc, 'download'])->whereNumber(['id', 'docId']);
    Route::post('{id}/documents/{docId}/verify', [$doc, 'verify'])->whereNumber(['id', 'docId']);
    Route::post('{id}/documents/{docId}/delete', [$doc, 'destroy'])->whereNumber(['id', 'docId']);
    Route::post('{id}/achievements/{rid}/verify', [$doc, 'verifyAchievement'])->whereNumber(['id', 'rid']);

    Route::get('{id}/{kind}', [$c, 'childList'])->whereNumber('id')->where('kind', $kinds);
    Route::post('{id}/{kind}/store', [$c, 'childStore'])->whereNumber('id')->where('kind', $kinds);
    Route::post('{id}/{kind}/{rid}/update', [$c, 'childUpdate'])->whereNumber(['id', 'rid'])->where('kind', $kinds);
    Route::post('{id}/{kind}/{rid}/delete', [$c, 'childDelete'])->whereNumber(['id', 'rid'])->where('kind', $kinds);
});

/*
| Student-facing routes: the same `api.session` JWT check but NOT `staff.only`. Each action is bound to the
| student in the token (see AlumniStudentApiController), so these never expose another student's data.
*/
Route::middleware(['api.session'])->prefix('alumni/student')->group(function () {
    $st = AlumniStudentApiController::class;
    Route::get('opportunities', [$st, 'opportunities']);
    Route::get('opportunities/{id}', [$st, 'opportunityShow'])->whereNumber('id');
    Route::get('mentorship/mentors', [$st, 'mentors']);
    Route::get('mentorship/requests', [$st, 'myRequests']);
    Route::post('mentorship/requests/store', [$st, 'requestStore']);
    Route::post('mentorship/requests/{id}/cancel', [$st, 'requestCancel'])->whereNumber('id');
    Route::post('mentorship/sessions/{sid}/feedback', [$st, 'sessionFeedback'])->whereNumber('sid');
});
