<?php

return [
    'route_prefix' => env('MCP_ROUTE_PREFIX', 'api/mcp'),

    'server' => [
        'name' => env('MCP_SERVER_NAME', env('APP_NAME', 'Laravel') . ' MCP Server'),
        'version' => env('MCP_SERVER_VERSION', '1.0.0'),
        'protocol_version' => env('MCP_PROTOCOL_VERSION', '2025-06-18'),
    ],

    'rate_limit' => [
        'per_minute' => (int) env('MCP_RATE_LIMIT_PER_MINUTE', 60),
    ],

    'confirmation' => [
        'ttl_minutes' => (int) env('MCP_CONFIRMATION_TTL_MINUTES', 10),
    ],
];
