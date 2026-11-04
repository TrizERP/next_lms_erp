<?php

namespace App\Domain\KnowledgeGraph;

use App\Domain\Ontology\EntityDefinition;
use App\Domain\Ontology\OntologyRegistry;
use App\Domain\Ontology\RelationshipDefinition;
use App\Services\Graph\GraphSchema;
use App\Services\Mcp\McpRequestContext;
use App\Services\Neo4jService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Throwable;

/**
 * Answers relationship questions over the platform's real data.
 *
 * Two stores, one interface. Where the Neo4j migration has actually landed an edge
 * (`ontology_relationships.in_graph`), the graph is used; everywhere else the same
 * edge is walked as a SQL join against the tables that already hold the records.
 * Learner-side edges are all SQL today because migration phases 7 (People) and 8
 * (Assessment) have not run — see docs/neo4j-migration-status.md. The caller never
 * has to know which store answered; `source` on each node records it.
 *
 * Every hop re-applies the tenant filter from McpRequestContext. Traversal is not a
 * back door: reaching a record through three joins gives no more access than
 * reading it directly.
 */
class GraphQueryService
{
    /**
     * Neo4j "from" label overrides, keyed by [ontology entity key][graph
     * relationship type]. `graphLabelFor()`'s naive transform is right for
     * almost every entity, but `fee_student` (sourceTable `tblstudent`, same
     * person as the `student` entity) needs a DIFFERENT real label depending
     * on which edge is being walked: `MADE_PAYMENT` is rooted directly on the
     * person (`:StuDetail`), while `ENROLLED_IN` needs the exact same
     * person->enrolment bridge `resolveGraphStartIds()` already does for
     * `student` — so it must resolve to `Student`, not naively to a
     * nonexistent `FeeStudent` label, so that bridge actually fires.
     */
    private const FROM_LABEL_OVERRIDE = [
        'fee_student' => [
            'ENROLLED_IN' => 'Student',
            'MADE_PAYMENT' => 'StuDetail',
        ],
    ];

    public function __construct(
        private readonly OntologyRegistry $registry,
        private readonly ?Neo4jService $neo4j = null,
    ) {
    }

    /**
     * Walk the graph from a starting record.
     *
     * @return array{
     *   start: array|null,
     *   nodes: array<int, array>,
     *   edges: array<int, array>,
     *   truncated: bool,
     *   sources: array<string, int>
     * }
     */
    public function traverse(TraversalSpec $spec, McpRequestContext $context): array
    {
        $tenantId = $context->selectedInstituteId;
        $startEntity = $this->registry->entity($spec->startEntityKey, $tenantId);

        if (! $startEntity) {
            return $this->emptyResult();
        }

        $startNode = $this->loadNode($startEntity, $spec->startId, $context);

        if (! $startNode) {
            // Either it does not exist or it is outside scope. Same answer either way.
            return $this->emptyResult();
        }

        $nodes = [$startNode->fingerprint() => $startNode];
        $edges = [];
        $sources = ['sql' => 0, 'graph' => 0];
        $truncated = false;

        // Current frontier: the nodes we expand from on this hop.
        $frontier = [$startNode];
        $hops = $spec->path !== [] ? count($spec->path) : $spec->maxDepth;
        $hops = min($hops, $spec->maxDepth);

        for ($depth = 0; $depth < $hops; $depth++) {
            $targetEntityKey = $spec->path[$depth] ?? null;
            $relationFilter = $spec->relationAt($depth);
            $nextFrontier = [];

            foreach ($frontier as $node) {
                $candidateEdges = $this->edgesFor($node->entityKey, $targetEntityKey, $relationFilter, $tenantId);

                foreach ($candidateEdges as $edge) {
                    $neighbours = $this->expand($node, $edge, $spec, $context, $depth + 1);

                    if (count($neighbours) >= $spec->limitPerHop) {
                        $truncated = true;
                    }

                    foreach ($neighbours as $neighbour) {
                        $sources[$neighbour->source] = ($sources[$neighbour->source] ?? 0) + 1;

                        $edges[] = [
                            'from' => $node->fingerprint(),
                            'to' => $neighbour->fingerprint(),
                            'relation' => $edge->relation,
                            'relationship_key' => $edge->key,
                            'source' => $neighbour->source,
                        ];

                        if (! isset($nodes[$neighbour->fingerprint()])) {
                            $nodes[$neighbour->fingerprint()] = $neighbour;
                            $nextFrontier[] = $neighbour;
                        }
                    }
                }
            }

            if ($nextFrontier === []) {
                break;
            }

            $frontier = $nextFrontier;
        }

        return [
            'start' => $startNode->toArray(),
            'nodes' => array_values(array_map(fn (GraphNode $node) => $node->toArray(), $nodes)),
            'edges' => $edges,
            'truncated' => $truncated,
            'sources' => $sources,
        ];
    }

