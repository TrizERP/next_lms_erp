<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether passing this course's quiz writes the mapped competency rating
 * without a review step. Off by default — see `CourseBuilderController`'s
 * settings docblock for what this switch does and does not do yet (the
 * write-on-pass runtime logic itself belongs to the quiz-scoring pipeline,
 * which this package does not have; this column only lets an author record
 * the intent, ready for that pipeline to read once it lands).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('lms_course_settings', 'auto_apply_rating')) {
            return;
        }

        Schema::table('lms_course_settings', function (Blueprint $table) {
            $table->boolean('auto_apply_rating')->default(false)->after('recert_alerts');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('lms_course_settings', 'auto_apply_rating')) {
            Schema::table('lms_course_settings', function (Blueprint $table) {
                $table->dropColumn('auto_apply_rating');
            });
        }
    }
};
