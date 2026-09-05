<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * G2G LMS (Package 1) â€” `lms_course_discussions` + `lms_course_discussion_replies`,
 * ported 1:1 from hp_erp's `2026_07_28_120000_create_lms_course_discussions_table.php`.
 * A thread scoped to a course (optionally to one lesson) and its replies â€” no
 * nested threading, deliberately kept simple.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lms_course_discussions')) {
            Schema::create('lms_course_discussions', function (Blueprint $table) {
                $table->bigIncrements('id');

                $table->unsignedInteger('course_id')->index();
                $table->unsignedBigInteger('chapter_id')->nullable()->index();
                $table->unsignedBigInteger('content_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->index();

                $table->string('title', 191)->nullable();
                $table->text('message');
                $table->boolean('is_instructor')->default(false);
                $table->boolean('is_resolved')->default(false);

                $table->unsignedBigInteger('sub_institute_id')->nullable()->index();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->unsignedBigInteger('deleted_by')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->index(['course_id', 'created_at'], 'lms_discussions_course_created_idx');
            });
        }

        if (! Schema::hasTable('lms_course_discussion_replies')) {
            Schema::create('lms_course_discussion_replies', function (Blueprint $table) {
                $table->bigIncrements('id');

                $table->unsignedBigInteger('discussion_id')->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->text('message');
                $table->boolean('is_instructor')->default(false);

                $table->unsignedBigInteger('sub_institute_id')->nullable()->index();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->unsignedBigInteger('deleted_by')->nullable();

                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_course_discussion_replies');
        Schema::dropIfExists('lms_course_discussions');
    }
};
