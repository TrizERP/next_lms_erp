<?php

namespace App\Services\PAL\ULU;

use App\Models\PAL\UnifiedLearningUnit;
use App\Services\Neo4jService;
use Illuminate\Support\Facades\Log;

class ULUGraphService
{
    public function __construct(
        private readonly ?Neo4jService $neo4j = null
    ) {
    }

    public function sync(UnifiedLearningUnit $ulu): array
    {
        if ($this->neo4j === null) {
            return ['synced' => false, 'reason' => 'neo4j_service_unavailable'];
        }

        try {
            $query = <<<'CYPHER'
MERGE (ulu:LearningUnit {ulu_id: $ulu_id})
SET ulu.title = $title,
    ulu.grade = $grade,
    ulu.duration_minutes = $duration_minutes,
    ulu.complexity = $difficulty,
    ulu.h5p_type = $h5p_type,
    ulu.cultural_context = $cultural_context,
    ulu.quality_status = $quality_status
WITH ulu
MERGE (concept:Concept {id: $concept_id})
MERGE (ulu)-[:TEACHES {primary: true}]->(concept)
WITH ulu
MERGE (casel:CASELCompetency {id: $casel_domain})
MERGE (ulu)-[:DEVELOPS]->(casel)
WITH ulu
MERGE (ngss:NGSSPractice {id: $ngss_practice})
MERGE (ulu)-[:EXERCISES]->(ngss)
WITH ulu
MERGE (career:CareerCluster {id: $career_cluster})
MERGE (ulu)-[:SIGNALS_CAREER]->(career)
WITH ulu
MERGE (ncdg:NCDGGoal {id: $ncdg_goal})
MERGE (ulu)-[:EVIDENCES]->(ncdg)
RETURN ulu.ulu_id AS ulu_id
CYPHER;

            $this->neo4j->run($query, [
                'ulu_id' => $ulu->ulu_id,
                'title' => $ulu->title,
                'grade' => $ulu->grade,
                'duration_minutes' => $ulu->duration_minutes,
                'difficulty' => $ulu->difficulty,
                'h5p_type' => $ulu->h5p_type,
                'cultural_context' => $ulu->cultural_context,
                'quality_status' => $ulu->status,
                'concept_id' => (string) data_get($ulu->academic_core, 'concept_id', $ulu->academic_concept),
                'casel_domain' => (string) $ulu->casel_domain,
                'ngss_practice' => (string) $ulu->ngss_practice,
                'career_cluster' => (string) $ulu->career_cluster,
                'ncdg_goal' => (string) $ulu->ncdg_goal,
            ]);

            return ['synced' => true];
        } catch (\Throwable $e) {
            Log::warning('ULU Neo4j sync failed', [
                'ulu_id' => $ulu->ulu_id,
                'error' => $e->getMessage(),
            ]);

            return ['synced' => false, 'reason' => $e->getMessage()];
        }
    }
}
