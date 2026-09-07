<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Per-user rate limit for the billable LLM generation endpoints.
 *
 * The global `api` middleware group runs `throttle:1000,1`. For an endpoint that
 * makes up to 17 sequential DeepSeek calls per request, 1000 requests/minute is
 * not a limit - it is an uncapped spend vector. This caps a single teacher to a
 * handful of generation runs per minute.
 *
 * Keyed on the JWT-derived user id rather than the client IP, because
 * `api.session` never calls Auth::login() (it only hydrates the session store),
 * so Laravel's built-in `throttle` middleware would fall back to the IP - and a
 * whole school behind one NAT gateway would share a single bucket. MUST run
 * AFTER `api.session`, which is what puts `user_id` in the session.
 *
 * Defaults are overridable per environment via config/deepseek.php.
 */
class ThrottleQuestionGeneration
{
    public function handle(Request $request, Closure $next, ?string $maxAttempts = null, ?string $decayMinutes = null)
    {
        $max = (int) ($maxAttempts ?? config('deepseek.rate_limit_attempts', 5));
        $decay = (int) ($decayMinutes ?? config('deepseek.rate_limit_decay_minutes', 1));

        $userId = session()->get('user_id');
        $tenantId = session()->get('sub_institute_id');

        // No hydrated identity means this ran outside/before `api.session`. Fall
        // back to the IP rather than sharing one global bucket across callers.
        $key = $userId
            ? "qgen:user:{$tenantId}:{$userId}"
            : 'qgen:ip:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, $max)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'status' => false,
                'message' => "Too many generation requests. Try again in {$seconds} second(s).",
                'data' => [],
            ], 429)->header('Retry-After', $seconds);
        }

        RateLimiter::hit($key, $decay * 60);

        return $next($request);
    }
}
