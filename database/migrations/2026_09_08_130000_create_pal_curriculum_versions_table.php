<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Curriculum versioning — content belongs to a syllabus AND a year.
 *
 * Today PAL content carries a `version` string ('1.0'), which is a row
 * revision: edit the row, bump the string. That cannot express the thing that
 * actually happens to a school — a board revises its syllabus, and last year's
 * content was correct for last year's students.
 *
 * Without this, a syllabus revision has only two outcomes, and both are bad:
 * overwrite the content, which silently rewrites what a cohort was taught and
 * detaches their evidence from the material behind it; or fork the content and
 * lose the link between the two. A named version makes the third option
 * possible — supersede, keeping both.
 *
 * The unique key is what enforces the model: one version per
 * (tenant, board, standard, subject, academic year). A second version of the
 * same year is a contradiction, not a variant.
 *
 * `superseded_by_id` points forward, never back, so history is a chain that
 * can be walked from any point without mutating what came before.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pal_curriculum_versions')) {
            Schema::create('pal_curriculum_versions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sub_institute_id')->default(0);

                $table->string('board', 64);
                $table->unsignedBigInteger('standard_id');
                $table->unsignedBigInteger('subject_id')->nullable();

                // 'YYYY-YY', the Indian academic-year convention (2026-27).
                $table->string('academic_year', 9);

                $table->string('label', 191)->nullable();

                // draft     — being authored, not servable
                // active    — the one a learner is taught from
                // superseded— kept for the cohorts taught under it
                $table->string('status', 16)->default('draft');

                // Forward-only pointer to the version that replaced this one.
                $table->unsignedBigInteger('superseded_by_id')->nullable();

                $table->date('effective_from')->nullable();
                $table->timestamps();

                $table->unique(
                    ['sub_institute_id', 'board', 'standard_id', 'subject_id', 'academic_year'],
                    'pal_curriculum_version_scope_unique'
                );

                $table->index(['sub_institute_id', 'status'], 'pal_curriculum_version_status_idx');
            });
        }

        // The binding. Nullable and unbackfilled: existing rows predate
        // versioning, and assigning them to a year would assert something
        // about what a past cohort was taught that nobody actually recorded.
        foreach (['pal_content_metadata', 'pal_question_metadata'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (Schema::hasColumn($table, 'curriculum_version_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) {
                $t->unsignedBigInteger('curriculum_version_id')->nullable()->after('sub_institute_id');
                $t->index('curriculum_version_id');
            });
        }
    }

    public function down(): void
    {
        foreach (['pal_content_metadata', 'pal_question_metadata'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'curriculum_version_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) {
                $t->dropIndex(['curriculum_version_id']);
                $t->dropColumn('curriculum_version_id');
            });
        }

        Schema::dropIfExists('pal_curriculum_versions');
    }
};
