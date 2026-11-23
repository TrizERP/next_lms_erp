<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 3D Virtual Lab (Application Lab) only: one row per student, chapter and lab.
 * `progress` holds the stage, prediction, saved world, interaction log,
 * notebook, answers and conclusion so a student can resume exactly where they
 * stopped. Nothing else in PAL reads or writes this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pal_virtual_lab_session')) {
            return;
        }

        Schema::create('pal_virtual_lab_session', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('sub_institute_id')->default(0);
            $table->unsignedBigInteger('chapter_id');
            $table->string('lab_id', 40);
            // in_progress -> submitted -> evaluated
            $table->string('status', 20)->default('in_progress');
            $table->string('stage', 20)->default('intro');
            $table->longText('progress')->nullable();
            $table->longText('evaluation')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('evaluated_at')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'chapter_id', 'lab_id'], 'pal_vlab_student_chapter_lab');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pal_virtual_lab_session');
    }
};
