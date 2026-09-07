<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adaptive Learning Engine — Developer Brief v1, Phase 9.
 *
 * item_type (recall | application | transfer) is distinct from the existing
 * `knowledge_type` column (Bloom's knowledge dimension: factual / conceptual /
 * procedural / metacognitive) — confirmed via full-repo search that no such
 * field exists under any name today. D4's mastery verdict needs it to tell
 * "knowledge accuracy" apart from "application accuracy".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pal_question_metadata', function (Blueprint $table) {
            if (! Schema::hasColumn('pal_question_metadata', 'item_type')) {
                $table->string('item_type', 16)->nullable()->after('knowledge_type');
            }
        });

        Schema::table('pal_question_metadata', function (Blueprint $table) {
            $table->index(['concept_ref_id', 'item_type'], 'pqm_concept_item_type_idx');
        });
    }

    public function down(): void
    {
        Schema::table('pal_question_metadata', function (Blueprint $table) {
            if (Schema::hasColumn('pal_question_metadata', 'item_type')) {
                $table->dropIndex('pqm_concept_item_type_idx');
                $table->dropColumn('item_type');
            }
        });
    }
};
