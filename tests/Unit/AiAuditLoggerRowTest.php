<?php

namespace Tests\Unit;

use App\Domain\AI\Support\AiAuditLogger;
use Tests\TestCase;

/**
 * `AiAuditLogger::buildRow()` — the row-shaping logic, exercised without a database
 * write.
 *
 * Extends `Tests\TestCase` (not plain PHPUnit) only because `buildRow()` calls the
 * `now()` and `request()` helpers, which need the container booted — booting it does
 * not touch the database, and nothing here calls `DB::`, `RefreshDatabase`, or
 * anything else that would. This repo's tests run against the same shared estate
 * every environment does, so a test that wrote to `ai_audit_logs` would be writing to
 * a real school's data; this one only ever inspects the array `record()` would have
 * inserted.
 */
class AiAuditLoggerRowTest extends TestCase
{
    private function logger(): AiAuditLogger
    {
        return new AiAuditLogger();
    }

    public function test_duration_ms_is_taken_from_an_explicit_option_first(): void
    {
        $row = $this->logger()->buildRow('tool.execution', null, [
            'duration_ms' => 420,
            'payload' => ['duration_ms' => 999],
        ]);

        $this->assertSame(420, $row['duration_ms']);
    }

    public function test_duration_ms_is_promoted_from_payload_when_not_given_explicitly(): void
    {
        $row = $this->logger()->buildRow('agent.run.completed', null, [
            'payload' => ['duration_ms' => 1834, 'status' => 'completed'],
        ]);

        $this->assertSame(1834, $row['duration_ms']);
        // Promoting it to a column must not remove it from the payload — a caller
        // reading the raw JSON today must see exactly what it saw before this change.
        $this->assertSame(1834, $row['payload'] !== null ? json_decode($row['payload'], true)['duration_ms'] : null);
    }

    public function test_duration_ms_is_null_not_zero_when_nobody_supplied_one(): void
    {
        $row = $this->logger()->buildRow('case.opened', null, []);

        $this->assertNull($row['duration_ms']);
    }

    public function test_a_non_numeric_duration_is_dropped_rather_than_coerced(): void
    {
        $row = $this->logger()->buildRow('tool.execution', null, [
            'payload' => ['duration_ms' => 'unknown'],
        ]);

        $this->assertNull($row['duration_ms']);
    }

    public function test_knowledge_graph_used_is_null_by_default(): void
    {
        $row = $this->logger()->buildRow('explanation.built', null, []);

        $this->assertNull($row['knowledge_graph_used']);
    }

    public function test_knowledge_graph_used_only_ever_reflects_what_the_caller_said(): void
    {
        $usedIt = $this->logger()->buildRow('explanation.built', null, ['knowledge_graph_used' => true]);
        $didNot = $this->logger()->buildRow('explanation.built', null, ['knowledge_graph_used' => false]);

        $this->assertTrue($usedIt['knowledge_graph_used']);
        $this->assertFalse($didNot['knowledge_graph_used']);
    }
}
