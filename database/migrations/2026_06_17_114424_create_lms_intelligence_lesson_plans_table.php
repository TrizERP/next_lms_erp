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
        Schema::create('lms_intelligence_lesson_plans', function (Blueprint $table) {
            $table->id();
            
            // SCHOOL IDENTITY
            $table->integer('sub_institute_id');
            $table->integer('syear');
            $table->integer('term_id');
            $table->integer('standard_id');
            $table->integer('subject_id');
            $table->integer('division_id')->nullable();
            
            // PLAN METADATA
            $table->string('plan_title', 255);
            $table->date('term_start_date');
            $table->date('term_end_date');
            
            // CAPACITY ANALYSIS
            $table->integer('total_teaching_days')->default(0);
            $table->integer('total_periods')->default(0);
            $table->integer('periods_per_week')->default(0);
            $table->integer('period_duration_min')->default(40);
            $table->integer('total_teaching_min')->default(0);
            $table->integer('total_required_min')->default(0);
            $table->decimal('buffer_percent', 5, 2)->default(0.00);
            $table->integer('holidays_count')->default(0);
            $table->integer('exam_days_count')->default(0);
            
            // PLAN CONTENT
            $table->json('macro_plan_json')->nullable();
            
            // GENERATION STATUS
            $table->enum('generation_status', ['pending', 'generating', 'completed', 'failed', 'regenerating'])->default('pending');
            $table->integer('generation_progress')->default(0);
            $table->text('generation_error')->nullable();
            $table->dateTime('generated_at')->nullable();
            $table->string('generated_by', 100)->nullable();
            
            // TIMESTAMPS
            $table->timestamps();
            
            // INDEXES
            $table->unique(['sub_institute_id', 'syear', 'term_id', 'standard_id', 'subject_id', 'division_id'], 'uk_plan');
            $table->index(['sub_institute_id', 'syear'], 'idx_school_year');
            $table->index(['standard_id', 'subject_id'], 'idx_standard_subject');
            $table->index('generation_status', 'idx_generation_status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('lms_intelligence_lesson_plans');
    }
};
