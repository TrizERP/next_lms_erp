<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ported verbatim from database/migrations/020_ai_governance.sql.
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
        if (!Schema::hasTable('hpbrain_ai_executions')) {
            DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_ai_executions (
  id                VARCHAR(36) PRIMARY KEY,
  tenant_id         VARCHAR(36) NOT NULL,
  user_id           VARCHAR(36) NOT NULL,
  service_name      TEXT NOT NULL,
  prompt_template_id VARCHAR(36),
  provider          VARCHAR(36) NOT NULL,
  model             TEXT,
  status            TEXT NOT NULL,
  input_tokens      INTEGER,
  output_tokens     INTEGER,
  latency_ms        INTEGER,
  estimated_cost_usd DECIMAL(12,4),
  error             TEXT,
  entity_type       TEXT,
  entity_id         VARCHAR(36),
  created_date      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        }
        if (Schema::hasTable('hpbrain_ai_executions')) {
            $idx = DB::select("SHOW INDEX FROM hpbrain_ai_executions WHERE Key_name = 'idx_ai_executions_tenant'");
            if (empty($idx)) DB::unprepared('CREATE INDEX idx_ai_executions_tenant ON hpbrain_ai_executions (tenant_id, created_date DESC)');
        }

        if (Schema::hasTable('hpbrain_prompt_templates')) {
            if (!Schema::hasColumn('hpbrain_prompt_templates', 'category')) {
                DB::unprepared('ALTER TABLE hpbrain_prompt_templates ADD COLUMN category TEXT');
            }
            if (!Schema::hasColumn('hpbrain_prompt_templates', 'default_model')) {
                DB::unprepared('ALTER TABLE hpbrain_prompt_templates ADD COLUMN default_model TEXT');
            }
            if (!Schema::hasColumn('hpbrain_prompt_templates', 'default_temperature')) {
                DB::unprepared('ALTER TABLE hpbrain_prompt_templates ADD COLUMN default_temperature DECIMAL(4,2) DEFAULT 0.7');
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hpbrain_ai_executions');
    }
};
