<?php

namespace App\Http\Controllers;

use App\Models\LmsDataContentNeo4j;
use App\Services\Neo4jService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class Neo4jSyncController extends Controller
{
    protected $neo4jService;

    public function __construct(Neo4jService $neo4jService)
    {
        $this->neo4jService = $neo4jService;
    }

    public function sync(): JsonResponse
    {
        // Fetched data from MySQL
        $data = LmsDataContentNeo4j::all();
        Log::info($data);
        foreach ($data as $item) {
            // Created academic_section node
            $query = 'MERGE (section:AcademicSection {acedemic_section: $acedemic_section}) RETURN section';
            $this->neo4jService->getClient()->run($query, [
                'acedemic_section' => $item->acedemic_section,
            ]);

            // Created standard node
            $query = 'MERGE (standard:Standard {standard: $standard}) RETURN standard';
            $this->neo4jService->getClient()->run($query, [
                'standard' => $item->standard,
            ]);

            // Created subject node
            $query = 'MERGE (subject:Subject {subject: $subject}) RETURN subject';
            $this->neo4jService->getClient()->run($query, [
                'subject' => $item->subject,
            ]);

            // Created chapter node
            $query = 'MERGE (chapter:Chapter {chapter: $chapter}) RETURN chapter';
            $this->neo4jService->getClient()->run($query, [
                'chapter' => $item->chapter,
            ]);

            // Created relationships: academic_section -> standard -> subject -> chapter
            $query = '
                MATCH (section:AcademicSection {acedemic_section: $acedemic_section}),
                      (standard:Standard {standard: $standard})
                MERGE (section)-[:OFFERS]->(standard)';
            $this->neo4jService->getClient()->run($query, [
                'acedemic_section' => $item->acedemic_section,
                'standard'         => $item->standard,
            ]);

            $query = '
                MATCH (standard:Standard {standard: $standard}),
                      (subject:Subject {subject: $subject})
                MERGE (standard)-[:OFFERS]->(subject)';
            $this->neo4jService->getClient()->run($query, [
                'standard' => $item->standard,
                'subject'  => $item->subject,
            ]);

            $query = '
                MATCH (subject:Subject {subject: $subject}),
                      (chapter:Chapter {chapter: $chapter})
                MERGE (subject)-[:OFFERS]->(chapter)';
            $this->neo4jService->getClient()->run($query, [
                'subject' => $item->subject,
                'chapter' => $item->chapter,
            ]);
        }

        return response()->json(['status' => 'Data synced to Neo4j']);
    }
}
