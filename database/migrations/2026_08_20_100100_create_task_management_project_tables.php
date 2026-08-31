<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task Management migration, stage 1 of 2 (migrations + models only).
 *
 * Ported from hp_erp's `2026_07_28_000000_create_task_management_project_tables.php`.
 * Table/column names are kept identical to the source so the ported frontend
 * and controllers (stage 2) need no renaming.
 *
 * Conventions follow this repo's TalentManagement precedent
 * (`2026_08_18_112000_create_talent_performance_tables.php`): bigIncrements
 * PK, indexed unsignedBigInteger sub_institute_id (mandatory, no default),
 * loose unsignedBigInteger(...)->index() joins with NO foreign key
 * constraints, enum-like fields as string with an inline comment + default +
 * index, created_by/updated_by as nullable indexed unsignedBigInteger,
 * timestamps() + softDeletes() on normal tables, named composite/unique
 * indexes to avoid MySQL's 64-char auto-generated name limit.
 *
 * Task Management is academic-year-scoped (unlike TalentManagement), so a
 * `syear` column is added wherever the source schema carries SYEAR/syear.
 *
 * `regulatory_flags` is stored as longText rather than native JSON, matching
 * the TalentManagement precedent's JSON-ish-payload convention.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('task_management_projects')) {
            Schema::create('task_management_projects', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('code', 30);
                $table->string('name', 191);
                $table->string('category', 50)->nullable();
                $table->text('description')->nullable();
                $table->unsignedBigInteger('department_id')->nullable()->index();
                $table->unsignedBigInteger('sponsor_id')->nullable()->index();
                $table->unsignedBigInteger('manager_id')->nullable()->index();
                $table->string('team_size', 20)->nullable();
                $table->string('priority', 20)->default('Medium')->index(); // Low | Medium | High | Critical
                $table->string('status', 30)->default('PLANNING')->index(); // PLANNING | ACTIVE | ON_HOLD | COMPLETED | ARCHIVED
                $table->date('start_date')->nullable();
                $table->date('due_date')->nullable();
                $table->decimal('budget_estimate', 15, 2)->nullable();
                $table->string('client_name', 191)->nullable();
                // JSON payload of regulatory/compliance flags, stored as text
                // for compatibility with MariaDB versions without native JSON.
                $table->longText('regulatory_flags')->nullable();
                $table->unsignedBigInteger('sub_institute_id')->index();
                $table->unsignedInteger('syear')->index();
                $table->unsignedBigInteger('created_by')->index();
                $table->unsignedBigInteger('updated_by')->nullable()->index();
                $table->unsignedBigInteger('deleted_by')->nullable();
                $table->timestamp('archived_at')->nullable()->index();
                $table->unsignedBigInteger('archived_by')->nullable()->index();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['sub_institute_id', 'code'], 'tm_projects_tenant_code_unique');
            });
        }

        if (! Schema::hasTable('task_management_project_members')) {
            Schema::create('task_management_project_members', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('project_id')->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('role', 30)->default('MEMBER')->index(); // MEMBER | LEAD | OBSERVER
                $table->unsignedBigInteger('sub_institute_id')->index();
                $table->unsignedInteger('syear')->index();
                $table->unsignedBigInteger('created_by')->index();
                $table->timestamps();

                $table->unique(['project_id', 'user_id'], 'tm_project_member_unique');
            });
        }

        if (! Schema::hasTable('task_management_workstreams')) {
            Schema::create('task_management_workstreams', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('project_id')->index();
                $table->string('name', 191);
                $table->text('description')->nullable();
                $table->unsignedBigInteger('owner_id')->nullable()->index();
                $table->string('status', 30)->default('PLANNING')->index(); // PLANNING | ACTIVE | ON_HOLD | COMPLETED
                $table->date('start_date')->nullable();
                $table->date('due_date')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->unsignedBigInteger('sub_institute_id')->index();
                $table->unsignedInteger('syear')->index();
                $table->unsignedBigInteger('created_by')->index();
                $table->unsignedBigInteger('updated_by')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('task_management_project_tasks')) {
            Schema::create('task_management_project_tasks', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('project_id')->index();
                $table->unsignedBigInteger('workstream_id')->nullable()->index();
                $table->unsignedBigInteger('task_id')->index();
                $table->unsignedBigInteger('sub_institute_id')->index();
                $table->unsignedInteger('syear')->index();
                $table->unsignedBigInteger('created_by')->index();
                $table->timestamps();

                $table->unique(['project_id', 'task_id'], 'tm_project_task_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('task_management_project_tasks');
        Schema::dropIfExists('task_management_workstreams');
        Schema::dropIfExists('task_management_project_members');
        Schema::dropIfExists('task_management_projects');
    }
};
