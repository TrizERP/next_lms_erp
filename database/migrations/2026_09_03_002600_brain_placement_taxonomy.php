<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_01_01_002500_placement_taxonomy.php
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
 * Ported verbatim from database/migrations/026_placement_taxonomy.sql.
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
        if (!Schema::hasTable('hpbrain_placement_companies')) {
            DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_placement_companies (
  id                VARCHAR(36) PRIMARY KEY,
  tenant_id         VARCHAR(36) NOT NULL,
  name              TEXT NOT NULL,
  industry          TEXT,
  preferred_skills  JSON NOT NULL DEFAULT \'[]\',
  notes             TEXT,
  created_by        TEXT NOT NULL,
  created_date      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        }
        if (!Schema::hasTable('hpbrain_placement_job_roles')) {
            DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_placement_job_roles (
  id                VARCHAR(36) PRIMARY KEY,
  tenant_id         VARCHAR(36) NOT NULL,
  company_id        VARCHAR(36) NOT NULL REFERENCES hpbrain_placement_companies(id),
  title             TEXT NOT NULL,
  description       TEXT,
  min_salary DECIMAL(12,4),
  max_salary DECIMAL(12,4),
  status            TEXT NOT NULL DEFAULT \'open\',
  created_by        TEXT NOT NULL,
  created_date      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            $idx = DB::select("SHOW INDEX FROM hpbrain_placement_job_roles WHERE Key_name = 'idx_placement_job_roles_company'");
            if (empty($idx)) DB::unprepared('CREATE INDEX idx_placement_job_roles_company ON hpbrain_placement_job_roles (tenant_id, company_id)');
        }
        if (!Schema::hasTable('hpbrain_job_role_capability_requirements')) {
            DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_job_role_capability_requirements (
  tenant_id       VARCHAR(36) NOT NULL,
  job_role_id     VARCHAR(36) NOT NULL REFERENCES hpbrain_placement_job_roles(id),
  capability_id   VARCHAR(36) NOT NULL REFERENCES hpbrain_capabilities(id),
  required_level DECIMAL(5,2) NOT NULL DEFAULT 3,
  PRIMARY KEY (job_role_id, capability_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            $idx = DB::select("SHOW INDEX FROM hpbrain_job_role_capability_requirements WHERE Key_name = 'idx_job_role_requirements_tenant'");
            if (empty($idx)) DB::unprepared('CREATE INDEX idx_job_role_requirements_tenant ON hpbrain_job_role_capability_requirements (tenant_id)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hpbrain_job_role_capability_requirements');
        Schema::dropIfExists('hpbrain_placement_job_roles');
        Schema::dropIfExists('hpbrain_placement_companies');
    }
};
