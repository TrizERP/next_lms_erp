<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-pilot readiness — cohort / Arm A-B assignment for the two-arm pilot
 * design in docs/CHAPTER_1014_PILOT_MEASUREMENT_PLAN.md.
 *
 * The ONLY new table this pass introduces for pilot measurement — every
 * metric is computed by reading existing tables (eso_decision_log for Arm B,
 * lms_online_exam/suggested_content for Arm A) joined through this one, per
 * the measurement plan's §4/§10. No event table, no analytics platform.
 *
 * Unique (student_id, chapter_id) is the arm-isolation guarantee: a student
 * has exactly one arm per chapter for the life of the pilot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pal_pilot_enrollments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('sub_institute_id');
            $table->unsignedBigInteger('chapter_id');
            $table->string('arm', 1); // 'A' | 'B'
            $table->string('cohort_label', 64)->nullable();
            $table->string('status', 16)->default('active'); // active | withdrawn | completed
            $table->timestamp('enrolled_at');
            $table->unsignedBigInteger('enrolled_by')->nullable(); // staff user id, nullable for console/system enrollment

            $table->timestamps();

            $table->unique(['student_id', 'chapter_id'], 'ppe_student_chapter_unique');
            $table->index(['chapter_id', 'arm', 'status'], 'ppe_chapter_arm_status_idx');
            $table->index('cohort_label', 'ppe_cohort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pal_pilot_enrollments');
    }
};
