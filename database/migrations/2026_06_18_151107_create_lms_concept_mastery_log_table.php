<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Concept Mastery Log
        Schema::create('lms_concept_mastery_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('concept_id');
            $table->decimal('mastery_level', 5, 2)->default(0);
            $table->integer('total_attempts')->default(0);
            $table->integer('correct_attempts')->default(0);
            $table->timestamps();
        });

        // Concept Mastery
        Schema::create('lms_concept_mastery', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('concept_id');
            $table->decimal('mastery_level', 5, 2)->default(0);
            $table->timestamp('mastered_at')->nullable();
            $table->timestamps();
        });

        // Practice Sessions
        Schema::create('lms_practice_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->string('session_id');
            $table->string('strategy_type');
            $table->integer('total_questions');
            $table->integer('correct_answers')->default(0);
            $table->integer('wrong_answers')->default(0);
            $table->decimal('score', 5, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('lms_practice_sessions');
        Schema::dropIfExists('lms_concept_mastery');
        Schema::dropIfExists('lms_concept_mastery_log');
    }
};
