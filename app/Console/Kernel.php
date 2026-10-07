<?php

namespace App\Console;

use App\Services\PAL\Coherence\ConceptTagger;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\DB;
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
        // inline flush could not deliver (Neo4j restart, bolt timeout) and, more
        // importantly, everything written by the dozen code paths that have no
        // controller flush at all — imports, admissions, bulk edits, raw SQL.
        // Their events reach `sync_log` through the database triggers and are
        // delivered here.
        //
        // THIS ONLY RUNS IF `schedule:run` RUNS. There was no cron entry on the
        // dev host until 2026-08-21, which is why no row was ever retried; see
        // `neo4j:drain --watch` for hosts without one.
        //
        // The 5 is not decoration. `withoutOverlapping()` defaults to a 1440
        // MINUTE (24 hour) mutex, and the lock is only released when the command
        // finishes cleanly. A drain killed mid-run — the box sleeping, the
        // console closing, a deploy — therefore disables the entire sync for a
        // day, silently: schedule:run keeps succeeding, the command never
        // executes, and rows sit PENDING with retry_count 0 so nothing even
        // looks like it failed. That happened on 2026-08-21: a run killed at
        // 10:48 held the mutex until 10:48 the next day. An expiry no longer
        // than the interval bounds the damage to one skipped pass.
        $schedule->command('neo4j:drain')->everyMinute()->withoutOverlapping(5);

        // Drift detection. A pipeline reporting all-SUCCESS proves only that
        // the events it was given were delivered — not that every row produced
        // one. This is what catches a row that never emitted an event at all.
        $schedule->command('neo4j:reconcile --limit=2000 --fix')
            ->dailyAt('02:30')
            ->withoutOverlapping(120)
            ->onFailure(fn () => Log::channel('daily')->error('Neo4j reconcile found drift it could not repair'));

        // The April 2026 failure was not that the consumer broke — it was that
        // nothing noticed for four months. Depth alerting is the actual fix.
        $schedule->call(function () {
            $depth = \App\Services\Graph\GraphDrain::depth();

            // Depth alone missed the 2026-08-21 stall: only ~20 rows were
            // queued, far under any sane threshold, while the drain had been
            // dead for 45 minutes. AGE is the signal that catches a stopped
            // consumer; depth only catches one that is too slow.
            $oldest = \Illuminate\Support\Facades\DB::table('sync_log')
                ->where('status', 'PENDING')
                ->min('created_at');

            $stalledFor = $oldest === null ? 0 : now()->diffInMinutes($oldest);

            if ($depth['nodes'] + $depth['rels'] > 1000 || $stalledFor > 15) {
                Log::channel('daily')->error('Neo4j sync is not draining', $depth + [
                    'oldest_pending'  => $oldest,
                    'stalled_minutes' => $stalledFor,
                ]);
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

        // Set Coherence Map — full nightly projection.
        //
        // pal:coherence-sync (concepts, REQUIRES/CROSS_LINKS, TEACHES, ASSESSES)
        // has no scheduled entry at all — it only ever runs when someone
        // remembers to type it by hand, so "the PAL Knowledge Graph" is only
        // ever as fresh as the last manual run. This closure is that missing
        // schedule: every scope with at least one lms_concept row gets a full
        // re-projection once a day. Cheap to run unconditionally because every
        // write in CoherenceGraphProjection is an idempotent MERGE (plus, as of
        // 2026-09-28, a scoped retraction of disqualified edges) — a clean
        // scope costs one read pass and writes nothing new.
        $schedule->call(function () {
            $projection = app(\App\Services\Graph\CoherenceGraphProjection::class);

            $scopes = \Illuminate\Support\Facades\DB::table('lms_concept')
                ->select('sub_institute_id', 'standard_id', 'subject_id')
                ->distinct()
                ->get();

            foreach ($scopes as $scope) {
                $tenant = (int) $scope->sub_institute_id;
                $standard = (int) $scope->standard_id;
                $subject = (int) $scope->subject_id;

                if ($tenant <= 0 || $standard <= 0 || $subject <= 0) {
                    continue;
                }

                try {
                    $projection->projectConcepts($tenant, $standard, $subject);
                    $projection->projectRelations($tenant, $standard, $subject);
                    $projection->projectTeaches($tenant, $standard, $subject);
                    $projection->projectAssesses($tenant, $standard, $subject);
                    $projection->projectLearningRelations($tenant, $standard, $subject);
                    $projection->projectMisconceptions($tenant, $standard, $subject);
                    $projection->projectConceptNodes($tenant, $standard, $subject);
                    $projection->projectNodeMastery($tenant, $standard, $subject);
                    $projection->projectLearningOutcomes($tenant, $standard, $subject);
                } catch (\Throwable $e) {
                    Log::channel('daily')->error('Coherence map nightly sync failed for scope', [
                        'tenant' => $tenant, 'standard' => $standard, 'subject' => $subject,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        })->dailyAt('03:00')->name('coherence-map-nightly-sync')->withoutOverlapping(120);

        // Phase 6.6 — auto-tag uploaded curriculum content and questions
        // against the concept graph.
        //
        // pal:coherence-tag (ConceptTagger) already does this correctly, but
        // takes an explicit --tenant/--standard/--subject triple and had no
        // scheduled entry — like coherence-map-nightly-sync above, it only
        // ever ran when someone typed it by hand. This enumerates the same
        // scopes coherence-map-nightly-sync does (any tenant/standard/subject
        // with at least one lms_concept row) and proposes links for both
        // estates. Heuristic only, by design (Phase 6.6 decision) — no AI
        // spend, no non-determinism, running nightly across every tenant.
        // Every write is a draft proposal (C5) and skips any row a human has
        // already reviewed, so a run that finds nothing new to do is a no-op.
        $schedule->call(function () {
            $tagger = app(ConceptTagger::class);

            $scopes = DB::table('lms_concept')
                ->select('sub_institute_id', 'standard_id', 'subject_id')
                ->distinct()
                ->get();

            foreach ($scopes as $scope) {
                $tenant = (int) $scope->sub_institute_id;
                $standard = (int) $scope->standard_id;
                $subject = (int) $scope->subject_id;

                if ($tenant <= 0 || $standard <= 0 || $subject <= 0) {
                    continue;
                }

                try {
                    $tagger->tagContent($tenant, $standard, $subject);
                    $tagger->tagQuestions($tenant, $standard, $subject);
                } catch (\Throwable $e) {
                    Log::channel('daily')->error('Coherence content tagging failed for scope', [
                        'tenant' => $tenant, 'standard' => $standard, 'subject' => $subject,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        })->dailyAt('02:00')->name('coherence-content-tagging')->withoutOverlapping(120);

        // Phase 6.6 — heuristic Bloom/difficulty/pedagogy/cultural tagging
        // over the untagged content and question estate. --engine=ai is
        // deliberately not scheduled (Phase 6.6 decision): heuristic only,
        // no recurring AI spend. Each run only touches rows with no existing
        // proposal yet (TagContentCommand's default), so this is a catch-up
        // sweep for newly uploaded content, not a full re-tag.
        $schedule->command('pal:tag-content --estate=content --engine=heuristic')
            ->dailyAt('01:30')->withoutOverlapping(120)
            ->onFailure(fn () => Log::channel('daily')->error('pal:tag-content --estate=content failed'));

        $schedule->command('pal:tag-content --estate=questions --engine=heuristic')
            ->dailyAt('01:45')->withoutOverlapping(120)
            ->onFailure(fn () => Log::channel('daily')->error('pal:tag-content --estate=questions failed'));
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
