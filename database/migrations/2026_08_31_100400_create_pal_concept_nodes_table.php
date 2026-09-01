<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adaptive Learning Engine — Developer Brief v1, §5/§6.1.
 *
 * K/A/S (Knowledge/Ability/Skill) nodes do not exist under any name in this
 * codebase (confirmed by full-repo search) — `pal_concept_metadata` is one
 * row per concept, not per masterable sub-unit, so it cannot represent "this
 * concept has a K1 node and an A1 node with independent mastery". This table
 * is the addressable node identity the brief's `learner_node_state.node_id`
 * and `eso_decision_log.node_id` reference.
 *
 * Deliberately K/A/S only (not literal Prerequisite/Misconception node rows,
 * despite the brief listing them alongside K/A/S as node_id sources): a
 * prerequisite is itself a concept, tracked via that concept's own K/A/S
 * nodes plus the existing pal_concept_relations edge (D2 reads the
 * prerequisite concept's node states, it does not need its own node type);
 * a misconception is tracked as the `misconception_flagged` overlay status
 * on the K/A/S node it was detected against (brief §5: "Misconception-flagged
 * as an overlay state"), not as a separately masterable node. This keeps v1
 * to one new node table instead of three, per the brief's own "do not
 * overengineer v1" instruction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pal_concept_nodes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('concept_id'); // lms_concept.id — no FK, matches pal_* sidecar convention
            $table->unsignedBigInteger('sub_institute_id')->default(0);
            $table->string('node_type', 4); // K | A | S
            $table->string('label', 191);
            $table->text('description')->nullable();
            $table->float('mastery_threshold')->nullable(); // 0-1; null = fall back to pal_concept_metadata.mastery_gate
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['concept_id', 'node_type'], 'pcn_concept_type_idx');
            $table->index('sub_institute_id', 'pcn_tenant_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pal_concept_nodes');
    }
};
