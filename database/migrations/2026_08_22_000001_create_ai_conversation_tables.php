<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The conversation layer, and the trace that makes the rest of the architecture
 * visible.
 *
 * Two tables. `ai_conversations` holds the thread and its memory — the referents that
 * let "why is she at risk" resolve to a student named two turns ago. `ai_conversation_turns`
 * holds one row per question, and critically stores the full stage trace alongside the
 * answer.
 *
 * Storing the trace is not debug logging. It is the record of which layer did what for
 * a decision a school later has to defend, and it is what lets the console replay a
 * journey after the fact rather than only while it is happening.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_conversations')) {
            Schema::create('ai_conversations', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('conversation_reference', 40)->unique();

                // Which part of the product this thread is about. One module per thread
                // keeps the intent catalogue small and the routing honest.
                $table->string('module_key', 100)->default('student_profiles');
                $table->string('title', 250)->nullable();

                // Referents carried between turns: student_id, case_id,
                // recommendation_id, workflow_run_id, outcome_id.
                $table->json('memory')->nullable();

                $table->unsignedInteger('turn_count')->default(0);
                $table->string('status', 30)->default('open');
                $table->timestamp('last_turn_at')->nullable();

                // Scope, pinned at creation exactly as every other AI table pins it.
                $table->unsignedBigInteger('user_id');
                $table->string('actor_role', 50)->nullable();
                $table->unsignedBigInteger('sub_institute_id');
                $table->unsignedBigInteger('client_id')->nullable();
                $table->integer('academic_year')->nullable();
                $table->unsignedBigInteger('term_id')->nullable();

                $table->timestamps();

                $table->index(['sub_institute_id', 'user_id', 'status'], 'ai_conv_scope_idx');
                $table->index(['sub_institute_id', 'module_key'], 'ai_conv_module_idx');
            });
        }

        if (! Schema::hasTable('ai_conversation_turns')) {
            Schema::create('ai_conversation_turns', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('conversation_id');
                $table->unsignedInteger('sequence');

                $table->text('question');
                $table->string('intent_key', 80)->nullable();
                $table->decimal('intent_confidence', 5, 4)->nullable();
                $table->json('intent_slots')->nullable();

                // What the user was shown: headline, sections, actions.
                $table->json('answer')->nullable();

                // The fifteen stages, each with its status, component and records.
                $table->json('trace')->nullable();
                $table->json('stage_counts')->nullable();

                // Denormalised links, so "show me every turn that touched case 12" is a
                // plain index lookup rather than a JSON scan.
                $table->string('subject_entity_key', 100)->nullable();
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->unsignedBigInteger('case_id')->nullable();
                $table->unsignedBigInteger('recommendation_id')->nullable();
                $table->unsignedBigInteger('agent_run_id')->nullable();
                $table->unsignedBigInteger('workflow_run_id')->nullable();
                $table->unsignedBigInteger('decision_id')->nullable();

                $table->unsignedInteger('duration_ms')->nullable();
                $table->string('status', 30)->default('answered');
                $table->text('error_message')->nullable();

                $table->unsignedBigInteger('sub_institute_id');
                $table->unsignedBigInteger('user_id')->nullable();

                $table->timestamps();

                $table->index(['conversation_id', 'sequence'], 'ai_turn_thread_idx');
                $table->index(['sub_institute_id', 'intent_key'], 'ai_turn_intent_idx');
                $table->index(['sub_institute_id', 'case_id'], 'ai_turn_case_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_conversation_turns');
        Schema::dropIfExists('ai_conversations');
    }
};
