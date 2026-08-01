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
