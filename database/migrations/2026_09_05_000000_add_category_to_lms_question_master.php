<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The PAL learning-flow category a generated question belongs to:
     * prerequisite, adaptive_diagnostic, concept_diagnostic, concept_understanding,
     * misconception_detection, prerequisite_concept_check, adaptive_test,
     * mastery_check, mastery_reverification.
     *
     * It mirrors `pal_question_metadata.stage`, but lives on the question itself so
     * the Question Bank's category dropdown can filter without joining a PAL-side
     * table the bank never otherwise reads. Legacy questions predate the column and
     * keep NULL, which is why it is nullable: there is nothing to derive a category
     * from on a row whose `answer` envelope is empty, and CONTENT LAW C2 allows only
     * additive changes to this table.
     *
     * The column is already present on the live database, applied by hand ahead of
     * this migration; the hasColumn guard makes this a no-op there and reproduces it
     * everywhere else. VARCHAR(48) and the index name both match what is live.
     */
    public function up(): void
    {
        if (!Schema::hasTable('lms_question_master')) {
            return;
        }

        if (!Schema::hasColumn('lms_question_master', 'category')) {
            Schema::table('lms_question_master', function (Blueprint $table) {
                $table->string('category', 48)
                    ->nullable()
                    ->after('subconcept')
                    ->comment('PAL learning-flow category (mirrors pal_question_metadata.stage)');
                // Single-column, matching the index already on the live table. The
                // bank filters chapter_id together with category, and chapter_id has
                // its own index, so leaving these separate keeps every environment
                // identical rather than trading that for a marginal composite.
                $table->index('category', 'idx_lms_question_master_category');
            });
        }
    }

    /**
     * Drop the category column again, index first.
     */
    public function down(): void
    {
        if (!Schema::hasTable('lms_question_master') ||
            !Schema::hasColumn('lms_question_master', 'category')) {
            return;
        }

        Schema::table('lms_question_master', function (Blueprint $table) {
            $table->dropIndex('idx_lms_question_master_category');
            $table->dropColumn('category');
        });
    }
};
