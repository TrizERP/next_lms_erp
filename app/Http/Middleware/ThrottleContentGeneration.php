<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Per-user rate limit for the billable content-generation endpoint.
 *
 * The sibling of ThrottleQuestionGeneration, for content_master rather than
 * lms_question_master. Generating every content type for a chapter is five
 * sequential Claude Opus 5 calls with long outputs, so this is a spend control,
 * not a DoS control.
 *
 * Unlike the question-generation endpoint, `lms/gamma-content-master` runs
 * outside `api.session` - it is unauthenticated and the drawer sends no bearer
 * token. So the identity here is read from the request body first (which is all
 * that endpoint ever had) and from a hydrated session when one exists, falling
 * back to the client IP. That is weaker than a JWT-derived id and is knowingly
 * so: it caps accidental runaway spend, it does not stop a determined caller.
 * Closing that gap means authenticating the endpoint, which changes the drawer
 * for every chapter and every provider.
 *
 * Defaults are overridable per environment via config/claude.php.
 */
class ThrottleContentGeneration
{
    public function handle(Request $request, Closure $next, ?string $maxAttempts = null, ?string $decayMinutes = null)
    {
        $max = (int) ($maxAttempts ?? config('claude.rate_limit_attempts', 12));
        $decay = (int) ($decayMinutes ?? config('claude.rate_limit_decay_minutes', 1));

        $userId = session()->get('user_id') ?? $request->input('user_id');
        $tenantId = session()->get('sub_institute_id') ?? $request->input('sub_institute_id');

        $key = $userId
            ? "contentgen:user:{$tenantId}:{$userId}"
            : 'contentgen:ip:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, $max)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'success' => false,
                'status_code' => 0,
                'message' => "Too many content generation requests. Try again in {$seconds} second(s).",
            ], 429)->header('Retry-After', $seconds);
        }

        RateLimiter::hit($key, $decay * 60);

        return $next($request);
    }
}
