<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ported verbatim from database/migrations/036_learning_efficacy.sql.
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
        if (!Schema::hasTable('hpbrain_eso_efficacy_records')) {
            DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_eso_efficacy_records (
  id                  VARCHAR(36) PRIMARY KEY,
  tenant_id           VARCHAR(36) NOT NULL,
  eso_definition_id   VARCHAR(36) NOT NULL,
  gap_type            VARCHAR(255) NOT NULL,
  
  
  
  population          VARCHAR(500) NOT NULL,
  efficacy_score       DECIMAL(5,4) NOT NULL, 
  sample_size          INT NOT NULL DEFAULT 1,
  computed_date         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by            VARCHAR(36) NOT NULL,
  created_date           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        }
        if (Schema::hasTable('hpbrain_eso_efficacy_records')) {
            $idx = DB::select("SHOW INDEX FROM hpbrain_eso_efficacy_records WHERE Key_name = 'idx_eso_efficacy_eso'");
            if (empty($idx)) DB::unprepared('CREATE INDEX idx_eso_efficacy_eso ON hpbrain_eso_efficacy_records (tenant_id, eso_definition_id)');
            $idx = DB::select("SHOW INDEX FROM hpbrain_eso_efficacy_records WHERE Key_name = 'idx_eso_efficacy_gap_type'");
            if (empty($idx)) DB::unprepared('CREATE INDEX idx_eso_efficacy_gap_type ON hpbrain_eso_efficacy_records (tenant_id, gap_type)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hpbrain_eso_efficacy_records');
    }
};
