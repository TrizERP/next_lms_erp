<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Course recommendations surfaced against an employee (a skill/task gap, or ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â
 * per X-13 in hp_erp ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â an expiring certification). G2G LMS migration
 * (Package 3).
 *
 * Combines hp_erp's `2026_01_30_120036_create_suggested_course.php` plus its
 * four follow-up migrations into ONE clean create with only the columns
 * confirmed actually live/used there, per the task brief:
 *
 *   - `skill_id` was added by the original create, then DROPPED by
 *     `2026_02_02_061152_rename_skill_id_to_task_id_in_suggested_course_table.php`
 *     ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â a misleading filename: that migration only ever calls
 *     `dropColumn('skill_id')`. It never creates `task_id`.
 *   - `task_id` is referenced defensively by
 *     `2026_08_11_000700_suggested_course_task_optional.php` (which checks
 *     `Schema::hasColumn` before doing anything), but no migration in hp_erp
 *     ever adds it. Per the task brief this is a confirmed inconsistency in
 *     the source history, not something to reproduce ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â `task_id` is defined
 *     here directly, nullable (never NOT NULL, matching the intent of that
 *     "make it optional" migration).
 *
 * `course_id` references `sub_std_map.id` (existing, read-only here).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('suggested_course')) {
            return;
        }
Schema::create('suggested_course', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('employee_id');
            $table->unsignedInteger('course_id');
            $table->string('course_name');
            // Nullable: a suggestion need not come from a task (e.g. an
            // expiring certification). See class doc-comment.
            $table->unsignedBigInteger('task_id')->nullable();

            $table->unsignedBigInteger('sub_institute_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suggested_course');
    }
};
