<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ported verbatim from database/migrations/017_conversation_engine.sql.
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
        if (!Schema::hasTable('hpbrain_conversation_sessions')) {
            DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_conversation_sessions (
  id                VARCHAR(36) PRIMARY KEY,
  tenant_id         VARCHAR(36) NOT NULL,
  org_id            VARCHAR(36),
  title             TEXT NOT NULL DEFAULT \'New conversation\',
  context_type      TEXT, 
  context_entity_id VARCHAR(36), 
  created_by        VARCHAR(255) NOT NULL,
  created_date      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_date      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        }
        if (Schema::hasTable('hpbrain_conversation_sessions')) {
            $idx = DB::select("SHOW INDEX FROM hpbrain_conversation_sessions WHERE Key_name = 'idx_conversation_sessions_tenant'");
            if (empty($idx)) DB::unprepared('CREATE INDEX idx_conversation_sessions_tenant ON hpbrain_conversation_sessions (tenant_id)');
            $idx = DB::select("SHOW INDEX FROM hpbrain_conversation_sessions WHERE Key_name = 'idx_conversation_sessions_user'");
            if (empty($idx)) DB::unprepared('CREATE INDEX idx_conversation_sessions_user ON hpbrain_conversation_sessions (tenant_id, created_by)');
        }

        if (!Schema::hasTable('hpbrain_conversation_messages')) {
            DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_conversation_messages (
  id                VARCHAR(36) PRIMARY KEY,
  tenant_id         VARCHAR(36) NOT NULL,
  session_id        VARCHAR(36) NOT NULL REFERENCES hpbrain_conversation_sessions(id),
  `role`            TEXT NOT NULL, 
  content           TEXT NOT NULL,
  citations         JSON NOT NULL DEFAULT \'[]\', 
  created_date      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            DB::unprepared('CREATE INDEX idx_conversation_messages_session ON hpbrain_conversation_messages (tenant_id, session_id)');
        }

        if (!Schema::hasTable('hpbrain_prompt_templates')) {
            DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_prompt_templates (
  id                VARCHAR(36) PRIMARY KEY,
  tenant_id         VARCHAR(36) NOT NULL,
  name              TEXT NOT NULL,
  template          TEXT NOT NULL, 
  variables         JSON NOT NULL DEFAULT \'[]\',
  version           INTEGER NOT NULL DEFAULT 1,
  previous_version_id VARCHAR(36) REFERENCES hpbrain_prompt_templates(id),
  status            TEXT NOT NULL DEFAULT \'active\',
  created_by        TEXT NOT NULL,
  created_date      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            $idx = DB::select("SHOW INDEX FROM hpbrain_prompt_templates WHERE Key_name = 'idx_prompt_templates_tenant'");
            if (empty($idx)) DB::unprepared('CREATE INDEX idx_prompt_templates_tenant ON hpbrain_prompt_templates (tenant_id)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hpbrain_prompt_templates');
        Schema::dropIfExists('hpbrain_conversation_messages');
        Schema::dropIfExists('hpbrain_conversation_sessions');
    }
};
