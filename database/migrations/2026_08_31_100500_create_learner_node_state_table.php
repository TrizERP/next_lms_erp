<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adaptive Learning Engine — Developer Brief v1, §6.1.
 *
 * Per-student, per-node mastery state. New table, not an extension of
 * pal_competencies (concept-grain, not node-grain) or pal_concept_mastery
 * (owned exclusively by BktEngine — writing the brief's simple ±0.2 rule into
 * it would corrupt BKT's own state). Update rule is intentionally the brief's
 * simple rule, not BKT/IRT (brief §6/Phase 5: "do NOT build BKT/deep tracing
 * in v1"), computed and written by the new EsoPolicyService — this migration
 * only lays down the shape.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learner_node_state', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('node_id'); // pal_concept_nodes.id
            $table->unsignedBigInteger('sub_institute_id')->default(0);

            $table->decimal('mastery_estimate', 4, 3)->default(0); // 0.000–1.000, clamped
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('hint_used_count')->default(0);
            $table->string('status', 24)->default('unseen'); // unseen|learning|mastered|retained|misconception_flagged

            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('next_review_at')->nullable(); // set on mastery (D5); null otherwise

            $table->timestamps();

            $table->unique(['student_id', 'node_id'], 'lns_student_node_unique');
            $table->index(['student_id', 'status'], 'lns_student_status_idx');
            $table->index('next_review_at', 'lns_review_due_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_node_state');
    }
};
