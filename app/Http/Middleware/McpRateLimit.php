<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;

class McpRateLimit
{
    public function __construct(private readonly RateLimiter $rateLimiter)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $key = $this->resolveKey($request);
        $maxAttempts = (int) config('mcp.rate_limit.per_minute', 60);

        if ($this->rateLimiter->tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = $this->rateLimiter->availableIn($key);

            return response()->json([
                'success' => false,
                'message' => 'Too many requests.',
                'data' => null,
                'errors' => null,
            ], 429, [
                'Retry-After' => (string) $retryAfter,
            ]);
        }

        // One busy or abusive account must not be able to use up the whole school's AI budget, and one
        // school must not drain a platform key shared by many: a school-wide per-minute and per-day cap.
        $schoolId = (string) ($request->attributes->get('mcp_auth')['sub_institute_id'] ?? '');
        if ($schoolId !== '') {
            $schoolMinute = 'mcp:school:' . $schoolId . ':m';
            $schoolDay = 'mcp:school:' . $schoolId . ':d';
            if ($this->rateLimiter->tooManyAttempts($schoolMinute, (int) config('mcp.rate_limit.school_per_minute', 600))
                || $this->rateLimiter->tooManyAttempts($schoolDay, (int) config('mcp.rate_limit.school_per_day', 20000))) {
                return response()->json(['success' => false, 'message' => 'This school has reached its AI usage limit. Try again later.', 'data' => null, 'errors' => null], 429);
            }
            $this->rateLimiter->hit($schoolMinute, 60);
            $this->rateLimiter->hit($schoolDay, 86400);
        }

        $this->rateLimiter->hit($key, 60);

        $response = $next($request);
        $response->headers->set('X-RateLimit-Limit', (string) $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', (string) max(0, $maxAttempts - $this->rateLimiter->attempts($key)));

        return $response;
    }

    private function resolveKey(Request $request): string
    {
        $auth = $request->attributes->get('mcp_auth', []);
        $userId = $auth['user_id'] ?? null;

        return 'mcp:' . ($userId ?: $request->ip());
    }
}
