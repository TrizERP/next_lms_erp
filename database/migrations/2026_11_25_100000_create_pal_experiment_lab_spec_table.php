<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versioned lab specifications for the Application Lab experiment builder.
 *
 * One row per version of a lab. A version is never edited once saved: changing a lab saves a new version in
 * "draft", and only a teacher/admin approving it makes it live, so an approved version stays what students see
 * until its replacement is approved. Students never read a row that is not "approved" (or "retired", for a student
 * who started before it was replaced).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pal_experiment_lab_spec')) {
            return;
        }

        Schema::create('pal_experiment_lab_spec', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('lab_key', 32);
            $table->unsignedInteger('version');
            $table->unsignedBigInteger('chapter_id');
            // 0 = every school; otherwise the school that authored it.
            $table->unsignedBigInteger('sub_institute_id')->default(0);
            // draft | approved | retired | rejected
            $table->string('status', 16)->default('draft');
            // builtin | ai | teacher
            $table->string('source', 16)->default('teacher');
            $table->longText('spec');
            $table->string('spec_hash', 40);
            $table->longText('validation')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->string('reject_reason', 300)->nullable();
            $table->timestamps();

            $table->unique(['lab_key', 'sub_institute_id', 'version'], 'pal_labspec_key_version');
            $table->index(['chapter_id', 'status', 'sub_institute_id'], 'pal_labspec_chapter_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pal_experiment_lab_spec');
    }
};
