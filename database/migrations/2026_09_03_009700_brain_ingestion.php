<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_08_05_000100_ingestion.php
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
 * Ingestion, on top of the import tables that already exist.
 *
 * WHY NO `ingestion_runs` TABLE. hpbrain_import_jobs and hpbrain_import_logs
 * already model exactly this: a run with row counts, per-row outcomes, an error
 * report and rollback data, plus nine live /api/v1/imports/* routes and two
 * repositories on top of them. A second runs table would have meant two
 * half-populated histories, two rollback paths, and a UI that had to ask which
 * kind of import it was looking at before it could show anything. Three
 * nullable columns is the whole cost of reusing what is there.
 *
 * WHAT IS ACTUALLY NEW. Only the source registry — the thing import_jobs has
 * no concept of. A job says what happened; a data source says where it can
 * happen again, and holds the checkpoint that makes the next run incremental
 * rather than a re-import of everything.
 *
 * TENANT_ID IS NOT OPTIONAL HERE. Every hpbrain_ table carries it and every
 * read goes through BaseRepository::scoped(). A source registry without it
 * would let one tenant's configured source be listed, edited, or run by
 * another — and because a source names ERP tables, running someone else's
 * source is a cross-tenant read, not a display bug.
 *
 * IDs ARE VARCHAR(36), NOT AUTO-INCREMENT. Everything these rows will ever join
 * to — import jobs, signals, evidence, event-store entity ids — is a UUID
 * string. A BIGINT here would have to be cast at every join.
 *
 * GUARDED THROUGHOUT. This migration runs against a database the institute ERP
 * shares, and ALTER TABLE is not idempotent the way CREATE TABLE IF NOT EXISTS
 * is. Every statement below checks first, so a re-run is a no-op rather than
 * SQLSTATE 42S21.
 */
return new class extends Migration
{
    private const JOBS = 'hpbrain_import_jobs';

    private const SOURCES = 'hpbrain_data_sources';

    public function up(): void
    {
        DB::unprepared("CREATE TABLE IF NOT EXISTS ".self::SOURCES." (
  id              VARCHAR(36) PRIMARY KEY,
  tenant_id       VARCHAR(36) NOT NULL,
  source_key      VARCHAR(191) NOT NULL,
  source_type     VARCHAR(50) NOT NULL,
  display_name    VARCHAR(255) NOT NULL,
  universal_entity VARCHAR(100) NULL,
  field_map       JSON NULL,
  checkpoint      VARCHAR(255) NULL,
  last_synced_at  DATETIME NULL,
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  created_by      TEXT NOT NULL,
  created_date    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_date    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT data_sources_tenant_key_unique UNIQUE (tenant_id, source_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->addIndex(self::SOURCES, 'idx_data_sources_tenant_active', 'tenant_id, is_active');

        // The three columns import_jobs lacks. All nullable, so every job row
        // written before today stays valid and the existing /imports/* routes
        // keep working untouched.
        //
        // source_ref is what makes preview-then-commit possible without holding
        // the parsed rows in a session: it records WHERE the batch came from
        // (an upload path on the local disk, or an ERP checkpoint), so commit
        // re-reads the same source rather than trusting a client to send the
        // rows back.
        $this->addColumn('source_id', "VARCHAR(191) NULL");
        $this->addColumn('sync_type', "VARCHAR(50) NULL");
        $this->addColumn('source_ref', "TEXT NULL");

        $this->addIndex(self::JOBS, 'idx_import_jobs_source_id', 'source_id');
    }

    public function down(): void
    {
        // The added columns are deliberately NOT dropped. Dropping source_ref
        // would destroy the only record of where a completed import came from,
        // and provenance that can be rolled back by a schema migration is not
        // provenance. The table goes; the history stays.
        Schema::dropIfExists(self::SOURCES);
    }

    private function addColumn(string $column, string $definition): void
    {
        if (! Schema::hasTable(self::JOBS) || Schema::hasColumn(self::JOBS, $column)) {
            return;
        }

        DB::unprepared('ALTER TABLE '.self::JOBS." ADD COLUMN {$column} {$definition}");
    }

    /**
     * CREATE INDEX has no IF NOT EXISTS on MySQL or MariaDB, and a duplicate is
     * error 1061 — which aborts the migration and every migration after it.
     * Checking the catalogue first is the only portable guard.
     */
    private function addIndex(string $table, string $name, string $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::unprepared("CREATE INDEX IF NOT EXISTS {$name} ON {$table} ({$columns})");

            return;
        }

        $exists = DB::selectOne(
            'SELECT 1 AS present FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            [$table, $name],
        );

        if ($exists === null) {
            DB::unprepared("CREATE INDEX {$name} ON {$table} ({$columns})");
        }
    }
};
