<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ported from G2G's `2026_07_28_100100_create_competency_module_tables.php`.
 *
 * Only the tables this port actually needs are recreated here:
 * `s_competency_certifications`, `s_competency_development_plans` and
 * `s_competency_activity_log`. The source migration also created
 * `s_competency_frameworks`, `_framework_items`, `_assessment_cycles` and
 * `_assessments` - those back the Competency Assessment Cycle / Framework
 * Studio features, which are explicitly out of scope for this port (only
 * Employee Profiles, Certifications and Development & Career Paths were
 * migrated), so they are not created here.
 *
 * Verified absent from this schema before writing this migration (grepped
 * `database/migrations` for `s_competency_`).
 *
 * Conventions match the source: bigIncrements PK, indexed
 * unsignedBigInteger sub_institute_id, nullable indexed audit columns,
 * timestamps + softDeletes, string-based status enums, loose joins
 * (jobrole string / department_id int) with NO foreign-key constraints.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Certifications ------------------------------------------------------
        Schema::create('s_competency_certifications', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->string('name', 191);
            $table->unsignedBigInteger('user_id')->nullable()->index();   // employee (tbluser.id)
            $table->unsignedBigInteger('competency_id')->nullable()->index();
            $table->string('issuing_body', 191)->nullable();
            $table->string('credential_id', 191)->nullable();
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->string('jobrole', 191)->nullable();
            // valid | expiring | expired | revoked
            $table->string('status', 30)->default('valid')->index();
            $table->date('issued_date')->nullable();
            $table->date('expiry_date')->nullable()->index();
            $table->unsignedBigInteger('created_by')->index()->nullable();
            $table->unsignedBigInteger('updated_by')->index()->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Development plans ----------------------------------------------------
        Schema::create('s_competency_development_plans', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->string('title', 191);
            $table->unsignedBigInteger('user_id')->nullable()->index();   // employee (tbluser.id)
            $table->unsignedBigInteger('competency_id')->nullable()->index();
            $table->unsignedBigInteger('framework_id')->nullable()->index();
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->string('jobrole', 191)->nullable();
            // active | completed | overdue | on_hold
            $table->string('status', 30)->default('active')->index();
            $table->unsignedTinyInteger('progress')->default(0);          // 0..100
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable()->index();
            $table->dateTime('completed_at')->nullable();
            $table->unsignedBigInteger('approver_id')->nullable()->index();
            // null | pending_approval | approved | rejected
            $table->string('approval_status', 30)->nullable()->index();
            $table->unsignedBigInteger('mentor_id')->nullable();
            $table->unsignedBigInteger('created_by')->index()->nullable();
            $table->unsignedBigInteger('updated_by')->index()->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Activity log (drives the Recent Activity / audit feeds) -------------
        Schema::create('s_competency_activity_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();   // actor (tbluser.id)
            $table->string('actor_name', 191)->nullable();
            $table->string('action', 191);
            $table->text('description')->nullable();
            $table->string('subject_type', 100)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('s_competency_activity_log');
        Schema::dropIfExists('s_competency_development_plans');
        Schema::dropIfExists('s_competency_certifications');
    }
};
