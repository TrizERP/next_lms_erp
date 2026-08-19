<?php

namespace App\Services\Graph;

use App\Services\Neo4jService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Consumer half of the outbox — the component that died on 2026-04-02 and
 * stranded 8 rows in each table.
 *
 * Drains `sync_log` (nodes) then `neo4j_sync_queue` (relationships), in that
 * order, because an edge cannot be MERGEd onto endpoints that do not exist yet.
 *
 * Every write is a MERGE keyed on the label's unique property, so re-processing
 * is safe and a replay can never duplicate a node or an edge.
 */
class GraphDrain
{
    public function __construct(private readonly Neo4jService $neo4j)
    {
    }

    /**
     * Drain specific outbox rows — used straight after a business transaction
     * commits so the change is in the Neo4j Browser immediately rather than at
     * the next scheduled pass.
     *
     * @param  array{log: int[], queue: int[]}  $ids
     * @return array{nodes: int, rels: int, failed: int}
     */
    public function flush(array $ids): array
    {
        $nodes = $this->drainNodes(ids: $ids['log'] ?? []);
        $rels  = $this->drainRelationships(ids: $ids['queue'] ?? []);

        return [
            'nodes'  => $nodes['ok'],
            'rels'   => $rels['ok'],
            'failed' => $nodes['failed'] + $rels['failed'],
        ];
    }

    /**
     * Drain pending node events.
     *
     * @param  int[]  $ids  restrict to these sync_log ids; empty = all pending
     * @return array{ok: int, failed: int, errors: string[]}
     */
    public function drainNodes(int $limit = 500, int $maxRetries = 5, array $ids = []): array
    {
        $rows = DB::table('sync_log')
            ->where('status', 'PENDING')
            ->where(fn ($q) => $q->where('retry_count', '<', $maxRetries)->orWhereNull('retry_count'))
            ->when($ids !== [], fn ($q) => $q->whereIn('id', $ids))
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $ok = 0; $failed = 0; $errors = [];

        foreach ($rows as $row) {
            try {
                $this->applyNode($row);

                DB::table('sync_log')->where('id', $row->id)->update([
                    'status'       => 'SUCCESS',
                    'processed_at' => now(),
                ]);
                $ok++;
            } catch (Throwable $e) {
                $retries = (int) $row->retry_count + 1;

                // Stay PENDING until the retry budget is spent, so a transient
                // Neo4j outage is retried rather than written off on first fail.
                DB::table('sync_log')->where('id', $row->id)->update([
                    'status'      => $retries >= $maxRetries ? 'FAILED' : 'PENDING',
                    'retry_count' => $retries,
                ]);

                $errors[] = "sync_log#{$row->id} ({$row->table_name}#{$row->record_id}): {$e->getMessage()}";
                $failed++;
            }
        }

        return ['ok' => $ok, 'failed' => $failed, 'errors' => $errors];
    }

    /**
     * Drain pending relationship events.
     *
     * @param  int[]  $ids  restrict to these neo4j_sync_queue ids; empty = all pending
     * @return array{ok: int, failed: int, errors: string[]}
     */
    public function drainRelationships(int $limit = 500, array $ids = []): array
    {
        $rows = DB::table('neo4j_sync_queue')
            ->where('status', 'pending')
            ->when($ids !== [], fn ($q) => $q->whereIn('id', $ids))
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $ok = 0; $failed = 0; $errors = [];

        foreach ($rows as $row) {
            try {
                $matched = $this->applyRelationship($row);

                if ($matched === 0) {
                    // Endpoints absent. Not an error worth a stack trace, but
                    // it must not silently report success either.
                    throw new \RuntimeException(
                        "endpoint missing: ({$row->source_table}#{$row->source_id})"
                        . "-[:{$row->rel_type}]->({$row->target_table}#{$row->new_target_id})"
                    );
                }

                DB::table('neo4j_sync_queue')->where('id', $row->id)->update([
                    'status'       => 'done',
                    'processed_at' => now(),
                ]);
                $ok++;
            } catch (Throwable $e) {
                DB::table('neo4j_sync_queue')->where('id', $row->id)->update(['status' => 'failed']);
                $errors[] = "queue#{$row->id}: {$e->getMessage()}";
                $failed++;
            }
        }

        return ['ok' => $ok, 'failed' => $failed, 'errors' => $errors];
    }

    // -----------------------------------------------------------------------

