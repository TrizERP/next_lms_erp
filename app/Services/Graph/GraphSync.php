<?php

namespace App\Services\Graph;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * What controllers call. Two steps, and the split matters:
 *
 *   INSIDE the transaction   ->  enqueueStudent()   writes the outbox rows
 *   AFTER  it commits        ->  flush()            pushes them to Neo4j
 *
 * Why not one call after the commit? Because then a crash between COMMIT and
 * the graph write loses the event with nothing recording that it was owed. The
 * outbox row commits atomically with the student; flush() is only an
 * accelerator so the change is visible in the Neo4j Browser immediately instead
 * of at the next scheduled `neo4j:drain`.
 *
 * Why not one call inside the transaction? Because a bolt round-trip inside an
 * open transaction holds row locks for the duration of a remote network call —
 * a Neo4j slowdown would become a MariaDB lock storm.
 *
 * flush() NEVER throws. Anything it cannot deliver stays PENDING in the outbox
 * and the scheduled drain retries it; a graph outage must not turn a saved
 * student into a 500.
 *
 * Usage:
 *
 *     $ids = DB::transaction(function () use ($request) {
 *         $studentId = DB::table('tblstudent')->insertGetId([...]);
 *         DB::table('tblstudent_enrollment')->insert([...]);
 *         return app(GraphSync::class)->enqueueStudent($studentId, $tenantId);
 *     });
 *     app(GraphSync::class)->flush($ids);
 */
class GraphSync
{
    public function __construct(
        private readonly StudentGraphProjection $students,
        private readonly GraphDrain $drain,
    ) {
    }

    /**
     * Queue graph events for a student. Call INSIDE the transaction.
     *
     * Deliberately allowed to throw: the outbox insert is a local INSERT in the
     * caller's own transaction, so a failure here means the transaction is
     * already doomed and rolling the student back with it is correct.
     *
     * @return array{log: int[], queue: int[]}
     */
    public function enqueueStudent(int $studentId, int $tenantId): array
    {
        if (! config('neo4j.sync_enabled')) {
            return ['log' => [], 'queue' => []];
        }

        return $this->students->enqueue($studentId, $tenantId);
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
