<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_01_01_002200_eso_execution_evidence.php
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
 * Ported verbatim from database/migrations/023_eso_execution_evidence.sql.
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
        if (!Schema::hasTable('hpbrain_eso_execution_evidence')) {
            DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_eso_execution_evidence (
  tenant_id     VARCHAR(36) NOT NULL,
  execution_id  VARCHAR(36) NOT NULL REFERENCES hpbrain_eso_executions(id),
  evidence_id   VARCHAR(36) NOT NULL REFERENCES hpbrain_evidence(id),
  linked_date   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (execution_id, evidence_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            $idx = DB::select("SHOW INDEX FROM hpbrain_eso_execution_evidence WHERE Key_name = 'idx_eso_execution_evidence_tenant'");
            if (empty($idx)) DB::unprepared('CREATE INDEX idx_eso_execution_evidence_tenant ON hpbrain_eso_execution_evidence (tenant_id)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hpbrain_eso_execution_evidence');
    }
};
