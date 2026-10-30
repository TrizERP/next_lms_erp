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
            foreach (['SystemRoot', 'Path', 'ComSpec', 'windir', 'TEMP', 'TMP', 'APPDATA', 'LOCALAPPDATA', 'USERPROFILE', 'SystemDrive', 'ALLUSERSPROFILE', 'ProgramData', 'ProgramFiles'] as $variable) {
                if (! in_array($variable, ServeCommand::$passthroughVariables, true)) {
                    ServeCommand::$passthroughVariables[] = $variable;
                }
            }
        }

        // Autoloader fallback for Linux environments where the service directory is lowercase lms
        spl_autoload_register(function ($class) {
            if (str_starts_with($class, 'App\\Services\\Lms\\')) {
                $file = app_path('Services/lms/' . str_replace('\\', '/', substr($class, 17)) . '.php');
                if (file_exists($file)) {
                    require_once $file;
                }
            }
        }, true, true);
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

        // A queue worker reports that it is alive, so a Course Presentation run nobody has picked up can say so.
        $events = $this->app['events'];
        $events->listen(\Illuminate\Queue\Events\Looping::class, fn () => \App\Services\CoursePresentation\WorkerHeartbeat::beat(false));
        $events->listen(\Illuminate\Queue\Events\JobProcessing::class, fn () => \App\Services\CoursePresentation\WorkerHeartbeat::beat(true));
        $events->listen(\Illuminate\Queue\Events\JobProcessed::class, fn () => \App\Services\CoursePresentation\WorkerHeartbeat::beat(false));
        $events->listen(\Illuminate\Queue\Events\JobFailed::class, fn () => \App\Services\CoursePresentation\WorkerHeartbeat::beat(false));

        // The same for the Question Paper worker, but ONLY for its own connection: a `default` worker must not make
        // question papers look healthy. (Looping fires on every poll, including while idle.)
        $questionPaperConnection = \App\Jobs\QuestionPaperExtractionJob::CONNECTION;
        $events->listen(\Illuminate\Queue\Events\Looping::class, function ($e) use ($questionPaperConnection) {
            if ($e->connectionName === $questionPaperConnection) {
                \App\Services\QuestionPapers\QuestionPaperWorkerHeartbeat::beat(false);
            }
        });
        $events->listen(\Illuminate\Queue\Events\JobProcessing::class, function ($e) use ($questionPaperConnection) {
            if ($e->connectionName === $questionPaperConnection) {
                \App\Services\QuestionPapers\QuestionPaperWorkerHeartbeat::beat(true);
            }
        });
        foreach ([\Illuminate\Queue\Events\JobProcessed::class, \Illuminate\Queue\Events\JobFailed::class] as $done) {
            $events->listen($done, function ($e) use ($questionPaperConnection) {
                if ($e->connectionName === $questionPaperConnection) {
                    \App\Services\QuestionPapers\QuestionPaperWorkerHeartbeat::beat(false);
                }
            });
        }

        // Graceful stop of the Question Paper worker (the Stop button's local-launcher driver sets this flag). Between jobs the
        // worker is told to start no further job and to quit once the current one is done. It only ever reacts on its own
        // connection, so a worker for `default` or anything else is never affected.
        $events->listen(\Illuminate\Queue\Events\Looping::class, function ($e) use ($questionPaperConnection) {
            if ($e->connectionName === $questionPaperConnection && \App\Services\QuestionPapers\Worker\WorkerStateStore::stopRequestedForWorker()) {
                app('queue.worker')->shouldQuit = true;

                return false; // "do not run another job now": the worker pauses, sees shouldQuit and exits
            }
        });
        $events->listen(\Illuminate\Queue\Events\JobProcessed::class, function ($e) use ($questionPaperConnection) {
            if ($e->connectionName === $questionPaperConnection && \App\Services\QuestionPapers\Worker\WorkerStateStore::stopRequestedForWorker()) {
                app('queue.worker')->shouldQuit = true;
            }
        });

        $this->app->bind(\App\Services\QuestionPapers\Worker\QuestionPaperWorkerManager::class, fn () => \App\Services\QuestionPapers\Worker\QuestionPaperWorkerManager::fromConfig());

        // On local Windows dev, running `artisan serve` automatically launches the dedicated Question Paper worker if not already running.
        if ($this->app->runningInConsole() && PHP_OS_FAMILY === 'Windows' && app()->environment('local')) {
            $argv = $_SERVER['argv'] ?? [];
            if (in_array('serve', $argv, true) && !in_array('--no-worker', $argv, true)) {
                $launcher = base_path('dev-question-papers.ps1');
                if (is_file($launcher)) {
                    $table = new \App\Services\QuestionPapers\Worker\SystemProcessTable();
                    $hasWorker = false;
                    foreach ($table->php() as $proc) {
                        if (\App\Services\QuestionPapers\Worker\WorkerSignature::matches($proc['command'])) {
                            $hasWorker = true;
                            break;
                        }
                    }
                    if (!$hasWorker) {
                        $php = PHP_BINARY;
                        $cmd = 'powershell.exe -NoProfile -ExecutionPolicy Bypass -File ' . escapeshellarg($launcher) . ' -NoServer -Php ' . escapeshellarg($php);
                        pclose(popen("cmd /c start \"\" /B {$cmd} > NUL 2>&1", 'r'));
                        register_shutdown_function(function () use ($launcher) {
                            $stopCmd = 'powershell.exe -NoProfile -ExecutionPolicy Bypass -File ' . escapeshellarg($launcher) . ' -Stop';
                            pclose(popen("cmd /c start \"\" /B {$stopCmd} > NUL 2>&1", 'r'));
                        });
                    }
                }
            }
        }

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

