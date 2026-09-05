<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_08_03_000200_signal_rules.php
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
 * Signal rules, as rows.
 *
 * The five rules were private methods on SignalGenerator. Adding a sixth meant a
 * deploy; giving a hospital a different sixth meant a conditional. As rows they
 * are configuration, and `industry_code` lets a rule be shipped to every tenant
 * in an industry without naming one.
 *
 * PREDICATES ARE JSON, NOT SQL, AND THAT IS NOT NEGOTIABLE. A rule row is
 * user-supplied data — an administrator writes it through the API. If the
 * predicate reached the query planner as text, that administrator would have the
 * database. The grammar in RuleEvaluator has a closed operator set and every
 * value is bound; identifiers come from EntityResolver, never from the rule.
 * There is deliberately no raw-SQL escape hatch, because an escape hatch is used
 * exactly once and then relied upon.
 *
 * confidence is DECIMAL(6,4) and threshold_value DECIMAL(18,4). NUMERIC without
 * precision becomes DECIMAL(10,0) on MySQL, which would silently round every
 * confidence to an integer — 0.8 stored as 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("CREATE TABLE IF NOT EXISTS hpbrain_signal_rules (
  id                  VARCHAR(36) PRIMARY KEY,
  tenant_id           VARCHAR(36) NOT NULL,
  industry_code       VARCHAR(100) NOT NULL DEFAULT '*',
  rule_key            VARCHAR(191) NOT NULL,
  universal_entity    VARCHAR(100) NOT NULL,
  predicate           JSON NOT NULL,
  join_entity         VARCHAR(100) NULL,
  join_predicate      JSON NULL,
  classification      VARCHAR(100) NOT NULL,
  severity            VARCHAR(50) NOT NULL,
  priority            VARCHAR(50) NOT NULL,
  confidence          DECIMAL(6,4) NOT NULL,
  evidence_fields     JSON NOT NULL,
  recommended_action  TEXT NOT NULL,
  owner_role          VARCHAR(100) NULL,
  threshold_op        VARCHAR(20) NULL,
  threshold_value     DECIMAL(18,4) NULL,
  is_active           TINYINT(1) NOT NULL DEFAULT 1,
  created_by          TEXT NOT NULL,
  created_date        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT signal_rules_tenant_rule_key_unique UNIQUE (tenant_id, rule_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        DB::unprepared('CREATE INDEX idx_signal_rules_tenant_active ON hpbrain_signal_rules (tenant_id, is_active)');
        DB::unprepared('CREATE INDEX idx_signal_rules_industry ON hpbrain_signal_rules (industry_code)');
    }

    public function down(): void
    {
        Schema::dropIfExists('hpbrain_signal_rules');
    }
};
