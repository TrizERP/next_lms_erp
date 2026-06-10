<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('lessonplan', function (Blueprint $table) {

            // Lesson date
            if (!Schema::hasColumn('lessonplan', 'lesson_date')) {
                $table->date('lesson_date')->nullable();
            }

            // Period number
            if (!Schema::hasColumn('lessonplan', 'period_number')) {
                $table->integer('period_number')->nullable();
            }

            // Status
            if (!Schema::hasColumn('lessonplan', 'status')) {
                $table->string('status')->nullable()->default('pending');
            }

            // Pedagogy tag
            if (!Schema::hasColumn('lessonplan', 'pedagogy_tag')) {
                $table->string('pedagogy_tag')->nullable();
            }

            // Engagement rating
            if (!Schema::hasColumn('lessonplan', 'engagement_rating')) {
                $table->float('engagement_rating')->nullable();
            }

            // Completed at
            if (!Schema::hasColumn('lessonplan', 'completed_at')) {
                $table->dateTime('completed_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lessonplan', function (Blueprint $table) {

            if (Schema::hasColumn('lessonplan', 'lesson_date')) {
                $table->dropColumn('lesson_date');
            }

            if (Schema::hasColumn('lessonplan', 'period_number')) {
                $table->dropColumn('period_number');
            }

            if (Schema::hasColumn('lessonplan', 'status')) {
                $table->dropColumn('status');
            }

            if (Schema::hasColumn('lessonplan', 'pedagogy_tag')) {
                $table->dropColumn('pedagogy_tag');
            }

            if (Schema::hasColumn('lessonplan', 'engagement_rating')) {
                $table->dropColumn('engagement_rating');
            }

            if (Schema::hasColumn('lessonplan', 'completed_at')) {
                $table->dropColumn('completed_at');
            }
        });
    }
};