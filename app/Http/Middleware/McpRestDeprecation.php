<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

/**
 * Marks every response from the /api/mcp/* REST façade as deprecated.
 *
 * The façade is kept deliberately — it delegates to the same App\Mcp\ToolRegistry the
 * official JSON-RPC endpoint publishes, so the frontend is not blocked on the migration
 * and the two surfaces cannot drift. What it must not do is stay quietly: a shim nobody
 * is reminded about is a shim nobody retires.
 *
 * So it says so on the wire, on every response including errors, in the headers a client
 * or gateway can actually act on:
 *
 *   - `Deprecation` (RFC 9745) — this endpoint is deprecated.
 *   - `Sunset` (RFC 8594) — the date after which it is expected to stop answering.
 *   - `Link` — where to go instead, and where the protocol is documented.
 *   - `Warning` — the human-readable sentence, for anyone reading a response by eye.
 *
 * The official endpoint is left unmarked. Only the shim is deprecated.
 */
class McpRestDeprecation
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // `headers` is a property on Symfony's Response, not a method — checking for it
        // with method_exists() silently disabled this middleware entirely.
        if (! $response instanceof SymfonyResponse) {
            return $response;
        }

        $successor = (string) config('mcp.rest_shim.successor', '/api/mcp');
        $sunset = $this->sunset();

        $response->headers->set('Deprecation', 'true');
        $response->headers->set('Link', sprintf(
            '<%s>; rel="successor-version", <%s>; rel="deprecation"',
            $successor,
            (string) config('mcp.rest_shim.docs', 'https://modelcontextprotocol.io/specification')
        ));

        $response->headers->set('Warning', sprintf(
            '299 - "The %s REST endpoints are deprecated. Use POST %s with the MCP JSON-RPC protocol%s."',
            '/' . trim((string) config('mcp.route_prefix', 'api/mcp'), '/') . '/*',
            $successor,
            $sunset !== null ? '. Removal: ' . $sunset->toDateString() : ''
        ));

        if ($sunset !== null) {
            // RFC 8594 requires an HTTP-date, not an ISO one.
            $response->headers->set('Sunset', $sunset->toRfc7231String());
        }

        return $response;
    }

    /**
     * The advertised removal date, or null if none is configured.
     *
     * An unparseable value is treated as "no date announced" rather than allowed to
     * throw: a misconfigured retirement date must not take the endpoint down with it.
     */
    private function sunset(): ?Carbon
    {
        $configured = config('mcp.rest_shim.sunset');

        if (! is_string($configured) || trim($configured) === '') {
            return null;
        }

        try {
            return Carbon::parse($configured)->endOfDay();
        } catch (Throwable) {
            return null;
        }
    }
}
