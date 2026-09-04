<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adaptive Learning Engine — per-response history.
 *
 * `learner_node_state` only ever stores an aggregate (attempts, mastery_estimate,
 * hint_used_count) — no existing table records each individual response a student
 * gave. The "Mastery details" screen's guided-vs-independent support split and
 * "Recent responses on this concept" list need that, so this is a new, append-only
 * log written once per scored response (diagnostic, practice attempt, or retrieval
 * check item) by EsoPolicyService::logResponse().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eso_response_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('concept_id'); // lms_concept.id
            $table->unsignedBigInteger('node_id'); // pal_concept_nodes.id
            $table->unsignedBigInteger('sub_institute_id')->default(0);
            $table->unsignedBigInteger('question_id')->nullable();

            $table->boolean('correct');
            $table->boolean('hint_used')->default(false);
            $table->string('mode', 20)->nullable(); // guided|independent|null (diagnostic/retrieval have no practice mode)

            $table->timestamp('created_at')->nullable()->useCurrent();

            $table->index(['student_id', 'concept_id'], 'erl_student_concept_idx');
            $table->index(['student_id', 'node_id'], 'erl_student_node_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eso_response_log');
    }
};
