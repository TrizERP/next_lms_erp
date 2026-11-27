<?php

namespace Tests\Feature\Graph;

use App\Services\Graph\GraphDrain;
use App\Services\Neo4jService;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

/**
 * Requirement: "concurrent drain invocations must not process the same
 * records incorrectly." Before this change, drainNodes()/drainRelationships()
 * used a plain unlocked SELECT, so two overlapping invocations (a scheduled
 * run colliding with a manually started `--watch` worker, say) could both
 * fetch the same pending row and both attempt to process it.
 *
 * This test proves the fix — the claim query now uses `FOR UPDATE SKIP
 * LOCKED` — by genuinely holding a row lock from a SEPARATE database
 * connection (simulating a second, concurrent drain process) and asserting
 * the app's own drain call skips it entirely rather than racing it.
 *
 * This needs a row that is actually COMMITTED so a second, independent PDO
 * connection can see and lock it — `DatabaseTransactions` (which keeps
 * everything uncommitted on the shared connection) cannot be used here.
 * The row is cleaned up manually in tearDown() by its unique sentinel
 * record_id instead.
 */
class GraphDrainConcurrencyTest extends TestCase
{
    private int $sentinelRecordId;

    private int $rowId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sentinelRecordId = random_int(900_000_000, 999_999_999);

        $this->rowId = DB::table('sync_log')->insertGetId([
            'table_name'     => 'Student',
            'operation_type' => 'INSERT',
            'record_id'      => $this->sentinelRecordId,
            'payload_json'   => json_encode(['data' => []]),
            'status'         => 'PENDING',
            'retry_count'    => 0,
            'created_at'     => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('sync_log')->where('record_id', $this->sentinelRecordId)->delete();

        parent::tearDown();
    }

    private function secondConnection(): PDO
    {
        $cfg = config('database.connections.' . config('database.default'));
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $cfg['host'], $cfg['port'], $cfg['database'], $cfg['charset'] ?? 'utf8mb4');

        return new PDO($dsn, $cfg['username'], $cfg['password']);
    }

    public function test_a_row_locked_by_another_connection_is_skipped_not_double_processed(): void
    {
        $this->mock(Neo4jService::class, function ($mock) {
            $mock->shouldReceive('run')->andReturn([]);
        });

        $holder = $this->secondConnection();
        $holder->beginTransaction();
        // Simulates another drain process having already claimed this row.
        $holder->prepare('SELECT * FROM sync_log WHERE id = ? FOR UPDATE')->execute([$this->rowId]);

        try {
            app(GraphDrain::class)->drainNodes(limit: 50, maxRetries: 5);

            $row = DB::table('sync_log')->where('id', $this->rowId)->first();

            $this->assertSame('PENDING', $row->status, 'a row locked by another connection must be skipped, not raced');
            $this->assertSame(0, $row->retry_count, 'a skipped row must be untouched, not counted as a failed attempt');
        } finally {
            $holder->rollBack();
        }

        // The lock is released — the SAME row must now be pickable.
        app(GraphDrain::class)->drainNodes(limit: 50, maxRetries: 5);

        $row = DB::table('sync_log')->where('id', $this->rowId)->first();
        $this->assertSame('SUCCESS', $row->status, 'once unlocked, the row must become processable again');
    }
}
