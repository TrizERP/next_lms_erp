<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_01_01_002000_knowledge_assets.php
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
 * Ported verbatim from database/migrations/021_knowledge_assets.sql.
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
        if (!Schema::hasTable('hpbrain_knowledge_assets')) {
            DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_knowledge_assets (
  id                VARCHAR(36) PRIMARY KEY,
  tenant_id         VARCHAR(36) NOT NULL,
  title             TEXT NOT NULL,
  category          VARCHAR(255) NOT NULL,
  content           TEXT NOT NULL,
  tags              JSON NOT NULL DEFAULT \'[]\',
  confidence DECIMAL(6,4) NOT NULL DEFAULT 0.7,
  department_id     VARCHAR(36),
  related_person_ids JSON NOT NULL DEFAULT \'[]\',
  related_capability_ids JSON NOT NULL DEFAULT \'[]\',
  reuse_count       INTEGER NOT NULL DEFAULT 0,
  status            TEXT NOT NULL DEFAULT \'active\',
  created_by        TEXT NOT NULL,
  created_date      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_date      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        }
        if (Schema::hasTable('hpbrain_knowledge_assets')) {
            $idx = DB::select("SHOW INDEX FROM hpbrain_knowledge_assets WHERE Key_name = 'idx_knowledge_assets_tenant'");
            if (empty($idx)) DB::unprepared('CREATE INDEX idx_knowledge_assets_tenant ON hpbrain_knowledge_assets (tenant_id, category)');
            $idx = DB::select("SHOW INDEX FROM hpbrain_knowledge_assets WHERE Key_name = 'idx_knowledge_assets_department'");
            if (empty($idx)) DB::unprepared('CREATE INDEX idx_knowledge_assets_department ON hpbrain_knowledge_assets (tenant_id, department_id)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hpbrain_knowledge_assets');
    }
};
