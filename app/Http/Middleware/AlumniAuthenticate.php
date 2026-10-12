<?php

namespace App\Http\Middleware;

use App\Services\Alumni\AlumniAuthService;
use App\Services\Alumni\AlumniRuleException;
use Closure;
use Illuminate\Http\Request;

/**
 * Guards the alumni portal's own routes (`api/alumni-portal/*` other than sign-in and registration).
 *
 * It accepts ONLY an alumni access token (signed with ALUMNI_JWT_SECRET, audience `alumni`) and checks, on every request, that
 * the login session is live, the account is active and the alumnus is not blocked. The identity is put in a request ATTRIBUTE
 * (`alumni_auth`) and nowhere else: this middleware deliberately does NOT build the legacy session that staff controllers read,
 * so even a bug elsewhere cannot make an alumni request look like a staff or student one. Controllers must take the institute and
 * alumnus from this attribute, never from the request body.
 */
class AlumniAuthenticate
{
    public function __construct(private AlumniAuthService $auth)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $bearer = $request->bearerToken();
        if (! $bearer) {
            return $this->deny('Please sign in.', 401);
        }
        try {
            $context = $this->auth->authenticate($bearer);
        } catch (AlumniRuleException $e) {
            return $this->deny($e->getMessage(), $e->status);
        }
        if ($context === null) {
            return $this->deny('Your session has ended. Please sign in again.', 401);
        }
        $request->attributes->set('alumni_auth', $context);

        return $next($request);
    }

    private function deny(string $message, int $status)
    {
        return response()->json(['status_code' => 0, 'message' => $message], $status);
    }
}
