<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_01_01_002300_career_taxonomy.php
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
 * Ported verbatim from database/migrations/024_career_taxonomy.sql.
 *
 * The SQL is kept as raw statements rather than rewritten into Schema
 * builder calls. That is deliberate: this DDL has been executed against a
 * live MySQL 8 server and carries three hard-won corrections that a
 * Schema-builder rewrite would silently undo —
 *   1. every table is hpbrain_-prefixed (the Brain shares a database with
 *      the institute ERP and must not collide with it),
 *   2. every id/foreign-key column is VARCHAR(36), because MySQL rejects
 *      TEXT in a key (error 1170),
 *   3. every ratio column states explicit precision, because a bare
 *      NUMERIC becomes DECIMAL(10,0) in MySQL and rounds confidence to an
 *      integer.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('hpbrain_career_clusters')) {
            DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_career_clusters (
  id            VARCHAR(36) PRIMARY KEY,
  tenant_id     VARCHAR(36) NOT NULL,
  code          VARCHAR(255) NOT NULL,
  name          TEXT NOT NULL,
  description   TEXT,
  created_by    TEXT NOT NULL,
  created_date  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            $idx = DB::select("SHOW INDEX FROM hpbrain_career_clusters WHERE Key_name = 'idx_career_clusters_code'");
            if (empty($idx)) DB::unprepared('CREATE UNIQUE INDEX idx_career_clusters_code ON hpbrain_career_clusters (tenant_id, code)');
        }

        if (!Schema::hasTable('hpbrain_occupations')) {
            DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_occupations (
  id                VARCHAR(36) PRIMARY KEY,
  tenant_id         VARCHAR(36) NOT NULL,
  cluster_id        VARCHAR(36) REFERENCES hpbrain_career_clusters(id),
  occupation_code   VARCHAR(255) NOT NULL,
  title             TEXT NOT NULL,
  description       TEXT,
  created_by        TEXT NOT NULL,
  created_date      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            $idx = DB::select("SHOW INDEX FROM hpbrain_occupations WHERE Key_name = 'idx_occupations_code'");
            if (empty($idx)) DB::unprepared('CREATE UNIQUE INDEX idx_occupations_code ON hpbrain_occupations (tenant_id, occupation_code)');
        }

        if (!Schema::hasTable('hpbrain_occupation_capability_requirements')) {
            DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_occupation_capability_requirements (
  tenant_id       VARCHAR(36) NOT NULL,
  occupation_id   VARCHAR(36) NOT NULL REFERENCES hpbrain_occupations(id),
  capability_id   VARCHAR(36) NOT NULL REFERENCES hpbrain_capabilities(id),
  required_level DECIMAL(5,2) NOT NULL DEFAULT 3,
  PRIMARY KEY (occupation_id, capability_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            $idx = DB::select("SHOW INDEX FROM hpbrain_occupation_capability_requirements WHERE Key_name = 'idx_occupation_requirements_tenant'");
            if (empty($idx)) DB::unprepared('CREATE INDEX idx_occupation_requirements_tenant ON hpbrain_occupation_capability_requirements (tenant_id)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hpbrain_occupation_capability_requirements');
        Schema::dropIfExists('hpbrain_occupations');
        Schema::dropIfExists('hpbrain_career_clusters');
    }
};