    /**
     * Neighbours of a single record along one named relation. This is the primitive
     * the Evidence collector uses when it needs "this student's last N assessments".
     *
     * @return array<int, GraphNode>
     */
    public function neighbours(
        string $entityKey,
        int|string $id,
        string $relation,
        McpRequestContext $context,
        int $limit = 25
    ): array {
        $tenantId = $context->selectedInstituteId;
        $entity = $this->registry->entity($entityKey, $tenantId);

        if (! $entity) {
            return [];
        }

        $node = $this->loadNode($entity, $id, $context);

        if (! $node) {
            return [];
        }

        $results = [];

        foreach ($this->registry->relationshipsFrom($entityKey, $tenantId) as $edge) {
            if ($edge->relation !== $relation) {
                continue;
            }

            $spec = new TraversalSpec(
                startEntityKey: $entityKey,
                startId: $id,
                maxDepth: 1,
                limitPerHop: $limit,
            );

            foreach ($this->expand($node, $edge, $spec, $context, 1) as $neighbour) {
                $results[] = $neighbour;
            }
        }

        return $results;
    }

    /**
     * Which relations can be walked from here? Used by the conversational layer to
     * describe what it can answer without guessing.
     */
    public function availableRelations(string $entityKey, ?int $subInstituteId = null): array
    {
        return array_values(array_map(
            fn (RelationshipDefinition $edge) => [
                'relation' => $edge->relation,
                'to' => $edge->toEntityKey,
                'traversable' => $edge->isSqlTraversable() || $edge->isGraphTraversable(),
                'source' => $edge->isGraphTraversable() ? 'graph' : 'sql',
            ],
            $this->registry->relationshipsFrom($entityKey, $subInstituteId)
        ));
    }

    /**
     * Expand one node's REAL connections in every direction the ontology
     * knows about — every outgoing edge `relationshipsFrom()` declares, and
     * every incoming edge `relationshipsTo()` declares — rather than one
     * named relation at a time. This is what a click-to-expand canvas needs:
     * "what is actually connected to this specific node", not a fixed,
     * pre-authored hop sequence like `OntologyViewResolver::resolve()` walks.
     *
     * Incoming edges are graph-only for now (no generic reverse-SQL-join
     * exists in this service, and none of the edges this is used against
     * today need one) — an incoming edge that isn't `in_graph` is reported
     * as unavailable with a reason, the same honesty `resolve()` already
     * applies to outgoing edges, rather than silently omitted.
     *
     * @return array{node: GraphNode, outgoing: array, incoming: array}|null
     */
    public function expandNode(
        string $entityKey,
        int|string $id,
        McpRequestContext $context,
        int $limitPerRelation = 10
    ): ?array {
        $tenantId = $context->selectedInstituteId;
        $entity = $this->registry->entity($entityKey, $tenantId);

        if (! $entity) {
            return null;
        }

        $node = $this->loadNode($entity, $id, $context);

        if (! $node) {
            return null;
        }

        $outgoing = [];

        foreach ($this->registry->relationshipsFrom($entityKey, $tenantId) as $edge) {
            if (! $edge->isSqlTraversable() && ! $edge->isGraphTraversable()) {
                $outgoing[] = [
                    'relation' => $edge->relation,
                    'to_entity' => $edge->toEntityKey,
                    'direction' => 'outgoing',
                    'available' => false,
                    'reason' => 'This relationship is not mapped in the current data model.',
                    'items' => [],
                ];

                continue;
            }

            $spec = new TraversalSpec(startEntityKey: $entityKey, startId: $id, maxDepth: 1, limitPerHop: $limitPerRelation);

            $outgoing[] = [
                'relation' => $edge->relation,
                'to_entity' => $edge->toEntityKey,
                'direction' => 'outgoing',
                'available' => true,
                'source' => $edge->isGraphTraversable() ? 'graph' : 'sql',
                'items' => $this->expand($node, $edge, $spec, $context, 1),
            ];
        }

        $incoming = [];

        foreach ($this->registry->relationshipsTo($entityKey, $tenantId) as $edge) {
            if (! $edge->isGraphTraversable()) {
                $incoming[] = [
                    'relation' => $edge->relation,
                    'from_entity' => $edge->fromEntityKey,
                    'direction' => 'incoming',
                    'available' => false,
                    'reason' => 'Neo4j graph mapping not available for this relationship.',
                    'items' => [],
                ];

                continue;
            }

            $incoming[] = [
                'relation' => $edge->relation,
                'from_entity' => $edge->fromEntityKey,
                'direction' => 'incoming',
                'available' => true,
                'source' => 'graph',
                'items' => $this->expandIncomingViaGraph($node, $edge, $context, $limitPerRelation) ?? [],
            ];
        }

        return ['node' => $node, 'outgoing' => $outgoing, 'incoming' => $incoming];
    }

