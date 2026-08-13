<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chapter/topic linkage for the Content Intelligence sidecars.
 *
 * WHY THIS EXISTS — measured on vivek_erp, 2026-08-13:
 *
 *   lms_question_master.concept_id populated  ...........     47 / 62,209
 *   content_master.concept_id populated  ................      1 / 31,362
 *   lms_question_master.chapter_id populated  ...........  62,206 / 62,209
 *   content_master.chapter_id populated  ................  31,357 / 31,362
 *   questions joined to answer history WITH a concept_id .      0
 *
 * The spec routes everything through :Concept, and the plan assumed lms_concept
 * (1,372 rows) was the join key. It is not: concept_id is empty on the live
 * estate, and lms_concept itself only spans 97 distinct chapters against the
 * 1,347 distinct chapters the questions actually use.
 *
 * So a concept-only sidecar would select zero rows for practically every learner.
 * chapter_id is the linkage that exists today, and the legacy PAL flow already
 * uses it (question_paper.paper_desc holds the chapter id).
 *
 * The layer therefore keys on concept_ref_id WHEN PRESENT and falls back to
 * chapter_ref_id otherwise. Concept remains the target model — as concept
 * linkage gets backfilled, routing sharpens from chapter granularity to concept
 * granularity with no further schema change.
 *
 * Additive and nullable, so CONTENT LAW C2 still holds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pal_question_metadata', function (Blueprint $table) {
            if (! Schema::hasColumn('pal_question_metadata', 'chapter_ref_id')) {
                $table->unsignedBigInteger('chapter_ref_id')->nullable()->after('concept_ref_id')
                    ->comment('chapter_master.id — the linkage that is actually populated');
                $table->unsignedBigInteger('topic_ref_id')->nullable()->after('chapter_ref_id');
                // The ladder's hot path when concept_ref_id is null.
                $table->index(['chapter_ref_id', 'practice_level', 'quality_status'], 'pqm_chapter_ladder_idx');
            }
        });

        Schema::table('pal_content_metadata', function (Blueprint $table) {
            if (! Schema::hasColumn('pal_content_metadata', 'chapter_ref_id')) {
                $table->unsignedBigInteger('chapter_ref_id')->nullable()->after('concept_ref_id');
                $table->unsignedBigInteger('topic_ref_id')->nullable()->after('chapter_ref_id');
                // The variant router's hot path when concept_ref_id is null.
                $table->index(['chapter_ref_id', 'content_type', 'variant_number', 'quality_status'], 'pcm_chapter_variant_idx');
            }
        });

        Schema::table('pal_misconception_library', function (Blueprint $table) {
            if (! Schema::hasColumn('pal_misconception_library', 'chapter_ref_id')) {
                $table->unsignedBigInteger('chapter_ref_id')->nullable()->after('concept_ref_id');
                $table->index(['chapter_ref_id', 'priority_level'], 'pml_chapter_priority_idx');
            }
        });

        Schema::table('pal_learner_content_exposure', function (Blueprint $table) {
            if (! Schema::hasColumn('pal_learner_content_exposure', 'chapter_ref_id')) {
                $table->unsignedBigInteger('chapter_ref_id')->nullable()->after('concept_ref_id');
                // The C7 shown-set lookup when routing by chapter.
                $table->index(['learner_id', 'chapter_ref_id', 'content_type'], 'plce_chapter_shown_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pal_learner_content_exposure', function (Blueprint $table) {
            if (Schema::hasColumn('pal_learner_content_exposure', 'chapter_ref_id')) {
                $table->dropIndex('plce_chapter_shown_idx');
                $table->dropColumn('chapter_ref_id');
            }
        });

        Schema::table('pal_misconception_library', function (Blueprint $table) {
            if (Schema::hasColumn('pal_misconception_library', 'chapter_ref_id')) {
                $table->dropIndex('pml_chapter_priority_idx');
                $table->dropColumn('chapter_ref_id');
            }
        });

        Schema::table('pal_content_metadata', function (Blueprint $table) {
            if (Schema::hasColumn('pal_content_metadata', 'chapter_ref_id')) {
                $table->dropIndex('pcm_chapter_variant_idx');
                $table->dropColumn(['chapter_ref_id', 'topic_ref_id']);
            }
        });

        Schema::table('pal_question_metadata', function (Blueprint $table) {
            if (Schema::hasColumn('pal_question_metadata', 'chapter_ref_id')) {
                $table->dropIndex('pqm_chapter_ladder_idx');
                $table->dropColumn(['chapter_ref_id', 'topic_ref_id']);
            }
        });
    }
};
