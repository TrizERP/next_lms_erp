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

        // MariaDB -> Neo4j outbox. Controllers flush inline so a change is
        // visible immediately; this is the safety net that catches anything the
        // inline flush could not deliver (Neo4j restart, bolt timeout).
        $schedule->command('neo4j:drain')->everyMinute()->withoutOverlapping();

        // The April 2026 failure was not that the consumer broke — it was that
        // nothing noticed for four months. Depth alerting is the actual fix.
        $schedule->call(function () {
            $depth = \App\Services\Graph\GraphDrain::depth();
            if ($depth['nodes'] + $depth['rels'] > 1000) {
                Log::channel('daily')->error('Neo4j sync backlog is growing', $depth);
            }
        })->everyFiveMinutes()->name('neo4j-outbox-depth')->withoutOverlapping();

        // Set Coherence Map — mastery safety net.
        //
        // CoherenceMapController pushes each HAS_MASTERY edge inline the moment
        // an answer is recorded, so a learner sees their own progress move
        // immediately. This sweep exists for the writes that inline push could
        // not deliver: Neo4j restarting, a bolt timeout, or a :StuDetail that
        // had not been backfilled yet. `graph_synced_at IS NULL OR < updated_at`
        // IS the outbox — nothing is lost, it is only ever late.
        //
        // Per-tenant because the projection is tenant-scoped; the query is a
        // single indexed read (pcmast_dirty_idx) and returns nothing on a
        // healthy system.
        $schedule->call(function () {
            $projection = app(\App\Services\Graph\CoherenceGraphProjection::class);

            $owed = \App\Models\PAL\ConceptMastery::query()
                ->whereNull('graph_synced_at')
                ->orWhereColumn('graph_synced_at', '<', 'updated_at')
                ->limit(1000)
                ->get();

            if ($owed->isEmpty()) {
                return;
            }

            $result = $projection->projectMastery($owed);

            // Stamp ONLY what the graph confirmed it wrote. Anything absent
            // stays owed and is retried next pass.
            foreach ($result['written'] as $pair) {
                \App\Models\PAL\ConceptMastery::where('learner_id', $pair['learner'])
                    ->where('concept_ref_id', $pair['concept'])
                    ->update(['graph_synced_at' => now()]);
            }

            if (($result['learners_missing'] ?? 0) > 0) {
                Log::channel('daily')->warning('Coherence mastery rows had no graph endpoint', $result);
            }
        })->everyFiveMinutes()->name('coherence-mastery-sweep')->withoutOverlapping();
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
            if (preg_match('/\b(db:seed|schema|fresh|refresh)\b/i', $cmd)) {
                fwrite(STDERR, "❌ Database schema commands are blocked in production.\n");
                exit(1);
            }
        }
    }
}
