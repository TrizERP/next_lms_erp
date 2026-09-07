<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_01_01_003100_context_library.php
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
 * Ported verbatim from database/migrations/032_context_library.sql.
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
        if (!Schema::hasTable('hpbrain_context_entities')) {
            DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_context_entities (
  id                    VARCHAR(36) PRIMARY KEY,
  tenant_id             VARCHAR(36) NOT NULL,
  org_id                VARCHAR(36) NOT NULL,
  
  entity_type           VARCHAR(100) NOT NULL,
  key_term              VARCHAR(255) NOT NULL,
  
  canonical_meaning      TEXT,
  
  
  
  tenant_specific_value  JSON NOT NULL DEFAULT (\'{}\'),
  status                VARCHAR(50) NOT NULL DEFAULT \'active\',
  created_by            VARCHAR(36) NOT NULL,
  created_date          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_date          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT context_entities_tenant_term_unique UNIQUE (tenant_id, entity_type, key_term)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        }
        if (Schema::hasTable('hpbrain_context_entities')) {
            $idx = DB::select("SHOW INDEX FROM hpbrain_context_entities WHERE Key_name = 'idx_context_entities_org'");
            if (empty($idx)) DB::unprepared('CREATE INDEX idx_context_entities_org ON hpbrain_context_entities (tenant_id, org_id)');
            $idx = DB::select("SHOW INDEX FROM hpbrain_context_entities WHERE Key_name = 'idx_context_entities_type'");
            if (empty($idx)) DB::unprepared('CREATE INDEX idx_context_entities_type ON hpbrain_context_entities (tenant_id, entity_type)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hpbrain_context_entities');
    }
};
