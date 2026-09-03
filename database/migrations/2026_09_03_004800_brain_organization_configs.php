<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_08_01_000002_organization_configs.php
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
        DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_organization_configs (
  id              VARCHAR(36) PRIMARY KEY,
  tenant_id       VARCHAR(36) NOT NULL,
  org_id          VARCHAR(36) NOT NULL,
  config_key      VARCHAR(255) NOT NULL,
  config_value    TEXT,
  config_type     VARCHAR(50) NOT NULL DEFAULT \'scalar\',
  description     TEXT,
  is_active       BOOLEAN NOT NULL DEFAULT TRUE,
  created_by      TEXT NOT NULL,
  created_date    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_date    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT org_configs_tenant_org_key_unique UNIQUE (tenant_id, org_id, config_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        DB::unprepared('CREATE INDEX idx_org_configs_tenant_id ON hpbrain_organization_configs (tenant_id)');
        DB::unprepared('CREATE INDEX idx_org_configs_org_id ON hpbrain_organization_configs (org_id)');
        DB::unprepared('CREATE INDEX idx_org_configs_is_active ON hpbrain_organization_configs (is_active)');
    }

    public function down(): void
    {
        Schema::dropIfExists('hpbrain_organization_configs');
    }
};
