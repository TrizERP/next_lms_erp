<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_08_01_000021_skills.php
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
        DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_skills (
  id              VARCHAR(36) PRIMARY KEY,
  tenant_id       VARCHAR(36) NOT NULL,
  skill_key       VARCHAR(100) NOT NULL,
  name            VARCHAR(255) NOT NULL,
  description     TEXT,
  category        VARCHAR(100),
  level           VARCHAR(50),
  metadata        JSON,
  status          VARCHAR(50) NOT NULL DEFAULT \'active\',
  created_by      TEXT NOT NULL,
  created_date    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_date    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT skills_tenant_skill_key_unique UNIQUE (tenant_id, skill_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        DB::unprepared('CREATE INDEX idx_skills_tenant_id ON hpbrain_skills (tenant_id)');
        DB::unprepared('CREATE INDEX idx_skills_skill_key ON hpbrain_skills (skill_key)');
        DB::unprepared('CREATE INDEX idx_skills_category ON hpbrain_skills (category)');
    }

    public function down(): void
    {
        Schema::dropIfExists('hpbrain_skills');
    }
};
