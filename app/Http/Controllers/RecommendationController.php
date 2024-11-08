<?php

namespace App\Http\Controllers;

use App\Services\Neo4jService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class RecommendationController extends Controller
{
    protected $neo4jService;

    public function __construct(Neo4jService $neo4jService)
    {
        $this->neo4jService = $neo4jService;
    }

    // This function will get the recommendations for the student
    public function getRecommendations(): JsonResponse
    {
        // For the sake of the example, assume we are testing with student ID 1 (Alice)
        $studentId = 1;

        // Cypher query to fetch the next chapters for the student
        $query = '
            MATCH (student:Student {id: $studentId})-[:HAS_COMPLETED]->(completed:Chapter)
            MATCH (next:Chapter)-[:REQUIRES]->(completed)
            WHERE NOT (student)-[:HAS_COMPLETED]->(next)
            RETURN next
            ORDER BY next.difficulty ASC, next.popularity DESC
            LIMIT 5
        ';

        // Run the query and pass the studentId as a parameter
        $result = $this->neo4jService->getClient()->run($query, ['studentId' => $studentId]);

        // Initialize array for recommendations
        $recommendations = [];

        // Process the result
        foreach ($result as $record) {
            $chapter = $record->get('next');  // Get the recommended chapter node

            // Get the properties of the recommended chapter
            $chapterProperties = $chapter->getProperties();

            // Add the recommended chapter to the response
            $recommendations[] = [
                'id' => $chapter->getId(),
                'label' => $chapterProperties['chapter'] ?? 'Unknown Chapter',
                'difficulty' => $chapterProperties['difficulty'] ?? 'Unknown',
                'popularity' => $chapterProperties['popularity'] ?? 0
            ];
        }

        // Return the recommendations as a JSON response
        return response()->json($recommendations);
    }
}
