<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Learner must finish course X before starting course Y." ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â G2G LMS
 * migration (Package 3). Ported as-is from hp_erp's
 * `2026_07_29_090001_create_lms_course_prerequisites_table.php`.
 *
 * A join table rather than a column, so a course can list several
 * prerequisites and be queried from both ends: what blocks this course
 * (the builder), and what completing it unlocks (enrolment eligibility).
 * Both `course_id` and `prerequisite_course_id` reference `sub_std_map.id`
 * (existing, read-only here).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lms_course_prerequisites')) {
            return;
        }
Schema::create('lms_course_prerequisites', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('course_id');
            $table->unsignedInteger('prerequisite_course_id');
            $table->unsignedBigInteger('sub_institute_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['course_id', 'prerequisite_course_id'], 'lms_course_prereq_unique');
            $table->index('prerequisite_course_id', 'lms_course_prereq_target_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_course_prerequisites');
    }
};
