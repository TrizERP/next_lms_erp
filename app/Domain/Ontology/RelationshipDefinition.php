<?php

namespace App\Domain\Ontology;

/**
 * One ontology edge, plus the plan for walking it.
 *
 * The same edge carries two traversal plans because the platform has two stores:
 *
 *  - SQL: `fromColumn` / `joinTable` / `joinFromColumn` / `joinToColumn` / `toColumn`
 *    describe the join against the tables that already hold the data. This always works.
 *  - Graph: `graphRelationshipType` names the Neo4j relationship, and `inGraph` says
 *    whether the migration has actually landed it yet. Only edges from completed Neo4j
 *    phases set this true, so the KG layer can prefer the graph and fall back to SQL
 *    without guessing.
 */
final class RelationshipDefinition
{
    public function __construct(
        public readonly string $key,
        public readonly string $fromEntityKey,
        public readonly string $relation,
        public readonly string $toEntityKey,
        public readonly string $cardinality = 'one_to_many',
        public readonly ?string $description = null,
        public readonly ?string $fromColumn = null,
        public readonly ?string $joinTable = null,
        public readonly ?string $joinFromColumn = null,
        public readonly ?string $joinToColumn = null,
        public readonly ?string $toColumn = null,
        public readonly ?string $graphRelationshipType = null,
        public readonly bool $inGraph = false,
        public readonly bool $traversable = true,
        public readonly int $traversalCost = 1,
        public readonly array $attributes = [],
        public readonly ?int $subInstituteId = null,
    ) {
    }

    public static function fromRow(object $row): self
    {
        return new self(
            key: (string) $row->relationship_key,
            fromEntityKey: (string) $row->from_entity_key,
            relation: (string) $row->relation,
            toEntityKey: (string) $row->to_entity_key,
            cardinality: (string) ($row->cardinality ?? 'one_to_many'),
            description: $row->description ?? null,
            fromColumn: $row->from_column ?? null,
            joinTable: $row->join_table ?? null,
            joinFromColumn: $row->join_from_column ?? null,
            joinToColumn: $row->join_to_column ?? null,
            toColumn: $row->to_column ?? null,
            graphRelationshipType: $row->graph_relationship_type ?? null,
            inGraph: (bool) ($row->in_graph ?? false),
            traversable: (bool) ($row->traversable ?? true),
            traversalCost: (int) ($row->traversal_cost ?? 1),
            attributes: self::decodeJson($row->attributes ?? null),
            subInstituteId: isset($row->sub_institute_id) ? (int) $row->sub_institute_id : null,
        );
    }

    /** A pivot edge needs three tables; a direct edge needs two. */
    public function usesJoinTable(): bool
    {
        return $this->joinTable !== null && $this->joinTable !== '';
    }

    /**
     * True when SQL traversal is fully configured. An edge that is declared but not
     * yet mapped is returned by the API (so the ontology stays honest about what it
     * knows) but is skipped by the traversal planner.
     */
    public function isSqlTraversable(): bool
    {
        if (! $this->traversable) {
            return false;
        }

        if ($this->usesJoinTable()) {
            return $this->fromColumn && $this->joinFromColumn && $this->joinToColumn && $this->toColumn;
        }

        return $this->fromColumn !== null && $this->toColumn !== null;
    }

    public function isGraphTraversable(): bool
    {
        return $this->traversable && $this->inGraph && $this->graphRelationshipType !== null;
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'from' => $this->fromEntityKey,
            'relation' => $this->relation,
            'to' => $this->toEntityKey,
            'cardinality' => $this->cardinality,
            'description' => $this->description,
            'sql_traversable' => $this->isSqlTraversable(),
            'graph_traversable' => $this->isGraphTraversable(),
            'graph_relationship_type' => $this->graphRelationshipType,
            'traversal_cost' => $this->traversalCost,
        ];
    }

    private static function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
