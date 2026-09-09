<?php

use Laravel\Mcp\Enums\ProtocolVersion;

/*
 * The MCP protocol versions this deployment offers, most preferred first.
 *
 * Derived from laravel/mcp rather than written out by hand. The previous hard-coded
 * '2025-06-18' was not what the server actually spoke: the package supports four
 * versions and negotiates down from 2025-11-25, so the REST shim's /health and
 * /initialize were advertising a version the real endpoint had already moved past, and
 * nothing on the official path read the setting at all.
 *
 * MCP_PROTOCOL_VERSIONS (comma-separated) can only *narrow* this set — a value the
 * package cannot speak is dropped rather than advertised, because a protocol version is
 * a promise about wire behaviour and config must not be able to make one the code
 * cannot keep. Narrow it only to hold older clients on an older shape deliberately.
 */
$configuredProtocolVersions = array_values(array_filter(
    array_map('trim', explode(',', (string) env('MCP_PROTOCOL_VERSIONS', ''))),
    static fn (string $version): bool => $version !== '' && in_array($version, ProtocolVersion::supported(), true)
));

$protocolVersions = $configuredProtocolVersions !== []
    ? $configuredProtocolVersions
    : ProtocolVersion::supported();

return [
    'route_prefix' => env('MCP_ROUTE_PREFIX', 'api/mcp'),

    'server' => [
        'name' => env('MCP_SERVER_NAME', env('APP_NAME', 'Laravel') . ' MCP Server'),
        'version' => env('MCP_SERVER_VERSION', '1.0.0'),

        // Every version offered, and the one negotiated when a client states no
        // preference. Both are read from the same list, so the REST shim cannot drift
        // from what the JSON-RPC endpoint answers.
        'protocol_versions' => $protocolVersions,
        'protocol_version' => $protocolVersions[0],
    ],

    'rate_limit' => [
        'per_minute' => (int) env('MCP_RATE_LIMIT_PER_MINUTE', 60),
    ],

    /*
     * The REST façade under /api/mcp/* — health, initialize, tools, tools/call.
     *
     * It is a compatibility shim, not a second API: it delegates to the same
     * App\Mcp\ToolRegistry the official JSON-RPC endpoint publishes, so the two can
     * never disagree about which tools exist or what they return. It stays so the
     * frontend is not blocked on the JSON-RPC migration, and it announces its own
     * retirement on every response via Deprecation, Sunset and Link headers.
     *
     * `sunset` is the advertised removal date and is deliberately a configuration
     * value: moving it is a product decision, not a code change. Set MCP_REST_SUNSET
     * once the frontend cutover date is agreed.
     */
    'rest_shim' => [
        'sunset' => env('MCP_REST_SUNSET', '2027-03-01'),
        'successor' => env('MCP_REST_SUCCESSOR', '/api/mcp'),
        'docs' => env('MCP_REST_DOCS', 'https://modelcontextprotocol.io/specification'),
    ],

    'confirmation' => [
        'ttl_minutes' => (int) env('MCP_CONFIRMATION_TTL_MINUTES', 10),
    ],
];
