<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_08_01_002805_ai_feedback.php
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

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_ai_feedback (
  id              VARCHAR(36) PRIMARY KEY,
  tenant_id       VARCHAR(36) NOT NULL,
  execution_id    VARCHAR(36) NOT NULL,
  user_id         VARCHAR(36) NOT NULL,
  rating          VARCHAR(50) NOT NULL,
  feedback_text   TEXT,
  feedback_type   VARCHAR(255),
  metadata        JSON,
  created_date    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        DB::unprepared('CREATE INDEX idx_ai_feedback_tenant_id ON hpbrain_ai_feedback (tenant_id)');
        DB::unprepared('CREATE INDEX idx_ai_feedback_execution_id ON hpbrain_ai_feedback (execution_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('hpbrain_ai_feedback');
    }
};
