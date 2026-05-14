<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Blade;
use App\View\Components\filters;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Blade::component('filters', filters::class);
        Paginator::useBootstrap();

        // if (env('APP_ENV') !== 'local') {
        //     URL::forceScheme('https');
        // }
        if (app()->environment('production')) {
            \URL::forceScheme('https');
        }


        Schema::defaultStringLength(191);
        
        DB::listen(function ($query) {
        $dangerous = ['DROP TABLE', 'TRUNCATE TABLE'];
        foreach ($dangerous as $cmd) {
            if (stripos($query->sql, $cmd) !== false) {
                    Log::critical("DANGEROUS SQL BLOCKED", [
                        'sql' => $query->sql,
                        'user' => auth()->user()?->email ?? 'system',
                        'time' => now()
                    ]);
                    throw new \Exception("Dangerous command blocked: $cmd");
                }
            }
        });
    }
}
