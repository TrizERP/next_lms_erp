<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adaptive Learning Engine — Developer Brief v1, Phase 8 / §8.
 *
 * Exactly the brief's three v1 learning patterns (Declarative, Classification,
 * Causal Model) — a preset the policy reads, not a table of authored ESO
 * artifacts. One column, not a new table, per the brief's own instruction
 * ("No per-concept ESO YAML files... 1 pattern-assignment field per concept").
 *
 * Placed after mastery_threshold (confirmed present on the live lms_concept
 * table) rather than chapter_id/lesson_id, since several earlier lms_concept
 * migrations (bloom_level, lesson_id, extraction_id columns) have not run on
 * every environment — anchoring to a column verified present on all of them
 * avoids an ->after() failure.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lms_concept', function (Blueprint $table) {
            if (! Schema::hasColumn('lms_concept', 'learning_pattern')) {
                $table->string('learning_pattern', 24)->nullable()->after('mastery_threshold');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lms_concept', function (Blueprint $table) {
            if (Schema::hasColumn('lms_concept', 'learning_pattern')) {
                $table->dropColumn('learning_pattern');
            }
        });
    }
};
