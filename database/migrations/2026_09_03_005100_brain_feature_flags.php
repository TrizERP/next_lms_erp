<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_08_01_000005_feature_flags.php
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
        DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_feature_flags (
  id                  VARCHAR(36) PRIMARY KEY,
  tenant_id           VARCHAR(36) NOT NULL,
  flag_key            VARCHAR(255) NOT NULL,
  flag_name           VARCHAR(255) NOT NULL,
  description         TEXT,
  enabled             BOOLEAN NOT NULL DEFAULT TRUE,
  level               VARCHAR(50) NOT NULL DEFAULT \'platform\',
  level_id            VARCHAR(255),
  rollout_percentage  INT NOT NULL DEFAULT 100,
  rules               JSON,
  created_by          TEXT NOT NULL,
  created_date        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_date        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT feature_flags_tenant_key_level_id_unique UNIQUE (tenant_id, flag_key, level, level_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        DB::unprepared('CREATE INDEX idx_feature_flags_tenant_id ON hpbrain_feature_flags (tenant_id)');
        DB::unprepared('CREATE INDEX idx_feature_flags_enabled ON hpbrain_feature_flags (enabled)');
    }

    public function down(): void
    {
        Schema::dropIfExists('hpbrain_feature_flags');
    }
};
