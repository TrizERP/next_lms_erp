<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Course Builder authoring settings ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â G2G LMS migration (Package 3).
 *
 * Combines hp_erp's `2026_07_29_090000_create_lms_course_settings_table.php`
 * and `2026_09_02_190000_add_sequential_unlock_to_lms_course_settings.php`
 * into one clean create.
 *
 * Kept as its own table rather than columns on `sub_std_map` because that
 * table is shared far beyond the LMS (competency, jobrole library,
 * enrolment); one row per course, joined only when the builder or a course
 * detail view needs it. `course_id` references `sub_std_map.id` (existing,
 * read-only here).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lms_course_settings')) {
            return;
        }
Schema::create('lms_course_settings', function (Blueprint $table) {
            $table->id();
            // sub_std_map.id ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â one settings row per course.
            $table->unsignedInteger('course_id')->unique();
            $table->boolean('sequential_unlock')->default(false);
            $table->unsignedBigInteger('sub_institute_id')->nullable()->index();

            // Step 1 - basic information
            $table->text('description')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->string('language', 50)->nullable();
            $table->boolean('is_mandatory')->default(false);
            $table->boolean('discussion_enabled')->default(false);
            // 'all' or 'restricted'. Distinct from sub_std_map.status (published or not).
            $table->string('visibility', 20)->default('all');

            // Step 3 - assessment rules that apply across the whole course
            $table->unsignedTinyInteger('passing_score')->nullable();
            $table->unsignedInteger('max_attempts')->nullable();

            // Step 4 - certification
            $table->boolean('issue_certificate')->default(true);
            $table->string('certificate_template', 50)->nullable();
            $table->boolean('recert_alerts')->default(false);

            // Step 5 - publish settings
            $table->string('enrollment_rule', 20)->default('open');
            // JSON arrays of hrms_departments.id and jobrole names.
            $table->longText('restrict_departments')->nullable();
            $table->longText('restrict_roles')->nullable();
            $table->date('available_from')->nullable();
            $table->date('available_until')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_course_settings');
    }
};
