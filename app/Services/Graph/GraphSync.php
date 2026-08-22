<?php

namespace App\Services\Graph;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * What controllers call.
 *
 * ---------------------------------------------------------------------------
 * THE PRODUCER IS THE DATABASE, NOT THE CONTROLLER
 * ---------------------------------------------------------------------------
 * A trigger on `tblstudent` / `tblstudent_enrollment` writes the outbox row, so
 * the intent to update the graph commits atomically with the student no matter
 * which of the fifteen code paths saved them. Controllers do not enqueue any
 * more — they only ACCELERATE delivery:
 *
 *     $studentId = DB::transaction(fn () => ...);   // trigger queues the event
 *     app(GraphSync::class)->flushRecord('tblstudent', $studentId);
 *
 * Without that call the student still reaches Neo4j, just at the next
 * `neo4j:drain` pass instead of within the request. With it, they are in the
 * Browser by the time the API responds.
 *
 * Why not flush inside the transaction? Because a bolt round-trip inside an
 * open transaction holds row locks for the duration of a remote network call —
 * a Neo4j slowdown would become a MariaDB lock storm.
 *
 * flush() NEVER throws. Anything it cannot deliver stays PENDING in the outbox
 * and the scheduled drain retries it; a graph outage must not turn a saved
 * student into a 500.
 */
class GraphSync
{
    public function __construct(private readonly GraphDrain $drain)
    {
    }

    /**
     * Deliver the queued graph events for one MariaDB row, now.
     *
     * Call AFTER the transaction commits. Safe to call when sync is off, when
     * the triggers are not installed, or when nothing was queued — it simply
     * finds no rows.
     */
    public function flushRecord(string $table, int $recordId): void
    {
        if (! config('neo4j.sync_enabled')) {
            return;
        }

        try {
            $ids = DB::table('sync_log')
                ->where('status', 'PENDING')
                ->where('table_name', $table)
                ->where('record_id', $recordId)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $this->flush(['log' => $ids, 'queue' => []]);
        } catch (Throwable $e) {
            Log::error('Neo4j flushRecord failed; events remain queued', [
                'table' => $table,
                'id'    => $recordId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Push queued events to Neo4j. Call AFTER the transaction commits.
     *
     * @param  array{log: int[], queue: int[]}  $ids
     */
    public function flush(array $ids): void
    {
        if (! config('neo4j.sync_enabled')) {
            return;
        }

        if (($ids['log'] ?? []) === [] && ($ids['queue'] ?? []) === []) {
            return;
        }

        try {
            $result = $this->drain->flush($ids);

            if ($result['failed'] > 0) {
                Log::warning('Neo4j flush left rows undelivered; scheduled drain will retry', [
                    'ids'    => $ids,
                    'result' => $result,
                ]);
            }
        } catch (Throwable $e) {
            // Never propagate: the business row is committed and the outbox
            // rows are durable, so the only correct behaviour is to leave them
            // for `neo4j:drain`.
            Log::error('Neo4j flush failed; events remain queued', [
                'ids'   => $ids,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
