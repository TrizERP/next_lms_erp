<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ported verbatim from database/migrations/014_mental_model_reinforcement.sql.
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
        DB::unprepared('ALTER TABLE hpbrain_mental_models ADD COLUMN confidence DECIMAL(6,4) NOT NULL DEFAULT 0.5');
        DB::unprepared('ALTER TABLE hpbrain_mental_models ADD COLUMN reinforcement_count INTEGER NOT NULL DEFAULT 0');
        DB::unprepared('ALTER TABLE hpbrain_mental_models MODIFY COLUMN domain VARCHAR(255) NOT NULL');
        DB::unprepared('CREATE INDEX idx_mental_models_domain ON hpbrain_mental_models (tenant_id, domain)');
    }

    public function down(): void
    {
        // No tables created by this migration.
    }
};
