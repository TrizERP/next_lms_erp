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
        Schema::create('lms_lesson_plan_concepts', function (Blueprint $table) {
            $table->id();
            
            // FK: lesson_plan_periods.id
            $table->unsignedBigInteger('lms_lesson_plan_periods_id');
            $table->integer('concept_id');
            $table->string('concept_name', 255);
            
            // COVERAGE DETAILS
            $table->boolean('is_primary')->default(false);
            $table->integer('coverage_percent')->default(0);
            
            $table->json('knowledge_items_covered')->nullable();
            
            // TIMESTAMPS
            $table->timestamp('created_at')->useCurrent();
            
            // FOREIGN KEYS
            $table->foreign('lms_lesson_plan_periods_id')
                  ->references('id')
                  ->on('lms_lesson_plan_periods')
                  ->onDelete('cascade');
                  
            // INDEXES
            $table->index('concept_id', 'idx_concept_lookup');
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
        Schema::dropIfExists('lms_lesson_plan_concepts');
    }
};
