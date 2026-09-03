<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ported verbatim from database/migrations/002_audit.sql.
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
        DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_audit_logs (
  id              VARCHAR(36) PRIMARY KEY,
  tenant_id       VARCHAR(36) NOT NULL,
  entity_type     VARCHAR(255) NOT NULL,
  entity_id       VARCHAR(36) NOT NULL,
  action          TEXT NOT NULL,
  actor_id        VARCHAR(36) NOT NULL,
  actor_name      TEXT NOT NULL,
  changes         JSON,
  ip_address      TEXT,
  user_agent      TEXT,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        DB::unprepared('CREATE INDEX idx_audit_logs_tenant_id ON hpbrain_audit_logs (tenant_id)');
        DB::unprepared('CREATE INDEX idx_audit_logs_entity ON hpbrain_audit_logs (entity_type, entity_id)');
        DB::unprepared('CREATE INDEX idx_audit_logs_created_at ON hpbrain_audit_logs (created_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('hpbrain_audit_logs');
    }
};
