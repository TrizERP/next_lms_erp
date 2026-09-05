<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assessment campaigns/cycles for G2G-LMS Assessments (the Assessment
 * Workspace's campaigns table + calendar bar).
 *
 * Ported from hp_erp's `s_competency_assessment_cycles` table - the
 * assessment_cycles portion of `2026_07_28_100100_create_competency_module_tables.php`
 * ONLY (frameworks/framework_items are out of scope for this package), plus
 * the columns added by `2026_07_29_130100_add_type_to_competency_assessment_cycles.php`
 * (`type`) and `2026_08_02_150000_add_framework_id_to_competency_assessment_cycles.php`
 * (`framework_id`).
 *
 * `framework_id` is a plain indexed bigint with NO FK: `s_competency_frameworks`
 * does not exist in this codebase and is not an approved table for this
 * package, so it is carried purely as an opaque id (a campaign may record
 * which framework it targets without this schema being able to resolve a
 * name for it - `AssessmentCycleController::index()` returns
 * `framework_name: null` accordingly).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('s_competency_assessment_cycles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            // scheduled | active | closed
            $table->string('status', 30)->default('scheduled')->index();
            // Assessment type label (e.g. "Self + Manager"). Nullable - a
            // cycle with no type set makes no claim about one.
            $table->string('type', 100)->nullable();
            // Plain indexed bigint, no FK - see class docblock.
            $table->unsignedBigInteger('framework_id')->nullable()->index();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('s_competency_assessment_cycles');
    }
};
