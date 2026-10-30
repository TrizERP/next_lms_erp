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

    /**
     * REBUILT 2026-10-10. The previous query matched `(:Student)-[:ACHIEVED]
     * ->(:Result)` then `(:Result)-[:FOR_SUBJECT]->(:Subject)`,
     * `[:PART_OF_EXAM]->(:Exam)`, `[:DURING_YEAR]->(:AcademicYear)` and
     * `[:IN_STANDARD]->(:Standard)` — none of those five relationship types
     * are in App\Services\Graph\GraphSchema's live-sync whitelist, and none
     * exist anywhere in the graph (confirmed by direct Cypher count against
     * dev.triz.co.in:7688 on 2026-10-10: `MATCH ()-[r]->() WHERE type(r) IN
     * [...]` for all five returns zero). The very first MATCH required
     * `ACHIEVED`, so this endpoint always returned `{rootNode: null, nodes:
     * [], relationships: []}` for every student, with no error raised -
     * exactly the "always empty" finding this rebuild exists to fix.
     *
     * WHAT IS ACTUALLY LIVE (measured the same way, same day):
     *   (:Student)-[:HAS_RESULT]->(:Result)        517,827 edges
     *   (:Result)-[:FOR_ASSESSMENT]->(:Assessment) 148,186 edges (the ONLY
     *                                               outgoing edge type any
     *                                               :Result node carries)
     *   (:Assessment)-[:ASSESSES]->(:Concept)       16 edges (real, sparse)
     *   (:Assessment)-[:ASSESSES_CHAPTER]->(:Chapter) 4 edges (real, sparse -
     *                                               see StudentGraphController's
     *                                               own docblock on why this
     *                                               edge is write-gated)
     *   (:Subject)-[:HAS_ASSESSMENT]->(:Assessment) 13 edges (real, sparse,
     *                                               and note the DIRECTION:
     *                                               Subject -> Assessment,
     *                                               not the reverse the old
     *                                               query assumed)
     *
     * `:Exam` is not a label anything in this graph uses (the result-card
     * exam tables project to :Examination/:ExamType, a completely separate,
     * unconnected part of the graph - no edge joins that subsystem to
     * :Assessment/:Result at all). `:AcademicYear` is likewise never reached
     * from :Assessment or :Result. Neither is faked here: there is no real
     * edge to traverse, so none is written. What DOES carry the subject/
     * standard/year context every one of those broken edges was reaching for
     * is the :Assessment node's OWN properties (subject_id, standard_id,
     * sub_institute_id, syear, total_marks, paper_name, exam_type -
     * confirmed live on a sampled node) - already returned here with no
     * traversal needed, because `ass` itself is now reachable.
     *
     * Subject/Standard are ALSO opportunistically resolved by their uid
     * fallback key (`Standard:<sub_institute_id>:0:<standard_id>`,
     * `Subject:<sub_institute_id>:0:<subject_id>` - the same dual-key
     * convention GraphSchema::uidExpression() encodes, verified against live
     * sampled nodes of both labels) built from the Assessment's own
     * properties, since most live Standard/Subject nodes carry a uid rather
     * than their native key and the real HAS_ASSESSMENT edge barely exists.
     * An OPTIONAL MATCH on a uid built from a null property simply matches
     * nothing - it cannot throw or fabricate a node.
     */
    public function show($stuId)
    {
        // A student or parent may only open their own results; staff only their own school's students.
        if (! \App\Support\StudentAccess::allows($stuId)) {
            return response()->json(['success' => false, 'error' => 'Not allowed for this student.'], 403);
        }

        $client = $this->neo4jService->getClient();

        $result = $client->run(
            'MATCH (stu:Student {stuId: $stuId})
             WITH stu
             MATCH (stu)-[r1:HAS_RESULT]->(r:Result)
             WITH stu, r, r1
             ORDER BY r.id
             LIMIT 2
             WITH stu, collect(r) as results, collect(r1) as r1s
             UNWIND range(0, size(results)-1) as i
             WITH stu, results[i] as r, r1s[i] as r1
             OPTIONAL MATCH (r)-[r2:FOR_ASSESSMENT]->(ass:Assessment)
             OPTIONAL MATCH (ass)-[r3:ASSESSES]->(con:Concept)
             OPTIONAL MATCH (ass)-[r4:ASSESSES_CHAPTER]->(ch:Chapter)
             OPTIONAL MATCH (sub:Subject)-[r5:HAS_ASSESSMENT]->(ass)
             OPTIONAL MATCH (stdUid:Standard {uid:
                 CASE WHEN ass.sub_institute_id IS NOT NULL AND ass.standard_id IS NOT NULL
                      THEN \'Standard:\' + toString(ass.sub_institute_id) + \':0:\' + toString(ass.standard_id)
                      ELSE null END})
             OPTIONAL MATCH (subUid:Subject {uid:
                 CASE WHEN ass.sub_institute_id IS NOT NULL AND ass.subject_id IS NOT NULL
                      THEN \'Subject:\' + toString(ass.sub_institute_id) + \':0:\' + toString(ass.subject_id)
                      ELSE null END})
             RETURN stu, r, ass, con, ch, sub, stdUid, subUid, r1, r2, r3, r4, r5;',
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

            /* Node-valued columns */
            foreach (['r', 'ass', 'con', 'ch', 'sub', 'stdUid', 'subUid'] as $nodeKey) {
                if ($record->get($nodeKey)) {
                    $node = $this->formatNode($record->get($nodeKey));
                    $nodes[$node['id']] = $node;
                }
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
