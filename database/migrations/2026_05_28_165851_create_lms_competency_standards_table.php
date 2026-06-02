<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('lms_competency_standards', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->integer('curriculum_id');
            $table->integer('standard_id');
            $table->integer('subject_id');
            $table->integer('chapter_id');
            $table->integer('parent_id')->nullable();
            $table->string('code', 20);
            $table->enum('type', ['goal', 'competency']);
            $table->text('description');
            
            $table->unique(['curriculum_id', 'code'], 'uq_learning_outcomes');
            
            $table->foreign('parent_id')
                  ->references('id')
                  ->on('lms_learning_outcomes')
                  ->onDelete('cascade');
                  
            $table->foreign('curriculum_id')
                  ->references('id')
                  ->on('lms_curriculum')
                  ->onDelete('cascade');
        });
        
        // Set charset and collation
        DB::statement('ALTER TABLE lms_competency_standards ENGINE = InnoDB');
        DB::statement('ALTER TABLE lms_competency_standards DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lms_competency_standards');
    }
};