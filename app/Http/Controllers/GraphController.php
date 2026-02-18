<?php

namespace App\Http\Controllers;

use App\Services\Neo4jService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
class GraphController extends Controller
{
    protected $neo4jService;

    public function __construct(Neo4jService $neo4jService)
    {
        $this->neo4jService = $neo4jService;
    }

    // Fetch LMS Content (Same as before)
    public function getGraphData(): JsonResponse
    {
        // Cypher query to fetch academic_section -> standard -> subject -> chapter relationships
        $query = '
            MATCH (section:AcademicSection)-[r1:OFFERS]->(standard:Standard)-[r2:OFFERS]->(subject:Subject)-[r3:OFFERS]->(chapter:Chapter)
            RETURN section, r1, standard, r2, subject, r3, chapter
            LIMIT 50';
        
        $result = $this->neo4jService->getClient()->run($query);
        //echo "<pre>";print_r($result);exit;
        return response()->json($result);exit;
        // Initialize arrays for nodes and edges
        $nodes = [];
        $edges = [];
        $nodeIds = [];

        // Process the result
        foreach ($result as $record) {
            $section = $record->get('section');
            $standard = $record->get('standard');
            $subject = $record->get('subject');
            $chapter = $record->get('chapter');

            $relationship1 = $record->get('r1');
            $relationship2 = $record->get('r2');
            $relationship3 = $record->get('r3');

            // Get the properties of each node
            $sectionProperties = $section->getProperties();
            $standardProperties = $standard->getProperties();
            $subjectProperties = $subject->getProperties();
            $chapterProperties = $chapter->getProperties();

            // Use Neo4j internal IDs for unique identification
            $sectionId = $section->getId();
            $standardId = $standard->getId();
            $subjectId = $subject->getId();
            $chapterId = $chapter->getId();

            // Add AcademicSection node
            if (!in_array($sectionId, $nodeIds)) {
                $sectionName = $sectionProperties['acedemic_section'] ?? 'Unknown Section';
                $nodes[] = [
                    'id' => $sectionId,
                    'label' => $sectionName,
                    'color' => [
                        'background' => '#FF5733',
                        'border' => '#333333'
                    ]
                ];
                $nodeIds[] = $sectionId;
            }

            // Add Standard node
            if (!in_array($standardId, $nodeIds)) {
                $standardName = $standardProperties['standard'] ?? 'Unknown Standard';
                $nodes[] = [
                    'id' => $standardId,
                    'label' => $standardName
                ];
                $nodeIds[] = $standardId;
            }
            if (!in_array($subjectId, $nodeIds)) {
                $subjectName = $subjectProperties['subject'] ?? 'Unknown Subject';
                $nodes[] = [
                    'id' => $subjectId,
                    'label' => $subjectName
                ];
                $nodeIds[] = $subjectId;
            }

            // Add Chapter node
            if (!in_array($chapterId, $nodeIds)) {
                $chapterName = $chapterProperties['chapter'] ?? 'Unknown Chapter';
                $nodes[] = [
                    'id' => $chapterId,
                    'label' => $chapterName
                ];
                $nodeIds[] = $chapterId;
            }

            // Add the edges (relationships)
            $edges[] = [
                'from' => $sectionId,
                'to' => $standardId,
                'label' => $relationship1->getType()
            ];
            $edges[] = [
                'from' => $standardId,
                'to' => $subjectId,
                'label' => $relationship2->getType()
            ];
            $edges[] = [
                'from' => $subjectId,
                'to' => $chapterId,
                'label' => $relationship3->getType()
            ];
        }

        // Return the nodes and edges as JSON response
        return response()->json([
            'nodes' => $nodes,
            'edges' => $edges
        ]);
    }

