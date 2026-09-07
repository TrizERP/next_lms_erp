<?php

namespace App\Http\Controllers;

use App\Services\Neo4jService;
use Illuminate\Http\JsonResponse;

/**
 * The two read endpoints behind /graph-data and /graph-data-learning-path, which the
 * welcomenew and newD3recommendnew views fetch to draw their D3 graphs.
 *
 * REWRITTEN 2026-09-04 against the schema the graph actually has. Both queries were
 * written for the pre-migration graph — they matched `[:OFFERS]` edges and read
 * `standard.standard` / `subject.subject` / `chapter.chapter` properties. That graph was
 * deleted in 2026-08-10's rebuild, so both returned an empty payload even once the routes
 * pointed here. The live curriculum spine is
 *
 *     (:Standard)-[:HAS_SUBJECT]->(:Subject)-[:HAS_CHAPTER]->(:Chapter)-[:HAS_CONCEPT]->(:Concept)
 *
 * and every node carries `displayLabel`, so the label falls back through
 * displayLabel -> name -> title rather than a property that no longer exists.
 *
 * `getLearningPath()` additionally read an undefined `$student` variable, which was a
 * fatal error on every call regardless of the data; that block is gone.
 */
class GraphController extends Controller
{
    protected $neo4jService;

    public function __construct(Neo4jService $neo4jService)
    {
        $this->neo4jService = $neo4jService;
    }

    /** Standard -> Subject -> Chapter, the top of the curriculum spine. */
    public function getGraphData(): JsonResponse
    {
        $query = '
            MATCH (st:Standard)-[r1:HAS_SUBJECT]->(sub:Subject)-[r2:HAS_CHAPTER]->(ch:Chapter)
            RETURN st, r1, sub, r2, ch
            LIMIT 50';

        $nodes = [];
        $edges = [];
        $seen  = [];

        foreach ($this->neo4jService->getClient()->run($query) as $record) {
            $st  = $record->get('st');
            $sub = $record->get('sub');
            $ch  = $record->get('ch');

            $this->addNode($nodes, $seen, $st,  '#FF5733');
            $this->addNode($nodes, $seen, $sub, '#33FF57');
            $this->addNode($nodes, $seen, $ch,  '#3357FF');

            $edges[] = ['from' => $st->getId(),  'to' => $sub->getId(), 'label' => $record->get('r1')->getType()];
            $edges[] = ['from' => $sub->getId(), 'to' => $ch->getId(),  'label' => $record->get('r2')->getType()];
        }

        return response()->json(['nodes' => $nodes, 'edges' => $edges]);
    }

    /**
     * A chapter's concepts and the prerequisites between them — the closure a SQL join
     * cannot express, which is the reason this path is in the graph at all.
     */
    public function getLearningPath(): JsonResponse
    {
        $query = '
            MATCH (ch:Chapter)-[r1:HAS_CONCEPT]->(con:Concept)
            OPTIONAL MATCH (pre:Concept)-[r2:PREREQUISITE_OF]->(con)
            RETURN ch, r1, con, pre
            LIMIT 50';

        $nodes = [];
        $edges = [];
        $seen  = [];

        foreach ($this->neo4jService->getClient()->run($query) as $record) {
            $ch  = $record->get('ch');
            $con = $record->get('con');
            $pre = $record->get('pre');

            $this->addNode($nodes, $seen, $ch,  '#3357FF');
            $this->addNode($nodes, $seen, $con, '#00FF00');

            $edges[] = ['from' => $ch->getId(), 'to' => $con->getId(), 'label' => $record->get('r1')->getType()];

            if ($pre !== null) {
                $this->addNode($nodes, $seen, $pre, '#CCCCFF');
                $edges[] = ['from' => $pre->getId(), 'to' => $con->getId(), 'label' => 'PREREQUISITE_OF'];
            }
        }

        return response()->json(['nodes' => $nodes, 'edges' => $edges]);
    }

    /**
     * Add a node once. The label falls back displayLabel -> name -> title -> chapter_name:
     * the reference ingest sets displayLabel on everything it creates, but the uid-keyed
     * nodes loaded by the batch pipeline do not all have one.
     */
    private function addNode(array &$nodes, array &$seen, $node, string $colour): void
    {
        $id = $node->getId();
        if (isset($seen[$id])) {
            return;
        }
        $seen[$id] = true;

        $props = $node->getProperties();
        $label = $props['displayLabel']
            ?? $props['name']
            ?? $props['title']
            ?? $props['chapter_name']
            ?? $props['display_name']
            ?? ('#' . $id);

        $nodes[] = [
            'id'    => $id,
            'label' => (string) $label,
            'color' => ['background' => $colour, 'border' => '#333333'],
        ];
    }
}
