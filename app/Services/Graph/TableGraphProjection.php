<?php

namespace App\Services\Graph;

use App\Services\Graph\Contracts\GraphProjection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The projection for every entity whose graph shape is just a column map.
 *
 * Standard, Subject, Chapter, Concept, Lesson, Curriculum, Unit, Assessment,
 * Question and Teacher all reduce to the same three statements:
 *
 *   - MERGE one node, keyed on the label's unique property
 *   - copy these columns onto these node properties
 *   - MERGE these edges, whose endpoints are foreign keys on the same row
 *
 * Spelling that out as data in `config/neo4j.php` (`projections` key) instead of as ten
 * near-identical classes is what makes the K12 entity set maintainable: adding
 * an entity is a config entry plus a trigger, and every entity provably goes
 * through the same MERGE-on-unique-key path, so none of them can quietly drift
 * into a second key convention the way the graph already did once.
 *
 * A spec looks like:
 *
 *   'chapter_master' => [
 *       'label'      => 'Chapter',
 *       'properties' => ['chId' => 'id', 'chapter_name' => 'chapter_name', ...],
 *       'display_label'  => ['prefix' => 'chapter:', 'column' => 'chapter_name'],
 *       'relationships'  => [
 *           ['type' => 'HAS_CHAPTER', 'from' => ['Subject', 'subject_id'], 'to' => ['Chapter', 'id']],
 *       ],
 *   ]
 *
 * In `from`/`to`, the first element is a Neo4j label and the second is a COLUMN
 * OF THIS ROW holding that node's key — `id` meaning the row's own primary key.
 *
 * `key_column` covers the one case where the node is NOT keyed on the row's
 * primary key. `sub_std_map` is a standard-to-subject mapping: 6,656 rows carry
 * only 1,193 distinct `subject_id`, and every `subject_id` foreign key in the
 * schema points at THAT value, not at the mapping row. Measured 2026-08-21:
 * 5,414 of 5,443 `question_paper` rows and 86 of 114 `chapter_master` rows join
 * `sub_std_map.subject_id`; zero join `sub_std_map.id`. k12_cypher.txt contains
 * both conventions in two contradicting MERGE blocks — the live :Subject nodes
 * settle it, their `display_name` and `standard_id` matching the subject_id
 * join and not the row-id one.
 */
class TableGraphProjection implements GraphProjection
{
    public function __construct(
        private readonly string $table,
        private readonly array $spec,
        private readonly GraphOutbox $outbox,
    ) {
        if (! isset($spec['label'], $spec['properties'])) {
            throw new RuntimeException("Projection spec for '{$table}' needs both 'label' and 'properties'");
        }
    }

    public function tables(): array
    {
        return [$this->table];
    }

    public function labels(): array
    {
        return [$this->spec['label']];
    }

    public function enqueue(string $table, int $recordId, array $hints = []): array
    {
        $row = DB::table($this->table)->where('id', $recordId)->first();

        if (! $row) {
            throw new RuntimeException("{$this->table} row {$recordId} not found");
        }

        $row = (array) $row;
        $label = $this->spec['label'];
        $nodeId = $this->nodeId($row, $recordId);

        if ($nodeId === null) {
            // The column this label is keyed on is empty, so there is no node to
            // MERGE. Not an error: a half-filled mapping row is just not a node.
            return ['log' => [], 'queue' => []];
        }

        $log = [$this->outbox->node($label, $nodeId, $this->properties($row, $nodeId))];

        $queue = [];

        foreach ($this->spec['relationships'] ?? [] as $rel) {
            foreach ($this->edges($rel, $row, $nodeId) as [$sourceId, $targetId]) {
                $queue[] = $this->outbox->relationship(
                    $rel['from'][0], $sourceId, $rel['type'], $rel['to'][0], $targetId
                );
            }
        }

        return ['log' => $log, 'queue' => $queue];
    }

    public function delete(string $table, int $recordId, array $hints = []): array
    {
        $keyColumn = $this->spec['key_column'] ?? null;

        if ($keyColumn === null) {
            return [
                'log'   => [$this->outbox->node($this->spec['label'], $recordId, [], 'DELETE')],
                'queue' => [],
            ];
        }

        // Column-keyed labels are many-rows-to-one-node. Deleting one mapping
        // row must not delete a :Subject that other rows still describe — an
        // easy way to silently strip a subject out of the graph when a single
        // standard drops it.
        $nodeId = $this->intOrNull($hints[$keyColumn] ?? null);

        if ($nodeId === null) {
            return ['log' => [], 'queue' => []];
        }

        $survivors = DB::table($this->table)->where($keyColumn, $nodeId)->exists();

        return $survivors
            ? ['log' => [], 'queue' => []]
            : ['log' => [$this->outbox->node($this->spec['label'], $nodeId, [], 'DELETE')], 'queue' => []];
    }

