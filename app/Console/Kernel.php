<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('check:broken-links')->everyMinute();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
        
    }

    public function bootstrap()
    {
        parent::bootstrap();

        if (app()->runningInConsole()) {
            $argv = $_SERVER['argv'] ?? [];

            Log::channel('daily')->warning('Artisan command executed', [
                'command' => implode(' ', $argv),
                'user' => get_current_user(),
                'ip' => gethostbyname(gethostname()),
            ]);
            
            $cmd  = implode(' ', $argv);
            if (preg_match('/\b(migrate|db:seed|schema|fresh|refresh)\b/i', $cmd)) {
                fwrite(STDERR, "❌ Database schema commands are blocked in production.\n");
                exit(1);
            }
        }
    }
}
