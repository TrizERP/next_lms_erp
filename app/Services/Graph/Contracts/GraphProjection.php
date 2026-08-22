<?php

namespace App\Services\Graph\Contracts;

/**
 * Turns one changed MariaDB row into outbox events.
 *
 * A projection is the ONLY place that knows what a table looks like as a graph.
 * It never talks to Neo4j — it writes `sync_log` / `neo4j_sync_queue` rows via
 * GraphOutbox and lets GraphDrain deliver them. That split is what makes the
 * whole pipeline replayable: re-running a projection produces the same MERGEs.
 *
 * Projections are driven by the database triggers installed in
 * `2026_08_21_100000_create_neo4j_sync_triggers`, which record only "row X of
 * table T changed". Everything about the SHAPE of the graph lives here, in PHP,
 * where it can be read and reviewed — never in SQL.
 */
interface GraphProjection
{
    /**
     * MariaDB tables this projection is the authority for.
     *
     * A projection may own several: a student is `tblstudent` (the person) plus
     * `tblstudent_enrollment` (their enrolments), and a change to either has to
     * re-project the same subgraph.
     *
     * @return string[]
     */
    public function tables(): array;

    /**
     * Neo4j labels this projection writes, so GraphDrain can work out which
     * projection to run when a relationship names an endpoint that is not in
     * the graph yet.
     *
     * @return string[]
     */
    public function labels(): array;

    /**
     * Expand a created/updated row into node + relationship events.
     *
     * @param  string  $table     which of `tables()` fired
     * @param  int     $recordId  primary key of the changed row
     * @param  array   $hints     columns the trigger captured (sub_institute_id,
     *                            student_id, ...); present for DELETE, where the
     *                            row can no longer be read back
     * @return array{log: int[], queue: int[]}
     */
    public function enqueue(string $table, int $recordId, array $hints = []): array;

    /**
     * Expand a deleted row into node/relationship removal events.
     *
     * The row is already gone by the time this runs, so it may only rely on
     * `$hints` and on what the outbox tables already recorded.
     *
     * @return array{log: int[], queue: int[]}
     */
    public function delete(string $table, int $recordId, array $hints = []): array;

    /**
     * Which real-world THING this event is about.
     *
     * Two source events can describe the same entity from different tables: a
     * new student queues one row for `tblstudent` and another for
     * `tblstudent_enrollment`, and because the student projection rebuilds the
     * whole person either way, expanding both does the identical work twice.
     * Rows sharing an entity key collapse to the newest before expansion.
     *
     * Return a stable string, e.g. "student:282261".
     */
    public function entityKey(string $table, int $recordId, array $hints = []): string;

    /**
     * Project a node identified by its GRAPH key rather than by a table row.
     *
     * GraphDrain uses this to repair an edge whose endpoint is not in the graph
     * yet: the queue row names `(:Standard {stId: 43})`, and only the projection
     * knows which MariaDB row backs that. Usually the key is the primary key —
     * but not for a `key_column` entity like :Subject, where one node is backed
     * by many mapping rows.
     *
     * @return array{log: int[], queue: int[]}  empty when nothing backs the node
     */
    public function enqueueNode(string $label, int $nodeId): array;
}
