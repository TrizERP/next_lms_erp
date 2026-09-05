<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_08_18_000500_intelligence_dashboard_indexes.php
 * (read-only reference; that repository is not modified).
 *
 * Every table here is hpbrain_-prefixed. The Brain was designed to share a
 * database with the institute ERP, so nothing below collides with, alters or
 * duplicates an LMS table — Organization, Departments, People, Positions and
 * Students continue to live in the LMS's own tables and are reached through
 * hpbrain_entity_mappings.
 */
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the aggregates the intelligence dashboards run on every load.
 *
 * WHAT THIS FIXES, MEASURED. Every screen in the intelligence loop counts its
 * tables by status, severity or confidence on each request. Those columns had
 * no index, so each count read whole rows — and both hpbrain_signals and
 * hpbrain_evidence carry a LONGTEXT (`metadata`, `content`) that the scan drags
 * along for nothing. On the Lions tenant, 10,430 signals:
 *
 *     GROUP BY status   (already indexed)  ->   236 ms
 *     GROUP BY severity (no index)         -> 1,444 ms
 *
 * Same table, same row count, same request. The difference is entirely whether
 * the aggregate can be answered from an index instead of from the rows.
 *
 * WHY THESE COLUMN ORDERS. Every index leads with tenant_id because every query
 * in this application is tenant-filtered, so the tenant predicate has to be the
 * leading edge or the index cannot serve it at all.
 *
 * The evidence index carries three columns rather than two: the dashboards ask
 * for average confidence and for how much evidence has gone stale in the same
 * breath, and (tenant_id, created_date, confidence) answers both from one
 * index-only scan instead of two row scans.
 *
 * THE PREFIX LENGTHS ARE NOT OPTIONAL. `severity`, `status` and `result` are
 * declared TEXT on these tables, and InnoDB will not index a TEXT column without
 * one — the whole migration fails with "key was too long". The prefixes are far
 * longer than the vocabularies they cover ('critical', 'approved', 'completed'),
 * so the index stays as selective as the full column.
 *
 * IDEMPOTENT AND MYSQL-ONLY, matching the other index migrations here. The
 * bounded lock wait is the same guard as 2026_08_18_000400 and exists for the
 * same reason: this database is shared with a live application, and a migration
 * that turns a slow table into an unavailable one is worse than the problem it
 * fixes.
 *
 * @see 2026_08_18_000400_operational_records_freshness_index
 */
return new class extends Migration
{
    /** @var list<array{table: string, index: string, columns: string}> */
    private const INDEXES = [
        ['table' => 'hpbrain_signals', 'index' => 'idx_signals_tenant_severity', 'columns' => 'tenant_id, severity(16)'],
        ['table' => 'hpbrain_evidence', 'index' => 'idx_evidence_tenant_created_conf', 'columns' => 'tenant_id, created_date, confidence'],
        ['table' => 'hpbrain_recommendations', 'index' => 'idx_recommendations_tenant_status', 'columns' => 'tenant_id, status(32)'],
        ['table' => 'hpbrain_eso_executions', 'index' => 'idx_eso_executions_tenant_status', 'columns' => 'tenant_id, status(32)'],
        ['table' => 'hpbrain_risks', 'index' => 'idx_risks_tenant_status', 'columns' => 'tenant_id, status(32)'],
        ['table' => 'hpbrain_outcomes', 'index' => 'idx_outcomes_tenant_result', 'columns' => 'tenant_id, result(32)'],
    ];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('SET SESSION lock_wait_timeout = 30');

        foreach (self::INDEXES as $spec) {
            if (! Schema::hasTable($spec['table']) || $this->hasIndex($spec['table'], $spec['index'])) {
                continue;
            }

            try {
                DB::statement("ALTER TABLE {$spec['table']} ADD INDEX {$spec['index']} ({$spec['columns']})");
            } catch (\Illuminate\Database\QueryException $e) {
                // 1205 is lock wait timeout. Transient load must not fail a
                // deploy — re-run the migration when the table is quieter.
                if ((string) ($e->errorInfo[1] ?? '') !== '1205') {
                    throw $e;
                }
            }
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::INDEXES as $spec) {
            if (Schema::hasTable($spec['table']) && $this->hasIndex($spec['table'], $spec['index'])) {
                DB::statement("ALTER TABLE {$spec['table']} DROP INDEX {$spec['index']}");
            }
        }
    }

    private function hasIndex(string $table, string $index): bool
    {
        return DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]) !== [];
    }
};
