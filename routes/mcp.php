<?php

use App\Http\Controllers\Mcp\InitializeController;
use App\Http\Controllers\Mcp\McpHealthController;
use App\Http\Controllers\Mcp\ToolsCallController;
use App\Http\Controllers\Mcp\ToolsListController;
use App\Http\Middleware\McpAuth;
use App\Http\Middleware\McpContextHydrator;
use Illuminate\Support\Facades\Route;

Route::prefix(config('mcp.route_prefix', 'api/mcp'))
    ->middleware(['api'])
    ->group(function () {
        Route::middleware([McpAuth::class, \App\Http\Middleware\McpRateLimit::class, McpContextHydrator::class])->group(function () {
            Route::get('/health', McpHealthController::class);
            Route::post('/initialize', InitializeController::class);
            Route::get('/tools', ToolsListController::class);
            Route::post('/tools/call', ToolsCallController::class);
        });
    });
