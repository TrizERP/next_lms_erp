<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
	 /**
     * Indicates whether the XSRF-TOKEN cookie should be set on the response.
     *
     * @var bool
     */
    protected $addHttpCookie = true;

    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        'circular/*',
        'api/*',
        'fees/*',
        'https://erp.triz.co.in/*',
        'https://dev.triz.co.in/*',
        'http://127.0.0.1:8000/*',
        // Career certainty (CI-GUIDE-DEV-001): a stateless JWT-bearer endpoint
        // reached through the Next.js proxy, same as api/* — the client never
        // holds a Laravel session cookie/XSRF token pair to present.
        'studentAspiration',
        // Career ambition / originality (CI-GUIDE-DEV-001, Career Awareness
        // Level-3): same stateless JWT-bearer reasoning as studentAspiration.
        'studentAmbition',
        'studentOriginality',
        // PAL student flow: stateless JWT-bearer endpoints the Next.js SPA
        // posts to directly, exactly like api/* above. The client never holds a
        // Laravel session cookie/XSRF token pair to present.
        //
        // These are listed as PATHS rather than relying on the full-URL entries
        // above, which match only the hosts named there. The SPA resolves its
        // base URL from the logged-in user's host_name and treats localhost,
        // 127.0.0.1, 192.168.*, 10.* and *.local as dev hosts - so a developer
        // on http://localhost:8000 hit 419 on every answer while the same build
        // on http://127.0.0.1:8000 worked. The symptom was invisible: each
        // answer POST failed, no answer was recorded, and the Submit button
        // stayed disabled, so clicking it appeared to do nothing at all.
        //
        // Identity is still enforced per request by
        // palController::resolveAuthorizedContext(), which requires a valid JWT
        // when no session is present and refuses cross-student access.
        'lms/pal/adaptive/answer',
        'lms/pal/diagnostic/attempt/*/submit',
        'lms/pal/learn/concept/*/read',
    ];
}
