<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_08_07_000100_recommendation_evidence.php
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
 * Links a recommendation to the specific evidence rows it was reasoned from.
 *
 * WHY A JOIN TABLE AND NOT AN evidence_refs JSON COLUMN. The relationship is
 * genuinely many-to-many — one evidence row (a single department missing its
 * manager) supports every recommendation drawn from that signal, and one
 * recommendation rests on many evidence rows. A JSON array of ids on
 * hpbrain_recommendations would express the same fact in a form nothing can
 * join against, index, or check for referential integrity, and the ids inside
 * it would rot silently when evidence is superseded.
 *
 * The shape is copied from hpbrain_case_evidence, which already solves exactly
 * this problem for cases: (tenant_id, <parent>_id, evidence_id, linked_date),
 * composite primary key, no surrogate id. Matching it means the two link tables
 * can be reasoned about — and queried — the same way, rather than each having
 * its own dialect.
 *
 * Raw DDL rather than Schema-builder calls, for the reasons stated at the head
 * of 2026_01_01_001200_decision_intelligence.php: VARCHAR(36) keys because
 * MySQL rejects TEXT in a key (error 1170), and the hpbrain_ prefix because the
 * Brain shares a database with the institute ERP.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hpbrain_recommendation_evidence')) {
            return;
        }

        DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_recommendation_evidence (
  tenant_id         VARCHAR(36) NOT NULL,
  recommendation_id VARCHAR(36) NOT NULL REFERENCES hpbrain_recommendations(id),
  evidence_id       VARCHAR(36) NOT NULL REFERENCES hpbrain_evidence(id),
  linked_date       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (tenant_id, recommendation_id, evidence_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        // "Which evidence backs this recommendation" is the read the Decision
        // Record makes; the primary key already serves it. This index serves
        // the opposite read — "what did this observation end up supporting" —
        // which is how a disputed evidence row is traced forward to every
        // claim that rests on it.
        $reverse = DB::select("SHOW INDEX FROM hpbrain_recommendation_evidence WHERE Key_name = 'idx_rec_evidence_evidence'");

        if (empty($reverse)) {
            DB::unprepared('CREATE INDEX idx_rec_evidence_evidence ON hpbrain_recommendation_evidence (tenant_id, evidence_id)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hpbrain_recommendation_evidence');
    }
};
