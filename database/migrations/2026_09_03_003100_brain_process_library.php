<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_01_01_003000_process_library.php
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
 * Ported verbatim from database/migrations/031_process_library.sql.
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
        if (!Schema::hasTable('hpbrain_process_definitions')) {
            DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_process_definitions (
  id              VARCHAR(36) PRIMARY KEY,
  tenant_id       VARCHAR(36) NOT NULL,
  org_id          VARCHAR(36) NOT NULL,
  process_code    VARCHAR(255) NOT NULL,
  name            VARCHAR(255) NOT NULL,
  description     TEXT,
  category        VARCHAR(255) NOT NULL DEFAULT \'general\',
  
  
  steps           JSON NOT NULL DEFAULT (\'[]\'),
  version         INT NOT NULL DEFAULT 1,
  status          VARCHAR(50) NOT NULL DEFAULT \'draft\',
  created_by      VARCHAR(36) NOT NULL,
  created_date    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_date    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT process_definitions_tenant_code_unique UNIQUE (tenant_id, process_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        }
        if (Schema::hasTable('hpbrain_process_definitions')) {
            $idx = DB::select("SHOW INDEX FROM hpbrain_process_definitions WHERE Key_name = 'idx_process_definitions_org'");
            if (empty($idx)) DB::unprepared('CREATE INDEX idx_process_definitions_org ON hpbrain_process_definitions (tenant_id, org_id)');
            $idx = DB::select("SHOW INDEX FROM hpbrain_process_definitions WHERE Key_name = 'idx_process_definitions_status'");
            if (empty($idx)) DB::unprepared('CREATE INDEX idx_process_definitions_status ON hpbrain_process_definitions (tenant_id, status)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hpbrain_process_definitions');
    }
};
