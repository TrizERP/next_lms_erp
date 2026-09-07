<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_01_01_003300_eso_definition_library.php
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
 * Ported verbatim from database/migrations/034_eso_definition_library.sql.
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
        if (!Schema::hasTable('hpbrain_eso_definitions')) {
            DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_eso_definitions (
   
  id                      VARCHAR(36) PRIMARY KEY,
  tenant_id               VARCHAR(36) NOT NULL,
  org_id                  VARCHAR(36) NOT NULL,
  eso_code                VARCHAR(255) NOT NULL,
  name                    VARCHAR(255) NOT NULL,
  version                 INT NOT NULL DEFAULT 1,
  status                  VARCHAR(50) NOT NULL DEFAULT \'draft\', 
  owner                   VARCHAR(36),
  provenance              VARCHAR(100) NOT NULL DEFAULT \'authored\', 
  kasba_node_id           VARCHAR(36),
  kasba_node_type         VARCHAR(100), 
  is_cognitive_primitive  BOOLEAN NOT NULL DEFAULT FALSE,

   
  trigger_description     TEXT,      
  applicable_contexts     JSON NOT NULL DEFAULT (\'[]\'),
  gap_types                JSON NOT NULL DEFAULT (\'[]\'),

   
  objective               VARCHAR(50) NOT NULL, 
  inputs                  JSON NOT NULL DEFAULT (\'[]\'),
  outputs                 JSON NOT NULL DEFAULT (\'[]\'),
  preconditions           JSON NOT NULL DEFAULT (\'[]\'),
  prerequisites           JSON NOT NULL DEFAULT (\'[]\'), 
  constraints_policies    JSON NOT NULL DEFAULT (\'[]\'),

   
  procedure_steps         JSON NOT NULL DEFAULT (\'[]\'), 

   
  allowed_executor_classes JSON NOT NULL DEFAULT (\'[]\'), 
  trust_level              VARCHAR(50) NOT NULL DEFAULT \'observe\', 
  routing_criteria         JSON NOT NULL DEFAULT (\'{}\'),
  escalation_path          JSON NOT NULL DEFAULT (\'[]\'),

   
  scaffolding              JSON NOT NULL DEFAULT (\'{}\'),

   
  gotchas                  JSON NOT NULL DEFAULT (\'[]\'),

   
  assessment                JSON NOT NULL DEFAULT (\'{}\'), 

   
  evidence_hooks             JSON NOT NULL DEFAULT (\'{}\'),

   
  resources                  JSON NOT NULL DEFAULT (\'[]\'), 
  composed_of                  JSON NOT NULL DEFAULT (\'[]\'), 
  composes_into                 JSON NOT NULL DEFAULT (\'[]\'),
  supersedes                    VARCHAR(36),
  superseded_by                 VARCHAR(36),

  created_by               VARCHAR(36) NOT NULL,
  created_date              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_date               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT eso_definitions_tenant_code_unique UNIQUE (tenant_id, eso_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        }
        if (Schema::hasTable('hpbrain_eso_definitions')) {
            $idx = DB::select("SHOW INDEX FROM hpbrain_eso_definitions WHERE Key_name = 'idx_eso_definitions_org'");
            if (empty($idx)) DB::unprepared('CREATE INDEX idx_eso_definitions_org ON hpbrain_eso_definitions (tenant_id, org_id)');
            $idx = DB::select("SHOW INDEX FROM hpbrain_eso_definitions WHERE Key_name = 'idx_eso_definitions_objective'");
            if (empty($idx)) DB::unprepared('CREATE INDEX idx_eso_definitions_objective ON hpbrain_eso_definitions (tenant_id, objective)');
            $idx = DB::select("SHOW INDEX FROM hpbrain_eso_definitions WHERE Key_name = 'idx_eso_definitions_status'");
            if (empty($idx)) DB::unprepared('CREATE INDEX idx_eso_definitions_status ON hpbrain_eso_definitions (tenant_id, status)');
            $idx = DB::select("SHOW INDEX FROM hpbrain_eso_definitions WHERE Key_name = 'idx_eso_definitions_kasba_node'");
            if (empty($idx)) DB::unprepared('CREATE INDEX idx_eso_definitions_kasba_node ON hpbrain_eso_definitions (tenant_id, kasba_node_id)');
        }
        if (Schema::hasTable('hpbrain_eso_executions') && !Schema::hasColumn('hpbrain_eso_executions', 'eso_definition_id')) {
            DB::unprepared('ALTER TABLE hpbrain_eso_executions ADD COLUMN eso_definition_id VARCHAR(36)');
            $idx = DB::select("SHOW INDEX FROM hpbrain_eso_executions WHERE Key_name = 'idx_eso_executions_definition'");
            if (empty($idx)) DB::unprepared('CREATE INDEX idx_eso_executions_definition ON hpbrain_eso_executions (eso_definition_id)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hpbrain_eso_definitions');
    }
};
