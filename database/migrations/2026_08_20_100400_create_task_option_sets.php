<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task Management migration, stage 1 of 2 (migrations + models only).
 *
 * Ported from hp_erp's `2026_08_05_100000_create_task_option_sets.php`.
 * Per-tenant custom statuses and priorities for the task module.
 *
 * The four system statuses (PENDING / IN-PROGRESS / ON HOLD / COMPLETED) stay
 * the workflow engine: boards, summaries, approvals and the transition rules
 * all key off them, and task.status keeps storing them. A custom status is a
 * tenant-defined LABEL mapped onto one of those categories - "In Review" can
 * live on top of ON HOLD without breaking a single existing query. The label
 * a task was given is kept on task.status_label, added in
 * `..._add_planning_columns_to_task_table.php` (guarded here too, in case
 * these migrations ever run out of order).
 *
 * Priorities are simpler: task.task_type already stores a free string in
 * hp_erp; here a custom priority is just a validated, ordered name with an
 * optional SLA, independent of that column.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('task_management_statuses')) {
            Schema::create('task_management_statuses', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('sub_institute_id')->index();
                $table->string('name', 100);
                // Which system status this label behaves as.
                $table->string('category', 20)->index(); // PENDING | IN-PROGRESS | ON HOLD | COMPLETED
                $table->string('color', 30)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('active')->default(true)->index();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();

                $table->unique(['sub_institute_id', 'name'], 'tm_statuses_tenant_name_unique');
            });
        }

        if (! Schema::hasTable('task_management_priorities')) {
            Schema::create('task_management_priorities', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('sub_institute_id')->index();
                $table->string('name', 100);
                // Lower sorts first; system High/Medium/Low occupy 10/20/30.
                $table->unsignedInteger('sort_order')->default(50);
                $table->unsignedInteger('sla_hours')->nullable();
                $table->boolean('active')->default(true)->index();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();

                $table->unique(['sub_institute_id', 'name'], 'tm_priorities_tenant_name_unique');
            });
        }

        if (! Schema::hasColumn('task', 'status_label')) {
            Schema::table('task', function (Blueprint $table) {
                // The tenant's display label for the status; null means the
                // system category name itself.
                $table->string('status_label', 100)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('task_management_priorities');
        Schema::dropIfExists('task_management_statuses');

        // status_label is owned by ..._add_planning_columns_to_task_table.php;
        // only drop it here if that migration never ran (defensive symmetry
        // with the hasColumn guard in up()).
    }
};
