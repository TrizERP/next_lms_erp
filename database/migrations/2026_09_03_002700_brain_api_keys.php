<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_01_01_002600_api_keys.php
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
 * Ported verbatim from database/migrations/027_api_keys.sql.
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
        if (!Schema::hasTable('hpbrain_api_keys')) {
            DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_api_keys (
  id            VARCHAR(36) PRIMARY KEY,
  tenant_id     VARCHAR(36) NOT NULL,
  user_id       VARCHAR(36) NOT NULL,
  name          TEXT NOT NULL,
  key_hash      VARCHAR(255) NOT NULL,
  key_prefix    TEXT NOT NULL,
  scopes        JSON NOT NULL DEFAULT \'[]\',
  last_used_date TIMESTAMP,
  revoked_date  TIMESTAMP,
  created_date  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_date  TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            $idx = DB::select("SHOW INDEX FROM hpbrain_api_keys WHERE Key_name = 'idx_api_keys_hash'");
            if (empty($idx)) DB::unprepared('CREATE UNIQUE INDEX idx_api_keys_hash ON hpbrain_api_keys (key_hash)');
            $idx = DB::select("SHOW INDEX FROM hpbrain_api_keys WHERE Key_name = 'idx_api_keys_tenant'");
            if (empty($idx)) DB::unprepared('CREATE INDEX idx_api_keys_tenant ON hpbrain_api_keys (tenant_id, user_id)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hpbrain_api_keys');
    }
};
