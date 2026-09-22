<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One answered adaptive-practice question, for one learner on one concept.
 *
 * Both the practice history the adaptive rule reads from, and the audit trail
 * for what it decided: rule_fired names the rule that chose the difficulty
 * served, so an odd-looking run of questions can be explained rather than
 * guessed at. varchar(64) fits the longest token plus a clamp suffix, e.g.
 * 'streak3_correct_escalate|clamp:medium'.
 *
 * NOTE there is deliberately NO unique key on (student_id, concept_id,
 * question_id), matching production. Idempotence for a double-clicked or
 * retried answer is handled by updateOrInsert in AdaptiveLearningService.
 * Adding the constraint here would diverge from the live table, where
 * existing rows may already violate it.
 *
 * See the guard rationale and the no-op down() in the attempt-table migration.
 *
 * Additive only: no existing table is altered.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pal_adaptive_response')) {
            return;
        }

        Schema::create('pal_adaptive_response', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('concept_id');
            $table->unsignedBigInteger('sub_institute_id')->default(0);
            $table->unsignedBigInteger('question_id');
            $table->unsignedBigInteger('answer_master_id')->nullable();
            $table->boolean('is_correct')->default(false);

            $table->string('difficulty_served', 8);
            $table->string('difficulty_source', 24)->nullable();
            $table->boolean('concept_exact')->default(false);

            $table->string('rule_fired', 64)->nullable();

            $table->timestamp('created_at')->nullable()->useCurrent();

            // id is part of the first index on purpose: the rule reads the
            // learner's most recent answers on a concept, so it wants the
            // ordering column covered too.
            $table->index(['student_id', 'concept_id', 'id'], 'par_student_concept_idx');
            $table->index(['student_id', 'question_id'], 'par_student_question_idx');
        });
    }

    /** Deliberately a no-op - this table predates the migration and holds learner answers. */
    public function down(): void
    {
        // Intentionally empty. See 2026_09_17_100000_create_pal_diagnostic_attempt_table.php.
    }
};
