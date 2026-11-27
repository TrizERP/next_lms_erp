<?php

namespace Tests\Unit\Graph;

use App\Services\Graph\GraphDrain;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Regression coverage for the FIFO-starvation fix in GraphDrain.
 *
 * Measured live before this fix: tenant 47's `fees_breackoff` backlog
 * (26,457 rows) got zero successes for over 40 hours because it queued
 * behind `fees_collect`'s much larger backlog (346,440 rows) in strict
 * `ORDER BY id` order, and `neo4j:drain` has no per-table or per-tenant
 * concept at all. These tests exercise `fairShareIds()` and
 * `maxMinFairShare()` directly via reflection — pure SQL-planning logic,
 * no Neo4j call involved, so no Neo4jService mocking is needed here.
 *
 * This installation's real `sync_log` has ~440k genuine pending rows at
 * the time this was written. `fairShareIds()`'s candidate window is
 * oldest-id-first by design (it is still FIFO *within* a fair share), so
 * any row this test inserts would land at the newest (highest-id, hence
 * last-considered) end of that real backlog and never appear in any
 * bounded window — not a bug in the fix, just incompatible with testing
 * against a live, heavily-queued shared table. Each test therefore runs
 * against a disposable in-memory SQLite connection carrying only the two
 * columns this file actually needs, swapped in for `database.default`
 * only for the duration of the test and restored in tearDown() — it
 * never touches the real `sync_log`/`neo4j_sync_queue` tables.
 */
class GraphDrainFairnessTest extends TestCase
{
    private GraphDrain $drain;

    private string $originalDefaultConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDefaultConnection = Config::get('database.default');

        Config::set('database.connections.sqlite_graphdrain_test', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
        Config::set('database.default', 'sqlite_graphdrain_test');
        DB::purge('sqlite_graphdrain_test');

        Schema::connection('sqlite_graphdrain_test')->create('sync_log', function ($table) {
            $table->id();
            $table->string('table_name');
            $table->string('operation_type')->nullable();
            $table->unsignedInteger('record_id');
            $table->text('payload_json')->nullable();
            $table->string('status')->default('PENDING');
            $table->integer('retry_count')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('processed_at')->nullable();
        });

        $this->drain = app(GraphDrain::class);
    }

    protected function tearDown(): void
    {
        Config::set('database.default', $this->originalDefaultConnection);
        DB::purge('sqlite_graphdrain_test');

        parent::tearDown();
    }

