<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Job-role audience-assignment records for the LMS Course Builder.
 *
 * Ported from hp_erp's `2026_08_07_100000_phase3_foundation_join_tables.php`
 * (source of `course_jobrole_map`). `App\Http\Controllers\G2gLms\
 * CourseBuilderController::assignAudience()` already `insertOrIgnore`s into
 * this table for every `jobrole_id` in an audience-assignment payload,
 * guarded by `lmsTableExists('course_jobrole_map')` — it was silently
 * no-op-ing because this table didn't exist yet. No controller change is
 * needed: creating the table activates the existing write path.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('course_jobrole_map')) {
            return;
        }

        Schema::create('course_jobrole_map', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->unsignedBigInteger('course_id')->index();
            $table->unsignedBigInteger('jobrole_id')->index();
            $table->timestamps();

            $table->unique(['course_id', 'jobrole_id'], 'uq_cjm');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_jobrole_map');
    }
};
