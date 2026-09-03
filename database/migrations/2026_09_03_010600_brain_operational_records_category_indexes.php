<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_08_18_000100_operational_records_category_indexes.php
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
 * Two indexes for the academic-result query patterns.
 *
 * hpbrain_operational_records already carries tenant-leading composites for
 * every axis the fee datasets are read along:
 *
 *   (tenant_id, dataset, occurred_at)   year-wise
 *   (tenant_id, dataset, status)        standard-wise
 *   (tenant_id, dataset, subject_ref)   student-wise
 *   (tenant_id, dataset, owner_name)    collector-wise
 *   (tenant_id, dataset, zone)
 *
 * The academic dataset added two more axes and neither was covered. Both are
 * GROUP BY targets on 388,401 rows for one tenant, so without an index each
 * report is a full scan of that tenant's slice:
 *
 *   category      → subject-wise average percentage
 *   sub_category  → exam-wise average percentage
 *
 * WHY THESE TWO AND NOT MORE. Every index costs write time on an import that
 * already inserts hundreds of thousands of rows, so this adds only the columns
 * with a real query behind them. `standard` needed nothing — it maps to
 * `status`, which is already indexed. `syear` needed nothing — it maps to
 * `occurred_at`, likewise. The redundant single-column tenant indexes on
 * hpbrain_signals / _evidence / _cases are left alone: they are wasteful
 * prefixes of wider composites, but dropping an index is a separate decision
 * from adding one and does not belong in the same migration.
 *
 * TENANT FIRST, THEN DATASET, THEN THE COLUMN — matching every other index on
 * this table and the shape of every query that reads it. A leading `category`
 * would be useless: nothing queries categories across tenants, and nothing may.
 *
 * IDEMPOTENT AND MYSQL-ONLY. The suite runs on SQLite where these indexes buy
 * nothing and the information_schema probe does not exist, so it returns early
 * there. Re-running is safe: an index already present is skipped rather than
 * failing the migration.
 */
return new class extends Migration
{
    private const TABLE = 'hpbrain_operational_records';

    /** @var array<string, string> index name => column */
    private const INDEXES = [
        'idx_oprec_tenant_dataset_category'     => 'category',
        'idx_oprec_tenant_dataset_sub_category' => 'sub_category',
    ];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        foreach (self::INDEXES as $name => $column) {
            if ($this->hasIndex($name) || ! Schema::hasColumn(self::TABLE, $column)) {
                continue;
            }

            DB::unprepared(
                'ALTER TABLE '.self::TABLE
                .' ADD INDEX '.$name.' (tenant_id, dataset, '.$column.')'
            );
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql' || ! Schema::hasTable(self::TABLE)) {
            return;
        }

        foreach (array_keys(self::INDEXES) as $name) {
            if ($this->hasIndex($name)) {
                DB::unprepared('ALTER TABLE '.self::TABLE.' DROP INDEX '.$name);
            }
        }
    }

    private function hasIndex(string $name): bool
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS n
               FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND INDEX_NAME = ?',
            [self::TABLE, $name],
        );

        return $row !== null && (int) $row->n > 0;
    }
};
