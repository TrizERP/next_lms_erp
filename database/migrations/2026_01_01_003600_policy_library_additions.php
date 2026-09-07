<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ported verbatim from database/migrations/037_policy_library_additions.sql.
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
        if (!Schema::hasColumn('hpbrain_policies', 'approval_gates')) {
            DB::unprepared('ALTER TABLE hpbrain_policies ADD COLUMN approval_gates JSON NOT NULL DEFAULT (\'[]\')');
        }
        if (!Schema::hasColumn('hpbrain_policies', 'data_access_rules')) {
            DB::unprepared('ALTER TABLE hpbrain_policies ADD COLUMN data_access_rules JSON NOT NULL DEFAULT (\'[]\')');
        }
        if (!Schema::hasColumn('hpbrain_policies', 'regulatory_constraints')) {
            DB::unprepared('ALTER TABLE hpbrain_policies ADD COLUMN regulatory_constraints JSON NOT NULL DEFAULT (\'[]\')');
        }
    }

    public function down(): void
    {
        // No tables created by this migration.
    }
};
