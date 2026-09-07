<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_08_01_000007_organization_modules.php
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
        DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_organization_modules (
  id              VARCHAR(36) PRIMARY KEY,
  tenant_id       VARCHAR(36) NOT NULL,
  org_id          VARCHAR(36) NOT NULL,
  module_id       VARCHAR(36) NOT NULL,
  is_enabled      BOOLEAN NOT NULL DEFAULT TRUE,
  config          JSON,
  enabled_by      TEXT,
  enabled_date    TIMESTAMP NULL,
  created_by      TEXT NOT NULL,
  created_date    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_date    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT org_modules_tenant_org_module_unique UNIQUE (tenant_id, org_id, module_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        DB::unprepared('CREATE INDEX idx_org_modules_tenant_id ON hpbrain_organization_modules (tenant_id)');
        DB::unprepared('CREATE INDEX idx_org_modules_org_id ON hpbrain_organization_modules (org_id)');
        DB::unprepared('CREATE INDEX idx_org_modules_is_enabled ON hpbrain_organization_modules (is_enabled)');
    }

    public function down(): void
    {
        Schema::dropIfExists('hpbrain_organization_modules');
    }
};
