<?php

namespace App\Console\Commands;

use App\Services\AI\Attendance\AttendanceAgentPipelineService;
use App\Services\AI\Users\UsersAgentPipelineService;
use Illuminate\Console\Command;

/**
 * Runs every Users / Attendance automation whose next cycle is due.
 *
 * The scheduler calls the same thing once a minute, but a host with no cron entry never does.
 * The state endpoint therefore starts this command, detached, when an active automation is
 * overdue and no open screen has taken the cycle — so "Start" keeps working without a browser
 * tab and without `schedule:run`. A cycle lease makes a second runner harmless.
 */
class AiModulePipelineTickCommand extends Command
{
    protected $signature = 'ai:pipeline-tick';

    protected $description = 'Run the due Users / Attendance agent automation cycles';

    public function handle(): int
    {
        app(UsersAgentPipelineService::class)->tickActive();
        app(AttendanceAgentPipelineService::class)->tickActive();
        // Fees keeps its own loop state in fees_agent_state and applies its own due-time check.
        app(\App\Services\AI\Fees\FeesAgentPipelineService::class)->tickActiveAgents();

        return self::SUCCESS;
    }
}
