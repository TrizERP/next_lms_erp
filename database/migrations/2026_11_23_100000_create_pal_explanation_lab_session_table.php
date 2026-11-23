<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Explanation Lab only: one row per challenge a student has been given for a
 * chapter. `challenge` holds the generated task AND its answer key (the key is
 * never sent to the browser). Until this is applied the lab keeps the same
 * record in the cache.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pal_explanation_lab_session')) {
            return;
        }

        Schema::create('pal_explanation_lab_session', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('sub_institute_id')->default(0);
            $table->unsignedBigInteger('chapter_id');
            // in_progress -> submitted -> evaluated
            $table->string('status', 20)->default('in_progress');
            $table->longText('challenge');
            $table->longText('workspace')->nullable();
            $table->longText('evaluation')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('evaluated_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'chapter_id', 'id'], 'pal_explab_student_chapter');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pal_explanation_lab_session');
    }
};
