<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates all tables required by the PAL (Personalized Adaptive Learning) V4 system.
     */
    public function up(): void
    {
        // 1. Core domain
        Schema::create('pal_subjects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('grade_level')->nullable();
            $table->timestamps();
        });

        Schema::create('pal_concepts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->nullable()->constrained('pal_subjects')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('bloom_level')->default(1);
            $table->unsignedTinyInteger('abstractness_level')->default(1);
            $table->boolean('requires_visual')->default(false);
            $table->boolean('requires_manipulation')->default(false);
            $table->boolean('requires_simulation')->default(false);
            $table->string('recommended_pedagogy')->nullable();
            $table->json('pedagogy_tags')->nullable();
            $table->timestamps();

            $table->index(['subject_id', 'bloom_level']);
        });

        Schema::create('pal_competencies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('learner_id'); // references users.id
            $table->foreignId('subject_id')->nullable()->constrained('pal_subjects')->nullOnDelete();
            $table->foreignId('concept_id')->nullable()->constrained('pal_concepts')->nullOnDelete();
            $table->float('mastery_score')->default(0);
            $table->unsignedTinyInteger('bloom_level')->default(1);
            $table->string('proficiency_trend')->nullable();
            $table->timestamp('last_assessed')->nullable();
            $table->timestamps();

            $table->index(['learner_id', 'concept_id']);
            $table->index('mastery_score');
        });

        // 2. Sessions & Events
        Schema::create('pal_learning_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('learner_id');
            $table->foreignId('subject_id')->nullable()->constrained('pal_subjects')->nullOnDelete();
            $table->string('status')->default('active'); // active, completed, abandoned
            $table->unsignedTinyInteger('difficulty_level')->default(1);
            $table->unsignedInteger('duration_minutes')->default(0);
            $table->unsignedInteger('interaction_count')->default(0);
            $table->float('engagement_score')->default(0);
            $table->float('mastery_score')->default(0);
            $table->string('device_type')->nullable();
            $table->string('initiated_by')->nullable(); // learner, teacher, system
            $table->timestamps();

            $table->index(['learner_id', 'status']);
        });

        Schema::create('pal_session_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('learner_id');
            $table->foreignId('session_id')->nullable()->constrained('pal_learning_sessions')->nullOnDelete();
            $table->string('event_type'); // video_played, answer_selected, rage_click, etc.
            $table->json('event_data')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->unsignedInteger('response_time_ms')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamp('timestamp')->useCurrent();
            $table->timestamps();

            $table->index(['learner_id', 'session_id', 'event_type']);
        });

        Schema::create('pal_assessment_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('learner_id');
            $table->unsignedBigInteger('question_id')->nullable();
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('response_time_ms')->default(0);
            $table->float('score')->nullable();
            $table->timestamps();

            $table->index(['learner_id', 'is_correct']);
        });

        // 3. Misconceptions & Remediation
        Schema::create('pal_misconceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('concept_id')->nullable()->constrained('pal_concepts')->nullOnDelete();
            $table->string('pattern');
            $table->string('category')->nullable();
            $table->text('root_cause')->nullable();
            $table->unsignedInteger('frequency')->default(0);
            $table->timestamps();
        });

        Schema::create('pal_learner_misconceptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('learner_id');
            $table->foreignId('concept_id')->nullable()->constrained('pal_concepts')->nullOnDelete();
            $table->foreignId('misconception_id')->constrained('pal_misconceptions')->cascadeOnDelete();
            $table->string('status')->default('active'); // active, resolved
            $table->unsignedTinyInteger('severity')->default(1); // 1-5
            $table->timestamps();

            $table->index(['learner_id', 'status']);
        });

        Schema::create('pal_remediations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('misconception_id')->constrained('pal_misconceptions')->cascadeOnDelete();
            $table->string('type')->nullable();
            $table->text('content')->nullable();
            $table->string('pedagogy')->nullable();
            $table->float('effectiveness_score')->default(0);
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('pal_remediation_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('learner_id');
            $table->foreignId('misconception_id')->nullable()->constrained('pal_misconceptions')->nullOnDelete();
            $table->string('status')->default('in_progress');
            $table->unsignedInteger('attempts')->default(0);
            $table->float('success_rate')->default(0);
            $table->timestamps();

            $table->index('learner_id');
        });

        // 4. Content
        Schema::create('pal_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('concept_id')->nullable()->constrained('pal_concepts')->nullOnDelete();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('content_type')->nullable(); // video, text, h5p, interactive
            $table->string('format')->nullable();
            $table->unsignedTinyInteger('difficulty_level')->default(1);
            $table->unsignedTinyInteger('bloom_level')->default(1);
            $table->json('pedagogy_tags')->nullable();
            $table->string('h5p_type')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('pal_content_recommendations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('learner_id');
            $table->foreignId('content_id')->constrained('pal_contents')->cascadeOnDelete();
            $table->string('event_type')->nullable(); // recommended, viewed, completed
            $table->unsignedInteger('engagement_score')->default(0);
            $table->timestamps();

            $table->index(['learner_id', 'content_id']);
        });

        // 5. Telemetry
        Schema::create('pal_telemetry_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_id'); // learner_id
            $table->string('verb'); // experienced, answered, etc.
            $table->string('object_id')->nullable();
            $table->json('context_id')->nullable();
            $table->json('result')->nullable();
            $table->json('raw_statement')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->timestamp('timestamp')->useCurrent();
            $table->timestamps();

            $table->index(['actor_id', 'verb']);
        });

        // 6. Pedagogy & Reflection
        Schema::create('pal_reflections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('learner_id');
            $table->string('prompt_type')->nullable();
            $table->text('learner_response');
            $table->unsignedTinyInteger('quality_score')->default(0);
            $table->json('context_data')->nullable();
            $table->timestamps();

            $table->index('learner_id');
        });

        Schema::create('pal_pedagogy_effectiveness', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('learner_id');
            $table->string('pedagogy_type');
            $table->string('outcome')->nullable();
            $table->float('effectiveness_score')->default(0);
            $table->timestamps();

            $table->index(['learner_id', 'pedagogy_type']);
        });

        Schema::create('pal_learner_preferences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('learner_id');
            $table->string('pref_key');
            $table->text('pref_value')->nullable();
            $table->timestamps();

            $table->unique(['learner_id', 'pref_key']);
        });

        // 7. Learner State Snapshots
        Schema::create('pal_learner_states', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('learner_id');
            $table->foreignId('session_id')->nullable()->constrained('pal_learning_sessions')->nullOnDelete();
            $table->json('competency_state')->nullable();
            $table->json('behavioral_state')->nullable();
            $table->json('motivational_state')->nullable();
            $table->json('social_state')->nullable();
            $table->json('contextual_state')->nullable();
            $table->json('metacognition_state')->nullable();
            $table->float('disengagement_risk')->default(0);
            $table->timestamps();

            $table->index('learner_id');
        });

        // 8. Behavioral / Social / Metacognition tables
        Schema::create('pal_collaboration_activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('learner_id');
            $table->string('activity_type')->nullable();
            $table->unsignedInteger('peer_count')->default(0);
            $table->float('engagement_score')->default(0);
            $table->timestamps();

            $table->index('learner_id');
        });

        Schema::create('pal_classroom_activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('learner_id');
            $table->float('participation_score')->default(0);
            $table->timestamps();

            $table->index('learner_id');
        });

        Schema::create('pal_discussions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('learner_id');
            $table->text('content')->nullable();
            $table->unsignedInteger('upvotes')->default(0);
            $table->timestamps();

            $table->index('learner_id');
        });

        Schema::create('pal_group_activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('learner_id');
            $table->float('performance_score')->default(0);
            $table->timestamps();

            $table->index('learner_id');
        });

        Schema::create('pal_self_corrections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('learner_id');
            $table->boolean('successful')->default(false);
            $table->timestamps();

            $table->index('learner_id');
        });

        Schema::create('pal_learning_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('learner_id');
            $table->json('plan_data')->nullable();
            $table->timestamps();

            $table->index('learner_id');
        });

        Schema::create('pal_strategy_selections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('learner_id');
            $table->string('strategy_type');
            $table->timestamps();

            $table->index(['learner_id', 'strategy_type']);
        });

        Schema::create('pal_learning_journals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('learner_id');
            $table->text('entry')->nullable();
            $table->timestamps();

            $table->index('learner_id');
        });

        // 9. Additional analytics tables referenced in engines
        Schema::create('pal_learning_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('learner_id');
            $table->string('event_type');
            $table->json('event_data')->nullable();
            $table->timestamps();

            $table->index(['learner_id', 'event_type']);
        });

        Schema::create('pal_learning_patterns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('learner_id');
            $table->string('pattern_type');
            $table->json('pattern_data')->nullable();
            $table->float('confidence')->default(0);
            $table->timestamps();

            $table->index('learner_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pal_learning_patterns');
        Schema::dropIfExists('pal_learning_events');
        Schema::dropIfExists('pal_learning_journals');
        Schema::dropIfExists('pal_strategy_selections');
        Schema::dropIfExists('pal_learning_plans');
        Schema::dropIfExists('pal_self_corrections');
        Schema::dropIfExists('pal_group_activities');
        Schema::dropIfExists('pal_discussions');
        Schema::dropIfExists('pal_classroom_activities');
        Schema::dropIfExists('pal_collaboration_activities');
        Schema::dropIfExists('pal_learner_states');
        Schema::dropIfExists('pal_learner_preferences');
        Schema::dropIfExists('pal_pedagogy_effectiveness');
        Schema::dropIfExists('pal_reflections');
        Schema::dropIfExists('pal_telemetry_events');
        Schema::dropIfExists('pal_content_recommendations');
        Schema::dropIfExists('pal_contents');
        Schema::dropIfExists('pal_remediation_sessions');
        Schema::dropIfExists('pal_remediations');
        Schema::dropIfExists('pal_learner_misconceptions');
        Schema::dropIfExists('pal_misconceptions');
        Schema::dropIfExists('pal_assessment_results');
        Schema::dropIfExists('pal_session_events');
        Schema::dropIfExists('pal_learning_sessions');
        Schema::dropIfExists('pal_competencies');
        Schema::dropIfExists('pal_concepts');
        Schema::dropIfExists('pal_subjects');
    }
};