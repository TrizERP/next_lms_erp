<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adaptive Learning Engine — Developer Brief v1, §6.2/Phase 7.
 *
 * The audit trail for every D1–D5 decision — non-negotiable per the brief,
 * built first, never retrofitted. Deliberately a new table rather than
 * widening pal_recommendation_log: that table's schema is purpose-built for
 * pedagogy→content recommendation outcome tracking (mastery_before/after,
 * shown_at/completed_at) and doesn't carry rule_fired/llm_instruction —
 * repurposing it risks whatever already depends on its current shape.
 * Append-only, no FKs to hot-path tables, matching pal_recommendation_log's
 * own documented design pattern.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eso_decision_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id')->index();
            $table->unsignedBigInteger('concept_id')->nullable()->index();
            $table->unsignedBigInteger('node_id')->nullable()->index();
            $table->unsignedBigInteger('sub_institute_id')->default(0);

            $table->json('state_snapshot')->nullable(); // node estimates read at decision time
            $table->string('rule_fired', 64); // D1..D5 + sub-rule, e.g. "D3: M2 flagged twice"
            $table->string('action', 191);
            $table->text('llm_instruction')->nullable(); // exact constraint sent to Pal, for audit

            $table->timestamps();

            $table->index(['student_id', 'created_at'], 'edl_student_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eso_decision_log');
    }
};
