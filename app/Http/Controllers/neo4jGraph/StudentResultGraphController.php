<?php

namespace App\Http\Controllers\neo4jGraph;

use App\Http\Controllers\Controller;
use App\Services\Neo4jService;
use Illuminate\Http\JsonResponse;

class StudentResultGraphController extends Controller
{
    protected $neo4jService;

    public function __construct(Neo4jService $neo4jService)
    {
        $this->neo4jService = $neo4jService;
    }

    public function show($stuId)
    {
        $client = $this->neo4jService->getClient();

        $result = $client->run(
            'MATCH (stu:Student {stuId: $stuId})
             OPTIONAL MATCH (stu)-[r1:ACHIEVED]->(r:Result)
             OPTIONAL MATCH (r)-[r2:FOR_SUBJECT]->(sub:Subject)
             OPTIONAL MATCH (r)-[r3:PART_OF_EXAM]->(ex:Exam)
             OPTIONAL MATCH (r)-[r4:DURING_YEAR]->(y:AcademicYear)
             OPTIONAL MATCH (r)-[r5:IN_STANDARD]->(st:Standard)
             RETURN stu, r, sub, ex, y, st, r1, r2, r3, r4, r5;',
            ['stuId' => is_numeric($stuId) ? (int)$stuId : $stuId]
        );

        $nodes = [];
        $relationships = [];
        $rootNode = null;

        foreach ($result as $record) {
            /* Root Student */
            if ($record->get('stu') && !$rootNode) {
                $rootNode = $this->formatNode($record->get('stu'));
                $nodes[$rootNode['id']] = $rootNode;
            }

            /* Result node */
            if ($record->get('r')) {
                $node = $this->formatNode($record->get('r'));
                $nodes[$node['id']] = $node;
            }

            /* Subject node */
            if ($record->get('sub')) {
                $node = $this->formatNode($record->get('sub'));
                $nodes[$node['id']] = $node;
            }

            /* Exam node */
            if ($record->get('ex')) {
                $node = $this->formatNode($record->get('ex'));
                $nodes[$node['id']] = $node;
            }

            /* AcademicYear node */
            if ($record->get('y')) {
                $node = $this->formatNode($record->get('y'));
                $nodes[$node['id']] = $node;
            }

            /* Standard node */
            if ($record->get('st')) {
                $node = $this->formatNode($record->get('st'));
                $nodes[$node['id']] = $node;
            }

            /* Relationships */
            foreach (['r1', 'r2', 'r3', 'r4', 'r5'] as $relKey) {
                if ($record->get($relKey)) {
                    $rel = $record->get($relKey);
                    $relationships[$rel->getId()] = $this->formatRelationship($rel);
                }
            }
        }

        return response()->json([
            'rootNode' => $rootNode,
            'nodes' => array_values($nodes),
            'relationships' => array_values($relationships)
        ]);
    }

    private function formatNode($node)
    {
        return [
            'id' => $node->getId(),
            'labels' => $node->getLabels(),
            'properties' => $node->getProperties()
        ];
    }

    private function formatRelationship($rel)
    {
        return [
            'id' => $rel->getId(),
            'type' => $rel->getType(),
            'startNode' => $rel->getStartNodeId(),
            'endNode' => $rel->getEndNodeId(),
            'properties' => $rel->getProperties()
        ];
    }
}
