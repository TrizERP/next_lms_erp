<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ported verbatim from database/migrations/019_notifications_and_settings.sql.
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
        if (!Schema::hasTable('hpbrain_notifications')) {
            DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_notifications (
  id                VARCHAR(36) PRIMARY KEY,
  tenant_id         VARCHAR(36) NOT NULL,
  user_id           VARCHAR(36) NOT NULL,
  type              TEXT NOT NULL,
  title             TEXT NOT NULL,
  body              TEXT,
  entity_type       TEXT,
  entity_id         VARCHAR(36),
  read_date         TIMESTAMP,
  created_date      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        }
        if (Schema::hasTable('hpbrain_notifications')) {
            $idx = DB::select("SHOW INDEX FROM hpbrain_notifications WHERE Key_name = 'idx_notifications_user'");
            if (empty($idx)) DB::unprepared('CREATE INDEX idx_notifications_user ON hpbrain_notifications (tenant_id, user_id, created_date DESC)');
            $idx = DB::select("SHOW INDEX FROM hpbrain_notifications WHERE Key_name = 'idx_notifications_unread'");
            if (empty($idx)) DB::unprepared('CREATE INDEX idx_notifications_unread ON hpbrain_notifications (tenant_id, user_id)');
        }

        if (!Schema::hasTable('hpbrain_settings')) {
            DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_settings (
  tenant_id   VARCHAR(36) NOT NULL,
  user_id     VARCHAR(36) NOT NULL DEFAULT \'_org_\',
  `key`       VARCHAR(255) NOT NULL,
  value       JSON NOT NULL,
  updated_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (tenant_id, user_id, `key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hpbrain_settings');
        Schema::dropIfExists('hpbrain_notifications');
    }
};
