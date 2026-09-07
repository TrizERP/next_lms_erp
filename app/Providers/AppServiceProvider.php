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
use Illuminate\Foundation\Console\ServeCommand;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Windows only: `php artisan serve` spawns the PHP development server through
        // Symfony Process and forwards just the variables listed in
        // ServeCommand::$passthroughVariables. That list is matched with a
        // case-sensitive in_array(), and it spells the entries PATH and SYSTEMROOT.
        // A native Windows shell exports them as Path and SystemRoot, so on PHP builds
        // that populate $_ENV (variables_order=EGPCS, e.g. Herd) neither name matches
        // and both are stripped from the child environment. Without SystemRoot the
        // spawned php.exe cannot initialise Winsock, so every bind attempt fails with
        // "Failed to listen on 127.0.0.1:<port> (reason: ?)" across the whole 8000-8010
        // range even though the ports are free.
        if (PHP_OS_FAMILY === 'Windows' && $this->app->runningInConsole()) {
            foreach (['SystemRoot', 'Path', 'ComSpec', 'windir', 'TEMP', 'TMP', 'APPDATA'] as $variable) {
                if (! in_array($variable, ServeCommand::$passthroughVariables, true)) {
                    ServeCommand::$passthroughVariables[] = $variable;
                }
            }
        }
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
