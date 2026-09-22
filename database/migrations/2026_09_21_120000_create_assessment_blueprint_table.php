<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assessment blueprints — the design of a paper, not its layout.
 *
 * NAMING, BECAUSE THE WORD IS ALREADY TAKEN. `QuestionPaperTemplateBlueprint`
 * is a *layout* blueprint: page size, header, sections, numbering, how a paper
 * PRINTS. This is the other thing schools call a blueprint: how many marks go
 * to which chapter, how many questions of which type, what share is easy and
 * what share is hard — the paper's DESIGN, settled before a single question is
 * chosen. They are deliberately separate tables and separate code paths; the
 * one that prints never needs the one that designs.
 *
 * `lms_assessment_typology` was the earlier attempt at this. Its migration
 * exists but was never run on any live database, and its only other reference
 * is a Neo4j reconcile command that already lists it as "table does not exist",
 * so nothing is being replaced here.
 *
 * REFERENCE vs SCHOOL. A row with `is_reference = 1` and no
 * `sub_institute_id` is a published design every school can read but none can
 * edit — the Delhi DoE and CBSE papers. A school starts from one by cloning it,
 * which writes a row carrying their own `sub_institute_id` and a `parent_id`
 * pointing back at what it came from, so "what did we change from the board
 * pattern" stays answerable.
 *
 * The distributions themselves live in `definition` as JSON rather than in four
 * child tables. A blueprint is read and written whole, never queried one
 * weighting at a time, and the real documents differ enough in shape
 * (sub-parts, split marks like 1+1+2, per-section notes) that columns would
 * have to be reinvented for every board anyway.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('assessment_blueprint')) {
            return;
        }

        Schema::create('assessment_blueprint', function (Blueprint $table) {
            $table->bigIncrements('id');

            // NULL on a reference blueprint: it belongs to the platform, not a
            // school, and every tenant reads the same row.
            $table->unsignedBigInteger('sub_institute_id')->nullable()->index();
            $table->integer('syear')->nullable();

            $table->string('name', 191);
            $table->string('description', 500)->nullable();

            // The facets a coordinator actually filters by.
            $table->string('academic_year', 20)->nullable();
            $table->string('board', 60)->nullable();
            $table->string('class_band', 40)->nullable();
            $table->string('assessment_type', 60)->nullable();

            // Bound to a real class/subject only on a school's own blueprint; a
            // reference covers a band ("III-V") and carries its subject as a
            // label, because no reference can point at one school's subject row.
            $table->unsignedBigInteger('standard_id')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label', 120)->nullable();

            $table->decimal('total_marks', 7, 2)->default(0);
            $table->unsignedSmallInteger('duration_minutes')->nullable();

            $table->boolean('is_reference')->default(false);
            $table->string('preset_key', 80)->nullable()->index();
            // The reference (or earlier blueprint) this was cloned from.
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->unsignedInteger('version')->default(1);

            // Where the design came from, in words a teacher would recognise —
            // "Delhi DoE 2025-26", "CBSE 2025-26", "School-custom".
            $table->string('source', 150)->nullable();
            $table->string('source_url', 500)->nullable();

            // Draft | Active | Archived
            $table->string('status', 20)->default('Draft');

            $table->longText('definition')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['sub_institute_id', 'syear'], 'ab_tenant_year');
            $table->index(['is_reference', 'class_band'], 'ab_reference_band');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_blueprint');
    }
};
