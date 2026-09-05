<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_01_01_002100_kasba_task_hierarchy.php
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
 * Ported verbatim from database/migrations/022_kasba_task_hierarchy.sql.
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
        if (!Schema::hasTable('hpbrain_capability_tasks')) {
            DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_capability_tasks (
  id                VARCHAR(36) PRIMARY KEY,
  tenant_id         VARCHAR(36) NOT NULL,
  capability_id     VARCHAR(36) NOT NULL REFERENCES hpbrain_capabilities(id),
  parent_task_id    VARCHAR(36) REFERENCES hpbrain_capability_tasks(id),
  name              TEXT NOT NULL,
  description       TEXT,
  evidence_required BOOLEAN NOT NULL DEFAULT false,
  status            TEXT NOT NULL DEFAULT \'active\',
  created_by        TEXT NOT NULL,
  created_date      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        }
        if (Schema::hasTable('hpbrain_capability_tasks')) {
            $idx = DB::select("SHOW INDEX FROM hpbrain_capability_tasks WHERE Key_name = 'idx_capability_tasks_capability'");
            if (empty($idx)) DB::unprepared('CREATE INDEX idx_capability_tasks_capability ON hpbrain_capability_tasks (tenant_id, capability_id)');
            $idx = DB::select("SHOW INDEX FROM hpbrain_capability_tasks WHERE Key_name = 'idx_capability_tasks_parent'");
            if (empty($idx)) DB::unprepared('CREATE INDEX idx_capability_tasks_parent ON hpbrain_capability_tasks (tenant_id, parent_task_id)');
        }

        if (!Schema::hasTable('hpbrain_capability_proficiency')) {
            DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_capability_proficiency (
  id                    VARCHAR(36) PRIMARY KEY,
  tenant_id             VARCHAR(36) NOT NULL,
  assignment_id         VARCHAR(36) NOT NULL REFERENCES hpbrain_capability_assignments(id),
  knowledge_level DECIMAL(12,4),
  ability_level DECIMAL(12,4),
  skill_level DECIMAL(12,4),
  behaviour_level DECIMAL(12,4),
  attitude_level DECIMAL(12,4),
  evidence_confidence DECIMAL(6,4),
  assessed_by           TEXT,
  assessed_date         TIMESTAMP,
  created_date          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            $idx = DB::select("SHOW INDEX FROM hpbrain_capability_proficiency WHERE Key_name = 'idx_capability_proficiency_assignment'");
            if (empty($idx)) DB::unprepared('CREATE INDEX idx_capability_proficiency_assignment ON hpbrain_capability_proficiency (tenant_id, assignment_id)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hpbrain_capability_proficiency');
        Schema::dropIfExists('hpbrain_capability_tasks');
    }
};
