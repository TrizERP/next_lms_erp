<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_08_01_000018_organization_units.php
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
        DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_organization_units (
  id              VARCHAR(36) PRIMARY KEY,
  tenant_id       VARCHAR(36) NOT NULL,
  org_id          VARCHAR(36),
  unit_type       VARCHAR(50) NOT NULL DEFAULT \'department\',
  name            VARCHAR(255) NOT NULL,
  description     TEXT,
  code            VARCHAR(100),
  parent_unit_id  VARCHAR(36),
  head_id         VARCHAR(36),
  location        VARCHAR(255),
  cost_center     VARCHAR(100),
  status          VARCHAR(50) NOT NULL DEFAULT \'active\',
  metadata        JSON,
  created_by      TEXT NOT NULL,
  created_date    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_date    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        DB::unprepared('CREATE INDEX idx_org_units_tenant_id ON hpbrain_organization_units (tenant_id)');
        DB::unprepared('CREATE INDEX idx_org_units_org_id ON hpbrain_organization_units (org_id)');
        DB::unprepared('CREATE INDEX idx_org_units_parent_unit_id ON hpbrain_organization_units (parent_unit_id)');
        DB::unprepared('CREATE INDEX idx_org_units_unit_type ON hpbrain_organization_units (unit_type)');
        DB::unprepared('CREATE INDEX idx_org_units_status ON hpbrain_organization_units (status)');
    }

    public function down(): void
    {
        Schema::dropIfExists('hpbrain_organization_units');
    }
};