    public function getLearningPath(): JsonResponse
    {
        $studentId = 1;  // Example student

        // Cypher query to fetch the student's learning path
        $query = '
    MATCH (standard:Standard)-[:OFFERS]->(subject:Subject)-[:OFFERS]->(chapter:Chapter)
    WHERE standard.standard = "8"
    OPTIONAL MATCH (student)-[:HAS_COMPLETED]->(chapter)
    OPTIONAL MATCH (chapter)-[:REQUIRES]->(prerequisite:Chapter)
    RETURN standard, subject, chapter, prerequisite
    LIMIT 50
';

        $result = $this->neo4jService->getClient()->run($query, ['studentId' => $studentId]);

        // Initialize arrays for nodes and edges
        $nodes = [];
        $edges = [];
        $nodeIds = [];

        foreach ($result as $record) {
            //$student = $record->get('student');
            $standard = $record->get('standard');
            $subject = $record->get('subject');
            $chapter = $record->get('chapter');
            $prerequisite = $record->get('prerequisite');

            // Get the properties of each node
            //$studentProperties = $student->getProperties();
            $standardProperties = $standard->getProperties();
            $subjectProperties = $subject->getProperties();
            $chapterProperties = $chapter->getProperties();
            $prerequisiteProperties = ($prerequisite) ? $prerequisite->getProperties() : null;
            // Use Neo4j internal IDs for unique identification
            $studentId = $student->getId();
            $standardId = $standard->getId();
            $subjectId = $subject->getId();
            $chapterId = $chapter->getId();
            $prerequisiteId = ($prerequisite) ? $prerequisite->getId() : null;

            // Add Student node
            if (!in_array($studentId, $nodeIds)) {
                $studentName = 'Alice';
                $nodes[] = [
                    'id' => $studentId,
                    'label' => $studentName,
                    'color' => '#FFD700'  // Gold for student node
                ];
                $nodeIds[] = $studentId;
            }

            // Add Standard node (numeric standard label)
            if (!in_array($standardId, $nodeIds)) {
                $standardName = $standardProperties['standard'] ?? 'Unknown Standard';
                $nodes[] = [
                    'id' => $standardId,
                    'label' => 'Standard ' . $standardName,  // Use the numeric standard from LMS content
                    'color' => '#FFE333'  // Yellow for standard
                ];
                $nodeIds[] = $standardId;
            }

            // Add Subject node
            if (!in_array($subjectId, $nodeIds)) {
                $subjectName = $subjectProperties['subject'] ?? 'Unknown Subject';
                $nodes[] = [
                    'id' => $subjectId,
                    'label' => $subjectName,
                    'color' => '#33FF57'  // Green for subject
                ];
                $nodeIds[] = $subjectId;
            }

            // Add Chapter node (different color if completed)
            if (!in_array($chapterId, $nodeIds)) {
                $chapterName = $chapterProperties['chapter'] ?? 'Unknown Chapter';
                $color =  '#00FF00';  // Green if completed, Red if not completed
                $nodes[] = [
                    'id' => $chapterId,
                    'label' => $chapterName,
                    'color' => $color
                ];
                $nodeIds[] = $chapterId;
            }

            // Add Prerequisite node if it exists
            if ($prerequisite && !in_array($prerequisiteId, $nodeIds)) {
                $prerequisiteName = $prerequisiteProperties['chapter'] ?? 'Unknown Chapter';
                $nodes[] = [
                    'id' => $prerequisiteId,
                    'label' => $prerequisiteName,
                    'color' => '#CCCCFF'  // Blue for prerequisite
                ];
                $nodeIds[] = $prerequisiteId;
            }

            // Add the edges (relationships)
            $edges[] = [
                'from' => $studentId,
                'to' => $standardId,
                'label' => 'ENROLLED_IN'
            ];
            $edges[] = [
                'from' => $standardId,
                'to' => $subjectId,
                'label' => 'OFFERS'
            ];
            $edges[] = [
                'from' => $subjectId,
                'to' => $chapterId,
                'label' => 'OFFERS'
            ];

            if ($prerequisite) {
                $edges[] = [
                    'from' => $prerequisiteId,
                    'to' => $chapterId,
                    'label' => 'REQUIRES'
                ];
            }
        }

        return response()->json([
            'nodes' => $nodes,
            'edges' => $edges
        ]);
    }
}