<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ported from G2G's
 * `2026_07_29_110000_create_competency_certification_requirements_table.php`.
 *
 * Certification requirements - the policy layer behind the Certification &
 * Compliance Center ("certification X is required for job role Y /
 * department Z"). Drives the "Add Certification Requirement" action, the
 * Requirements + Compliance side-panel tabs and the Compliant Employees KPI.
 *
 * Conventions match the sibling competency migrations: bigIncrements PK,
 * indexed sub_institute_id, loose joins (jobrole string / department_id int)
 * with no FK constraints, string status, audit columns + soft deletes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('s_competency_certification_requirements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->string('name', 191);
            // Industry Certification | Internal Certification | Regulatory | License | Vendor
            $table->string('certification_type', 100)->nullable()->index();
            $table->string('issuing_body', 191)->nullable();
            $table->text('description')->nullable();

            // Scope: who the requirement applies to. All nullable - a row scoped
            // to neither is an organisation-wide requirement.
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->string('jobrole', 191)->nullable()->index();
            $table->unsignedBigInteger('competency_id')->nullable()->index(); // s_users_skills.id

            $table->boolean('is_mandatory')->default(true)->index();
            $table->unsignedSmallInteger('validity_months')->nullable();
            $table->unsignedSmallInteger('renewal_reminder_days')->default(60);
            $table->unsignedSmallInteger('grace_period_days')->nullable();

            // active | archived
            $table->string('status', 30)->default('active')->index();

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('s_competency_certification_requirements');
    }
};
