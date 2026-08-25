<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Make the two existing outbox tables fit to be drained continuously.
 *
 * `sync_log` and `neo4j_sync_queue` were built for this job and are kept as
 * they are — this only fixes two things that stop them being drained safely.
 *
 * 1. `neo4j_sync_queue` had no `retry_count`, so GraphDrain had nowhere to
 *    record an attempt and marked a row `failed` on its first miss. The usual
 *    miss is an edge draining a moment before the node it points at, which is
 *    transient by nature — so the permanent-failure behaviour silently and
 *    irrecoverably dropped edges. `sync_log` has always had the column; this
 *    gives relationships the same retry budget.
 *
 * 2. Neither table had an index beyond its primary key. Every drain pass ran
 *    `WHERE status = 'PENDING'` as a full scan — survivable at today's 9k/12k
 *    rows, not at the volume trigger-driven capture produces.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->hasColumn('neo4j_sync_queue', 'retry_count')) {
            DB::statement('ALTER TABLE `neo4j_sync_queue` ADD COLUMN `retry_count` INT NOT NULL DEFAULT 0 AFTER `status`');
        }

        // Drain lookup: pending rows in id order.
        $this->addIndex('sync_log', 'sync_log_status_id_index', '(`status`, `id`)');
        $this->addIndex('neo4j_sync_queue', 'neo4j_sync_queue_status_id_index', '(`status`, `id`)');

        // flushRecord() lookup: this row's pending events.
        $this->addIndex('sync_log', 'sync_log_table_record_status_index', '(`table_name`, `record_id`, `status`)');

        // StudentGraphProjection reads the last ENROLLED_IN target back to spot
        // a class change, and delete() reads HAS_STUDENT history.
        $this->addIndex('neo4j_sync_queue', 'neo4j_sync_queue_source_rel_index', '(`source_table`, `source_id`, `rel_type`)');
    }

    public function down(): void
    {
        $this->dropIndex('sync_log', 'sync_log_status_id_index');
        $this->dropIndex('sync_log', 'sync_log_table_record_status_index');
        $this->dropIndex('neo4j_sync_queue', 'neo4j_sync_queue_status_id_index');
        $this->dropIndex('neo4j_sync_queue', 'neo4j_sync_queue_source_rel_index');

        if ($this->hasColumn('neo4j_sync_queue', 'retry_count')) {
            DB::statement('ALTER TABLE `neo4j_sync_queue` DROP COLUMN `retry_count`');
        }
    }

    private function hasColumn(string $table, string $column): bool
    {
        return DB::selectOne(
            'SELECT 1 AS found FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        ) !== null;
    }

    private function hasIndex(string $table, string $index): bool
    {
        return DB::selectOne(
            'SELECT 1 AS found FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $index]
        ) !== null;
    }

    private function addIndex(string $table, string $index, string $columns): void
    {
        if (! $this->hasIndex($table, $index)) {
            DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$index}` {$columns}");
        }
    }

    private function dropIndex(string $table, string $index): void
    {
        if ($this->hasIndex($table, $index)) {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
        }
    }
};
