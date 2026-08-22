<?php

namespace App\Services\Graph;

use Illuminate\Support\Facades\DB;

/**
 * Producer half of the transactional outbox.
 *
 * Writes into the two tables that already exist in this database and were
 * designed for exactly this — no new tables:
 *
 *   `sync_log`          NODE events.         One row per node to MERGE.
 *   `neo4j_sync_queue`  RELATIONSHIP events. One row per edge to MERGE.
 *
 * Both stopped on 2026-04-02 when their consumer died ~2h before their
 * producer, stranding 8 rows in each. The schema is sound; it lost its
 * consumer, not its design. `GraphDrain` is that consumer.
 *
 * CALL THESE INSIDE THE BUSINESS TRANSACTION. That is the whole point of an
 * outbox: the intent to update the graph commits atomically with the row it
 * describes, so the graph can never be told about a student MariaDB rolled
 * back, and a crash between the two can never lose the event.
 *
 * The payload column mirrors the format already in the table, so old and new
 * rows drain through the same code path:
 *
 *   {"record_id":436295,"event":"INSERT","node_label":"Student",
 *    "data":{"stuId":436295,"student_id":280668,...}}
 */
class GraphOutbox
{
    /**
     * Queue a node MERGE.
     *
     * @param  string  $label  Neo4j label — must be in GraphSchema
     * @param  int     $id     value of the label's unique key property
     * @param  array   $data   node properties (must include the unique key)
     * @return int     sync_log.id
     */
    public function node(string $label, int $id, array $data, string $event = 'INSERT'): int
    {
        GraphSchema::assertLabel($label);

        return (int) DB::table('sync_log')->insertGetId([
            'table_name'     => $label,
            'operation_type' => $event,
            'record_id'      => $id,
            'payload_json'   => json_encode([
                'record_id'  => $id,
                'event'      => $event,
                'node_label' => $label,
                'data'       => $data,
            ]),
            'status'      => 'PENDING',
            'retry_count' => 0,
            'created_at'  => now(),
        ]);
    }

    /**
     * Queue a relationship MERGE.
     *
     * `old_target_id` is how the existing schema expresses a re-point: when it
     * differs from `new_target_id` the drain removes the stale edge before
     * creating the new one. That matters for ENROLLED_IN — a student moving
     * class must not end up enrolled in both.
     *
     * @return int neo4j_sync_queue.id
     */
    public function relationship(
        string $sourceLabel,
        int $sourceId,
        string $relType,
        string $targetLabel,
        ?int $newTargetId,
        ?int $oldTargetId = null,
        string $event = 'INSERT'
    ): int {
        GraphSchema::assertLabel($sourceLabel);
        GraphSchema::assertLabel($targetLabel);
        GraphSchema::assertRelationship($relType);

        return (int) DB::table('neo4j_sync_queue')->insertGetId([
            'event_type'     => $event,
            'source_table'   => $sourceLabel,
            'source_id'      => $sourceId,
            'rel_type'       => $relType,
            'target_table'   => $targetLabel,
            'old_target_id'  => $oldTargetId,
            'new_target_id'  => $newTargetId,
            'status'         => 'pending',
            'created_at'     => now(),
        ]);
    }
}
