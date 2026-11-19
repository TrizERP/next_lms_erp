<?php

namespace Tests\Feature\Pal;

use App\Services\Neo4jService;
use App\Services\PAL\Coherence\CoherenceMapRepository;
use Tests\TestCase;

/**
 * Live-data companion to `MasteryOverviewRecallQueueTest` (which mocks
 * `CoherenceMapRepository` for a deterministic ordering test). This proves
 * `downstreamFanOut()` itself returns a real, correct count against the
 * live Neo4j `:REQUIRES` projection — discovered at run time rather than
 * hardcoded, for the same "the dev DB shifts under you" reason every other
 * live-data test in this batch discovers its own fixture.
 */
class CoherenceMapRepositoryFanOutTest extends TestCase
{
    public function test_fan_out_matches_live_incoming_requires_edge_count(): void
    {
        /** @var Neo4jService $neo4j */
        $neo4j = app(Neo4jService::class);

        // readEdges()'s own docblock: the projection writes
        // (later)-[:REQUIRES]->(earlier), so counting DIRECT incoming
        // REQUIRES edges on a concept is a same-depth lower bound on its
        // transitive downstreamFanOut() — real enough to prove the method
        // reads live data and isn't just returning 0/a stub, without
        // depending on the exact transitive-closure size staying stable.
        $rows = $neo4j->run(
            'MATCH (a:Concept)<-[:REQUIRES]-(b:Concept) WHERE a.standard_id IS NOT NULL '
            . 'RETURN a.id AS id, count(b) AS directCount ORDER BY directCount DESC LIMIT 1',
            []
        );

        $top = null;
        foreach ($rows ?? [] as $row) {
            $top = ['id' => (int) $row->get('id'), 'directCount' => (int) $row->get('directCount')];
        }

        if ($top === null || $top['directCount'] < 1) {
            $this->markTestSkipped('No concept with at least one live incoming :REQUIRES edge was found to test fan-out against.');
        }

        $repo = app(CoherenceMapRepository::class);
        $fanOut = $repo->downstreamFanOut($top['id']);

        $this->assertGreaterThanOrEqual(
            $top['directCount'],
            $fanOut,
            "downstreamFanOut({$top['id']}) returned {$fanOut}, less than its {$top['directCount']} known direct descendants — the transitive closure cannot be smaller than its direct edges."
        );
    }

    public function test_fan_out_is_zero_for_an_unknown_concept(): void
    {
        $repo = app(CoherenceMapRepository::class);

        $this->assertSame(0, $repo->downstreamFanOut(999999999));
    }
}
