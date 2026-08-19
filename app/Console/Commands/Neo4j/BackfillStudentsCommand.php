<?php

namespace App\Console\Commands\Neo4j;

use App\Services\Graph\GraphSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Push students that already exist in MariaDB into the graph.
 *
 * The live sync only catches students created or edited AFTER it was wired in.
 * Everyone already in `tblstudent` — 83,715 rows against 5,414 `:Student` nodes
 * when this was written — needs one deliberate pass. This is that pass.
 *
 * It reuses the exact same projection the controllers use, so a backfilled
 * student is byte-identical to a live-synced one; there is no second code path
 * that could drift.
 *
 *   php artisan neo4j:backfill-students --id=281468 --id=281469
 *   php artisan neo4j:backfill-students --tenant=1 --limit=500
 *   php artisan neo4j:backfill-students --since=2026-08-01
 *   php artisan neo4j:backfill-students --tenant=1 --dry-run
 *
 * Safe to re-run: every write is a MERGE on the label's unique key.
 */
class BackfillStudentsCommand extends Command
{
    protected $signature = 'neo4j:backfill-students
                            {--id=* : Specific tblstudent.id values}
                            {--tenant= : Restrict to one sub_institute_id}
                            {--since= : Only students whose enrollment was created on/after this date}
                            {--limit=200 : Max students per run}
                            {--chunk=25 : Students per flush batch}
                            {--dry-run : List what would sync, write nothing}';

    protected $description = 'Backfill existing MariaDB students into Neo4j through the normal outbox path';

    public function handle(GraphSync $sync): int
    {
        if (! config('neo4j.sync_enabled')) {
            $this->warn('neo4j.sync_enabled is false. Set NEO4J_SYNC_ENABLED=true first.');

            return self::FAILURE;
        }

        $students = $this->resolve();

        if ($students->isEmpty()) {
            $this->info('Nothing to backfill.');

            return self::SUCCESS;
        }

        $this->info("{$students->count()} student(s) selected.");

        if ($this->option('dry-run')) {
            foreach ($students as $s) {
                $this->line("  would sync tblstudent#{$s->id} ({$s->first_name}) tenant {$s->sub_institute_id}");
            }

            return self::SUCCESS;
        }

        $ok = 0; $failed = 0;
        $bar = $this->output->createProgressBar($students->count());
        $bar->start();

        // Flush in batches rather than per student: one bolt round-trip for 25
        // students instead of 25, without letting a failure take down the run.
        foreach ($students->chunk((int) $this->option('chunk')) as $chunk) {
            $ids = ['log' => [], 'queue' => []];

            foreach ($chunk as $s) {
                try {
                    // Same transactional-outbox path the controllers use.
                    $part = DB::transaction(
                        fn () => $sync->enqueueStudent((int) $s->id, (int) $s->sub_institute_id)
                    );
                    $ids['log'] = array_merge($ids['log'], $part['log']);
                    $ids['queue'] = array_merge($ids['queue'], $part['queue']);
                    $ok++;
                } catch (Throwable $e) {
                    $this->newLine();
                    $this->error("  tblstudent#{$s->id}: {$e->getMessage()}");
                    $failed++;
                }
                $bar->advance();
            }

            $sync->flush($ids);
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Enqueued {$ok} student(s); {$failed} skipped.");

        $depth = \App\Services\Graph\GraphDrain::depth();
        if ($depth['nodes'] + $depth['rels'] > 0) {
            $this->warn("Outbox still holds {$depth['nodes']} node(s) / {$depth['rels']} relationship(s) — run `php artisan neo4j:drain`.");
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Only students that actually have an enrollment: a `tblstudent` row with
     * no `tblstudent_enrollment` row produces a lone :StuDetail with nothing to
     * attach to, which is the orphan shape the rebuild exists to avoid.
     */
    private function resolve()
    {
        $q = DB::table('tblstudent as t')
            ->join('tblstudent_enrollment as e', function ($j) {
                $j->on('e.student_id', '=', 't.id')
                  ->on('e.sub_institute_id', '=', 't.sub_institute_id');
            })
            ->select('t.id', 't.first_name', 't.sub_institute_id')
            ->distinct();

        if ($ids = array_filter((array) $this->option('id'))) {
            $q->whereIn('t.id', $ids);
        }

        if ($tenant = $this->option('tenant')) {
            $q->where('t.sub_institute_id', (int) $tenant);
        }

        if ($since = $this->option('since')) {
            $q->where('e.created_on', '>=', $since);
        }

        return $q->orderByDesc('t.id')->limit((int) $this->option('limit'))->get();
    }
}
