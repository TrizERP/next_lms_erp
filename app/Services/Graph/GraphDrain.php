<?php

namespace App\Services\Graph;

use App\Services\Neo4jService;
use Illuminate\Support\Facades\DB;
use RuntimeException;
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
 *
 * ---------------------------------------------------------------------------
 * TWO KINDS OF sync_log ROW
 * ---------------------------------------------------------------------------
 * `table_name` says which:
 *
 *   a Neo4j LABEL  ('Student', 'Chapter')   — a projected node event, ready to
 *                                             MERGE. Written by GraphOutbox.
 *   a MariaDB TABLE ('tblstudent')          — a thin "row X changed" event
 *                                             written by a database trigger.
 *                                             Expanded here through the row's
 *                                             projection, which emits the
 *                                             projected events above.
 *
 * The database triggers deliberately record only the fact of the change. That
 * keeps every decision about the SHAPE of the graph in PHP where it can be read
 * and reviewed, while making capture impossible to bypass: the fifteen-odd code
 * paths that write a student — admissions, imports, bulk edits, the legacy web
 * controller, raw SQL — all go through INSERT, and INSERT fires the trigger.
 * That is the defect this replaces. Before it, only two of those paths emitted
 * events, so a student added through any of the others (tblstudent#281472,
 * 2026-08-21) reached MariaDB and never reached the graph.
 */
class GraphDrain
{
    /** Guards against a projection that somehow emits another source event. */
    private const MAX_EXPANSION_DEPTH = 2;

