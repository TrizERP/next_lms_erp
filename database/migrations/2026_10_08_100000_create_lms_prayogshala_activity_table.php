<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prayogshala: practical / experiment / activity-based learning, filed against a chapter.
 *
 * Why a table of its own rather than another content_master category. content_master
 * holds a file or a link plus one description blob; a Prayogshala activity is structured
 * (objective, materials, procedure, observation, result, safety, teacher and student
 * instructions) and a teacher needs to read those as separate sections, not as one HTML
 * document. Everything else is reused: the chapter / standard / subject / sub_institute
 * keys are the same canonical ids content_master and chapter_master already use, so there
 * is no second hierarchy to keep in step. standard_id and subject_id are denormalised from
 * chapter_master on write (the same shape content_master has) so a list can be filtered
 * without a join, and the controller derives them from the chapter - never from the
 * client - so they cannot disagree with it.
 *
 * Nothing is subject-specific: `activity_type` is a free label validated against the
 * controller's vocabulary, so Science experiments, Maths explorations and Social Science
 * map activities all live in the same table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lms_prayogshala_activity')) {
            return;
        }

        Schema::create('lms_prayogshala_activity', function (Blueprint $table) {
            $table->bigIncrements('id');
            // Owning institute. Same meaning as content_master.sub_institute_id.
            $table->unsignedBigInteger('sub_institute_id');
            $table->unsignedInteger('syear')->nullable();
            $table->unsignedInteger('grade_id')->nullable();
            $table->unsignedInteger('standard_id');
            $table->unsignedInteger('subject_id');
            $table->unsignedInteger('chapter_id');
            // Optional: the topic (topic_master.id) of the chapter this activity belongs to.
            // topic_master already carries chapter_id, so Standard > Subject > Chapter >
            // Topic is the existing hierarchy; this column only points into it. NULL means
            // the activity is chapter-wide.
            $table->unsignedBigInteger('topic_id')->nullable();
            // Optional: the concept (lms_concept.id) this activity illustrates.
            $table->unsignedBigInteger('concept_id')->nullable();

            $table->string('title', 250);
            // Stable key within a chapter. Seeders and generators upsert on it, so a retry or a
            // re-run updates the same activity instead of adding a duplicate. NULL for
            // activities typed in by hand.
            $table->string('slug', 120)->nullable();
            $table->string('activity_type', 40)->default('experiment');
            $table->text('description')->nullable();
            $table->text('objective')->nullable();
            // JSON arrays of strings (materials, procedure steps). longText + casts in the
            // controller rather than a native json column, to match the rest of this schema.
            $table->longText('materials_required')->nullable();
            $table->longText('procedure_steps')->nullable();
            $table->text('observation')->nullable();
            $table->text('result')->nullable();
            $table->text('safety_instructions')->nullable();
            $table->text('teacher_instructions')->nullable();
            $table->text('student_instructions')->nullable();
            $table->unsignedSmallInteger('estimated_minutes')->nullable();
            // JSON array of {type: image|video|pdf|link, title, url}.
            $table->longText('resources')->nullable();
            // The interactive lab: the eight-step flow's content plus the simulation it runs
            // (engine type + parameters). Pure data - the frontend renders it with generic
            // engines, so no chapter has code of its own. NULL = a plain document activity.
            $table->longText('lab_config')->nullable();
            // draft | review | published. Learners only ever see 'published'; staff see all,
            // which is how AI-assisted or newly authored activities wait for a teacher.
            $table->string('status', 16)->default('published');

            $table->unsignedTinyInteger('show_hide')->default(1);
            $table->integer('sort_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['chapter_id', 'sub_institute_id'], 'idx_prayog_chapter_tenant');
            $table->index(['sub_institute_id', 'standard_id', 'subject_id'], 'idx_prayog_tenant_std_sub');
            $table->index('topic_id', 'idx_prayog_topic');
            $table->unique(['sub_institute_id', 'chapter_id', 'slug'], 'uq_prayog_chapter_slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_prayogshala_activity');
    }
};
