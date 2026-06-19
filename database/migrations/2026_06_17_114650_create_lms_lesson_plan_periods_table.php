<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lms_lesson_plan_periods', function (Blueprint $table) {
            $table->id();
            
            // FK: lesson_plans.id (Updated to lms_intelligence_lesson_plans)
            $table->unsignedBigInteger('lms_intelligence_lesson_plans_id');
            
            // WHEN (Calendar View)
            $table->date('scheduled_date');
            $table->char('week_day', 1);
            $table->integer('week_number');
            $table->unsignedInteger('period_id');
            $table->string('period_slot', 10);
            
            // WHO (Teacher View)
            $table->unsignedInteger('teacher_id');
            
            // WHAT (Content View)
            $table->integer('chapter_id')->nullable();
            $table->string('chapter_name', 255)->nullable();
            $table->integer('primary_concept_id')->nullable();
            $table->string('primary_concept_name', 255)->nullable();
            
            // HOW (The Lesson Plan Content)
            $table->enum('period_type', ['teaching', 'assessment', 'revision', 'activity', 'lab', 'project', 'buffer'])
                  ->default('teaching');
            $table->json('plan_json')->nullable();
            
            // INTELLIGENCE METADATA
            $table->string('blooms_level', 20)->nullable();
            $table->tinyInteger('dok_level')->nullable();
            $table->string('pedagogy_method', 100)->nullable();
            $table->string('difficulty_level', 20)->nullable();
            $table->json('learning_objectives')->nullable();
            $table->json('learning_outcomes_mapped')->nullable();
            
            // PLANNED DURATION
            $table->integer('planned_duration_min')->default(40);
            
            // TEACHER TRACKING (updated by teacher after class)
            $table->enum('status', ['not_started', 'in_progress', 'completed', 'skipped', 'rescheduled'])
                  ->default('not_started');
            $table->integer('actual_duration_min')->nullable();
            $table->tinyInteger('engagement_rating')->nullable();
            $table->integer('completion_percent')->nullable();
            $table->text('teacher_notes')->nullable();
            $table->dateTime('completed_at')->nullable();
            
            // RESCHEDULING
            $table->date('original_date')->nullable();
            $table->string('reschedule_reason', 255)->nullable();
            
            // TIMESTAMPS
            $table->timestamps();
            
            // FOREIGN KEYS
            $table->foreign('lms_intelligence_lesson_plans_id', 'fk_lms_intel_lesson_plans')
                  ->references('id')
                  ->on('lms_intelligence_lesson_plans')
                  ->onDelete('cascade');
                  
            // INDEXES
            $table->index(['scheduled_date', 'lms_intelligence_lesson_plans_id'], 'idx_calendar');
            $table->index(['teacher_id', 'scheduled_date', 'status'], 'idx_teacher');
            $table->index('chapter_id', 'idx_chapter');
            $table->index('primary_concept_id', 'idx_concept');
            $table->index(['status', 'scheduled_date'], 'idx_status');
            $table->index(['lms_intelligence_lesson_plans_id', 'scheduled_date'], 'idx_plan_date');
            $table->index(['lms_intelligence_lesson_plans_id', 'week_number'], 'idx_week');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('lms_lesson_plan_periods');
    }
};
