<?php

use App\Http\Controllers\Mcp\InitializeController;
use App\Http\Controllers\Mcp\McpHealthController;
use App\Http\Controllers\Mcp\ToolsCallController;
use App\Http\Controllers\Mcp\ToolsListController;
use App\Http\Middleware\McpAuth;
use App\Http\Middleware\McpContextHydrator;
use App\Http\Middleware\McpRestDeprecation;
use App\Mcp\Servers\LmsMcpServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

$middleware = ['api', McpAuth::class, \App\Http\Middleware\McpRateLimit::class, McpContextHydrator::class];

// The standards-compliant MCP endpoint. Laravel\Mcp owns the JSON-RPC protocol,
// session header and tools/list/tools/call methods; the server publishes the same
// registry instances the lifecycle calls rather than duplicating the business tools.
//
// Registered inside a group rather than with ->middleware() on the return value.
// Mcp::web() registers three routes — GET and DELETE returning the protocol's 405
// "use POST" stubs, plus the POST that runs the server — but returns only the POST
// route, so chaining ->middleware() onto it left the other two verbs with no auth,
// no rate limit and no tenant hydration. A group covers all three.
Route::middleware($middleware)->group(function () {
    Mcp::web('/' . trim(config('mcp.route_prefix', 'api/mcp'), '/'), LmsMcpServer::class);
});

// Compatibility shim for the existing frontend adapters.
//
// It is not a second API: every route below delegates to the same App\Mcp\ToolRegistry
// the JSON-RPC server publishes, so the two surfaces cannot disagree about which tools
// exist or what they return. It stays so the frontend is not blocked on the JSON-RPC
// migration, and McpRestDeprecation announces its retirement on every response.
Route::prefix(config('mcp.route_prefix', 'api/mcp'))
    ->middleware(['api'])
    ->group(function () use ($middleware) {
        Route::middleware(array_merge(
            array_values(array_filter($middleware, static fn ($entry) => $entry !== 'api')),
            [McpRestDeprecation::class]
        ))->group(function () {
            Route::get('/health', McpHealthController::class);
            Route::post('/initialize', InitializeController::class);
            Route::get('/tools', ToolsListController::class);
            Route::post('/tools/call', ToolsCallController::class);
        });
    });
