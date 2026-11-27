<?php

namespace Tests\Feature\Pal;

use App\Services\Neo4jService;
use App\Services\PAL\Coherence\CoherenceMapRepository;
use Tests\TestCase;

/**
 * Live-data companion proving CoherenceMapRepository::map()'s Topic
 * read-through (added alongside this test) actually surfaces real,
 * already-synced (:Topic)-[:HAS_CONCEPT]->(:Concept) data — not a stub.
 *
 * Follows the same discover-a-real-fixture-at-runtime pattern as
 * CoherenceMapRepositoryFanOutTest, since this repo's dev Neo4j data shifts
 * under the test suite rather than being a stable fixture.
 */
class ContextGraphTopicReadThroughTest extends TestCase
{
    public function test_map_surfaces_topics_for_a_scope_with_live_topic_coverage(): void
    {
        /** @var Neo4jService $neo4j */
        $neo4j = app(Neo4jService::class);

        $rows = $neo4j->run(
            'MATCH (t:Topic)-[:HAS_CONCEPT]->(c:Concept) '
            . 'WHERE c.standard_id IS NOT NULL AND c.subject_id IS NOT NULL '
            . 'RETURN toInteger(c.standard_id) AS sid, toInteger(c.subject_id) AS subid '
            . 'LIMIT 1',
            []
        );

        $scope = null;
        foreach ($rows ?? [] as $row) {
            $scope = ['standard_id' => (int) $row->get('sid'), 'subject_id' => (int) $row->get('subid')];
        }

        if ($scope === null) {
            $this->markTestSkipped('No live (:Topic)-[:HAS_CONCEPT]->(:Concept) edge with a standard_id/subject_id scope was found to test against.');
        }

        $repo = app(CoherenceMapRepository::class);
        $map = $repo->map($scope['standard_id'], $scope['subject_id']);

        $this->assertTrue($map['available']);
        $this->assertGreaterThan(0, $map['stats']['topics'] ?? 0, 'map() stats.topics should be > 0 for a scope with live Topic coverage.');
        $this->assertNotEmpty($map['topics'] ?? [], 'map() should return a non-empty top-level topics array.');
        $this->assertTrue(
            collect($map['nodes'])->contains(fn ($n) => $n['topic_id'] !== null),
            'At least one concept node should carry a non-null topic_id.'
        );
    }

    public function test_map_returns_zero_topics_for_an_unknown_scope(): void
    {
        $repo = app(CoherenceMapRepository::class);
        $map = $repo->map(999999991, 999999992);

        $this->assertFalse($map['available']);
        $this->assertSame(0, $map['stats']['topics'] ?? null);
        $this->assertSame([], $map['topics'] ?? null);
    }
}
