<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per teaching method: the stored context the pedagogical workflow and the AI prompts read.
 * Global reference data (no tenant column). Whether a chapter uses the staged workflow is decided elsewhere
 * (config pedagogical_workflow.scope); this table only holds what each method IS.
 *
 * Apply by path (this database has many unrecorded migrations; never run a bare `migrate`):
 *   php artisan migrate --path=database/migrations/2026_11_02_100000_create_pedagogical_method_contexts_table.php
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('pedagogical_method_contexts')) {
            return;
        }
        Schema::create('pedagogical_method_contexts', function (Blueprint $table) {
            $table->id();
            $table->string('method_key', 64)->unique();
            $table->string('method_name', 191);
            $table->text('method_definition');
            $table->text('teaching_philosophy');
            $table->json('core_principles');
            $table->text('teacher_role');
            $table->text('student_role');
            $table->json('teaching_strategies');
            $table->json('workflow_stages');
            $table->json('classroom_activities');
            $table->json('questioning_strategies');
            $table->json('assessment_strategy');
            $table->longText('ai_generation_instructions');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedagogical_method_contexts');
    }
};
