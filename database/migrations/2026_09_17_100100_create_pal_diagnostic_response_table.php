<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One served question inside a diagnostic attempt, and the learner's answer.
 *
 * Rows are written UNANSWERED when the paper is drawn and updated in place on
 * submit. That is why answer_master_id is nullable and is_correct defaults to
 * 0: a row exists for every question served, whether or not it was attempted,
 * and `answer_master_id IS NULL` is the single test for "unanswered".
 *
 * The unique key on (attempt_id, question_id) is load-bearing, not decorative:
 * it makes it impossible for the selector to put the same question into one
 * paper twice, even if the in-PHP exclusion list were ever wrong.
 *
 * difficulty_served is the SLOT the item filled (easy|medium|hard).
 * difficulty_source is where that claim came from - 'dok', 'g_difficulty',
 * 'adjacent:medium' when the band had to be filled from a neighbour, or
 * 'untagged'. varchar(24) is sized for 'adjacent:medium'.
 *
 * See the guard rationale and the no-op down() in the attempt-table migration;
 * the same reasoning applies here, and this table holds the actual answers.
 *
 * Additive only: no existing table is altered.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pal_diagnostic_response')) {
            return;
        }

        Schema::create('pal_diagnostic_response', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('attempt_id');
            $table->unsignedBigInteger('question_id');
            $table->unsignedBigInteger('answer_master_id')->nullable();
            $table->boolean('is_correct')->default(false);

            $table->string('difficulty_served', 8);
            $table->string('difficulty_source', 24)->nullable();

            // Resolved when the paper is drawn, not at read time: the
            // curriculum can be re-tagged later, and the scoring of a past
            // attempt must not move underneath it.
            $table->unsignedBigInteger('concept_id_snapshot')->nullable();
            $table->unsignedBigInteger('chapter_id_snapshot')->nullable();
            $table->boolean('concept_exact')->default(false);

            $table->unsignedSmallInteger('sequence')->default(0);
            $table->timestamp('answered_at')->nullable();

            $table->unique(['attempt_id', 'question_id'], 'pdr_attempt_question_unique');
            $table->index('attempt_id', 'pdr_attempt_idx');
            $table->index(['attempt_id', 'concept_id_snapshot'], 'pdr_attempt_concept_idx');
        });
    }

    /** Deliberately a no-op - this table predates the migration and holds learner answers. */
    public function down(): void
    {
        // Intentionally empty. See 2026_09_17_100000_create_pal_diagnostic_attempt_table.php.
    }
};
