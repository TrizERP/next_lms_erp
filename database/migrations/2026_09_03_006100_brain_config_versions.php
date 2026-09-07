<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_08_01_000015_config_versions.php
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
        DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_config_versions (
  id              VARCHAR(36) PRIMARY KEY,
  tenant_id       VARCHAR(36) NOT NULL,
  org_id          VARCHAR(36) NOT NULL,
  config_type     VARCHAR(100) NOT NULL,
  config_key      VARCHAR(255) NOT NULL,
  version         INT NOT NULL,
  data            JSON,
  status          VARCHAR(50) NOT NULL DEFAULT \'draft\',
  activated_by    TEXT,
  activated_date  TIMESTAMP NULL,
  rolled_back_by  TEXT,
  rolled_back_date TIMESTAMP NULL,
  change_summary  TEXT,
  created_by      TEXT NOT NULL,
  created_date    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_date    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT config_versions_tenant_type_key_version_unique UNIQUE (tenant_id, config_type, config_key, version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        DB::unprepared('CREATE INDEX idx_config_versions_tenant_id ON hpbrain_config_versions (tenant_id)');
        DB::unprepared('CREATE INDEX idx_config_versions_status ON hpbrain_config_versions (status)');
        DB::unprepared('CREATE INDEX idx_config_versions_activated_date ON hpbrain_config_versions (activated_date)');
    }

    public function down(): void
    {
        Schema::dropIfExists('hpbrain_config_versions');
    }
};
