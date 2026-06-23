<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // ============================================
        // 1. STUDENT INTELLIGENCE TABLES
        // ============================================
        
        // Student Learning Profile
        Schema::create('lms_student_profile', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->string('learning_style')->nullable(); // visual, auditory, kinesthetic, reading
            $table->string('learning_pace')->nullable(); // slow, medium, fast
            $table->string('motivation_level')->nullable(); // high, medium, low
            $table->decimal('attention_span', 5, 2)->default(0);
            $table->json('preferred_content_types')->nullable(); // video, text, interactive, audio
            $table->json('strengths')->nullable();
            $table->json('weaknesses')->nullable();
            $table->json('interests')->nullable();
            $table->timestamps();
        });

        // Forgetting Curve Data
        Schema::create('lms_forgetting_curve', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('concept_id');
            $table->decimal('retention_rate', 5, 2)->default(100);
            $table->timestamp('last_reviewed_at');
            $table->timestamp('next_review_at');
            $table->integer('review_interval_days')->default(1);
            $table->integer('review_count')->default(0);
            $table->timestamps();
        });

        // Student Engagement Log
        Schema::create('lms_student_engagement', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->string('activity_type'); // quiz, practice, content_view, video_watch
            $table->unsignedBigInteger('activity_id')->nullable();
            $table->integer('time_spent_seconds')->default(0);
            $table->integer('interaction_count')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // ============================================
        // 2. TEACHER INTELLIGENCE TABLES
        // ============================================
        
        // Class Insights
        Schema::create('lms_class_insights', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('teacher_id');
            $table->unsignedBigInteger('standard_id');
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('chapter_id')->nullable();
            $table->string('insight_type'); // struggling_students, mastered_concepts, common_misconceptions
            $table->json('data');
            $table->json('recommendations')->nullable();
            $table->timestamp('generated_at');
            $table->timestamps();
        });

        // Teacher Interventions
        Schema::create('lms_teacher_interventions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('teacher_id');
            $table->unsignedBigInteger('student_id')->nullable();
            $table->unsignedBigInteger('concept_id')->nullable();
            $table->string('intervention_type'); // remedial, enrichment, peer_tutoring, parent_contact
            $table->text('description');
            $table->text('action_plan')->nullable();
            $table->string('status')->default('pending'); // pending, in_progress, completed
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // Peer Tutoring Groups
        Schema::create('lms_peer_tutoring_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('teacher_id');
            $table->unsignedBigInteger('concept_id');
            $table->unsignedBigInteger('tutor_student_id');
            $table->json('tutee_student_ids');
            $table->string('status')->default('active'); // active, completed
            $table->timestamp('created_at');
            $table->timestamp('completed_at')->nullable();
        });

        // ============================================
        // 3. CONTENT INTELLIGENCE TABLES
        // ============================================
        
        // Knowledge Graph (Prerequisites)
        Schema::create('lms_knowledge_graph', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('concept_id');
            $table->unsignedBigInteger('prerequisite_concept_id');
            $table->string('relationship_type')->default('requires'); // requires, recommends, conflicts
            $table->integer('strength')->default(1);
            $table->timestamps();
        });

        // Content Recommendations
        Schema::create('lms_content_recommendations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('concept_id')->nullable();
            $table->unsignedBigInteger('content_id');
            $table->string('content_type'); // video, document, interactive, assessment
            $table->string('recommendation_reason');
            $table->integer('priority')->default(0);
            $table->string('status')->default('pending'); // pending, viewed, completed, skipped
            $table->timestamp('recommended_at');
            $table->timestamp('viewed_at')->nullable();
            $table->timestamps();
        });

        // Content Metadata
        Schema::create('lms_content_metadata', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('content_id');
            $table->string('content_type'); // video, document, interactive, assessment
            $table->string('difficulty_level'); // easy, medium, hard
            $table->integer('estimated_duration_minutes')->default(5);
            $table->string('pedagogy_type')->nullable(); // demonstration, explanation, practice, exploration
            $table->json('prerequisite_content_ids')->nullable();
            $table->json('tags')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('lms_content_metadata');
        Schema::dropIfExists('lms_content_recommendations');
        Schema::dropIfExists('lms_knowledge_graph');
        Schema::dropIfExists('lms_peer_tutoring_groups');
        Schema::dropIfExists('lms_teacher_interventions');
        Schema::dropIfExists('lms_class_insights');
        Schema::dropIfExists('lms_student_engagement');
        Schema::dropIfExists('lms_forgetting_curve');
        Schema::dropIfExists('lms_student_profile');
    }
};