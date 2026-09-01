<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * pal_competencies.bloom_level must be nullable.
 *
 * `pal:derive-competencies` projects lms_online_exam_answer into
 * pal_competencies at chapter grain. 6,043 of the 23,999 chapter-grain rows
 * are built from questions carrying no Bloom tag at all. The column was
 * NOT NULL DEFAULT 1, which stamped "Remember" on a quarter of the estate --
 * indistinguishable from a genuine Level-1 reading, and enough to drag every
 * affected learner's weighted Bloom level to the floor.
 *
 * NOTE ON STATE: this migration is already recorded as applied on vivek_erp
 * (batch 364) but its file had gone missing from the repo, so the schema was
 * ahead of the code and any fresh environment would have rebuilt the column
 * NOT NULL. It is written idempotently for exactly that reason -- it is a
 * no-op where the column is already nullable, and correct where it is not.
 *
 * Raw DDL rather than $table->change(): this install runs laravel/framework
 * 9.52 with no doctrine/dbal, so ->change() is unavailable.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pal_competencies') || !Schema::hasColumn('pal_competencies', 'bloom_level')) {
            return;
        }

        if ($this->bloomLevelIsNullable()) {
            return;
        }

        DB::statement('ALTER TABLE `pal_competencies` MODIFY `bloom_level` TINYINT UNSIGNED NULL DEFAULT NULL');
    }

    public function down(): void
    {
        // Deliberately not reverting to NOT NULL. Rows legitimately hold NULL
        // now, and a reverse migration would have to invent a Bloom level for
        // every one of them -- which is the exact data corruption this
        // migration exists to undo.
    }

    protected function bloomLevelIsNullable(): bool
    {
        $column = DB::selectOne('SHOW COLUMNS FROM `pal_competencies` LIKE ?', ['bloom_level']);

        return $column !== null && strtoupper($column->Null) === 'YES';
    }
};
