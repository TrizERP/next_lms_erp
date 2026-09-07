<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One participant's assessment record within a campaign, for G2G-LMS
 * Assessments.
 *
 * Ported from hp_erp's `s_competency_assessments` table (same source
 * migration as the cycles table - the assessments/campaign-record portion
 * ONLY). `cycle_id` FKs (no hard constraint, matching this package's
 * convention) into this package's own `s_competency_assessment_cycles`
 * (previous migration). `framework_id` is a plain indexed bigint with no FK,
 * same reasoning as the cycles table - `s_competency_frameworks` is out of
 * scope for this package.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('s_competency_assessments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->string('title', 191);
            // Plain indexed bigint, no FK - s_competency_frameworks is out of scope.
            $table->unsignedBigInteger('framework_id')->nullable()->index();
            $table->unsignedBigInteger('cycle_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();     // employee assessed (tbluser.id)
            $table->unsignedBigInteger('assessor_id')->nullable();          // tbluser.id of assessor
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->string('jobrole', 191)->nullable();
            // open | in_progress | completed | overdue
            $table->string('status', 30)->default('open')->index();
            // null | pending_review | reviewed
            $table->string('review_status', 30)->nullable()->index();
            $table->decimal('score', 5, 2)->nullable();
            $table->date('due_date')->nullable();
            $table->dateTime('completed_at')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('s_competency_assessments');
    }
};
