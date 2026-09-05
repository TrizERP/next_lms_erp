<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_08_10_000100_data_sources_field_map_and_checkpoint.php
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
 * Add the two columns DataSourceRepository has always written and the table has
 * never had.
 *
 * THE DRIFT. 2026_08_06_000100_ingestion_data_sources created
 * hpbrain_data_sources with (id, tenant_id, source_key, display_name,
 * source_type, config, is_active, last_synced_at, created_by, created_date,
 * updated_date). DataSourceRepository — written afterwards — reads and writes
 * `field_map` and `checkpoint`, which are in neither the migration nor the live
 * database.
 *
 * HOW IT STAYED HIDDEN. Only two paths touch those columns: saveFieldMap(),
 * reached when an ingestion commit is sent with save_map = true, and the
 * checkpoint update on the internal ERP source. Every ingestion commit against
 * the live database therefore died with
 *
 *     SQLSTATE[42S22]: Unknown column 'field_map' in 'field list'
 *
 * — but only AFTER writing all its signals and evidence, so the failure looked
 * like a 500 at the end of a long request rather than a missing column. On this
 * remote database the commit was also exceeding the 60-second execution limit
 * before it ever got that far, which masked the error entirely: the timeout was
 * reported instead. Fixing the per-row round trips is what surfaced it.
 *
 * NOT STORED INSIDE `config`. Folding them into the existing JSON column would
 * avoid a migration, but the repository's list()/find() project `field_map` as
 * a first-class column and its jsonColumns() declares it decodable; making it a
 * nested key would require changing four methods to work around a schema the
 * code already assumes. Two nullable columns is the smaller, more honest edit.
 *
 * SAFETY. Both steps are guarded, so running this against a database that
 * already has either column is a metadata read and nothing else. The table is
 * Brain-owned (hpbrain_ prefix); no ERP table is touched.
 */
return new class extends Migration
{
    private const TABLE = 'hpbrain_data_sources';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        // JSON, matching how the repository encodes it and how `config` is
        // stored on the same table. LONGTEXT on MySQL either way.
        if (! Schema::hasColumn(self::TABLE, 'field_map')) {
            DB::statement('ALTER TABLE '.self::TABLE.' ADD COLUMN field_map LONGTEXT NULL AFTER config');
        }

        // Opaque resume token for incremental sources — an id, a timestamp or a
        // cursor depending on the source, so it is a string rather than typed.
        if (! Schema::hasColumn(self::TABLE, 'checkpoint')) {
            DB::statement('ALTER TABLE '.self::TABLE.' ADD COLUMN checkpoint VARCHAR(255) NULL AFTER field_map');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        foreach (['checkpoint', 'field_map'] as $column) {
            if (Schema::hasColumn(self::TABLE, $column)) {
                DB::statement('ALTER TABLE '.self::TABLE.' DROP COLUMN '.$column);
            }
        }
    }
};
