<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which competencies a course develops — ported from hp_erp's
 * `course_competency_map` (`App\Http\Controllers\Api\Competency\
 * CourseCompetencyMapController`).
 *
 * Feeds the Course Builder's "Capabilities this course builds" panel
 * (`CourseCompetencyInlinePanel`), which was imported by the original port
 * of `create-course-page.tsx` and never rendered because no table/endpoint
 * existed here yet.
 *
 * `lms_course_competency_effectiveness` (hp_erp's measured-vs-declared
 * comparison, LEFT joined by `index()`) does not exist in this package and
 * is not created here — the panel only needs the declared mapping; the
 * measured side is a separate, larger analytics feature out of this scope.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('course_competency_map')) {
            return;
        }

        Schema::create('course_competency_map', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->unsignedBigInteger('course_id')->index();
            $table->unsignedBigInteger('competency_id')->index();
            $table->unsignedTinyInteger('proficiency_level')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['course_id', 'competency_id'], 'uq_course_competency');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_competency_map');
    }
};
