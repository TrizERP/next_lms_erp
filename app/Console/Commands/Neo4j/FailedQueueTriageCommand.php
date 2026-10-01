<?php

namespace App\Console\Commands\Neo4j;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Report on (and optionally requeue) `neo4j_sync_queue` rows that exhausted
 * their retries and were never revisited.
 *
 * Found during the 2026-09-28 sync-architecture review: 16,566 of 173,377
 * `neo4j_sync_queue` rows (9.6%) sit at `status = 'failed'` with nothing
 * left `pending`/`processing` — this is a silent backlog neither
 * `neo4j:drain` nor `neo4j:reconcile` surfaces anywhere today. This command
 * is the first place it becomes visible.
 *
 * Report-only by default. `--requeue` resets a bounded batch back to
 * `pending` with `retry_count` cleared, so the NEXT `neo4j:drain` run picks
 * them up through the normal, already-proven path. This command never talks
 * to Neo4j directly — it only ever un-sticks the existing outbox table, the
 * smallest change that can move a stuck row forward.
 */
class FailedQueueTriageCommand extends Command
{
    protected $signature = 'neo4j:failed-queue
        {--requeue : reset a batch of failed rows back to pending for neo4j:drain to retry}
        {--limit=500 : max rows to requeue in one run}
        {--source= : only requeue rows for this source_table}
        {--rel= : only requeue rows for this rel_type}';

    protected $description = 'Report on permanently-failed neo4j_sync_queue rows; optionally requeue a bounded batch for neo4j:drain';

    public function handle(): int
    {
        $total = DB::table('neo4j_sync_queue')->count();

        if ($total === 0) {
            $this->info('neo4j_sync_queue is empty.');

            return self::SUCCESS;
        }

        $byStatus = DB::table('neo4j_sync_queue')
            ->select('status', DB::raw('count(*) as c'))
            ->groupBy('status')
            ->get();

        $this->info('neo4j_sync_queue: ' . number_format($total) . ' rows total');
        $this->table(
            ['status', 'count'],
            $byStatus->map(fn ($r) => [$r->status, number_format($r->c)])->all()
        );

        $failedQuery = fn () => DB::table('neo4j_sync_queue')->where('status', 'failed');
        $failedTotal = $failedQuery()->count();

        if ($failedTotal === 0) {
            $this->info('No permanently-failed rows.');

            return self::SUCCESS;
        }

        $breakdown = $failedQuery()
            ->select('source_table', 'rel_type', 'target_table', DB::raw('count(*) as c'), DB::raw('max(retry_count) as max_retries'))
            ->groupBy('source_table', 'rel_type', 'target_table')
            ->orderByDesc('c')
            ->limit(30)
            ->get();

        $this->newLine();
        $this->warn(number_format($failedTotal) . ' permanently-failed row(s), top 30 by (source_table, rel_type, target_table):');
        $this->table(
            ['source_table', 'rel_type', 'target_table', 'count', 'max retry_count'],
            $breakdown->map(fn ($r) => [$r->source_table, $r->rel_type, $r->target_table, number_format($r->c), $r->max_retries])->all()
        );

        if (! $this->option('requeue')) {
            $this->newLine();
            $this->line('Run with --requeue (optionally --source=X --rel=Y --limit=N) to reset a batch back to pending.');

            return self::SUCCESS;
        }

        $query = $failedQuery();

        if ($source = $this->option('source')) {
            $query->where('source_table', $source);
        }

        if ($rel = $this->option('rel')) {
            $query->where('rel_type', $rel);
        }

        $limit = (int) $this->option('limit');
        $ids = $query->orderBy('id')->limit($limit)->pluck('id');

        if ($ids->isEmpty()) {
            $this->info('Nothing matched the given filters.');

            return self::SUCCESS;
        }

        DB::table('neo4j_sync_queue')
            ->whereIn('id', $ids)
            ->update(['status' => 'pending', 'retry_count' => 0, 'processed_at' => null]);

        $this->info(count($ids) . ' row(s) reset to pending. Run `php artisan neo4j:drain` (or wait for the schedule) to retry them.');

        return self::SUCCESS;
    }
}