    /** @return mixed */
    private function invokePrivate(string $method, array $args)
    {
        $reflection = new ReflectionMethod(GraphDrain::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($this->drain, ...$args);
    }

    private function seedPendingRow(string $table, ?array $data = null): int
    {
        $payload = $data === null ? null : json_encode(['data' => $data]);

        return DB::table('sync_log')->insertGetId([
            'table_name'     => $table,
            'operation_type' => 'INSERT',
            'record_id'      => random_int(1, 2_000_000_000),
            'payload_json'   => $payload,
            'status'         => 'PENDING',
            'retry_count'    => 0,
            'created_at'     => now(),
        ]);
    }

    // -------------------------------------------------------------------
    // maxMinFairShare() — pure algorithm
    // -------------------------------------------------------------------

    public function test_max_min_fair_share_rolls_leftover_from_small_buckets_to_larger_ones(): void
    {
        // One bucket has only 2 available; the other two have plenty.
        // Budget 30 across 3 buckets would naively be 10 each, but the
        // 2-item bucket can't use 10, so its leftover 8 must roll forward
        // to the remaining two buckets.
        $allocation = $this->invokePrivate('maxMinFairShare', [
            ['small' => 2, 'medium' => 100, 'large' => 100],
            30,
        ]);

        $this->assertSame(2, $allocation['small']);
        $this->assertSame(30 - 2, $allocation['medium'] + $allocation['large']);
        // The two unconstrained buckets split the rest evenly.
        $this->assertSame($allocation['medium'], $allocation['large']);
    }

    public function test_max_min_fair_share_never_allocates_more_than_available_or_budget(): void
    {
        $allocation = $this->invokePrivate('maxMinFairShare', [
            ['a' => 1, 'b' => 1, 'c' => 1, 'd' => 1, 'e' => 1],
            3,
        ]);

        $this->assertLessThanOrEqual(3, array_sum($allocation));

        foreach ($allocation as $bucket => $take) {
            $this->assertLessThanOrEqual(1, $take, "bucket {$bucket} over-allocated");
        }
    }

    public function test_max_min_fair_share_with_a_single_bucket_takes_the_whole_budget(): void
    {
        $allocation = $this->invokePrivate('maxMinFairShare', [['only' => 9999], 500]);

        $this->assertSame(500, $allocation['only']);
    }

    // -------------------------------------------------------------------
    // fairShareIds() — the selection the fix actually changes
    // -------------------------------------------------------------------

    public function test_fair_share_ids_lets_a_small_table_through_despite_a_much_larger_backlog_ahead_of_it(): void
    {
        // Simulate the exact live incident: a big table's backlog queues
        // first (lower ids), a much smaller table's backlog queues right
        // after it. Strict FIFO would starve the small one completely for
        // as many passes as it takes to drain the big one. Kept inside the
        // lookahead window (limit 50 -> window 300) so both tables are
        // actually visible to this pass — a bigger backlog than the window
        // itself is a "wait for the next pass" case, not a fairness one.
        for ($i = 0; $i < 200; $i++) {
            $this->seedPendingRow('big_backlog_table');
        }

        $smallIds = [];
        for ($i = 0; $i < 5; $i++) {
            $smallIds[] = $this->seedPendingRow('small_backlog_table');
        }

        $selected = $this->invokePrivate('fairShareIds', ['sync_log', 'table_name', 'PENDING', 50, 5, false]);

        $selectedSmall = array_values(array_intersect($selected, $smallIds));

        $this->assertNotEmpty($selectedSmall, 'the small table must not be starved by the larger one');
        $this->assertCount(5, $selectedSmall, 'a small, fully-available bucket should be taken in full');
    }

    public function test_fair_share_ids_splits_fairly_across_tenants_within_one_table(): void
    {
        // Kept inside the lookahead window (limit 20 -> window 120), same
        // reasoning as the table-fairness test above.
        for ($i = 0; $i < 100; $i++) {
            $this->seedPendingRow('fees_collect', ['sub_institute_id' => 254]);
        }

        $tenant61Ids = [];
        for ($i = 0; $i < 3; $i++) {
            $tenant61Ids[] = $this->seedPendingRow('fees_collect', ['sub_institute_id' => 61]);
        }

        $selected = $this->invokePrivate('fairShareIds', ['sync_log', 'table_name', 'PENDING', 20, 5, true]);

        $selectedTenant61 = array_values(array_intersect($selected, $tenant61Ids));

        $this->assertCount(3, $selectedTenant61, 'the small tenant must get its own fair share, not be drowned out by tenant 254');
    }

    public function test_fair_share_ids_degrades_to_table_only_fairness_when_tenant_is_not_extractable(): void
    {
        // No payload at all — tenant cannot be read. Must still select
        // rows normally instead of erroring or returning nothing.
        $ids = [];
        for ($i = 0; $i < 10; $i++) {
            $ids[] = $this->seedPendingRow('platform_wide_table', null);
        }

        $selected = $this->invokePrivate('fairShareIds', ['sync_log', 'table_name', 'PENDING', 5, 5, true]);

        $this->assertCount(5, array_intersect($selected, $ids));
    }

    public function test_fair_share_ids_never_returns_more_than_the_limit(): void
    {
        foreach (['t1', 't2', 't3', 't4', 't5'] as $table) {
            for ($i = 0; $i < 200; $i++) {
                $this->seedPendingRow($table, ['sub_institute_id' => $i % 7]);
            }
        }

        $selected = $this->invokePrivate('fairShareIds', ['sync_log', 'table_name', 'PENDING', 500, 5, true]);

        $this->assertLessThanOrEqual(500, count($selected));
        $this->assertSame(count($selected), count(array_unique($selected)), 'no row id should be selected twice');
    }

    public function test_fair_share_ids_excludes_rows_that_have_exhausted_their_retry_budget(): void
    {
        $exhaustedId = $this->seedPendingRow('retry_table');
        DB::table('sync_log')->where('id', $exhaustedId)->update(['retry_count' => 5]);

        $freshId = $this->seedPendingRow('retry_table');

        $selected = $this->invokePrivate('fairShareIds', ['sync_log', 'table_name', 'PENDING', 10, 5, false]);

        $this->assertNotContains($exhaustedId, $selected, 'a row that has used up its retry budget must not be re-selected');
        $this->assertContains($freshId, $selected);
    }

    public function test_fair_share_ids_treats_malformed_tenant_payload_as_unextractable_rather_than_erroring(): void
    {
        // Not valid JSON at all, and valid JSON that isn't the expected
        // {"data": {...}} / {...} shape — both must degrade to "no tenant
        // readable", never throw and never silently drop the row.
        $brokenJsonId = DB::table('sync_log')->insertGetId([
            'table_name' => 'malformed_table', 'operation_type' => 'INSERT',
            'record_id' => random_int(1, 2_000_000_000), 'payload_json' => '{not valid json',
            'status' => 'PENDING', 'retry_count' => 0, 'created_at' => now(),
        ]);

        $wrongShapeId = DB::table('sync_log')->insertGetId([
            'table_name' => 'malformed_table', 'operation_type' => 'INSERT',
            'record_id' => random_int(1, 2_000_000_000), 'payload_json' => json_encode(['data' => 'not-an-array']),
            'status' => 'PENDING', 'retry_count' => 0, 'created_at' => now(),
        ]);

        $selected = $this->invokePrivate('fairShareIds', ['sync_log', 'table_name', 'PENDING', 10, 5, true]);

        $this->assertContains($brokenJsonId, $selected, 'invalid JSON must not throw or silently drop the row');
        $this->assertContains($wrongShapeId, $selected, 'an unexpected payload shape must not throw or silently drop the row');
    }

    public function test_fair_share_ids_window_cap_is_respected_with_more_candidates_than_the_cap(): void
    {
        // limit=500 -> window = min(500*6, 3000) = 3000 exactly. Seed more
        // than 3000 pending rows so the cap itself is actually the binding
        // constraint, not just the per-bucket budget — the previous "never
        // exceeds the limit" test used only 1,000 rows, which never reached
        // the cap at all.
        for ($i = 0; $i < 3200; $i++) {
            $this->seedPendingRow('t' . ($i % 5));
        }

        $selected = $this->invokePrivate('fairShareIds', ['sync_log', 'table_name', 'PENDING', 500, 5, true]);

        $this->assertLessThanOrEqual(500, count($selected), 'the cap on the lookahead window must never let more than $limit through');
        $this->assertSame(count($selected), count(array_unique($selected)));
    }

    // -------------------------------------------------------------------
    // supersedeDuplicates() — unchanged logic, now fed a fairness-selected
    // (table-interleaved) subset instead of a strict id-contiguous one
    // -------------------------------------------------------------------

    public function test_supersede_duplicates_collapses_interleaved_duplicate_source_events_but_leaves_distinct_rows_alone(): void
    {
        $recordId = random_int(1, 2_000_000_000);

        // Two "fees_breackoff row #X changed" events for the SAME record —
        // the classic bulk-update-fires-the-trigger-twice case.
        $olderDuplicateId = DB::table('sync_log')->insertGetId([
            'table_name' => 'fees_breackoff', 'operation_type' => 'UPDATE',
            'record_id' => $recordId, 'payload_json' => null,
            'status' => 'PENDING', 'retry_count' => 0, 'created_at' => now(),
        ]);

        // A projected node event interleaved in between — must never collapse.
        $studentNodeId = DB::table('sync_log')->insertGetId([
            'table_name' => 'Student', 'operation_type' => 'INSERT',
            'record_id' => random_int(1, 2_000_000_000), 'payload_json' => json_encode(['data' => []]),
            'status' => 'PENDING', 'retry_count' => 0, 'created_at' => now(),
        ]);

        $newerDuplicateId = DB::table('sync_log')->insertGetId([
            'table_name' => 'fees_breackoff', 'operation_type' => 'UPDATE',
            'record_id' => $recordId, 'payload_json' => null,
            'status' => 'PENDING', 'retry_count' => 0, 'created_at' => now(),
        ]);

        $rows = DB::table('sync_log')->whereIn('id', [$olderDuplicateId, $studentNodeId, $newerDuplicateId])
            ->orderBy('id')->get();

        $result = $this->invokePrivate('supersedeDuplicates', [$rows]);
        $resultIds = $result->pluck('id')->all();

        $this->assertNotContains($olderDuplicateId, $resultIds, 'the older duplicate must be superseded, not reprocessed');
        $this->assertContains($newerDuplicateId, $resultIds, 'the newest duplicate carries the information and must survive');
        $this->assertContains($studentNodeId, $resultIds, 'a projected node event must never be collapsed as a duplicate');

        $this->assertSame('SUCCESS', DB::table('sync_log')->where('id', $olderDuplicateId)->value('status'));
    }
}
