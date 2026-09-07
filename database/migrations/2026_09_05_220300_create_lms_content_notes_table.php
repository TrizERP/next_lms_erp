<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * G2G LMS (Package 1) â€” `lms_content_notes`, ported 1:1 from hp_erp's
 * `2026_07_28_110001_create_lms_content_notes_table.php`. Notes are private
 * to their author; content_id/timestamp_seconds are nullable so a note can
 * attach to the course generally, to one lesson, or to a moment in a video.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lms_content_notes')) {
            return;
        }

        Schema::create('lms_content_notes', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedInteger('course_id')->index();
            $table->unsignedBigInteger('chapter_id')->nullable()->index();
            $table->unsignedBigInteger('content_id')->nullable()->index();

            $table->text('note');
            $table->unsignedInteger('timestamp_seconds')->nullable();

            $table->unsignedBigInteger('sub_institute_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'course_id'], 'lms_content_notes_user_course_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_content_notes');
    }
};
