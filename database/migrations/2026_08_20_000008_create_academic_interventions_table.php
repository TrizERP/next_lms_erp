<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Academic interventions — the business record an approved recommendation creates.
 *
 * This is a genuinely missing entity rather than a duplicate. The estate has
 * `pal_remediations` (tied to a misconception), `pal_remediation_sessions` (a learner
 * working through one) and `pal_learning_plans` (an untyped JSON blob), but nothing
 * that represents "a teacher agreed to support this student, here is what was done,
 * and here is whether it worked". Shoehorning that into `pal_learning_plans` would
 * have made it unqueryable and untrackable.
 *
 * The links back to case, recommendation, decision and workflow run are what make the
 * chain inspectable end to end: from the intervention on screen, back to the teacher
 * who approved it, back to the evidence that justified it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_interventions', function (Blueprint $table) {
            $table->id();
            $table->string('intervention_reference', 60)->unique();

            $table->unsignedBigInteger('student_id')->index();
            $table->string('student_name', 200)->nullable();
            $table->unsignedBigInteger('standard_id')->nullable()->index();
            $table->unsignedBigInteger('section_id')->nullable()->index();
            $table->unsignedBigInteger('subject_id')->nullable()->index();

            // Provenance: why this exists at all.
            $table->unsignedBigInteger('case_id')->nullable()->index();
            $table->unsignedBigInteger('recommendation_id')->nullable()->index();
            $table->unsignedBigInteger('decision_id')->nullable()->index();
            $table->unsignedBigInteger('workflow_run_id')->nullable()->index();

            $table->string('intervention_type', 60)->default('academic_support');
            $table->string('title', 300);
            $table->text('description')->nullable();
            $table->text('rationale')->nullable();
            $table->string('severity', 24)->default('moderate')->index();

            // Content may be generated; if so, it is flagged and never shown as fact.
            $table->longText('activity_content')->nullable();
            $table->boolean('activity_is_generated')->default(false);
            $table->unsignedBigInteger('generation_output_id')->nullable();

            $table->unsignedBigInteger('assigned_to')->nullable()->index();   // teacher
            $table->string('assigned_to_name', 150)->nullable();
            $table->unsignedBigInteger('created_by')->index();
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable()->index();

            $table->string('status', 32)->default('active')->index();
            // active | in_progress | completed | cancelled | lapsed
            $table->timestamp('completed_at')->nullable();
            $table->text('completion_notes')->nullable();
            $table->unsignedTinyInteger('progress_percent')->default(0);

            $table->json('metadata')->nullable();

            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->unsignedInteger('academic_year')->nullable()->index();
            $table->unsignedInteger('term_id')->nullable();
            $table->timestamps();

            $table->index(['sub_institute_id', 'status'], 'academic_interventions_tenant_status_idx');
            $table->index(['student_id', 'status'], 'academic_interventions_student_status_idx');
        });

        Schema::create('academic_intervention_activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('intervention_id')->index();
            $table->string('activity_type', 60)->default('practice');
            $table->string('title', 300);
            $table->text('instructions')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedBigInteger('chapter_id')->nullable();
            $table->unsignedBigInteger('concept_id')->nullable();
            $table->unsignedBigInteger('content_id')->nullable();

            $table->boolean('is_generated')->default(false);
            $table->date('due_date')->nullable();
            $table->string('status', 24)->default('assigned')->index();
            // assigned | started | completed | skipped
            $table->timestamp('completed_at')->nullable();
            $table->decimal('score', 8, 2)->nullable();

            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_intervention_activities');
        Schema::dropIfExists('academic_interventions');
    }
};
