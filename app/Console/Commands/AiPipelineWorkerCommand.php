<?php

namespace App\Console\Commands;

use App\Services\AI\Attendance\AttendanceAgentPipelineService;
use App\Services\AI\Fees\FeesAgentPipelineService as GovernedFeesPipeline;
use App\Services\AI\Pipeline\BackgroundTick;
use App\Services\AI\Users\UsersAgentPipelineService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * The loop behind "press Start once".
 *
 * Every few seconds it runs whatever Users, Attendance and Fees automation is due, one sequential
 * cycle at a time, as the person who pressed Start. It retires after a few idle rounds (everything
 * stopped), so Stop really ends it, and it hands over to a fresh process when it has run for a while.
 */
class AiPipelineWorkerCommand extends Command
{
    protected $signature = 'ai:pipeline-worker {--sleep=10 : Seconds between rounds} {--max-minutes=30 : Restart itself after this long}';

    protected $description = 'Run the active Users / Attendance / Fees agent automations until they are stopped';

    private const IDLE_ROUNDS_BEFORE_EXIT = 3;

    public function handle(): int
    {
        @set_time_limit(0);
        $pid = (string) getmypid();

        // One worker at a time.
        if (! Cache::add(BackgroundTick::HEARTBEAT_KEY, $pid, BackgroundTick::HEARTBEAT_TTL)) {
            return self::SUCCESS;
        }

        $sleep = max(2, (int) $this->option('sleep'));
        $deadline = time() + max(1, (int) $this->option('max-minutes')) * 60;
        $idle = 0;
        $handOver = false;

        try {
            while (true) {
                Cache::put(BackgroundTick::HEARTBEAT_KEY, $pid, BackgroundTick::HEARTBEAT_TTL);

                $active = 0;
                try {
                    $active += app(UsersAgentPipelineService::class)->tickActive(BackgroundTick::GRACE_SECONDS);
                    $active += app(AttendanceAgentPipelineService::class)->tickActive(BackgroundTick::GRACE_SECONDS);

                    app(GovernedFeesPipeline::class)->tickActiveAgents();
                    if (Schema::hasTable('fees_agent_state')) {
                        $active += (int) DB::table('fees_agent_state')
                            ->where('agent_key', GovernedFeesPipeline::STATE_AGENT_KEY)
                            ->where('is_active', true)
                            ->count();
                    }
                } catch (Throwable $e) {
                    Log::warning('ai:pipeline-worker round failed: ' . $e->getMessage());
                }

                $idle = $active > 0 ? 0 : $idle + 1;
                if ($idle >= self::IDLE_ROUNDS_BEFORE_EXIT) {
                    break;
                }
                if (time() >= $deadline) {
                    $handOver = true;
                    break;
                }

                sleep($sleep);
            }
        } finally {
            if ((string) Cache::get(BackgroundTick::HEARTBEAT_KEY) === $pid) {
                Cache::forget(BackgroundTick::HEARTBEAT_KEY);
            }
        }

        // Still active but running long: a fresh process takes over, so a stopped-by-age worker
        // never leaves a running automation unattended.
        if ($handOver) {
            Cache::forget('pipeline_worker:launch');
            BackgroundTick::ensureWorker();
        }

        return self::SUCCESS;
    }
}
