<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ported verbatim from database/migrations/016_case_engine.sql.
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
        if (!Schema::hasTable('hpbrain_cases')) {
            DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_cases (
  id                VARCHAR(36) PRIMARY KEY,
  tenant_id         VARCHAR(36) NOT NULL,
  signal_id         VARCHAR(36) REFERENCES hpbrain_signals(id), 
  title             TEXT NOT NULL,
  description       TEXT,
  status            VARCHAR(255) NOT NULL DEFAULT \'open\', 
  resolved_hypothesis_id VARCHAR(36),
  created_by        TEXT NOT NULL,
  created_date      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_date      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        }
        if (Schema::hasTable('hpbrain_cases')) {
            $idx = DB::select("SHOW INDEX FROM hpbrain_cases WHERE Key_name = 'idx_cases_tenant'");
            if (empty($idx)) DB::unprepared('CREATE INDEX idx_cases_tenant ON hpbrain_cases (tenant_id)');
            $idx = DB::select("SHOW INDEX FROM hpbrain_cases WHERE Key_name = 'idx_cases_signal'");
            if (empty($idx)) DB::unprepared('CREATE INDEX idx_cases_signal ON hpbrain_cases (tenant_id, signal_id)');
            $idx = DB::select("SHOW INDEX FROM hpbrain_cases WHERE Key_name = 'idx_cases_status'");
            if (empty($idx)) DB::unprepared('CREATE INDEX idx_cases_status ON hpbrain_cases (tenant_id, status)');
        }

        if (!Schema::hasTable('hpbrain_hypotheses')) {
            DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_hypotheses (
  id                VARCHAR(36) PRIMARY KEY,
  tenant_id         VARCHAR(36) NOT NULL,
  case_id           VARCHAR(36) NOT NULL REFERENCES hpbrain_cases(id),
  statement         TEXT NOT NULL, 
  root_cause_family TEXT NOT NULL, 
  confidence DECIMAL(6,4) NOT NULL DEFAULT 0.5,
  status            TEXT NOT NULL DEFAULT \'proposed\', 
  supporting_evidence_ids JSON NOT NULL DEFAULT \'[]\', 
  rejected_reason   TEXT, 
  proposed_by       TEXT NOT NULL, 
  created_date      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        }
        if (Schema::hasTable('hpbrain_hypotheses')) {
            $idx = DB::select("SHOW INDEX FROM hpbrain_hypotheses WHERE Key_name = 'idx_hypotheses_tenant'");
            if (empty($idx)) DB::unprepared('CREATE INDEX idx_hypotheses_tenant ON hpbrain_hypotheses (tenant_id)');
            $idx = DB::select("SHOW INDEX FROM hpbrain_hypotheses WHERE Key_name = 'idx_hypotheses_case'");
            if (empty($idx)) DB::unprepared('CREATE INDEX idx_hypotheses_case ON hpbrain_hypotheses (tenant_id, case_id)');
        }

        if (Schema::hasTable('hpbrain_cases') && Schema::hasTable('hpbrain_hypotheses')) {
            $constraints = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'hpbrain_cases' AND REFERENCED_TABLE_NAME = 'hpbrain_hypotheses' AND CONSTRAINT_NAME = 'fk_cases_resolved_hypothesis'");
            if (empty($constraints)) {
                DB::unprepared('ALTER TABLE hpbrain_cases ADD CONSTRAINT fk_cases_resolved_hypothesis
  FOREIGN KEY (resolved_hypothesis_id) REFERENCES hpbrain_hypotheses(id)');
            }
        }

        if (!Schema::hasTable('hpbrain_case_evidence')) {
            DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_case_evidence (
  tenant_id    VARCHAR(36) NOT NULL,
  case_id      VARCHAR(36) NOT NULL REFERENCES hpbrain_cases(id),
  evidence_id  VARCHAR(36) NOT NULL REFERENCES hpbrain_evidence(id),
  linked_date  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (case_id, evidence_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            $idx = DB::select("SHOW INDEX FROM hpbrain_case_evidence WHERE Key_name = 'idx_case_evidence_tenant'");
            if (empty($idx)) {
                DB::unprepared('CREATE INDEX idx_case_evidence_tenant ON hpbrain_case_evidence (tenant_id)');
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hpbrain_case_evidence');
        Schema::dropIfExists('hpbrain_hypotheses');
        Schema::dropIfExists('hpbrain_cases');
    }
};
