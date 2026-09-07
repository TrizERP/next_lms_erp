<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_08_01_000012_branding.php
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
        DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_branding (
  id                  VARCHAR(36) PRIMARY KEY,
  tenant_id           VARCHAR(36) NOT NULL,
  org_id              VARCHAR(36) NOT NULL,
  name                VARCHAR(255),
  logo_url            TEXT,
  favicon_url         TEXT,
  primary_color       VARCHAR(20),
  secondary_color     VARCHAR(20),
  accent_color        VARCHAR(20),
  font_family         VARCHAR(255),
  login_background_url TEXT,
  email_header_url    TEXT,
  custom_css          TEXT,
  is_active           BOOLEAN NOT NULL DEFAULT TRUE,
  created_by          TEXT NOT NULL,
  created_date        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_date        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT branding_tenant_org_unique UNIQUE (tenant_id, org_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        DB::unprepared('CREATE INDEX idx_branding_tenant_id ON hpbrain_branding (tenant_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('hpbrain_branding');
    }
};
