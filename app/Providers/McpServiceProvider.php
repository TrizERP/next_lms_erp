<?php

namespace App\Providers;

use App\Mcp\ToolRegistry;
use App\Mcp\Tools\AdmissionsTodayTool;
use App\Mcp\Tools\FeesCollectionReportTool;
use App\Mcp\Tools\StudentSearchTool;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class McpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(config_path('mcp.php'), 'mcp');

        $this->app->singleton(StudentSearchTool::class);
        $this->app->singleton(FeesCollectionReportTool::class);
        $this->app->singleton(AdmissionsTodayTool::class);

        $this->app->tag([
            StudentSearchTool::class,
            FeesCollectionReportTool::class,
            AdmissionsTodayTool::class,
        ], 'mcp.tools');

        $this->app->singleton(ToolRegistry::class, function ($app) {
            return new ToolRegistry(
                $app->tagged('mcp.tools'),
                $app->make(\App\Services\Mcp\McpConfirmationService::class)
            );
        });
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(base_path('routes/mcp.php'));

        RateLimiter::for('mcp', function (Request $request) {
            $limit = (int) config('mcp.rate_limit.per_minute', 60);
            $auth = $request->attributes->get('mcp_auth', []);
            $userId = $auth['user_id'] ?? null;

            return Limit::perMinute($limit)->by($userId ?: $request->ip());
        });
    }
}
