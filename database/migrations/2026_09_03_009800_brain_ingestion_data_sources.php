<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_08_06_000100_ingestion_data_sources.php
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
 * hpbrain_data_sources — the tenant's configured ingestion sources.
 *
 * WHY A NEW TABLE RATHER THAN AN ALTER ON `data_sources`.
 * The existing `data_sources` table has two defects recorded in
 * docs/API-FUNCTIONAL-AUDIT.md, and neither is fixable by adding a column:
 *
 *   1. IT IS UNPREFIXED. This installation shares its database with the
 *      institute ERP, which is the entire reason every other table in this
 *      application carries the hpbrain_ prefix. A bare `data_sources` is a
 *      name collision waiting for the ERP to want the same word.
 *
 *   2. IT HAS NO tenant_id. Every repository in this application scopes by
 *      tenant through BaseRepository::scoped(); a table without the column
 *      cannot participate, and GET /ingestion/sources/{tenantId} would have
 *      had to answer with every tenant's sources or none.
 *
 * RENAMING IN PLACE WOULD BE THE TIDIER STORY AND IS THE WRONG ONE: a rename
 * plus an ALTER on a table that another deployment may already be writing to
 * is a migration that can half-apply. Both `data_sources` and `ingestion_runs`
 * hold ZERO rows on this installation (verified 2026-08-06), so there is
 * nothing to carry across and this table starts clean.
 *
 * THE OLD TABLES ARE DELIBERATELY NOT DROPPED. The audit's instruction was to
 * verify before removing, and a migration that drops a table it did not create
 * removes the only evidence of what was there. Dropping them is a separate,
 * reversible decision once nothing references them.
 *
 * source_key, NOT source_id. It is the name the client's DataSourceRow
 * declares (web/src/api/ingestion.ts), and `source_id` beside a VARCHAR(36)
 * `id` on the same row reads as a foreign key to something.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_data_sources (
  id             VARCHAR(36) PRIMARY KEY,
  tenant_id      VARCHAR(36) NOT NULL,
  source_key     VARCHAR(190) NOT NULL,
  display_name   VARCHAR(255) NOT NULL,
  source_type    VARCHAR(50) NOT NULL DEFAULT \'csv_upload\',
  config         JSON NULL,
  is_active      BOOLEAN NOT NULL DEFAULT TRUE,
  last_synced_at DATETIME NULL,
  created_by     VARCHAR(255) NOT NULL,
  created_date   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_date   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT data_sources_tenant_key_unique UNIQUE (tenant_id, source_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        DB::unprepared('CREATE INDEX idx_data_sources_tenant ON hpbrain_data_sources (tenant_id, is_active)');
    }

    public function down(): void
    {
        Schema::dropIfExists('hpbrain_data_sources');
    }
};
