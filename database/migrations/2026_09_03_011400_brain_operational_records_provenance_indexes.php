<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_08_25_000200_operational_records_provenance_indexes.php
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
 * The three provenance columns the dataset profile counts distinct values of.
 *
 * OrganizationDataProfiler::dataset() asks three questions about where a
 * dataset came from, and each is a COUNT(DISTINCT …) scoped to one tenant and
 * one dataset:
 *
 *   source_file     → how many files this dataset was assembled from
 *   import_job_id   → how many import runs contributed to it
 *   natural_key     → distinct subjects, whose shortfall against the row count
 *                     is the duplication figure the gap detector reports
 *
 * None had an index a tenant-scoped count could use. `natural_key` looks
 * covered — there is a unique index carrying it — but it sits third in that
 * index, and a count filtered by tenant_id and dataset cannot use a prefix it
 * does not lead. Measured on the development database, one tenant's 27,000-row
 * dataset: COUNT(DISTINCT source_file) took 143 seconds on its own.
 *
 * SEPARATE FROM THE MEASURE INDEXES BESIDE IT, and deliberately so. Each ALTER
 * on this table runs for several minutes against a remote database; splitting
 * the work means an operator who has to stop halfway leaves the migrations
 * table telling the truth about what has been applied, rather than one entry
 * that may or may not have finished.
 *
 * EXPECT THIS ONE TO TAKE A WHILE. Three index builds over the whole table,
 * roughly seven minutes each on the development database. It is safe to
 * interrupt and re-run: an index already present is skipped.
 *
 * IDEMPOTENT AND MYSQL-ONLY, matching every other index migration here — the
 * suite runs on SQLite, where these buy nothing and the information_schema
 * probe does not exist.
 */
return new class extends Migration
{
    private const TABLE = 'hpbrain_operational_records';

    /** @var array<string, string> index name => column */
    private const INDEXES = [
        'idx_oprec_tenant_dataset_source_file'  => 'source_file',
        'idx_oprec_tenant_dataset_import_job_c' => 'import_job_id',
        'idx_oprec_tenant_dataset_natural_key'  => 'natural_key',
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
