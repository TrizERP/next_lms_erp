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
    ];
}
