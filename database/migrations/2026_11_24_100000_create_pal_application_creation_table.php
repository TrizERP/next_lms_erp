<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Application Lab, activity 2 (student-created demonstration) only. One row per
 * creation. Until this is applied the activity keeps the same record in the cache.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pal_application_creation')) {
            return;
        }

        Schema::create('pal_application_creation', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('sub_institute_id')->default(0);
            $table->unsignedBigInteger('chapter_id');
            // in_progress -> submitted -> evaluated
            $table->string('status', 20)->default('in_progress');
            // The scope the student chose (some concepts, or the whole chapter): one creation per scope.
            $table->string('scope_key', 32)->default('');
            $table->text('scope')->nullable();
            $table->string('concept', 200)->nullable();
            $table->longText('creation')->nullable();
            $table->longText('evaluation')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('evaluated_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'chapter_id', 'scope_key', 'id'], 'pal_appcreate_student_chapter');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pal_application_creation');
    }
};
