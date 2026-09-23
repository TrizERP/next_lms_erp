<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brings `lms_concept.topic_id` under migration control.
 *
 * WHY THIS EXISTS
 * This column is load-bearing and undocumented. It is the authoritative
 * concept -> topic_master link - lmsCurriculumController.php:115 says so in as many
 * words - and it is read by chapterMasterController.php (the endpoint behind
 * /course-master/{id}/chapters) and declared in config/neo4j.php. But no migration
 * ever created it. It exists on this estate because someone added it by hand.
 *
 * That is a real hazard rather than untidiness: any environment built from
 * `php artisan migrate` alone gets an `lms_concept` with no `topic_id`, and every
 * one of those readers fails on a column that the code treats as always present.
 * The Coherence Map adds a fourth reader and a backfill command that WRITES it, so
 * the drift is closed here before more code depends on it.
 *
 * WHAT IT DOES ON AN ESTATE THAT ALREADY HAS THE COLUMN
 * Nothing. The `Schema::hasColumn` guard makes this a no-op on vivek_erp, where the
 * column is already `bigint(20) unsigned NULL` with an index named
 * `idx_lms_concept_topic` (verified 2026-09-16). The definition below reproduces
 * that shape exactly, so a fresh database and this estate converge rather than
 * drifting further apart.
 *
 * WHY NULLABLE STAYS NULLABLE
 * Measured 2026-09-16: 1,410 of 2,571 concepts carry a topic_id and the rest do not.
 * Concepts extracted before the topic split have none, and the controllers already
 * handle that case explicitly by grouping them separately rather than dropping them
 * (chapterMasterController.php). Making this NOT NULL with a 0 default would turn
 * "no topic recorded" into "topic 0", which is a different and false claim.
 *
 * WHAT THIS IS DELIBERATELY NOT
 * No foreign key to `topic_master`. The estate convention is that these curriculum
 * links are plain indexed columns (`lms_concept.chapter_id` has no FK either), and a
 * FK here would fail on any row whose topic was deleted out from under it. This
 * migration also does not backfill anything - `lms:backfill-concept-topics` does
 * that, separately and reversibly.
 *
 * ROLLBACK
 * down() drops a COLUMN, not a table, so it is not caught by the DB::listen guard in
 * AppServiceProvider.php:77-90. It is still destructive - it would discard 1,410 real
 * topic assignments - so it is guarded to fire only where this migration could have
 * created the column.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('lms_concept', 'topic_id')) {
            return;
        }

        Schema::table('lms_concept', function (Blueprint $table) {
            // Mirrors the live column exactly: bigint unsigned, nullable, indexed.
            $table->unsignedBigInteger('topic_id')->nullable()->after('chapter_id')
                ->comment('topic_master.id - the authoritative concept -> topic link; null for pre-split concepts');

            $table->index('topic_id', 'idx_lms_concept_topic');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('lms_concept', 'topic_id')) {
            return;
        }

        Schema::table('lms_concept', function (Blueprint $table) {
            $table->dropIndex('idx_lms_concept_topic');
            $table->dropColumn('topic_id');
        });
    }
};