    /**
     * The incoming mirror of `expandViaGraph()` — same label/key resolution,
     * same tenant filter, same `FROM_LABEL_OVERRIDE`-aware bridge for the
     * node being expanded, arrow direction reversed: `(a)-[:TYPE]->(b)`
     * where `b` is this node, returning real `a` nodes.
     *
     * @return array<int, GraphNode>|null
     */
    private function expandIncomingViaGraph(
        GraphNode $node,
        RelationshipDefinition $edge,
        McpRequestContext $context,
        int $limit
    ): ?array {
        $sourceEntity = $this->registry->entity($edge->fromEntityKey, $context->selectedInstituteId);

        if (! $sourceEntity || $node->id === null) {
            return null;
        }

        $toLabel = self::FROM_LABEL_OVERRIDE[$node->entityKey][$edge->graphRelationshipType]
            ?? $this->graphLabelFor($node->entityKey);
        $fromLabel = $this->graphLabelFor($sourceEntity->key);

        try {
            $toKey = GraphSchema::key($toLabel);
            $fromKey = GraphSchema::key($fromLabel);
        } catch (InvalidArgumentException) {
            return null;
        }

        $startIds = $this->resolveGraphStartIds($node, $toLabel, $context);

        if ($startIds === null) {
            return null;
        }

        if ($startIds === []) {
            return [];
        }

        $cypher = sprintf(
            'MATCH (b:%s) WHERE b.%s IN $startIds '
            . 'MATCH (a:%s)-[:%s]->(b) '
            . 'WHERE ($tenantId IS NULL OR a.sub_institute_id IS NULL OR a.sub_institute_id = $tenantId) '
            . 'RETURN DISTINCT a LIMIT %d',
            $toLabel,
            $toKey,
            $fromLabel,
            $edge->graphRelationshipType,
            $limit
        );

        try {
            $result = $this->neo4j->run($cypher, ['startIds' => $startIds, 'tenantId' => $context->selectedInstituteId]);
        } catch (Throwable) {
            return null;
        }

        if ($result === null) {
            return null;
        }

        $nodes = [];

        foreach ($result as $record) {
            $value = is_object($record) && method_exists($record, 'get') ? $record->get('a') : null;
            $properties = (is_object($value) && method_exists($value, 'getProperties'))
                ? (method_exists($value->getProperties(), 'toArray') ? $value->getProperties()->toArray() : (array) $value->getProperties())
                : [];

            if ($properties === []) {
                continue;
            }

            $nodes[] = new GraphNode(
                entityKey: $sourceEntity->key,
                id: $properties[$fromKey] ?? null,
                label: $this->labelFor($properties, $sourceEntity),
                attributes: $this->attributesFor($properties, $sourceEntity),
                viaRelation: $edge->relation,
                depth: 1,
                source: 'graph',
            );
        }

        return $nodes;
    }

    // ---------------------------------------------------------------- internals

