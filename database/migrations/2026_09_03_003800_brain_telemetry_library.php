<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_01_01_003700_telemetry_library.php
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
 * Ported verbatim from database/migrations/038_telemetry_library.sql.
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
        if (!Schema::hasTable('hpbrain_telemetry_events')) {
            DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_telemetry_events (
  id                VARCHAR(36) PRIMARY KEY,
  tenant_id         VARCHAR(36) NOT NULL,
  org_id            VARCHAR(36) NOT NULL,
  
  event_type        VARCHAR(100) NOT NULL,
  
  entity_type       VARCHAR(100),
  entity_id         VARCHAR(36),
  metric_name       VARCHAR(255) NOT NULL,
  metric_value      DECIMAL(18,4) NOT NULL,
  unit              VARCHAR(50),
  metadata          JSON NOT NULL DEFAULT (\'{}\'),
  recorded_date      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        }
        if (Schema::hasTable('hpbrain_telemetry_events')) {
            $idx = DB::select("SHOW INDEX FROM hpbrain_telemetry_events WHERE Key_name = 'idx_telemetry_events_org'");
            if (empty($idx)) DB::unprepared('CREATE INDEX idx_telemetry_events_org ON hpbrain_telemetry_events (tenant_id, org_id)');
            $idx = DB::select("SHOW INDEX FROM hpbrain_telemetry_events WHERE Key_name = 'idx_telemetry_events_type'");
            if (empty($idx)) DB::unprepared('CREATE INDEX idx_telemetry_events_type ON hpbrain_telemetry_events (tenant_id, event_type)');
            $idx = DB::select("SHOW INDEX FROM hpbrain_telemetry_events WHERE Key_name = 'idx_telemetry_events_entity'");
            if (empty($idx)) DB::unprepared('CREATE INDEX idx_telemetry_events_entity ON hpbrain_telemetry_events (tenant_id, entity_type, entity_id)');
            $idx = DB::select("SHOW INDEX FROM hpbrain_telemetry_events WHERE Key_name = 'idx_telemetry_events_metric'");
            if (empty($idx)) DB::unprepared('CREATE INDEX idx_telemetry_events_metric ON hpbrain_telemetry_events (tenant_id, metric_name)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hpbrain_telemetry_events');
    }
};
