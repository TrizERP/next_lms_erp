<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Build with AI" ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â saved outlines and their rendered Gamma decks. G2G LMS
 * migration (Package 3).
 *
 * Combines hp_erp's `2025_12_12_112543_create_ai_course_outlines_table.php`
 * and `2026_07_28_100000_add_generation_fields_to_ai_course_outlines_table.php`
 * into ONE clean create, deliberately NOT reproducing the source's data
 * quality bug: the original migration calls `dropColumn('status')` on a
 * table that never had a `status` column yet in that same `up()`, then
 * re-adds it as an `enum('status', ['completed','Incompleted'])` ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â and the
 * follow-up migration adds a SECOND `status` column
 * (`varchar(20) default 'draft'`) on top of that. This migration defines
 * exactly one well-typed `status` column instead.
 *
 * No foreign keys to `school_setup` / `tbluser` (the source added these):
 * this repo's shared tables for those concepts differ per-tenant setup, and
 * every sibling G2G-ported table in this migration set stays FK-free against
 * `sub_std_map`/`tbluser` for the same reason the task brief gives for
 * `enrollment_id` on `lms_certificates`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_course_outlines')) {
            return;
        }
Schema::create('ai_course_outlines', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('course_type', 255);
            $table->longText('input_fields');
            $table->longText('configure_fields');
            $table->longText('outline');

            $table->string('presentation_platform', 50)->nullable();
            $table->string('ai_model', 100)->nullable();
            $table->unsignedInteger('slide_count')->nullable();
            $table->string('generation_id', 191)->nullable()->index();
            $table->text('gamma_url')->nullable();
            $table->text('export_url')->nullable();

            // pending/completed/failed mirror Gamma's own statuses; draft is a
            // saved outline with no deck rendered yet. ONE column, correctly
            // typed ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â see the class doc-comment for why.
            $table->string('status', 20)->default('draft');

            // Set once the outline has been turned into a catalogue course.
            $table->unsignedInteger('course_id')->nullable()->index();

            $table->unsignedBigInteger('sub_institute_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_course_outlines');
    }
};
