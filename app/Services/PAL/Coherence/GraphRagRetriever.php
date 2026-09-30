<?php

namespace App\Services\PAL\Coherence;

/**
 * Pure retrieval for Graph RAG — assembles the graph context a concept
 * explanation needs (root blockers, assessing questions, teaching content,
 * misconceptions) from data CoherenceGraphProjection already writes nightly.
 * No LLM call anywhere in this class: retrieval and generation stay two
 * separate steps, so a caller can inspect what was retrieved independently
 * of whether generation succeeds, and a graph outage surfaces as a normal
 * exception here rather than silently becoming a hallucinated answer
 * upstream.
 *
 * Every method this composes already exists and is already proven against
 * live data (rootBlockers() — Phase 3/4, contentFor()/questionsFor() —
 * pre-existing, misconceptionsFor() — Phase 2). This class adds no new
 * Cypher of its own.
 */
class GraphRagRetriever
{
    public function __construct(private readonly CoherenceMapRepository $coherenceMap)
    {
    }

    /**
     * @return array{
     *   concept_id: int,
     *   blocked: bool,
     *   root_blockers: array<int, array{id:int, name:string, mastery:float, gate:float, depth:int}>,
     *   assessing_questions: array<int, array<string, mixed>>,
     *   teaching_content: array<int, array<string, mixed>>,
     *   misconceptions: array<int, array<string, mixed>>
     * }
     */
    public function contextFor(int $studentId, int $conceptId, int $subInstituteId): array
    {
        $rootBlockers = $this->coherenceMap->rootBlockers($conceptId, $studentId);

        return [
            'concept_id'          => $conceptId,
            'blocked'             => $rootBlockers !== [],
            'root_blockers'       => $rootBlockers,
            'assessing_questions' => $this->coherenceMap->questionsFor($conceptId, 5),
            'teaching_content'    => $this->coherenceMap->contentFor($conceptId, [], 3),
            'misconceptions'      => $this->coherenceMap->misconceptionsFor($conceptId, 5),
        ];
    }
}
