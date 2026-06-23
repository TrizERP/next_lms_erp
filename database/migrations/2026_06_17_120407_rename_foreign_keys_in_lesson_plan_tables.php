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
        // 1. Rename column in lms_lesson_plan_periods
        Schema::table('lms_lesson_plan_periods', function (Blueprint $table) {
            $table->dropForeign(['lesson_plan_id']);
            $table->dropIndex('idx_calendar');
            $table->dropIndex('idx_plan_date');
            $table->dropIndex('idx_week');
            
            $table->renameColumn('lesson_plan_id', 'lms_intelligence_lesson_plans_id');
        });

        Schema::table('lms_lesson_plan_periods', function (Blueprint $table) {
            $table->foreign('lms_intelligence_lesson_plans_id', 'fk_lms_intel_lesson_plans')
                  ->references('id')
                  ->on('lms_intelligence_lesson_plans')
                  ->onDelete('cascade');
                  
            $table->index(['scheduled_date', 'lms_intelligence_lesson_plans_id'], 'idx_calendar');
            $table->index(['lms_intelligence_lesson_plans_id', 'scheduled_date'], 'idx_plan_date');
            $table->index(['lms_intelligence_lesson_plans_id', 'week_number'], 'idx_week');
        });

        // 2. Rename column in lms_lesson_plan_concepts
        Schema::table('lms_lesson_plan_concepts', function (Blueprint $table) {
            $table->dropForeign(['lesson_plan_period_id']);
            $table->dropIndex('idx_period_concepts');
            $table->dropUnique('uk_period_concept');
            
            $table->renameColumn('lesson_plan_period_id', 'lms_lesson_plan_periods_id');
        });

        Schema::table('lms_lesson_plan_concepts', function (Blueprint $table) {
            $table->foreign('lms_lesson_plan_periods_id', 'fk_lms_intel_lesson_periods')
                  ->references('id')
                  ->on('lms_lesson_plan_periods')
                  ->onDelete('cascade');
                  
            $table->index('lms_lesson_plan_periods_id', 'idx_period_concepts');
            $table->unique(['lms_lesson_plan_periods_id', 'concept_id'], 'uk_period_concept');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Revert lms_lesson_plan_concepts
        Schema::table('lms_lesson_plan_concepts', function (Blueprint $table) {
            $table->dropForeign('fk_lms_intel_lesson_periods');
            $table->dropIndex('idx_period_concepts');
            $table->dropUnique('uk_period_concept');
            
            $table->renameColumn('lms_lesson_plan_periods_id', 'lesson_plan_period_id');
        });

        Schema::table('lms_lesson_plan_concepts', function (Blueprint $table) {
            $table->foreign('lesson_plan_period_id')
                  ->references('id')
                  ->on('lms_lesson_plan_periods')
                  ->onDelete('cascade');
                  
            $table->index('lesson_plan_period_id', 'idx_period_concepts');
            $table->unique(['lesson_plan_period_id', 'concept_id'], 'uk_period_concept');
        });

        // Revert lms_lesson_plan_periods
        Schema::table('lms_lesson_plan_periods', function (Blueprint $table) {
            $table->dropForeign('fk_lms_intel_lesson_plans');
            $table->dropIndex('idx_calendar');
            $table->dropIndex('idx_plan_date');
            $table->dropIndex('idx_week');
            
            $table->renameColumn('lms_intelligence_lesson_plans_id', 'lesson_plan_id');
        });

        Schema::table('lms_lesson_plan_periods', function (Blueprint $table) {
            $table->foreign('lesson_plan_id')
                  ->references('id')
                  ->on('lms_intelligence_lesson_plans')
                  ->onDelete('cascade');
                  
            $table->index(['scheduled_date', 'lesson_plan_id'], 'idx_calendar');
            $table->index(['lesson_plan_id', 'scheduled_date'], 'idx_plan_date');
            $table->index(['lesson_plan_id', 'week_number'], 'idx_week');
        });
    }
};
