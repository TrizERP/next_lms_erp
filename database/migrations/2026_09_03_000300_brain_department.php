<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_01_01_000200_department.php
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
 * Ported verbatim from database/migrations/003_department.sql.
 *
 * The SQL is kept as raw statements rather than rewritten into Schema
 * builder calls. That is deliberate: this DDL has been executed against a
 * live MySQL 8 server and carries three hard-won corrections that a
 * Schema-builder rewrite would silently undo —
 *   1. every table is hpbrain_-prefixed (the Brain shares a database with
 *      the institute ERP and must not collide with it),
 *   2. every id/foreign-key column is VARCHAR(36), because MySQL rejects
 *      TEXT in a key (error 1170),
 *   3. every ratio column states explicit precision, because a bare
 *      NUMERIC becomes DECIMAL(10,0) in MySQL and rounds confidence to an
 *      integer.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_departments (
  id                   VARCHAR(36) PRIMARY KEY,
  tenant_id            VARCHAR(36) NOT NULL,
  name                 TEXT NOT NULL,
  description          TEXT,
  department_type      TEXT NOT NULL DEFAULT \'department\',
  parent_department_id VARCHAR(36),
  head_id              VARCHAR(36),
  org_id               VARCHAR(36) NOT NULL,
  status               VARCHAR(255) NOT NULL DEFAULT \'active\',
  created_by           TEXT NOT NULL,
  created_date         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_date         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        DB::unprepared('CREATE INDEX idx_departments_tenant_id ON hpbrain_departments (tenant_id)');
        DB::unprepared('CREATE INDEX idx_departments_org_id ON hpbrain_departments (org_id)');
        DB::unprepared('CREATE INDEX idx_departments_parent_id ON hpbrain_departments (parent_department_id)');
        DB::unprepared('CREATE INDEX idx_departments_status ON hpbrain_departments (status)');
    }

    public function down(): void
    {
        Schema::dropIfExists('hpbrain_departments');
    }
};
