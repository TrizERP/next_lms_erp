<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_01_01_000700_intelligence_entities.php
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
 * Ported verbatim from database/migrations/008_intelligence_entities.sql.
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
        DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_signals (
  id                   VARCHAR(36) PRIMARY KEY,
  tenant_id            VARCHAR(36) NOT NULL,
  org_id               VARCHAR(36) NOT NULL,
  source               TEXT NOT NULL,
  classification       TEXT NOT NULL DEFAULT \'unclassified\',
  priority             TEXT NOT NULL DEFAULT \'normal\',
  severity             TEXT NOT NULL DEFAULT \'low\',
  confidence DECIMAL(6,4) NOT NULL DEFAULT 0.5,
  related_entity_type  TEXT,
  related_entity_id    VARCHAR(36),
  status               VARCHAR(255) NOT NULL DEFAULT \'new\',
  metadata             JSON NOT NULL DEFAULT \'{}\',
  created_by           TEXT NOT NULL,
  created_date         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_date         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        DB::unprepared('CREATE INDEX idx_signals_tenant ON hpbrain_signals (tenant_id)');
        DB::unprepared('CREATE INDEX idx_signals_status ON hpbrain_signals (tenant_id, status)');
        DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_reasoning_steps (
  id                   VARCHAR(36) PRIMARY KEY,
  tenant_id            VARCHAR(36) NOT NULL,
  case_id              VARCHAR(36),
  signal_id            VARCHAR(36) REFERENCES hpbrain_signals(id),
  mental_model_id      VARCHAR(36),
  step_order           INTEGER NOT NULL,
  description          TEXT NOT NULL,
  confidence_score DECIMAL(6,4) NOT NULL DEFAULT 0.5,
  created_by           TEXT NOT NULL,
  created_date         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        DB::unprepared('CREATE INDEX idx_reasoning_steps_tenant ON hpbrain_reasoning_steps (tenant_id)');
        DB::unprepared('CREATE INDEX idx_reasoning_steps_signal ON hpbrain_reasoning_steps (tenant_id, signal_id)');
        DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_recommendations (
  id                   VARCHAR(36) PRIMARY KEY,
  tenant_id            VARCHAR(36) NOT NULL,
  reasoning_step_id    VARCHAR(36) REFERENCES hpbrain_reasoning_steps(id),
  category             TEXT NOT NULL DEFAULT \'watch\',
  title                TEXT NOT NULL,
  description          TEXT,
  priority             TEXT NOT NULL DEFAULT \'medium\',
  confidence DECIMAL(6,4) NOT NULL DEFAULT 0.5,
  impact               TEXT,
  cost                 TEXT,
  risk                 TEXT,
  dependencies         JSON NOT NULL DEFAULT \'[]\',
  status               TEXT NOT NULL DEFAULT \'pending\',
  created_by           TEXT NOT NULL,
  created_date         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_date         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        DB::unprepared('CREATE INDEX idx_recommendations_tenant ON hpbrain_recommendations (tenant_id)');
        DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_decisions (
  id                     VARCHAR(36) PRIMARY KEY,
  tenant_id              VARCHAR(36) NOT NULL,
  recommendation_id      VARCHAR(36) REFERENCES hpbrain_recommendations(id),
  decided_by             VARCHAR(36) NOT NULL,
  executor_type          TEXT NOT NULL DEFAULT \'human\',
  rationale              TEXT NOT NULL,
  alternatives_considered JSON NOT NULL DEFAULT \'[]\',
  status                 TEXT NOT NULL DEFAULT \'approved\',
  created_date           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        DB::unprepared('CREATE INDEX idx_decisions_tenant ON hpbrain_decisions (tenant_id)');
        DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_mental_models (
  id                   VARCHAR(36) PRIMARY KEY,
  tenant_id            VARCHAR(36) NOT NULL,
  name                 TEXT NOT NULL,
  description          TEXT,
  domain               TEXT NOT NULL,
  rules                JSON NOT NULL DEFAULT \'{}\',
  version              INTEGER NOT NULL DEFAULT 1,
  status               TEXT NOT NULL DEFAULT \'active\',
  created_by           TEXT NOT NULL,
  created_date         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_date         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        DB::unprepared('CREATE INDEX idx_mental_models_tenant ON hpbrain_mental_models (tenant_id)');
        DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_policies (
  id                     VARCHAR(36) PRIMARY KEY,
  tenant_id              VARCHAR(36) NOT NULL,
  name                   TEXT NOT NULL,
  scope                  TEXT NOT NULL,
  allowed_executor_classes JSON NOT NULL DEFAULT \'[]\',
  trust_levels            JSON NOT NULL DEFAULT \'[]\',
  routing_criteria        JSON NOT NULL DEFAULT \'{}\',
  escalation_path         JSON NOT NULL DEFAULT \'[]\',
  status                  TEXT NOT NULL DEFAULT \'active\',
  created_by              TEXT NOT NULL,
  created_date            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_date             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        DB::unprepared('CREATE INDEX idx_policies_tenant ON hpbrain_policies (tenant_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('hpbrain_policies');
        Schema::dropIfExists('hpbrain_mental_models');
        Schema::dropIfExists('hpbrain_decisions');
        Schema::dropIfExists('hpbrain_recommendations');
        Schema::dropIfExists('hpbrain_reasoning_steps');
        Schema::dropIfExists('hpbrain_signals');
    }
};
