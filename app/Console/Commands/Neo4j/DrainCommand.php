<?php

namespace App\Console\Commands\Neo4j;

use App\Services\Graph\GraphDrain;
use Illuminate\Console\Command;

/**
 * The supervised consumer for the MariaDB -> Neo4j outbox.
 *
 * This is the component whose absence caused the April 2026 failure: the
 * previous consumer stopped at 11:49:55 on 2026-04-02, the producer kept
 * running until 13:50:55, and 8 rows were stranded in each table with nothing
 * watching. Run it on a schedule AND alert on queue depth — the drain existing
 * is not the same as the drain working.
 *
 *     $schedule->command('neo4j:drain')->everyMinute()->withoutOverlapping();
 *
 * Nodes are drained before relationships, because an edge cannot attach to
 * endpoints that do not exist yet.
 */
class DrainCommand extends Command
{
    protected $signature = 'neo4j:drain
                            {--limit=500 : Max rows per table per pass}
                            {--retries=5 : Give up on a node row after this many attempts}
                            {--nodes-only : Drain sync_log only}
                            {--rels-only : Drain neo4j_sync_queue only}
                            {--status : Show queue depth and exit}';

    protected $description = 'Drain the sync_log (nodes) and neo4j_sync_queue (relationships) outbox into Neo4j';

    public function handle(GraphDrain $drain): int
    {
        $depth = GraphDrain::depth();

        if ($this->option('status')) {
            $this->line("sync_log PENDING        : {$depth['nodes']}");
            $this->line("neo4j_sync_queue pending: {$depth['rels']}");

            return self::SUCCESS;
        }

        if (! config('neo4j.sync_enabled')) {
            $this->warn('neo4j.sync_enabled is false — refusing to drain. Set NEO4J_SYNC_ENABLED=true.');

            return self::SUCCESS;
        }

        $limit = (int) $this->option('limit');
        $failed = 0;

        if (! $this->option('rels-only')) {
            $r = $drain->drainNodes($limit, (int) $this->option('retries'));
            $this->info("Nodes:         {$r['ok']} synced, {$r['failed']} failed");
            foreach (array_slice($r['errors'], 0, 10) as $e) {
                $this->error("  {$e}");
            }
            $failed += $r['failed'];
        }

        if (! $this->option('nodes-only')) {
            $r = $drain->drainRelationships($limit);
            $this->info("Relationships: {$r['ok']} synced, {$r['failed']} failed");
            foreach (array_slice($r['errors'], 0, 10) as $e) {
                $this->error("  {$e}");
            }
            $failed += $r['failed'];
        }

        $after = GraphDrain::depth();
        $this->line("Backlog now: {$after['nodes']} node(s), {$after['rels']} relationship(s)");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
