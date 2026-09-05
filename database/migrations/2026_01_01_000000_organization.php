<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ported verbatim from database/migrations/001_organization.sql.
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
        DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_organizations (
  id              VARCHAR(36) PRIMARY KEY,
  tenant_id       VARCHAR(36) NOT NULL,
  name            TEXT NOT NULL,
  legal_name      TEXT,
  org_code        VARCHAR(255) NOT NULL UNIQUE,
  industry        TEXT,
  country         TEXT,
  timezone        TEXT DEFAULT \'UTC\',
  currency        TEXT DEFAULT \'USD\',
  logo            TEXT,
  status          VARCHAR(255) NOT NULL DEFAULT \'active\',
  created_by      TEXT NOT NULL,
  created_date    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_date    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT organizations_tenant_org_code_unique UNIQUE (tenant_id, org_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        DB::unprepared('CREATE INDEX idx_organizations_tenant_id ON hpbrain_organizations (tenant_id)');
        DB::unprepared('CREATE INDEX idx_organizations_status ON hpbrain_organizations (status)');
    }

    public function down(): void
    {
        Schema::dropIfExists('hpbrain_organizations');
    }
};
