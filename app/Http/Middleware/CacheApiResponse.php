<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Short-lived cache for JSON API responses, in two modes.
 *
 * 1. `api.cache:auto` -- in the `api` middleware group, so it covers every API route.
 *    - Caches only GET requests that carry a bearer token (the token is what identifies the
 *      caller at this point in the stack; no token, no caching).
 *    - Any successful non-GET API request bumps a per-school version that is part of every key,
 *      so a user who saves something never reads a stale list afterwards.
 *    - Skips paths that are auth, export/download, AI, streaming or otherwise unsafe to cache
 *      (see $skipFragments). When in doubt a path is listed there: skipping is always safe.
 *
 * 2. `api.cache:60` -- explicit, on read-only POST endpoints (dashboard summaries). Put it LAST in
 *    the route's middleware list so auth, session hydration and permission checks still run on
 *    every request, cache hit or not.
 *
 * Only HTTP 200 JSON bodies are stored. A client can force fresh data with a
 * `Cache-Control: no-cache` request header. Writes made outside the API (Blade pages, console,
 * other apps) do not bump the version, so staleness there is bounded by the TTL.
 */
class CacheApiResponse
{
    private const AUTO_TTL = 30;
    private const MAX_BODY_BYTES = 1048576;
    private const MAX_AUTO_BODY_BYTES = 262144; // file cache entries are not garbage collected; keep them small

    /** Path fragments that are never cached in auto mode. */
    private array $skipFragments = [
        'login', 'logout', 'otp', 'token', 'refresh', 'password', 'register', 'verify', 'auth',
        'export', 'download', 'print', 'pdf', 'upload', 'stream', 'poll', 'heartbeat', 'notification',
        'webhook', 'callback', 'cron', 'sync', 'generate', 'chat', 'mcp', 'brain', 'alumni-portal',
        'payment', 'pay', 'receipt', 'handoff', 'bootstrap',
    ];

    public function handle(Request $request, Closure $next, $ttl = 60)
    {
        $auto = ($ttl === 'auto');
        $method = $request->getMethod();

        if ($auto && $method !== 'GET') {
            return $this->invalidateAfterWrite($request, $next);
        }
        if (! in_array($method, ['GET', 'POST'], true)) {
            return $next($request);
        }
        if ($auto && ! $this->autoCacheable($request)) {
            return $next($request);
        }

        $seconds = $auto ? self::AUTO_TTL : max(1, (int) $ttl);
        $key = $this->key($request);
        $bypass = stripos((string) $request->header('Cache-Control'), 'no-cache') !== false;

        if (! $bypass) {
            $hit = Cache::get($key);
            if (is_array($hit) && isset($hit['body'])) {
                return response($hit['body'], 200, ['Content-Type' => 'application/json', 'X-Api-Cache' => 'HIT']);
            }
        }

        $response = $next($request);

        if ($this->cacheable($response, $auto ? self::MAX_AUTO_BODY_BYTES : self::MAX_BODY_BYTES)) {
            Cache::put($key, ['body' => $response->getContent()], $seconds);
            $response->headers->set('X-Api-Cache', 'MISS');
        }

        return $response;
    }

    /** Runs a write request, then invalidates cached reads for the school(s) it touched. */
    private function invalidateAfterWrite(Request $request, Closure $next)
    {
        if ($this->isExplicitReadRoute($request)) {
            return $next($request);
        }
        $tenants = $this->writeCounters($request);
        $response = $next($request);

        if (method_exists($response, 'getStatusCode') && $response->getStatusCode() < 400) {
            $stamp = (string) microtime(true);
            foreach ($tenants as $tenant) {
                Cache::forever('api_ver:'.$tenant, $stamp);
            }
        }

        return $response;
    }

    private function autoCacheable(Request $request): bool
    {
        if (! $request->bearerToken()) {
            return false;
        }
        $path = strtolower($request->path());
        foreach ($this->skipFragments as $fragment) {
            if (strpos($path, $fragment) !== false) {
                return false;
            }
        }

        return true;
    }

    private function cacheable($response, int $maxBytes): bool
    {
        if (! method_exists($response, 'getStatusCode') || $response->getStatusCode() !== 200) {
            return false;
        }
        if (stripos((string) $response->headers->get('Content-Type'), 'json') === false) {
            return false;
        }
        $body = $response->getContent();
        if (! is_string($body) || $body === '' || strlen($body) > $maxBytes) {
            return false;
        }

        // Never pin an error that was returned with HTTP 200. A false negative here (the text
        // appearing inside real data) only means the response is not cached.
        return strpos($body, '"success":false') === false && strpos($body, '"status":0') === false;
    }

    private function key(Request $request): string
    {
        $input = $request->all();
        $this->sortRecursive($input);

        $identity = [
            $request->bearerToken() ? sha1($request->bearerToken()) : null,
            $this->sessionValue('user_id'),
            $this->sessionValue('sub_institute_id'),
            $this->sessionValue('user_profile_id'),
            $this->sessionValue('syear'),
            $this->sessionValue('term_id'),
        ];

        $versions = [];
        foreach (array_merge($this->tenants($request), ['*']) as $tenant) {
            $versions[$tenant] = Cache::get('api_ver:'.$tenant);
        }

        return 'api_resp:'.sha1(json_encode([$request->getMethod(), $request->path(), $identity, $versions, $input]));
    }

    /**
     * School ids this request belongs to: the one inside the (unverified) token payload plus any
     * the request names. Unverified is fine here -- it only picks which version counter to read or
     * bump, and the key also holds the full token hash, so a forged token matches no cache entry.
     */
    private function tenants(Request $request): array
    {
        $tenants = [];
        $token = (string) $request->bearerToken();
        $parts = explode('.', $token);
        if (count($parts) === 3) {
            $payload = json_decode((string) base64_decode(strtr($parts[1], '-_', '+/')), true);
            if (is_array($payload) && isset($payload['sub_institute_id']) && is_scalar($payload['sub_institute_id'])) {
                $tenants[] = (string) $payload['sub_institute_id'];
            }
        }
        $named = $request->input('sub_institute_id');
        if (is_scalar($named) && $named !== '') {
            $tenants[] = (string) $named;
        }

        return array_values(array_unique($tenants));
    }

    /** Counters a write bumps: its schools, or the global one when it cannot be attributed to any. */
    private function writeCounters(Request $request): array
    {
        return $this->tenants($request) ?: ['*'];
    }

    /** True for read-only POST routes that opted into the explicit cache (they must not invalidate). */
    private function isExplicitReadRoute(Request $request): bool
    {
        $route = $request->route();
        if (! $route) {
            return false;
        }
        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && strpos($middleware, 'api.cache:') === 0 && $middleware !== 'api.cache:auto') {
                return true;
            }
        }

        return false;
    }

    private function sessionValue(string $name)
    {
        try {
            return session()->get($name);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function sortRecursive(array &$array): void
    {
        ksort($array);
        foreach ($array as &$value) {
            if (is_array($value)) {
                $this->sortRecursive($value);
            }
        }
    }
}
