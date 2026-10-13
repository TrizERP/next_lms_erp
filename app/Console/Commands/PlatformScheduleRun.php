<?php

namespace App\Console\Commands;

use App\Services\Platform\SchedulerDispatcher;
use Illuminate\Console\Command;

class PlatformScheduleRun extends Command
{
    protected $signature = 'platform:schedule-run';

    protected $description = 'Run the platform scheduled tasks that are due, and escalate overdue approval steps';

    public function handle(SchedulerDispatcher $dispatcher): int
    {
        $s = $dispatcher->tick();
        $this->info("ran {$s['ran']}, failed {$s['failed']}, no handler {$s['no_handler']}, escalated {$s['escalated']}");

        return self::SUCCESS;
    }
}
