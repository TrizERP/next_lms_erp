<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * G2G LMS (Package 1) â€” `lms_content_progress`, ported 1:1 from hp_erp's
 * `2026_07_28_110000_create_lms_content_progress_table.php`. One row per
 * (user, content_master item); course_id/chapter_id are denormalised so the
 * course-level percentage can be computed without walking content_master.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lms_content_progress')) {
            return;
        }

        Schema::create('lms_content_progress', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('user_id')->index();
            // sub_std_map.id - the course, matching lms_course_enroll.course_id.
            $table->unsignedInteger('course_id')->index();
            $table->unsignedBigInteger('chapter_id')->nullable()->index();
            $table->unsignedBigInteger('content_id')->index();

            $table->enum('status', ['not-started', 'in-progress', 'completed'])
                  ->default('not-started')
                  ->index();

            $table->unsignedInteger('last_position_seconds')->nullable();
            $table->unsignedInteger('time_spent_seconds')->default(0);
            $table->timestamp('completed_at')->nullable();

            $table->unsignedBigInteger('sub_institute_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'content_id'], 'lms_content_progress_user_content_unique');
            $table->index(['user_id', 'course_id'], 'lms_content_progress_user_course_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_content_progress');
    }
};
