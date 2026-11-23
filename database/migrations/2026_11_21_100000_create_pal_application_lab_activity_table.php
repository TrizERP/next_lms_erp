<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Application Lab only: one row per generated activity per student.
 * Holds the activity, the student's working copy, the submission and the AI
 * evaluation. Nothing else in PAL reads or writes this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pal_application_lab_activity')) {
            return;
        }

        Schema::create('pal_application_lab_activity', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('sub_institute_id')->default(0);
            $table->unsignedBigInteger('chapter_id');
            // draft = generated, not started; in_progress; submitted; evaluated
            $table->string('status', 20)->default('draft');
            $table->string('mode', 30)->nullable();
            $table->string('title', 255)->nullable();
            $table->json('activity');
            $table->json('response')->nullable();
            $table->json('evaluation')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('evaluated_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'chapter_id', 'id'], 'pal_applab_student_chapter');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pal_application_lab_activity');
    }
};
