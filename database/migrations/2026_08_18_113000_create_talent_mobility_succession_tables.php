<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ported from G2G's `2026_08_01_100000_create_internal_mobility_tables`
 * (Talent Management -> Internal Mobility & Succession Center).
 *
 * Same table names, same column names/types as the source. Only the audit
 * columns follow this project's convention: `$table->id()` instead of
 * `bigIncrements`, and no FK constraints (joins are convention-based only,
 * matching every other migration in this project).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Internal Job Vacancies
        Schema::create('s_mobility_jobs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->string('job_id', 100)->unique();
            $table->string('title', 191);
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->string('department', 191)->nullable();
            $table->string('location', 191);
            $table->string('grade', 50);
            $table->string('job_type', 50)->default('Permanent'); // Permanent | Contract | Temporary
            $table->date('posted_on');
            $table->date('deadline')->nullable();
            $table->unsignedBigInteger('hiring_manager_id')->nullable()->index();
            $table->string('hiring_manager_name', 191)->nullable();
            $table->string('current_incumbent', 191)->nullable();
            $table->unsignedInteger('vacancies')->default(1);
            $table->string('relocation_required', 50)->default('No'); // Yes | No | Case-by-case
            $table->text('description')->nullable();
            $table->string('status', 30)->default('Open')->index(); // Open | In Review | Closed
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Internal Job Applications
        Schema::create('s_mobility_applications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->unsignedBigInteger('job_posting_id')->index(); // s_mobility_jobs.id
            $table->unsignedBigInteger('user_id')->index(); // applicant (tbluser.id)
            $table->date('applied_on');
            $table->string('status', 30)->default('Applied')->index(); // Applied | Screening | Interviewing | Offered | Rejected | Closed
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        // 3. Internal Transfers
        Schema::create('s_mobility_transfers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->unsignedBigInteger('user_id')->index(); // employee (tbluser.id)
            $table->unsignedBigInteger('from_department_id')->nullable()->index();
            $table->string('from_department', 191)->nullable();
            $table->unsignedBigInteger('to_department_id')->nullable()->index();
            $table->string('to_department', 191)->nullable();
            $table->string('from_jobrole', 191)->nullable();
            $table->string('to_jobrole', 191)->nullable();
            $table->date('effective_date');
            $table->string('status', 30)->default('Pending')->index(); // Pending | Approved | Completed | Cancelled
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        // 4. Internal Promotions
        Schema::create('s_mobility_promotions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->unsignedBigInteger('user_id')->index(); // employee (tbluser.id)
            $table->string('current_grade', 50)->nullable();
            $table->string('proposed_grade', 50)->nullable();
            $table->string('current_designation', 191)->nullable();
            $table->string('proposed_designation', 191)->nullable();
            $table->date('effective_date');
            $table->string('status', 30)->default('Pending')->index(); // Pending | Approved | Completed | Cancelled
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        // 5. Succession Plans
        Schema::create('s_mobility_succession_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->unsignedBigInteger('critical_jobrole_id')->nullable()->index(); // s_user_jobrole.id
            $table->string('critical_jobrole_name', 191);
            $table->unsignedBigInteger('successor_user_id')->index(); // tbluser.id
            $table->string('readiness', 50)->default('Ready Now'); // Ready Now | Ready in 1-2 Years | Ready in 2+ Years
            $table->boolean('emergency_successor')->default(false);
            $table->string('status', 30)->default('Active')->index(); // Active | Inactive
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        // 6. Talent Pools
        Schema::create('s_mobility_talent_pools', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->string('status', 30)->default('Active')->index(); // Active | Inactive
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        // 7. Talent Pool Members
        Schema::create('s_mobility_talent_pool_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pool_id')->index(); // s_mobility_talent_pools.id
            $table->unsignedBigInteger('user_id')->index(); // tbluser.id
            $table->date('added_on');
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('s_mobility_talent_pool_members');
        Schema::dropIfExists('s_mobility_talent_pools');
        Schema::dropIfExists('s_mobility_succession_plans');
        Schema::dropIfExists('s_mobility_promotions');
        Schema::dropIfExists('s_mobility_transfers');
        Schema::dropIfExists('s_mobility_applications');
        Schema::dropIfExists('s_mobility_jobs');
    }
};
