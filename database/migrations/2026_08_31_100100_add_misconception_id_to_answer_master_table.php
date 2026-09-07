<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adaptive Learning Engine (Learning ESO) — Developer Brief v1, Phase 9 / D3.
 *
 * Distractor→misconception mapping did not exist anywhere at the option grain
 * before this: `pal_question_metadata.misconception_tags` is question-level
 * JSON, not per-option. This is the column D3 needs to turn "student picked
 * option C" into "student holds misconception M2" instead of just "wrong".
 *
 * No FK constraint to pal_misconception_library, matching the convention used
 * by every other pal_* sidecar column that references a core/legacy table in
 * this codebase (pal_question_metadata.concept_ref_id, pal_content_metadata's
 * content_master_id, etc.) — answer_master is a large, actively-shared table
 * and a hard FK here would couple its writes to the misconception library's
 * lifecycle for no benefit. NULL = "generic error", exactly as the brief
 * specifies (Phase 9: "If misconception_id is NULL, treat it as: generic
 * error").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('answer_master', function (Blueprint $table) {
            if (! Schema::hasColumn('answer_master', 'misconception_id')) {
                $table->unsignedBigInteger('misconception_id')->nullable()->after('correct_answer');
            }
        });

        Schema::table('answer_master', function (Blueprint $table) {
            $table->index('misconception_id', 'am_misconception_idx');
        });
    }

    public function down(): void
    {
        Schema::table('answer_master', function (Blueprint $table) {
            if (Schema::hasColumn('answer_master', 'misconception_id')) {
                $table->dropIndex('am_misconception_idx');
                $table->dropColumn('misconception_id');
            }
        });
    }
};
