<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adaptive Learning Engine — Developer Brief v1, Phase 9 / §6.3.
 *
 * "question -> concept_id, node_id, item_type" — node_id was missed in the
 * first item_type migration. References pal_concept_nodes.id (the new K/A/S
 * node table), nullable: not every legacy question will be node-tagged in
 * v1, only the Chapter 3 pilot set (Phase 0 tagging scope).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pal_question_metadata', function (Blueprint $table) {
            if (! Schema::hasColumn('pal_question_metadata', 'node_id')) {
                $table->unsignedBigInteger('node_id')->nullable()->after('concept_ref_id');
            }
        });

        Schema::table('pal_question_metadata', function (Blueprint $table) {
            $table->index('node_id', 'pqm_node_idx');
        });
    }

    public function down(): void
    {
        Schema::table('pal_question_metadata', function (Blueprint $table) {
            if (Schema::hasColumn('pal_question_metadata', 'node_id')) {
                $table->dropIndex('pqm_node_idx');
                $table->dropColumn('node_id');
            }
        });
    }
};
