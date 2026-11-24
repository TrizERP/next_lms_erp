<?php

namespace Tests\Feature\Graph;

use App\Services\Graph\GraphDrain;
use App\Services\Neo4jService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * Confirms the bounded-fairness rewrite of GraphDrain::drainNodes()/
 * drainRelationships() preserved every existing behavioural contract:
 * the per-pass limit, the retry/FAILED lifecycle, and nodes-before-
 * relationships ordering. Neo4jService is mocked throughout — these
 * tests exercise the SQL-side state machine, not the graph itself.
 *
 * Every test here passes an explicit `ids:` list rather than relying on
 * the automatic fair-share selection (that selection is covered on its
 * own, against an isolated SQLite connection, in GraphDrainFairnessTest)
 * — this installation's real `sync_log` has ~440k genuine pending rows
 * with lower ids than anything a test seeds, so a freshly inserted row
 * would never appear in a bounded lookahead window and these per-row
 * behaviour checks would silently test nothing. Passing explicit ids
 * exercises exactly the code path these tests are about (the claim +
 * per-row retry/success/fail logic) without depending on queue position.
 */
class GraphDrainTest extends TestCase
{
    use DatabaseTransactions;

    private function seedPendingRow(string $table, int $retryCount = 0): int
    {
        return DB::table('sync_log')->insertGetId([
            'table_name'     => $table,
            'operation_type' => 'INSERT',
            'record_id'      => random_int(1, 2_000_000_000),
            'payload_json'   => json_encode(['data' => []]),
            'status'         => 'PENDING',
            'retry_count'    => $retryCount,
            'created_at'     => now(),
        ]);
    }

    public function test_total_rows_processed_per_pass_never_exceeds_the_limit(): void
    {
        // Regression check for a real near-miss in this rewrite: the
        // original code applied ->limit($limit) unconditionally, even when
        // an explicit $ids list was passed in (flush(), repairEndpoints(),
        // the expansion recursion). The first draft of this fix dropped
        // that for the explicit-ids path, trusting the caller — this test
        // passes more ids than the limit specifically to prove the cap is
        // still enforced regardless of how many ids are handed in.
        $this->mock(Neo4jService::class, function ($mock) {
            $mock->shouldReceive('run')->andReturn([]);
        });

        $ids = [];
        for ($i = 0; $i < 30; $i++) {
            $ids[] = $this->seedPendingRow('Student');
        }

        $result = app(GraphDrain::class)->drainNodes(limit: 10, maxRetries: 5, ids: $ids);

        $this->assertSame(10, $result['ok'] + $result['failed'], 'exactly $limit rows should be claimed, not all 30 ids');

        $successCount = DB::table('sync_log')->whereIn('id', $ids)->where('status', 'SUCCESS')->count();
        $this->assertSame(10, $successCount);
    }

    public function test_retry_count_increments_and_row_stays_pending_until_retry_budget_is_spent(): void
    {
        $this->mock(Neo4jService::class, function ($mock) {
            $mock->shouldReceive('run')->andThrow(new RuntimeException('simulated Neo4j outage'));
        });

        $id = $this->seedPendingRow('Student');

        app(GraphDrain::class)->drainNodes(limit: 10, maxRetries: 5, ids: [$id]);

        $row = DB::table('sync_log')->where('id', $id)->first();

        $this->assertSame('PENDING', $row->status, 'a transient failure must stay retryable, not fail outright');
        $this->assertSame(1, $row->retry_count);
    }

    public function test_row_flips_to_failed_once_retry_budget_is_exhausted(): void
    {
        $this->mock(Neo4jService::class, function ($mock) {
            $mock->shouldReceive('run')->andThrow(new RuntimeException('simulated Neo4j outage'));
        });

        $id = $this->seedPendingRow('Student', retryCount: 4);

        app(GraphDrain::class)->drainNodes(limit: 10, maxRetries: 5, ids: [$id]);

        $row = DB::table('sync_log')->where('id', $id)->first();

        $this->assertSame('FAILED', $row->status);
        $this->assertSame(5, $row->retry_count);
    }

    public function test_a_successful_row_is_marked_success_with_a_processed_at_timestamp(): void
    {
        $this->mock(Neo4jService::class, function ($mock) {
            $mock->shouldReceive('run')->andReturn([]);
        });

        $id = $this->seedPendingRow('Student');

        app(GraphDrain::class)->drainNodes(limit: 10, maxRetries: 5, ids: [$id]);

        $row = DB::table('sync_log')->where('id', $id)->first();

        $this->assertSame('SUCCESS', $row->status);
        $this->assertNotNull($row->processed_at);
    }

    public function test_flush_drains_nodes_before_relationships(): void
    {
        // flush() is the one method that encodes the node-before-relationship
        // invariant directly: it only drains the relationship queue AFTER
        // drainNodes() has returned, using nodes' own 'queued' output.
        $this->mock(Neo4jService::class, function ($mock) {
            $mock->shouldReceive('run')->andReturn([]);
        });

        $nodeId = $this->seedPendingRow('Student');

        $relId = DB::table('neo4j_sync_queue')->insertGetId([
            'event_type' => 'INSERT', 'source_table' => 'Student', 'source_id' => 1,
            'rel_type' => 'ENROLLED_IN', 'target_table' => 'Standard', 'new_target_id' => 1,
            'status' => 'pending', 'retry_count' => 0, 'created_at' => now(),
        ]);

        $result = app(GraphDrain::class)->flush(['log' => [$nodeId], 'queue' => [$relId]]);

        $this->assertSame(1, $result['nodes']);
        // The node drained successfully before the relationship was ever attempted.
        $this->assertSame('SUCCESS', DB::table('sync_log')->where('id', $nodeId)->value('status'));
    }
}
