<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ported verbatim from database/migrations/005_capability.sql.
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
        DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_capabilities (
  id                VARCHAR(36) PRIMARY KEY,
  tenant_id         VARCHAR(36) NOT NULL,
  org_id            VARCHAR(36) NOT NULL,
  capability_code   VARCHAR(255) NOT NULL,
  name              TEXT NOT NULL,
  description       TEXT,
  category          VARCHAR(255) NOT NULL DEFAULT \'general\',
  capability_type   TEXT NOT NULL DEFAULT \'competency\',
  difficulty        TEXT NOT NULL DEFAULT \'intermediate\',
  criticality       TEXT NOT NULL DEFAULT \'medium\',
  version           INTEGER NOT NULL DEFAULT 1,
  status            VARCHAR(255) NOT NULL DEFAULT \'active\',
  created_by        TEXT NOT NULL,
  created_date      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_date      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  knowledge         JSON,
  ability           JSON,
  skill             JSON,
  behaviour         JSON,
  attitude          JSON
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        DB::unprepared('CREATE UNIQUE INDEX idx_capabilities_tenant_code ON hpbrain_capabilities (tenant_id, capability_code)');
        DB::unprepared('CREATE INDEX idx_capabilities_tenant_id ON hpbrain_capabilities (tenant_id)');
        DB::unprepared('CREATE INDEX idx_capabilities_org_id ON hpbrain_capabilities (org_id)');
        DB::unprepared('CREATE INDEX idx_capabilities_category ON hpbrain_capabilities (category)');
        DB::unprepared('CREATE INDEX idx_capabilities_status ON hpbrain_capabilities (status)');
        DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_capability_versions (
  id            VARCHAR(36) PRIMARY KEY,
  capability_id VARCHAR(36) NOT NULL,
  tenant_id     VARCHAR(36) NOT NULL,
  version       INTEGER NOT NULL,
  name          TEXT NOT NULL,
  description   TEXT,
  category      TEXT,
  capability_type TEXT,
  difficulty    TEXT,
  criticality   TEXT,
  knowledge     JSON,
  ability       JSON,
  skill         JSON,
  behaviour     JSON,
  attitude      JSON,
  created_by    TEXT NOT NULL,
  created_date  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        DB::unprepared('CREATE INDEX idx_capability_versions_cap ON hpbrain_capability_versions (capability_id)');
        DB::unprepared('CREATE INDEX idx_capability_versions_tenant ON hpbrain_capability_versions (tenant_id)');
        DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_capability_assignments (
  id            VARCHAR(36) PRIMARY KEY,
  tenant_id     VARCHAR(36) NOT NULL,
  capability_id VARCHAR(36) NOT NULL,
  target_type   VARCHAR(255) NOT NULL,
  target_id     VARCHAR(36) NOT NULL,
  assigned_by   TEXT NOT NULL,
  assigned_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status        TEXT NOT NULL DEFAULT \'active\'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        DB::unprepared('CREATE UNIQUE INDEX idx_capability_assignments_uniq ON hpbrain_capability_assignments (tenant_id, capability_id, target_type, target_id)');
        DB::unprepared('CREATE INDEX idx_capability_assignments_tenant ON hpbrain_capability_assignments (tenant_id)');
        DB::unprepared('CREATE INDEX idx_capability_assignments_cap ON hpbrain_capability_assignments (capability_id)');
        DB::unprepared('CREATE INDEX idx_capability_assignments_target ON hpbrain_capability_assignments (target_type, target_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('hpbrain_capability_assignments');
        Schema::dropIfExists('hpbrain_capability_versions');
        Schema::dropIfExists('hpbrain_capabilities');
    }
};
