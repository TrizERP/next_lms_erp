<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PAL V4 recommendation outcome log (spec Phase 18).
 *
 * Every pedagogy->content recommendation should be logged with enough
 * context to later evaluate whether it worked -- learner state and mastery
 * before, what was recommended and why, what was actually shown, and (once a
 * later event updates this row) whether the student received/completed it
 * and what mastery did afterward. This is what BKT/model calibration would
 * eventually train against; without it every recommendation is a one-shot
 * decision nobody can audit.
 *
 * No FKs to pal_concepts/pal_subjects deliberately -- unlike pal_competencies,
 * this is a pure append-only log read by analytics, not a table joined against
 * in a hot request path, so the FK-bridging workaround pal_competencies needed
 * (see palController::ensurePalSubjectExists()) doesn't apply here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pal_recommendation_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('learner_id')->index();
            $table->unsignedBigInteger('concept_id')->nullable()->index();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedInteger('sub_institute_id')->nullable()->index();

            // State captured at the moment of recommendation.
            $table->float('mastery_before')->nullable();
            $table->boolean('had_data_before')->default(false);

            // The decision itself.
            $table->string('pedagogy_type')->nullable();
            $table->string('selection_reason', 512)->nullable();
            $table->unsignedInteger('content_master_id')->nullable();
            $table->string('content_format')->nullable();
            $table->boolean('content_exhausted')->default(false);
            $table->string('algorithm_version', 32)->default('pal-v4-1');

            // Outcome, filled in later by a separate call once known.
            $table->timestamp('shown_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->float('mastery_after')->nullable();
            $table->string('outcome', 32)->nullable(); // improved | unchanged | declined | abandoned

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pal_recommendation_log');
    }
};
