<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_08_25_000100_operational_records_measure_indexes.php
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
 * The two measure columns the data profiler sorts and groups on.
 *
 * hpbrain_operational_records carries a tenant-leading composite for every axis
 * that is READ along — occurred_at, status, zone, owner_name, subject_ref,
 * category, sub_category. The two the profiler MEASURES along were the only
 * ones with nothing behind them:
 *
 *   metric_value  → ORDER BY, for the median and the 95th percentile
 *   metric_unit   → GROUP BY, for the dataset's dominant unit
 *
 * MEASURED, NOT ASSUMED. Against the development database, for one tenant's
 * 27,000-row school_fee dataset:
 *
 *   WHERE tenant_id = ?                              0.26s
 *   WHERE tenant_id = ? AND dataset = ?              0.72s
 *   … GROUP BY metric_unit ORDER BY COUNT(*) DESC  118.96s
 *   … ORDER BY metric_value LIMIT 1 OFFSET n       129.99s
 *
 * The filter is instant and the sort is two minutes, which is the signature of
 * a filesort over the matched slice with no index to walk. Every organization's
 * intelligence is recomputed through that path whenever its data changes, so
 * two minutes per dataset is the difference between a profile that completes
 * inside a request and one that does not.
 *
 * WHY THIS IS WORTH THE WRITE COST. Every index slows an import that already
 * inserts hundreds of thousands of rows, so the bar for adding one is a real
 * query. These two have the same query — profileDataset() — running for every
 * dataset of every tenant, on the path that produces the product's headline
 * numbers.
 *
 * TENANT FIRST, THEN DATASET, THEN THE COLUMN, matching every other index on
 * this table. A leading metric_value would be useless: nothing reads
 * measurements across tenants, and nothing may.
 *
 * IDEMPOTENT AND MYSQL-ONLY, for the same reasons as the category indexes
 * beside it: the suite runs on SQLite, where these buy nothing and the
 * information_schema probe does not exist.
 */
return new class extends Migration
{
    private const TABLE = 'hpbrain_operational_records';

    /** @var array<string, string> index name => column */
    private const INDEXES = [
        'idx_oprec_tenant_dataset_metric_value'     => 'metric_value',
        'idx_oprec_tenant_dataset_metric_unit'      => 'metric_unit',
        // The last two profiled axes with nothing behind them. CLASSIFIERS and
        // ACTORS name seven columns between them and five were already covered
        // — category, sub_category, status, zone, owner_name — leaving `area`
        // and `supervisor_name` as full scans on the same code path as the two
        // above, for the same reason and at the same cost.
        'idx_oprec_tenant_dataset_area'             => 'area',
        'idx_oprec_tenant_dataset_supervisor_name'  => 'supervisor_name',
    ];

    /*
        The three PROVENANCE columns — source_file, import_job_id, natural_key —
        are covered by the migration that follows this one rather than added
        here. They are the same fix for the same reason, and they are separated
        only because each ALTER on a table this size takes minutes: keeping them
        apart means a deployment that has to stop and resume ends up in a state
        the migrations table can describe.
    */

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