    /**
     * @return array<int, RelationshipDefinition>
     */
    private function edgesFor(
        string $fromEntityKey,
        ?string $targetEntityKey,
        ?string $relationFilter,
        ?int $tenantId
    ): array {
        $edges = $this->registry->relationshipsFrom($fromEntityKey, $tenantId);

        return array_values(array_filter($edges, function (RelationshipDefinition $edge) use ($targetEntityKey, $relationFilter) {
            if ($targetEntityKey !== null && $edge->toEntityKey !== $targetEntityKey) {
                return false;
            }

            if ($relationFilter !== null && $edge->relation !== $relationFilter) {
                return false;
            }

            return $edge->isSqlTraversable() || $edge->isGraphTraversable();
        }));
    }

    /**
     * @return array<int, GraphNode>
     */
    private function expand(
        GraphNode $node,
        RelationshipDefinition $edge,
        TraversalSpec $spec,
        McpRequestContext $context,
        int $depth
    ): array {
        if ($spec->preferGraph && $edge->isGraphTraversable() && $this->neo4j !== null) {
            $graphResults = $this->expandViaGraph($node, $edge, $spec, $context, $depth);

            if ($graphResults !== null) {
                return $graphResults;
            }
            // Graph unavailable or empty plan — fall through to SQL rather than fail.
        }

        return $edge->isSqlTraversable()
            ? $this->expandViaSql($node, $edge, $spec, $context, $depth)
            : [];
    }

    /**
     * @return array<int, GraphNode>
     */
    private function expandViaSql(
        GraphNode $node,
        RelationshipDefinition $edge,
        TraversalSpec $spec,
        McpRequestContext $context,
        int $depth
    ): array {
        $targetEntity = $this->registry->entity($edge->toEntityKey, $context->selectedInstituteId);

        if (! $targetEntity || ! $targetEntity->sourceTable || ! Schema::hasTable($targetEntity->sourceTable)) {
            return [];
        }

        // The value we join from: usually the node's primary key, but an edge may
        // start from one of its foreign keys (e.g. enrollment.standard_id -> standard.id).
        $fromValue = $this->resolveFromValue($node, $edge, $context);

        if ($fromValue === null) {
            return [];
        }

        $targetTable = $targetEntity->sourceTable;
        $targetKey = $targetEntity->primaryKeyColumn ?: 'id';

        $query = DB::table($targetTable);

        if ($edge->usesJoinTable()) {
            if (! Schema::hasTable($edge->joinTable)) {
                return [];
            }

            $query->join(
                $edge->joinTable,
                $edge->joinTable . '.' . $edge->joinToColumn,
                '=',
                $targetTable . '.' . $edge->toColumn
            )->where($edge->joinTable . '.' . $edge->joinFromColumn, $fromValue);
        } else {
            if (! Schema::hasColumn($targetTable, $edge->toColumn)) {
                return [];
            }

            $query->where($targetTable . '.' . $edge->toColumn, $fromValue);
        }

        // Extra static equality filters an edge needs beyond the join itself — e.g.
        // `ai_signals` shares its `id`/`subject_id` numbering across more than one
        // `subject_entity_key`, so a bare `subject_id = $fromValue` join can match a
        // different kind of subject that happens to share the id. Declared on the
        // edge's `attributes` JSON (`{"sql_filters":{"column":"value"}}`), not
        // hardcoded here, so this stays generic rather than a signal-specific hack.
        foreach ($edge->attributes['sql_filters'] ?? [] as $column => $value) {
            if (Schema::hasColumn($targetTable, $column)) {
                $query->where($targetTable . '.' . $column, $value);
            }
        }

        $this->applyScope($query, $targetEntity, $context);

        $query->select($targetTable . '.*')->limit($spec->limitPerHop);

        try {
            $rows = $query->get();
        } catch (Throwable $exception) {
            Log::warning('[knowledge-graph] SQL traversal failed', [
                'edge' => $edge->key,
                'error' => $exception->getMessage(),
            ]);

            return [];
        }

        return $rows->map(function ($row) use ($targetEntity, $targetKey, $edge, $depth) {
            $data = (array) $row;

            return new GraphNode(
                entityKey: $targetEntity->key,
                id: $data[$targetKey] ?? null,
                label: $this->labelFor($data, $targetEntity),
                attributes: $this->attributesFor($data, $targetEntity),
                viaRelation: $edge->relation,
                depth: $depth,
                source: 'sql',
            );
        })->all();
    }

