<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Blade;
use App\View\Components\Filters;
use Illuminate\Support\Facades\Log;
use App\Services\FeesIntelligenceService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Register Fees Intelligence Service as singleton
        $this->app->singleton(FeesIntelligenceService::class, function ($app) {
            return new FeesIntelligenceService();
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Blade::component('filters', Filters::class);
        Paginator::useBootstrap();

        // if (env('APP_ENV') !== 'local') {
        //     URL::forceScheme('https');
        // }
        if (app()->environment('production')) {
            \URL::forceScheme('https');
        }


        Schema::defaultStringLength(191);

        // DB::listen(function ($query) {
        // $dangerous = ['DROP', 'TRUNCATE'];
        // foreach ($dangerous as $cmd) {
        //     if (stripos($query->sql, $cmd) !== false) {
        //             Log::critical("DANGEROUS SQL BLOCKED", [
        //                 'sql' => $query->sql,
        //                 'user' => auth()->user()?->email ?? 'system',
        //                 'time' => now()
        //             ]);
        //             throw new \Exception("Dangerous command blocked: $cmd");
        //         }
        //     }
        // });
        // added on 08-05-2026
        DB::listen(function ($query) {
        $dangerous = ['DROP TABLE', 'TRUNCATE TABLE'];
        foreach ($dangerous as $cmd) {
            if (stripos($query->sql, $cmd) !== false) {
                    Log::critical("DANGEROUS SQL BLOCKED", [
                        'sql'  => $query->sql,
                        'user' => auth()->user()?->email ?? 'system',
                        'time' => now()
                    ]);

                    throw new \Exception("Dangerous SQL command blocked.");
                }
            }
        });
    }
}
