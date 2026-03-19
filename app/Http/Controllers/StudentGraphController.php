<?php

namespace App\Http\Controllers;

use App\Services\Neo4jService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class StudentGraphController extends Controller
{
    protected $neo4jService;

    public function __construct(Neo4jService $neo4jService)
    {
        $this->neo4jService = $neo4jService;
    }

    // public function show(Request $request): JsonResponse
    // {
    //     try {
    //         $client = $this->neo4jService->getClient();

    //         // ✅ Mandatory params
    //         $studentId = (int) $request->get('student_id');
    //         $subInstituteId = (int) $request->get('sub_institute_id');
    //         $year = (int) $request->get('syear');

    //         // ❌ validation (important)
    //         if (!$studentId || !$subInstituteId || !$year) {
    //             return response()->json([
    //                 'error' => 'student_id, sub_institute_id and year are required'
    //             ], 400);
    //         }

    //         // ✅ Optional params
    //         $subjectId = $request->get('subject_id');
    //         $standardId = $request->get('standard_id');
    //         $chapterId = $request->get('chapter_id');

    //         // 🔥 Query - match with year (year is required param so include it)
    //         $query = '
    //         MATCH (sd:StuDetail {student_id: $studentId, sub_institute_id: $subInstituteId, year: $year})
    //         OPTIONAL MATCH (sd)-[:HAS_STUDENT]->(stu:Student)
    //         OPTIONAL MATCH (stu)-[r1:ENROLLED_IN]->(st:Standard)
    //         OPTIONAL MATCH (st)-[r2:HAS_SUBJECT]->(sub:Subject)
    //         OPTIONAL MATCH (sub)-[r3:HAS_CHAPTER]->(ch:Chapter)

    //         OPTIONAL MATCH (stu)-[r4:MASTERS]->(ch)
    //         WHERE r4.proficiency_score IS NOT NULL

    //         OPTIONAL MATCH (ass:Assessment)-[r5:ASSESSES_CHAPTER]->(ch)
    //         OPTIONAL MATCH (stu)-[r6:HAS_RESULT]->(r:Result)
    //         OPTIONAL MATCH (r)-[r7:FOR_ASSESSMENT]->(ass)

    //         OPTIONAL MATCH (ass)-[r8:HAS_QUESTION]->(q:Question)-[r9:BELONGS_TO]->(ch)

    //         WITH sd, stu, st, sub, ch, ass, r, q,
    //              r1, r2, r3, r4, r5, r6, r7, r8, r9
    //         ';

    //         // 🔥 Dynamic filters
    //         $filters = [];

    //         if ($standardId) {
    //             $filters[] = 'st.standard_id = $standardId';
    //         }

    //         if ($subjectId) {
    //             $filters[] = 'sub.subject_id = $subjectId';
    //         }

    //         if ($chapterId) {
    //             $filters[] = 'ch.chId = $chapterId';
    //         }

    //         if (!empty($filters)) {
    //             $query .= ' WHERE ' . implode(' AND ', $filters);
    //         }

    //         // 🔥 Return
    //         $query .= '
    //         RETURN sd, stu, st, sub, ch, ass, r, q,
    //                r1, r2, r3, r4, r5, r6, r7, r8, r9
    //         ';

    //         // ✅ Params bind
    //         $params = [
    //             'studentId' => $studentId,
    //             'subInstituteId' => $subInstituteId,
    //             'year' => $year,
    //             'standardId' => $standardId ? (int)$standardId : null,
    //             'subjectId' => $subjectId ? (int)$subjectId : null,
    //             'chapterId' => $chapterId ? (int)$chapterId : null,
    //         ];

    //         $result = $client->run($query, $params);

    //         $nodes = [];
    //         $relationships = [];
    //         $rootNode = null;

    //         foreach ($result as $record) {

    //             if ($record->get('sd') && !$rootNode) {
    //                 $rootNode = $this->formatNode($record->get('sd'));
    //                 $nodes[$rootNode['id']] = $rootNode;
    //             }

    //             foreach (['stu','st','sub','ch','ass','r','q'] as $nodeKey) {
    //                 if ($record->get($nodeKey)) {
    //                     $node = $this->formatNode($record->get($nodeKey));
    //                     $nodes[$node['id']] = $node;
    //                 }
    //             }

    //             foreach (['r1','r2','r3','r4','r5','r6','r7','r8','r9'] as $relKey) {
    //                 if ($record->get($relKey)) {
    //                     $rel = $record->get($relKey);
    //                     $relationships[$rel->getId()] = $this->formatRelationship($rel);
    //                 }
    //             }
    //         }

    //         // If no results, try without year constraint
    //         if (!$rootNode) {
    //             return response()->json([
    //                 'searched_params' => ['student_id' => $studentId, 'sub_institute_id' => $subInstituteId],
    //                 'rootNode' => null,
    //                 'nodes' => [],
    //                 'relationships' => []
    //             ]);
    //         }

    //         $result = $client->run($query, $params);

    //         $nodes = [];
    //         $relationships = [];
    //         $rootNode = null;

    //         foreach ($result as $record) {

    //             if ($record->get('sd') && !$rootNode) {
    //                 $rootNode = $this->formatNode($record->get('sd'));
    //                 $nodes[$rootNode['id']] = $rootNode;
    //             }

    //             foreach (['stu','st','sub','ch','ass','r','q'] as $nodeKey) {
    //                 if ($record->get($nodeKey)) {
    //                     $node = $this->formatNode($record->get($nodeKey));
    //                     $nodes[$node['id']] = $node;
    //                 }
    //             }

    //             foreach (['r1','r2','r3','r4','r5','r6','r7','r8','r9'] as $relKey) {
    //                 if ($record->get($relKey)) {
    //                     $rel = $record->get($relKey);
    //                     $relationships[$rel->getId()] = $this->formatRelationship($rel);
    //                 }
    //             }
    //         }

    //         return response()->json([
    //             'debug' => [
    //                 'student_found' => $foundStudent !== null,
    //                 'has_year_property' => $hasYear,
    //                 'node_properties' => $foundStudent
    //             ],
    //             'rootNode' => $rootNode,
    //             'nodes' => array_values($nodes),
    //             'relationships' => array_values($relationships)
    //         ]);
    //     } catch (\Exception $e) {
    //         Log::error('StudentGraphController Error: ' . $e->getMessage(), [
    //             'student_id' => $studentId ?? null,
    //             'sub_institute_id' => $subInstituteId ?? null,
    //             'syear' => $year ?? null,
    //             'trace' => $e->getTraceAsString()
    //         ]);
    //         return response()->json([
    //             'error' => 'Failed to fetch student details',
    //             'message' => $e->getMessage()
    //         ], 500);
    //     }
    // }

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

    /**
     * Get student assessment data with scores and levels
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getStudentAssessment(Request $request): JsonResponse
    {
        try {
            $client = $this->neo4jService->getClient();

            // Required parameters
            $studentId = (int) $request->get('student_id');
            $subInstituteId = (int) $request->get('sub_institute_id');
            $year = (int) $request->get('syear', 2024); // Default to 2024

            // Validation
            if (!$studentId || !$subInstituteId) {
                return response()->json([
                    'success' => false,
                    'error' => 'student_id and sub_institute_id are required'
                ], 400);
            }

            // Cypher query converted from the user's request
            $query = '
            MATCH (sd:StuDetail {student_id: $studentId, sub_institute_id: $subInstituteId})
            -[:HAS_STUDENT]->(stu:Student)

            /* Standard → Subject → Chapter */
            MATCH (stu)-[:ENROLLED_IN]->(st:Standard)
            MATCH (st)-[:HAS_SUBJECT]->(sub:Subject)
            MATCH (sub)-[:HAS_CHAPTER]->(ch:Chapter)

            /* Mastery */
            MATCH (stu)-[m:MASTERS]->(ch)
            WHERE m.proficiency_score IS NOT NULL

            /* Result → Assessment (filtered by year) */
            MATCH (stu)-[:HAS_RESULT]->(r:Result)
            MATCH (r)-[:FOR_ASSESSMENT]->(ass:Assessment)
            WHERE stu.syear = $year

            OPTIONAL MATCH (ass)-[:ASSESSES_CHAPTER]->(ch)

            /* Aggregation */
            WITH sd, stu, st, sub, ch, ass,
                 MAX(r.obtain_marks) AS obtained_marks,
                 COALESCE(ass.total_marks, 0) AS total_marks

            /* Percentage */
            WITH sd, stu, st, sub, ch, ass,
                 obtained_marks,
                 total_marks,
                 CASE 
                    WHEN total_marks > 0 
                    THEN (obtained_marks * 1.0 / total_marks) * 100
                    ELSE 0
                 END AS percentage

            /* Level */
            WITH sd, stu, st, sub, ch, ass,
                 total_marks,
                 obtained_marks,
                 percentage,
                 CASE 
                    WHEN percentage < 40 THEN "easy"
                    WHEN percentage < 70 THEN "medium"
                    ELSE "hard"
                 END AS student_level

            RETURN
              stu.student_id                     AS student_id,
              sd.first_name + " " + sd.last_name AS student_name,
              stu.syear                          AS syear,
              st.standard_id                     AS standard_id,
              st.name                            AS standard_name,
              sub.subject_id                     AS subject_id,
              sub.display_name                   AS subject_name,
              ass.assId                          AS assessment_id,
              ass.paper_name                     AS assessment_name,
              COALESCE(ass.question_ids, [])    AS question_ids,
              ch.chId                            AS chapter_id,
              ch.chapter_name                    AS chapter_name,
              total_marks                        AS assessment_total,
              obtained_marks                     AS obtained_marks,
              ROUND(percentage,2)                AS student_average_score,
              student_level                      AS student_level

            ORDER BY sub.display_name, ch.chapter_name
            ';

            $params = [
                'studentId' => $studentId,
                'subInstituteId' => $subInstituteId,
                'year' => $year
            ];

            $result = $client->run($query, $params);

            $data = [];
            foreach ($result as $record) {
                $data[] = [
                    'student_id' => $record->get('student_id'),
                    'student_name' => $record->get('student_name'),
                    'syear' => $record->get('syear'),
                    'standard_id' => $record->get('standard_id'),
                    'standard_name' => $record->get('standard_name'),
                    'subject_id' => $record->get('subject_id'),
                    'subject_name' => $record->get('subject_name'),
                    'assessment_id' => $record->get('assessment_id'),
                    'assessment_name' => $record->get('assessment_name'),
                    'question_ids' => $record->get('question_ids') ?? [],
                    'chapter_id' => $record->get('chapter_id'),
                    'chapter_name' => $record->get('chapter_name'),
                    'assessment_total' => $record->get('assessment_total'),
                    'obtained_marks' => $record->get('obtained_marks'),
                    'student_average_score' => $record->get('student_average_score'),
                    'student_level' => $record->get('student_level')
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'count' => count($data),
                'filters' => [
                    'student_id' => $studentId,
                    'sub_institute_id' => $subInstituteId,
                    'syear' => $year
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('StudentAssessment API Error: ' . $e->getMessage(), [
                'student_id' => $request->get('student_id'),
                'sub_institute_id' => $request->get('sub_institute_id'),
                'syear' => $request->get('syear'),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch student assessment data',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
