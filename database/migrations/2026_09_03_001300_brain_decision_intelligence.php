<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_01_01_001200_decision_intelligence.php
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
 * Ported verbatim from database/migrations/013_decision_intelligence.sql.
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
        if (!Schema::hasColumn('hpbrain_decisions', 'confidence')) {
            DB::unprepared('ALTER TABLE hpbrain_decisions ADD COLUMN confidence DECIMAL(6,4) NOT NULL DEFAULT 0.5');
        }
        if (!Schema::hasColumn('hpbrain_decisions', 'explanation')) {
            DB::unprepared('ALTER TABLE hpbrain_decisions ADD COLUMN explanation TEXT');
        }
        if (!Schema::hasColumn('hpbrain_decisions', 'trace')) {
            DB::unprepared('ALTER TABLE hpbrain_decisions ADD COLUMN trace JSON NOT NULL DEFAULT \'[]\'');
        }

        if (Schema::hasTable('hpbrain_evidence') && !Schema::hasColumn('hpbrain_evidence', 'observed_date')) {
            DB::unprepared('ALTER TABLE hpbrain_evidence ADD COLUMN observed_date TIMESTAMP');
            DB::unprepared('UPDATE hpbrain_evidence SET observed_date = created_date WHERE observed_date IS NULL');
        }

        if (Schema::hasTable('hpbrain_recommendations') && !Schema::hasColumn('hpbrain_recommendations', 'urgency')) {
            DB::unprepared('ALTER TABLE hpbrain_recommendations ADD COLUMN urgency TEXT NOT NULL DEFAULT \'normal\'');
        }
        if (Schema::hasTable('hpbrain_recommendations') && !Schema::hasColumn('hpbrain_recommendations', 'expected_roi')) {
            DB::unprepared('ALTER TABLE hpbrain_recommendations ADD COLUMN expected_roi DECIMAL(14,4)');
        }

        if (Schema::hasTable('hpbrain_policies') && !Schema::hasColumn('hpbrain_policies', 'policy_type')) {
            DB::unprepared('ALTER TABLE hpbrain_policies ADD COLUMN policy_type TEXT NOT NULL DEFAULT \'executor_autonomy\'');
        }
        if (Schema::hasTable('hpbrain_policies') && !Schema::hasColumn('hpbrain_policies', 'rules')) {
            DB::unprepared('ALTER TABLE hpbrain_policies ADD COLUMN rules JSON NOT NULL DEFAULT \'[]\'');
        }
        if (Schema::hasTable('hpbrain_policies') && !Schema::hasColumn('hpbrain_policies', 'version')) {
            DB::unprepared('ALTER TABLE hpbrain_policies ADD COLUMN version INTEGER NOT NULL DEFAULT 1');
        }
        if (Schema::hasTable('hpbrain_policies') && !Schema::hasColumn('hpbrain_policies', 'previous_version_id')) {
            DB::unprepared('ALTER TABLE hpbrain_policies ADD COLUMN previous_version_id VARCHAR(36) REFERENCES hpbrain_policies(id)');
        }
        DB::unprepared('ALTER TABLE hpbrain_policies MODIFY COLUMN policy_type VARCHAR(255) NOT NULL DEFAULT \'executor_autonomy\'');

        if (Schema::hasTable('hpbrain_policies')) {
            $indexes = DB::select("SHOW INDEX FROM hpbrain_policies WHERE Key_name = 'idx_policies_type'");
            if (empty($indexes)) {
                DB::unprepared('CREATE INDEX idx_policies_type ON hpbrain_policies (tenant_id, policy_type)');
            }
        }

        if (!Schema::hasTable('hpbrain_risks')) {
            DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_risks (
  id                VARCHAR(36) PRIMARY KEY,
  tenant_id         VARCHAR(36) NOT NULL,
  decision_id       VARCHAR(36) REFERENCES hpbrain_decisions(id),
  recommendation_id VARCHAR(36) REFERENCES hpbrain_recommendations(id),
  category          TEXT NOT NULL,
  probability DECIMAL(6,4) NOT NULL DEFAULT 0.5,
  impact            TEXT NOT NULL DEFAULT \'medium\',
  score DECIMAL(10,4) NOT NULL DEFAULT 0,
  mitigation        TEXT,
  status            TEXT NOT NULL DEFAULT \'open\',
  created_by        TEXT NOT NULL,
  created_date      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_date      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

            $tenantIdx = DB::select("SHOW INDEX FROM hpbrain_risks WHERE Key_name = 'idx_risks_tenant'");
            if (empty($tenantIdx)) {
                DB::unprepared('CREATE INDEX idx_risks_tenant ON hpbrain_risks (tenant_id)');
            }
            $decisionIdx = DB::select("SHOW INDEX FROM hpbrain_risks WHERE Key_name = 'idx_risks_decision'");
            if (empty($decisionIdx)) {
                DB::unprepared('CREATE INDEX idx_risks_decision ON hpbrain_risks (tenant_id, decision_id)');
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hpbrain_risks');
    }
};
