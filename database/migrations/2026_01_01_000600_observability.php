<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ported verbatim from database/migrations/007_observability.sql.
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
        DB::unprepared('ALTER TABLE hpbrain_audit_logs ADD COLUMN org_id TEXT');
        DB::unprepared('ALTER TABLE hpbrain_audit_logs ADD COLUMN session_id TEXT');
        DB::unprepared('ALTER TABLE hpbrain_audit_logs ADD COLUMN correlation_id TEXT');
        DB::unprepared('ALTER TABLE hpbrain_audit_logs ADD COLUMN event_id TEXT');
        DB::unprepared('ALTER TABLE hpbrain_audit_logs ADD COLUMN source TEXT DEFAULT \'api\'');
        DB::unprepared('ALTER TABLE hpbrain_audit_logs ADD COLUMN execution_time INTEGER');
        DB::unprepared('ALTER TABLE hpbrain_audit_logs ADD COLUMN status TEXT DEFAULT \'success\'');
        DB::unprepared('ALTER TABLE hpbrain_audit_logs ADD COLUMN request_id TEXT');
        DB::unprepared('CREATE INDEX idx_audit_logs_org_id ON hpbrain_audit_logs (org_id)');
        DB::unprepared('CREATE INDEX idx_audit_logs_correlation_id ON hpbrain_audit_logs (correlation_id)');
        DB::unprepared('CREATE INDEX idx_audit_logs_event_id ON hpbrain_audit_logs (event_id)');
        DB::unprepared('CREATE INDEX idx_audit_logs_source ON hpbrain_audit_logs (source)');
        DB::unprepared('CREATE INDEX idx_audit_logs_status ON hpbrain_audit_logs (status)');
        DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_metrics (
  id VARCHAR(36) PRIMARY KEY,
  tenant_id VARCHAR(36),
  metric_name VARCHAR(255) NOT NULL,
  metric_value DECIMAL(18,6) NOT NULL,
  tags JSON,
  recorded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        DB::unprepared('CREATE INDEX idx_metrics_tenant_id ON hpbrain_metrics (tenant_id)');
        DB::unprepared('CREATE INDEX idx_metrics_name ON hpbrain_metrics (metric_name)');
        DB::unprepared('CREATE INDEX idx_metrics_recorded_at ON hpbrain_metrics (recorded_at)');
        DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_health_checks (
  id VARCHAR(36) PRIMARY KEY,
  check_name VARCHAR(255) NOT NULL,
  status VARCHAR(255) NOT NULL,
  details JSON,
  response_time INTEGER,
  checked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        DB::unprepared('CREATE INDEX idx_health_checks_name ON hpbrain_health_checks (check_name)');
        DB::unprepared('CREATE INDEX idx_health_checks_status ON hpbrain_health_checks (status)');
        DB::unprepared('CREATE INDEX idx_health_checks_checked_at ON hpbrain_health_checks (checked_at)');
        DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_logs (
  id VARCHAR(36) PRIMARY KEY,
  tenant_id VARCHAR(36),
  org_id VARCHAR(36),
  level VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  module TEXT,
  user_id VARCHAR(36),
  request_id VARCHAR(36),
  correlation_id VARCHAR(36),
  execution_time INTEGER,
  metadata JSON,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        DB::unprepared('CREATE INDEX idx_logs_tenant_id ON hpbrain_logs (tenant_id)');
        DB::unprepared('CREATE INDEX idx_logs_level ON hpbrain_logs (level)');
        DB::unprepared('CREATE INDEX idx_logs_created_at ON hpbrain_logs (created_at)');
        DB::unprepared('CREATE INDEX idx_logs_correlation_id ON hpbrain_logs (correlation_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('hpbrain_logs');
        Schema::dropIfExists('hpbrain_health_checks');
        Schema::dropIfExists('hpbrain_metrics');
    }
};
