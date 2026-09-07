<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ported verbatim from database/migrations/012_executors.sql.
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
        DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_executors (
  id                VARCHAR(36) PRIMARY KEY,
  tenant_id         VARCHAR(36) NOT NULL,
  executor_type     VARCHAR(255) NOT NULL,
  name              TEXT NOT NULL,
  person_id         VARCHAR(36) REFERENCES hpbrain_people(id),
  capability_tags   JSON NOT NULL DEFAULT \'[]\',
  trust_level DECIMAL(5,2) NOT NULL DEFAULT 0.5,
  max_concurrent    INTEGER NOT NULL DEFAULT 1,
  current_workload  INTEGER NOT NULL DEFAULT 0,
  available         BOOLEAN NOT NULL DEFAULT true,
  status            TEXT NOT NULL DEFAULT \'active\',
  created_date      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_date      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        DB::unprepared('CREATE INDEX idx_executors_tenant ON hpbrain_executors (tenant_id)');
        DB::unprepared('CREATE INDEX idx_executors_type ON hpbrain_executors (tenant_id, executor_type)');
    }

    public function down(): void
    {
        Schema::dropIfExists('hpbrain_executors');
    }
};