    /**
     * Graph expansion. Returns null (rather than an empty array) when the graph could
     * not answer, so the caller knows to fall back to SQL instead of concluding
     * "no neighbours".
     *
     * @return array<int, GraphNode>|null
     */
    private function expandViaGraph(
        GraphNode $node,
        RelationshipDefinition $edge,
        TraversalSpec $spec,
        McpRequestContext $context,
        int $depth
    ): ?array {
        $targetEntity = $this->registry->entity($edge->toEntityKey, $context->selectedInstituteId);

        if (! $targetEntity || $node->id === null) {
            return null;
        }

        $fromLabel = self::FROM_LABEL_OVERRIDE[$node->entityKey][$edge->graphRelationshipType]
            ?? $this->graphLabelFor($node->entityKey);
        $toLabel = $this->graphLabelFor($targetEntity->key);

        // The graph is never keyed on a generic `id` property (GraphSchema.php's own
        // docblock: "no node uses `id` as its key" — MERGEing on it would silently miss
        // every real node). Resolve each label's actual unique key instead. A label
        // GraphSchema doesn't know about isn't safe to query — fall back to SQL rather
        // than build a MATCH against a made-up property.
        try {
            $fromKey = GraphSchema::key($fromLabel);
            $toKey = GraphSchema::key($toLabel);
        } catch (InvalidArgumentException $exception) {
            Log::info('[knowledge-graph] label not in GraphSchema whitelist, using SQL', [
                'edge' => $edge->key,
                'from_label' => $fromLabel,
                'to_label' => $toLabel,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        $startIds = $this->resolveGraphStartIds($node, $fromLabel, $context);

        if ($startIds === null) {
            return null; // bridge lookup itself failed — let the caller fall back to SQL.
        }

        if ($startIds === []) {
            return []; // genuinely nothing to start from yet (e.g. person has no enrolment) — not a failure.
        }

        // Labels, relationship types and key property names come from the ontology and
        // GraphSchema, never from user input, so they are safe to interpolate. Values
        // stay parameterised.
        $cypher = sprintf(
            'MATCH (a:%s) WHERE a.%s IN $startIds '
            . 'MATCH (a)-[:%s]->(b:%s) '
            . 'WHERE ($tenantId IS NULL OR b.sub_institute_id IS NULL OR b.sub_institute_id = $tenantId) '
            . 'RETURN DISTINCT b LIMIT %d',
            $fromLabel,
            $fromKey,
            $edge->graphRelationshipType,
            $toLabel,
            $spec->limitPerHop
        );

        try {
            $result = $this->neo4j->run($cypher, [
                'startIds' => $startIds,
                'tenantId' => $context->selectedInstituteId,
            ]);
        } catch (Throwable $exception) {
            Log::info('[knowledge-graph] graph traversal unavailable, using SQL', [
                'edge' => $edge->key,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        if ($result === null) {
            return null;
        }

        $nodes = [];

        foreach ($result as $record) {
            $properties = $this->extractGraphProperties($record);

            if ($properties === []) {
                continue;
            }

            $nodes[] = new GraphNode(
                entityKey: $targetEntity->key,
                id: $properties[$toKey] ?? null,
                label: $this->labelFor($properties, $targetEntity),
                attributes: $this->attributesFor($properties, $targetEntity),
                viaRelation: $edge->relation,
                depth: $depth,
                source: 'graph',
            );
        }

        return $nodes;
    }

    /**
     * The real graph-side starting id(s) to walk an edge from.
     *
     * For almost every entity this is just `[$node->id]` — the ontology key and
     * the Neo4j label name the same real-world row. The `student` entity does
     * not: its SQL identity (`tblstudent`, the PERSON) and its Neo4j identity
     * for every outgoing edge (`:Student`, one node per ENROLMENT, per
     * `StudentGraphProjection`) are two different real nodes connected by a
     * genuine edge, `(:StuDetail {sdId})-[:HAS_STUDENT]->(:Student {stuId})` —
     * not a GraphSchema::siblingOf() case (that mechanism is for :Staff/:Teacher,
     * the SAME row double-keyed under two conventions; this is two different
     * rows). Without this bridge, `graphLabelFor('student')` naively produces
     * `Student` and tries to MATCH it directly on the PERSON's id, which no
     * `:Student` node carries, so every graph-rooted edge from a real person id
     * silently returned zero rows — indistinguishable from "not synced yet",
     * which is why it went unnoticed.
     *
     * A person can hold more than one enrolment (transfer, re-admission), so
     * this fans out to all of them, matching the many_to_many cardinality
     * `enrolled_in_standard` already declares.
     *
     * `fee_student` shares this bridge (via `FROM_LABEL_OVERRIDE` resolving
     * its `ENROLLED_IN` edge to `Student`, same as `student`) — it is the
     * identical real person/enrolment table pair, just a different ontology
     * entity for the Fees UI context.
     *
     * @return array<int>|null  null only when the bridge query itself failed
     *                          (caller falls back to SQL); [] means the person
     *                          genuinely has no live enrolment yet.
     */
    private function resolveGraphStartIds(GraphNode $node, string $fromLabel, McpRequestContext $context): ?array
    {
        if (! in_array($node->entityKey, ['student', 'fee_student'], true) || $fromLabel !== 'Student' || $node->id === null) {
            return [is_numeric($node->id) ? (int) $node->id : $node->id];
        }

        $personKey = GraphSchema::key('StuDetail');
        $tenantId = $context->selectedInstituteId;

        try {
            $result = $this->neo4j->run(
                "MATCH (p:StuDetail {{$personKey}: \$id}) "
                . 'WHERE ($tenantId IS NULL OR p.sub_institute_id IS NULL OR p.sub_institute_id = $tenantId) '
                . 'MATCH (p)-[:HAS_STUDENT]->(s:Student) '
                . 'WHERE ($tenantId IS NULL OR s.sub_institute_id IS NULL OR s.sub_institute_id = $tenantId) '
                . 'RETURN DISTINCT s.stuId AS stuId',
                ['id' => (int) $node->id, 'tenantId' => $tenantId]
            );
        } catch (Throwable $exception) {
            Log::info('[knowledge-graph] StuDetail->Student bridge unavailable, using SQL', [
                'person_id' => $node->id,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        if ($result === null) {
            return null;
        }

        $ids = [];

        foreach ($result as $record) {
            $stuId = $record->get('stuId');

            if ($stuId !== null) {
                $ids[] = (int) $stuId;
            }
        }

        return $ids;
    }

    private function extractGraphProperties(mixed $record): array
    {
        try {
            $value = is_object($record) && method_exists($record, 'get') ? $record->get('b') : $record;

            if (is_object($value) && method_exists($value, 'getProperties')) {
                $properties = $value->getProperties();

                return method_exists($properties, 'toArray') ? $properties->toArray() : (array) $properties;
            }

            return is_array($value) ? $value : [];
        } catch (Throwable) {
            return [];
        }
    }

    /** Neo4j labels are PascalCase; ontology keys are snake_case. */
    private function graphLabelFor(string $entityKey): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $entityKey)));
    }

    /**
     * The value on the source side of the edge. For most edges that is the node's
     * own id; for many-to-one edges it is a foreign key held on the node's record,
     * which means we may need to re-read the row to get it.
     */
    private function resolveFromValue(
        GraphNode $node,
        RelationshipDefinition $edge,
        McpRequestContext $context
    ): mixed {
        $sourceEntity = $this->registry->entity($node->entityKey, $context->selectedInstituteId);

        if (! $sourceEntity) {
            return null;
        }

        $primaryKey = $sourceEntity->primaryKeyColumn ?: 'id';

        if ($edge->fromColumn === null || $edge->fromColumn === $primaryKey) {
            return $node->id;
        }

        // The traversal already carries the attribute in most cases.
        foreach ($node->attributes as $key => $value) {
            if ($key === $edge->fromColumn) {
                return $value;
            }
        }

        foreach ($sourceEntity->attributes as $attribute) {
            if (($attribute['column'] ?? null) === $edge->fromColumn) {
                $key = $attribute['key'] ?? null;
                if ($key !== null && array_key_exists($key, $node->attributes)) {
                    return $node->attributes[$key];
                }
            }
        }

        if (! $sourceEntity->sourceTable
            || ! Schema::hasTable($sourceEntity->sourceTable)
            || ! Schema::hasColumn($sourceEntity->sourceTable, $edge->fromColumn)) {
            return null;
        }

        $query = DB::table($sourceEntity->sourceTable)->where($primaryKey, $node->id);
        $this->applyScope($query, $sourceEntity, $context);

        return $query->value($edge->fromColumn);
    }

    private function loadNode(
        EntityDefinition $entity,
        int|string $id,
        McpRequestContext $context
    ): ?GraphNode {
        if (! $entity->sourceTable || ! Schema::hasTable($entity->sourceTable)) {
            return null;
        }

        $primaryKey = $entity->primaryKeyColumn ?: 'id';

        $query = DB::table($entity->sourceTable)->where($primaryKey, $id);
        $this->applyScope($query, $entity, $context);

        $row = $query->first();

        if (! $row) {
            return null;
        }

        $data = (array) $row;

        return new GraphNode(
            entityKey: $entity->key,
            id: $data[$primaryKey] ?? null,
            label: $this->labelFor($data, $entity),
            attributes: $this->attributesFor($data, $entity),
            depth: 0,
            source: 'sql',
        );
    }

    /**
     * The tenant filter, applied identically on every hop.
     *
     * Entities whose own table has no tenant column (lms_online_exam,
     * pal_learning_evidence) are marked un-scoped in the ontology and reached only
     * through an already-scoped parent — the student row was filtered before we got here.
     */
    private function applyScope($query, EntityDefinition $entity, McpRequestContext $context): void
    {
        if (! $entity->isTenantScoped) {
            return;
        }

        $table = $entity->sourceTable;

        if ($entity->clientColumn && Schema::hasColumn($table, $entity->clientColumn)) {
            if ($context->clientId === null) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->where($table . '.' . $entity->clientColumn, $context->clientId);

            if ($entity->tenantColumn && Schema::hasColumn($table, $entity->tenantColumn)) {
                $query->where($table . '.' . $entity->tenantColumn, $context->selectedInstituteId);
            }

            return;
        }

        if (! $entity->tenantColumn || ! Schema::hasColumn($table, $entity->tenantColumn)) {
            $query->whereRaw('1 = 0');

            return;
        }

        $allowed = $context->allowedInstituteIds !== []
            ? $context->allowedInstituteIds
            : [$context->selectedInstituteId];

        $query->where($table . '.' . $entity->tenantColumn, $context->selectedInstituteId)
            ->whereIn($table . '.' . $entity->tenantColumn, $allowed);

        if ($entity->academicYearColumn
            && $context->academicYear !== null
            && Schema::hasColumn($table, $entity->academicYearColumn)) {
            $query->where($table . '.' . $entity->academicYearColumn, $context->academicYear);
        }
    }

    private function labelFor(array $data, EntityDefinition $entity): string
    {
        $column = $entity->labelColumn;

        if ($column && ! str_contains($column, '(') && isset($data[$column])) {
            return (string) $data[$column];
        }

        // Composite labels such as CONCAT_WS(' ', first_name, last_name) are declared
        // as expressions; rebuild them from the columns we already selected.
        if ($column && str_contains($column, '(')) {
            preg_match_all('/[a-z_]+/i', $column, $matches);
            $parts = [];
            foreach ($matches[0] ?? [] as $candidate) {
                if (isset($data[$candidate]) && is_scalar($data[$candidate]) && $data[$candidate] !== '') {
                    $parts[] = (string) $data[$candidate];
                }
            }

            if ($parts !== []) {
                return implode(' ', $parts);
            }
        }

        foreach (['name', 'subject_name', 'chapter_name', 'title', 'SchoolName', 'client_name', 'display_name', 'displayLabel'] as $candidate) {
            if (! empty($data[$candidate])) {
                return (string) $data[$candidate];
            }
        }

        $primaryKey = $entity->primaryKeyColumn ?: 'id';

        return $entity->label . ' #' . ($data[$primaryKey] ?? '?');
    }

    private function attributesFor(array $data, EntityDefinition $entity): array
    {
        $attributes = [];

        foreach ($entity->attributes as $attribute) {
            $key = $attribute['key'] ?? null;
            $column = $attribute['column'] ?? null;

            if ($key && $column && array_key_exists($column, $data)) {
                $attributes[$key] = $data[$column];
            }
        }

        return $attributes;
    }

    private function emptyResult(): array
    {
        return [
            'start' => null,
            'nodes' => [],
            'edges' => [],
            'truncated' => false,
            'sources' => ['sql' => 0, 'graph' => 0],
        ];
    }
}