    public function __construct(
        private readonly Neo4jService $neo4j,
        private readonly ProjectionRegistry $registry,
    ) {
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

        // Expanding a source event writes fresh relationship rows that are not
        // in the caller's id list, so the ids to drain are the ones asked for
        // PLUS whatever the expansion produced.
        $queue = array_values(array_unique(array_merge($ids['queue'] ?? [], $nodes['queued'])));

        $rels = $this->drainRelationships(ids: $queue);

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
     * @return array{ok: int, failed: int, errors: string[], queued: int[]}
     */
    public function drainNodes(int $limit = 500, int $maxRetries = 5, array $ids = [], int $depth = 0): array
    {
        $rows = DB::table('sync_log')
            ->where('status', 'PENDING')
            ->where(fn ($q) => $q->where('retry_count', '<', $maxRetries)->orWhereNull('retry_count'))
            ->when($ids !== [], fn ($q) => $q->whereIn('id', $ids))
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $rows = $this->supersedeDuplicates($rows);

        $ok = 0; $failed = 0; $errors = []; $queued = [];

        foreach ($rows as $row) {
            try {
                $expanded = $this->applyNode($row);

                DB::table('sync_log')->where('id', $row->id)->update([
                    'status'       => 'SUCCESS',
                    'processed_at' => now(),
                ]);
                $ok++;

                // A source event only becomes graph writes once its projection
                // has run. Drain those immediately so one pass is enough.
                if ($expanded !== null && $depth < self::MAX_EXPANSION_DEPTH) {
                    $queued = array_merge($queued, $expanded['queue']);

                    if ($expanded['log'] !== []) {
                        $inner = $this->drainNodes($limit, $maxRetries, $expanded['log'], $depth + 1);
                        $ok += $inner['ok'];
                        $failed += $inner['failed'];
                        $errors = array_merge($errors, $inner['errors']);
                        $queued = array_merge($queued, $inner['queued']);
                    }
                }
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

        return ['ok' => $ok, 'failed' => $failed, 'errors' => $errors, 'queued' => $queued];
    }

    /**
     * Drain pending relationship events.
     *
     * @param  int[]  $ids  restrict to these neo4j_sync_queue ids; empty = all pending
     * @return array{ok: int, failed: int, errors: string[]}
     */
    public function drainRelationships(int $limit = 500, array $ids = [], int $maxRetries = 5): array
    {
        $rows = DB::table('neo4j_sync_queue')
            ->where('status', 'pending')
            ->where(fn ($q) => $q->where('retry_count', '<', $maxRetries)->orWhereNull('retry_count'))
            ->when($ids !== [], fn ($q) => $q->whereIn('id', $ids))
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $ok = 0; $failed = 0; $errors = [];

        foreach ($rows as $row) {
            try {
                $matched = $this->applyRelationship($row);

                // Endpoints absent. Before giving up, try to build them: the
                // usual cause is an edge draining ahead of a node whose own
                // event has not been processed yet.
                if ($matched === 0 && (int) $row->retry_count === 0 && $this->repairEndpoints($row)) {
                    $matched = $this->applyRelationship($row);
                }

                if ($matched === 0) {
                    throw new RuntimeException(
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
                $retries = (int) $row->retry_count + 1;

                // Relationships get the same retry budget nodes have always
                // had. Marking them `failed` on the first miss — as this did
                // until 2026-08-21 — permanently dropped any edge whose target
                // simply had not drained yet, with nothing to retry it.
                DB::table('neo4j_sync_queue')->where('id', $row->id)->update([
                    'status'      => $retries >= $maxRetries ? 'failed' : 'pending',
                    'retry_count' => $retries,
                ]);

                $errors[] = "queue#{$row->id}: {$e->getMessage()}";
                $failed++;
            }
        }

        return ['ok' => $ok, 'failed' => $failed, 'errors' => $errors];
    }

    // -----------------------------------------------------------------------

    /**
     * Collapse repeated source events describing the same entity.
     *
     * Two causes, both common:
     *
     *   - a bulk update touches a student ten times, queueing ten identical
     *     "tblstudent#281472 changed" events;
     *   - one new student queues BOTH a `tblstudent` and a
     *     `tblstudent_enrollment` event, and the projection rebuilds the whole
     *     person from either — so expanding both writes every node twice.
     *
     * Collapsing on the projection's entity key catches both. Only the newest
     * row carries information; the rest are marked done without work. The graph
     * was never wrong without this (every write is a MERGE), but it did twice
     * the Cypher for every student saved, and twice again for every row of a
     * bulk import.
     *
     * DELETE events are never collapsed. A person delete and an enrolment
     * delete map to the same entity, but they remove different nodes, and
     * keeping only the newest would silently skip one of them.
     *
     * Projected node events are never collapsed either — each names its own node
     * and they are already unique per (label, id) per change.
     */
    private function supersedeDuplicates($rows)
    {
        $keep = [];
        $superseded = [];

        foreach ($rows as $row) {
            $key = $this->collapseKey($row);

            if ($key === null) {
                $keep[] = $row;

                continue;
            }

            if (isset($keep[$key])) {
                $superseded[] = $keep[$key]->id;
            }

            $keep[$key] = $row;
        }

        if ($superseded !== []) {
            DB::table('sync_log')->whereIn('id', $superseded)->update([
                'status'       => 'SUCCESS',
                'processed_at' => now(),
            ]);
        }

        return collect($keep)->sortBy('id')->values();
    }

    /**
     * The key a source event collapses on, or null when it must be kept as-is.
     */
    private function collapseKey(object $row): ?string
    {
        $table = (string) $row->table_name;

        if (GraphSchema::knowsLabel($table)) {
            return null;   // a projected node event
        }

        if (strtoupper((string) ($row->operation_type ?? '')) === 'DELETE') {
            return null;   // see the note above
        }

        try {
            $payload = $row->payload_json === null || $row->payload_json === ''
                ? []
                : (json_decode((string) $row->payload_json, true) ?: []);

            return $this->registry->for($table)->entityKey(
                $table,
                (int) $row->record_id,
                $payload['data'] ?? $payload
            );
        } catch (Throwable) {
            // Unknown table, or the row is gone. Fall back to per-row identity;
            // applyNode will produce the real error with proper retry handling.
            return $table . '#' . $row->record_id;
        }
    }

    /**
     * @return array{log: int[], queue: int[]}|null  outbox ids a source event
     *                                               expanded into, else null
     */
    private function applyNode(object $row): ?array
    {
        $payload = $row->payload_json === null || $row->payload_json === ''
            ? []
            : (json_decode((string) $row->payload_json, true) ?: []);

        $label = $payload['node_label'] ?? $row->table_name;

        if (! GraphSchema::knowsLabel((string) $label)) {
            // `data` is the envelope written since 2026-08-21; the bare payload
            // is the older thin format. Accept both so rows queued before the
            // change still drain.
            return $this->expandSourceEvent($row, $payload['data'] ?? $payload);
        }

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

            $this->cancelPendingEdges($label, $id);

            return null;
        }

        // `SET n += $props` merges: properties this projection does not own
        // (PAL's preferred_pedagogy, engagement_score, ...) are preserved.
        $this->neo4j->run(
            "MERGE (n:`{$label}` {`{$key}`: \$id}) SET n += \$props, n.updated_at = datetime()",
            ['id' => $id, 'props' => $this->scalarsOnly($data)]
        );

        return null;
    }

    /**
     * A node was deleted — retire any edge still queued for it.
     *
     * DETACH DELETE already removed its edges from the graph, so a pending row
     * pointing at it can never do anything except fail five times and settle as
     * `failed`. Left alone those accumulate as permanent noise in the health
     * report, indistinguishable from the genuinely broken rows worth acting on
     * (the cross-tenant ENROLLED_IN rows, say). Create-then-delete-quickly is
     * ordinary — a mistyped admission corrected a minute later — so this is a
     * normal path, not an edge case.
     *
     * Marked `done` rather than deleted: the outbox tables are the audit trail
     * of what the graph was told, and erasing rows from them loses that.
     */
    private function cancelPendingEdges(string $label, int $id): void
    {
        DB::table('neo4j_sync_queue')
            ->where('status', 'pending')
            ->where(function ($q) use ($label, $id) {
                $q->where(fn ($w) => $w->where('source_table', $label)->where('source_id', $id))
                  ->orWhere(fn ($w) => $w->where('target_table', $label)->where('new_target_id', $id));
            })
            ->update(['status' => 'done', 'processed_at' => now()]);
    }

    /**
     * Run the projection for a trigger-written "row X of table T changed" event.
     *
     * @return array{log: int[], queue: int[]}
     */
    private function expandSourceEvent(object $row, array $hints): array
    {
        $table = (string) $row->table_name;
        $recordId = (int) $row->record_id;
        $projection = $this->registry->for($table);
        $event = strtoupper((string) ($row->operation_type ?? 'UPSERT'));


        if ($event === 'DELETE') {
            return $projection->delete($table, $recordId, $hints);
        }

        try {
            return $projection->enqueue($table, $recordId, $hints);
        } catch (RuntimeException $e) {
            // The row went away between the trigger firing and this drain. Its
            // own DELETE event does the cleanup, so this one is spent — retrying
            // it could only fail the same way until the budget ran out.
            if (! DB::table($table)->where('id', $recordId)->exists()) {
                return ['log' => [], 'queue' => []];
            }

            throw $e;
        }
    }

    /**
     * Try to create an edge's missing endpoints by running their projections.
     *
     * THE TEST IS "DOES A NODE EXIST UNDER EITHER CONVENTION", NOT "IS THIS
     * LABEL UID-CAPABLE". Creating a legacy-keyed :Concept when the tenant
     * already has a uid-keyed one mints a duplicate twin — defect D2, the exact
     * duplication the graph is being migrated out of. But refusing to repair
     * every uid-capable label, as this did until 2026-08-21, also refuses the
     * case where NOTHING exists under either convention, where creating the node
     * is both safe and the only way the edge can ever be built. That cost four
     * real Question->ASSESSES->Concept edges, whose :Concept simply had not been
     * loaded: MariaDB has concepts up to id 2721, the CSV ingest stopped at 1467.
     *
     * So: check both conventions, and repair only into a genuine void.
     */
    private function repairEndpoints(object $row): bool
    {
        $endpoints = [
            [(string) $row->source_table, (int) $row->source_id],
            [(string) $row->target_table, (int) $row->new_target_id],
        ];

        $repaired = false;

        foreach ($endpoints as [$label, $nodeId]) {
            if ($nodeId <= 0 || ! GraphSchema::knowsLabel($label)) {
                continue;
            }

            if ($this->nodeExistsEitherConvention($label, $nodeId)) {
                continue;
            }

            $table = $this->registry->tableForLabel($label);

            if ($table === null) {
                continue;
            }

            try {
                $ids = $this->registry->for($table)->enqueueNode($label, $nodeId);

                if ($ids['log'] !== []) {
                    $this->drainNodes(ids: $ids['log'], depth: self::MAX_EXPANSION_DEPTH);
                    $repaired = true;
                }
            } catch (Throwable) {
                // Nothing in MariaDB backs this node either. Leave it; the row
                // retries and reconcile reports it.
            }
        }

        return $repaired;
    }

    /**
     * Is this row in the graph at all — under its legacy key, or as a uid-keyed
     * node whose uid carries the same id?
     *
     * The uid is `Label:tenant:syear:id`, so segment 3 is the id. It is read
     * back rather than reconstructed, because reconstructing it needs a tenant
     * and the tenant is exactly what an outbox row does not carry.
     *
     * Tenant-blind on purpose: a uid node for a DIFFERENT tenant with the same
     * id still counts as present here, so repair declines. That is the
     * conservative direction — declining to repair leaves an edge unbuilt and
     * visible in `neo4j:reconcile`, while repairing wrongly creates a duplicate
     * node that is far harder to find and undo.
     */
    private function nodeExistsEitherConvention(string $label, int $id): bool
    {
        GraphSchema::assertLabel($label);
        $key = GraphSchema::key($label);

        $legacy = $this->countOf($this->neo4j->run(
            "MATCH (n:`{$label}` {`{$key}`: \$id}) RETURN count(n) AS c",
            ['id' => $id]
        ));

        if ($legacy > 0) {
            return true;
        }

        if (! GraphSchema::hasUidFallback($label)) {
            return false;
        }

        return $this->countOf($this->neo4j->run(
            "MATCH (n:`{$label}`) WHERE n.uid IS NOT NULL
             WITH toInteger(split(n.uid, ':')[3]) AS uidId
             WHERE uidId = \$id
             RETURN count(*) AS c",
            ['id' => $id]
        )) > 0;
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
        // student is not left enrolled in both.
        if ($row->old_target_id !== null && (int) $row->old_target_id !== (int) $row->new_target_id) {
            $this->neo4j->run(
                "MATCH (s:`{$srcLabel}` {`{$srcKey}`: \$sid})-[r:`{$relType}`]->(t:`{$tgtLabel}` {`{$tgtKey}`: \$old})
                 DELETE r",
                ['sid' => (int) $row->source_id, 'old' => (int) $row->old_target_id]
            );
        }

        if ($event === 'DELETE' || $row->new_target_id === null) {
            // countOf, not ->first(): a DELETE that matches nothing returns an
            // EMPTY result, and first() throws OutOfBoundsException on empty —
            // turning "the edge was already gone" into a hard error.
            return $this->countOf($this->neo4j->run(
                "MATCH (s:`{$srcLabel}` {`{$srcKey}`: \$sid})-[r:`{$relType}`]->(t:`{$tgtLabel}` {`{$tgtKey}`: \$tid})
                 DELETE r RETURN count(r) AS c",
                ['sid' => (int) $row->source_id, 'tid' => (int) $row->old_target_id]
            ));
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

        $matched = $this->countOf($this->neo4j->run($cypher, $params));

        if ($matched > 0 || ! GraphSchema::hasUidFallback($srcLabel)) {
            return $matched;
        }

        // Nothing matched and the SOURCE is a label that also has uid-keyed
        // nodes. The query above can only ever find the source by its legacy
        // key — the fallback there is target-only, because the uid needs a
        // tenant and the tenant came from the source. Mirror it: resolve the
        // TARGET by legacy key, take the tenant from that, and use it to build
        // the source's uid.
        //
        // Without this every curriculum edge whose source is uid-keyed —
        // Standard->Subject, Subject->Chapter, Chapter->Concept,
        // Chapter->Lesson, Curriculum->Unit — silently fails to link, which is
        // most of the K12 graph above the student.
        $srcUid = GraphSchema::uidExpression($srcLabel, 't', 'sid');

        return $this->countOf($this->neo4j->run("
            MATCH (t:`{$tgtLabel}` {`{$tgtKey}`: \$tid})
            OPTIONAL MATCH (legacy:`{$srcLabel}` {`{$srcKey}`: \$sid})
                WHERE legacy.sub_institute_id = t.sub_institute_id
            OPTIONAL MATCH (modern:`{$srcLabel}` {uid: {$srcUid}})
            WITH t, coalesce(legacy, modern) AS s
            WHERE s IS NOT NULL
            MERGE (s)-[r:`{$relType}`]->(t)
            RETURN count(r) AS c
        ", $params));
    }

    private function countOf($result): int
    {
        foreach ($result as $record) {
            return (int) $record->get('c');
        }

        return 0;
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
