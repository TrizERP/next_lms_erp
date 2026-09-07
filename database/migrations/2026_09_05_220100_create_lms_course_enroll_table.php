<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * G2G LMS (Package 1) â€” `lms_course_enroll`, ported from hp_erp's
 * `2026_07_28_000000_create_lms_course_enroll_table.php` (backdated CREATE
 * for a table that predates any migration on that project).
 *
 * Differences from the hp_erp source, both deliberate:
 *   - UNIQUE (user_id, course_id): hp_erp's live table has none, and live
 *     data has duplicate pairs from a `store()` with no dedupe â€” this is a
 *     NEW table on a NEW database, so there is no legacy data to violate the
 *     constraint, and the task explicitly calls out not repeating that bug.
 *     `EnrolmentWriter`-equivalent write paths in Package 1 controllers use
 *     "does this pair already exist" checks precisely because of this.
 *   - FK `course_id` -> `sub_std_map.id`, per the task's DB plan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lms_course_enroll')) {
            // sub_std_map.id is INT UNSIGNED in the existing ERP schema.
            DB::statement('ALTER TABLE `lms_course_enroll` MODIFY `course_id` INT UNSIGNED NOT NULL');

            $foreignKeyExists = DB::table('information_schema.KEY_COLUMN_USAGE')
                ->where('TABLE_SCHEMA', DB::raw('DATABASE()'))
                ->where('TABLE_NAME', 'lms_course_enroll')
                ->where('COLUMN_NAME', 'course_id')
                ->whereNotNull('REFERENCED_TABLE_NAME')
                ->exists();

            if (! $foreignKeyExists) {
                Schema::table('lms_course_enroll', function (Blueprint $table) {
                    $table->foreign('course_id')->references('id')->on('sub_std_map')
                        ->onUpdate('NO ACTION')->onDelete('NO ACTION');
                });
            }
            return;
        }

        Schema::create('lms_course_enroll', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sub_institute_id');
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedInteger('course_id')->index();
            $table->string('status', 20)->default('enrolled')->index();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'course_id'], 'lms_course_enroll_user_course_unique');
            $table->index(['sub_institute_id', 'user_id', 'course_id'], 'lms_course_enroll_lookup');

            $table->foreign('course_id')
                ->references('id')->on('sub_std_map')
                ->onUpdate('NO ACTION')
                ->onDelete('NO ACTION');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_course_enroll');
    }
};