    /**
     * One row, one node — except for a `key_column` entity, where many mapping
     * rows share a node and therefore share an entity key.
     */
    public function entityKey(string $table, int $recordId, array $hints = []): string
    {
        $keyColumn = $this->spec['key_column'] ?? null;

        if ($keyColumn === null) {
            return $this->table . ':' . $recordId;
        }

        $nodeId = $this->intOrNull($hints[$keyColumn] ?? null)
            ?? $this->intOrNull(DB::table($this->table)->where('id', $recordId)->value($keyColumn));

        return $this->table . ':' . ($nodeId ?? 'row-' . $recordId);
    }

    public function enqueueNode(string $label, int $nodeId): array
    {
        if ($label !== $this->spec['label']) {
            return ['log' => [], 'queue' => []];
        }

        $keyColumn = $this->spec['key_column'] ?? null;

        // For a column-keyed entity any backing row rebuilds the same node, so
        // the first one is as good as any.
        $recordId = $keyColumn === null
            ? $nodeId
            : $this->intOrNull(DB::table($this->table)->where($keyColumn, $nodeId)->value('id'));

        if ($recordId === null || ! DB::table($this->table)->where('id', $recordId)->exists()) {
            return ['log' => [], 'queue' => []];
        }

        return $this->enqueue($this->table, $recordId);
    }

    // -----------------------------------------------------------------------

    /**
     * The value this node is MERGEd on: the row's primary key, unless the spec
     * names a different column (see `key_column` above).
     */
    private function nodeId(array $row, int $recordId): ?int
    {
        $keyColumn = $this->spec['key_column'] ?? null;

        return $keyColumn === null
            ? $recordId
            : $this->intOrNull($row[$keyColumn] ?? null);
    }

    private function intOrNull($value): ?int
    {
        return (is_numeric($value) && (int) $value > 0) ? (int) $value : null;
    }

    /**
     * Column values mapped onto node properties, with the unique key and the
     * displayLabel the k12 ingest script writes.
     */
    private function properties(array $row, int $nodeId): array
    {
        $label = $this->spec['label'];
        $key = GraphSchema::key($label);

        $props = [$key => $nodeId];

        foreach ($this->spec['properties'] as $property => $column) {
            $value = $row[$column] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            $props[$property] = $this->cast($property, $value);
        }

        if (isset($this->spec['display_label'])) {
            $column = $this->spec['display_label']['column'];
            $props['displayLabel'] = $this->spec['display_label']['prefix']
                . trim((string) ($row[$column] ?? $nodeId));
        }

        // The key is authoritative — a stray column must never overwrite the
        // property the node is MERGEd on.
        $props[$key] = $nodeId;

        return $props;
    }

    /**
     * Node properties are typed in Neo4j: an id stored as the string "43" will
     * not match a node MERGEd on the integer 43. Anything ending `_id`, any
     * declared cast, and the unique key itself are forced to int.
     */
    private function cast(string $property, $value)
    {
        $casts = $this->spec['casts'] ?? [];

        if (($casts[$property] ?? null) === 'string') {
            return trim((string) $value);
        }

        if (($casts[$property] ?? null) === 'int'
            || str_ends_with($property, '_id')
            || $property === GraphSchema::key($this->spec['label'])) {
            return is_numeric($value) ? (int) $value : null;
        }

        if (($casts[$property] ?? null) === 'float') {
            return is_numeric($value) ? (float) $value : null;
        }

        return is_scalar($value) ? $value : null;
    }

    /**
     * Resolve one relationship spec into concrete (sourceId, targetId) pairs.
     *
     * `list` names the endpoint whose column holds a comma-separated set of ids
     * rather than a single one — `question_paper.question_ids` is the only case
     * in the K12 set, and it is exactly how the ingest script reads it
     * (`UNWIND split(row.question_ids, ',') AS qid`).
     *
     * @return array<array{0: int, 1: int}>
     */
    private function edges(array $rel, array $row, int $nodeId): array
    {
        $sources = $this->endpointIds($rel['from'][1], $row, $nodeId, ($rel['list'] ?? null) === 'from');
        $targets = $this->endpointIds($rel['to'][1], $row, $nodeId, ($rel['list'] ?? null) === 'to');

        $edges = [];

        foreach ($sources as $sourceId) {
            foreach ($targets as $targetId) {
                $edges[] = [$sourceId, $targetId];
            }
        }

        return $edges;
    }

    /** @return int[] */
    private function endpointIds(string $column, array $row, int $nodeId, bool $isList): array
    {
        // `id` means THIS ROW'S NODE KEY, which for a `key_column` spec is the
        // keyed column rather than the primary key.
        if ($column === 'id') {
            return [$nodeId];
        }

        $value = $row[$column] ?? null;

        if ($value === null || $value === '') {
            return [];
        }

        $candidates = $isList ? explode(',', (string) $value) : [$value];

        $ids = [];

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);

            // 0 is not a foreign key, it is an unset column — linking to a node
            // keyed 0 would invent an edge nobody asked for.
            if (is_numeric($candidate) && (int) $candidate > 0) {
                $ids[] = (int) $candidate;
            }
        }

        return array_values(array_unique($ids));
    }
}
