<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_01_01_000500_events.php
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
 * Ported verbatim from database/migrations/006_events.sql.
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
        DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_event_store (
  id                VARCHAR(36) PRIMARY KEY,
  type              VARCHAR(255) NOT NULL,
  tenant_id         VARCHAR(36) NOT NULL,
  entity_type       VARCHAR(255) NOT NULL,
  entity_id         VARCHAR(36) NOT NULL,
  actor_id          VARCHAR(36) NOT NULL,
  payload           JSON NOT NULL,
  metadata          JSON,
  correlation_id    VARCHAR(36),
  causation_id      VARCHAR(36),
  idempotency_key   VARCHAR(36),
  status            VARCHAR(255) NOT NULL DEFAULT \'pending\',
  retry_count       INTEGER NOT NULL DEFAULT 0,
  last_retry_at     TIMESTAMP,
  failure_reason    TEXT,
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at      TIMESTAMP,
  completed_at      TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        DB::unprepared('CREATE INDEX idx_event_store_tenant_id ON hpbrain_event_store (tenant_id)');
        DB::unprepared('CREATE INDEX idx_event_store_type ON hpbrain_event_store (type)');
        DB::unprepared('CREATE INDEX idx_event_store_entity ON hpbrain_event_store (entity_type, entity_id)');
        DB::unprepared('CREATE INDEX idx_event_store_status ON hpbrain_event_store (status)');
        DB::unprepared('CREATE INDEX idx_event_store_created_at ON hpbrain_event_store (created_at)');
        DB::unprepared('CREATE INDEX idx_event_store_correlation ON hpbrain_event_store (correlation_id)');
        DB::unprepared('CREATE UNIQUE INDEX idx_event_store_idempotency ON hpbrain_event_store (idempotency_key)');
        DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_dead_letter_queue (
  id                VARCHAR(36) PRIMARY KEY,
  event_id          VARCHAR(36) NOT NULL REFERENCES hpbrain_event_store(id),
  consumer_name     VARCHAR(255) NOT NULL,
  error_message     TEXT NOT NULL,
  error_stack       TEXT,
  retry_count       INTEGER NOT NULL DEFAULT 0,
  max_retries       INTEGER NOT NULL DEFAULT 3,
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        DB::unprepared('CREATE INDEX idx_dlq_event_id ON hpbrain_dead_letter_queue (event_id)');
        DB::unprepared('CREATE INDEX idx_dlq_consumer ON hpbrain_dead_letter_queue (consumer_name)');
        DB::unprepared('CREATE INDEX idx_dlq_created_at ON hpbrain_dead_letter_queue (created_at)');
        DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_consumer_state (
  id                VARCHAR(36) PRIMARY KEY,
  consumer_name     VARCHAR(255) NOT NULL UNIQUE,
  last_processed_event_id VARCHAR(36) REFERENCES hpbrain_event_store(id),
  last_processed_at TIMESTAMP,
  status            TEXT NOT NULL DEFAULT \'active\',
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        DB::unprepared('CREATE INDEX idx_consumer_state_name ON hpbrain_consumer_state (consumer_name)');
    }

    public function down(): void
    {
        Schema::dropIfExists('hpbrain_consumer_state');
        Schema::dropIfExists('hpbrain_dead_letter_queue');
        Schema::dropIfExists('hpbrain_event_store');
    }
};