    private function applyNode(object $row): void
    {
        $payload = json_decode((string) $row->payload_json, true, 512, JSON_THROW_ON_ERROR);

        $label = $payload['node_label'] ?? $row->table_name;
        $event = strtoupper($payload['event'] ?? $row->operation_type ?? 'INSERT');
        $data  = $payload['data'] ?? [];

        GraphSchema::assertLabel($label);           // closes the injection path
        $key = GraphSchema::key($label);
        $id  = (int) ($payload['record_id'] ?? $row->record_id);

        if ($event === 'DELETE') {
            $this->neo4j->run(
                "MATCH (n:`{$label}` {`{$key}`: \$id}) DETACH DELETE n",
                ['id' => $id]
            );

            return;
        }

        // `SET n += $props` merges: properties this projection does not own
        // (PAL's preferred_pedagogy, engagement_score, ...) are preserved.
        $this->neo4j->run(
            "MERGE (n:`{$label}` {`{$key}`: \$id}) SET n += \$props, n.updated_at = datetime()",
            ['id' => $id, 'props' => $this->scalarsOnly($data)]
        );
    }

    /**
     * @return int number of relationships matched/created
     */
    private function applyRelationship(object $row): int
    {
        $srcLabel = (string) $row->source_table;
        $tgtLabel = (string) $row->target_table;
        $relType  = (string) $row->rel_type;

        GraphSchema::assertLabel($srcLabel);
        GraphSchema::assertLabel($tgtLabel);
        GraphSchema::assertRelationship($relType);

        $srcKey = GraphSchema::key($srcLabel);
        $tgtKey = GraphSchema::key($tgtLabel);
        $event  = strtoupper((string) $row->event_type);

        // A re-point (student changes class): drop the stale edge first, so the
        // student is not left enrolled in both standards.
        if ($row->old_target_id !== null && (int) $row->old_target_id !== (int) $row->new_target_id) {
            $this->neo4j->run(
                "MATCH (s:`{$srcLabel}` {`{$srcKey}`: \$sid})-[r:`{$relType}`]->(t:`{$tgtLabel}` {`{$tgtKey}`: \$old})
                 DELETE r",
                ['sid' => (int) $row->source_id, 'old' => (int) $row->old_target_id]
            );
        }

        if ($event === 'DELETE' || $row->new_target_id === null) {
            $res = $this->neo4j->run(
                "MATCH (s:`{$srcLabel}` {`{$srcKey}`: \$sid})-[r:`{$relType}`]->(t:`{$tgtLabel}` {`{$tgtKey}`: \$tid})
                 DELETE r RETURN count(r) AS c",
                ['sid' => (int) $row->source_id, 'tid' => (int) $row->old_target_id]
            );

            return (int) $res->first()->get('c');
        }

        $params = ['sid' => (int) $row->source_id, 'tid' => (int) $row->new_target_id];

        // Target may be keyed under either convention. Resolve legacy first,
        // fall back to uid, link exactly one — and never CREATE a target:
        // minting a legacy twin for a tenant that already has a uid node would
        // deepen the duplication the graph already carries.
        if (GraphSchema::hasUidFallback($tgtLabel)) {
            $uid = GraphSchema::uidExpression($tgtLabel, 's', 'tid');

            $cypher = "
                MATCH (s:`{$srcLabel}` {`{$srcKey}`: \$sid})
                OPTIONAL MATCH (legacy:`{$tgtLabel}` {`{$tgtKey}`: \$tid})
                    WHERE legacy.sub_institute_id = s.sub_institute_id
                OPTIONAL MATCH (modern:`{$tgtLabel}` {uid: {$uid}})
                WITH s, coalesce(legacy, modern) AS t
                WHERE t IS NOT NULL
                MERGE (s)-[r:`{$relType}`]->(t)
                RETURN count(r) AS c";
        } else {
            $cypher = "
                MATCH (s:`{$srcLabel}` {`{$srcKey}`: \$sid})
                MATCH (t:`{$tgtLabel}` {`{$tgtKey}`: \$tid})
                MERGE (s)-[r:`{$relType}`]->(t)
                RETURN count(r) AS c";
        }

        $res = $this->neo4j->run($cypher, $params);
        $first = $res->first();

        return $first === null ? 0 : (int) $first->get('c');
    }

    /**
     * Neo4j properties must be primitives or arrays of primitives; a nested
     * object silently fails the whole write. Drop anything else rather than
     * letting one bad payload poison the batch.
     */
    private function scalarsOnly(array $data): array
    {
        return array_filter($data, fn ($v) => $v === null || is_scalar($v));
    }

    /** Current backlog, for monitoring. */
    public static function depth(): array
    {
        return [
            'nodes' => (int) DB::table('sync_log')->where('status', 'PENDING')->count(),
            'rels'  => (int) DB::table('neo4j_sync_queue')->where('status', 'pending')->count(),
        ];
    }
}
